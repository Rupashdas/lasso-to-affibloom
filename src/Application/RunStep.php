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
	 * @param bool $update Refresh what was migrated before (--update, F10 D8).
	 * @param bool $force  Take Lasso's side where both sides changed (--force).
	 * @return list<MigrationLine>
	 */
	public function run( bool $update = false, bool $force = false ): array;
}
