<?php
/**
 * Elementor's data, as the conversion reads and writes it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\TextStore;

/**
 * Elementor's data (`_elementor_data`; Task LM4, F10 AC26): every string setting with `[lasso`, and the shortcode of
 * Lasso's own Elementor widget (`lasso_shortcode`, F4 R65), keyed `<post>|<element>|<setting>`. A converted Lasso widget
 * becomes Affibloom's (`affibloom`, its control `affibloom_shortcode`; ED2). A place is a post's Elementor data: before
 * its first conversion the meta is kept once in `_affibloom_lasso_pre_conversion_elementor`, with the md5 of what was
 * last written, and revert puts it back, refusing data changed since unless forced. Elementor's CSS and element caches
 * of the post are cleared on each write, so it rebuilds them.
 */
final class ElementorTexts implements TextStore {

	/** Elementor's data. */
	private const DATA = '_elementor_data';

	/** The post's Elementor data before its first conversion. */
	public const COPY = '_affibloom_lasso_pre_conversion_elementor';

	/** The md5 of the data the last conversion wrote. */
	public const WRITTEN = '_affibloom_lasso_converted_elementor_md5';

	/** Lasso's Elementor widget and its shortcode control. */
	private const LASSO_WIDGET = 'lasso_shortcode';

	/** Affibloom's Elementor widget (ElementorDisplayWidget::NAME) and its control (::SHORTCODE). */
	private const AFFIBLOOM_WIDGET = array( 'affibloom', 'affibloom_shortcode' );

	/**
	 * What a place is, for the report.
	 */
	public function name(): string {
		return 'Elementor data of post';
	}

	/**
	 * The texts with Lasso's markup in posts' Elementor data, in post order.
	 *
	 * @param int $post One post; 0 for all.
	 * @return list<array{key: string, post: int, place: string, status: string, text: string}>
	 */
	public function texts( int $post = 0 ): array {
		$texts = array();
		foreach ( $this->posts( $post ) as $id ) {
			$status = (string) get_post_status( $id );
			foreach ( self::strings( $this->data( $id ) ) as $path => $text ) {
				$texts[] = array(
					'key'    => "$id|$path",
					'post'   => $id,
					'place'  => 'elementor',
					'status' => $status,
					'text'   => $text,
				);
			}
		}

		return $texts;
	}

	/**
	 * Writes one converted setting, keeping the post's data as it was before the first conversion.
	 *
	 * @param string $key    `<post>|<element>|<setting>`.
	 * @param string $before The setting now.
	 * @param string $after  The converted setting.
	 */
	public function write( string $key, string $before, string $after ): bool {
		[ $post, $element, $setting ] = array_pad( explode( '|', $key, 3 ), 3, '' );
		$post                         = (int) $post;
		$raw                          = (string) get_post_meta( $post, self::DATA, true );
		$data                         = $this->data( $post );
		if ( ! self::set( $data, $element, $setting, $before, $after ) ) {
			return false;
		}
		if ( '' === (string) get_post_meta( $post, self::COPY, true ) ) {
			update_post_meta( $post, self::COPY, wp_slash( $raw ) );
		}
		$json = (string) wp_json_encode( $data );
		update_post_meta( $post, self::DATA, wp_slash( $json ) );
		if ( (string) get_post_meta( $post, self::DATA, true ) !== $json ) {
			update_post_meta( $post, self::DATA, wp_slash( $raw ) );

			return false;
		}
		update_post_meta( $post, self::WRITTEN, md5( $json ) );
		self::clear_caches( $post );

		return true;
	}

