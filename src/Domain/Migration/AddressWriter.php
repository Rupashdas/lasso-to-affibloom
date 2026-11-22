<?php
/**
 * Affibloom's products' cloaked addresses.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * A product's cloaked address in Affibloom: slug, old slugs, cloaking switch (Task 26G).
 */
interface AddressWriter {

	/**
	 * A product's address, or null when there is no such product.
	 *
	 * @param int $product The product.
	 * @return array{slug: string, old_slugs: list<string>, cloak: bool}|null
	 */
	public function address( int $product ): ?array;

	/**
	 * The product that answers at a slug, as its slug or an old one; 0 when none.
	 *
	 * @param string $slug The slug.
	 */
	public function holder( string $slug ): int;

	/**
	 * Sets a product's address.
	 *
	 * @param int          $product   The product.
	 * @param string       $slug      Its slug.
	 * @param list<string> $old_slugs Its old slugs.
	 * @param bool         $cloak     Its cloaking switch.
	 * @throws InvalidArgumentException When a slug is another product's.
	 */
	public function set( int $product, string $slug, array $old_slugs, bool $cloak ): void;
}
