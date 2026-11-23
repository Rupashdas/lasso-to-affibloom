<?php
/**
 * The ties the stamp cleaning made, in post meta.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\TieLog;

/**
 * The ties the stamp cleaning made (Task LM6), one `_affibloom_lasso_stamp_tie` meta per tie: `{"url":…,"product":…}`.
 */
final class PostMetaTieLog implements TieLog {

	/** The meta key. */
	public const KEY = '_affibloom_lasso_stamp_tie';

	/**
	 * Notes a tie made.
	 *
	 * @param int    $post    The post.
	 * @param string $url     The link.
	 * @param int    $product The product it was tied to.
	 */
	public function add( int $post, string $url, int $product ): void {
		add_post_meta(
			$post,
			self::KEY,
			wp_slash(
				(string) wp_json_encode(
					array(
						'url'     => $url,
						'product' => $product,
					)
				)
			)
		);
	}

	/**
	 * A post's ties, in the order they were made.
	 *
	 * @param int $post The post.
	 * @return list<array{url: string, product: int}>
	 */
	public function of( int $post ): array {
		$ties = array();
		foreach ( (array) get_post_meta( $post, self::KEY, false ) as $value ) {
			$tie = json_decode( (string) $value, true );
			if ( is_array( $tie ) && is_string( $tie['url'] ?? null ) && is_int( $tie['product'] ?? null ) ) {
				$ties[] = array(
					'url'     => $tie['url'],
					'product' => $tie['product'],
				);
			}
		}

		return $ties;
	}

	/**
	 * Forgets a post's ties.
	 *
	 * @param int $post The post.
	 */
	public function forget( int $post ): void {
		delete_post_meta( $post, self::KEY );
	}
}
