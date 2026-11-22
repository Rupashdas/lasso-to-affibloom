<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\VariantsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoViews;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;
use AffibloomLassoMigrator\Domain\Migration\VariantWriter;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use InvalidArgumentException;

/**
 * F10 R15, AC15 (Task 26H): each record's variants become its product's, in Lasso's order; a refused row is an error
 * line; a second run changes nothing; a list changed in Affibloom waits for --update.
 */
final class VariantsStepTest extends TestCase {

	/** Affibloom's variants by product. @var array<int, list<array{name: string, key: string, kind: string}>> */
	public array $stored = array(
		100 => array(),
		101 => array(),
	);

	private FakeLog $log;

	private FakeIdMap $map;

	protected function setUp(): void {
		parent::setUp();
		$this->log = new FakeLog();
		$this->map = new FakeIdMap();
		$this->map->link( 100, 10, new MigrationMark( '', '' ) );
		$this->map->link( 101, 11, new MigrationMark( '', '' ) );
	}

	private function step(): VariantsStep {
		$stored   = &$this->stored;
		$writer   = new class( $stored ) implements VariantWriter {
			/** @param array<int, list<array{name: string, key: string, kind: string}>> $stored */
			public function __construct( private array &$stored ) {}
			public function variants( int $product ): ?array {
				return $this->stored[ $product ] ?? null;
			}
			public function set( int $product, array $rows ): void {
				if ( in_array( 'about', array_column( $rows, 'key' ), true ) ) {
					throw new InvalidArgumentException( '0.key: taken_by_post' );
				}
				$this->stored[ $product ] = array_map( static fn ( array $row ): array => $row + array( 'kind' => 'permalink' ), $rows );
			}
		};
		$variants = array(
			10 => array(
				array(
					'name' => 'Blue',
					'key'  => 'ball-blue',
					'kind' => 'permalink',
				),
				array(
					'name' => 'Red',
					'key'  => 'ball-red',
					'kind' => 'permalink',
				),
			),
			11 => array(
				array(
					'name' => 'About',
					'key'  => 'about',
					'kind' => 'permalink',
				),
			),
			12 => array(
				array(
					'name' => 'Gone',
					'key'  => 'gone',
					'kind' => 'permalink',
				),
			),
		);

		return new VariantsStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), array(), new LassoAmazon(), new LassoViews(), array(), $variants ), $this->map, $writer, $this->log );
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

	public function test_variants_are_created_in_lasso_s_order_a_refusal_is_an_error_and_a_second_run_changes_nothing(): void {
		$lines = self::actions( $this->step()->run() );
		$this->log->ran( Family::Variants, 3, 1 );

		self::assertSame( 'create', $lines['10'] );
		self::assertSame( array( 'ball-blue', 'ball-red' ), array_column( $this->stored[100], 'key' ) );
		self::assertSame( 'error | 0.key: taken_by_post', $lines['11'] );
		self::assertStringStartsWith( 'skip | no product', $lines['12'] );

		self::assertSame( 'unchanged', self::actions( $this->step()->run() )['10'] );
	}

	public function test_a_list_changed_in_affibloom_is_kept_on_a_later_run_unless_update(): void {
		$this->step()->run();
		$this->log->ran( Family::Variants, 3, 1 );
		$this->stored[100][1]['name'] = 'Crimson';

		self::assertStringStartsWith( 'skip', self::actions( $this->step()->run() )['10'] );
		self::assertSame( 'update | replaces 2 of Affibloom\'s', self::actions( $this->step()->run( true ) )['10'] );
		self::assertSame( 'Red', $this->stored[100][1]['name'] );
	}
}
