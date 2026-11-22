<?php
/**
 * The products family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;
use AffibloomLassoMigrator\Domain\Migration\ProductRules;
use AffibloomLassoMigrator\Domain\Migration\ProductWriter;
use InvalidArgumentException;

/**
 * The products family (F10 R4-R6, D1, D4-D7, D19; Tasks 25A, 26A): one line per Lasso record, every status. A record
 * that already has a product is unchanged (a product of the one-time import is adopted: it gets its mark); one with
 * no link is skipped with the ASIN in its slug; a published one becomes a new product with the record's dates; a
 * trashed one a product in the trash; a retired one a retired product pointing at its target's, made after the
 * targets. A value Affibloom refuses is an error line and the rest go on; two products with one Lasso ID stop the
 * family. The dry run and the run share each record's decision, so the run does what the dry run said.
 */
final class ProductsStep implements RunStep {

	/** R6: how far a retired record's pointer is followed. */
	private const MAX_HOPS = 50;

	/**
	 * Builds the step.
	 *
	 * @param LassoSource       $source   Lasso's records.
	 * @param LassoRecordMapper $mapper   A record as Affibloom's fields.
	 * @param MigratedProducts  $migrated The ID map and the marks.
	 * @param ProductRules      $rules    What Affibloom refuses.
	 * @param ProductWriter     $writer   Affibloom's products, written through its API.
	 */
	public function __construct( private LassoSource $source, private LassoRecordMapper $mapper, private MigratedProducts $migrated, private ProductRules $rules, private ProductWriter $writer ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Products;
	}

	/**
	 * One line per Lasso record, nothing written.
	 *
	 * @return list<MigrationLine>
	 */
	public function plan(): array {
		return array_map( static fn ( array $decision ): MigrationLine => $decision['line'], $this->decisions() );
	}

	/**
	 * The migration: published and trashed records first, then the retired ones, so their targets have products.
	 *
	 * @return list<MigrationLine>
	 */
	public function run(): array {
		$decisions = $this->decisions();
		$lines     = array();
		foreach ( $decisions as $key => $decision ) {
			if ( Action::Retire !== $decision['line']->action ) {
				$lines[ $key ] = $this->apply( $decision );
			}
		}
		foreach ( $decisions as $key => $decision ) {
			if ( Action::Retire === $decision['line']->action ) {
				$lines[ $key ] = $this->apply( $decision );
			}
		}
		ksort( $lines );

		return array_values( $lines );
	}

	/**
	 * Each record's decision, in Lasso's order; the family stops at a record with two products.
	 *
	 * @return list<array{record: LassoRecord, line: MigrationLine, fields: array<string, mixed>|null, product: int, target: int}>
	 */
	private function decisions(): array {
		$records = array();
		foreach ( $this->source->records( $this->source->ids() ) as $record ) {
			$records[ $record->id ] = $record;
		}

		$decisions = array();
		foreach ( $records as $record ) {
			$products = $this->migrated->for_record( $record->id );
			if ( count( $products ) > 1 ) {
				$decisions[] = $this->decision( $record, Action::Error, null, sprintf( 'Lasso #%d has products %s: keep one, then run again.', $record->id, '#' . implode( ' and #', $products ) ) );
				break;
			}
			$decisions[] = $this->decide( $record, $products[0] ?? 0, $records );
		}

		return $decisions;
	}

	/**
	 * What becomes of one record.
	 *
	 * @param LassoRecord             $record  The record.
	 * @param int                     $product Its product, 0 when it has none.
	 * @param array<int, LassoRecord> $records Every record by ID, for a retired one's target.
	 * @return array{record: LassoRecord, line: MigrationLine, fields: array<string, mixed>|null, product: int, target: int}
	 */
	private function decide( LassoRecord $record, int $product, array $records ): array {
		$fields = $this->mapper->fields( $record );
		if ( $product > 0 ) {
			return $this->decision( $record, Action::Unchanged, $fields, '', '#' . $product, $product );
		}
		if ( null === $fields ) {
			$asin = preg_match( '/\b(b0[0-9a-z]{8})\b/i', str_replace( '-', ' ', $record->slug ), $found ) ? strtoupper( $found[1] ) : '';
			return $this->decision( $record, Action::Skip, null, '' === $asin ? 'no link' : 'no link; ASIN ' . $asin . ' in its slug' );
		}
		$refusals = $this->rules->refusals( $fields );
		if ( array() !== $refusals ) {
			$reasons = array();
			foreach ( $refusals as $field => $reason ) {
				$reasons[] = $field . ': ' . $reason;
			}
			return $this->decision( $record, Action::Error, $fields, implode( '; ', $reasons ) );
		}

		if ( 'lasso_delete' === $record->status ) {
			$target = $this->target( $record, $records );
			return 0 === $target
				? $this->decision( $record, Action::Retire, $fields, 'its target is missing: retired with no pointer' )
				: $this->decision( $record, Action::Retire, $fields, "points at Lasso #$target's product", 'Lasso #' . $target, 0, $target );
		}

		return match ( $record->status ) {
			'publish' => $this->decision( $record, Action::Create, $fields, (string) $fields['url'] ),
			'trash'   => $this->decision( $record, Action::Trash, $fields, 'a product in the trash' ),
			default   => $this->decision( $record, Action::Skip, null, 'status ' . $record->status . ': Lasso shows only published records' ),
		};
	}

