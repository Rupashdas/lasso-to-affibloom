<?php
/**
 * What the migration writes to Affibloom's products.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * What the migration writes to Affibloom's products, all through Affibloom's own rules (F10 D1, D5; Tasks 26A, 26B).
 */
interface ProductWriter {

	/**
	 * A new published product from the fields, with the record's dates.
	 *
	 * @param array<string, mixed> $fields   Affibloom's product fields.
	 * @param string               $created  The record's created date.
	 * @param string               $modified The record's changed date.
	 * @return int The product.
	 * @throws InvalidArgumentException When Affibloom refuses a field.
	 */
	public function import( array $fields, string $created, string $modified ): int;

	/**
	 * A product's fields as Affibloom holds them, or null when there is no such product.
	 *
	 * @param int $product The product.
	 * @return array<string, mixed>|null
	 */
	public function fields( int $product ): ?array;

	/**
	 * Changes a published product's fields (Task 26B).
	 *
	 * @param int                  $product The product.
	 * @param array<string, mixed> $fields  Affibloom's product fields.
	 * @throws InvalidArgumentException When Affibloom refuses a field.
	 */
	public function update( int $product, array $fields ): void;

	/**
	 * Retires a product, pointing at another (0 for none).
	 *
	 * @param int $product The product.
	 * @param int $to      The product it points at.
	 */
	public function retire( int $product, int $to ): void;

	/**
	 * Writes a product's dates as given (after its status changed, which stamps "now").
	 *
	 * @param int    $product  The product.
	 * @param string $created  The record's created date.
	 * @param string $modified The record's changed date.
	 */
	public function date( int $product, string $created, string $modified ): void;

	/**
	 * Moves a product to the trash.
	 *
	 * @param int $product The product.
	 */
	public function trash( int $product ): void;
}
