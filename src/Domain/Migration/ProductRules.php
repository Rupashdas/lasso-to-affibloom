<?php
/**
 * What Affibloom refuses in a product's fields.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * What Affibloom would refuse in a product's fields (F10 D19), asked before anything is written.
 */
interface ProductRules {

	/**
	 * Field => reason code; empty when Affibloom accepts every field.
	 *
	 * @param array<string, mixed> $fields Affibloom's product fields.
	 * @return array<string, string>
	 */
	public function refusals( array $fields ): array;
}
