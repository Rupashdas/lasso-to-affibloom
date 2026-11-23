<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\LassoRecordMapper;
use AffibloomLassoMigrator\Application\ProductsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoRecord;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAffibloom;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * F10 R5, R6, D1, D5-D7, D19, AC7, AC8 (Tasks 25A, 26A): one line per record, every status; the run makes what the dry
 * run said, retired records after their targets, adopts the one-time import's products and changes nothing again.
 */
final class ProductsStepTest extends TestCase {

	private FakeAffibloom $affibloom;

	private FakeIdMap $map;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_strip_all_tags' )->alias( 'strip_tags' );
		$this->affibloom = new FakeAffibloom();
		$this->map       = new FakeIdMap();
	}

	/**
	 * The step over these records.
	 *
	 * @param list<LassoRecord> $records Lasso's records.
	 */
	private function step( array $records ): ProductsStep {
		return new ProductsStep( new FakeLassoSource( $records ), new LassoRecordMapper( 'https://site.example' ), $this->map, $this->affibloom, $this->affibloom );
	}

	private static function record( int $id, string $status = 'publish', array $meta = array( 'lasso_final_url' => 'https://c.example/' ), string $slug = '' ): LassoRecord {
		return new LassoRecord( id: $id, title: "Record $id", status: $status, slug: $slug, date: '2024-01-0' . ( $id % 9 + 1 ) . ' 10:00:00', modified: '2025-01-01 10:00:00', meta: $meta );
	}

	private static function retired( int $id, int $to ): LassoRecord {
		return self::record(
			$id,
			'lasso_delete',
			array(
				'lasso_final_url'             => 'https://c.example/',
				'lasso_repointed_to_lasso_id' => (string) $to,
			)
		);
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
		$lines = $this->step(
			array(
				self::record( 1 ),
				self::record( 2, 'trash' ),
				self::retired( 3, 1 ),
				self::record( 4, 'publish', array(), 'amzn-mizuno-b07xyz1234-shoe' ),
				self::record( 5, 'draft' ),
			)
		)->plan();

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
		self::assertSame( array(), $this->affibloom->products, 'The dry run writes nothing.' );
	}

	public function test_a_retired_record_follows_its_pointer_through_retired_ones_and_stops_at_a_loop_or_a_missing_target(): void {
		$lines = $this->step( array( self::record( 1 ), self::retired( 2, 3 ), self::retired( 3, 1 ), self::retired( 4, 5 ), self::retired( 5, 4 ), self::retired( 6, 99 ) ) )->plan();

		self::assertSame( 'Lasso #1', $lines[1]->target );
		self::assertSame( 'Lasso #1', $lines[2]->target );
		self::assertSame( '', $lines[3]->target );
		self::assertSame( 'its target is missing: retired with no pointer', $lines[5]->note );
	}

	public function test_the_run_makes_each_product_with_its_dates_status_and_mark_and_retires_after_the_targets(): void {
		// The retired record comes first in Lasso's order; its target's product must exist before it is retired.
		$lines = $this->step( array( self::retired( 3, 1 ), self::record( 1 ), self::record( 2, 'trash' ), self::record( 4, 'publish', array() ) ) )->run();

		self::assertSame(
			array(
				'3' => 'retire',
				'1' => 'create',
				'2' => 'trash',
				'4' => 'skip',
			),
			self::actions( $lines )
		);
		$target = $this->map->for_record( 1 )[0];
		$old    = $this->map->for_record( 3 )[0];
		self::assertSame( 'publish', $this->affibloom->products[ $target ]['status'] );
		self::assertSame( 'trash', $this->affibloom->products[ $this->map->for_record( 2 )[0] ]['status'] );
		self::assertSame( 'retired', $this->affibloom->products[ $old ]['status'] );
		self::assertSame( $target, $this->affibloom->products[ $old ]['to'] );
		self::assertSame( '2024-01-02 10:00:00', $this->affibloom->products[ $target ]['created'] );
		self::assertSame( '2025-01-01 10:00:00', $this->affibloom->products[ $old ]['modified'] ?? '', 'Dated again after it was retired.' );
		self::assertNotNull( $this->map->mark( $target ) );
		self::assertSame( '#' . $target, $lines[1]->target );
	}

	public function test_a_second_run_changes_nothing(): void {
		$records = array( self::record( 1 ), self::record( 2, 'trash' ), self::retired( 3, 1 ) );
		$this->step( $records )->run();
		$written = $this->affibloom->products;

		$again = $this->step( $records )->run();

		self::assertSame(
			array(
				'1' => 'unchanged',
				'2' => 'unchanged',
				'3' => 'unchanged',
			),
			self::actions( $again )
		);
		self::assertSame( $written, $this->affibloom->products );
	}

	public function test_the_one_time_import_s_product_is_adopted_once(): void {
		$this->affibloom->products[40] = array(
			'status'  => 'publish',
			'fields'  => array( 'name' => 'Imported' ),
			'to'      => 0,
			'created' => '',
		);
		$this->map->map[1]             = array( 40 );

		$first = $this->step( array( self::record( 1 ) ) )->run();
		self::assertSame( 'adopted from the one-time import', $first[0]->note );
		self::assertNotNull( $this->map->mark( 40 ) );

		$second = $this->step( array( self::record( 1 ) ) )->run();
		self::assertSame( '', $second[0]->note );
	}

	/**
	 * Task 29A (deviation 161): an adopted product kept the one-time import's values; --update --force gives it Lasso's
	 * where they differ, Affibloom's fresh Amazon price aside, once; without --force, or for a product the migration
	 * made, nothing changes.
	 */
	public function test_force_gives_an_adopted_product_lasso_s_values_once_keeping_its_price(): void {
		$lasso    = self::record(
			1,
			'publish',
			array(
				'lasso_final_url' => 'https://c.example/',
				'open_new_tab'    => '1',
			)
		);
		$fields   = ( new LassoRecordMapper( 'https://site.example' ) )->fields( $lasso );
		$imported = array_merge( $fields, array( 'new_tab' => false, 'price' => '$19.99' ) );
		$this->affibloom->products[40] = array(
			'status'  => 'publish',
			'fields'  => $imported,
			'to'      => 0,
			'created' => '',
		);
		$this->map->map[1]             = array( 40 );
		$this->step( array( $lasso ) )->run();
		self::assertTrue( $this->map->mark( 40 )->adopted );

		self::assertSame( Action::Unchanged, $this->step( array( $lasso ) )->run( true )[0]->action, 'Without --force an adopted product keeps its values.' );

		$forced = $this->step( array( $lasso ) )->run( true, true );
		self::assertSame( Action::Update, $forced[0]->action );
		self::assertStringStartsWith( 'adopted from the one-time import', $forced[0]->note );
		self::assertSame( $fields['new_tab'], $this->affibloom->products[40]['fields']['new_tab'] );
		self::assertSame( '$19.99', $this->affibloom->products[40]['fields']['price'], "Affibloom's Amazon price is kept." );
		self::assertFalse( $this->map->mark( 40 )->adopted, 'Restored: it carries the values Lasso gives now.' );

		self::assertSame( Action::Unchanged, $this->step( array( $lasso ) )->run( true, true )[0]->action, 'A second run changes nothing.' );
	}

	public function test_force_leaves_a_product_the_migration_made(): void {
		$this->step( array( self::record( 1 ) ) )->run();
		$product = $this->map->for_record( 1 )[0];
		$this->affibloom->products[ $product ]['fields']['new_tab'] = false;

		self::assertSame( Action::Unchanged, $this->step( array( self::record( 1 ) ) )->run( true, true )[0]->action );
		self::assertFalse( $this->affibloom->products[ $product ]['fields']['new_tab'] );
	}

	public function test_a_value_affibloom_refuses_is_an_error_line_and_the_rest_continue(): void {
		$this->affibloom->refuse_url = 'https://bad.example/';
		$lines                       = $this->step( array( self::record( 1, 'publish', array( 'lasso_final_url' => 'https://bad.example/' ) ), self::record( 2 ) ) )->run();

		self::assertSame(
			array(
				'1' => 'error',
				'2' => 'create',
			),
			self::actions( $lines )
		);
		self::assertSame( 'url: invalid_url', $lines[0]->note );
	}

	/** The record's badge, as Lasso changes it. */
	private static function badged( int $id, string $badge ): LassoRecord {
		return self::record(
			$id,
			'publish',
			array(
				'lasso_final_url' => 'https://c.example/',
				'badge_text'      => $badge,
			)
		);
	}

	public function test_update_takes_a_lasso_change_into_an_untouched_product(): void {
		$this->step( array( self::badged( 1, 'Best' ) ) )->run();
		$product = $this->map->for_record( 1 )[0];

		$lines = $this->step( array( self::badged( 1, 'Top pick' ) ) )->run( true );

		self::assertSame( Action::Update, $lines[0]->action );
		self::assertSame( 'Top pick', $this->affibloom->products[ $product ]['fields']['badge'] );
		self::assertSame( Action::Unchanged, $this->step( array( self::badged( 1, 'Top pick' ) ) )->run( true )[0]->action, 'Marked again: nothing more to do.' );
	}

	public function test_without_update_a_lasso_change_waits(): void {
		$this->step( array( self::badged( 1, 'Best' ) ) )->run();

		self::assertSame( Action::Unchanged, $this->step( array( self::badged( 1, 'Top pick' ) ) )->run()[0]->action );
	}

	public function test_an_affibloom_edit_alone_is_left_and_an_edit_on_both_sides_is_kept_unless_force(): void {
		$this->step( array( self::badged( 1, 'Best' ) ) )->run();
		$product = $this->map->for_record( 1 )[0];
		$this->affibloom->products[ $product ]['fields']['badge'] = 'Edited';

		self::assertSame( Action::Unchanged, $this->step( array( self::badged( 1, 'Best' ) ) )->run( true )[0]->action );

		$kept = $this->step( array( self::badged( 1, 'Top pick' ) ) )->run( true );
		self::assertSame( Action::Skip, $kept[0]->action );
		self::assertStringStartsWith( 'changed on both sides', $kept[0]->note );
		self::assertSame( 'Edited', $this->affibloom->products[ $product ]['fields']['badge'] );

		$forced = $this->step( array( self::badged( 1, 'Top pick' ) ) )->run( true, true );
		self::assertSame( Action::Update, $forced[0]->action );
		self::assertSame( 'Top pick', $this->affibloom->products[ $product ]['fields']['badge'] );
	}

	public function test_update_leaves_a_product_no_longer_published_and_reports_a_record_deleted_in_lasso(): void {
		$this->step( array( self::badged( 1, 'Best' ), self::record( 2 ) ) )->run();
		$product = $this->map->for_record( 1 )[0];
		$this->affibloom->products[ $product ]['status'] = 'trash';

		$lines = $this->step( array( self::badged( 1, 'Top pick' ) ) )->run( true );

		self::assertSame( Action::Unchanged, $lines[0]->action );
		self::assertSame( 'trash', $this->affibloom->products[ $product ]['status'] );
		self::assertSame( '2', $lines[1]->key );
		self::assertSame( 'deleted in Lasso: its product is kept', $lines[1]->note );
	}

	public function test_update_still_creates_new_records(): void {
		$this->step( array( self::record( 1 ) ) )->run();

		$lines = $this->step( array( self::record( 1 ), self::record( 2 ) ) )->run( true );

		self::assertSame(
			array(
				'1' => 'unchanged',
				'2' => 'create',
			),
			self::actions( $lines )
		);
	}

	public function test_two_products_with_one_lasso_id_stop_the_family_naming_both(): void {
		$this->map->map[2] = array( 40, 41 );
		$lines             = $this->step( array( self::record( 1 ), self::record( 2 ), self::record( 3 ) ) )->run();

		self::assertCount( 2, $lines );
		self::assertSame( Action::Error, $lines[1]->action );
		self::assertStringContainsString( '#40 and #41', $lines[1]->note );
		self::assertCount( 1, $this->affibloom->products, 'Only the record before the stop was migrated.' );
	}
}
