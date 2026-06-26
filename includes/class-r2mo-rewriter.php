<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Rewriter {

    /** Pure: public delivery base = public_base [+ "/" + prefix]. */
    public static function target_base($public_base, $prefix) {
        $base   = rtrim((string) $public_base, '/');
        $prefix = trim((string) $prefix, '/');
        return $prefix !== '' ? $base . '/' . $prefix : $base;
    }

    /** Pure: swap every occurrence of the uploads base URL for the target. */
    public static function swap_base($subject, $baseurl, $target) {
        if ($baseurl === '' || $target === '') {
            return $subject;
        }
        return str_replace($baseurl, rtrim($target, '/'), $subject);
    }

    /* ---------------- WordPress glue ---------------- */

    private function active() {
        $s = R2MO_Settings::get();
        return !empty($s['public_base']) ? $s : null;
    }

    private function offloaded($attachment_id) {
        return (bool) get_post_meta($attachment_id, R2MO_Offloader::META_OFFLOADED, true);
    }

    /** Layer 1: per-attachment URL rewrite (always safe). */
    public function rewrite_url($url, $attachment_id) {
        $s = $this->active();
        if (!$s || !$this->offloaded($attachment_id)) {
            return $url;
        }
        $upload = wp_get_upload_dir();
        return self::swap_base($url, $upload['baseurl'], self::target_base($s['public_base'], $s['prefix']));
    }

    public function rewrite_image_src($image, $attachment_id, $size, $icon) {
        $s = $this->active();
        if (!$s || empty($image[0]) || !$this->offloaded($attachment_id)) {
            return $image;
        }
        $upload   = wp_get_upload_dir();
        $image[0] = self::swap_base($image[0], $upload['baseurl'], self::target_base($s['public_base'], $s['prefix']));
        return $image;
    }

    public function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        $s = $this->active();
        if (!$s || !$this->offloaded($attachment_id)) {
            return $sources;
        }
        $upload = wp_get_upload_dir();
        $target = self::target_base($s['public_base'], $s['prefix']);
        foreach ($sources as &$src) {
            $src['url'] = self::swap_base($src['url'], $upload['baseurl'], $target);
        }
        return $sources;
    }

    /** Layer 2: gated full-page output buffer. */
    public function maybe_start_buffer() {
        $s = $this->active();
        if (!$s || empty($s['fullpage_rewrite'])) {
            return;
        }
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || wp_doing_ajax() || is_feed()) {
            return;
        }
        ob_start([$this, 'buffer_callback']);
    }

    public function buffer_callback($html) {
        $s = $this->active();
        if (!$s) {
            return $html;
        }
        $upload = wp_get_upload_dir();
        return self::swap_base($html, $upload['baseurl'], self::target_base($s['public_base'], $s['prefix']));
    }
}
