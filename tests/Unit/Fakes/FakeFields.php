<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Migration\FieldWriter;
use InvalidArgumentException;

/**
 * Affibloom's fields in memory: the built-in ones with Lasso's IDs, created ones from 100, and products' rows.
 */
final class FakeFields implements FieldWriter {

	/** Definitions by ID. @var array<int, array{id: int, name: string, type: string, description: string, built_in: bool}> */
	public array $definitions = array();

	/** Rows by published product. @var array<int, list<array{field: int, value: string, visible: bool, show_name: bool}>> */
	public array $rows = array();

	private int $next = 100;

	public function __construct() {
		foreach ( array( 1 => 'Primary Rating', 2 => 'Pros', 3 => 'Cons' ) as $id => $name ) {
			$this->definitions[ $id ] = array(
				'id'          => $id,
				'name'        => $name,
				'type'        => 'text',
				'description' => '',
				'built_in'    => true,
			);
		}
	}

	public function definitions(): array {
		return array_values( $this->definitions );
	}

	public function create( string $name, string $type, string $description ): int {
		$id                       = $this->next++;
		$this->definitions[ $id ] = array(
			'id'          => $id,
			'name'        => $name,
			'type'        => $type,
			'description' => $description,
			'built_in'    => false,
		);

		return $id;
	}

	public function rename( int $id, string $name ): void {
		$this->definitions[ $id ]['name'] = $name;
	}

	public function rows( int $product ): ?array {
		return $this->rows[ $product ] ?? null;
	}

	public function set_rows( int $product, array $rows ): void {
		if ( ! isset( $this->rows[ $product ] ) ) {
			throw new InvalidArgumentException( "No published product $product." );
		}
		// Affibloom shows Primary Rating first.
		usort( $rows, static fn ( array $a, array $b ): int => (int) ( 1 !== $a['field'] ) <=> (int) ( 1 !== $b['field'] ) );
		$this->rows[ $product ] = $rows;
	}
}
