<?php
/**
 * The `wp affibloom-lasso blocks` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\Conversion\BlockRepairer;
use AffibloomLassoMigrator\Application\Conversion\ConversionLocked;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso blocks [--dry-run] [--post=<id>] [--revert [--force]] [--format=<format>]` (Task LM6A, deviation
 * 160): per post, the GenerateBlocks buttons Lasso left with settings and HTML that disagree, which the block editor
 * flags once Lasso is off. With --dry-run nothing is written; without it they are written as GenerateBlocks saves
 * them, what readers get kept. --revert puts repaired posts back exactly.
 */
final class BlocksCommand {

	/** The report's columns. */
	private const FIELDS = array( 'post', 'status', 'action', 'item', 'detail' );

	/**
	 * Builds the command.
	 *
	 * @param BlockRepairer $repairer The block repair.
	 */
	public function __construct( private BlockRepairer $repairer ) {}

	/**
	 * Adds the command when WP-CLI is running.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'affibloom-lasso blocks', $this );
		}
	}

	/**
	 * Repairs the GenerateBlocks buttons Lasso left that the block editor flags without Lasso.
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
	 * : Put repaired posts back as they were, exactly.
	 *
	 * [--force]
	 * : With --revert: also posts edited after their repair.
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
	 *     wp affibloom-lasso blocks --dry-run
	 *     wp affibloom-lasso blocks --post=3297
	 *     wp affibloom-lasso blocks --revert
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		$post = isset( $assoc_args['post'] ) ? absint( $assoc_args['post'] ) : 0;
		try {
			if ( isset( $assoc_args['revert'] ) ) {
				RevertReport::print( $this->repairer->revert( $post, isset( $assoc_args['force'] ) ), 'repair' );
				return;
			}
			$dry_run = isset( $assoc_args['dry-run'] );
			$lines   = $dry_run ? $this->repairer->plan( $post ) : $this->repairer->run( $post );
		} catch ( ConversionLocked $locked ) {
			WP_CLI::error( $locked->getMessage() );
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( array() !== $lines ) {
			Utils\format_items(
				$format,
				array_map(
					static fn ( StampLine $line ): array => array(
						'post'   => $line->post,
						'status' => $line->status,
						'action' => $line->action,
						'item'   => $line->item,
						'detail' => $line->detail,
					),
					$lines
				),
				self::FIELDS
			);
			// WP-CLI prints JSON without a line end; the summary starts on its own line.
			if ( 'json' === $format ) {
				WP_CLI::line();
			}
		}
		$of      = static fn ( string $action ): array => array_filter( $lines, static fn ( StampLine $line ): bool => $action === $line->action );
		$buttons = array_sum( array_map( static fn ( StampLine $line ): int => (int) $line->item, $of( StampLine::REPAIR ) ) );
		$summary = sprintf( '%d buttons in %d posts %s; %d left to repair in the editor.', $buttons, count( $of( StampLine::REPAIR ) ), $dry_run ? 'to repair' : 'repaired', count( $of( StampLine::BLOCKED ) ) );
		if ( $dry_run ) {
			WP_CLI::success( $summary . ' Nothing was written.' );
			return;
		}
		if ( array() !== $of( StampLine::ERROR ) ) {
			WP_CLI::warning( $summary . ' ' . count( $of( StampLine::ERROR ) ) . ' posts could not be written; they were left as they were.' );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $summary );
	}
}
