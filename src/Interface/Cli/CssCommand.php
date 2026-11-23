<?php
/**
 * The `wp affibloom-lasso css` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\Conversion\ConversionLocked;
use AffibloomLassoMigrator\Application\Conversion\CssTranslator;
use AffibloomLassoMigrator\Domain\Conversion\StampLine;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso css [--dry-run] [--revert [--force]] [--format=<format>]` (Task LM7; ADR-6, C1): each `.lasso-*`
 * rule of the active theme's Additional CSS with its Affibloom selector, and the names with no Affibloom equivalent.
 * With --dry-run nothing is written; without it the translated CSS is saved, a copy of the old kept. --revert puts it
 * back exactly.
 */
final class CssCommand {

	/** The report's columns. */
	private const FIELDS = array( 'place', 'action', 'lasso', 'affibloom' );

	/**
	 * Builds the command.
	 *
	 * @param CssTranslator $translator The CSS translation.
	 */
	public function __construct( private CssTranslator $translator ) {}

	/**
	 * Adds the command when WP-CLI is running.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'affibloom-lasso css', $this );
		}
	}

	/**
	 * Translates the site's Lasso CSS rules to Affibloom's class names.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report only; nothing is written.
	 *
	 * [--revert]
	 * : Put the Additional CSS back as it was, exactly.
	 *
	 * [--force]
	 * : With --revert: also CSS edited after its translation.
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
	 *     wp affibloom-lasso css --dry-run
	 *     wp affibloom-lasso css
	 *     wp affibloom-lasso css --revert
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		try {
			if ( isset( $assoc_args['revert'] ) ) {
				RevertReport::print( $this->translator->revert( isset( $assoc_args['force'] ) ), 'translation' );
				return;
			}
			$dry_run = isset( $assoc_args['dry-run'] );
			$lines   = $dry_run ? $this->translator->plan() : $this->translator->run();
		} catch ( ConversionLocked $locked ) {
			WP_CLI::error( $locked->getMessage() );
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( array() !== $lines ) {
			Utils\format_items(
				$format,
				array_map(
					static fn ( StampLine $line ): array => array(
						'place'     => $line->status,
						'action'    => $line->action,
						'lasso'     => $line->item,
						'affibloom' => $line->detail,
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
		$count   = static fn ( string $action ): int => count( array_filter( $lines, static fn ( StampLine $line ): bool => $action === $line->action ) );
		$summary = sprintf( '%d Lasso rules %s; %d names with no Affibloom equivalent.', $count( StampLine::TRANSLATE ), $dry_run ? 'to translate' : 'translated', $count( StampLine::BLOCKED ) );
		if ( $dry_run ) {
			WP_CLI::success( $summary . ' Nothing was written.' );
			return;
		}
		if ( $count( StampLine::ERROR ) > 0 ) {
			WP_CLI::warning( 'The Additional CSS did not save as written; it was left as it was.' );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $summary );
	}
}
