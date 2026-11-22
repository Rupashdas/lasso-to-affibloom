<?php
/**
 * Lasso's records, read only.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * Lasso's records, read only (ADR-2: the migrator never writes Lasso's data).
 */
interface LassoSource {

	/**
	 * The IDs of every Lasso record, whatever its status, oldest change first; empty when Lasso has none.
	 *
	 * @return list<int>
	 */
	public function ids(): array;

	/**
	 * These records, in the order given.
	 *
	 * @param list<int> $ids From ids().
	 * @return list<LassoRecord>
	 */
	public function records( array $ids ): array;

	/**
	 * Lasso's settings and tracking-ID blacklist, the secret keys' values never kept (F10 D10).
	 */
	public function settings(): LassoSettings;
}
