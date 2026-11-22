<?php
/**
 * Affibloom's products, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\ProductRules;
use AffibloomLassoMigrator\Domain\Migration\ProductWriter;

/**
 * Affibloom's products, through its extension API only (Tasks 25A, 26A, 26B): what it refuses, and the imports, reads,
 * changes, retirements and trashing the migration makes.
 */
final class ApiProducts implements ProductRules, ProductWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * Field => reason code; empty when Affibloom accepts every field.
	 *
	 * @param array<string, mixed> $fields Affibloom's product fields.
	 * @return array<string, string>
	 */
	public function refusals( array $fields ): array {
		return $this->api->check_product( $fields );
	}

	/**
	 * A new published product from the fields, with the record's dates.
	 *
	 * @param array<string, mixed> $fields   Affibloom's product fields.
	 * @param string               $created  The record's created date.
	 * @param string               $modified The record's changed date.
	 */
	public function import( array $fields, string $created, string $modified ): int {
		return $this->api->import_product( $fields, $created, $modified );
	}

	/**
	 * A product's fields, or null when there is no such product.
	 *
	 * @param int $product The product.
	 * @return array<string, mixed>|null
	 */
	public function fields( int $product ): ?array {
		return $this->api->product_fields( $product );
	}

	/**
	 * Changes a published product's fields.
	 *
	 * @param int                  $product The product.
	 * @param array<string, mixed> $fields  Affibloom's product fields.
	 */
	public function update( int $product, array $fields ): void {
		$this->api->update_product( $product, $fields );
	}

	/**
	 * Retires a product, pointing at another (0 for none).
	 *
	 * @param int $product The product.
	 * @param int $to      The product it points at.
	 */
	public function retire( int $product, int $to ): void {
		$this->api->retire_product( $product, $to );
	}

	/**
	 * Writes a product's dates as given.
	 *
	 * @param int    $product  The product.
	 * @param string $created  The record's created date.
	 * @param string $modified The record's changed date.
	 */
	public function date( int $product, string $created, string $modified ): void {
		$this->api->date_product( $product, $created, $modified );
	}

	/**
	 * Moves a product to the trash.
	 *
	 * @param int $product The product.
	 */
	public function trash( int $product ): void {
		$this->api->trash_product( $product );
	}
}
