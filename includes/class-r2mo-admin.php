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
        $s       = R2MO_Settings::get();
        $pending = $this->migrator->count_pending();
        ?>
        <div class="wrap r2mo">
            <h1>Kurulum Sihirbazı</h1>
            <div class="r2mo-steps">
                <span data-dot="1" class="on">1. Sağlayıcı</span>
                <span data-dot="2">2. Kimlik</span>
                <span data-dot="3">3. Test</span>
                <span data-dot="4">4. Seçenekler</span>
                <span data-dot="5">5. Bitir</span>
            </div>
            <form method="post" action="options.php" id="r2mo-wizard-form">
                <?php settings_fields('r2mo_group'); ?>

                <div class="r2mo-wizard-step active" data-step="1">
                    <h2>1. Depolama sağlayıcısını seçin</h2>
                    <p>
                        <label><input type="radio" name="<?php echo esc_attr($this->field_name('provider')); ?>" value="r2" <?php checked($s['provider'], 'r2'); ?>> Cloudflare R2 (önerilen — sıfır egress)</label><br>
                        <label><input type="radio" name="<?php echo esc_attr($this->field_name('provider')); ?>" value="generic" <?php checked($s['provider'], 'generic'); ?>> Genel S3-uyumlu (Wasabi, Backblaze B2, DigitalOcean, MinIO)</label>
                    </p>
                    <button type="button" class="button button-primary r2mo-next">İleri</button>
                </div>

                <div class="r2mo-wizard-step" data-step="2">
                    <h2>2. Kimlik bilgileri</h2>
                    <p class="description">Güvenlik için bu değerleri <code>wp-config.php</code> içinde sabit olarak da tanımlayabilirsiniz (örn. <code>R2MO_SECRET_KEY</code>); o zaman DB'deki değeri ezer.</p>
                    <table class="form-table" role="presentation">
                        <tr><th>Account ID (R2)</th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('account_id')); ?>" value="<?php echo esc_attr($s['account_id']); ?>"></td></tr>
                        <tr><th>Endpoint (genel)</th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('endpoint')); ?>" value="<?php echo esc_attr($s['endpoint']); ?>" placeholder="s3.us-west-1.wasabisys.com"></td></tr>
                        <tr><th>Region</th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('region')); ?>" value="<?php echo esc_attr($s['region']); ?>"></td></tr>
                        <tr><th>Access Key ID</th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('access_key')); ?>" value="<?php echo esc_attr($s['access_key']); ?>"></td></tr>
                        <tr><th>Secret Access Key</th><td><input type="password" class="regular-text" name="<?php echo esc_attr($this->field_name('secret_key')); ?>" value="<?php echo esc_attr($s['secret_key']); ?>"></td></tr>
                        <tr><th>Bucket</th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('bucket')); ?>" value="<?php echo esc_attr($s['bucket']); ?>"></td></tr>
                    </table>
                    <button type="button" class="button r2mo-prev">Geri</button>
                    <button type="button" class="button button-primary r2mo-next">İleri</button>
                </div>

                <div class="r2mo-wizard-step" data-step="3">
                    <h2>3. Bağlantıyı test edin</h2>
                    <p class="description">Önce ayarları kaydedin (alttaki "Kaydet"), ardından test edin.</p>
                    <button type="button" class="button" id="r2mo-wizard-test">Bağlantıyı test et</button>
                    <span id="r2mo-test-result" class="r2mo-test-result"></span>
                    <p style="margin-top:15px;">
                        <button type="button" class="button r2mo-prev">Geri</button>
                        <button type="button" class="button button-primary r2mo-next">İleri</button>
                    </p>
                </div>

                <div class="r2mo-wizard-step" data-step="4">
                    <h2>4. Sunum ve seçenekler</h2>
                    <table class="form-table" role="presentation">
                        <tr><th>Public Base URL</th><td><input type="url" class="regular-text" name="<?php echo esc_attr($this->field_name('public_base')); ?>" value="<?php echo esc_attr($s['public_base']); ?>" placeholder="https://cdn.example.com"></td></tr>
                        <tr><th>Key Prefix</th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('prefix')); ?>" value="<?php echo esc_attr($s['prefix']); ?>" placeholder="wp"></td></tr>
                        <tr><th>Otomatik offload</th><td><label><input type="checkbox" name="<?php echo esc_attr($this->field_name('auto_offload')); ?>" value="1" <?php checked($s['auto_offload']); ?>> Yeni yüklemeleri R2'ye gönder</label></td></tr>
                        <tr><th>Yerel kopyayı sil</th><td><label><input type="checkbox" name="<?php echo esc_attr($this->field_name('delete_local')); ?>" value="1" <?php checked($s['delete_local']); ?>> Doğruladıktan sonra sunucudan sil</label></td></tr>
                    </table>
                    <button type="button" class="button r2mo-prev">Geri</button>
                    <button type="button" class="button button-primary r2mo-next">İleri</button>
                </div>

                <div class="r2mo-wizard-step" data-step="5">
                    <h2>5. Bitir</h2>
                    <p>Ayarları kaydedin. Bekleyen <strong><?php echo (int) $pending; ?></strong> medya dosyası var.</p>
                    <p class="description">Kaydettikten sonra <a href="<?php echo esc_url(admin_url('admin.php?page=r2mo-migrate')); ?>">Migrasyon</a> sayfasından mevcut medyayı taşıyın. Migrasyon bittikten sonra Ayarlar'dan <em>Tam sayfa URL yeniden yazma</em>yı açın.</p>
                    <button type="button" class="button r2mo-prev">Geri</button>
                    <?php submit_button('Kaydet', 'primary', 'submit', false); ?>
                </div>
            </form>
        </div>
        <?php
    }

    public function render_migrate() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $pending = $this->migrator->count_pending();
        ?>
        <div class="wrap r2mo">
            <h1>R2 Migrasyon</h1>
            <p>R2'ye taşınmamış medya: <strong id="r2mo-pending"><?php echo (int) $pending; ?></strong></p>
            <button class="button button-primary" id="r2mo-start" data-total="<?php echo (int) $pending; ?>">Taşımayı başlat</button>
            <button class="button" id="r2mo-stop" disabled>Durdur</button>
            <div class="r2mo-bar-wrap"><div id="r2mo-bar" class="r2mo-bar">0%</div></div>
            <pre id="r2mo-log" class="r2mo-log"></pre>
        </div>
        <?php
    }
}
