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
use AffibloomLassoMigrator\Domain\Migration\ProductRules;

/**
 * The products family (F10 R4-R6, D5-D7, D19; Task 25A): one line per Lasso record, every status. A record that
 * already has a product is unchanged; one with no link is skipped with the ASIN in its slug; a retired one becomes a
 * retired product pointing at its target's; a trashed one a product in the trash; a published one a new product. A
 * value Affibloom refuses is an error line; two products with one Lasso ID stop the family.
 */
final class ProductsStep implements Step {

	/** R6: how far a retired record's pointer is followed. */
	private const MAX_HOPS = 50;

	/**
	 * Builds the step.
	 *
	 * @param LassoSource       $source   Lasso's records.
	 * @param LassoRecordMapper $mapper  A record as Affibloom's fields.
	 * @param MigratedProducts  $migrated The ID map.
	 * @param ProductRules      $rules    What Affibloom refuses.
	 */
	public function __construct( private LassoSource $source, private LassoRecordMapper $mapper, private MigratedProducts $migrated, private ProductRules $rules ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Products;
	}

	/**
	 * One line per Lasso record.
	 *
	 * @return list<MigrationLine>
	 */
	public function plan(): array {
		$records = array();
		foreach ( $this->source->records( $this->source->ids() ) as $record ) {
			$records[ $record->id ] = $record;
		}

		$lines = array();
		foreach ( $records as $record ) {
			$products = $this->migrated->for_record( $record->id );
			if ( count( $products ) > 1 ) {
				$lines[] = $this->line( $record, Action::Error, '', sprintf( 'Lasso #%d has products %s: keep one, then run again.', $record->id, '#' . implode( ' and #', $products ) ) );
				return $lines;
			}
			$lines[] = $this->plan_one( $record, $products[0] ?? 0, $records );
		}

		return $lines;
	}

	/**
	 * What becomes of one record.
	 *
	 * @param LassoRecord             $record  The record.
	 * @param int                     $product Its product, 0 when it has none.
	 * @param array<int, LassoRecord> $records Every record by ID, for a retired one's target.
	 */
	private function plan_one( LassoRecord $record, int $product, array $records ): MigrationLine {
		if ( $product > 0 ) {
			return $this->line( $record, Action::Unchanged, '#' . $product );
		}
		$fields = $this->mapper->fields( $record );
		if ( null === $fields ) {
			$asin = preg_match( '/\b(b0[0-9a-z]{8})\b/i', str_replace( '-', ' ', $record->slug ), $found ) ? strtoupper( $found[1] ) : '';
			return $this->line( $record, Action::Skip, '', '' === $asin ? 'no link' : 'no link; ASIN ' . $asin . ' in its slug' );
		}
		$refusals = $this->rules->refusals( $fields );
		if ( array() !== $refusals ) {
			$reasons = array();
			foreach ( $refusals as $field => $reason ) {
				$reasons[] = $field . ': ' . $reason;
			}
			return $this->line( $record, Action::Error, '', implode( '; ', $reasons ) );
		}

		return match ( $record->status ) {
			'publish'      => $this->line( $record, Action::Create, '', (string) $fields['url'] ),
			'trash'        => $this->line( $record, Action::Trash, '', 'a product in the trash' ),
			'lasso_delete' => $this->retire( $record, $records ),
			default        => $this->line( $record, Action::Skip, '', 'status ' . $record->status . ': Lasso shows only published records' ),
		};
	}

	/**
	 * A retired record (R6, D5): its pointer followed to the record it ends at; a missing target leaves the product
	 * retired with no pointer.
	 *
	 * @param LassoRecord             $record  The retired record.
	 * @param array<int, LassoRecord> $records Every record by ID.
	 */
	private function retire( LassoRecord $record, array $records ): MigrationLine {
		$seen   = array( $record->id => true );
		$target = $record->repointed_to();
		for ( $hop = 1; $hop <= self::MAX_HOPS && isset( $records[ $target ] ) && ! isset( $seen[ $target ] ); $hop++ ) {
			if ( 'lasso_delete' !== $records[ $target ]->status ) {
				return $this->line( $record, Action::Retire, 'Lasso #' . $target, "points at Lasso #$target's product" );
			}
			$seen[ $target ] = true;
			$target          = $records[ $target ]->repointed_to();
		}

		return $this->line( $record, Action::Retire, '', 'its target is missing: retired with no pointer' );
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
