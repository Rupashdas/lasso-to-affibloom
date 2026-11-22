<?php
/**
 * Affibloom's products' URL variants, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\VariantWriter;

/**
 * Affibloom's products' URL variants, through its extension API only (Task 26H).
 */
final class ApiVariants implements VariantWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * A published product's variants, or null.
	 *
	 * @param int $product The product.
	 * @return list<array{name: string, key: string, kind: string}>|null
	 */
	public function variants( int $product ): ?array {
		return $this->api->product_variants( $product );
	}

	/**
	 * Makes a product's variants this list.
	 *
	 * @param int                                    $product The product.
	 * @param list<array{name: string, key: string}> $rows    The rows.
	 */
	public function set( int $product, array $rows ): void {
		$this->api->set_product_variants( $product, $rows );
	}
}
