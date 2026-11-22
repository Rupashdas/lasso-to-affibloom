<?php
/**
 * When each family was last migrated.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * When each family was last migrated and how it went (F10 analysis "Data": the migration's option), for the admin
 * page's Run (26O) and Finish (LM10).
 */
interface MigrationLog {

	/**
	 * Records a family's run.
	 *
	 * @param Family $family The family.
	 * @param int    $items  Its lines.
	 * @param int    $errors Its error lines.
	 */
	public function ran( Family $family, int $items, int $errors ): void;

	/**
	 * Whether the family ran before (the settings' first run writes, a later one only with --update, F10 D9).
	 *
	 * @param Family $family The family.
	 */
	public function has_run( Family $family ): bool;
}
