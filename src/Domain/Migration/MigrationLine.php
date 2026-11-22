<?php
/**
 * One line of the migration report.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * One item of Lasso's data and what becomes of it (F10 D18).
 */
final class MigrationLine {

	/**
	 * Builds the line.
	 *
	 * @param Family $family Its family.
	 * @param string $key    Its Lasso ID, or its key for a setting.
	 * @param string $name   What Lasso calls it.
	 * @param string $type   Its kind (a record's store: `amazon`, `walmart`, `other`).
	 * @param string $status A record's Lasso status (`publish`, `trash`, `lasso_delete`); '' for anything else.
	 * @param Action $action What the migration does.
	 * @param string $target What it becomes or points at in Affibloom.
	 * @param string $note   Why, or what to know.
	 */
	public function __construct(
		public readonly Family $family,
		public readonly string $key,
		public readonly string $name,
		public readonly string $type,
		public readonly string $status,
		public readonly Action $action,
		public readonly string $target = '',
		public readonly string $note = '',
	) {}
}
