<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;

/**
 * The migration's log in memory.
 */
final class FakeLog implements MigrationLog {

	/** The families that ran. @var array<string, true> */
	public array $ran = array();

	public function ran( Family $family, int $items, int $errors ): void {
		$this->ran[ $family->value ] = true;
	}

	public function has_run( Family $family ): bool {
		return isset( $this->ran[ $family->value ] );
	}

	/** The ID maps. @var array<string, array<int, int>> */
	public array $maps = array();

	public function map( string $name ): array {
		return $this->maps[ $name ] ?? array();
	}

	public function remember( string $name, int $from, int $to ): void {
		$this->maps[ $name ][ $from ] = $to;
	}
}
