<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;

/**
 * Lasso's data in memory.
 */
final class FakeLassoSource implements LassoSource {

	/**
	 * Builds the source.
	 *
	 * @param list<LassoRecord> $records   The records, in Lasso's order.
	 * @param LassoInventory    $inventory The counts.
	 * @param LassoSettings     $settings  Lasso's settings.
	 */
	public function __construct( private array $records = array(), private LassoInventory $inventory = new LassoInventory( array() ), private LassoSettings $settings = new LassoSettings( array() ) ) {}

	public function ids(): array {
		return array_map( static fn ( LassoRecord $record ): int => $record->id, $this->records );
	}

	public function records( array $ids ): array {
		return array_values( array_filter( $this->records, static fn ( LassoRecord $record ): bool => in_array( $record->id, $ids, true ) ) );
	}

	public function settings(): LassoSettings {
		return $this->settings;
	}

	public function inventory(): LassoInventory {
		return $this->inventory;
	}
}
