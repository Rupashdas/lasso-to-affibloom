<?php
/**
 * The settings and display families.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;

/**
 * The settings family or the display family (F10 D3, D9; Task 25B): both read Lasso's one settings option, each
 * through its own mapper.
 */
final class SettingsStep implements Step {

	/**
	 * Builds the step.
	 *
	 * @param Family                       $family Settings or Display.
	 * @param LassoSource                  $source Lasso's data.
	 * @param SettingsMapper|DisplayMapper $mapper The family's mapper.
	 */
	public function __construct( private Family $family, private LassoSource $source, private SettingsMapper|DisplayMapper $mapper ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return $this->family;
	}

	/**
	 * One line per key.
	 *
	 * @param bool $update Unused: nothing was migrated before.
	 * @param bool $force  Unused.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Step's signature; this family has no run yet.
		return $this->mapper->lines( $this->source->settings() );
	}
}
