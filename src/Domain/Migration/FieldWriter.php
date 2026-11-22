<?php
/**
 * Affibloom's fields, as the migration reads and writes them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * Affibloom's fields, through the Fields screens' and the product page's rules (Task 26E).
 */
interface FieldWriter {

	/**
	 * Every field, `{ id, name, type, description, built_in }`.
	 *
	 * @return list<array{id: int, name: string, type: string, description: string, built_in: bool}>
	 */
	public function definitions(): array;

	/**
	 * Creates a field.
	 *
	 * @param string $name        Its name.
	 * @param string $type        Its kind.
	 * @param string $description Its description.
	 * @return int Its ID.
	 * @throws InvalidArgumentException When a value is refused.
	 */
	public function create( string $name, string $type, string $description ): int;

	/**
	 * Renames a field.
	 *
	 * @param int    $id   The field.
	 * @param string $name Its name.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function rename( int $id, string $name ): void;

	/**
	 * A published product's field rows, or null.
	 *
	 * @param int $product The product.
	 * @return list<array{field: int, value: string, visible: bool, show_name: bool}>|null
	 */
	public function rows( int $product ): ?array;

	/**
	 * Sets a published product's field rows, in order.
	 *
	 * @param int                                                                    $product The product.
	 * @param list<array{field: int, value: string, visible: bool, show_name: bool}> $rows    The rows.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function set_rows( int $product, array $rows ): void;
}
