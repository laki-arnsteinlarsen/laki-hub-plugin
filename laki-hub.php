<?php
/**
 * Plugin Name: Laki Hub
 * Description: Internt dashbord for Laki AS — CRM, timeføring, prosjekter, inntekt og nyheter.
 * Version: 1.0.0
 * Author: Laki AS
 * Text Domain: laki-hub
 */

defined('ABSPATH') || exit;

define('LAKI_HUB_VERSION', '1.0.0');
define('LAKI_HUB_DIR', plugin_dir_path(__FILE__));
define('LAKI_HUB_URL', plugin_dir_url(__FILE__));

require_once LAKI_HUB_DIR . 'includes/class-db.php';
require_once LAKI_HUB_DIR . 'includes/class-brreg.php';
require_once LAKI_HUB_DIR . 'includes/class-crm.php';
require_once LAKI_HUB_DIR . 'includes/class-projects.php';
require_once LAKI_HUB_DIR . 'includes/class-time.php';
require_once LAKI_HUB_DIR . 'includes/class-revenue.php';
require_once LAKI_HUB_DIR . 'admin/admin.php';
require_once LAKI_HUB_DIR . 'frontend/class-frontend.php';

register_activation_hook(__FILE__, ['LakiHub_DB', 'install']);

add_action('plugins_loaded', function () {
    LakiHub_Admin::init();
    LakiHub_Frontend::init();
});

// AJAX handlers
add_action('wp_ajax_laki_brreg_lookup',    ['LakiHub_Brreg', 'ajax_lookup']);
add_action('wp_ajax_laki_crm_save',        ['LakiHub_CRM',   'ajax_save']);
add_action('wp_ajax_laki_crm_delete',      ['LakiHub_CRM',   'ajax_delete']);
add_action('wp_ajax_laki_time_save',       ['LakiHub_Time',  'ajax_save']);
add_action('wp_ajax_laki_time_delete',     ['LakiHub_Time',  'ajax_delete']);
add_action('wp_ajax_laki_project_save',    ['LakiHub_Projects', 'ajax_save']);
add_action('wp_ajax_laki_project_delete',  ['LakiHub_Projects', 'ajax_delete']);
add_action('wp_ajax_laki_revenue_save',    ['LakiHub_Revenue', 'ajax_save']);
add_action('wp_ajax_laki_revenue_delete',  ['LakiHub_Revenue', 'ajax_delete']);
