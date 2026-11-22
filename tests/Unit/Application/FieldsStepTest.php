<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\FieldsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeFields;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R12, AC12 (Task 26E): a custom field is created once (its ID in the field map), a renamed built-in keeps its
 * name, and each product's rows take Lasso's values, order, "visible" and "show field name".
 */
final class FieldsStepTest extends TestCase {

	private FakeFields $affibloom;

	private FakeLog $log;

	private FakeIdMap $map;

	protected function setUp(): void {
		parent::setUp();
		$this->affibloom           = new FakeFields();
		$this->affibloom->rows[40] = array();
		$this->log                 = new FakeLog();
		$this->map                 = new FakeIdMap();
		$this->map->map[9701]      = array( 40 );
	}

	private static function row( int $field, string $value, bool $visible = true, bool $show_name = false ): array {
		return array(
			'field'     => $field,
			'value'     => $value,
			'visible'   => $visible,
			'show_name' => $show_name,
		);
	}

	private static function definition( int $id, string $name, string $type ): array {
		return array(
			'id'          => $id,
			'name'        => $name,
			'type'        => $type,
			'description' => '',
		);
	}

	private function step(): FieldsStep {
		$fields = new LassoFields(
			array( self::definition( 1, 'Our Rating', 'rating' ), self::definition( 2, 'Pros', 'textarea' ), self::definition( 99995, 'DETAILS', 'text' ) ),
			array(
				9701 => array( self::row( 99995, 'Light' ), self::row( 1, '4.5', false, true ) ),
				9746 => array( self::row( 1, '3.5' ) ),
			)
		);

		return new FieldsStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), $fields ), $this->affibloom, $this->map, $this->log );
	}

	/**
	 * Each line's action, by type and key.
	 *
	 * @param list<MigrationLine> $lines The lines.
	 * @return array<string, string>
	 */
	private static function actions( array $lines ): array {
		$actions = array();
		foreach ( $lines as $line ) {
			$actions[ $line->type . ':' . $line->key ] = $line->action->value;
		}

		return $actions;
	}

	public function test_definitions_then_values_and_a_second_run_changes_nothing(): void {
		$lines = $this->step()->run();
		$this->log->ran( Family::Fields, count( $lines ), 0 );
		$details = $this->log->map( FieldsStep::MAP )[99995];

		self::assertSame(
			array(
				'definition:1'     => 'update',
				'definition:2'     => 'unchanged',
				'definition:99995' => 'create',
				'values:9701'      => 'create',
				'values:9746'      => 'skip',
			),
			self::actions( $lines )
		);
		self::assertSame( 'Our Rating', $this->affibloom->definitions[1]['name'] );
		self::assertSame( array( 1, $details ), array_column( $this->affibloom->rows[40], 'field' ) );
		self::assertFalse( $this->affibloom->rows[40][0]['visible'] );
		self::assertTrue( $this->affibloom->rows[40][0]['show_name'] );

		$again = self::actions( $this->step()->run() );
		self::assertSame( 'unchanged', $again['definition:99995'] );
		self::assertSame( 'unchanged', $again['values:9701'] );
		self::assertCount( 4, $this->affibloom->definitions, 'DETAILS was not created twice.' );
	}

	public function test_a_later_run_keeps_affibloom_s_differing_rows_unless_update(): void {
		$this->step()->run();
		$this->log->ran( Family::Fields, 1, 0 );
		$this->affibloom->rows[40][0]['value'] = '2';

		self::assertSame( 'skip', self::actions( $this->step()->run() )['values:9701'] );
		self::assertSame( '2', $this->affibloom->rows[40][0]['value'] );

		self::assertSame( 'create', self::actions( $this->step()->run( true ) )['values:9701'] );
		self::assertSame( '4.5', $this->affibloom->rows[40][0]['value'] );
	}

	public function test_the_dry_run_writes_nothing(): void {
		$this->step()->plan();

		self::assertCount( 3, $this->affibloom->definitions );
		self::assertSame( array(), $this->affibloom->rows[40] );
		self::assertSame( array(), $this->log->map( FieldsStep::MAP ) );
	}
}
