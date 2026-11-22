<?php
/**
 * The posts that hold Lasso items.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * The site's posts with Lasso items in their content (Task LM2), read only.
 */
interface LassoContent {

	/**
	 * The posts and their items, in ID order; one post when `$post` is given.
	 *
	 * @param int $post One post's ID; 0 for every post.
	 * @return list<array{id: int, status: string, title: string, items: list<LassoItem>}>
	 */
	public function posts( int $post = 0 ): array;
}
