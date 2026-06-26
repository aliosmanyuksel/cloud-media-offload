<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Offloader {

    const META_OFFLOADED = '_r2mo_offloaded';

    /** Pure: map {relative_path => absolute_path} for an attachment. */
    public static function map_files($basedir, $attached_file, $metadata) {
        $basedir  = rtrim($basedir, '/');
        $base_dir = dirname($attached_file);
        $rel_pre  = ($base_dir === '.' || $base_dir === '') ? '' : $base_dir . '/';
        $abs_pre  = $basedir . '/' . $rel_pre;

        $files = [];
        $files[$attached_file] = $basedir . '/' . $attached_file;

        if (!empty($metadata['original_image'])) {
            $files[$rel_pre . $metadata['original_image']] = $abs_pre . $metadata['original_image'];
        }
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size) {
                if (empty($size['file'])) {
                    continue;
                }
                $files[$rel_pre . $size['file']] = $abs_pre . $size['file'];
            }
        }
        return $files;
    }

    /** Pure: prepend a key prefix, normalizing slashes. */
    public static function build_key($prefix, $relative) {
        $relative = ltrim($relative, '/');
        $prefix   = trim($prefix, '/');
        return $prefix !== '' ? $prefix . '/' . $relative : $relative;
    }

    /* ---------------- WordPress glue ---------------- */

    private function client() {
        $s = R2MO_Settings::get();
        return new R2MO_S3_Client(R2MO_Provider::config_from_settings($s));
    }

    private function ready($s) {
        if ($s['provider'] === 'r2') {
            return $s['account_id'] !== '' && $s['access_key'] !== '' && $s['secret_key'] !== '' && $s['bucket'] !== '';
        }
        return $s['endpoint'] !== '' && $s['access_key'] !== '' && $s['secret_key'] !== '' && $s['bucket'] !== '';
    }

    /** Build the relative=>absolute map for a stored attachment. */
    public function attachment_files($attachment_id, $metadata = null) {
        $file = get_post_meta($attachment_id, '_wp_attached_file', true);
        if (!$file) {
            return [];
        }
        if ($metadata === null) {
            $metadata = wp_get_attachment_metadata($attachment_id);
        }
        $upload = wp_get_upload_dir();
        return self::map_files($upload['basedir'], $file, is_array($metadata) ? $metadata : []);
    }

    /**
     * Upload every file for an attachment, HEAD-verify, then mark offloaded.
     * @return array{ok:bool, uploaded:int, verified:int, msg:string}
     */
    public function offload_attachment($attachment_id, $metadata = null) {
        $s = R2MO_Settings::get();
        if (!$this->ready($s)) {
            return ['ok' => false, 'uploaded' => 0, 'verified' => 0, 'msg' => 'R2 ayarları eksik'];
        }

        $files = $this->attachment_files($attachment_id, $metadata);
        if (empty($files)) {
            return ['ok' => false, 'uploaded' => 0, 'verified' => 0, 'msg' => 'Dosya bulunamadı'];
        }

        $client   = $this->client();
        $uploaded = 0;
        $verified = 0;
        $keys     = [];

        foreach ($files as $relative => $absolute) {
            if (!file_exists($absolute)) {
                continue;
            }
            $key  = self::build_key($s['prefix'], $relative);
            $type = wp_check_filetype($absolute)['type'] ?: 'application/octet-stream';
            $put  = $client->put($key, $absolute, $type);
            if ($put['code'] < 200 || $put['code'] >= 300) {
                return ['ok' => false, 'uploaded' => $uploaded, 'verified' => $verified,
                        'msg' => "Yükleme hatası ({$key}): HTTP {$put['code']} {$put['error']}"];
            }
            $uploaded++;
            if (!$client->exists($key)) {
                return ['ok' => false, 'uploaded' => $uploaded, 'verified' => $verified,
                        'msg' => "Doğrulama başarısız ({$key}): R2'de bulunamadı"];
            }
            $verified++;
            $keys[] = [$relative, $absolute];
        }

        if ($uploaded === 0) {
            return ['ok' => false, 'uploaded' => 0, 'verified' => 0,
                    'msg' => 'Dosya bulunamadı (disk üzerinde yok)'];
        }

        update_post_meta($attachment_id, self::META_OFFLOADED, 1);

        if (!empty($s['delete_local'])) {
            foreach ($keys as [$relative, $absolute]) {
                if (file_exists($absolute)) {
                    @unlink($absolute);
                }
            }
        }

        return ['ok' => true, 'uploaded' => $uploaded, 'verified' => $verified,
                'msg' => "{$verified} dosya yüklendi ve doğrulandı"];
    }

    /** Filter callback on wp_generate_attachment_metadata (priority 9999). */
    public function offload_on_upload($metadata, $attachment_id) {
        $s = R2MO_Settings::get();
        if (!empty($s['auto_offload'])) {
            $result = $this->offload_attachment($attachment_id, $metadata);
            if ($result['ok']) {
                R2MO_Cache::flush();
            }
        }
        return $metadata;
    }

    /** Action callback on delete_attachment. */
    public function delete_from_r2($attachment_id) {
        if (!get_post_meta($attachment_id, self::META_OFFLOADED, true)) {
            return;
        }
        $s      = R2MO_Settings::get();
        $client = $this->client();
        foreach ($this->attachment_files($attachment_id) as $relative => $absolute) {
            $client->delete(self::build_key($s['prefix'], $relative));
        }
    }

    public function is_offloaded($attachment_id) {
        return (bool) get_post_meta($attachment_id, self::META_OFFLOADED, true);
    }
}
