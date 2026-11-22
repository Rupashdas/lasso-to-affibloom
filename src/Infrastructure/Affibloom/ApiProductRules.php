<?php
/**
 * Affibloom's product rules, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Migration\ProductRules;

/**
 * What Affibloom refuses in a product's fields, asked through its extension API (`check_product`, Task 25A).
 */
final class ApiProductRules implements ProductRules {

	/**
	 * Builds the rules.
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
}
