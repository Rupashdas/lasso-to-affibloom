<?php
/**
 * A family whose migration is built.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Migration\MigrationLine;

/**
 * A family whose migration is built, not only its dry run (Task 26A on). Running it twice changes nothing the second
 * time (F10 D7).
 */
interface RunStep extends Step {

	/**
	 * The migration: what was done with every item.
	 *
	 * @return list<MigrationLine>
	 */
	public function run(): array;
}
