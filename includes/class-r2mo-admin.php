<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Admin {

    /** @var R2MO_Migrator */
    private $migrator;

    public function __construct(R2MO_Migrator $migrator) {
        $this->migrator = $migrator;
    }

    public function register_settings() {
        R2MO_Settings::register();
    }

    public function menu() {
        add_menu_page(
            'R2 Media Offload', 'R2 Offload', 'manage_options', 'r2mo',
            [$this, 'render_settings'], 'dashicons-cloud', 81
        );
        add_submenu_page('r2mo', 'Ayarlar', 'Ayarlar', 'manage_options', 'r2mo', [$this, 'render_settings']);
        add_submenu_page('r2mo', 'Kurulum Sihirbazı', 'Kurulum Sihirbazı', 'manage_options', 'r2mo-wizard', [$this, 'render_wizard']);
        add_submenu_page('r2mo', 'Migrasyon', 'Migrasyon', 'manage_options', 'r2mo-migrate', [$this, 'render_migrate']);
    }

    public function enqueue($hook) {
        if (strpos($hook, 'r2mo') === false) {
            return;
        }
        wp_enqueue_style('r2mo-admin', R2MO_URL . 'assets/admin.css', [], R2MO_VERSION);
        wp_enqueue_script('r2mo-admin', R2MO_URL . 'assets/admin.js', [], R2MO_VERSION, true);
        wp_localize_script('r2mo-admin', 'R2MO', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(R2MO_Migrator::NONCE),
        ]);
    }

    private function field_name($key) {
        return R2MO_Settings::OPTION . '[' . $key . ']';
    }

    private function locked_note($key) {
        return R2MO_Settings::is_constant($key)
            ? ' <em>(wp-config ile yönetiliyor)</em>'
            : '';
    }

    public function render_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = R2MO_Settings::get();
        ?>
        <div class="wrap r2mo">
            <h1>R2 Media Offload</h1>
            <form method="post" action="options.php">
                <?php settings_fields('r2mo_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th>Sağlayıcı</th><td>
                        <select name="<?php echo esc_attr($this->field_name('provider')); ?>">
                            <option value="r2" <?php selected($s['provider'], 'r2'); ?>>Cloudflare R2</option>
                            <option value="generic" <?php selected($s['provider'], 'generic'); ?>>Genel S3-uyumlu</option>
                        </select>
                    </td></tr>
                    <tr><th>Account ID (R2)<?php echo $this->locked_note('account_id'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('account_id')); ?>" value="<?php echo esc_attr($s['account_id']); ?>">
                    </td></tr>
                    <tr><th>Endpoint (genel)<?php echo $this->locked_note('endpoint'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('endpoint')); ?>" value="<?php echo esc_attr($s['endpoint']); ?>" placeholder="s3.us-west-1.wasabisys.com">
                    </td></tr>
                    <tr><th>Region<?php echo $this->locked_note('region'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('region')); ?>" value="<?php echo esc_attr($s['region']); ?>">
                        <p class="description">R2 için <code>auto</code>.</p>
                    </td></tr>
                    <tr><th>Path-style</th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('path_style')); ?>" value="1" <?php checked($s['path_style']); ?>> Path-style URL kullan (R2 ve çoğu S3-uyumlu için açık)</label>
                    </td></tr>
                    <tr><th>Access Key ID<?php echo $this->locked_note('access_key'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('access_key')); ?>" value="<?php echo esc_attr($s['access_key']); ?>">
                    </td></tr>
                    <tr><th>Secret Access Key<?php echo $this->locked_note('secret_key'); ?></th><td>
                        <input type="password" class="regular-text" name="<?php echo esc_attr($this->field_name('secret_key')); ?>" value="<?php echo esc_attr($s['secret_key']); ?>">
                    </td></tr>
                    <tr><th>Bucket<?php echo $this->locked_note('bucket'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('bucket')); ?>" value="<?php echo esc_attr($s['bucket']); ?>">
                    </td></tr>
                    <tr><th>Public Base URL<?php echo $this->locked_note('public_base'); ?></th><td>
                        <input type="url" class="regular-text" name="<?php echo esc_attr($this->field_name('public_base')); ?>" value="<?php echo esc_attr($s['public_base']); ?>" placeholder="https://cdn.example.com">
                        <p class="description">Custom domain veya r2.dev adresi. URL yeniden yazma bunu kullanır.</p>
                    </td></tr>
                    <tr><th>Key Prefix</th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('prefix')); ?>" value="<?php echo esc_attr($s['prefix']); ?>" placeholder="wp">
                    </td></tr>
                    <tr><th>Otomatik offload</th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('auto_offload')); ?>" value="1" <?php checked($s['auto_offload']); ?>> Yeni yüklemeleri R2'ye gönder</label>
                    </td></tr>
                    <tr><th>Tam sayfa URL yeniden yazma</th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('fullpage_rewrite')); ?>" value="1" <?php checked($s['fullpage_rewrite']); ?>> Çıktı tamponuyla tüm sayfadaki yerel medya URL'lerini çevir</label>
                        <p class="description"><strong>Yalnızca migrasyon tamamlandıktan (0 bekleyen) sonra açın</strong> — aksi halde henüz taşınmamış görseller 404 verebilir.</p>
                    </td></tr>
                    <tr><th>Yerel kopyayı sil</th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('delete_local')); ?>" value="1" <?php checked($s['delete_local']); ?>> R2'ye yükleyip doğruladıktan sonra sunucudan sil</label>
                    </td></tr>
                    <tr><th>Batch boyutu</th><td>
                        <input type="number" min="1" max="200" name="<?php echo esc_attr($this->field_name('batch_size')); ?>" value="<?php echo esc_attr($s['batch_size']); ?>">
                    </td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2>Bağlantı testi</h2>
            <button class="button" id="r2mo-test">Bağlantıyı test et</button>
            <span id="r2mo-test-result" class="r2mo-test-result"></span>
        </div>
        <?php
    }

    public function render_wizard() {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap r2mo"><h1>Kurulum Sihirbazı</h1><p>Bu sayfa Task 11\'de doldurulacak.</p></div>';
    }

    public function render_migrate() {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap r2mo"><h1>Migrasyon</h1><p>Bu sayfa Task 12\'de doldurulacak.</p></div>';
    }
}
