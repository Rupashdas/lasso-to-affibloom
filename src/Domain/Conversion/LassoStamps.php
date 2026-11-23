<?php
/**
 * Lasso's stamps in a text.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Lasso's stamps in a text (Task LM6; F34 R18, D2, D4, AC6; F21 R3, R37): the `data-lasso-id` (its link-location row),
 * `data-lasso-lid` (the Lasso record it tied the link to) and `data-lasso-name` attributes its scan wrote into tags,
 * Kadence's `singlebtn` included; AAWP's `lasso-ll-do-not-delete-<n>` class; and `lasso-scan-ignore`, which becomes
 * `affibloom-scan-ignore`, the class Affibloom's scan skips. Tags are read as HTML and as HTML inside a block's JSON
 * (`<…>` with `"` quotes, or `\"` quotes), so a block's comment and its HTML stay alike. Every other byte
 * is kept. Pure: no WordPress.
 */
final class LassoStamps {

	/** The link-location row Lasso stamped. */
	public const ID = 'data-lasso-id';

	/** The Lasso record a link is tied to. */
	public const LID = 'data-lasso-lid';

	/** That record's name. */
	public const NAME = 'data-lasso-name';

	/** AAWP's class with Lasso's link-location row. */
	public const KEEP_CLASS = 'lasso-ll-do-not-delete';

	/** The class Lasso's scan skips; Affibloom's is `affibloom-scan-ignore`. */
	public const IGNORE = 'lasso-scan-ignore';

	/** A tag, as HTML or as HTML encoded in JSON. */
	private const TAG = '/<[a-zA-Z][^<>]*>|\\\\u003c[a-zA-Z](?:(?!\\\\u003e).)*\\\\u003e/s';

	/** An attribute's value, in each quoting: `"…"`, `"…"`, `\"…\"`. */
	private const VALUE = '("[^"]*"|\\\\u0022(?:(?!\\\\u0022).)*?\\\\u0022|\\\\"(?:(?!\\\\").)*?\\\\")';

	/**
	 * The text without Lasso's stamps, how many of each it had, and the links Lasso tied to a record.
	 *
	 * @param string $text The text.
	 * @return array{text: string, counts: array<string, int>, ties: list<array{url: string, lasso: int, name: string}>}
	 */
	public static function clean( string $text ): array {
		$counts = array_fill_keys( array( self::ID, self::LID, self::NAME, self::KEEP_CLASS, self::IGNORE ), 0 );
		$ties   = array();
		$text   = (string) preg_replace_callback(
			self::TAG,
			static function ( array $tag ) use ( &$counts, &$ties ): string {
				$found = array();
				$clean = (string) preg_replace_callback(
					'/\s+(' . self::ID . '|' . self::LID . '|' . self::NAME . ')=' . self::VALUE . '/s',
					static function ( array $attribute ) use ( &$counts, &$found ): string {
						++$counts[ $attribute[1] ];
						$found[ $attribute[1] ] = self::value( $attribute[2] );

						return '';
					},
					$tag[0]
				);
				$href  = preg_match( '/\shref=' . self::VALUE . '/s', $tag[0], $link ) ? self::value( $link[1] ) : '';
				if ( isset( $found[ self::LID ] ) && '' !== $href && ctype_digit( $found[ self::LID ] ) ) {
					$ties[] = array(
						'url'   => $href,
						'lasso' => (int) $found[ self::LID ],
						'name'  => $found[ self::NAME ] ?? '',
					);
				}

				return $clean;
			},
			$text
		);
		// AAWP's class goes with the space before it, or after it at the start of a value.
		$text = (string) preg_replace( '/\s' . self::KEEP_CLASS . '-\d+(?![\w-])|(?<![\w-])' . self::KEEP_CLASS . '-\d+(?![\w-]) ?/', '', $text, -1, $kept );
		$text = (string) preg_replace( '/(?<![\w-])' . self::IGNORE . '(?![\w-])/', 'affibloom-scan-ignore', $text, -1, $ignored );

		$counts[ self::KEEP_CLASS ] = $kept;
		$counts[ self::IGNORE ]     = $ignored;

		return array(
			'text'   => $text,
			'counts' => $counts,
			'ties'   => $ties,
		);
	}

	/**
	 * An attribute's value as the browser reads it: its quotes and JSON escapes gone, its entities decoded.
	 *
	 * @param string $quoted The value with its quotes.
	 */
	private static function value( string $quoted ): string {
		if ( str_starts_with( $quoted, '\\u0022' ) ) {
			$decoded = json_decode( '"' . substr( $quoted, 6, -6 ) . '"' );
			$inner   = is_string( $decoded ) ? $decoded : '';
		} elseif ( str_starts_with( $quoted, '\\"' ) ) {
			$inner = stripslashes( substr( $quoted, 2, -2 ) );
		} else {
			$inner = substr( $quoted, 1, -1 );
		}

		return html_entity_decode( $inner, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}
