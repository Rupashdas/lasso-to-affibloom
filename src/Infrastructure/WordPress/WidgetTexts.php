<?php
/**
 * Widgets' text, as the conversion reads and writes it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\TextStore;

/**
 * The text, custom HTML and block widgets (Task LM4; F10 AC26): each instance's text with Lasso's markup, keyed
 * `<option>:<instance>`. A place is a widget option: before its first conversion the whole option is kept once in
 * `affibloom_lasso_pre_conversion_widgets`, with the md5 of what was last written, and revert puts the option back,
 * refusing one changed since unless forced.
 */
final class WidgetTexts implements TextStore {

	/** The widget options and the instance field that holds their text. */
	private const WIDGETS = array(
		'widget_text'        => 'text',
		'widget_custom_html' => 'content',
		'widget_block'       => 'content',
	);

	/** The copies: option → array{before: mixed, written: string}. */
	public const COPY = 'affibloom_lasso_pre_conversion_widgets';

	/**
	 * What a place is, for the report.
	 */
	public function name(): string {
		return 'widget option';
	}

	/**
	 * The widget texts with Lasso's markup; none for one post (widgets belong to no post).
	 *
	 * @param int $post One post; widgets are listed only for 0.
	 * @return list<array{key: string, post: int, place: string, status: string, text: string}>
	 */
	public function texts( int $post = 0 ): array {
		if ( $post > 0 ) {
			return array();
		}
		$texts = array();
		foreach ( self::WIDGETS as $option => $field ) {
			$instances = get_option( $option );
			foreach ( is_array( $instances ) ? $instances : array() as $number => $instance ) {
				$text = is_array( $instance ) && is_string( $instance[ $field ] ?? null ) ? $instance[ $field ] : '';
				if ( str_contains( $text, '[lasso' ) || str_contains( $text, 'wp:affiliate-plugin/lasso' ) ) {
					$texts[] = array(
						'key'    => "$option:$number",
						'post'   => 0,
						'place'  => "$option #$number",
						'status' => 'widget',
						'text'   => $text,
					);
				}
			}
		}

		return $texts;
	}

	/**
	 * Writes one widget's converted text, keeping its option as it was before the first conversion.
	 *
	 * @param string $key    `<option>:<instance>`.
	 * @param string $before The text now.
	 * @param string $after  The converted text.
	 */
	public function write( string $key, string $before, string $after ): bool {
		[ $option, $number ] = array_pad( explode( ':', $key, 2 ), 2, '' );
		$field               = self::WIDGETS[ $option ] ?? null;
		$instances           = get_option( $option );
		if ( null === $field || ! is_array( $instances ) || ( $instances[ $number ][ $field ] ?? null ) !== $before ) {
			return false;
		}
		$copies = get_option( self::COPY, array() );
		$copies = is_array( $copies ) ? $copies : array();
		if ( ! isset( $copies[ $option ] ) ) {
			$copies[ $option ] = array( 'before' => $instances );
		}
		$instances[ $number ][ $field ] = $after;
		update_option( $option, $instances );
		if ( ( get_option( $option )[ $number ][ $field ] ?? null ) !== $after ) {
			return false;
		}
		$copies[ $option ]['written'] = md5( (string) wp_json_encode( $instances ) );
		update_option( self::COPY, $copies, false );

		return true;
	}

	/**
	 * The widget options with a copy; none for one post.
	 *
	 * @param int $post One post; widgets are listed only for 0.
	 * @return list<string>
	 */
	public function converted( int $post = 0 ): array {
		$copies = get_option( self::COPY, array() );

		return $post > 0 || ! is_array( $copies ) ? array() : array_map( 'strval', array_keys( $copies ) );
	}

	/**
	 * Puts a widget option back as it was before its first conversion.
	 *
	 * @param string $place The option.
	 * @param bool   $force Revert an option changed after its conversion too.
	 */
	public function revert( string $place, bool $force ): string {
		$copies = get_option( self::COPY, array() );
		if ( ! is_array( $copies ) || ! isset( $copies[ $place ] ) ) {
			return self::NONE;
		}
		if ( ! $force && md5( (string) wp_json_encode( get_option( $place ) ) ) !== ( $copies[ $place ]['written'] ?? '' ) ) {
			return self::EDITED;
		}
		update_option( $place, $copies[ $place ]['before'] );
		if ( get_option( $place ) !== $copies[ $place ]['before'] ) {
			return self::FAILED;
		}
		unset( $copies[ $place ] );
		if ( array() === $copies ) {
			delete_option( self::COPY );
		} else {
			update_option( self::COPY, $copies, false );
		}

		return self::REVERTED;
	}
}
