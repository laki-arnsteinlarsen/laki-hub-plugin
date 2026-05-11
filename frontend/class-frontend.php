<?php
/**
 * Edifice — Front-end app renderer
 * Serves the dashboard at /hub/ as a full-screen SPA-style app.
 */
defined('ABSPATH') || exit;

class Edifice_Frontend {

    public static function init() {
        add_action('init',              [__CLASS__, 'create_hub_page']);
        add_action('template_redirect', [__CLASS__, 'login_wall'], 1);
        add_action('template_redirect', [__CLASS__, 'render_hub'], 5);
    }

    /**
     * Create the /hub/ page once on first load (idempotent).
     * Also sets it as the WordPress front page.
     */
    public static function create_hub_page() {
        $stored_id = (int) get_option('edifice_page_id');
        if ($stored_id) {
            $page = get_post($stored_id);
            if ($page && $page->post_status === 'publish') {
                // Ensure front page is still set correctly (may have been reset)
                self::set_as_front_page($stored_id);
                return;
            }
        }

        $page_id = wp_insert_post([
            'post_title'   => 'Edifice',
            'post_name'    => 'hub',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            'post_author'  => 1,
        ]);

        if (!is_wp_error($page_id)) {
            update_option('edifice_page_id', $page_id);
            self::set_as_front_page($page_id);
        }
    }

    /**
     * Set a page as the WordPress static front page.
     */
    private static function set_as_front_page(int $page_id) {
        if ((int) get_option('page_on_front') !== $page_id) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', $page_id);
        }
    }

    /**
     * Redirect all non-logged-in front-end visitors to WP login.
     * Skips: admin, login page, AJAX, REST API.
     */
    public static function login_wall() {
        if (is_user_logged_in()) return;
        if (is_admin()) return;
        if (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'wp-login.php') return;
        if (wp_doing_ajax()) return;
        if (defined('REST_REQUEST') && REST_REQUEST) return;
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) return;

        wp_redirect(wp_login_url(home_url('/hub/')));
        exit;
    }

    /**
     * Intercept requests to /hub/ and render the full-screen app.
     */
    public static function render_hub() {
        $page_id = (int) get_option('edifice_page_id');
        if (!$page_id) return;
        if (!is_page($page_id) && !is_page('hub')) return;
        if (!is_user_logged_in()) return; // login_wall handles redirect

        self::output_app();
        exit;
    }

    /**
     * Output the full HTML app shell with all sections pre-rendered.
     */
    public static function output_app() {
        $plugin_url   = EDIFICE_URL;
        $ajax_url     = admin_url('admin-ajax.php');
        $nonce        = wp_create_nonce('edifice_nonce');
        $logout_url   = wp_logout_url(home_url('/hub/'));
        $user         = wp_get_current_user();
        $settings_url = admin_url('admin.php?page=edifice-settings');
        $gmail_on     = Edifice_Gmail::is_connected() ? 1 : 0;
        ?>
<!DOCTYPE html>
<html lang="no">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edifice</title>
  <link rel="stylesheet" href="<?= esc_url($plugin_url) ?>assets/css/admin.css">
  <link rel="stylesheet" href="<?= esc_url($plugin_url) ?>assets/css/frontend.css">
  <script src="<?= includes_url('js/jquery/jquery.min.js') ?>"></script>
  <script>
    var Edifice = {
      ajax_url:      '<?= esc_js($ajax_url) ?>',
      nonce:         '<?= esc_js($nonce) ?>',
      settings_url:  '<?= esc_js($settings_url) ?>',
      gmail_enabled: <?= (int) $gmail_on ?>,
      plugin_url:    '<?= esc_js($plugin_url) ?>'
    };
  </script>
</head>
<body class="lh-app">

  <?php /* Delt log-modal renders FØRST på body-nivå så guarden i partialen kan blokkere
            duplikat-inclusions fra crm.php/network.php nedenfor. */ ?>
  <?php include EDIFICE_DIR . 'admin/views/_interaction-log-modal.php'; ?>

  <!-- Sidebar -->
  <nav class="lh-sidebar">
    <div class="lh-sidebar-logo"><span class="lh-logo-icon">🌋</span><span>Edifice</span></div>
    <ul class="lh-nav">
      <li><a href="#dashboard" class="lh-nav-link active" data-section="dashboard">📊 Dashboard</a></li>
      <li><a href="#crm"       class="lh-nav-link"        data-section="crm">👥 CRM</a></li>
      <li><a href="#projects"  class="lh-nav-link"        data-section="projects">🚀 Prosjekter</a></li>
      <li><a href="#time"      class="lh-nav-link"        data-section="time">⏱️ Timeføring</a></li>
      <li><a href="#revenue"   class="lh-nav-link"        data-section="revenue">💰 Inntekt</a></li>
      <li><a href="#products"  class="lh-nav-link"        data-section="products">🛍️ Produkter</a></li>
      <li><a href="#prospects" class="lh-nav-link"        data-section="prospects">🎯 Prospekter</a></li>
      <li><a href="#network"   class="lh-nav-link"        data-section="network">🤝 Nettverk</a></li>
    </ul>
    <div class="lh-sidebar-footer">
      <span class="lh-user"><?= esc_html($user->display_name) ?></span>
      <a href="<?= esc_url($logout_url) ?>" class="lh-logout">Logg ut →</a>
    </div>
  </nav>

  <!-- Main content -->
  <main class="lh-main">

    <div class="lh-section" id="section-dashboard">
      <?php include EDIFICE_DIR . 'admin/views/dashboard.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-crm">
      <?php include EDIFICE_DIR . 'admin/views/crm.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-projects">
      <?php include EDIFICE_DIR . 'admin/views/projects.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-time">
      <?php include EDIFICE_DIR . 'admin/views/time.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-revenue">
      <?php include EDIFICE_DIR . 'admin/views/revenue.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-products">
      <?php include EDIFICE_DIR . 'admin/views/products.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-prospects">
      <?php include EDIFICE_DIR . 'admin/views/prospects.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-network">
      <?php include EDIFICE_DIR . 'admin/views/network.php'; ?>
    </div>

  </main>

  <script src="<?= esc_url($plugin_url) ?>assets/js/admin.js"></script>
  <script src="<?= esc_url($plugin_url) ?>assets/js/frontend.js"></script>

</body>
</html>
        <?php
    }
}
