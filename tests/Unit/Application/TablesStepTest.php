<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\FieldsStep;
use AffibloomLassoMigrator\Application\TablesStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoIssues;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoViews;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;
use AffibloomLassoMigrator\Domain\Migration\TableWriter;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAffibloom;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R14, AC16 (Task 26I): a Lasso table with its products in order, badges, groups and values; custom fields
 * through the field map; an unpublished product left out and named; a second run changes nothing.
 */
final class TablesStepTest extends TestCase {

	/** Affibloom's tables. @var array<int, array<string, mixed>> */
	public array $tables = array();

	private FakeLog $log;

	private FakeIdMap $map;

	private FakeAffibloom $products;

	protected function setUp(): void {
		parent::setUp();
		$this->log      = new FakeLog();
		$this->map      = new FakeIdMap();
		$this->products = new FakeAffibloom();
		$this->log->remember( FieldsStep::MAP, 5, 136 );
		foreach ( array( 10 => 100, 11 => 101, 12 => 102 ) as $lasso => $product ) {
			$this->map->link( $product, $lasso, new MigrationMark( '', '' ) );
			$this->products->products[ $product ] = array(
				'status'  => 102 === $product ? 'trash' : 'publish',
				'fields'  => array( 'name' => "P$product" ),
				'to'      => 0,
				'created' => '',
			);
		}
	}

	private function step(): TablesStep {
		$tables = &$this->tables;
		$writer = new class( $tables ) implements TableWriter {
			/** @param array<int, array<string, mixed>> $tables Tables. */
			public function __construct( private array &$tables ) {}
			public function table( int $id ): ?array {
				return $this->tables[ $id ] ?? null;
			}
			public function put( int $id, array $data ): int {
				$id                  = 0 === $id ? 500 + count( $this->tables ) : $id;
				$this->tables[ $id ] = $data;

				return $id;
			}
		};
		$lasso  = array(
			'id'           => 2,
			'title'        => 'Volleyball Shoe',
			'style'        => 'Row',
			'theme'        => 'Cactus',
			'show_title'   => false,
			'show_headers' => true,
			'products'     => array(
				array(
					'lasso_id' => 11,
					'badge'    => 'Runner Up ',
				),
				array(
					'lasso_id' => 10,
					'badge'    => 'Best Overall',
				),
				array(
					'lasso_id' => 12,
					'badge'    => 'Gone',
				),
			),
			'groups'       => array(
				array(
					'id'     => 'group_a',
					'fields' => array( 99991, 99990 ),
				),
				array(
					'id'     => 'group_b',
					'fields' => array( 5 ),
				),
			),
			'values'       => array(
				10 => array(
					99990 => 'Shoe A',
					5     => 'Light',
				),
				12 => array( 99990 => 'Gone' ),
			),
		);

		return new TablesStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), array(), new LassoAmazon(), new LassoViews(), array(), array(), new LassoIssues(), array( $lasso ) ), $this->map, $this->products, $writer, $this->log );
	}

	/**
	 * Each line's action and note, by key.
	 *
	 * @param list<MigrationLine> $lines The lines.
	 * @return array<string, string>
	 */
	private static function actions( array $lines ): array {
		return array_combine( array_column( $lines, 'key' ), array_map( static fn ( MigrationLine $line ): string => $line->action->value . ( '' === $line->note ? '' : ' | ' . $line->note ), $lines ) );
	}

	public function test_a_table_is_created_whole_and_a_second_run_changes_nothing(): void {
		$lines = self::actions( $this->step()->run() );
		$this->log->ran( Family::Tables, 1, 0 );

		self::assertSame( 'create | left out, not published in Affibloom: Lasso records 12', $lines['2'] );
		self::assertSame(
			array(
				'name'            => 'Volleyball Shoe',
				'style'           => 'row',
				'theme'           => 'Cactus',
				'show_headers'    => true,
				'show_field_name' => false,
				'products'        => array(
					array(
						'id'    => 101,
						'badge' => 'Runner Up',
					),
					array(
						'id'    => 100,
						'badge' => 'Best Overall',
					),
				),
				'groups'          => array(
					array(
						'id'     => 'group_a',
						'fields' => array( 99991, 99990 ),
					),
					array(
						'id'     => 'group_b',
						'fields' => array( 136 ),
					),
				),
				'values'          => array(
					100 => array(
						99990 => 'Shoe A',
						136   => 'Light',
					),
				),
			),
			$this->tables[500]
		);
		self::assertSame( array( 2 => 500 ), $this->log->map( TablesStep::MAP ) );

		self::assertStringStartsWith( 'unchanged', self::actions( $this->step()->run() )['2'] );
	}

	public function test_a_table_changed_in_affibloom_is_kept_on_a_later_run_unless_update(): void {
		$this->step()->run();
		$this->log->ran( Family::Tables, 1, 0 );
		$this->tables[500]['name'] = 'Renamed';

		self::assertStringStartsWith( 'skip', self::actions( $this->step()->run() )['2'] );
		self::assertStringStartsWith( 'update', self::actions( $this->step()->run( true ) )['2'] );
		self::assertSame( 'Volleyball Shoe', $this->tables[500]['name'] );
	}
}
