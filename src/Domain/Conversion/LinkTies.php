<?php
/**
 * Which product Affibloom's link index ties a post's link to.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * The ties of Affibloom's link index (Task LM6; F21 R3, R37): a link is a post and a URL; each place the post holds it
 * has the product its own address ties it to, an editor's assignment and whether it is turned back to its original URL.
 */
interface LinkTies {

	/**
	 * Each place a post holds a link; empty when the index has none.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link.
	 * @return list<array{product: int, assigned: int, original: bool}>
	 */
	public function places( int $post, string $url ): array;

	/**
	 * Ties to a product the places whose own address ties them to another or to none, and that have no assignment and
	 * are not turned back; kept across a rescan.
	 *
	 * @param int    $post    The post.
	 * @param string $url     The link.
	 * @param int    $product The product.
	 * @return int How many places were tied.
	 */
	public function tie( int $post, string $url, int $product ): int;

	/**
	 * Clears the places' assignment to a product.
	 *
	 * @param int    $post    The post.
	 * @param string $url     The link.
	 * @param int    $product The product.
	 * @return int How many places were cleared.
	 */
	public function untie( int $post, string $url, int $product ): int;
}
