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
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso convert [--dry-run] [--post=<id>] [--revert [--force]] [--format=<format>]` (F10 AC3, AC25, D16,
 * D17; Tasks LM2-LM4): every Lasso block, `[lasso …]` and `[lasso-attr …]` in the site's content with what it becomes,
 * or why it stays, and a summary. With --dry-run nothing is written; without it Lasso's convertible blocks are written
 * as Affibloom's, bare shortcodes too, in posts, widgets and Elementor's data (LM4). --revert puts converted places back
 * exactly.
 */
final class ConvertCommand {

	/** The report's columns. */
	private const FIELDS = array( 'post', 'place', 'status', 'item', 'lasso', 'action', 'becomes' );

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
				RevertReport::print( $this->converter->revert( $post, isset( $assoc_args['force'] ) ), 'conversion' );
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
	 * The run's summary: items converted, the places written, what stays.
	 *
	 * @param list<ConversionLine> $lines The lines.
	 */
	private static function done( array $lines ): string {
		$converted = array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::CONVERT === $line->action );
		$places    = count( array_unique( array_map( static fn ( ConversionLine $line ): string => $line->post . ' ' . $line->place, $converted ) ) );
		$blocked   = count( array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::BLOCKED === $line->action ) );

		return sprintf( '%d Lasso items converted in %d places; %d blocked.', count( $converted ), $places, $blocked );
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
			'place'   => $line->place,
			'status'  => $line->status,
			'item'    => $line->kind,
			'lasso'   => $line->lasso,
			'action'  => $line->action,
			'becomes' => ConversionLine::CONVERT === $line->action ? $line->target : $line->reason,
		);
	}

	/**
	 * "N places, N items: N to convert, N blocked."
	 *
	 * @param list<ConversionLine> $lines The lines.
	 */
	private static function summary( array $lines ): string {
		$places  = count( array_unique( array_map( static fn ( ConversionLine $line ): string => $line->post . ' ' . $line->place, $lines ) ) );
		$convert = count( array_filter( $lines, static fn ( ConversionLine $line ): bool => ConversionLine::CONVERT === $line->action ) );

		return sprintf( '%d places, %d items: %d to convert, %d blocked.', $places, count( $lines ), $convert, count( $lines ) - $convert );
	}
}
