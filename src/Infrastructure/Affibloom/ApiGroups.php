<?php
/**
 * Affibloom's groups, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\GroupWriter;

/**
 * Affibloom's groups, through its extension API only (Task 26F).
 */
final class ApiGroups implements GroupWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Every group.
	 *
	 * @return list<array{id: int, name: string, slug: string, description: string}>
	 */
	public function all(): array {
		return $this->api->groups();
	}

	/**
	 * Creates a group.
	 *
	 * @param string $name        Its name.
	 * @param string $description Its description.
	 */
	public function create( string $name, string $description ): int {
		return $this->api->create_group( $name, $description );
	}

	/**
	 * Changes a group's name and description.
	 *
	 * @param int    $id          The group.
	 * @param string $name        Its name.
	 * @param string $description Its description.
	 */
	public function update( int $id, string $name, string $description ): void {
		$this->api->update_group( $id, $name, $description );
	}

	/**
	 * A group's published products, in order.
	 *
	 * @param int $id The group.
	 * @return list<int>
	 */
	public function members( int $id ): array {
		return $this->api->group_members( $id );
	}

	/**
	 * Makes a group's products these ones, in order.
	 *
	 * @param int       $id       The group.
	 * @param list<int> $products Product IDs.
	 */
	public function set_members( int $id, array $products ): void {
		$this->api->set_group_members( $id, $products );
	}
}
