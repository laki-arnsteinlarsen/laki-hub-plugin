<?php
defined('ABSPATH') || exit;

class LakiHub_Admin {

    public static function init() {
        add_action('admin_menu',            [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_head',            [__CLASS__, 'inject_favicon']);
    }

    public static function register_menu() {
        add_menu_page(
            'Laki Hub', 'Laki Hub',
            'manage_options',
            'laki-hub',
            [__CLASS__, 'page_dashboard'],
            'dashicons-building',
            3
        );
        add_submenu_page('laki-hub', 'Dashbord',      'Dashbord',      'manage_options', 'laki-hub',           [__CLASS__, 'page_dashboard']);
        add_submenu_page('laki-hub', 'CRM',           'CRM',           'manage_options', 'laki-hub-crm',       [__CLASS__, 'page_crm']);
        add_submenu_page('laki-hub', 'Prosjekter',    'Prosjekter',    'manage_options', 'laki-hub-projects',  [__CLASS__, 'page_projects']);
        add_submenu_page('laki-hub', 'Timeføring',    'Timeføring',    'manage_options', 'laki-hub-time',      [__CLASS__, 'page_time']);
        add_submenu_page('laki-hub', 'Inntekt',       'Inntekt',       'manage_options', 'laki-hub-revenue',   [__CLASS__, 'page_revenue']);
        add_submenu_page('laki-hub', 'Innstillinger', 'Innstillinger', 'manage_options', 'laki-hub-settings',  [__CLASS__, 'page_settings']);
    }

    public static function enqueue_assets(string $hook) {
        if (strpos($hook, 'laki-hub') === false) return;
        wp_enqueue_style('laki-hub-css', LAKI_HUB_URL . 'assets/css/admin.css',  [], LAKI_HUB_VERSION);
        wp_enqueue_script('laki-hub-js', LAKI_HUB_URL . 'assets/js/admin.js',   ['jquery'], LAKI_HUB_VERSION, true);
        wp_localize_script('laki-hub-js', 'LakiHub', [
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('laki_hub_nonce'),
            'gmail_enabled' => LakiHub_Gmail::is_connected() ? 1 : 0,
            'settings_url'  => admin_url('admin.php?page=laki-hub-settings'),
        ]);
    }

    /** Replace the browser-tab favicon on all Laki Hub admin pages */
    public static function inject_favicon() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'laki-hub') === false) return;
        $url = esc_url(LAKI_HUB_URL . 'assets/images/favicon.svg');
        echo "<link rel=\"icon\" type=\"image/svg+xml\" href=\"{$url}\">\n";
        echo "<link rel=\"shortcut icon\" href=\"{$url}\">\n";
    }

    public static function page_dashboard() { include LAKI_HUB_DIR . 'admin/views/dashboard.php'; }
    public static function page_crm()       { include LAKI_HUB_DIR . 'admin/views/crm.php'; }
    public static function page_projects()  { include LAKI_HUB_DIR . 'admin/views/projects.php'; }
    public static function page_time()      { include LAKI_HUB_DIR . 'admin/views/time.php'; }
    public static function page_revenue()   { include LAKI_HUB_DIR . 'admin/views/revenue.php'; }
    public static function page_settings()  { include LAKI_HUB_DIR . 'admin/views/settings.php'; }
}
