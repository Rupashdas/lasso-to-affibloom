<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Migration\GroupWriter;
use InvalidArgumentException;

/**
 * Affibloom's groups in memory: groups by ID with a slug from the name, and their products in order.
 */
final class FakeGroups implements GroupWriter {

	/** Groups by ID. @var array<int, array{id: int, name: string, slug: string, description: string}> */
	public array $groups = array();

	/** Products by group. @var array<int, list<int>> */
	public array $members = array();

	private int $next = 500;

	public function all(): array {
		return array_values( $this->groups );
	}

	public function create( string $name, string $description ): int {
		if ( in_array( $name, array_column( $this->groups, 'name' ), true ) ) {
			throw new InvalidArgumentException( 'name: group_name_taken' );
		}
		$id                  = $this->next++;
		$this->groups[ $id ] = array(
			'id'          => $id,
			'name'        => $name,
			'slug'        => strtolower( str_replace( ' ', '-', $name ) ),
			'description' => $description,
		);

		return $id;
	}

	public function update( int $id, string $name, string $description ): void {
		$this->groups[ $id ]['name']        = $name;
		$this->groups[ $id ]['description'] = $description;
	}

	public function members( int $id ): array {
		return $this->members[ $id ] ?? array();
	}

	public function set_members( int $id, array $products ): void {
		$this->members[ $id ] = $products;
	}
}
