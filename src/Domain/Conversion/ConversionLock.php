<?php
/**
 * One conversion at a time.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Conversion;

/**
 * The conversion's lock (Task LM3): a run or revert takes it, so two never write the same posts at once.
 */
interface ConversionLock {

	/**
	 * Takes the lock; false while another run holds it.
	 */
	public function take(): bool;

	/**
	 * Gives it back.
	 */
	public function release(): void;
}
