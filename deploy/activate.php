<?php
$_SERVER['HTTP_HOST']   = 'edifice.arnsteinlarsen.no';
$_SERVER['REQUEST_URI'] = '/';
define('ABSPATH', '/var/www/html/');
require_once '/var/www/html/wp-load.php';
deactivate_plugins('laki-hub/laki-hub.php');
echo "Deactivated laki-hub\n";
$r = activate_plugin('edifice/edifice.php');
echo is_wp_error($r) ? 'ERROR: '.$r->get_error_message()."\n" : "Plugin activated OK\n";
$old = get_option('laki_hub_page_id');
if ($old && !get_option('edifice_page_id')) {
    update_option('edifice_page_id', $old);
    delete_option('laki_hub_page_id');
    echo "Migrated page_id: $old\n";
} else {
    echo "edifice_page_id: ".get_option('edifice_page_id')."\n";
}
echo "Active plugins:\n";
foreach (get_option('active_plugins') as $p) echo "  $p\n";
unlink(__FILE__);
echo "Done\n";
