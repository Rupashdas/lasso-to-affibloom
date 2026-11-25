<?php
/**
 * The `wp affibloom-lasso migrate` command.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface\Cli;

use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\DataItem;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationReport;
use AffibloomLassoMigrator\Infrastructure\Lasso\LassoInstall;
use AffibloomLassoMigrator\Interface\ReportCsv;
use InvalidArgumentException;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso migrate [--dry-run] [--only=<families>] [--update [--force]] [--report=<file.csv>]
 * [--format=<format>]` (F10 D3, D8, D18; Tasks 25A, 26A, 26B): one line per Lasso item with what becomes of it and a
 * summary per family. With --dry-run nothing is written; without it, each family whose migration is built runs
 * (products from 26A) and the others say so. --update refreshes what was migrated before from Lasso, keeping an
 * Affibloom edit unless --force.
 */
final class MigrateCommand {

	/**
	 * Builds the command.
	 *
	 * @param MigrationService $migration The migration.
	 * @param LassoInstall     $lasso     Whether the site has Lasso's data.
	 */
	public function __construct( private MigrationService $migration, private LassoInstall $lasso ) {}

	/**
	 * Adds the command when WP-CLI is running.
	 */
	public function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'affibloom-lasso migrate', $this );
		}
	}

	/**
	 * Moves this site's Lasso data to Affibloom, or reports what that will do.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report only; nothing is written.
	 *
	 * [--only=<families>]
	 * : Comma-separated families: settings, display, fields, products, groups, slugs, variants, tables, issues, amazon,
	 * views, opportunities, locations. Default: all, in that order.
	 *
	 * [--update]
	 * : Also refresh what was migrated before: a record changed in Lasso since changes its product, unless the product was
	 * changed in Affibloom too.
	 *
	 * [--force]
	 * : With --update, take Lasso's values where both sides changed.
	 *
	 * [--report=<file>]
	 * : Also write every line to this CSV file.
	 *
	 * [--format=<format>]
	 * : How to print the lines.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp affibloom-lasso migrate --dry-run
	 *     wp affibloom-lasso migrate --dry-run --only=products --report=migration.csv
	 *     wp affibloom-lasso migrate --only=products
	 *     wp affibloom-lasso migrate --only=products --update
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		$dry_run = isset( $assoc_args['dry-run'] );
		if ( ! $this->lasso->has_data() ) {
			WP_CLI::success( 'No Lasso data found.' );
			return;
		}
		try {
			$families = Family::parse( $assoc_args['only'] ?? '' );
		} catch ( InvalidArgumentException $unknown ) {
			WP_CLI::error( $unknown->getMessage() );
		}

		$update = isset( $assoc_args['update'] );
		$force  = $update && isset( $assoc_args['force'] );
		$report = $dry_run ? $this->migration->plan( $families, $update, $force ) : $this->migration->run( $families, $update, $force );
		$rows   = array_map( ReportCsv::line( ... ), $report->lines );
		if ( array() !== $rows ) {
			Utils\format_items( $assoc_args['format'] ?? 'table', $rows, ReportCsv::FIELDS );
			// WP-CLI prints JSON without a line end; the summary starts on its own line.
			if ( 'json' === ( $assoc_args['format'] ?? 'table' ) ) {
				WP_CLI::line();
			}
		}
		foreach ( $report->families as $family ) {
			WP_CLI::line( self::summary( $report, $family ) );
		}
		$data = array_map( self::data_row( ... ), $report->data );
		if ( array() !== $data ) {
			WP_CLI::line( "Lasso's data, and where it goes:" );
			Utils\format_items( 'table', $data, array( 'data', 'rows', 'verdict' ) );
			WP_CLI::line( self::left_summary( $report ) );
		}
		if ( isset( $assoc_args['report'] ) ) {
			self::write_csv( $assoc_args['report'], ReportCsv::rows( $report ) );
			WP_CLI::line( sprintf( 'Report written to %s.', $assoc_args['report'] ) );
		}

		$done = $dry_run ? 'Nothing was written.' : "Done. Lasso's data was only read.";
		if ( $report->has_errors() ) {
			WP_CLI::warning( 'Some items have errors (see the error lines). ' . $done );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( $done );
	}

	/**
	 * A family's summary line, e.g. "products: 265 records: 254 to create, 9 skipped, …".
	 *
	 * @param MigrationReport $report The report.
	 * @param Family          $family The family.
	 */
	public static function summary( MigrationReport $report, Family $family ): string {
		if ( ! $report->is_built( $family ) ) {
			return sprintf( $report->dry_run ? '%s: not built yet.' : '%s: its migration is not built yet; nothing was written.', $family->value );
		}
		if ( Family::Settings === $family || Family::Display === $family ) {
			$kept = count( array_filter( $report->lines_of( $family ), static fn ( MigrationLine $line ): bool => Action::Skip === $line->action && str_starts_with( $line->note, 'Affibloom has' ) ) );
			return sprintf(
				$report->dry_run ? '%s: %d keys: %d to import, %d unchanged, %d kept, %d left, %d errors.' : '%s: %d keys: %d imported, %d unchanged, %d kept, %d left, %d errors.',
				$family->value,
				count( $report->lines_of( $family ) ),
				$report->count( $family, Action::Create ),
				$report->count( $family, Action::Unchanged ),
				$kept,
				$report->count( $family, Action::Skip ) - $kept,
				$report->count( $family, Action::Error )
			);
		}
		if ( Family::Products !== $family ) {
			return sprintf(
				$report->dry_run ? '%s: %d items: %d to create, %d to update, %d unchanged, %d skipped, %d errors.' : '%s: %d items: %d created, %d updated, %d unchanged, %d skipped, %d errors.',
				$family->value,
				count( $report->lines_of( $family ) ),
				$report->count( $family, Action::Create ),
				$report->count( $family, Action::Update ),
				$report->count( $family, Action::Unchanged ),
				$report->count( $family, Action::Skip ),
				$report->count( $family, Action::Error )
			);
		}
		$lines     = $report->lines_of( $family );
		$published = count( array_filter( $lines, static fn ( MigrationLine $line ): bool => 'publish' === $line->status ) );
		if ( $report->update ) {
			$noted = static fn ( string $start ): int => count( array_filter( $lines, static fn ( MigrationLine $line ): bool => str_starts_with( $line->note, $start ) ) );
			return sprintf(
				$report->dry_run
					? 'products: %d to create, %d to update, %d unchanged, %d changed on both sides (kept), %d deleted in Lasso (kept), %d skipped, %d to retire, %d to trash, %d errors.'
					: 'products: %d created, %d updated, %d unchanged, %d changed on both sides (kept), %d deleted in Lasso (kept), %d skipped, %d retired, %d in the trash, %d errors.',
				$report->count( $family, Action::Create ),
				$report->count( $family, Action::Update ),
				$report->count( $family, Action::Unchanged ),
				$noted( 'changed on both sides: Affibloom' ),
				$noted( 'deleted in Lasso' ),
				$report->count( $family, Action::Skip ) - $noted( 'changed on both sides: Affibloom' ) - $noted( 'deleted in Lasso' ),
				$report->count( $family, Action::Retire ),
				$report->count( $family, Action::Trash ),
				$report->count( $family, Action::Error )
			);
		}
		if ( ! $report->dry_run ) {
			return sprintf(
				'products: %d records: %d created, %d skipped, %d retired, %d in the trash, %d unchanged (%d adopted), %d errors (%d published).',
				count( $lines ),
				$report->count( $family, Action::Create ),
				$report->count( $family, Action::Skip ),
				$report->count( $family, Action::Retire ),
				$report->count( $family, Action::Trash ),
				$report->count( $family, Action::Unchanged ),
				count( array_filter( $lines, static fn ( MigrationLine $line ): bool => str_starts_with( $line->note, 'adopted' ) ) ),
				$report->count( $family, Action::Error ),
				$published
			);
		}

		return sprintf(
			'products: %d records: %d to create, %d skipped, %d to retire, %d to trash, %d already migrated, %d errors (%d published).',
			count( $lines ),
			$report->count( $family, Action::Create ),
			$report->count( $family, Action::Skip ),
			$report->count( $family, Action::Retire ),
			$report->count( $family, Action::Trash ),
			$report->count( $family, Action::Unchanged ),
			$report->count( $family, Action::Error ),
			$published
		);
	}

	/**
	 * The closing count of what is left behind (AC5).
	 *
	 * @param MigrationReport $report The report.
	 */
	public static function left_summary( MigrationReport $report ): string {
		$left    = array_filter( $report->data, static fn ( DataItem $item ): bool => $item->is_left() );
		$unknown = count( array_filter( $report->data, static fn ( DataItem $item ): bool => ! $item->known ) );

		return sprintf(
			'Left behind: %d of %d kinds of Lasso data (%d rows)%s.',
			count( $left ),
			count( $report->data ),
			array_sum( array_map( static fn ( DataItem $item ): int => $item->rows, $left ) ),
			$unknown > 0 ? sprintf( '; %d tables the migrator does not know', $unknown ) : ''
		);
	}

	/**
	 * A kind of Lasso's data as printed.
	 *
	 * @param DataItem $item The item.
	 * @return array<string, string>
	 */
	private static function data_row( DataItem $item ): array {
		return array(
			'data'    => $item->name,
			'rows'    => (string) $item->rows,
			'verdict' => null === $item->family ? ( $item->known ? 'left: ' : 'error: ' ) . $item->reason : 'moved by the ' . $item->family->value . ' family',
		);
	}



	/**
	 * Writes the lines to a CSV file, with a header row.
	 *
	 * @param string                      $file The file.
	 * @param list<array<string, string>> $rows The lines.
	 */
	private static function write_csv( string $file, array $rows ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- a file the developer named on the command line.
		$handle = fopen( $file, 'w' );
		if ( false === $handle ) {
			WP_CLI::error( sprintf( 'Could not write %s.', $file ) );
		}
		ReportCsv::write( $handle, $rows );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
		fclose( $handle );
	}
}
