<?php
/**
 * One family's part of the migration.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;

/**
 * One family's part of the migration (F10 D3). `run()` joins with the first family that writes (26A).
 */
interface Step {

	/**
	 * The family it migrates.
	 */
	public function family(): Family;

	/**
	 * What it will do with every item, nothing written (the dry run).
	 *
	 * @return list<MigrationLine>
	 */
	public function plan(): array;
}
