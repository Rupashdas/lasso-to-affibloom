<?php
/**
 * Lasso's CSS names as Affibloom's.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * Lasso's CSS names as Affibloom's (Task LM7; ADR-6, decision C1): Affibloom's box and table print Lasso's markup under
 * `affibloom-` names (`docs/ui/tokens.md`, "Product box" and "Comparison table"), so a site rule that styles
 * `.lasso-<name>` styles `.affibloom-<name>`, with the table's few renames (`lasso-container` is `affibloom-box`, …).
 * A class or id is translated only when Affibloom prints that name (or a family of them: a URL's, an anchor's, a
 * field's); one it does not print is left and reported. `--lasso-<name>` variables and Lasso's `data-` attributes
 * follow the same table. Generic names (`table-vertical`, `badge-text`, `btn`, …) are Affibloom's too and stay. Pure:
 * no WordPress.
 */
final class LassoCssMap {

	/** Lasso's names Affibloom prints under another name. */
	private const RENAMED = array(
		'container'              => 'box',
		'ds-v2-frontend'         => 'advanced',
		'v2-link-badge-override' => 'badge-override',
	);

	/** Families of names Affibloom prints with a value: a product's URL slug, an anchor, a field's slug or ID. */
	private const FAMILIES = array( 'url-', 'anchor-id-', 'fields-' );

	/** The names Affibloom's box, displays and table print, after `affibloom-` (its front-end templates and CSS). */
	private const NAMES = array( 'aawp-fields', 'advanced', 'ai', 'ai-text', 'ai-text-container', 'amazon-info', 'amazon-prime', 'background', 'badge', 'badge-override', 'badge-reserve', 'badge-text', 'box', 'box-1', 'box-2', 'box-3', 'box-4', 'box-5', 'box-6', 'brag', 'brag-grid', 'btn', 'button', 'button-1', 'button-2', 'button-container', 'button-text', 'cactus', 'check', 'check-content', 'clear', 'cons', 'copy-clipboard-btn', 'copy-clipboard-icon', 'copy-clipboard-value', 'cutter', 'date', 'description', 'disclosure', 'discount-price', 'display', 'display-table', 'end', 'feature-list', 'field-copy-clipboard', 'fields', 'flow', 'geek', 'grid', 'grid-1', 'grid-2', 'grid-3', 'grid-4', 'grid-5', 'grid-row', 'grid-wrap', 'id', 'image', 'image-container', 'image-no-lazy', 'lab', 'latest-price', 'layout-columns', 'list', 'list-content', 'list-ol', 'list-style-alpha', 'list-style-circle', 'list-style-decimal', 'list-style-hide', 'list-style-roman', 'list-style-square', 'list-title', 'llama', 'main', 'money', 'name', 'price', 'price-value', 'pros', 'secondary-button', 'single-brag', 'splash', 'stars', 'stars-label', 'stars-value', 'table', 'table-cactus-theme', 'table-cutter-theme', 'table-flow-theme', 'table-geek-theme', 'table-lab-theme', 'table-llama-theme', 'table-money-theme', 'table-splash-theme', 'table-wrapper', 'title', 'url', 'x', 'x-1', 'x-2' );

	/** Lasso's `data-` attributes in Affibloom's links and box; null: Affibloom prints none. */
	private const DATA = array(
		'box-trackable' => 'data-affibloom-box-trackable',
		'lid'           => 'data-affibloom-id',
		'name'          => 'data-affibloom-name',
		'button'        => 'data-affibloom-btn',
		'id'            => null,
		'link'          => null,
	);

	/** A Lasso name in CSS: a class or id, a variable, a `data-` attribute (`[data-lasso-lid]`), an element hidden. */
	private const TOKEN = '/(\.|#|(?<![\w-])--|(?<![\w-])data-)lasso-([A-Za-z0-9_-]*[A-Za-z0-9_])|(?<![\w-])data-v2-hide-(?=[a-z])/';

	/**
	 * Affibloom's name for a Lasso class or id (`cutter` for `lasso-cutter`), or null when Affibloom prints none.
	 *
	 * @param string $name Lasso's name, after `lasso-`.
	 */
	public static function name( string $name ): ?string {
		$name = self::RENAMED[ $name ] ?? $name;
		if ( in_array( $name, self::NAMES, true ) ) {
			return 'affibloom-' . $name;
		}
		foreach ( self::FAMILIES as $family ) {
			if ( str_starts_with( $name, $family ) && strlen( $name ) > strlen( $family ) ) {
				return 'affibloom-' . $name;
			}
		}

		return null;
	}

	/**
	 * The CSS with Lasso's names as Affibloom's, each rule whose selector names Lasso (before and after, comments left
	 * out), and the names Affibloom prints no equivalent of (left as they were).
	 *
	 * @param string $css The CSS.
	 * @return array{css: string, rules: list<array{lasso: string, affibloom: string}>, unknown: list<string>}
	 */
	public static function translate( string $css ): array {
		$unknown = array();
		$rules   = array();
		// The selectors, comments left out, that name Lasso.
		preg_match_all( '/([^{};]+)\{/', (string) preg_replace( '#/\*.*?\*/#s', '', $css ), $found );
		foreach ( $found[1] as $selector ) {
			$selector  = trim( (string) preg_replace( '/\s+/', ' ', $selector ) );
			$affibloom = str_starts_with( $selector, '@' ) ? $selector : self::tokens( $selector, $unknown );
			// A selector that names Lasso only where Affibloom has no equivalent is reported by that name alone.
			if ( $affibloom !== $selector ) {
				$rules[] = array(
					'lasso'     => $selector,
					'affibloom' => $affibloom,
				);
			}
		}

		return array(
			'css'     => self::tokens( $css, $unknown ),
			'rules'   => $rules,
			'unknown' => array_values( array_unique( $unknown ) ),
		);
	}

	/**
	 * A text with each Lasso name translated, where Affibloom has one.
	 *
	 * @param string       $text    The text.
	 * @param list<string> $unknown The names with no equivalent, added to.
	 */
	private static function tokens( string $text, array &$unknown ): string {
		return (string) preg_replace_callback(
			self::TOKEN,
			static function ( array $token ) use ( &$unknown ): string {
				if ( ! isset( $token[1] ) ) { // data-v2-hide-: the first alternative's groups are not set.
					return 'data-hide-';
				}
				[ $all, $kind, $name ] = $token;
				if ( '--' === $kind ) {
					return '--affibloom-' . $name;
				}
				if ( 'data-' === $kind ) {
					$data = array_key_exists( $name, self::DATA ) ? self::DATA[ $name ] : 'data-affibloom-' . $name;
					if ( null === $data ) {
						$unknown[] = $all;

						return $all;
					}

					return $data;
				}
				$affibloom = self::name( $name );
				if ( null === $affibloom ) {
					$unknown[] = $all;

					return $all;
				}

				return $kind . $affibloom;
			},
			$text
		);
	}
}
