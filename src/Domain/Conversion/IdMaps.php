<?php
/**
 * The migration's maps, as the conversion reads them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * What the migration made of Lasso's records, groups and tables (26A, 26F, 26I), so a Lasso box can name Affibloom's.
 */
final class IdMaps {

	/**
	 * Builds the maps.
	 *
	 * @param array<int, int>       $products Lasso record ID → Affibloom product ID.
	 * @param array<string, string> $groups   Lasso group slug → Affibloom group slug.
	 * @param array<int, int>       $tables   Lasso table ID → Affibloom table ID.
	 */
	public function __construct( public readonly array $products = array(), public readonly array $groups = array(), public readonly array $tables = array() ) {}
}
