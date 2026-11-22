<?php
/**
 * Why a Lasso item stays as it is.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * A Lasso item the conversion leaves exactly as it is (F10 D16), with a reason someone can act on.
 */
final class Blocked {

	/**
	 * Builds the answer.
	 *
	 * @param string $reason Why.
	 */
	public function __construct( public readonly string $reason ) {}
}
