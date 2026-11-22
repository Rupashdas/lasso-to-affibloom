<?php
/**
 * The migrator's ID map.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Lasso;

use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;

/**
 * The migrator's ID map (F10 D1, D7): the post meta `_affibloom_lasso_id` on the products made from Lasso's records,
 * the one-time import's included, any status. Read once, with one SELECT. The map is the migrator's own data and goes
 * with Finish (LM10).
 */
final class LassoIdMap implements MigratedProducts {

	/** The meta that holds a product's Lasso record. */
	public const META_KEY = '_affibloom_lasso_id';

	/** The meta that holds a product's mark (F10 D8). */
	public const MARK_KEY = '_affibloom_migrated';

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
	 * Names the product's Lasso record and keeps its mark: the migrator's own meta on the product, which Finish removes.
	 *
	 * @param int           $product  The product.
	 * @param int           $lasso_id Its Lasso record.
	 * @param MigrationMark $mark     What it was given and what was stored.
	 */
	public function link( int $product, int $lasso_id, MigrationMark $mark ): void {
		update_post_meta( $product, self::META_KEY, (string) $lasso_id );
		update_post_meta(
			$product,
			self::MARK_KEY,
			wp_slash(
				(string) wp_json_encode(
					array(
						'given'  => $mark->given,
						'stored' => $mark->stored,
					)
				)
			)
		);
		if ( null !== $this->map && ! in_array( $product, $this->map[ $lasso_id ] ?? array(), true ) ) {
			$this->map[ $lasso_id ][] = $product;
		}
	}

	/**
	 * A product's mark, or null when it has none.
	 *
	 * @param int $product The product.
	 */
	public function mark( int $product ): ?MigrationMark {
		$stored = json_decode( (string) get_post_meta( $product, self::MARK_KEY, true ), true );

		return is_array( $stored ) && isset( $stored['given'], $stored['stored'] ) ? new MigrationMark( (string) $stored['given'], (string) $stored['stored'] ) : null;
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
