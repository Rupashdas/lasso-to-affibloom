<?php
/**
 * Affibloom's products' URL variants.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * A product's URL variants in Affibloom (Task 26H).
 */
interface VariantWriter {

	/**
	 * A published product's variants, or null when there is no such product.
	 *
	 * @param int $product The product.
	 * @return list<array{name: string, key: string, kind: string}>|null
	 */
	public function variants( int $product ): ?array;

	/**
	 * Makes a product's variants this list.
	 *
	 * @param int                                    $product The product.
	 * @param list<array{name: string, key: string}> $rows    The rows.
	 * @throws InvalidArgumentException When a row is refused.
	 */
	public function set( int $product, array $rows ): void;
}
