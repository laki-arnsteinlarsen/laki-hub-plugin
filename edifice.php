<?php
/**
 * Plugin Name: Edifice
 * Description: Internt dashbord for Laki AS — CRM, timeføring, prosjekter, inntekt og nyheter.
 * Version: 1.0.0
 * Author: Laki AS
 * Text Domain: edifice
 */

defined('ABSPATH') || exit;

define('EDIFICE_VERSION', '1.0.0');
define('EDIFICE_DIR', plugin_dir_path(__FILE__));
define('EDIFICE_URL', plugin_dir_url(__FILE__));

require_once EDIFICE_DIR . 'includes/class-db.php';
require_once EDIFICE_DIR . 'includes/class-brreg.php';
require_once EDIFICE_DIR . 'includes/class-crm.php';
require_once EDIFICE_DIR . 'includes/class-projects.php';
require_once EDIFICE_DIR . 'includes/class-time.php';
require_once EDIFICE_DIR . 'includes/class-revenue.php';
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
    Edifice_Admin::init();
    Edifice_Frontend::init();
});

// AJAX handlers
add_action('wp_ajax_edifice_brreg_lookup',    ['Edifice_Brreg', 'ajax_lookup']);
add_action('wp_ajax_edifice_crm_save',        ['Edifice_CRM',   'ajax_save']);
add_action('wp_ajax_edifice_crm_delete',      ['Edifice_CRM',   'ajax_delete']);
add_action('wp_ajax_edifice_time_save',        ['Edifice_Time', 'ajax_save']);
add_action('wp_ajax_edifice_time_delete',      ['Edifice_Time', 'ajax_delete']);
add_action('wp_ajax_edifice_time_start',       ['Edifice_Time', 'ajax_start_timer']);
add_action('wp_ajax_edifice_time_stop',        ['Edifice_Time', 'ajax_stop_timer']);
add_action('wp_ajax_edifice_time_active',      ['Edifice_Time', 'ajax_active_timer']);
add_action('wp_ajax_edifice_time_period_data', ['Edifice_Time', 'ajax_period_data']);
add_action('wp_ajax_edifice_time_export',      ['Edifice_Time', 'ajax_export']);
add_action('wp_ajax_edifice_project_save',    ['Edifice_Projects', 'ajax_save']);
add_action('wp_ajax_edifice_project_delete',  ['Edifice_Projects', 'ajax_delete']);
add_action('wp_ajax_edifice_revenue_save',    ['Edifice_Revenue', 'ajax_save']);
add_action('wp_ajax_edifice_revenue_delete',  ['Edifice_Revenue', 'ajax_delete']);
