<?php
/**
 * The tables family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Domain\Migration\ProductWriter;
use AffibloomLassoMigrator\Domain\Migration\TableWriter;
use InvalidArgumentException;

/**
 * The tables family (F10 R14, AC16; Task 26I): each Lasso comparison table becomes an Affibloom table with its name,
 * style, theme and Show Headers, its products in Lasso's order with their badges, its field groups in order and each
 * product's value per field, as Lasso stores them (a button cell is its JSON in both). Products through the ID map,
 * custom fields through `field_map` (built-in fields keep their IDs), the table's ID kept in `table_map`. With 26C's
 * rule, a later run replaces a table Affibloom holds otherwise only with --update. A product that is not published in
 * Affibloom is left out of the table, named in the line; Lasso's Show Title has no Affibloom setting.
 */
final class TablesStep implements RunStep {

	public const MAP = 'table_map';

	/**
	 * Builds the step.
	 *
	 * @param LassoSource      $source   Lasso's data.
	 * @param MigratedProducts $migrated The products' ID map.
	 * @param ProductWriter    $products Affibloom's products, to tell a published one.
	 * @param TableWriter      $writer   Affibloom's tables.
	 * @param MigrationLog     $log      Whether the family ran, and the field and table maps.
	 */
	public function __construct( private LassoSource $source, private MigratedProducts $migrated, private ProductWriter $products, private TableWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Tables;
	}

	/**
	 * One line per table, nothing written.
	 *
	 * @param bool $update Replace a table Affibloom holds otherwise (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The tables.
	 *
	 * @param bool $update Replace a table Affibloom holds otherwise (--update).
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
		$first  = ! $this->log->has_run( Family::Tables );
		$fields = $this->log->map( FieldsStep::MAP );
		$map    = $this->log->map( self::MAP );
		$lines  = array();

		foreach ( $this->source->tables() as $lasso ) {
			$id      = (int) $lasso['id'];
			$target  = $map[ $id ] ?? 0;
			$dropped = array();
			$data    = $this->mapped( $lasso, $fields, $dropped );
			$notes   = array();
			if ( array() !== $dropped ) {
				$notes[] = 'left out, not published in Affibloom: Lasso records ' . implode( ', ', $dropped );
			}
			if ( ! empty( $lasso['show_title'] ) ) {
				$notes[] = "Show Title: Affibloom's tables have no such setting";
			}
			$line = static fn ( Action $action, string $note, int $table ): MigrationLine => new MigrationLine( Family::Tables, (string) $id, (string) $lasso['title'], 'table', '', $action, 0 === $table ? '' : (string) $table, $note );
			$has  = 0 === $target ? null : $this->writer->table( $target );
			if ( null !== $has && $has == $data ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- the same keys and values; key order differs.
				$lines[] = $line( Action::Unchanged, implode( '; ', $notes ), $target );
				continue;
			}
			if ( null !== $has && ! $first && ! $update ) {
				$lines[] = $line( Action::Skip, "Affibloom's table differs: kept (--update takes Lasso's)", $target );
				continue;
			}
			$action = null === $has ? Action::Create : Action::Update;
			if ( ! $write ) {
				$lines[] = $line( $action, implode( '; ', $notes ), $target );
				continue;
			}
			try {
				$stored = $this->writer->put( null === $has ? 0 : $target, $data );
				$this->log->remember( self::MAP, $id, $stored );
				$lines[] = $line( $action, implode( '; ', $notes ), $stored );
			} catch ( InvalidArgumentException $refused ) {
				$lines[] = $line( Action::Error, $refused->getMessage(), $target );
			}
		}

		return $lines;
	}

	/**
	 * A Lasso table in Affibloom's shape.
	 *
	 * @param array<string, mixed> $lasso   The table as LassoSource::tables() gives it.
	 * @param array<int, int>      $fields  Lasso field ID → Affibloom field ID; a built-in field keeps its ID.
	 * @param list<int>            $dropped Lasso records left out, filled in.
	 * @return array<string, mixed>
	 */
	private function mapped( array $lasso, array $fields, array &$dropped ): array {
		$products = array();
		$ids      = array();
		foreach ( $lasso['products'] as $row ) {
			$product = $this->migrated->for_record( (int) $row['lasso_id'] )[0] ?? 0;
			if ( 0 === $product || null === $this->products->fields( $product ) ) {
				$dropped[] = (int) $row['lasso_id'];
				continue;
			}
			$ids[ (int) $row['lasso_id'] ] = $product;
			$products[]                    = array(
				'id'    => $product,
				'badge' => trim( (string) $row['badge'] ),
			);
		}
		$groups = array();
		foreach ( $lasso['groups'] as $group ) {
			$groups[] = array(
				'id'     => (string) $group['id'],
				'fields' => array_map( static fn ( int $field ): int => $fields[ $field ] ?? $field, $group['fields'] ),
			);
		}
		$values = array();
		foreach ( $lasso['values'] as $record => $cells ) {
			if ( ! isset( $ids[ $record ] ) ) {
				continue;
			}
			foreach ( $cells as $field => $value ) {
				$values[ $ids[ $record ] ][ $fields[ $field ] ?? $field ] = (string) $value;
			}
		}

		return array(
			'name'            => (string) $lasso['title'],
			'style'           => strtolower( (string) $lasso['style'] ),
			'theme'           => (string) $lasso['theme'],
			'show_headers'    => (bool) $lasso['show_headers'],
			'show_field_name' => false,
			'products'        => $products,
			'groups'          => $groups,
			'values'          => $values,
		);
	}
}
