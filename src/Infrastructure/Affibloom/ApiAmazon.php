<?php
/**
 * Affibloom's Amazon product data, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\AmazonWriter;

/**
 * Affibloom's Amazon product store, through its extension API only (Task 26L).
 */
final class ApiAmazon implements AmazonWriter {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * A cached product, or null.
	 *
	 * @param string $asin    The ASIN.
	 * @param string $country The store.
	 * @return array<string, mixed>|null
	 */
	public function product( string $asin, string $country ): ?array {
		return $this->api->amazon_product( $asin, $country );
	}

	/**
	 * Stores a cached product, marked imported.
	 *
	 * @param string               $asin    The ASIN.
	 * @param string               $country The store.
	 * @param array<string, mixed> $data    Its fields.
	 */
	public function import( string $asin, string $country, array $data ): void {
		$this->api->import_amazon_product( $asin, $country, $data );
	}
}
