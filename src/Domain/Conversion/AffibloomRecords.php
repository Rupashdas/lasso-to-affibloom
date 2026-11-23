<?php
/**
 * Whether Affibloom has a product, group or table.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Whether Affibloom has what a box names (Task LM9): a product by ID, a group by ID or slug, a table by ID.
 */
interface AffibloomRecords {

	/**
	 * Whether it exists.
	 *
	 * @param string $kind `product`, `group` or `table`.
	 * @param string $ref  Its ID, or a group's slug.
	 */
	public function exists( string $kind, string $ref ): bool;
}
