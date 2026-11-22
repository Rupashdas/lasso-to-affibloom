<?php
/**
 * The admin run's lock and families, in options.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Migration\RunState;

/**
 * The admin run's state in two options (Task 26O): `affibloom_lasso_migration_lock`, the Unix time the lock was taken
 * (add_option() is the atomic take: it fails while the row exists), and `affibloom_lasso_migration_run`, the families
 * still to run with the update flag. Neither autoloads.
 */
final class OptionRunState implements RunState {

	public const LOCK = 'affibloom_lasso_migration_lock';

	public const RUN = 'affibloom_lasso_migration_run';

	/**
	 * Takes the lock; an abandoned one (older than the time to live) is taken over.
	 *
	 * @param int $now The Unix time.
	 * @param int $ttl Seconds.
	 */
	public function lock( int $now, int $ttl ): bool {
		if ( add_option( self::LOCK, $now, '', false ) ) {
			return true;
		}
		$since = (int) get_option( self::LOCK, 0 );
		if ( $now - $since < $ttl ) {
			return false;
		}
		delete_option( self::LOCK );

		return add_option( self::LOCK, $now, '', false );
	}

	/**
	 * Gives the lock back.
	 */
	public function unlock(): void {
		delete_option( self::LOCK );
	}

	/**
	 * The run going on, or null.
	 *
	 * @return array{families: list<string>, update: bool}|null
	 */
	public function current(): ?array {
		$run = get_option( self::RUN );
		if ( ! is_array( $run ) || empty( $run['families'] ) || ! is_array( $run['families'] ) ) {
			return null;
		}

		return array(
			'families' => array_values( array_map( 'strval', $run['families'] ) ),
			'update'   => ! empty( $run['update'] ),
		);
	}

	/**
	 * Keeps the families still to run; none ends the run.
	 *
	 * @param list<string> $families The families.
	 * @param bool         $update   Whether it is an update.
	 */
	public function save( array $families, bool $update ): void {
		if ( array() === $families ) {
			delete_option( self::RUN );

			return;
		}
		update_option(
			self::RUN,
			array(
				'families' => $families,
				'update'   => $update,
			),
			false
		);
	}
}
