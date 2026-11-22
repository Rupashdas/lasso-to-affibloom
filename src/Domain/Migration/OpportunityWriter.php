<?php
/**
 * Affibloom's dismissed opportunities.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * Affibloom's dismissed opportunities, by post and link (Task 26N).
 */
interface OpportunityWriter {

	/**
	 * Whether a post's link is dismissed; null when Affibloom's link index has none.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link.
	 */
	public function dismissed( int $post, string $url ): ?bool;

	/**
	 * Dismisses a post's link; returns how many places.
	 *
	 * @param int    $post The post.
	 * @param string $url  The link.
	 */
	public function dismiss( int $post, string $url ): int;
}
