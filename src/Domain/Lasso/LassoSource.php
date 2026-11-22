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

	/**
	 * How much of each kind of Lasso's data the site holds: every Lasso table with its rows, and the other counts.
	 */
	public function inventory(): LassoInventory;

	/**
	 * Lasso's custom fields: definitions, each record's values, "show field name".
	 */
	public function fields(): LassoFields;

	/**
	 * Lasso's groups with their records in Lasso's order.
	 *
	 * @return list<LassoGroup>
	 */
	public function groups(): array;

	/**
	 * Lasso's Amazon data: the cached rows records use, counts, and whether Creators credentials are set.
	 */
	public function amazon(): LassoAmazon;

	/**
	 * Lasso's saved Dashboard views by user, whether its Dashboard tour was ended, and its closed education boxes.
	 */
	public function views(): LassoViews;

	/**
	 * Every Lasso record's cloaked address: slug, status, cloaking switch and old slugs, by post ID.
	 *
	 * @return list<LassoAddress>
	 */
	public function slugs(): array;
}
