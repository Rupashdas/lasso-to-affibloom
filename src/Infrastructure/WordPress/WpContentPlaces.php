<?php
/**
 * Every place the site's content lives, as WordPress keeps it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\ContentPlaces;

/**
 * Every place the site's content lives (Task LM9), each named and linked for the check's list: posts of every type and
 * status (revisions, auto-drafts, the Customizer's and Lasso's own records aside; reusable blocks included), their
 * Elementor data, the ACF fields Affibloom scans (its `scan_custom_fields`, matched as its scan does), text, HTML and
 * block widgets, and the active theme's Additional CSS. Only places whose text names Lasso or Affibloom are read, in
 * pages of 200 posts.
 */
final class WpContentPlaces implements ContentPlaces {

	/** Post types that hold no content of the site's. */
	private const SKIPPED = array( 'revision', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'lasso-urls' );

	/** The widget options and the setting that holds each one's text. */
	private const WIDGETS = array(
		'widget_text'        => 'text',
		'widget_custom_html' => 'content',
		'widget_block'       => 'content',
	);

	/**
	 * Builds the places.
	 *
	 * @param list<string> $fields The ACF fields Affibloom scans (its `scan_custom_fields`).
	 */
	public function __construct( private array $fields = array() ) {}

	/**
	 * The places that name Lasso or Affibloom.
	 *
	 * @return iterable<array{title: string, edit: string, text: string, css: bool}>
	 */
	public function places(): iterable {
		yield from $this->posts();
		yield from $this->meta( '_elementor_data', static fn ( string $title ): string => sprintf( /* translators: %s: a post's title. */ __( 'Elementor data of %s', 'affibloom-lasso-migrator' ), $title ) );
		foreach ( array_filter( $this->fields ) as $field ) {
			yield from $this->meta( $field, static fn ( string $title, string $key ): string => sprintf( /* translators: 1: a post's title; 2: a custom field. */ __( '%1$s, field %2$s', 'affibloom-lasso-migrator' ), $title, $key ), true );
		}
		yield from $this->widgets();
		$css = wp_get_custom_css_post();
		if ( $css instanceof \WP_Post && '' !== $css->post_content ) {
			yield array(
				/* translators: %s: the active theme's folder name. */
				'title' => sprintf( __( 'Additional CSS (%s)', 'affibloom-lasso-migrator' ), get_stylesheet() ),
				'edit'  => admin_url( 'customize.php?autofocus[section]=custom_css' ),
				'text'  => (string) $css->post_content,
				'css'   => true,
			);
		}
	}

	/**
	 * Posts whose content names Lasso or Affibloom, by ID, 200 at a time.
	 *
	 * @return \Generator<array{title: string, edit: string, text: string, css: bool}>
	 */
	private function posts(): \Generator {
		global $wpdb;

		$skip = implode( ',', array_fill( 0, count( self::SKIPPED ), '%s' ) );
		$last = 0;
		do {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- a one-time read in pages; $skip holds only placeholders.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE ID > %d AND post_type NOT IN ($skip) AND post_status <> 'auto-draft' AND ( post_content LIKE %s OR post_content LIKE %s ) ORDER BY ID LIMIT 200",
					...array_merge( array( $last ), self::SKIPPED, array( '%lasso%', '%affibloom%' ) )
				)
			);
			// phpcs:enable
			$rows = is_array( $rows ) ? $rows : array();
			$size = count( $rows );
			foreach ( $rows as $row ) {
				$last = (int) $row->ID;
				yield array(
					'title' => self::title( $last, (string) $row->post_title ),
					'edit'  => self::edit( $last ),
					'text'  => (string) $row->post_content,
					'css'   => false,
				);
			}
		} while ( 200 === $size );
	}

	/**
	 * Post meta values that name Lasso or Affibloom: one key, or (for an ACF field) every key ending in it but ACF's own
	 * references, which start with "_".
	 *
	 * @param string   $key    The meta key, or the ACF field's name.
	 * @param callable $name   The place's name from the post's title (and, for a field, its key).
	 * @param bool     $ending Whether the key is a field name the meta key ends with (repeaters and groups prefix it).
	 * @return \Generator<array{title: string, edit: string, text: string, css: bool}>
	 */
	private function meta( string $key, callable $name, bool $ending = false ): \Generator {
		global $wpdb;

		$match = $ending ? $wpdb->prepare( 'meta_key LIKE %s AND meta_key NOT LIKE %s', '%' . $wpdb->esc_like( $key ), $wpdb->esc_like( '_' ) . '%' ) : $wpdb->prepare( 'meta_key = %s', $key );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- a one-time read; $match is prepared.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE {$match} AND ( meta_value LIKE %s OR meta_value LIKE %s ) ORDER BY post_id", '%lasso%', '%affibloom%' ) );
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$post = (int) $row->post_id;
			yield array(
				'title' => $name( self::title( $post, get_the_title( $post ) ), (string) $row->meta_key ),
				'edit'  => self::edit( $post ),
				'text'  => (string) $row->meta_value,
				'css'   => false,
			);
		}
	}

	/**
	 * The text, HTML and block widgets that name Lasso or Affibloom.
	 *
	 * @return \Generator<array{title: string, edit: string, text: string, css: bool}>
	 */
	private function widgets(): \Generator {
		foreach ( self::WIDGETS as $option => $setting ) {
			$widgets = get_option( $option );
			foreach ( is_array( $widgets ) ? $widgets : array() as $number => $widget ) {
				$text = is_array( $widget ) && is_string( $widget[ $setting ] ?? null ) ? $widget[ $setting ] : '';
				if ( str_contains( $text, 'lasso' ) || str_contains( $text, 'affibloom' ) ) {
					yield array(
						/* translators: 1: a widget's kind, such as widget_text; 2: its number. */
						'title' => sprintf( __( 'Widget %1$s #%2$s', 'affibloom-lasso-migrator' ), $option, (string) $number ),
						'edit'  => admin_url( 'widgets.php' ),
						'text'  => $text,
						'css'   => false,
					);
				}
			}
		}
	}

	/**
	 * A post's edit link, built here: get_edit_post_link() gives none without a signed-in user, as in WP-CLI.
	 *
	 * @param int $post The post.
	 */
	private static function edit( int $post ): string {
		return admin_url( 'post.php?post=' . $post . '&action=edit' );
	}

	/**
	 * A post's name for the list: its title, or its ID when it has none.
	 *
	 * @param int    $post  The post.
	 * @param string $title Its title.
	 */
	private static function title( int $post, string $title ): string {
		/* translators: %d: a post's ID. */
		return '' === trim( $title ) ? sprintf( __( 'Post %d (no title)', 'affibloom-lasso-migrator' ), $post ) : $title;
	}
}
