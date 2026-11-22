<?php
/**
 * Affibloom's Amazon product data, as the migration reads and writes it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

use InvalidArgumentException;

/**
 * Affibloom's Amazon product store (Task 26L).
 */
interface AmazonWriter {

	/**
	 * A cached product, or null.
	 *
	 * @param string $asin    The ASIN.
	 * @param string $country The store.
	 * @return array<string, mixed>|null
	 */
	public function product( string $asin, string $country ): ?array;

	/**
	 * Stores a cached product, marked imported.
	 *
	 * @param string               $asin    The ASIN.
	 * @param string               $country The store.
	 * @param array<string, mixed> $data    Its fields in the store's names.
	 * @throws InvalidArgumentException When it is refused.
	 */
	public function import( string $asin, string $country, array $data ): void;
}
