<?php
/**
 * The migration report as CSV.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Interface;

use AffibloomLassoMigrator\Domain\Migration\DataItem;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationReport;

/**
 * The migration report as CSV (F10 D18), one row per line and then one per kind of Lasso's data (family `data`), for
 * `--report` and the admin page's "Download report (CSV)".
 */
final class ReportCsv {

	/** The columns, in order. */
	public const FIELDS = array( 'family', 'lasso_id', 'name', 'type', 'status', 'value', 'action', 'target', 'note' );

	/**
	 * A report line as a row.
	 *
	 * @param MigrationLine $line The line.
	 * @return array<string, string>
	 */
	public static function line( MigrationLine $line ): array {
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
	 * Every row of the report.
	 *
	 * @param MigrationReport $report The report.
	 * @return list<array<string, string>>
	 */
	public static function rows( MigrationReport $report ): array {
		return array_merge( array_map( self::line( ... ), $report->lines ), array_map( self::data( ... ), $report->data ) );
	}

	/**
	 * Writes the header and the rows.
	 *
	 * @param resource                    $handle An open, writable stream.
	 * @param list<array<string, string>> $rows   The rows.
	 */
	public static function write( $handle, array $rows ): void {
		fputcsv( $handle, self::FIELDS, ',', '"', '' );
		foreach ( $rows as $row ) {
			fputcsv( $handle, array_values( $row ), ',', '"', '' );
		}
	}

	/**
	 * A kind of Lasso's data as a row.
	 *
	 * @param DataItem $item The item.
	 * @return array<string, string>
	 */
	private static function data( DataItem $item ): array {
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
}
