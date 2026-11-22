<?php
/**
 * Affibloom's original links.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * The URL a post's link had before Lasso's scan rewrote it, in Affibloom's link index (Task 26Q; F22 N3, E17), so a
 * post's links can turn it back at render time (Affibloom's Task GF6).
 */
interface OriginalLinkWriter {

	/**
	 * The original a post's link has now (its own URL until one is given); null when Affibloom's index has none.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link as the content has it now.
	 */
	public function original( int $post, string $url ): ?string;

	/**
	 * Gives every place a post holds the link its original; returns how many places took it.
	 *
	 * @param int    $post     The post.
	 * @param string $url      The link as the content has it now.
	 * @param string $original The link before Lasso rewrote it.
	 */
	public function set( int $post, string $url, string $original ): int;
}
