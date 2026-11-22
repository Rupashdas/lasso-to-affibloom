<?php
/**
 * Affibloom's block, through its extension API.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\Affibloom;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomBlocks;

/**
 * Affibloom's Product box block, through its extension API only (Task LM2).
 */
final class ApiBlocks implements AffibloomBlocks {

	/**
	 * Builds the bridge.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function __construct( private Api $api ) {}

	/**
	 * The block's attributes and what it would lose.
	 *
	 * @param array<string, string> $attributes `[affibloom]`'s attributes.
	 * @return array{attributes: array<string, mixed>, lost: list<string>}|null
	 */
	public function for_shortcode( array $attributes ): ?array {
		return $this->api->block_for_shortcode( $attributes );
	}

	/**
	 * The block as WordPress stores it: a self-closing comment, as Affibloom's dynamic block saves (Task LM3).
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 */
	public function markup( array $attributes ): string {
		return serialize_block(
			array(
				'blockName'    => 'affibloom/product-box',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
}
