<?php
/**
 * The active theme's Additional CSS, as the CSS translation reads and writes it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\TextStore;

/**
 * The active theme's Additional CSS (Task LM7; ADR-6, C1): its `custom_css` post, keyed by the theme's stylesheet,
 * listed when it names Lasso. A write goes through wp_update_custom_css_post() (a revision is kept, as the Customizer
 * does), with kses off as for posts (WP-CLI has no user that may save unfiltered CSS, and `>` must stay `>`); it is read
 * back and put back when it did not store exactly. The CSS before the first translation is kept once in the option
 * `affibloom_lasso_pre_css`, the md5 of what was written in `affibloom_lasso_css_md5`, both by stylesheet.
 */
final class CustomCssTexts implements TextStore {

	/** The CSS before its first translation, by stylesheet. */
	public const COPY = 'affibloom_lasso_pre_css';

	/** The md5 of the CSS the translation wrote, by stylesheet. */
	public const WRITTEN = 'affibloom_lasso_css_md5';

	/**
	 * What a place is, for the report.
	 */
	public function name(): string {
		return 'Additional CSS of';
	}

	/**
	 * The active theme's CSS when it names Lasso.
	 *
	 * @param int $post One post; the CSS is no post's, so it is listed only for 0.
	 * @return list<array{key: string, post: int, place: string, status: string, text: string}>
	 */
	public function texts( int $post = 0 ): array {
		$theme = get_stylesheet();
		$css   = $this->css( $theme );
		if ( $post > 0 || null === $css || ! str_contains( $css, 'lasso-' ) ) {
			return array();
		}

		return array(
			array(
				'key'    => $theme,
				'post'   => 0,
				'place'  => "Additional CSS ({$theme})",
				'status' => 'css',
				'text'   => $css,
			),
		);
	}

	/**
	 * Writes a theme's translated CSS, keeping a copy of its first CSS.
	 *
	 * @param string $key    The theme's stylesheet.
	 * @param string $before Its CSS now.
	 * @param string $after  Its translated CSS.
	 */
	public function write( string $key, string $before, string $after ): bool {
		if ( $this->css( $key ) !== $before ) {
			return false;
		}
		if ( ! $this->save( $key, $after ) ) {
			$this->save( $key, $before );

			return false;
		}
		$copies = $this->option( self::COPY );
		if ( ! isset( $copies[ $key ] ) ) {
			$copies[ $key ] = $before;
			update_option( self::COPY, $copies, false );
		}
		$written         = $this->option( self::WRITTEN );
		$written[ $key ] = md5( $after );
		update_option( self::WRITTEN, $written, false );

		return true;
	}

	/**
	 * The themes with a copy.
	 *
	 * @param int $post Only 0 lists them; the CSS is no post's.
	 * @return list<string>
	 */
	public function converted( int $post = 0 ): array {
		return $post > 0 ? array() : array_map( 'strval', array_keys( $this->option( self::COPY ) ) );
	}

	/**
	 * Puts a theme's copy back and forgets it.
	 *
	 * @param string $place The theme's stylesheet.
	 * @param bool   $force Revert CSS edited after its translation too.
	 */
	public function revert( string $place, bool $force ): string {
		$copies = $this->option( self::COPY );
		$now    = $this->css( $place );
		if ( ! isset( $copies[ $place ] ) || null === $now ) {
			return self::NONE;
		}
		if ( ! $force && md5( $now ) !== ( $this->option( self::WRITTEN )[ $place ] ?? '' ) ) {
			return self::EDITED;
		}
		if ( ! $this->save( $place, $copies[ $place ] ) ) {
			$this->save( $place, $now );

			return self::FAILED;
		}
		$written = $this->option( self::WRITTEN );
		unset( $copies[ $place ], $written[ $place ] );
		self::keep( self::COPY, $copies );
		self::keep( self::WRITTEN, $written );

		return self::REVERTED;
	}

	/**
	 * A theme's CSS as stored (no filter); null when it has none.
	 *
	 * @param string $theme The theme's stylesheet.
	 */
	private function css( string $theme ): ?string {
		$post = wp_get_custom_css_post( $theme );

		return $post instanceof \WP_Post ? (string) $post->post_content : null;
	}

	/**
	 * Saves a theme's CSS without kses and says whether it stored exactly that.
	 *
	 * @param string $theme The theme's stylesheet.
	 * @param string $css   The CSS.
	 */
	private function save( string $theme, string $css ): bool {
		$filtered = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $filtered ) {
			kses_remove_filters();
		}
		$saved = wp_update_custom_css_post( $css, array( 'stylesheet' => $theme ) );
		if ( $filtered ) {
			kses_init_filters();
		}
		if ( $saved instanceof \WP_Post ) {
			clean_post_cache( $saved->ID );
		}

		return ! is_wp_error( $saved ) && $this->css( $theme ) === $css;
	}

	/**
	 * Keeps an option holding values by stylesheet; an empty one is deleted, so a full revert leaves nothing.
	 *
	 * @param string                $name   The option.
	 * @param array<string, string> $values The values.
	 */
	private static function keep( string $name, array $values ): void {
		if ( array() === $values ) {
			delete_option( $name );
			return;
		}
		update_option( $name, $values, false );
	}

	/**
	 * An option holding values by stylesheet.
	 *
	 * @param string $name The option.
	 * @return array<string, string>
	 */
	private function option( string $name ): array {
		$value = get_option( $name, array() );

		return is_array( $value ) ? $value : array();
	}
}
