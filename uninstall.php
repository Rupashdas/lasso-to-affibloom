<?php
/**
 * Deleting Affibloom Lasso Migrator (Task LM10): removes anything it left, whether or not the migration was finished:
 * its ID map and marks, the conversion's copies, its options, logs, locks and the finished mark. Lasso's data and
 * Affibloom's stay.
 *
 * @package AffibloomLassoMigrator
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}

require __DIR__ . '/vendor/autoload.php';

( new AffibloomLassoMigrator\Infrastructure\WordPress\WpMigratorData() )->forget();
