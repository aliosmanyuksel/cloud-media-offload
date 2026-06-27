<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Plugin {

    private static $instance = null;

    /** @var R2MO_Offloader */ private $offloader;
    /** @var R2MO_Rewriter */  private $rewriter;
    /** @var R2MO_Migrator */  private $migrator;
    /** @var R2MO_Admin */     private $admin;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->offloader = new R2MO_Offloader();
        $this->rewriter  = new R2MO_Rewriter();
        $this->migrator  = new R2MO_Migrator($this->offloader);
        $this->admin     = new R2MO_Admin($this->migrator);

        $this->hooks();
    }

    private function hooks() {
        // i18n
        add_action('init', function () {
            // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
            load_plugin_textdomain('cloud-media-offload', false, dirname(plugin_basename(R2MO_FILE)) . '/languages');
        });

        // Settings + admin
        add_action('admin_init', [$this->admin, 'register_settings']);
        add_action('admin_menu', [$this->admin, 'menu']);
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueue']);

        // Offload on upload (after all sizes are generated)
        add_filter('wp_generate_attachment_metadata', [$this->offloader, 'offload_on_upload'], 9999, 2);
        add_action('delete_attachment', [$this->offloader, 'delete_from_r2']);

        // URL rewriting — Layer 1 (attachment filters)
        add_filter('wp_get_attachment_url', [$this->rewriter, 'rewrite_url'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this->rewriter, 'rewrite_image_src'], 10, 4);
        add_filter('wp_calculate_image_srcset', [$this->rewriter, 'rewrite_srcset'], 10, 5);

        // URL rewriting — Layer 2 (gated full-page output buffer)
        add_action('template_redirect', [$this->rewriter, 'maybe_start_buffer'], 1);

        // AJAX
        add_action('wp_ajax_r2mo_migrate_batch', [$this->migrator, 'ajax_migrate_batch']);
        add_action('wp_ajax_r2mo_test', [$this->migrator, 'ajax_test_connection']);

        // WP-CLI
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('r2mo migrate', [$this->migrator, 'cli_migrate']);
        }
    }
}
