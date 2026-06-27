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
            __('Cloud Media Offload', 'cloud-media-offload'), __('Cloud Offload', 'cloud-media-offload'), 'manage_options', 'r2mo',
            [$this, 'render_settings'], 'dashicons-cloud', 81
        );
        add_submenu_page('r2mo', __('Settings', 'cloud-media-offload'), __('Settings', 'cloud-media-offload'), 'manage_options', 'r2mo', [$this, 'render_settings']);
        add_submenu_page('r2mo', __('Setup Wizard', 'cloud-media-offload'), __('Setup Wizard', 'cloud-media-offload'), 'manage_options', 'r2mo-wizard', [$this, 'render_wizard']);
        add_submenu_page('r2mo', __('Migration', 'cloud-media-offload'), __('Migration', 'cloud-media-offload'), 'manage_options', 'r2mo-migrate', [$this, 'render_migrate']);
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
            'i18n' => [
                'testing'    => __('Testing…', 'cloud-media-offload'),
                'error'      => __('Error:', 'cloud-media-offload'),
                'connError'  => __('Connection error:', 'cloud-media-offload'),
                'started'    => __('Started…', 'cloud-media-offload'),
                'stopped'    => __('Stopped.', 'cloud-media-offload'),
                'completed'  => __('Completed. Remaining:', 'cloud-media-offload'),
                'failPrefix' => __('ERROR:', 'cloud-media-offload'),
            ],
        ]);
    }

    private function field_name($key) {
        return R2MO_Settings::OPTION . '[' . $key . ']';
    }

    private function locked_note($key) {
        return R2MO_Settings::is_constant($key)
            ? ' <em>' . __('(managed via wp-config)', 'cloud-media-offload') . '</em>'
            : '';
    }

    public function render_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = R2MO_Settings::get();
        ?>
        <div class="wrap r2mo">
            <h1><?php esc_html_e('Cloud Media Offload', 'cloud-media-offload'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('r2mo_group'); ?>
                <table class="form-table" role="presentation">
                    <tr><th><?php esc_html_e('Provider', 'cloud-media-offload'); ?></th><td>
                        <select name="<?php echo esc_attr($this->field_name('provider')); ?>">
                            <option value="r2" <?php selected($s['provider'], 'r2'); ?>><?php esc_html_e('Cloudflare R2', 'cloud-media-offload'); ?></option>
                            <option value="generic" <?php selected($s['provider'], 'generic'); ?>><?php esc_html_e('Generic S3-compatible', 'cloud-media-offload'); ?></option>
                        </select>
                    </td></tr>
                    <tr><th><?php esc_html_e('Account ID (R2)', 'cloud-media-offload'); ?><?php echo $this->locked_note('account_id'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('account_id')); ?>" value="<?php echo esc_attr($s['account_id']); ?>">
                    </td></tr>
                    <tr><th><?php esc_html_e('Endpoint (generic)', 'cloud-media-offload'); ?><?php echo $this->locked_note('endpoint'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('endpoint')); ?>" value="<?php echo esc_attr($s['endpoint']); ?>" placeholder="s3.us-west-1.wasabisys.com">
                    </td></tr>
                    <tr><th><?php esc_html_e('Region', 'cloud-media-offload'); ?><?php echo $this->locked_note('region'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('region')); ?>" value="<?php echo esc_attr($s['region']); ?>">
                        <p class="description"><?php printf( esc_html__( 'Use %s for R2.', 'cloud-media-offload' ), '<code>auto</code>' ); ?></p>
                    </td></tr>
                    <tr><th><?php esc_html_e('Path-style', 'cloud-media-offload'); ?></th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('path_style')); ?>" value="1" <?php checked($s['path_style']); ?>> <?php esc_html_e('Use path-style URLs (on for R2 and most S3-compatible)', 'cloud-media-offload'); ?></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Access Key ID', 'cloud-media-offload'); ?><?php echo $this->locked_note('access_key'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('access_key')); ?>" value="<?php echo esc_attr($s['access_key']); ?>">
                    </td></tr>
                    <tr><th><?php esc_html_e('Secret Access Key', 'cloud-media-offload'); ?><?php echo $this->locked_note('secret_key'); ?></th><td>
                        <input type="password" class="regular-text" name="<?php echo esc_attr($this->field_name('secret_key')); ?>" value="<?php echo esc_attr($s['secret_key']); ?>">
                    </td></tr>
                    <tr><th><?php esc_html_e('Bucket', 'cloud-media-offload'); ?><?php echo $this->locked_note('bucket'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('bucket')); ?>" value="<?php echo esc_attr($s['bucket']); ?>">
                    </td></tr>
                    <tr><th><?php esc_html_e('Public Base URL', 'cloud-media-offload'); ?><?php echo $this->locked_note('public_base'); ?></th><td>
                        <input type="url" class="regular-text" name="<?php echo esc_attr($this->field_name('public_base')); ?>" value="<?php echo esc_attr($s['public_base']); ?>" placeholder="https://cdn.example.com">
                        <p class="description"><?php esc_html_e('Custom domain or r2.dev address. URL rewriting uses this.', 'cloud-media-offload'); ?></p>
                    </td></tr>
                    <tr><th><?php esc_html_e('Key Prefix', 'cloud-media-offload'); ?></th><td>
                        <input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('prefix')); ?>" value="<?php echo esc_attr($s['prefix']); ?>" placeholder="wp">
                    </td></tr>
                    <tr><th><?php esc_html_e('Automatic offload', 'cloud-media-offload'); ?></th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('auto_offload')); ?>" value="1" <?php checked($s['auto_offload']); ?>> <?php esc_html_e('Send new uploads to R2', 'cloud-media-offload'); ?></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Full-page URL rewriting', 'cloud-media-offload'); ?></th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('fullpage_rewrite')); ?>" value="1" <?php checked($s['fullpage_rewrite']); ?>> <?php esc_html_e('Rewrite all local media URLs across the page via output buffering', 'cloud-media-offload'); ?></label>
                        <p class="description"><strong><?php esc_html_e('Enable only after migration completes (0 pending) — otherwise not-yet-migrated images may 404.', 'cloud-media-offload'); ?></strong></p>
                    </td></tr>
                    <tr><th><?php esc_html_e('Delete local copy', 'cloud-media-offload'); ?></th><td>
                        <label><input type="checkbox" name="<?php echo esc_attr($this->field_name('delete_local')); ?>" value="1" <?php checked($s['delete_local']); ?>> <?php esc_html_e('Delete from the server after upload is verified on R2', 'cloud-media-offload'); ?></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Batch size', 'cloud-media-offload'); ?></th><td>
                        <input type="number" min="1" max="200" name="<?php echo esc_attr($this->field_name('batch_size')); ?>" value="<?php echo esc_attr($s['batch_size']); ?>">
                    </td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e('Connection test', 'cloud-media-offload'); ?></h2>
            <button class="button" id="r2mo-test"><?php esc_html_e('Test connection', 'cloud-media-offload'); ?></button>
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
            <h1><?php esc_html_e('Setup Wizard', 'cloud-media-offload'); ?></h1>
            <div class="r2mo-steps">
                <span data-dot="1" class="on"><?php esc_html_e('1. Provider', 'cloud-media-offload'); ?></span>
                <span data-dot="2"><?php esc_html_e('2. Credentials', 'cloud-media-offload'); ?></span>
                <span data-dot="3"><?php esc_html_e('3. Test', 'cloud-media-offload'); ?></span>
                <span data-dot="4"><?php esc_html_e('4. Options', 'cloud-media-offload'); ?></span>
                <span data-dot="5"><?php esc_html_e('5. Finish', 'cloud-media-offload'); ?></span>
            </div>
            <form method="post" action="options.php" id="r2mo-wizard-form">
                <?php settings_fields('r2mo_group'); ?>

                <div class="r2mo-wizard-step active" data-step="1">
                    <h2><?php esc_html_e('1. Choose your storage provider', 'cloud-media-offload'); ?></h2>
                    <p>
                        <label><input type="radio" name="<?php echo esc_attr($this->field_name('provider')); ?>" value="r2" <?php checked($s['provider'], 'r2'); ?>> <?php esc_html_e('Cloudflare R2 (recommended — zero egress)', 'cloud-media-offload'); ?></label><br>
                        <label><input type="radio" name="<?php echo esc_attr($this->field_name('provider')); ?>" value="generic" <?php checked($s['provider'], 'generic'); ?>> <?php esc_html_e('Generic S3-compatible (Wasabi, Backblaze B2, DigitalOcean, MinIO)', 'cloud-media-offload'); ?></label>
                    </p>
                    <button type="button" class="button button-primary r2mo-next"><?php esc_html_e('Next', 'cloud-media-offload'); ?></button>
                </div>

                <div class="r2mo-wizard-step" data-step="2">
                    <h2><?php esc_html_e('2. Credentials', 'cloud-media-offload'); ?></h2>
                    <p class="description"><?php esc_html_e('You may also define these values as constants in wp-config.php (e.g. R2MO_SECRET_KEY); a constant overrides the database value.', 'cloud-media-offload'); ?></p>
                    <table class="form-table" role="presentation">
                        <tr><th><?php esc_html_e('Account ID (R2)', 'cloud-media-offload'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('account_id')); ?>" value="<?php echo esc_attr($s['account_id']); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Endpoint (generic)', 'cloud-media-offload'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('endpoint')); ?>" value="<?php echo esc_attr($s['endpoint']); ?>" placeholder="s3.us-west-1.wasabisys.com"></td></tr>
                        <tr><th><?php esc_html_e('Region', 'cloud-media-offload'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('region')); ?>" value="<?php echo esc_attr($s['region']); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Access Key ID', 'cloud-media-offload'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('access_key')); ?>" value="<?php echo esc_attr($s['access_key']); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Secret Access Key', 'cloud-media-offload'); ?></th><td><input type="password" class="regular-text" name="<?php echo esc_attr($this->field_name('secret_key')); ?>" value="<?php echo esc_attr($s['secret_key']); ?>"></td></tr>
                        <tr><th><?php esc_html_e('Bucket', 'cloud-media-offload'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('bucket')); ?>" value="<?php echo esc_attr($s['bucket']); ?>"></td></tr>
                    </table>
                    <button type="button" class="button r2mo-prev"><?php esc_html_e('Back', 'cloud-media-offload'); ?></button>
                    <button type="button" class="button button-primary r2mo-next"><?php esc_html_e('Next', 'cloud-media-offload'); ?></button>
                </div>

                <div class="r2mo-wizard-step" data-step="3">
                    <h2><?php esc_html_e('3. Test', 'cloud-media-offload'); ?></h2>
                    <p class="description"><?php esc_html_e('Save your settings first, then test.', 'cloud-media-offload'); ?></p>
                    <button type="button" class="button" id="r2mo-wizard-test"><?php esc_html_e('Test connection', 'cloud-media-offload'); ?></button>
                    <span id="r2mo-test-result" class="r2mo-test-result"></span>
                    <p style="margin-top:15px;">
                        <button type="button" class="button r2mo-prev"><?php esc_html_e('Back', 'cloud-media-offload'); ?></button>
                        <button type="button" class="button button-primary r2mo-next"><?php esc_html_e('Next', 'cloud-media-offload'); ?></button>
                    </p>
                </div>

                <div class="r2mo-wizard-step" data-step="4">
                    <h2><?php esc_html_e('4. Presentation and options', 'cloud-media-offload'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr><th><?php esc_html_e('Public Base URL', 'cloud-media-offload'); ?></th><td><input type="url" class="regular-text" name="<?php echo esc_attr($this->field_name('public_base')); ?>" value="<?php echo esc_attr($s['public_base']); ?>" placeholder="https://cdn.example.com"></td></tr>
                        <tr><th><?php esc_html_e('Key Prefix', 'cloud-media-offload'); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr($this->field_name('prefix')); ?>" value="<?php echo esc_attr($s['prefix']); ?>" placeholder="wp"></td></tr>
                        <tr><th><?php esc_html_e('Automatic offload', 'cloud-media-offload'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr($this->field_name('auto_offload')); ?>" value="1" <?php checked($s['auto_offload']); ?>> <?php esc_html_e('Send new uploads to R2', 'cloud-media-offload'); ?></label></td></tr>
                        <tr><th><?php esc_html_e('Delete local copy', 'cloud-media-offload'); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr($this->field_name('delete_local')); ?>" value="1" <?php checked($s['delete_local']); ?>> <?php esc_html_e('Delete from the server after upload is verified on R2', 'cloud-media-offload'); ?></label></td></tr>
                    </table>
                    <button type="button" class="button r2mo-prev"><?php esc_html_e('Back', 'cloud-media-offload'); ?></button>
                    <button type="button" class="button button-primary r2mo-next"><?php esc_html_e('Next', 'cloud-media-offload'); ?></button>
                </div>

                <div class="r2mo-wizard-step" data-step="5">
                    <h2><?php esc_html_e('5. Finish', 'cloud-media-offload'); ?></h2>
                    <p><?php echo sprintf( __('There are %d media file(s) pending.', 'cloud-media-offload'), (int) $pending ); ?></p>
                    <p class="description"><?php esc_html_e('After saving, migrate the existing library from the Migration page. After migration completes, enable Full-page URL rewriting in Settings.', 'cloud-media-offload'); ?> <a href="<?php echo esc_url(admin_url('admin.php?page=r2mo-migrate')); ?>"><?php esc_html_e('Migration', 'cloud-media-offload'); ?></a>.</p>
                    <button type="button" class="button r2mo-prev"><?php esc_html_e('Back', 'cloud-media-offload'); ?></button>
                    <?php submit_button(__('Save', 'cloud-media-offload'), 'primary', 'submit', false); ?>
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
            <h1><?php esc_html_e('Cloud Migration', 'cloud-media-offload'); ?></h1>
            <p><?php esc_html_e('Media not yet moved to R2:', 'cloud-media-offload'); ?> <strong id="r2mo-pending"><?php echo (int) $pending; ?></strong></p>
            <button class="button button-primary" id="r2mo-start" data-total="<?php echo (int) $pending; ?>"><?php esc_html_e('Start migration', 'cloud-media-offload'); ?></button>
            <button class="button" id="r2mo-stop" disabled><?php esc_html_e('Stop', 'cloud-media-offload'); ?></button>
            <div class="r2mo-bar-wrap"><div id="r2mo-bar" class="r2mo-bar">0%</div></div>
            <pre id="r2mo-log" class="r2mo-log"></pre>
        </div>
        <?php
    }
}
