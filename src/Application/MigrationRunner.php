<?php
/**
 * The migration run from the admin page, one family per request.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\RunState;

/**
 * The migration from the admin page (F10 D13, AC22; Task 26O): one family per request, in the fixed order, so no request
 * holds the whole site (the largest here, 265 products, takes about two seconds); one request at a time; the families
 * still to run kept, so a run cut off continues on the next press, and a family cut off runs again (each is
 * idempotent).
 */
final class MigrationRunner {

	/** Seconds after which a lock is taken as abandoned: a request killed mid-family. */
	public const LOCK_TTL = 600;

	/**
	 * Builds the runner.
	 *
	 * @param MigrationService $migration The migration.
	 * @param RunState         $state     The lock and the families still to run.
	 */
	public function __construct( private MigrationService $migration, private RunState $state ) {}

	/**
	 * Runs the next family of the run going on, or starts one.
	 *
	 * @param bool $update Start an update ("Update from Lasso"); a run going on keeps its own.
	 * @param int  $now    The Unix time.
	 * @return array{family: string, done: bool, position: int, total: int, counts: array<string, int>}
	 * @throws RunLocked When another request is running a family.
	 */
	public function batch( bool $update, int $now ): array {
		if ( ! $this->state->lock( $now, self::LOCK_TTL ) ) {
			throw new RunLocked();
		}
		try {
			$all     = array_map( static fn ( Family $family ): string => $family->value, Family::cases() );
			$current = $this->state->current() ?? array(
				'families' => $all,
				'update'   => $update,
			);
			$pending = $current['families'];
			$family  = Family::from( (string) array_shift( $pending ) );
			$report  = $this->migration->run( array( $family ), $current['update'] );
			$this->state->save( $pending, $current['update'] );
			$counts = array_fill_keys( array_map( static fn ( Action $action ): string => $action->value, Action::cases() ), 0 );
			foreach ( $report->lines as $line ) {
				++$counts[ $line->action->value ];
			}

			return array(
				'family'   => $family->value,
				'done'     => array() === $pending,
				'position' => count( $all ) - count( $pending ),
				'total'    => count( $all ),
				'counts'   => $counts,
			);
		} finally {
			$this->state->unlock();
		}
	}
}
