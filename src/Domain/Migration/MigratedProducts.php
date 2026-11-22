<?php
/**
 * Which Affibloom products a Lasso record already became.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * Which Affibloom products a Lasso record already became (F10 D1, D7): the migrator's ID map, `_affibloom_lasso_id`,
 * any status, including the products the one-time import made; and each product's mark (D8).
 */
interface MigratedProducts {

	/**
	 * The products made from the Lasso record; more than one is an error the family stops on (D7).
	 *
	 * @param int $lasso_id The Lasso record's ID.
	 * @return list<int>
	 */
	public function for_record( int $lasso_id ): array;

	/**
	 * Every Lasso record that has a product, with its products (for the records deleted in Lasso since, Task 26B).
	 *
	 * @return array<int, list<int>>
	 */
	public function all(): array;

	/**
	 * Names the product's Lasso record and keeps its mark.
	 *
	 * @param int           $product  The product.
	 * @param int           $lasso_id Its Lasso record.
	 * @param MigrationMark $mark     What it was given and what was stored.
	 */
	public function link( int $product, int $lasso_id, MigrationMark $mark ): void;

	/**
	 * A product's mark; null for a product the migration has not marked (the one-time import's, until adopted).
	 *
	 * @param int $product The product.
	 */
	public function mark( int $product ): ?MigrationMark;
}
