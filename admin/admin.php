<?php
defined('ABSPATH') || exit;

class Edifice_Admin {

    public static function init() {
        add_action('admin_menu',            [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_head', [__CLASS__, 'inject_favicon'], 100);
    }

    public static function register_menu() {
        add_menu_page(
            'Edifice', 'Edifice',
            'manage_options',
            'edifice',
            [__CLASS__, 'page_dashboard'],
            'dashicons-building',
            3
        );
        add_submenu_page('edifice', 'Dashbord',      'Dashbord',      'manage_options', 'edifice',              [__CLASS__, 'page_dashboard']);
        add_submenu_page('edifice', 'CRM',           'CRM',           'manage_options', 'edifice-crm',          [__CLASS__, 'page_crm']);
        add_submenu_page('edifice', 'Prosjekter',    'Prosjekter',    'manage_options', 'edifice-projects',     [__CLASS__, 'page_projects']);
        add_submenu_page('edifice', 'Timeføring',    'Timeføring',    'manage_options', 'edifice-time',         [__CLASS__, 'page_time']);
        add_submenu_page('edifice', 'Inntekt',       'Inntekt',       'manage_options', 'edifice-revenue',      [__CLASS__, 'page_revenue']);
        add_submenu_page('edifice', 'Produkter',     'Produkter',     'manage_options', 'edifice-products',     [__CLASS__, 'page_products']);
        add_submenu_page('edifice', 'Innstillinger', 'Innstillinger', 'manage_options', 'edifice-settings',     [__CLASS__, 'page_settings']);
    }

    public static function enqueue_assets(string $hook) {
        if (strpos($hook, 'edifice') === false) return;
        wp_enqueue_style('edifice-css', EDIFICE_URL . 'assets/css/admin.css',  [], EDIFICE_VERSION);
        wp_enqueue_script('edifice-js', EDIFICE_URL . 'assets/js/admin.js',   ['jquery'], EDIFICE_VERSION, true);
        wp_localize_script('edifice-js', 'Edifice', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('edifice_nonce'),
            'gmail_enabled' => Edifice_Gmail::is_connected() ? 1 : 0,
            'settings_url'  => admin_url('admin.php?page=edifice-settings'),
        ]);
    }

    /** Replace the browser-tab favicon on all Edifice admin pages */
    public static function inject_favicon() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'edifice') === false) return;
        $url = esc_url(EDIFICE_URL . 'assets/images/favicon.svg');
        echo "<link rel=\"icon\" type=\"image/svg+xml\" href=\"{$url}\">\n";
        echo "<link rel=\"shortcut icon\" href=\"{$url}\">\n";
    }

    public static function page_dashboard() { include EDIFICE_DIR . 'admin/views/dashboard.php'; }
    public static function page_crm()       { include EDIFICE_DIR . 'admin/views/crm.php'; }
    public static function page_projects()  { include EDIFICE_DIR . 'admin/views/projects.php'; }
    public static function page_time()      { include EDIFICE_DIR . 'admin/views/time.php'; }
    public static function page_revenue()   { include EDIFICE_DIR . 'admin/views/revenue.php'; }
    public static function page_products()  { include EDIFICE_DIR . 'admin/views/products.php'; }
    public static function page_settings()  { include EDIFICE_DIR . 'admin/views/settings.php'; }
}
