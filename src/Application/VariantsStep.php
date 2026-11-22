<?php
/**
 * The variants family.
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
use AffibloomLassoMigrator\Domain\Migration\VariantWriter;
use InvalidArgumentException;

/**
 * The variants family (F10 R15, AC15; Task 26H): each record's URL variants, in Lasso's order, become its product's
 * through the Variants tab's rules, so pages that link to a variant keep it. A product's list is replaced as a whole,
 * as the tab saves it; with 26C's rule, a later run replaces a list Affibloom holds otherwise only with --update. Every
 * row takes the product's kind, as in Lasso (R23); a row the rules refuse is an error line for that product.
 */
final class VariantsStep implements RunStep {

	/**
	 * Builds the step.
	 *
	 * @param LassoSource      $source   Lasso's data.
	 * @param MigratedProducts $migrated The products' ID map.
	 * @param VariantWriter    $writer   Affibloom's variants.
	 * @param MigrationLog     $log      Whether the family ran.
	 */
	public function __construct( private LassoSource $source, private MigratedProducts $migrated, private VariantWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Variants;
	}

	/**
	 * One line per record with variants, nothing written.
	 *
	 * @param bool $update Replace a list Affibloom holds otherwise (--update).
	 * @param bool $force  Unused: --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return $this->work( $update, false );
	}

	/**
	 * The variants.
	 *
	 * @param bool $update Replace a list Affibloom holds otherwise (--update).
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
		$first = ! $this->log->has_run( Family::Variants );
		$lines = array();
		foreach ( $this->source->variants() as $lasso_id => $rows ) {
			$count   = count( $rows ) . ' variant' . ( 1 === count( $rows ) ? '' : 's' );
			$product = $this->migrated->for_record( $lasso_id )[0] ?? 0;
			$line    = static fn ( Action $action, string $note ): MigrationLine => new MigrationLine( Family::Variants, (string) $lasso_id, $count, 'variants', '', $action, 0 === $product ? '' : (string) $product, $note );
			$has     = 0 === $product ? null : $this->writer->variants( $product );
			if ( null === $has ) {
				$lines[] = $line( Action::Skip, 0 === $product ? 'no product: the products family did not copy this record' : "its product is in Affibloom's trash, retired or deleted" );
				continue;
			}
			$wanted = array_map(
				static fn ( array $row ): array => array(
					'name' => $row['name'],
					'key'  => $row['key'],
				),
				$rows
			);
			$holds  = array_map(
				static fn ( array $row ): array => array(
					'name' => $row['name'],
					'key'  => $row['key'],
				),
				$has
			);
			if ( $holds === $wanted ) {
				$lines[] = $line( Action::Unchanged, '' );
				continue;
			}
			if ( array() !== $has && ! $first && ! $update ) {
				$lines[] = $line( Action::Skip, "Affibloom's list differs: kept (--update takes Lasso's)" );
				continue;
			}
			$done = $line( array() === $has ? Action::Create : Action::Update, array() === $has ? '' : 'replaces ' . count( $has ) . " of Affibloom's" );
			if ( $write ) {
				try {
					$this->writer->set( $product, $wanted );
				} catch ( InvalidArgumentException $refused ) {
					$done = $line( Action::Error, $refused->getMessage() );
				}
			}
			$lines[] = $done;
		}

		return $lines;
	}
}
