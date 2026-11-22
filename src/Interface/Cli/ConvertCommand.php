<?php
/**
 * The `wp affibloom-lasso convert` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Domain\Conversion\ConversionLine;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso convert --dry-run [--post=<id>] [--format=<format>]` (F10 AC25, D16; Task LM2): every Lasso
 * block, `[lasso …]` and `[lasso-attr …]` in the site's content with what it becomes, or why it stays, and a summary.
 * Nothing is written; the conversion itself is Tasks LM3-LM5.
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
	 * : Report only; nothing is written. Required until the conversion itself is built (Task LM3).
	 *
	 * [--post=<id>]
	 * : One post only.
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
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		if ( ! isset( $assoc_args['dry-run'] ) ) {
			WP_CLI::error( 'Only --dry-run is built so far; converting comes with Task LM3. Nothing was written.' );
		}

		$lines  = $this->converter->plan( isset( $assoc_args['post'] ) ? absint( $assoc_args['post'] ) : 0 );
		$format = $assoc_args['format'] ?? 'table';
		if ( array() !== $lines ) {
			Utils\format_items( $format, array_map( self::row( ... ), $lines ), self::FIELDS );
			// WP-CLI prints JSON without a line end; the summary starts on its own line.
			if ( 'json' === $format ) {
				WP_CLI::line();
			}
		}
		WP_CLI::success( self::summary( $lines ) . ' Nothing was written.' );
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
