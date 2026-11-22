<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\LassoRecordMapper;
use AffibloomLassoMigrator\Application\ProductsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\MigratedProducts;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\ProductRules;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * F10 R5, R6, D5-D7, D19 (Task 25A): one line per record, every status, with what becomes of it.
 */
final class ProductsStepTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
	}

	/**
	 * The step over these records.
	 *
	 * @param list<LassoRecord>     $records  Lasso's records.
	 * @param array<int, list<int>> $map      Products by Lasso ID.
	 * @param array<string, string> $refusals What Affibloom refuses in every product.
	 * @return list<MigrationLine>
	 */
	private static function plan( array $records, array $map = array(), array $refusals = array() ): array {
		$source   = new class( $records ) implements LassoSource {
			/** @param list<LassoRecord> $records */
			public function __construct( private array $records ) {}
			public function ids(): array {
				return array_map( static fn ( LassoRecord $r ): int => $r->id, $this->records );
			}
			public function records( array $ids ): array {
				return array_values( array_filter( $this->records, static fn ( LassoRecord $r ): bool => in_array( $r->id, $ids, true ) ) );
			}
			public function settings(): LassoSettings {
				return new LassoSettings( array() );
			}
			public function inventory(): LassoInventory {
				return new LassoInventory( array() );
			}
		};
		$migrated = new class( $map ) implements MigratedProducts {
			/** @param array<int, list<int>> $map */
			public function __construct( private array $map ) {}
			public function for_record( int $lasso_id ): array {
				return $this->map[ $lasso_id ] ?? array();
			}
		};
		$rules    = new class( $refusals ) implements ProductRules {
			/** @param array<string, string> $refusals */
			public function __construct( private array $refusals ) {}
			public function refusals( array $fields ): array {
				return $this->refusals;
			}
		};

		return ( new ProductsStep( $source, new LassoRecordMapper( 'https://site.example' ), $migrated, $rules ) )->plan();
	}

	private static function record( int $id, string $status = 'publish', array $meta = array( 'lasso_final_url' => 'https://c.example/' ), string $slug = '' ): LassoRecord {
		return new LassoRecord( id: $id, title: "Record $id", status: $status, slug: $slug, meta: $meta );
	}

	/**
	 * Each line's action, by Lasso ID.
	 *
	 * @param list<MigrationLine> $lines The lines.
	 * @return array<string, string>
	 */
	private static function actions( array $lines ): array {
		$actions = array();
		foreach ( $lines as $line ) {
			$actions[ $line->key ] = $line->action->value;
		}

		return $actions;
	}

	public function test_every_status_has_its_action(): void {
		$lines = self::plan(
			array(
				self::record( 1 ),
				self::record( 2, 'trash' ),
				self::record( 3, 'lasso_delete', array( 'lasso_final_url' => 'https://c.example/', 'lasso_repointed_to_lasso_id' => '1' ) ),
				self::record( 4, 'publish', array(), 'amzn-mizuno-b07xyz1234-shoe' ),
				self::record( 5, 'draft' ),
			)
		);

		self::assertSame(
			array(
				'1' => 'create',
				'2' => 'trash',
				'3' => 'retire',
				'4' => 'skip',
				'5' => 'skip',
			),
			self::actions( $lines )
		);
		self::assertSame( 'Lasso #1', $lines[2]->target );
		self::assertSame( 'no link; ASIN B07XYZ1234 in its slug', $lines[3]->note );
		self::assertSame( 'other', $lines[0]->type );
		self::assertSame( 'publish', $lines[0]->status );
	}

	public function test_a_record_that_has_a_product_is_unchanged_whatever_its_status(): void {
		$lines = self::plan( array( self::record( 1 ), self::record( 2, 'trash' ) ), array( 1 => array( 40 ), 2 => array( 41 ) ) );

		self::assertSame( array( '1' => 'unchanged', '2' => 'unchanged' ), self::actions( $lines ) );
		self::assertSame( '#40', $lines[0]->target );
	}

	public function test_a_retired_record_follows_its_pointer_through_retired_ones_and_stops_at_a_loop_or_a_missing_target(): void {
		$retired = static fn ( int $id, int $to ): LassoRecord => self::record( $id, 'lasso_delete', array( 'lasso_final_url' => 'https://c.example/', 'lasso_repointed_to_lasso_id' => (string) $to ) );

		$lines = self::plan( array( self::record( 1 ), $retired( 2, 3 ), $retired( 3, 1 ), $retired( 4, 5 ), $retired( 5, 4 ), $retired( 6, 99 ) ) );

		self::assertSame( 'Lasso #1', $lines[1]->target );
		self::assertSame( 'Lasso #1', $lines[2]->target );
		self::assertSame( '', $lines[3]->target );
		self::assertSame( 'its target is missing: retired with no pointer', $lines[5]->note );
		self::assertSame( Action::Retire, $lines[5]->action );
	}

	public function test_a_value_affibloom_refuses_is_an_error_line_and_the_rest_continue(): void {
		$lines = self::plan( array( self::record( 1 ), self::record( 2 ) ), array(), array( 'url' => 'invalid_url' ) );

		self::assertSame( array( '1' => 'error', '2' => 'error' ), self::actions( $lines ) );
		self::assertSame( 'url: invalid_url', $lines[0]->note );
	}

	public function test_two_products_with_one_lasso_id_stop_the_family_naming_both(): void {
		$lines = self::plan( array( self::record( 1 ), self::record( 2 ), self::record( 3 ) ), array( 2 => array( 40, 41 ) ) );

		self::assertCount( 2, $lines );
		self::assertSame( Action::Error, $lines[1]->action );
		self::assertStringContainsString( '#40 and #41', $lines[1]->note );
	}
}
