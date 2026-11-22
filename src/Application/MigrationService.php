<?php
/**
 * The migration, family by family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationReport;

/**
 * The migration, family by family in the fixed order (F10 D3). Each task adds its family's step; a family without one
 * is reported as not built yet.
 */
final class MigrationService {

	/**
	 * The steps by family.
	 *
	 * @var array<string, Step>
	 */
	private array $steps = array();

	/**
	 * Builds the service.
	 *
	 * @param list<Step> $steps One step per built family.
	 */
	public function __construct( array $steps ) {
		foreach ( $steps as $step ) {
			$this->steps[ $step->family()->value ] = $step;
		}
	}

	/**
	 * The dry run: what each family will do, nothing written.
	 *
	 * @param list<Family> $families The families, in the fixed order.
	 */
	public function plan( array $families ): MigrationReport {
		$lines     = array();
		$not_built = array();
		foreach ( $families as $family ) {
			if ( ! isset( $this->steps[ $family->value ] ) ) {
				$not_built[] = $family;
				continue;
			}
			array_push( $lines, ...$this->steps[ $family->value ]->plan() );
		}

		return new MigrationReport( $families, $lines, $not_built );
	}
}
