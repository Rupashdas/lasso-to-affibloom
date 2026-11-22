<?php
/**
 * Affibloom's Display settings, as the migration reads and writes them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * Affibloom's Display settings, read and written through the Display screen's rules (F10 D9; Task 26D).
 */
interface DisplayWriter {

	/**
	 * Every type's settings: `{ columns, single: { theme, palette, elements, density, layout }, grid: …, list: … }`.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array;

	/**
	 * Saves one type's settings, in the shape values() gives them; `columns` is the grid's.
	 *
	 * @param string               $type   `single`, `grid` or `list`.
	 * @param array<string, mixed> $config Any of `theme`, `palette`, `elements`, `density`, `layout`, `columns`.
	 * @return list<string> The colours and labels Affibloom refused.
	 */
	public function save( string $type, array $config ): array;
}
