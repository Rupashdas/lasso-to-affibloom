<?php
/**
 * Affibloom's groups, as the migration reads and writes them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * Affibloom's groups, through the Groups screens' rules (Task 26F).
 */
interface GroupWriter {

	/**
	 * Every group.
	 *
	 * @return list<array{id: int, name: string, slug: string, description: string}>
	 */
	public function all(): array;

	/**
	 * Creates a group.
	 *
	 * @param string $name        Its name.
	 * @param string $description Its description.
	 * @return int Its ID.
	 * @throws InvalidArgumentException When the name is refused.
	 */
	public function create( string $name, string $description ): int;

	/**
	 * Changes a group's name and description.
	 *
	 * @param int    $id          The group.
	 * @param string $name        Its name.
	 * @param string $description Its description.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function update( int $id, string $name, string $description ): void;

	/**
	 * A group's published products, in order.
	 *
	 * @param int $id The group.
	 * @return list<int>
	 */
	public function members( int $id ): array;

	/**
	 * Makes a group's products these published ones, in order.
	 *
	 * @param int       $id       The group.
	 * @param list<int> $products Product IDs.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function set_members( int $id, array $products ): void;
}
