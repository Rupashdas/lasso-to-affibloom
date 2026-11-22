<?php
/**
 * One Lasso item in a post's content.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * A Lasso item in content (Task LM2): Lasso's block (its `short_code` is the box, its schema attributes are read too),
 * a `[lasso …]` outside it, or a `[lasso-attr …]`.
 */
final class LassoItem {

	/** Lasso's `affiliate-plugin/lasso` block. */
	public const BLOCK = 'block';

	/** A `[lasso …]` outside Lasso's block. */
	public const SHORTCODE = 'shortcode';

	/** A `[lasso-attr …]`. */
	public const ATTR = 'lasso-attr';

	/** WordPress's Shortcode block holding one `[lasso …]` and nothing else; it converts as Lasso's block (Task LM4). */
	public const SHORTCODE_BLOCK = 'shortcode-block';

	/**
	 * Builds the item.
	 *
	 * @param string                $kind       BLOCK, SHORTCODE or ATTR.
	 * @param string                $markup     The shortcode as written (a block's `short_code`).
	 * @param array<string, string> $attributes Its attributes, as WordPress parses them.
	 * @param array<string, mixed>  $block      A block's own attributes (its schema settings); empty for a shortcode.
	 */
	public function __construct( public readonly string $kind, public readonly string $markup, public readonly array $attributes, public readonly array $block = array() ) {}

	/**
	 * The box's attributes with a block's schema settings added as the shortcode writes them (`enable`, A52).
	 *
	 * @return array<string, string>
	 */
	public function box(): array {
		$box = $this->attributes;
		foreach ( array( 'schema_review', 'schema_pros_cons' ) as $toggle ) {
			$on = $this->block[ $toggle ] ?? false;
			if ( true === $on || 'true' === $on || 'enable' === $on ) {
				$box[ $toggle ] = 'enable';
			}
		}
		foreach ( array( 'schema_price', 'schema_price_currency' ) as $value ) {
			if ( is_scalar( $this->block[ $value ] ?? null ) && '' !== trim( (string) $this->block[ $value ] ) ) {
				$box[ $value ] = trim( (string) $this->block[ $value ] );
			}
		}

		return $box;
	}
}