	/**
	 * Carries out a decision and says what was done.
	 *
	 * @param array{record: LassoRecord, line: MigrationLine, fields: array<string, mixed>|null, product: int, target: int} $decision The decision.
	 */
	private function apply( array $decision ): MigrationLine {
		$record = $decision['record'];
		$line   = $decision['line'];
		$fields = $decision['fields'];
		if ( Action::Unchanged === $line->action ) {
			// The one-time import's product (D7): adopted, so a later --update can tell an edit from a Lasso change.
			if ( null !== $fields && null === $this->migrated->mark( $decision['product'] ) ) {
				$this->mark( $decision['product'], $record->id, $fields );
				return $this->line( $record, Action::Unchanged, $line->target, 'adopted from the one-time import' );
			}
			return $line;
		}
		if ( null === $fields || ! in_array( $line->action, array( Action::Create, Action::Trash, Action::Retire ), true ) ) {
			return $line;
		}

		try {
			$product = $this->writer->import( $fields, $record->date, $record->modified );
			$this->mark( $product, $record->id, $fields );
			// A status change stamps "now"; the record's dates are written again after it (Lasso's trash or retire time).
			if ( Action::Trash === $line->action ) {
				$this->writer->trash( $product );
				$this->writer->date( $product, $record->date, $record->modified );
			} elseif ( Action::Retire === $line->action ) {
				$to = 0 === $decision['target'] ? 0 : (int) ( $this->migrated->for_record( $decision['target'] )[0] ?? 0 );
				$this->writer->retire( $product, $to );
				$this->writer->date( $product, $record->date, $record->modified );
				return $this->line( $record, Action::Retire, '#' . $product, 0 === $to ? 'retired with no pointer: its target has no product' : '#' . $to . ' took its place' );
			}
		} catch ( InvalidArgumentException $refused ) {
			return $this->line( $record, Action::Error, '', $refused->getMessage() );
		}

		return $this->line( $record, $line->action, '#' . $product, $line->note );
	}

	/**
	 * Names the product's record and keeps what it was given and what Affibloom stored (D8).
	 *
	 * @param int                  $product  The product.
	 * @param int                  $lasso_id Its record.
	 * @param array<string, mixed> $fields   What the record's fields are.
	 */
	private function mark( int $product, int $lasso_id, array $fields ): void {
		$this->migrated->link( $product, $lasso_id, new MigrationMark( MigrationMark::hash( $fields ), MigrationMark::hash( $this->writer->fields( $product ) ?? array() ) ) );
	}

	/**
	 * The record a retired one ends at (R6): its pointer followed through retired records, 0 at a missing target or a
	 * loop.
	 *
	 * @param LassoRecord             $record  The retired record.
	 * @param array<int, LassoRecord> $records Every record by ID.
	 */
	private function target( LassoRecord $record, array $records ): int {
		$seen   = array( $record->id => true );
		$target = $record->repointed_to();
		for ( $hop = 1; $hop <= self::MAX_HOPS && isset( $records[ $target ] ) && ! isset( $seen[ $target ] ); $hop++ ) {
			if ( 'lasso_delete' !== $records[ $target ]->status ) {
				return $target;
			}
			$seen[ $target ] = true;
			$target          = $records[ $target ]->repointed_to();
		}

		return 0;
	}

	/**
	 * A decision.
	 *
	 * @param LassoRecord               $record  The record.
	 * @param Action                    $action  What becomes of it.
	 * @param array<string, mixed>|null $fields  Its Affibloom fields.
	 * @param string                    $note    Why.
	 * @param string                    $target  What it becomes or points at.
	 * @param int                       $product Its product, when it has one.
	 * @param int                       $to      A retired record's target record.
	 * @return array{record: LassoRecord, line: MigrationLine, fields: array<string, mixed>|null, product: int, target: int}
	 */
	private function decision( LassoRecord $record, Action $action, ?array $fields, string $note, string $target = '', int $product = 0, int $to = 0 ): array {
		return array(
			'record'  => $record,
			'line'    => $this->line( $record, $action, $target, $note ),
			'fields'  => $fields,
			'product' => $product,
			'target'  => $to,
		);
	}

	/**
	 * A line for a record.
	 *
	 * @param LassoRecord $record The record.
	 * @param Action      $action What becomes of it.
	 * @param string      $target What it becomes or points at.
	 * @param string      $note   Why.
	 */
	private function line( LassoRecord $record, Action $action, string $target = '', string $note = '' ): MigrationLine {
		return new MigrationLine(
			Family::Products,
			(string) $record->id,
			trim( html_entity_decode( $record->title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			'' === $record->product_type ? 'other' : $record->product_type,
			$record->status,
			$action,
			$target,
			$note
		);
	}
}
