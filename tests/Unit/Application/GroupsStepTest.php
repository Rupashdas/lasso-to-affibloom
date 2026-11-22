<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\GroupsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoGroup;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAffibloom;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeGroups;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R13, AC13 (Task 26F): a group per Lasso group with its name and description, its ID in the group map, and the
 * records' published products in Lasso's order; a second run changes nothing; a group of the same name is adopted.
 */
final class GroupsStepTest extends TestCase {

	private FakeGroups $affibloom;

	private FakeAffibloom $products;

	private FakeLog $log;

	private FakeIdMap $map;

	protected function setUp(): void {
		parent::setUp();
		$this->affibloom = new FakeGroups();
		$this->products  = new FakeAffibloom();
		$this->log       = new FakeLog();
		$this->map       = new FakeIdMap();
		foreach ( array(
			1 => 'publish',
			2 => 'publish',
			3 => 'trash',
		) as $lasso => $status ) {
			$product                                        = $this->products->import( array( 'url' => "https://e.example/$lasso" ), '', '' );
			$this->products->products[ $product ]['status'] = $status;
			$this->map->map[ $lasso ]                       = array( $product );
		}
	}

	private function step(): GroupsStep {
		$groups = array( new LassoGroup( 15, 'best volyball shoe', 'best-volyball-shoe', 'Our picks', array( 2, 1, 3 ) ) );

		return new GroupsStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), $groups ), $this->affibloom, $this->map, $this->products, $this->log );
	}

	/**
	 * Each line's action, by type.
	 *
	 * @param list<MigrationLine> $lines The lines.
	 * @return array<string, string>
	 */
	private static function actions( array $lines ): array {
		return array_combine( array_column( $lines, 'type' ), array_map( static fn ( MigrationLine $line ): string => $line->action->value, $lines ) );
	}

	public function test_the_group_its_map_and_its_published_products_in_lasso_s_order_and_a_second_run_changes_nothing(): void {
		$lines = $this->step()->run();
		$this->log->ran( Family::Groups, count( $lines ), 0 );
		$id = $this->log->map( GroupsStep::MAP )[15];

		self::assertSame(
			array(
				'group'   => 'create',
				'members' => 'create',
			),
			self::actions( $lines )
		);
		self::assertSame( 'Our picks', $this->affibloom->groups[ $id ]['description'] );
		self::assertSame( array( $this->map->map[2][0], $this->map->map[1][0] ), $this->affibloom->members[ $id ], 'Lasso order; the trashed product left out.' );
		self::assertStringContainsString( '1 of its records have no published product', $lines[1]->note );

		self::assertSame(
			array(
				'group'   => 'unchanged',
				'members' => 'unchanged',
			),
			self::actions( $this->step()->run() )
		);
		self::assertCount( 1, $this->affibloom->groups );
	}

	public function test_a_group_of_the_same_name_is_adopted_and_a_later_run_keeps_affibloom_s_members_unless_update(): void {
		$existing = $this->affibloom->create( 'best volyball shoe', 'Our picks' );
		$this->log->ran( Family::Groups, 1, 0 );

		$kept = $this->step()->run();
		self::assertSame(
			array(
				'group'   => 'unchanged',
				'members' => 'skip',
			),
			self::actions( $kept )
		);
		self::assertSame( array(), $this->affibloom->members( $existing ) );

		self::assertSame( 'create', self::actions( $this->step()->run( true ) )['members'] );
		self::assertCount( 2, $this->affibloom->members( $existing ) );
	}

	public function test_the_dry_run_writes_nothing(): void {
		$this->step()->plan();

		self::assertSame( array(), $this->affibloom->groups );
		self::assertSame( array(), $this->log->map( GroupsStep::MAP ) );
	}
}
