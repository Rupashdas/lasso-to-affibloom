<?php
/**
 * Lasso's markup in a text, found and replaced in place.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Lasso's items in a text (a post's content, a widget's, an Elementor setting), in document order, each once (Tasks
 * LM2-LM4; F10 D16): Lasso's block (its shortcode copy inside is part of it), a Shortcode block that holds one
 * `[lasso …]` and nothing else, a `[lasso-attr …]`, a bare `[lasso …]`. rewrite() replaces the n-th item with the
 * n-th replacement, or keeps it when that is null; every other byte stays. Scanning and rewriting share one pattern, so
 * the places always match.
 */
final class LassoMarkup {

	/** The four kinds, in the order they are tried at a place. */
	private const ITEM = '#(?<block><!--\s+wp:affiliate-plugin/lasso(?<json>\s+\{.*?\})?\s+(?<closed>/)?-->(?(closed)|.*?<!--\s+/wp:affiliate-plugin/lasso\s+-->))'
		. '|(?<scblock><!--\s+wp:shortcode\s+-->\s*\[lasso(?<scattrs>\s[^\]]*)?\]\s*<!--\s+/wp:shortcode\s+-->)'
		. '|(?<attr>\[lasso-attr(?<aattrs>\s[^\]]*)?\])'
		. '|(?<short>\[lasso(?<sattrs>\s[^\]]*)?\])#s';

	/**
	 * The items, in document order: each one's kind (LassoItem's), its markup as written, its shortcode's attribute
	 * text, and a block's own attributes.
	 *
	 * @param string $text The text.
	 * @return list<array{kind: string, markup: string, attributes: string, block: array<string, mixed>}>
	 */
	public static function scan( string $text ): array {
		if ( ! preg_match_all( self::ITEM, $text, $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL ) ) {
			return array();
		}

		return array_map( self::item( ... ), $found );
	}

	/**
	 * The text with its items replaced; null when the replacements are not one per item.
	 *
	 * @param string            $text         The text.
	 * @param list<string|null> $replacements One per item, in order; null keeps that item.
	 */
	public static function rewrite( string $text, array $replacements ): ?string {
		if ( count( self::scan( $text ) ) !== count( $replacements ) ) {
			return null;
		}
		$place = 0;

		return (string) preg_replace_callback(
			self::ITEM,
			static function ( array $found ) use ( &$place, $replacements ): string {
				return $replacements[ $place++ ] ?? $found[0];
			},
			$text
		);
	}

	/**
	 * One match as an item.
	 *
	 * @param array<int|string, string|null> $found The match, unmatched groups null.
	 * @return array{kind: string, markup: string, attributes: string, block: array<string, mixed>}
	 */
	private static function item( array $found ): array {
		if ( null !== $found['block'] ) {
			$block = null === $found['json'] ? array() : json_decode( trim( $found['json'] ), true );
			$block = is_array( $block ) ? $block : array();
			$code  = is_string( $block['short_code'] ?? null ) ? $block['short_code'] : '';

			return array(
				'kind'       => LassoItem::BLOCK,
				'markup'     => (string) $found['block'],
				'attributes' => preg_match( '#^\[lasso(\s[^\]]*)?\]#', trim( $code ), $inner ) ? ( $inner[1] ?? '' ) : '',
				'block'      => $block,
			);
		}
		$kind = null !== $found['scblock'] ? LassoItem::SHORTCODE_BLOCK : ( null !== $found['attr'] ? LassoItem::ATTR : LassoItem::SHORTCODE );

		return array(
			'kind'       => $kind,
			'markup'     => (string) $found[0],
			'attributes' => (string) ( $found['scattrs'] ?? $found['aattrs'] ?? $found['sattrs'] ?? '' ),
			'block'      => array(),
		);
	}
}
