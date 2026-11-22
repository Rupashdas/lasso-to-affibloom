<?php
/**
 * The migration's maps for the conversion.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application\Conversion;

use AffibloomLassoMigrator\Application\GroupsStep;
use AffibloomLassoMigrator\Application\TablesStep;
use AffibloomLassoMigrator\Domain\Conversion\IdMaps;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\GroupWriter;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;

/**
 * What the migration made of Lasso's records, groups and tables (26A, 26F, 26I), as the conversion reads them: each
 * record's product (the first; more than one stopped the products family, D7), each Lasso group slug's Affibloom group
 * slug through the group map, each table's Affibloom table through the table map.
 */
final class MigrationMaps {

	/**
	 * Builds the reader.
	 *
	 * @param MigratedProducts $products The products' ID map.
	 * @param MigrationLog     $log      The group and table maps.
	 * @param LassoSource      $source   Lasso's groups, for their slugs.
	 * @param GroupWriter      $groups   Affibloom's groups, for theirs.
	 */
	public function __construct( private MigratedProducts $products, private MigrationLog $log, private LassoSource $source, private GroupWriter $groups ) {}

	/**
	 * The maps as they are now.
	 */
	public function read(): IdMaps {
		$products = array();
		foreach ( $this->products->all() as $lasso => $made ) {
			if ( array() !== $made ) {
				$products[ $lasso ] = $made[0];
			}
		}

		$affibloom = array_column( $this->groups->all(), 'slug', 'id' );
		$map       = $this->log->map( GroupsStep::MAP );
		$groups    = array();
		foreach ( $this->source->groups() as $group ) {
			$id = $map[ $group->id ] ?? 0;
			if ( isset( $affibloom[ $id ] ) ) {
				$groups[ $group->slug ] = (string) $affibloom[ $id ];
			}
		}

		return new IdMaps( $products, $groups, $this->log->map( TablesStep::MAP ) );
	}
}
