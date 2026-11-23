<?php
/**
 * GenerateBlocks buttons as GenerateBlocks saves them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * GenerateBlocks buttons whose settings and HTML Lasso made disagree (Task LM6A, deviation 160): Lasso's editor script
 * read a button's `target` and `rel` from its HTML, so saving under Lasso dropped `"target":true` from the block's
 * settings, and Lasso's scan rewrote `rel` (no `noreferrer`; `nofollow`, `sponsored` from its own link settings).
 * Without Lasso the editor flags such a button. What readers get is kept: the settings follow the HTML (`target` from
 * `target="_blank"`, `relNoFollow` and `relSponsored` from `rel`), and `rel` becomes what GenerateBlocks saves from them:
 * `nofollow`, `noopener noreferrer` for a new tab, `sponsored`, or no `rel`. A button that already agrees is not
 * touched; one whose settings would not be written back exactly as WordPress writes them is reported. Pure: no
 * WordPress.
 */
final class GenerateBlocksButtons {

	/** A GenerateBlocks button with its settings and its HTML. */
	private const BLOCK = '/<!-- wp:generateblocks\/button (\{(?:(?!-->).)*\}) -->(.*?)<!-- \/wp:generateblocks\/button -->/s';

	/**
	 * The text with its buttons repaired, how many were, and the settings of those it could not rewrite exactly.
	 *
	 * @param string $text The text.
	 * @return array{text: string, repaired: int, skipped: list<string>}
	 */
	public static function repair( string $text ): array {
		$repaired = 0;
		$skipped  = array();
		$text     = (string) preg_replace_callback(
			self::BLOCK,
			static function ( array $block ) use ( &$repaired, &$skipped ): string {
				$fixed = self::button( $block[1], $block[2] );
				if ( null === $fixed ) {
					return $block[0];
				}
				if ( false === $fixed ) {
					$skipped[] = $block[1];

					return $block[0];
				}
				++$repaired;

				return '<!-- wp:generateblocks/button ' . $fixed[0] . ' -->' . $fixed[1] . '<!-- /wp:generateblocks/button -->';
			},
			$text
		);

		return array(
			'text'     => $text,
			'repaired' => $repaired,
			'skipped'  => $skipped,
		);
	}

	/**
	 * One button: its settings and HTML repaired; null when it agrees (or has no link); false when its settings would not
	 * be written back exactly.
	 *
	 * @param string $json Its settings as stored.
	 * @param string $html Its HTML.
	 * @return array{string, string}|false|null
	 */
	private static function button( string $json, string $html ): array|false|null {
		$attributes = json_decode( $json, true );
		if ( ! is_array( $attributes ) || ! preg_match( '/<a\s[^>]*>/', $html, $tag, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}
		$a     = $tag[0][0];
		$rel   = preg_match( '/\srel="([^"]*)"/', $a, $found ) ? preg_split( '/\s+/', trim( $found[1] ) ) : array();
		$blank = (bool) preg_match( '/\starget="_blank"/', $a );

		// The settings as the HTML says, then rel as GenerateBlocks writes it from them.
		$wanted = $attributes;
		self::flag( $wanted, 'target', $blank );
		self::flag( $wanted, 'relNoFollow', in_array( 'nofollow', $rel, true ) );
		self::flag( $wanted, 'relSponsored', in_array( 'sponsored', $rel, true ) );
		$save = array_merge( in_array( 'nofollow', $rel, true ) ? array( 'nofollow' ) : array(), $blank ? array( 'noopener', 'noreferrer' ) : array(), in_array( 'sponsored', $rel, true ) ? array( 'sponsored' ) : array() );

		if ( $attributes === $wanted && array_values( array_filter( $rel ) ) === $save ) {
			return null;
		}
		if ( self::encode( $attributes ) !== $json ) {
			return false;
		}

		$value = implode( ' ', $save );
		if ( preg_match( '/\srel="[^"]*"/', $a ) ) {
			$fixed = (string) preg_replace( '/\srel="[^"]*"/', '' === $value ? '' : ' rel="' . $value . '"', $a, 1 );
		} elseif ( '' !== $value ) {
			$fixed = (string) preg_replace( '/\shref="[^"]*"/', '$0 rel="' . $value . '"', $a, 1 );
		} else {
			$fixed = $a;
		}

		return array( self::encode( $wanted ), substr_replace( $html, $fixed, $tag[0][1], strlen( $a ) ) );
	}

	/**
	 * A true/false setting: true when on; when off, a true one goes (GenerateBlocks' default) and a stored false stays.
	 *
	 * @param array<string, mixed> $attributes The settings.
	 * @param string               $name       The setting.
	 * @param bool                 $on         Whether it is on.
	 */
	private static function flag( array &$attributes, string $name, bool $on ): void {
		if ( $on ) {
			$attributes[ $name ] = true;
		} elseif ( ! empty( $attributes[ $name ] ) ) {
			unset( $attributes[ $name ] );
		}
	}

	/**
	 * Settings as WordPress writes them in a block's comment (serialize_block_attributes()).
	 *
	 * @param array<string, mixed> $attributes The settings.
	 */
	private static function encode( array $attributes ): string {
		$json = (string) json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the Domain has no WordPress; the escapes below are serialize_block_attributes()'s.

		return str_replace( array( '--', '<', '>', '&', '\\"' ), array( '\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022' ), $json );
	}
}
