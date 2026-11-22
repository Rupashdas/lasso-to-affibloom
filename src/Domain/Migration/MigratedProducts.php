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
 * any status, including the products the one-time import made.
 */
interface MigratedProducts {

	/**
	 * The products made from the Lasso record; more than one is an error the family stops on (D7).
	 *
	 * @param int $lasso_id The Lasso record's ID.
	 * @return list<int>
	 */
	public function for_record( int $lasso_id ): array;
}
