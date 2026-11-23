<?php
/**
 * The `wp affibloom-lasso finish` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\Finish;
use AffibloomLassoMigrator\Application\NotClean;
use AffibloomLassoMigrator\Application\RunLocked;
use WP_CLI;

/**
 * `wp affibloom-lasso finish [--yes]` (Task LM10): Finish migration, as the page's button. It asks first, as after it
 * the way back is the database backup only; it is refused while `wp affibloom-lasso verify` finds anything. It prints
 * what it removed and how to delete the plugin.
 */
final class FinishCommand {

	/**
	 * Builds the command.
	 *
	 * @param Finish $finish Finish.
	 */
	public function __construct( private Finish $finish ) {}

	/**
	 * Adds the command when WP-CLI is running.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'affibloom-lasso finish', $this );
		}
	}

	/**
	 * Finishes the migration: removes the migrator's copies, ID map, options, logs and locks.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Do not ask first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp affibloom-lasso finish
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		$finished = $this->finish->finished();
		if ( $finished > 0 ) {
			WP_CLI::success( sprintf( 'The migration was finished on %s. %s', wp_date( 'Y-m-d H:i', $finished ), self::delete() ) );
			return;
		}
		WP_CLI::confirm( "After this, the way back to Lasso is your database backup only: the conversion's copies and the migration's map are removed. Finish the migration?", $assoc_args );
		try {
			$removed = $this->finish->finish( time() );
		} catch ( NotClean $refused ) {
			WP_CLI::error( sprintf( 'Convert or remove the items listed under Check first (%d findings; wp affibloom-lasso verify lists them).', $refused->findings ) );
		} catch ( RunLocked ) {
			WP_CLI::error( 'Another migration or conversion is running.' );
		}
		foreach ( $removed as $name => $rows ) {
			WP_CLI::line( sprintf( 'Removed %s (%d)', $name, $rows ) );
		}
		WP_CLI::success( 'Migration finished. ' . self::delete() );
	}

	/**
	 * How to remove the plugin.
	 */
	private static function delete(): string {
		return 'Deactivate and delete Affibloom Lasso Migrator: wp plugin uninstall affibloom-lasso-migrator --deactivate';
	}
}
