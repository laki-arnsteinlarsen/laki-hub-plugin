<?php
/**
 * Plugin Name: Edifice
 * Description: Internt dashbord for Laki AS — CRM, timeføring, prosjekter, inntekt og nyheter.
 * Version: 1.0.0
 * Author: Laki AS
 * Text Domain: edifice
 */

defined('ABSPATH') || exit;

define('EDIFICE_VERSION', '1.0.2'); // deployed with Gumroad OAuth // deployed 2026-05-05T12:54Z
define('EDIFICE_DIR', plugin_dir_path(__FILE__));
define('EDIFICE_URL', plugin_dir_url(__FILE__));

require_once EDIFICE_DIR . 'includes/class-db.php';
require_once EDIFICE_DIR . 'includes/class-brreg.php';
require_once EDIFICE_DIR . 'includes/class-crm.php';
require_once EDIFICE_DIR . 'includes/class-projects.php';
require_once EDIFICE_DIR . 'includes/class-time.php';
require_once EDIFICE_DIR . 'includes/class-revenue.php';
require_once EDIFICE_DIR . 'includes/class-products-digital.php';
require_once EDIFICE_DIR . 'includes/class-sync-products.php';
require_once EDIFICE_DIR . 'admin/admin.php';
require_once EDIFICE_DIR . 'frontend/class-frontend.php';

register_activation_hook(__FILE__, function () {
    // Always migrate (rename old tables) before install so dbDelta
    // never creates empty new tables that shadow existing data.
    Edifice_DB::maybe_migrate();
    Edifice_DB::install();
});

add_action('plugins_loaded', function () {
    Edifice_DB::maybe_migrate();
    Edifice_Sync_Products::init();
    Edifice_Admin::init();
    Edifice_Frontend::init();
});

// AJAX handlers — core modules
add_action('wp_ajax_edifice_brreg_lookup',    ['Edifice_Brreg',    'ajax_lookup']);
add_action('wp_ajax_edifice_crm_save',        ['Edifice_CRM',      'ajax_save']);
add_action('wp_ajax_edifice_crm_delete',      ['Edifice_CRM',      'ajax_delete']);
add_action('wp_ajax_edifice_time_save',        ['Edifice_Time',    'ajax_save']);
add_action('wp_ajax_edifice_time_delete',      ['Edifice_Time',    'ajax_delete']);
add_action('wp_ajax_edifice_time_start',       ['Edifice_Time',    'ajax_start_timer']);
add_action('wp_ajax_edifice_time_stop',        ['Edifice_Time',    'ajax_stop_timer']);
add_action('wp_ajax_edifice_time_active',      ['Edifice_Time',    'ajax_active_timer']);
add_action('wp_ajax_edifice_time_period_data', ['Edifice_Time',    'ajax_period_data']);
add_action('wp_ajax_edifice_time_export',      ['Edifice_Time',    'ajax_export']);
add_action('wp_ajax_edifice_project_save',    ['Edifice_Projects', 'ajax_save']);
add_action('wp_ajax_edifice_project_delete',  ['Edifice_Projects', 'ajax_delete']);
add_action('wp_ajax_edifice_revenue_save',    ['Edifice_Revenue',  'ajax_save']);
add_action('wp_ajax_edifice_revenue_delete',  ['Edifice_Revenue',  'ajax_delete']);

// AJAX handlers — digital products / passive income
add_action('wp_ajax_edifice_product_save',          ['Edifice_Products_Digital', 'ajax_save_product']);
add_action('wp_ajax_edifice_product_delete',         ['Edifice_Products_Digital', 'ajax_delete_product']);
add_action('wp_ajax_edifice_listing_save',           ['Edifice_Products_Digital', 'ajax_save_listing']);
add_action('wp_ajax_edifice_listing_delete',         ['Edifice_Products_Digital', 'ajax_delete_listing']);
add_action('wp_ajax_edifice_product_revenue_save',   ['Edifice_Products_Digital', 'ajax_save_revenue']);
add_action('wp_ajax_edifice_product_revenue_delete', ['Edifice_Products_Digital', 'ajax_delete_revenue']);
add_action('wp_ajax_edifice_listings_for_product',   ['Edifice_Products_Digital', 'ajax_listings_for_product']);

// AJAX handlers — product sync
add_action('wp_ajax_edifice_sync_gumroad',          ['Edifice_Sync_Products', 'ajax_trigger_gumroad']);
add_action('wp_ajax_edifice_sync_chrome_batch',      ['Edifice_Sync_Products', 'ajax_chrome_sync_batch']);
add_action('wp_ajax_edifice_sync_chrome_revenue',    ['Edifice_Sync_Products', 'ajax_chrome_sync_revenue']);
add_action('wp_ajax_edifice_sync_save_settings',     ['Edifice_Sync_Products', 'ajax_save_settings']);
add_action('wp_ajax_edifice_sync_get_settings',      ['Edifice_Sync_Products', 'ajax_get_settings']);
add_action('wp_ajax_edifice_sync_get_listings',      ['Edifice_Sync_Products', 'ajax_get_listings_for_sync']);

// AJAX handlers — Gumroad OAuth
add_action('wp_ajax_edifice_sync_get_oauth_url',   ['Edifice_Sync_Products', 'ajax_get_oauth_url']);
add_action('wp_ajax_edifice_sync_disconnect',       ['Edifice_Sync_Products', 'ajax_disconnect_gumroad']);
add_action('wp_ajax_edifice_register_promptbase',    ['Edifice_Sync_Products', 'ajax_register_promptbase_product']);
