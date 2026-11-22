<?php
/**
 * What the migration will do, or did.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Migration;

/**
 * The report (F10 D18): one line per item, the families asked for in the fixed order, and which of them are not built
 * yet. The command, the CSV and the admin page (25D) read the same report.
 */
final class MigrationReport {

	/**
	 * Builds the report.
	 *
	 * @param list<Family>        $families  The families asked for, in order.
	 * @param list<MigrationLine> $lines     Every item's line.
	 * @param list<Family>        $not_built The families among them whose step does not exist yet.
	 * @param list<DataItem>      $data      Every kind of Lasso's data and its verdict, when every family was planned.
	 */
	public function __construct( public readonly array $families, public readonly array $lines, public readonly array $not_built = array(), public readonly array $data = array() ) {}

	/**
	 * One family's lines.
	 *
	 * @param Family $family The family.
	 * @return list<MigrationLine>
	 */
	public function lines_of( Family $family ): array {
		return array_values( array_filter( $this->lines, static fn ( MigrationLine $line ): bool => $line->family === $family ) );
	}

	/**
	 * How many of a family's lines have the action.
	 *
	 * @param Family $family The family.
	 * @param Action $action The action.
	 */
	public function count( Family $family, Action $action ): int {
		return count( array_filter( $this->lines_of( $family ), static fn ( MigrationLine $line ): bool => $line->action === $action ) );
	}

	/**
	 * Whether the family's step is not built yet.
	 *
	 * @param Family $family The family.
	 */
	public function is_built( Family $family ): bool {
		return ! in_array( $family, $this->not_built, true );
	}

	/**
	 * Whether any line is an error (the command then exits with 1, D19).
	 */
	public function has_errors(): bool {
		foreach ( $this->lines as $line ) {
			if ( Action::Error === $line->action ) {
				return true;
			}
		}
		foreach ( $this->data as $item ) {
			if ( ! $item->known ) {
				return true;
			}
		}

		return false;
	}
}
