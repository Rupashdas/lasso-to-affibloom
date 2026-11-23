<?php
/**
 * Finish the migration.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Application\Conversion\LeftoverCheck;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLock;
use AffibloomLassoMigrator\Domain\Migration\MigratorData;
use AffibloomLassoMigrator\Domain\Migration\RunState;

/**
 * Finish migration (Task LM10; F10, the user's rule of 2026-11-17: the helper can be safely removed). Once the cutover
 * is signed off, the migrator's copies, map and options are dead weight: Finish erases them, so the way back is the
 * database backup only. It is refused while LM9's check finds anything ("Convert or remove the items listed under Check
 * first.") and while a migration, conversion or command runs (both locks). Lasso's own data is not touched (Task 42).
 * Once finished, the migration cannot run again (its map is gone): the page and the commands only say to delete the
 * plugin.
 */
final class Finish {

	/** Seconds after which the migration's lock is taken as abandoned, as the page's run (26O). */
	private const TTL = 1800;

	/**
	 * Builds Finish.
	 *
	 * @param LeftoverCheck  $check   LM9's check.
	 * @param MigratorData   $data    The migrator's data.
	 * @param RunState       $run     The migration's lock (the page's runs, 26O).
	 * @param ConversionLock $convert The conversion's lock (the commands, LM3).
	 */
	public function __construct( private LeftoverCheck $check, private MigratorData $data, private RunState $run, private ConversionLock $convert ) {}

	/**
	 * When the migration was finished, a Unix time; 0 while it is not.
	 */
	public function finished(): int {
		return $this->data->finished();
	}

	/**
	 * Finishes the migration; nothing when it is finished already.
	 *
	 * @param int $now The time.
	 * @return array<string, int> What was removed: how many of each option and meta.
	 * @throws NotClean  While LM9's check finds anything.
	 * @throws RunLocked While a migration, conversion or command runs.
	 */
	public function finish( int $now ): array {
		if ( $this->data->finished() > 0 ) {
			return array();
		}
		if ( ! $this->run->lock( $now, self::TTL ) ) {
			throw new RunLocked();
		}
		try {
			if ( ! $this->convert->take() ) {
				throw new RunLocked();
			}
			try {
				$found = $this->check->check();
				if ( array() !== $found ) {
					throw new NotClean( count( $found ) );
				}
				$removed = $this->data->erase();
				$this->data->finish( $now );

				return $removed;
			} finally {
				$this->convert->release();
			}
		} finally {
			$this->run->unlock();
		}
	}
}
