<?php
/**
 * Affibloom's settings, as the migration reads and writes them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * Affibloom's settings, read and written through the Settings screens' own rules (F10 D9; Task 26C).
 */
interface SettingsWriter {

	/**
	 * Every setting's current value.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array;

	/**
	 * Saves some settings of one tab; a switch not given keeps its value.
	 *
	 * @param string               $tab    `general`, `display`, `amazon` or `import`.
	 * @param array<string, mixed> $values Setting => value.
	 * @return list<string> The settings Affibloom refused.
	 */
	public function save( string $tab, array $values ): array;
}
