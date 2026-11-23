<?php
/**
 * What of Lasso a text still holds, and the Affibloom boxes it names.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * The check before Lasso goes (Task LM9; F10, the user's rule of 2026-11-17: no Lasso shortcode or block left behind):
 * how many of Lasso's markers a text holds (its shortcodes, `[lasso-attr]`, its block, its `data-lasso-` stamps, its
 * class names; in CSS, its `.lasso-` selectors), and the products, groups and tables the text's Affibloom boxes name
 * (Affibloom's block, `[affibloom …]`, also as Elementor keeps it, with escaped quotes), so each can be looked up. Pure:
 * no WordPress.
 */
final class LassoLeftovers {

	/** `[lasso …]`. */
	public const SHORTCODE = '[lasso';

	/** `[lasso-attr …]`. */
	public const ATTR = '[lasso-attr';

	/** Lasso's block. */
	public const BLOCK = 'wp:affiliate-plugin/lasso';

	/** Lasso's stamps. */
	public const STAMP = 'data-lasso-';

	/** A Lasso class name in a `class` attribute or a block's `className`. */
	public const CLASS_NAME = 'lasso- class';

	/** A `.lasso-` selector in CSS. */
	public const CSS = '.lasso-';

	/** The block's ID setting for each kind, a table's first. */
	private const BLOCK_IDS = array(
		'tableId'   => 'table',
		'groupId'   => 'group',
		'productId' => 'product',
	);

	/** A quote as content holds it: plain, escaped by Elementor's JSON, or JSON's ". */
	private const QUOTE = '(?:\\\\u0022|\\\\?")';

	/**
	 * How many of each of Lasso's markers a text holds; only the ones it holds.
	 *
	 * @param string $text The text.
	 * @param bool   $css  Whether the text is CSS (only `.lasso-` selectors count).
	 * @return array<string, int>
	 */
	public static function count( string $text, bool $css = false ): array {
		if ( $css ) {
			$counts = array( self::CSS => (int) preg_match_all( '/\.lasso-[A-Za-z0-9_-]/', $text ) );
		} else {
			$attr   = substr_count( $text, self::ATTR );
			$counts = array(
				self::SHORTCODE  => substr_count( $text, self::SHORTCODE ) - $attr,
				self::ATTR       => $attr,
				self::BLOCK      => (int) preg_match_all( '/<!-- wp:affiliate-plugin\/lasso(?![\w\/-])/', $text ), // A block's opening comment, not its closing one.
				self::STAMP      => (int) preg_match_all( '/(?<![\w-])data-lasso-[a-z]/', $text ),
				self::CLASS_NAME => self::classes( $text ),
			);
		}

		return array_filter( $counts );
	}

	/**
	 * The products, groups and tables the text's Affibloom boxes name, each once, in the order found.
	 *
	 * @param string $text The text.
	 * @return list<array{kind: string, ref: string}> `kind` is `product`, `group` (an ID or a slug) or `table`.
	 */
	public static function references( string $text ): array {
		$found = array();
		// Affibloom's block: its type and the ID that type uses.
		preg_match_all( '/<!-- wp:affibloom\/product-box (\{(?:(?!-->).)*\}) \/?-->/s', $text, $blocks );
		foreach ( $blocks[1] as $json ) {
			$box  = json_decode( $json, true );
			$type = is_array( $box ) ? (string) ( $box['type'] ?? 'single' ) : '';
			foreach ( self::BLOCK_IDS as $key => $kind ) {
				if ( is_array( $box ) && isset( $box[ $key ] ) && (int) $box[ $key ] > 0 && ( 'table' === $kind ) === ( 'table' === $type ) ) {
					$found[] = array(
						'kind' => $kind,
						'ref'  => (string) (int) $box[ $key ],
					);
					break;
				}
			}
		}
		// `[affibloom …]`: a table by `type="table" id`, a group by its slug, else a product by `id`.
		preg_match_all( '/\[affibloom\s([^\]]*)\]/', $text, $shortcodes );
		foreach ( $shortcodes[1] as $attributes ) {
			$value = static fn ( string $name ): string => preg_match( '/(?<![\w-])' . $name . '=' . self::QUOTE . '([^"\\\\]*)' . self::QUOTE . '/', $attributes, $m ) ? trim( $m[1] ) : '';
			$group = '' !== $value( 'group' ) ? $value( 'group' ) : ( '' !== $value( 'categories' ) ? $value( 'categories' ) : $value( 'category' ) );
			if ( 'table' === $value( 'type' ) && ctype_digit( $value( 'id' ) ) ) {
				$found[] = array(
					'kind' => 'table',
					'ref'  => $value( 'id' ),
				);
			} elseif ( '' !== $group ) {
				$found[] = array(
					'kind' => 'group',
					'ref'  => $group,
				);
			} elseif ( ctype_digit( $value( 'id' ) ) ) {
				$found[] = array(
					'kind' => 'product',
					'ref'  => $value( 'id' ),
				);
			}
		}

		return array_values( array_unique( $found, SORT_REGULAR ) );
	}

	/**
	 * Lasso's class names in `class` attributes and blocks' `className` (plain, escaped or JSON-encoded quotes).
	 *
	 * @param string $text The text.
	 */
	private static function classes( string $text ): int {
		preg_match_all( '/(?<![A-Za-z-])(?:class|className)' . self::QUOTE . '?\s*[=:]\s*' . self::QUOTE . '([^"\\\\]*)' . self::QUOTE . '/', $text, $values );
		$count = 0;
		foreach ( $values[1] as $value ) {
			$count += (int) preg_match_all( '/(?<![\w-])lasso-[A-Za-z0-9_-]/', $value );
		}

		return $count;
	}
}
