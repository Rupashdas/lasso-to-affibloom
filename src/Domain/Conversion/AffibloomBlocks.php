<?php
/**
 * Affibloom's block, as its API describes it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Affibloom's Product box block (Task LM2): its attributes for a display named by `[affibloom …]`'s, and what it would
 * lose of them.
 */
interface AffibloomBlocks {

	/**
	 * The block's attributes and the names it would lose; null when the attributes name nothing Affibloom can draw.
	 *
	 * @param array<string, string> $attributes `[affibloom]`'s attributes.
	 * @return array{attributes: array<string, mixed>, lost: list<string>}|null
	 */
	public function for_shortcode( array $attributes ): ?array;
}
