<?php
/**
 * Laki Hub — Front-end app renderer
 * Serves the dashboard at /hub/ as a full-screen SPA-style app.
 */
defined('ABSPATH') || exit;

class LakiHub_Frontend {

    public static function init() {
        add_action('init',              [__CLASS__, 'create_hub_page']);
        add_action('template_redirect', [__CLASS__, 'login_wall'], 1);
        add_action('template_redirect', [__CLASS__, 'render_hub'], 5);
    }

    /**
     * Create the /hub/ page once on first load (idempotent).
     */
    public static function create_hub_page() {
        $stored_id = (int) get_option('laki_hub_page_id');
        if ($stored_id) {
            $page = get_post($stored_id);
            if ($page && $page->post_status === 'publish') return;
        }

        $page_id = wp_insert_post([
            'post_title'   => 'Laki Hub',
            'post_name'    => 'hub',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '',
            'post_author'  => 1,
        ]);

        if (!is_wp_error($page_id)) {
            update_option('laki_hub_page_id', $page_id);
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
        $page_id = (int) get_option('laki_hub_page_id');
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
        $plugin_url = LAKI_HUB_URL;
        $ajax_url   = admin_url('admin-ajax.php');
        $nonce      = wp_create_nonce('laki_hub_nonce');
        $logout_url = wp_logout_url(home_url('/hub/'));
        $user       = wp_get_current_user();
        ?>
<!DOCTYPE html>
<html lang="no">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Laki Hub</title>
  <link rel="stylesheet" href="<?= esc_url($plugin_url) ?>assets/css/admin.css">
  <link rel="stylesheet" href="<?= esc_url($plugin_url) ?>assets/css/frontend.css">
</head>
<body class="lh-app">

  <!-- Sidebar -->
  <nav class="lh-sidebar">
    <div class="lh-sidebar-logo">🏛️ <span>Laki Hub</span></div>
    <ul class="lh-nav">
      <li><a href="#dashboard" class="lh-nav-link active" data-section="dashboard">📊 Dashboard</a></li>
      <li><a href="#crm"       class="lh-nav-link"        data-section="crm">👥 CRM</a></li>
      <li><a href="#projects"  class="lh-nav-link"        data-section="projects">🚀 Prosjekter</a></li>
      <li><a href="#time"      class="lh-nav-link"        data-section="time">⏱️ Timeføring</a></li>
      <li><a href="#revenue"   class="lh-nav-link"        data-section="revenue">💰 Inntekt</a></li>
    </ul>
    <div class="lh-sidebar-footer">
      <span class="lh-user"><?= esc_html($user->display_name) ?></span>
      <a href="<?= esc_url($logout_url) ?>" class="lh-logout">Logg ut →</a>
    </div>
  </nav>

  <!-- Main content -->
  <main class="lh-main">

    <div class="lh-section" id="section-dashboard">
      <?php include LAKI_HUB_DIR . 'admin/views/dashboard.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-crm">
      <?php include LAKI_HUB_DIR . 'admin/views/crm.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-projects">
      <?php include LAKI_HUB_DIR . 'admin/views/projects.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-time">
      <?php include LAKI_HUB_DIR . 'admin/views/time.php'; ?>
    </div>

    <div class="lh-section lh-hidden" id="section-revenue">
      <?php include LAKI_HUB_DIR . 'admin/views/revenue.php'; ?>
    </div>

  </main>

  <script>
    var LakiHub = {
      ajaxUrl: '<?= esc_js($ajax_url) ?>',
      nonce:   '<?= esc_js($nonce) ?>'
    };
  </script>
  <script src="<?= esc_url($plugin_url) ?>assets/js/admin.js"></script>
  <script src="<?= esc_url($plugin_url) ?>assets/js/frontend.js"></script>

</body>
</html>
        <?php
    }
}
