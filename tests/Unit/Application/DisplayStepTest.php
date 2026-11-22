<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\DisplayMapper;
use AffibloomLassoMigrator\Application\DisplayStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeDisplay;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * F10 S22-S28, AC11 (Task 26D): each type's theme, colours, elements, density and layout and the grid's columns are
 * written one type at a time; a second run finds them unchanged; a refused colour is an error and the rest is saved.
 */
final class DisplayStepTest extends TestCase {

	private FakeDisplay $affibloom;

	private FakeLog $log;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$this->affibloom = new FakeDisplay( array( 'columns' => 3 ) );
		$this->log       = new FakeLog();
	}

	private function step(): DisplayStep {
		$lasso = new LassoSettings(
			array(
				'theme_name'     => array( 'single' => 'Lab', 'grid' => 'Cutter', 'list' => 'Cutter' ),
				'display_colors' => array( 'single' => array( 'main' => '#111111' ) ),
				'display_layout' => array( 'columns' => 2 ),
			)
		);

		return new DisplayStep( new FakeLassoSource( array(), new LassoInventory( array() ), $lasso ), new DisplayMapper(), $this->affibloom, $this->log );
	}

	/**
	 * Lines by key.
	 *
	 * @param list<\AffibloomLassoMigrator\Domain\Migration\MigrationLine> $lines The lines.
	 * @return array<string, \AffibloomLassoMigrator\Domain\Migration\MigrationLine>
	 */
	private static function keyed( array $lines ): array {
		return array_column( $lines, null, 'key' );
	}

	public function test_the_first_run_writes_every_type_and_a_second_finds_it_unchanged(): void {
		$lines = self::keyed( $this->step()->run() );
		$this->log->ran( Family::Display, count( $lines ), 0 );

		self::assertSame( Action::Create, $lines['theme_name.single']->action );
		self::assertSame( array( 'single', 'grid', 'list' ), array_column( $this->affibloom->saves, 0 ) );
		self::assertSame( 'Lab', $this->affibloom->values['single']['theme'] );
		self::assertSame( 'Cutter', $this->affibloom->values['grid']['theme'] );
		self::assertSame( '#111111', $this->affibloom->values['single']['palette']['main'] );
		self::assertSame( 2, $this->affibloom->values['columns'] );
		self::assertArrayNotHasKey( 'disclosure', $this->affibloom->values['grid']['elements'] );

		$saves = count( $this->affibloom->saves );
		$again = $this->step()->run();
		self::assertSame( array(), array_filter( $again, static fn ( $line ): bool => Action::Create === $line->action ), 'Nothing left to import.' );
		self::assertCount( $saves, $this->affibloom->saves, 'Nothing saved again.' );
	}

	public function test_a_refused_colour_is_an_error_and_the_type_s_other_parts_are_saved(): void {
		$this->affibloom->refuse = 'main';
		$lines                   = self::keyed( $this->step()->run() );

		self::assertSame( Action::Error, $lines['display_colors.single']->action );
		self::assertSame( 'Lab', $this->affibloom->values['single']['theme'] );
	}
}
