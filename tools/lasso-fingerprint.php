<?php
/**
 * The fingerprint of Lasso's data (F10 D17, AC23; Task 26A): row counts and checksums of every `{prefix}lasso_*`
 * table, the `lasso-urls` posts and their meta, the `lasso-cat` terms and Lasso's options. Equal before and after a
 * migration run proves the run changed nothing of Lasso's, so switching back to Lasso is a rollback.
 *
 * Run it with WP-CLI before and after a run and compare:
 *
 *     wp eval-file wp-content/plugins/affibloom-lasso-migrator/tools/lasso-fingerprint.php > before.json
 *
 * It only reads. Values are hashed, never printed.
 *
 * @package AffibloomLassoMigrator
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a read-only check run by hand; table names come from SHOW TABLES and are backquoted.

defined( 'ABSPATH' ) || exit;

global $wpdb;

/**
 * The count and an md5 over every row of a query, read in order.
 *
 * @param string $sql A SELECT with an ORDER BY.
 * @return array{rows: int, md5: string}
 */
$affibloom_lasso_digest = static function ( string $sql ) use ( $wpdb ): array {
	$hash = hash_init( 'md5' );
	$rows = 0;
	foreach ( $wpdb->get_results( $sql, ARRAY_N ) as $row ) {
		hash_update( $hash, implode( "\x1f", array_map( 'strval', $row ) ) . "\x1e" );
		++$rows;
	}

	return array(
		'rows' => $rows,
		'md5'  => hash_final( $hash ),
	);
};

$affibloom_lasso_print = array( 'tables' => array() );
foreach ( $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'lasso_' ) . '%' ) ) as $affibloom_lasso_table ) {
	$affibloom_lasso_name                                    = str_replace( '`', '', (string) $affibloom_lasso_table );
	$affibloom_lasso_sum                                     = $wpdb->get_row( "CHECKSUM TABLE `$affibloom_lasso_name`", ARRAY_N );
	$affibloom_lasso_print['tables'][ $affibloom_lasso_name ] = array(
		'rows'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `$affibloom_lasso_name`" ),
		'checksum' => (string) ( $affibloom_lasso_sum[1] ?? '' ),
	);
}

$affibloom_lasso_print['posts']    = $affibloom_lasso_digest( $wpdb->prepare( "SELECT ID, post_title, post_name, post_status, post_date, post_modified, post_content FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID", 'lasso-urls' ) );
$affibloom_lasso_print['postmeta'] = $affibloom_lasso_digest( $wpdb->prepare( "SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = %s ORDER BY m.meta_id", 'lasso-urls' ) );
$affibloom_lasso_print['terms']    = $affibloom_lasso_digest( $wpdb->prepare( "SELECT t.term_id, t.name, t.slug, tt.description, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s ORDER BY t.term_id", 'lasso-cat' ) );
$affibloom_lasso_print['options']  = $affibloom_lasso_digest( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name = %s OR option_name = %s ORDER BY option_name", 'lasso_settings', 'lasso_amazon_tracking_id_blacklist' ) );

echo wp_json_encode( $affibloom_lasso_print, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
