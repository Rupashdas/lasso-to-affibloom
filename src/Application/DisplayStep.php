<?php
/**
 * The display family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\DisplayWriter;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;

/**
 * The display family (F10 S22-S28, D9; Tasks 25B, 26D): Lasso's theme, colours and labels, elements, density and layout
 * for Single, Grid and List, and the grid's columns, written through the Display screen's rules one type at a time. A
 * part Affibloom already has is unchanged; the first run writes the others, a later run keeps Affibloom's and reports
 * them unless --update. A refused colour or label is an error line; the type's other parts are saved.
 */
final class DisplayStep implements RunStep {

	/**
	 * Builds the step.
	 *
	 * @param LassoSource   $source Lasso's data.
	 * @param DisplayMapper $mapper Lasso's display keys → Affibloom's display model.
	 * @param DisplayWriter $writer Affibloom's Display settings.
	 * @param MigrationLog  $log    Whether the family ran before.
	 */
	public function __construct( private LassoSource $source, private DisplayMapper $mapper, private DisplayWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Display;
	}

	/**
	 * One line per type and part, nothing written.
	 *
	 * @param bool $update Take Lasso's where Affibloom's differs (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return array_column( $this->decisions( $update ), 'line' );
	}

	/**
	 * Writes the parts to import, one type at a time.
	 *
	 * @param bool $update Take Lasso's where Affibloom's differs (--update).
	 * @param bool $force  Unused, as in plan().
	 * @return list<MigrationLine>
	 */
	public function run( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		$decisions = $this->decisions( $update );
		$writes    = array();
		foreach ( $decisions as $decision ) {
			if ( Action::Create === $decision['line']->action && null !== $decision['target'] ) {
				list( $type, $field, $value ) = $decision['target'];
				$writes[ $type ][ $field ]    = $value;
			}
		}
		$refused = array();
		foreach ( $writes as $type => $config ) {
			$refused[ $type ] = $this->writer->save( $type, $config );
		}

		$lines = array();
		foreach ( $decisions as $decision ) {
			$line    = $decision['line'];
			$target  = $decision['target'];
			$lines[] = null !== $target && 'palette' === $target[1] && array() !== ( $refused[ $target[0] ] ?? array() )
				? new MigrationLine( Family::Display, $line->key, '', '', '', Action::Error, $line->target, 'Affibloom refused ' . implode( ', ', $refused[ $target[0] ] ) . '; the rest is saved', $line->value )
				: $line;
		}

		return $lines;
	}

	/**
	 * Each line's decision: the mapper's line and, for a part carried, whether Affibloom already has it.
	 *
	 * @param bool $update With --update.
	 * @return list<array{line: MigrationLine, target: array{0: string, 1: string, 2: mixed}|null}>
	 */
	private function decisions( bool $update ): array {
		$settings = $this->source->settings();
		$targets  = $this->mapper->targets( $settings );
		$current  = $this->writer->values();
		$first    = ! $this->log->has_run( Family::Display );

		$decisions = array();
		foreach ( $this->mapper->lines( $settings ) as $line ) {
			$target = $targets[ $line->key ] ?? null;
			if ( null === $target ) {
				$decisions[] = array(
					'line'   => $line,
					'target' => null,
				);
				continue;
			}
			list( $type, $field, $value ) = $target;
			$has                          = 'columns' === $field ? ( $current['columns'] ?? null ) : ( $current[ $type ][ $field ] ?? null );
			$decisions[]                  = array(
				'line'   => match ( true ) {
					self::same( $field, $value, $has ) => self::with( $line, Action::Unchanged, $line->note ),
					$first || $update                  => self::with( $line, Action::Create, $line->note ),
					default                            => self::with( $line, Action::Skip, 'Affibloom has ' . ValueText::of( $has ) . ': kept (--update takes Lasso\'s)' ),
				},
				'target' => $target,
			);
		}

		return $decisions;
	}

	/**
	 * Whether Affibloom already has Lasso's part: every key Lasso gives with the same value (the density by its values:
	 * the screen names a preset itself).
	 *
	 * @param string $field     The part.
	 * @param mixed  $lasso     Lasso's value, mapped.
	 * @param mixed  $affibloom Affibloom's.
	 */
	private static function same( string $field, mixed $lasso, mixed $affibloom ): bool {
		if ( 'density' === $field && is_array( $lasso ) && is_array( $affibloom ) ) {
			return self::same( 'values', $lasso['values'] ?? array(), $affibloom['values'] ?? array() );
		}
		if ( is_array( $lasso ) ) {
			if ( ! is_array( $affibloom ) ) {
				return false;
			}
			foreach ( $lasso as $key => $value ) {
				if ( ! array_key_exists( $key, $affibloom ) || ! self::same( (string) $key, $value, $affibloom[ $key ] ) ) {
					return false;
				}
			}
			return true;
		}
		if ( is_bool( $lasso ) || is_bool( $affibloom ) ) {
			return (bool) $lasso === (bool) $affibloom;
		}

		return strtolower( trim( (string) $lasso ) ) === strtolower( trim( (string) $affibloom ) );
	}

	/**
	 * The line with another action and note.
	 *
	 * @param MigrationLine $line   The mapper's line.
	 * @param Action        $action The action.
	 * @param string        $note   The note.
	 */
	private static function with( MigrationLine $line, Action $action, string $note ): MigrationLine {
		return new MigrationLine( $line->family, $line->key, $line->name, $line->type, $line->status, $action, $line->target, $note, $line->value );
	}
}
