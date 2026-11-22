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
use InvalidArgumentException;
use WP_CLI;
use WP_CLI\Utils;

/**
 * `wp affibloom-lasso migrate --dry-run [--only=<families>] [--report=<file.csv>] [--format=<format>]` (F10 D3, D18;
 * Task 25A): one line per Lasso item with what becomes of it, a summary per family, and nothing written. The run itself
 * (without --dry-run) comes with 26A.
 */
final class MigrateCommand {

	/** The printed and reported columns. */
	public const FIELDS = array( 'family', 'lasso_id', 'name', 'type', 'status', 'value', 'action', 'target', 'note' );

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
	 * Reports what moving from Lasso to Affibloom will do with every Lasso item.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report only; nothing is written. Needed for now: the run itself is not built yet.
	 *
	 * [--only=<families>]
	 * : Comma-separated families: settings, display, fields, products, groups, slugs, variants, tables, issues, amazon,
	 * views, opportunities. Default: all, in that order.
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
	 *
	 * @param list<string>          $args       Unused.
	 * @param array<string, string> $assoc_args The options.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		unset( $args );
		if ( ! isset( $assoc_args['dry-run'] ) ) {
			WP_CLI::error( 'Only the dry run is built yet: add --dry-run.' );
		}
		if ( ! $this->lasso->has_data() ) {
			WP_CLI::success( 'No Lasso data found.' );
			return;
		}
		try {
			$families = Family::parse( $assoc_args['only'] ?? '' );
		} catch ( InvalidArgumentException $unknown ) {
			WP_CLI::error( $unknown->getMessage() );
		}

		$report = $this->migration->plan( $families );
		$rows   = array_map( self::row( ... ), $report->lines );
		if ( array() !== $rows ) {
			Utils\format_items( $assoc_args['format'] ?? 'table', $rows, self::FIELDS );
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
			self::write_csv( $assoc_args['report'], array_merge( $rows, array_map( self::data_csv_row( ... ), $report->data ) ) );
			WP_CLI::line( sprintf( 'Report written to %s.', $assoc_args['report'] ) );
		}

		if ( $report->has_errors() ) {
			WP_CLI::warning( 'Some items have errors (see the error lines). Nothing was written.' );
			WP_CLI::halt( 1 );
		}
		WP_CLI::success( 'Nothing was written.' );
	}

	/**
	 * A family's summary line, e.g. "products: 265 records: 254 to create, 9 skipped, …".
	 *
	 * @param MigrationReport $report The report.
	 * @param Family          $family The family.
	 */
	public static function summary( MigrationReport $report, Family $family ): string {
		if ( ! $report->is_built( $family ) ) {
			return sprintf( '%s: not built yet.', $family->value );
		}
		if ( Family::Settings === $family || Family::Display === $family ) {
			return sprintf(
				'%s: %d keys: %d to import, %d left, %d errors.',
				$family->value,
				count( $report->lines_of( $family ) ),
				$report->count( $family, Action::Create ),
				$report->count( $family, Action::Skip ),
				$report->count( $family, Action::Error )
			);
		}
		if ( $report->count( $family, Action::Waiting ) > 0 ) {
			return sprintf( '%s: %s.', $family->value, $report->lines_of( $family )[0]->note );
		}
		if ( Family::Products !== $family ) {
			return sprintf( '%s: %d items.', $family->value, count( $report->lines_of( $family ) ) );
		}
		$lines     = $report->lines_of( $family );
		$published = count( array_filter( $lines, static fn ( MigrationLine $line ): bool => 'publish' === $line->status ) );

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
	 * A kind of Lasso's data as a report line (family `data`).
	 *
	 * @param DataItem $item The item.
	 * @return array<string, string>
	 */
	private static function data_csv_row( DataItem $item ): array {
		return array(
			'family'   => 'data',
			'lasso_id' => $item->name,
			'name'     => '',
			'type'     => '',
			'status'   => '',
			'value'    => (string) $item->rows,
			'action'   => null !== $item->family ? 'moved' : ( $item->known ? 'left' : 'error' ),
			'target'   => null === $item->family ? '' : $item->family->value,
			'note'     => $item->reason,
		);
	}

	/**
	 * A line as printed.
	 *
	 * @param MigrationLine $line The line.
	 * @return array<string, string>
	 */
	private static function row( MigrationLine $line ): array {
		return array(
			'family'   => $line->family->value,
			'lasso_id' => $line->key,
			'name'     => $line->name,
			'type'     => $line->type,
			'status'   => $line->status,
			'value'    => $line->value,
			'action'   => $line->action->value,
			'target'   => $line->target,
			'note'     => $line->note,
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
		fputcsv( $handle, self::FIELDS, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, array_values( $row ), ',', '"', '' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- as above.
		fclose( $handle );
	}
}
