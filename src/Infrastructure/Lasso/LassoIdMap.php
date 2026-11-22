<?php
/**
 * The migrator's ID map.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Lasso;

use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;

/**
 * The migrator's ID map (F10 D1, D7): the post meta `_affibloom_lasso_id` on the products made from Lasso's records,
 * the one-time import's included, any status. Read once, with one SELECT. The map is the migrator's own data and goes
 * with Finish (LM10).
 */
final class LassoIdMap implements MigratedProducts {

	/** The meta that holds a product's Lasso record. */
	public const META_KEY = '_affibloom_lasso_id';

	/**
	 * The products by Lasso ID, once read.
	 *
	 * @var array<int, list<int>>|null
	 */
	private ?array $map = null;

	/**
	 * The products made from the Lasso record.
	 *
	 * @param int $lasso_id The Lasso record's ID.
	 * @return list<int>
	 */
	public function for_record( int $lasso_id ): array {
		if ( null === $this->map ) {
			$this->map = $this->read();
		}

		return $this->map[ $lasso_id ] ?? array();
	}

	/**
	 * Every mapped product, by Lasso ID.
	 *
	 * @return array<int, list<int>>
	 */
	private function read(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- the whole map, once per run.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id ASC", self::META_KEY ) );

		$map = array();
		foreach ( $rows as $row ) {
			$map[ (int) $row->meta_value ][] = (int) $row->post_id;
		}

		return $map;
	}
}
