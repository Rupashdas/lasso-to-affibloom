<?php
/**
 * The migration, family by family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Domain\Migration\MigrationReport;

/**
 * The migration, family by family in the fixed order (F10 D3). Each task adds its family's step; a family without one
 * is reported as not built yet.
 */
final class MigrationService {

	/**
	 * The steps by family.
	 *
	 * @var array<string, Step>
	 */
	private array $steps = array();

	/**
	 * Builds the service.
	 *
	 * @param list<Step>        $steps One step per family.
	 * @param LeftBehind|null   $data  Every kind of Lasso's data and its verdict (Task 25C).
	 * @param MigrationLog|null $log   When each family last ran (Task 26A).
	 */
	public function __construct( array $steps, private ?LeftBehind $data = null, private ?MigrationLog $log = null ) {
		foreach ( $steps as $step ) {
			$this->steps[ $step->family()->value ] = $step;
		}
	}

	/**
	 * The dry run: what each family will do, nothing written; with every family, the verdict on all of Lasso's data.
	 *
	 * @param list<Family> $families The families, in the fixed order.
	 * @param bool         $update   With --update (F10 D8).
	 * @param bool         $force    With --force.
	 */
	public function plan( array $families, bool $update = false, bool $force = false ): MigrationReport {
		$lines     = array();
		$not_built = array();
		foreach ( $families as $family ) {
			if ( ! isset( $this->steps[ $family->value ] ) ) {
				$not_built[] = $family;
				continue;
			}
			array_push( $lines, ...$this->steps[ $family->value ]->plan( $update, $force ) );
		}

		// Family::parse() gives each family once, so every family is asked for when the counts match.
		$whole = count( Family::cases() ) === count( $families );

		return new MigrationReport( $families, $lines, $not_built, $whole && null !== $this->data ? $this->data->items() : array(), true, $update );
	}

	/**
	 * The migration: each family whose run is built, in the fixed order; the others are reported as not built.
	 *
	 * @param list<Family> $families The families, in the fixed order.
	 * @param bool         $update   With --update (F10 D8).
	 * @param bool         $force    With --force.
	 */
	public function run( array $families, bool $update = false, bool $force = false ): MigrationReport {
		$lines     = array();
		$not_built = array();
		foreach ( $families as $family ) {
			$step = $this->steps[ $family->value ] ?? null;
			if ( ! $step instanceof RunStep ) {
				$not_built[] = $family;
				continue;
			}
			$done = $step->run( $update, $force );
			array_push( $lines, ...$done );
			$this->log?->ran( $family, count( $done ), count( array_filter( $done, static fn ( MigrationLine $line ): bool => Action::Error === $line->action ) ) );
		}

		return new MigrationReport( $families, $lines, $not_built, array(), false, $update );
	}
}
