<?php
/**
 * The `wp affibloom-lasso stamps` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\Conversion\ConversionLocked;
use AffibloomLassoMigrator\Application\Conversion\StampCleaner;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use AffibloomLassoMigrator\Domain\Conversion\TextStore;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso stamps [--dry-run] [--post=<id>] [--revert [--force]] [--format=<format>]` (Task LM6; F34 AC6):
 * per post, Lasso's stamps to remove and the ties to keep, and a summary. With --dry-run nothing is written; without it
 * the stamps go, each link Lasso tied staying tied in Affibloom. --revert puts cleaned posts back exactly and clears the
 * ties made.
 */
final class StampsCommand {

	/** The report's columns. */
	private const FIELDS = array( 'post', 'status', 'action', 'item', 'detail' );

	/**
	 * Builds the command.
	 *
	 * @param StampCleaner $cleaner The stamp cleaning.
	 */
	public function __construct( private StampCleaner $cleaner ) {}

	/**
	 * Adds the command when WP-CLI is running.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'affibloom-lasso stamps', $this );
		}
	}

	/**
	 * Removes Lasso's data-lasso-* stamps from the site's posts, keeping the links Lasso tied to a product tied.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report only; nothing is written.
	 *
	 * [--post=<id>]
	 * : One post only.
	 *
	 * [--revert]
	 * : Put cleaned posts back as they were, exactly, and clear the ties made.
	 *
	 * [--force]
	 * : With --revert: also posts edited after their cleaning.
	 *
	 * [--format=<format>]
	 * : How the lines are printed.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp affibloom-lasso stamps --dry-run
	 *     wp affibloom-lasso stamps --post=9492
	 *     wp affibloom-lasso stamps --revert
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		$post = isset( $assoc_args['post'] ) ? absint( $assoc_args['post'] ) : 0;
		try {
			if ( isset( $assoc_args['revert'] ) ) {
				$this->revert( $post, isset( $assoc_args['force'] ) );
				return;
			}
			$dry_run = isset( $assoc_args['dry-run'] );
			$lines   = $dry_run ? $this->cleaner->plan( $post ) : $this->cleaner->run( $post );
		} catch ( ConversionLocked $locked ) {
			WP_CLI::error( $locked->getMessage() );
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( array() !== $lines ) {
			Utils\format_items( $format, array_map( self::row( ... ), $lines ), self::FIELDS );
			// WP-CLI prints JSON without a line end; the summary starts on its own line.
			if ( 'json' === $format ) {
				WP_CLI::line();
			}
		}
		$count = static fn ( string $action ): int => count( array_filter( $lines, static fn ( StampLine $line ): bool => $action === $line->action ) );
		$posts = count( array_unique( array_map( static fn ( StampLine $line ): int => $line->post, array_filter( $lines, static fn ( StampLine $line ): bool => StampLine::BLOCKED === $line->action ) ) ) );
		if ( $dry_run ) {
			WP_CLI::success( sprintf( '%d posts to clean, %d links to tie, %d kept as Affibloom has them; %d posts keep their stamps. Nothing was written.', $count( StampLine::REMOVE ), $count( StampLine::TIE ), $count( StampLine::KEEP ), $posts ) );
			return;
		}
		$done = sprintf( '%d posts cleaned, %d links tied, %d kept as Affibloom has them; %d posts keep their stamps.', $count( StampLine::REMOVE ), $count( StampLine::TIE ), $count( StampLine::KEEP ), $posts );
		if ( $count( StampLine::ERROR ) > 0 ) {
			$errors = count( array_unique( array_map( static fn ( StampLine $line ): int => $line->post, array_filter( $lines, static fn ( StampLine $line ): bool => StampLine::ERROR === $line->action ) ) ) );
			WP_CLI::warning( "$done $errors posts could not be written; they were left as they were." );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $done );
	}

	/**
	 * Puts cleaned posts back and says how each went.
	 *
	 * @param int  $post  One post; 0 for every cleaned post.
	 * @param bool $force Also posts changed after their cleaning.
	 */
	private function revert( int $post, bool $force ): void {
		$results = $this->cleaner->revert( $post, $force );
		$counts  = array_count_values( $results );
		foreach ( $results as $one => $result ) {
			if ( TextStore::EDITED === $result ) {
				WP_CLI::warning( ucfirst( $one ) . ' was changed after its cleaning: kept (--force reverts it).' );
			} elseif ( TextStore::FAILED === $result ) {
				WP_CLI::warning( ucfirst( $one ) . ' could not be put back exactly: it keeps its copy.' );
			}
		}
		$line = sprintf(
			'%d reverted, %d edited since (kept), %d with nothing to revert, %d failed.',
			$counts[ TextStore::REVERTED ] ?? 0,
			$counts[ TextStore::EDITED ] ?? 0,
			$counts[ TextStore::NONE ] ?? 0,
			$counts[ TextStore::FAILED ] ?? 0
		);
		if ( isset( $counts[ TextStore::FAILED ] ) ) {
			WP_CLI::warning( $line );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $line );
	}

	/**
	 * A line as the report's row.
	 *
	 * @param StampLine $line The line.
	 * @return array<string, string|int>
	 */
	private static function row( StampLine $line ): array {
		return array(
			'post'   => $line->post,
			'status' => $line->status,
			'action' => $line->action,
			'item'   => $line->item,
			'detail' => $line->detail,
		);
	}
}
