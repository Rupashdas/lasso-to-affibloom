<?php
/**
 * One thing of Lasso left in the content, or a box that names nothing.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * One finding of the check before Lasso goes (Task LM9): where (a name and a link to change it), what, and how many.
 */
final class Leftover {

	/**
	 * Builds the finding.
	 *
	 * @param string $title Where: a post's title, a widget, the Additional CSS.
	 * @param string $edit  The link to change it.
	 * @param string $what  What: one of Lasso's markers, or "a box names product 5, which does not exist".
	 * @param int    $count How many.
	 */
	public function __construct( public readonly string $title, public readonly string $edit, public readonly string $what, public readonly int $count ) {}
}
