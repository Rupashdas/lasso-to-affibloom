<?php
/**
 * The `wp affibloom-lasso convert` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Application\Conversion\ConversionLocked;
use AffibloomLassoMigrator\Domain\Conversion\ContentWriter;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso convert [--dry-run] [--post=<id>] [--revert [--force]] [--format=<format>]` (F10 AC3, AC25, D16,
 * D17; Tasks LM2, LM3): every Lasso block, `[lasso …]` and `[lasso-attr …]` in the site's content with what it becomes,
 * or why it stays, and a summary. With --dry-run nothing is written; without it Lasso's convertible blocks are written
 * as Affibloom's (bare shortcodes are LM4's). --revert puts converted posts back exactly.
 */
final class ConvertCommand {

	/** The report's columns. */
	private const FIELDS = array( 'post', 'status', 'item', 'lasso', 'action', 'becomes' );

	/**
	 * Builds the command.
	 *
	 * @param ContentConverter $converter The conversion.
	 */
	public function __construct( private ContentConverter $converter ) {}

	/**
	 * Adds the command when WP-CLI is running.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'affibloom-lasso convert', $this );
		}
	}

	/**
	 * Lists every Lasso item in the site's content with what it becomes, or why it stays as it is.
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
	 * : Put converted posts back as they were before their first conversion, exactly.
	 *
	 * [--force]
	 * : With --revert: also posts edited after their conversion.
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
	 *     wp affibloom-lasso convert --dry-run
	 *     wp affibloom-lasso convert --dry-run --post=2622 --format=csv
	 *     wp affibloom-lasso convert --post=2622
	 *     wp affibloom-lasso convert --revert --post=2622
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
			$lines   = $dry_run ? $this->converter->plan( $post ) : $this->converter->run( $post );
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
		if ( $dry_run ) {
			WP_CLI::success( self::summary( $lines ) . ' Nothing was written.' );
			return;
		}
		$errors = count( array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::ERROR === $line->action ) );
		$done   = self::done( $lines );
		if ( $errors > 0 ) {
			WP_CLI::warning( "$done $errors could not be written; their posts were left as they were." );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $done );
	}

	/**
	 * Puts converted posts back and says how each went.
	 *
	 * @param int  $post  One post; 0 for every converted post.
	 * @param bool $force Also posts edited after their conversion.
	 */
	private function revert( int $post, bool $force ): void {
		$results = $this->converter->revert( $post, $force );
		$counts  = array_count_values( $results );
		foreach ( $results as $one => $result ) {
			if ( ContentWriter::EDITED === $result ) {
				WP_CLI::warning( "Post $one was edited after its conversion: kept (--force reverts it)." );
			} elseif ( ContentWriter::FAILED === $result ) {
				WP_CLI::warning( "Post $one could not be put back exactly: it keeps its copy." );
			}
		}
		$line = sprintf(
			'%d reverted, %d edited since (kept), %d with nothing to revert, %d failed.',
			$counts[ ContentWriter::REVERTED ] ?? 0,
			$counts[ ContentWriter::EDITED ] ?? 0,
			$counts[ ContentWriter::NONE ] ?? 0,
			$counts[ ContentWriter::FAILED ] ?? 0
		);
		if ( isset( $counts[ ContentWriter::FAILED ] ) ) {
			WP_CLI::warning( $line );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $line );
	}

	/**
	 * The run's summary: blocks converted, posts written, what is left.
	 *
	 * @param list<ConversionLine> $lines The lines.
	 */
	private static function done( array $lines ): string {
		$converted = array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::CONVERT === $line->action );
		$posts     = count( array_unique( array_map( static fn ( ConversionLine $line ): int => $line->post, $converted ) ) );
		$later     = count( array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::LATER === $line->action ) );
		$blocked   = count( array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::BLOCKED === $line->action ) );

		return sprintf( '%d Lasso blocks converted in %d posts; %d bare shortcodes left for Task LM4, %d blocked.', count( $converted ), $posts, $later, $blocked );
	}

	/**
	 * A line as the report's row: what it becomes, or why it stays.
	 *
	 * @param ConversionLine $line The line.
	 * @return array<string, string|int>
	 */
	private static function row( ConversionLine $line ): array {
		return array(
			'post'    => $line->post,
			'status'  => $line->status,
			'item'    => $line->kind,
			'lasso'   => $line->lasso,
			'action'  => $line->action,
			'becomes' => ConversionLine::CONVERT === $line->action ? $line->target : $line->reason,
		);
	}

	/**
	 * "N posts, N items: N to convert, N blocked."
	 *
	 * @param list<ConversionLine> $lines The lines.
	 */
	private static function summary( array $lines ): string {
		$posts   = count( array_unique( array_map( static fn ( ConversionLine $line ): int => $line->post, $lines ) ) );
		$convert = count( array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::CONVERT === $line->action ) );

		return sprintf( '%d posts, %d items: %d to convert, %d blocked.', $posts, count( $lines ), $convert, count( $lines ) - $convert );
	}
}
