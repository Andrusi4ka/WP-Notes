<?php
/**
 * Plugin Name: WP Notes
 * Plugin URI: https://example.com/
 * Description: Admin notes for WordPress screens with per-note permissions and plugin-managed uploads.
 * Version: 2.0.20
 * Author: Relevant AS (Andrii Boiko)
 * Author URI: https://relevant.no/
 * Text Domain: wp-notes
 */

if (! defined('ABSPATH')) {
	exit;
}

define('WP_NOTES_VERSION', '2.0.20');
define('WP_NOTES_FILE', __FILE__);
define('WP_NOTES_PATH', plugin_dir_path(__FILE__));
define('WP_NOTES_URL', plugin_dir_url(__FILE__));

require_once WP_NOTES_PATH . 'includes/class-wp-notes-i18n.php';
require_once WP_NOTES_PATH . 'includes/class-wp-notes-context.php';
require_once WP_NOTES_PATH . 'includes/class-wp-notes-repository.php';
require_once WP_NOTES_PATH . 'includes/class-wp-notes-permissions.php';
require_once WP_NOTES_PATH . 'includes/class-wp-notes-renderer.php';
require_once WP_NOTES_PATH . 'includes/class-wp-notes-admin.php';
require_once WP_NOTES_PATH . 'includes/class-wp-notes-plugin.php';

register_activation_hook(__FILE__, array('WP_Notes_Plugin', 'activate'));

WP_Notes_Plugin::instance();
