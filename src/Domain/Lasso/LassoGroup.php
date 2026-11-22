<?php
/**
 * One of Lasso's groups.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * One of Lasso's groups (F10 R13; table X1): a `lasso-cat` term and its records in Lasso's order
 * (`lasso_category_order`, then the records it does not order, oldest first). Read only.
 */
final class LassoGroup {

	/**
	 * Builds the group.
	 *
	 * @param int       $id          The term's ID.
	 * @param string    $name        Its name.
	 * @param string    $slug        Its slug.
	 * @param string    $description Its description.
	 * @param list<int> $members     Its Lasso records, in Lasso's order.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $slug,
		public readonly string $description = '',
		public readonly array $members = array(),
	) {}
}
