<?php
/**
 * The fields family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\FieldWriter;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use InvalidArgumentException;

/**
 * The fields family (F10 R12, AC12; Task 26E): the definitions first, then each product's values. A built-in field
 * (Lasso's and Affibloom's IDs are the same) keeps a name changed in Lasso; a custom field becomes a new one, its new ID
 * kept in the log's field map, so a second run finds it. A product's rows take Lasso's values, order, "visible" and
 * "show field name"; with 26C's rule, a product whose rows Affibloom already has is unchanged, and a later run keeps
 * Affibloom's differing rows unless --update. A product not published in Affibloom is left as it is.
 */
final class FieldsStep implements RunStep {

	/** The log's field map: Lasso field ID → Affibloom field ID. */
	public const MAP = 'field_map';

	/** Lasso's and Affibloom's Primary Rating, which Affibloom always shows first. */
	private const RATING = 1;

	/**
	 * Builds the step.
	 *
	 * @param LassoSource      $source   Lasso's data.
	 * @param FieldWriter      $writer   Affibloom's fields.
	 * @param MigratedProducts $migrated The products' ID map.
	 * @param MigrationLog     $log      Whether the family ran, and the field map.
	 */
	public function __construct( private LassoSource $source, private FieldWriter $writer, private MigratedProducts $migrated, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Fields;
	}

	/**
	 * One line per definition and per product with values, nothing written.
	 *
	 * @param bool $update Take Lasso's rows where Affibloom's differ (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The definitions, then the products' rows.
	 *
	 * @param bool $update Take Lasso's rows where Affibloom's differ (--update).
	 * @param bool $force  Unused, as in plan().
	 * @return list<MigrationLine>
	 */
	public function run( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, true );
	}

	/**
	 * Decides each line and, when writing, carries it out.
	 *
	 * @param bool $update With --update.
	 * @param bool $write  The run, not the dry run.
	 * @return list<MigrationLine>
	 */
	private function work( bool $update, bool $write ): array {
		$fields  = $this->source->fields();
		$first   = ! $this->log->has_run( Family::Fields );
		$map     = $this->log->map( self::MAP );
		$current = array_column( $this->writer->definitions(), null, 'id' );
		$lines   = array();

		foreach ( $fields->definitions as $definition ) {
			$id   = $definition['id'];
			$line = static fn ( Action $action, string $target, string $note = '' ): MigrationLine => new MigrationLine( Family::Fields, (string) $id, $definition['name'], 'definition', '', $action, $target, $note );
			if ( ! empty( $current[ $id ]['built_in'] ) ) {
				if ( $current[ $id ]['name'] === $definition['name'] ) {
					$lines[] = $line( Action::Unchanged, '#' . $id );
					continue;
				}
				$lines[] = $this->apply( $write, fn () => $this->writer->rename( $id, $definition['name'] ), $line( Action::Update, '#' . $id, 'built-in field renamed from ' . $current[ $id ]['name'] ) );
				continue;
			}
			if ( isset( $map[ $id ], $current[ $map[ $id ] ] ) ) {
				$lines[] = $line( Action::Unchanged, '#' . $map[ $id ] );
				continue;
			}
			$lines[] = $this->apply(
				$write,
				function () use ( $definition, $id, &$map ): void {
					$map[ $id ] = $this->writer->create( $definition['name'], $definition['type'], $definition['description'] );
					$this->log->remember( self::MAP, $id, $map[ $id ] );
				},
				$line( Action::Create, 'a new ' . $definition['type'] . ' field' )
			);
		}

		foreach ( $fields->values as $lasso_id => $rows ) {
			$product = $this->migrated->for_record( $lasso_id )[0] ?? 0;
			$line    = static fn ( Action $action, string $note = '' ): MigrationLine => new MigrationLine( Family::Fields, (string) $lasso_id, count( $rows ) . ' field values', 'values', '', $action, 0 === $product ? '' : '#' . $product, $note );
			if ( 0 === $product ) {
				$lines[] = $line( Action::Skip, 'no product yet: migrate the products first' );
				continue;
			}
			$given = array();
			foreach ( $rows as $row ) {
				$given[] = array( 'field' => $map[ $row['field'] ] ?? $row['field'] ) + $row;
			}
			$has = $this->writer->rows( $product );
			if ( null === $has ) {
				$lines[] = $line( Action::Skip, 'its product is not published in Affibloom: left as it is' );
				continue;
			}
			if ( self::same( $given, $has ) ) {
				$lines[] = $line( Action::Unchanged );
			} elseif ( $first || $update ) {
				$lines[] = $this->apply( $write, fn () => $this->writer->set_rows( $product, $given ), $line( Action::Create ) );
			} else {
				$lines[] = $line( Action::Skip, "Affibloom's field values differ: kept (--update takes Lasso's)" );
			}
		}

		return $lines;
	}

	/**
	 * Carries out a change when writing; a refusal becomes an error line.
	 *
	 * @param bool          $write  The run, not the dry run.
	 * @param callable      $change The change.
	 * @param MigrationLine $line   What it does.
	 */
	private function apply( bool $write, callable $change, MigrationLine $line ): MigrationLine {
		if ( ! $write ) {
			return $line;
		}
		try {
			$change();
		} catch ( InvalidArgumentException $refused ) {
			return new MigrationLine( $line->family, $line->key, $line->name, $line->type, '', Action::Error, $line->target, $refused->getMessage() );
		}

		return $line;
	}

	/**
	 * Whether Affibloom's rows are Lasso's: the same fields in the same order (Primary Rating first, where Affibloom
	 * always shows it), values, "visible" and "show field name".
	 *
	 * @param list<array{field: int, value: string, visible: bool, show_name: bool}> $lasso     Lasso's rows, mapped.
	 * @param list<array{field: int, value: string, visible: bool, show_name: bool}> $affibloom Affibloom's.
	 */
	private static function same( array $lasso, array $affibloom ): bool {
		usort( $lasso, static fn ( array $a, array $b ): int => (int) ( self::RATING !== $a['field'] ) <=> (int) ( self::RATING !== $b['field'] ) );
		$plain = static fn ( array $rows ): array => array_map( static fn ( array $row ): array => array( (int) $row['field'], trim( (string) $row['value'] ), (bool) $row['visible'], self::RATING === (int) $row['field'] && (bool) $row['show_name'] ), $rows );

		return $plain( $lasso ) === $plain( $affibloom );
	}
}
