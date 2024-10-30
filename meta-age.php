<?php

/**
 * Plugin Name: Meta Age
 * Description: Verify users' age with crypto wallets.
 * Author: adastracrypto.com
 * Version: 1.2.2
 * Author URI: https://adastracrypto.com
 * Text Domain: meta-age
 * License: GPL v3+
 */

// Useful constants.
define('META_AGE_VER', '1.2.2');
define('META_AGE_DIR', __DIR__ . '/');
define('META_AGE_URI', plugins_url('/', __FILE__));
define('AGE_PLUGIN', "meta-age");
define('AGE_TABLE', "meta_age_sessions");

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/common/class-rest-api.php';

/**
 * Do activation
 *
 * @see https://developer.wordpress.org/reference/functions/register_activation_hook/
 */
function meta_age_activate($network)
{
	global $wpdb;

	try {
		if (version_compare(PHP_VERSION, '7.2', '<')) {
			throw new Exception(__('Meta Age Verification requires PHP version 7.2 at least!', AGE_PLUGIN));
		}

		if (version_compare($GLOBALS['wp_version'], '4.6.0', '<')) {
			throw new Exception(__('Meta Age Verification requires WordPress 4.6.0 at least!', AGE_PLUGIN));
		}

		if (!get_option('meta_age_verification_activated') && !get_transient('meta_age_verification_init_activation') && !set_transient('meta_age_verification_init_activation', 1)) {
			throw new Exception(__('Failed to initialize setup wizard.', AGE_PLUGIN));
		}

		if (!function_exists('dbDelta')) {
			require ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$wpdb->query('DROP TABLE IF EXISTS meta_age_sessions;');

		dbDelta("CREATE TABLE IF NOT EXISTS meta_age_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip VARCHAR(32) NOT NULL DEFAULT '',
			agent VARCHAR(512) NOT NULL DEFAULT '',
			link VARCHAR(255) NOT NULL DEFAULT '',
			email varchar(126) NULL,
			balance VARCHAR(32) NOT NULL DEFAULT '',
			wallet_type VARCHAR(16) NOT NULL DEFAULT '0',
			wallet_address VARCHAR(126) NOT NULL DEFAULT '',
			visited_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			synced TINYINT DEFAULT 0,
			PRIMARY KEY  (id)
		);");
		$wpdb->query('CREATE TABLE IF NOT EXISTS meta_wallet_connections (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			plugin_name VARCHAR(255) NOT NULL,
			session_table VARCHAR(255) NOT NULL,
			session_id INT NOT NULL,
			wallet_address VARCHAR(126) NOT NULL,
			ticker VARCHAR(16) NOT NULL,
			wallet_type VARCHAR(16) NOT NULL
		)');

		MetaAgeApi::setupKeypair();

		if (!wp_next_scheduled('meta_age_sync_data')) {

			if (!wp_schedule_event(time(), 'every_sixty_minutes', 'meta_age_sync_data')) {
				throw new Exception(__('Failed to connect to remote server!', AGE_PLUGIN));
			}
		}
	} catch (Exception $e) {
		if (defined('DOING_AJAX') && DOING_AJAX) { // Someone may install it via AJAX
			header('Content-Type:application/json;charset=' . get_option('blog_charset'));
			status_header(500);
			exit(json_encode([
				'success' => false,
				'name' => __('Failed To Activate Meta Age Verification plugin.', AGE_PLUGIN),
				'message' => $e->getMessage(),
			]));
		} else {
			exit($e->getMessage());
		}
	}
}
add_action('activate_meta-age/meta-age.php', 'meta_age_activate');

function runs_every_sixty_minute($schedules)
{
	$schedules['every_sixty_minutes'] = array(
		'interval' => 3600,
		'display' => __('Every 60 Minutes', 'textdomain')
	);
	return $schedules;
}

add_filter('cron_schedules', 'runs_every_sixty_minute');
/**
 * Do installation
 *
 * @see https://developer.wordpress.org/reference/hooks/plugins_loaded/
 */
function meta_age_install()
{
	load_plugin_textdomain(AGE_PLUGIN, false, 'meta-age/languages');

	require __DIR__ . '/common/functions.php';
	require __DIR__ . '/common/shortcode.php';
	require __DIR__ . '/common/hooks.php';

	if (is_admin()) {
		require __DIR__ . '/admin/class-terms-page.php';
		require __DIR__ . '/admin/class-settings-page.php';
		require __DIR__ . '/admin/class-plugin-activator.php';
		require __DIR__ . '/admin/hooks.php';
	} else {
		require __DIR__ . '/frontend/hooks.php';
	}
}
add_action('plugins_loaded', 'meta_age_install', 10, 0);

/*
	   |--------------------------------------------------------------------------
	   |  admin noticce for add infura project key
	   |--------------------------------------------------------------------------
		*/

function meta_age_admin_notice_warn()
{
	$settings = (array) get_option('meta_age_verification_settings');

	if (!isset($settings['infura_project_id']) && empty($settings['infura_project_id'])) {
		echo '<div class="notice notice-error is-dismissible">
				<p>Important:Please enter an infura API-KEY for WalletConnect to work <a style="font-weight:bold" href="' . esc_url(get_admin_url(null, 'admin.php?page=meta-age-settings')) . '">Link</a></p>
				</div>';
	}
}
if (is_admin()) {
	add_action('admin_notices', 'meta_age_admin_notice_warn');
}