<?php
/**
 * Affibloom's comparison tables.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * Affibloom's comparison tables, read and stored whole (Task 26I).
 */
interface TableWriter {

	/**
	 * A table whole, or null.
	 *
	 * @param int $id The table.
	 * @return array<string, mixed>|null
	 */
	public function table( int $id ): ?array;

	/**
	 * Stores a table whole: new when $id is 0.
	 *
	 * @param int                  $id   The table to replace; 0 for a new one.
	 * @param array<string, mixed> $data The table.
	 * @return int The table's ID.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function put( int $id, array $data ): int;
}
