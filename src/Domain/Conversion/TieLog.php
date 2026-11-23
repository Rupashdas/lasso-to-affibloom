<?php
/**
 * The ties the stamp cleaning made.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * The ties the stamp cleaning made, by post (Task LM6), so its revert clears them.
 */
interface TieLog {

	/**
	 * Notes a tie made.
	 *
	 * @param int    $post    The post.
	 * @param string $url     The link.
	 * @param int    $product The product it was tied to.
	 */
	public function add( int $post, string $url, int $product ): void;

	/**
	 * A post's ties, in the order they were made.
	 *
	 * @param int $post The post.
	 * @return list<array{url: string, product: int}>
	 */
	public function of( int $post ): array;

	/**
	 * Forgets a post's ties.
	 *
	 * @param int $post The post.
	 */
	public function forget( int $post ): void;
}
