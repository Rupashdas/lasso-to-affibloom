<?php
/**
 * The posts that hold Lasso items, read from WordPress.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\LassoContent;
use AffibloomLassoMigrator\Domain\Conversion\LassoItem;

/**
 * The posts with Lasso items (Task LM2): posts, pages, reusable blocks and the types Lasso scanned (`cpt_support`), in
 * every status but revisions, autosaves and auto-drafts. Each post's content is read as blocks: a Lasso block is one
 * item (its `short_code`, its attributes), and every other block's own markup, classic content included, is searched for
 * `[lasso …]` and `[lasso-attr …]`. Read only.
 */
final class ContentHosts implements LassoContent {

	/** Lasso's block. */
	private const LASSO_BLOCK = 'affiliate-plugin/lasso';

	/** A shortcode of Lasso's: `[lasso …]` or `[lasso-attr …]` (group 1 the name, 2 the attributes). */
	private const SHORTCODE = '/\[(lasso(?:-attr)?)(\s[^\]]*)?\]/';

	/**
	 * Builds the reader.
	 *
	 * @param list<string> $types The post types Lasso scanned (its `cpt_support`; post, page and reusable blocks always).
	 */
	public function __construct( private array $types = array() ) {}

	/**
	 * The posts and their items.
	 *
	 * @param int $post One post's ID; 0 for every post.
	 * @return list<array{id: int, status: string, title: string, items: list<LassoItem>}>
	 */
	public function posts( int $post = 0 ): array {
		global $wpdb;

		$types = array_values( array_unique( array_merge( array( 'post', 'page', 'wp_block' ), $this->types ) ) );
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$one   = $post > 0 ? $wpdb->prepare( ' AND ID = %d', $post ) : '';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- a one-time read; $in holds only placeholders, $one is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_status, post_title, post_content FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status NOT IN ('auto-draft', 'inherit') AND ( post_content LIKE %s OR post_content LIKE %s ){$one} ORDER BY ID",
				...array_merge( $types, array( '%[lasso%', '%wp:' . self::LASSO_BLOCK . '%' ) )
			)
		);
		// phpcs:enable

		$posts = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$items = self::items( parse_blocks( (string) $row->post_content ) );
			if ( array() !== $items ) {
				$posts[] = array(
					'id'     => (int) $row->ID,
					'status' => (string) $row->post_status,
					'title'  => (string) $row->post_title,
					'items'  => $items,
				);
			}
		}

		return $posts;
	}

	/**
	 * The Lasso items of some blocks and their inner blocks, in order.
	 *
	 * @param array<int, array<string, mixed>> $blocks parse_blocks()'s blocks.
	 * @return list<LassoItem>
	 */
	private static function items( array $blocks ): array {
		$items = array();
		foreach ( $blocks as $block ) {
			if ( self::LASSO_BLOCK === ( $block['blockName'] ?? null ) ) {
				$attributes = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
				$markup     = is_string( $attributes['short_code'] ?? null ) ? $attributes['short_code'] : '';
				$items[]    = new LassoItem( LassoItem::BLOCK, $markup, self::attributes( preg_match( self::SHORTCODE, $markup, $match ) ? ( $match[2] ?? '' ) : '' ), $attributes );
				continue;
			}
			// The block's own markup (inner blocks are null in innerContent and read on their own).
			$own = implode( '', array_filter( is_array( $block['innerContent'] ?? null ) ? $block['innerContent'] : array(), 'is_string' ) );
			if ( preg_match_all( self::SHORTCODE, $own, $found, PREG_SET_ORDER ) ) {
				foreach ( $found as $match ) {
					$items[] = new LassoItem( 'lasso-attr' === $match[1] ? LassoItem::ATTR : LassoItem::SHORTCODE, $match[0], self::attributes( $match[2] ?? '' ) );
				}
			}
			$items = array_merge( $items, self::items( is_array( $block['innerBlocks'] ?? null ) ? $block['innerBlocks'] : array() ) );
		}

		return $items;
	}

	/**
	 * A shortcode's attributes as WordPress parses them, the named ones only.
	 *
	 * @param string $text The attributes' text.
	 * @return array<string, string>
	 */
	private static function attributes( string $text ): array {
		$named = array();
		foreach ( shortcode_parse_atts( trim( $text ) ) as $name => $value ) {
			if ( is_string( $name ) && is_scalar( $value ) ) {
				$named[ $name ] = (string) $value;
			}
		}

		return $named;
	}
}
