<?php
namespace AffibloomLassoMigrator\Tests\Unit\Fakes;

use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;

/**
 * The conversion's lock in memory.
 */
final class FakeConversionLock implements ConversionLock {

	/** Whether another run holds it. */
	public bool $held = false;

	public function take(): bool {
		if ( $this->held ) {
			return false;
		}
		$this->held = true;

		return true;
	}

	public function release(): void {
		$this->held = false;
	}
}
