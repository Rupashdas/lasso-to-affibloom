<?php
/**
 * Posts' content, as the conversion reads and writes it.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\TextStore;

/**
 * Posts' content (Tasks LM2-LM4; F10 D16, D17): posts, pages, reusable blocks and the types Lasso scanned (`cpt_support`),
 * every status but revisions, autosaves and auto-drafts, whose content holds Lasso's markup; a post's key is its ID.
 * A write goes through wp_update_post(), so its save hooks run and a revision is kept, with WordPress's kses filter off
 * as an importer's (WP-CLI has no user that may post unfiltered HTML, and the post's own markup must stay as it is). It
 * is read back: a post that did not store exactly what was written (another plugin's save hook changed it) is put back.
 * The content before the first conversion is kept once in `_affibloom_lasso_pre_conversion`, the md5 of what was last
 * written in `_affibloom_lasso_converted_md5`; revert refuses a post whose content is no longer that.
 */
final class PostTexts implements TextStore {

	/** The post's content before its first conversion. */
	public const COPY = '_affibloom_lasso_pre_conversion';

	/** The md5 of the content the last conversion wrote. */
	public const WRITTEN = '_affibloom_lasso_converted_md5';

	/**
	 * Builds the store.
	 *
	 * @param list<string> $types The post types Lasso scanned (post, page and reusable blocks always).
	 */
	public function __construct( private array $types = array() ) {}

	/**
	 * What a place is, for the report.
	 */
	public function name(): string {
		return 'post';
	}

	/**
	 * The posts with Lasso's markup, in ID order.
	 *
	 * @param int $post One post; 0 for all.
	 * @return list<array{key: string, post: int, place: string, status: string, text: string}>
	 */
	public function texts( int $post = 0 ): array {
		global $wpdb;

		$types = array_values( array_unique( array_merge( array( 'post', 'page', 'wp_block' ), $this->types ) ) );
		$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$one   = $post > 0 ? $wpdb->prepare( ' AND ID = %d', $post ) : '';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- a one-time read; $in holds only placeholders, $one is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_status, post_content FROM {$wpdb->posts} WHERE post_type IN ($in) AND post_status NOT IN ('auto-draft', 'inherit') AND ( post_content LIKE %s OR post_content LIKE %s ){$one} ORDER BY ID",
				...array_merge( $types, array( '%[lasso%', '%wp:affiliate-plugin/lasso%' ) )
			)
		);
		// phpcs:enable

		return array_map(
			static fn ( object $row ): array => array(
				'key'    => (string) $row->ID,
				'post'   => (int) $row->ID,
				'place'  => '',
				'status' => (string) $row->post_status,
				'text'   => (string) $row->post_content,
			),
			is_array( $rows ) ? $rows : array()
		);
	}

	/**
	 * Writes a post's converted content, keeping a copy of its first content.
	 *
	 * @param string $key    The post's ID.
	 * @param string $before Its content now.
	 * @param string $after  Its converted content.
	 */
	public function write( string $key, string $before, string $after ): bool {
		$post = (int) $key;
		if ( $this->content( $post ) !== $before ) {
			return false;
		}
		if ( ! $this->save( $post, $after ) ) {
			$this->save( $post, $before );

			return false;
		}
		if ( '' === (string) get_post_meta( $post, self::COPY, true ) ) {
			update_post_meta( $post, self::COPY, wp_slash( $before ) );
		}
		update_post_meta( $post, self::WRITTEN, md5( $after ) );

		return true;
	}

	/**
	 * The posts with a copy.
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
	 * Puts a post's copy back and forgets it.
	 *
	 * @param string $place The post's ID.
	 * @param bool   $force Revert a post edited after its conversion too.
	 */
	public function revert( string $place, bool $force ): string {
		$post = (int) $place;
		$copy = get_post_meta( $post, self::COPY, true );
		$now  = $this->content( $post );
		if ( ! is_string( $copy ) || '' === $copy || null === $now ) {
			return self::NONE;
		}
		if ( ! $force && md5( $now ) !== (string) get_post_meta( $post, self::WRITTEN, true ) ) {
			return self::EDITED;
		}
		if ( ! $this->save( $post, $copy ) ) {
			$this->save( $post, $now );

			return self::FAILED;
		}
		delete_post_meta( $post, self::COPY );
		delete_post_meta( $post, self::WRITTEN );

		return self::REVERTED;
	}

	/**
	 * A post's content now; null for a post that is gone.
	 *
	 * @param int $post The post.
	 */
	private function content( int $post ): ?string {
		$found = get_post( $post );

		return $found instanceof \WP_Post ? (string) $found->post_content : null;
	}

	/**
	 * Saves a post's content through WordPress without kses, and says whether it stored exactly that.
	 *
	 * @param int    $post    The post.
	 * @param string $content The content.
	 */
	private function save( int $post, string $content ): bool {
		$filtered = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $filtered ) {
			kses_remove_filters();
		}
		$saved = wp_update_post(
			array(
				'ID'           => $post,
				'post_content' => wp_slash( $content ),
			),
			true
		);
		if ( $filtered ) {
			kses_init_filters();
		}
		clean_post_cache( $post );

		return ! is_wp_error( $saved ) && $this->content( $post ) === $content;
	}
}
