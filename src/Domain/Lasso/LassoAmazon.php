<?php
/**
 * Lasso's Amazon data.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * Lasso's Amazon data (F10 R17; T2, T3, T16, S19): the cached rows of `lasso_amazon_products` that a record uses, how
 * many per-country tracking IDs and other-store rows there are, and whether Creators credentials are set (yes or no
 * only: their values are never read). Read only.
 */
final class LassoAmazon {

	/**
	 * Builds the data.
	 *
	 * @param list<array<string, string>> $rows            The cached rows a record uses, columns as Lasso names them.
	 * @param int                         $tracking_ids    Per-country tracking IDs with a value.
	 * @param int                         $other_stores    `lasso_extend_products` rows.
	 * @param bool                        $creators        Whether Lasso holds Creators credentials.
	 */
	public function __construct( public readonly array $rows = array(), public readonly int $tracking_ids = 0, public readonly int $other_stores = 0, public readonly bool $creators = false ) {}
}
