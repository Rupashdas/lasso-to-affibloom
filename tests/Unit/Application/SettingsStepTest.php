<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\SettingsMapper;
use AffibloomLassoMigrator\Application\SettingsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeSettings;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * F10 D9, AC10 (Task 26C): the first run writes Lasso's values tab by tab, a switch Lasso has off included; a later run
 * keeps Affibloom's differing values unless --update; a refused value is an error and the rest are saved.
 */
final class SettingsStepTest extends TestCase {

	private FakeSettings $affibloom;

	private FakeLog $log;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		$this->affibloom = new FakeSettings(
			array(
				'sponsored'   => true,
				'show_price'  => true,
				'tracking_id' => 'vbvault-20',
			)
		);
		$this->log       = new FakeLog();
	}

	/**
	 * The step over Lasso's settings.
	 *
	 * @param array<string, mixed> $lasso     The stored option.
	 * @param list<string>         $blacklist The blacklist.
	 */
	private function step( array $lasso, array $blacklist = array() ): SettingsStep {
		return new SettingsStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( $lasso, $blacklist ) ), new SettingsMapper(), $this->affibloom, $this->log );
	}

	/**
	 * Lines by Lasso key.
	 *
	 * @param list<MigrationLine> $lines The lines.
	 * @return array<string, MigrationLine>
	 */
	private static function keyed( array $lines ): array {
		return array_column( $lines, null, 'key' );
	}

	public function test_the_first_run_writes_lasso_s_values_tab_by_tab_and_an_off_switch_as_off(): void {
		$lines = self::keyed(
			$this->step(
				array(
					'enable_sponsored'   => false,
					'show_price'         => '0',
					'amazon_tracking_id' => 'vbvault-20',
					'enable_history'     => true,
				),
				array( 'vbvault-20' )
			)->run()
		);

		self::assertSame( Action::Create, $lines['enable_sponsored']->action );
		self::assertSame( Action::Unchanged, $lines['amazon_tracking_id']->action );
		self::assertSame( Action::Skip, $lines['enable_history']->action );
		self::assertFalse( $this->affibloom->values['sponsored'] );
		self::assertFalse( $this->affibloom->values['show_price'] );
		self::assertSame( array( 'vbvault-20' ), $this->affibloom->values['tracking_id_blacklist'] );
		self::assertSame( array( 'general', 'display', 'import' ), array_column( $this->affibloom->saves, 0 ), 'Nothing differed on the Amazon tab, so it was not saved.' );
		self::assertSame( false, $this->affibloom->saves[0][1]['sponsored'] );
	}

	public function test_a_later_run_keeps_affibloom_s_differing_values_unless_update(): void {
		$this->log->ran( Family::Settings, 1, 0 );
		$lasso = array( 'enable_sponsored' => false );

		$kept = self::keyed( $this->step( $lasso )->run() );
		self::assertSame( Action::Skip, $kept['enable_sponsored']->action );
		self::assertStringStartsWith( 'Affibloom has true: kept', $kept['enable_sponsored']->note );
		self::assertTrue( $this->affibloom->values['sponsored'] );

		$updated = self::keyed( $this->step( $lasso )->run( true ) );
		self::assertSame( Action::Create, $updated['enable_sponsored']->action );
		self::assertFalse( $this->affibloom->values['sponsored'] );
	}

	public function test_a_refused_value_is_an_error_and_the_tab_s_other_values_are_saved(): void {
		$this->affibloom->refuse = 'scan_post_types';
		$lines                   = self::keyed(
			$this->step(
				array(
					'cpt_support'      => array( 'post', 'page', 'product' ),
					'enable_sponsored' => false,
				)
			)->run()
		);

		self::assertSame( Action::Error, $lines['cpt_support']->action );
		self::assertSame( Action::Create, $lines['enable_sponsored']->action );
		self::assertFalse( $this->affibloom->values['sponsored'] );
	}

	public function test_the_dry_run_says_the_same_and_writes_nothing(): void {
		$lines = self::keyed( $this->step( array( 'enable_sponsored' => false ) )->plan() );

		self::assertSame( Action::Create, $lines['enable_sponsored']->action );
		self::assertSame( array(), $this->affibloom->saves );
	}
}
