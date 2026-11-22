<?php
/**
 * What a Lasso item becomes.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * What a Lasso item becomes (Task LM2): Affibloom's Product box block with its attributes, or `[affibloom …]`.
 */
final class AffibloomTarget {

	/** Affibloom's block. */
	public const BLOCK = 'affibloom/product-box';

	/**
	 * Builds the target.
	 *
	 * @param string               $kind       BLOCK, or 'shortcode'.
	 * @param array<string, mixed> $attributes The block's attributes, or the shortcode's.
	 */
	public function __construct( public readonly string $kind, public readonly array $attributes ) {}

	/**
	 * The block's attributes.
	 *
	 * @param array<string, mixed> $attributes The block's attributes.
	 */
	public static function block( array $attributes ): self {
		return new self( self::BLOCK, $attributes );
	}

	/**
	 * `[affibloom …]` with these attributes.
	 *
	 * @param array<string, string> $attributes The shortcode's attributes.
	 */
	public static function shortcode( array $attributes ): self {
		return new self( 'shortcode', $attributes );
	}

	/**
	 * As it would be written: the block's comment (its attributes as JSON) or the shortcode's text.
	 */
	public function text(): string {
		if ( self::BLOCK === $this->kind ) {
			return '<!-- wp:' . self::BLOCK . ' ' . (string) json_encode( $this->attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->'; // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Domain code does not call WordPress.
		}
		$text = '';
		foreach ( $this->attributes as $name => $value ) {
			$text .= ' ' . $name . '="' . str_replace( '"', '&quot;', (string) $value ) . '"';
		}

		return '[affibloom' . $text . ']';
	}
}
