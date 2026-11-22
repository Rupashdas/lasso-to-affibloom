<?php
/**
 * One kind of Lasso's data and its verdict.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * One Lasso table or option group, how much the site holds, and its verdict (F10 AC5): the family that moves it, or
 * why it is left behind; a table the migrator does not know is an error.
 */
final class DataItem {

	/**
	 * Builds the item.
	 *
	 * @param string      $name   The table (without the prefix) or the option group.
	 * @param int         $rows   Its rows or entries on the site.
	 * @param Family|null $family The family that moves it; null when it is left behind.
	 * @param string      $reason Why it is left behind ('' when a family moves it).
	 * @param bool        $known  False for a Lasso table the migrator does not know.
	 */
	public function __construct(
		public readonly string $name,
		public readonly int $rows,
		public readonly ?Family $family,
		public readonly string $reason = '',
		public readonly bool $known = true,
	) {}

	/**
	 * Whether nothing of it is copied.
	 */
	public function is_left(): bool {
		return null === $this->family;
	}
}