	/**
	 * The posts with a copy of their Elementor data.
	 *
	 * @param int $post One post; 0 for all.
	 * @return list<string>
	 */
	public function converted( int $post = 0 ): array {
		global $wpdb;

		$one = $post > 0 ? $wpdb->prepare( ' AND post_id = %d', $post ) : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- one list for a revert run; $one is prepared.
		return array_map( 'strval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s{$one} ORDER BY post_id", self::COPY ) ) );
	}

	/**
	 * Puts a post's Elementor data back as it was before its first conversion.
	 *
	 * @param string $place The post's ID.
	 * @param bool   $force Revert data changed after its conversion too.
	 */
	public function revert( string $place, bool $force ): string {
		$post = (int) $place;
		$copy = get_post_meta( $post, self::COPY, true );
		if ( ! is_string( $copy ) || '' === $copy ) {
			return self::NONE;
		}
		if ( ! $force && md5( (string) get_post_meta( $post, self::DATA, true ) ) !== (string) get_post_meta( $post, self::WRITTEN, true ) ) {
			return self::EDITED;
		}
		update_post_meta( $post, self::DATA, wp_slash( $copy ) );
		if ( (string) get_post_meta( $post, self::DATA, true ) !== $copy ) {
			return self::FAILED;
		}
		delete_post_meta( $post, self::COPY );
		delete_post_meta( $post, self::WRITTEN );
		self::clear_caches( $post );

		return self::REVERTED;
	}

	/**
	 * The posts whose Elementor data mentions Lasso.
	 *
	 * @param int $post One post; 0 for all.
	 * @return list<int>
	 */
	private function posts( int $post ): array {
		global $wpdb;

		$one = $post > 0 ? $wpdb->prepare( ' AND post_id = %d', $post ) : '';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a one-time read; $one is prepared.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s{$one} ORDER BY post_id", self::DATA, '%lasso%' ) ) );
	}

	/**
	 * A post's Elementor data, decoded; empty when there is none.
	 *
	 * @param int $post The post.
	 * @return array<int|string, mixed>
	 */
	private function data( int $post ): array {
		$data = json_decode( (string) get_post_meta( $post, self::DATA, true ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Every string setting with `[lasso` and every Lasso widget's shortcode, by `<element>|<setting>`.
	 *
	 * @param array<int|string, mixed> $elements Elements.
	 * @return array<string, string>
	 */
	private static function strings( array $elements ): array {
		$found = array();
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$id       = (string) ( $element['id'] ?? '' );
			$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
			foreach ( $settings as $name => $value ) {
				$lasso = self::LASSO_WIDGET === ( $element['widgetType'] ?? '' ) && self::LASSO_WIDGET === $name;
				if ( is_string( $value ) && ( $lasso || str_contains( $value, '[lasso' ) ) ) {
					$found[ "$id|$name" ] = $value;
				}
			}
			$found += self::strings( is_array( $element['elements'] ?? null ) ? $element['elements'] : array() );
		}

		return $found;
	}

	/**
	 * Sets one element's setting when it is still `$before`; a Lasso widget's shortcode makes it Affibloom's widget.
	 *
	 * @param array<int|string, mixed> $elements Elements, changed in place.
	 * @param string                   $id       The element's ID.
	 * @param string                   $name     The setting.
	 * @param string                   $before   Its value now.
	 * @param string                   $after    Its new value.
	 */
	private static function set( array &$elements, string $id, string $name, string $before, string $after ): bool {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( (string) ( $element['id'] ?? '' ) === $id ) {
				if ( ( $element['settings'][ $name ] ?? null ) !== $before ) {
					return false;
				}
				if ( self::LASSO_WIDGET === ( $element['widgetType'] ?? '' ) && self::LASSO_WIDGET === $name ) {
					unset( $element['settings'][ $name ] );
					$element['widgetType']                            = self::AFFIBLOOM_WIDGET[0];
					$element['settings'][ self::AFFIBLOOM_WIDGET[1] ] = $after;
				} else {
					$element['settings'][ $name ] = $after;
				}

				return true;
			}
			if ( is_array( $element['elements'] ?? null ) && self::set( $element['elements'], $id, $name, $before, $after ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clears Elementor's caches of a post, which it rebuilds from the data.
	 *
	 * @param int $post The post.
	 */
	private static function clear_caches( int $post ): void {
		delete_post_meta( $post, '_elementor_css' );
		delete_post_meta( $post, '_elementor_element_cache' );
	}
}
