<?php
/**
 * The display family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;

/**
 * The display family (F10 S22-S28; Task 25B): Lasso's display settings per type, read as Lasso reads them. Its run
 * comes with 26D.
 */
final class DisplayStep implements Step {

	/**
	 * Builds the step.
	 *
	 * @param LassoSource   $source Lasso's data.
	 * @param DisplayMapper $mapper Lasso's display keys → Affibloom's display model.
	 */
	public function __construct( private LassoSource $source, private DisplayMapper $mapper ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Display;
	}

	/**
	 * One line per type and field.
	 *
	 * @param bool $update Unused until the run is built.
	 * @param bool $force  Unused.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Step's signature; this family has no run yet.
		return $this->mapper->lines( $this->source->settings() );
	}
}
