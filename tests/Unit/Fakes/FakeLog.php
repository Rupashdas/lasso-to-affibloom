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
}
