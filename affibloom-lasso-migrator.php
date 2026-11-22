<?php
/**
 * Plugin Name:       Affibloom Lasso Migrator
 * Description:       Moves this site from Lasso to Affibloom: its links, settings and content. Delete it when the move is finished.
 * Version:           0.1.0
 * Requires at least: 7.1.4
 * Requires PHP:      8.3
 * Requires Plugins:  affibloom
 * Author:            Devrupash
 * Author URI:        https://devrupash.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       affibloom-lasso-migrator
 * Domain Path:       /languages
 *
 * @package AffibloomLassoMigrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AFFIBLOOM_LASSO_MIGRATOR_VERSION', '0.1.0' );
define( 'AFFIBLOOM_LASSO_MIGRATOR_FILE', __FILE__ );

/**
 * Explains why the plugin did not start. Shown only when the checks below fail.
 *
 * The return type is a docblock, not `: void`, so this file stays readable by
 * any PHP version and can show the notice instead of a parse error.
 *
 * @return void
 */
function affibloom_lasso_requirements_notice() {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html__( 'Affibloom Lasso Migrator needs PHP 8.3 and WordPress 7.1.4 or newer. It has not started.', 'affibloom-lasso-migrator' )
	);
}

// WordPress checks the header's requirements before activation, but not after
// a server is downgraded, so they are checked again on every load.
if ( version_compare( PHP_VERSION, '8.3', '<' ) || version_compare( get_bloginfo( 'version' ), '7.1.4', '<' ) || ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action( 'admin_notices', 'affibloom_lasso_requirements_notice' );
	return;
}

require __DIR__ . '/vendor/autoload.php';

AffibloomLassoMigrator\Plugin::boot();
