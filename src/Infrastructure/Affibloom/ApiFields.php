<?php
/**
 * Affibloom's fields, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\FieldWriter;

/**
 * Affibloom's fields, through its extension API only (Task 26E).
 */
final class ApiFields implements FieldWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Every field.
	 *
	 * @return list<array{id: int, name: string, type: string, description: string, built_in: bool}>
	 */
	public function definitions(): array {
		return $this->api->field_definitions();
	}

	/**
	 * Creates a field.
	 *
	 * @param string $name        Its name.
	 * @param string $type        Its kind.
	 * @param string $description Its description.
	 */
	public function create( string $name, string $type, string $description ): int {
		return $this->api->create_field( $name, $type, $description );
	}

	/**
	 * Renames a field.
	 *
	 * @param int    $id   The field.
	 * @param string $name Its name.
	 */
	public function rename( int $id, string $name ): void {
		$this->api->rename_field( $id, $name );
	}

	/**
	 * A published product's field rows, or null.
	 *
	 * @param int $product The product.
	 * @return list<array{field: int, value: string, visible: bool, show_name: bool}>|null
	 */
	public function rows( int $product ): ?array {
		return $this->api->product_field_rows( $product );
	}

	/**
	 * Sets a published product's field rows.
	 *
	 * @param int                                                                    $product The product.
	 * @param list<array{field: int, value: string, visible: bool, show_name: bool}> $rows    The rows.
	 */
	public function set_rows( int $product, array $rows ): void {
		$this->api->set_product_field_rows( $product, $rows );
	}
}
