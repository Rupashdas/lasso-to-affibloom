<?php
/**
 * The admin run's lock and the families it still has to run.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * The state of a migration run from the admin page (F10 D13; Task 26O): one request at a time, and the families still
 * to run, so a cut-off run continues on the next press.
 */
interface RunState {

	/**
	 * Takes the lock; false when another request holds one younger than the time to live.
	 *
	 * @param int $now The Unix time.
	 * @param int $ttl Seconds after which a lock is taken as abandoned.
	 */
	public function lock( int $now, int $ttl ): bool;

	/**
	 * Gives the lock back.
	 */
	public function unlock(): void;

	/**
	 * The run going on: the families still to run, in order, and whether it is an update; null when none is.
	 *
	 * @return array{families: list<string>, update: bool}|null
	 */
	public function current(): ?array;

	/**
	 * Keeps the families still to run; none ends the run.
	 *
	 * @param list<string> $families The families, in order.
	 * @param bool         $update   Whether it is an update.
	 */
	public function save( array $families, bool $update ): void;
}
