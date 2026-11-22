<?php
/**
 * The conversion's lock in an option.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;

/**
 * `affibloom_lasso_convert_lock` (Task LM3): the Unix time it was taken; add_option() is the atomic take (it fails
 * while the row exists), and a lock older than half an hour is taken as abandoned, as the migration's (26O). It does
 * not autoload.
 */
final class OptionConversionLock implements ConversionLock {

	/** The option. */
	public const LOCK = 'affibloom_lasso_convert_lock';

	/** Seconds after which a lock is taken as abandoned. */
	private const TTL = 1800;

	/**
	 * Takes the lock.
	 */
	public function take(): bool {
		if ( add_option( self::LOCK, time(), '', false ) ) {
			return true;
		}
		if ( time() - (int) get_option( self::LOCK, 0 ) < self::TTL ) {
			return false;
		}
		delete_option( self::LOCK );

		return add_option( self::LOCK, time(), '', false );
	}

	/**
	 * Gives the lock back.
	 */
	public function release(): void {
		delete_option( self::LOCK );
	}
}
