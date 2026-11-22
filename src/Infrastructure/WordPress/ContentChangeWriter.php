<?php
/**
 * Converted posts, written through WordPress with a copy to go back to.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\ContentWriter;

/**
 * Writes a converted post through wp_update_post(), so its save hooks run and a revision is kept (Task LM3; F10 D16,
 * D17). The content before the first conversion is kept once in `_affibloom_lasso_pre_conversion`, the md5 of what was
 * written in `_affibloom_lasso_converted_md5`. WordPress's kses filter is off for these saves, as an importer's are
 * (WP-CLI has no user that may post unfiltered HTML, and the post's own markup must stay as it is). Each save is read
 * back: a post that did not store exactly what was written (another plugin's save hook changed it) is put back as it
 * was. Revert does the same with the copy, and refuses a post whose content is no longer what was written.
 */
final class ContentChangeWriter implements ContentWriter {

	/** The post's content before its first conversion. */
	public const COPY = '_affibloom_lasso_pre_conversion';

	/** The md5 of the content the last conversion wrote. */
	public const WRITTEN = '_affibloom_lasso_converted_md5';

	/**
	 * A post's content now; null for a post that is gone.
	 *
	 * @param int $post The post.
	 */
	public function content( int $post ): ?string {
		$found = get_post( $post );

		return $found instanceof \WP_Post ? (string) $found->post_content : null;
	}

	/**
	 * Writes the converted content, keeping a copy of the first content.
	 *
	 * @param int    $post   The post.
	 * @param string $before Its content now.
	 * @param string $after  Its converted content.
	 */
	public function write( int $post, string $before, string $after ): bool {
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
	 * @return list<int>
	 */
	public function converted(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one list for a revert run.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY post_id", self::COPY ) ) );
	}

	/**
	 * Puts a post's copy back and forgets it.
	 *
	 * @param int  $post  The post.
	 * @param bool $force Revert a post edited after its conversion too.
	 */
	public function revert( int $post, bool $force ): string {
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
