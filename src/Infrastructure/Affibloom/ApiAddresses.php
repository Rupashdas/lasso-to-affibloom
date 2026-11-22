<?php
/**
 * Affibloom's products' cloaked addresses, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\AddressWriter;

/**
 * Affibloom's products' cloaked addresses, through its extension API only (Task 26G).
 */
final class ApiAddresses implements AddressWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * A product's address, or null.
	 *
	 * @param int $product The product.
	 * @return array{slug: string, old_slugs: list<string>, cloak: bool}|null
	 */
	public function address( int $product ): ?array {
		return $this->api->product_address( $product );
	}

	/**
	 * The product that answers at a slug; 0 when none.
	 *
	 * @param string $slug The slug.
	 */
	public function holder( string $slug ): int {
		return $this->api->product_at_slug( $slug );
	}

	/**
	 * Sets a product's address.
	 *
	 * @param int          $product   The product.
	 * @param string       $slug      Its slug.
	 * @param list<string> $old_slugs Its old slugs.
	 * @param bool         $cloak     Its cloaking switch.
	 */
	public function set( int $product, string $slug, array $old_slugs, bool $cloak ): void {
		$this->api->set_product_address( $product, $slug, $old_slugs, $cloak );
	}
}
