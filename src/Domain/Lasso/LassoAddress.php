<?php
/**
 * A Lasso record's cloaked address.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * A Lasso record's cloaked address (F10 R4, R8): its slug (`post_name`), its status, its cloaking switch
 * (`link_cloaking`, read with intval as Lasso does) and the old slugs that still answer (`lasso_old_slug`,
 * `_wp_old_slug`). Read only.
 */
final class LassoAddress {

	/**
	 * Builds the address.
	 *
	 * @param int          $id        The Lasso post ID.
	 * @param string       $slug      post_name.
	 * @param string       $status    post_status.
	 * @param bool         $cloak     Whether cloaking is on.
	 * @param list<string> $old_slugs Its old slugs, none empty.
	 */
	public function __construct( public readonly int $id, public readonly string $slug, public readonly string $status, public readonly bool $cloak, public readonly array $old_slugs = array() ) {}
}
