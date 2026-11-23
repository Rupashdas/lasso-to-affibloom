<?php
/**
 * The migrator's own data.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * What the migrator keeps on a site (Task LM10): its ID map and marks, the conversion's copies, its run states, logs and
 * locks. Finish erases it and leaves one mark, the time it finished, which uninstall removes.
 */
interface MigratorData {

	/**
	 * When the migration was finished, a Unix time; 0 while it is not.
	 */
	public function finished(): int;

	/**
	 * Erases every option and post meta the migrator wrote, the finished mark aside.
	 *
	 * @return array<string, int> How many were removed, by name; a name with none is left out.
	 */
	public function erase(): array;

	/**
	 * Marks the migration finished.
	 *
	 * @param int $now The time.
	 */
	public function finish( int $now ): void;
}
