<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\GroupsStep;
use AffibloomLassoMigrator\Application\ViewsMapper;
use AffibloomLassoMigrator\Application\ViewsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoViews;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\ViewWriter;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R22, AC20; F38 ON5, ON8 (Task 26M): each user's views by their Lasso ID, the group through the map, an unknown
 * filter dropped with a note, a second run unchanged; the tour ended and the closed boxes closed, never reopened.
 */
final class ViewsStepTest extends TestCase {

	private FakeLog $log;

	/** Affibloom's side. */
	private ViewWriter $writer;

	protected function setUp(): void {
		parent::setUp();
		$this->log = new FakeLog();
		$this->log->remember( GroupsStep::MAP, 512, 569 );
		$this->writer = new class() implements ViewWriter {
			/** @var array<int, array<string, array<string, mixed>>> */
			public array $views = array();
			public bool $tour   = false;
			/** @var list<string> */
			public array $boxes = array( 'domains' );

			public function views( int $user ): array {
				return array_values( $this->views[ $user ] ?? array() );
			}
			public function save( int $user, array $view ): void {
				$this->views[ $user ][ $view['id'] ] = $view;
			}
			public function tour_done(): bool {
				return $this->tour;
			}
			public function finish_tour(): void {
				$this->tour = true;
			}
			public function closed_boxes(): array {
				return $this->boxes;
			}
			public function close_boxes( array $boxes ): void {
				$this->boxes = $boxes;
			}
		};
	}

	private function step(): ViewsStep {
		$views = new LassoViews(
			array(
				2 => array(
					array(
						'id'      => 'v-group',
						'name'    => 'Shoes group',
						'filters' => array(
							'dashboard_group_term_id' => 512,
							'pageSize'                => 25,
						),
					),
					array(
						'id'      => 'v-brand',
						'name'    => 'By brand',
						'filters' => array(
							'dashboard_link_kind' => 'amazon',
							'dashboard_brand'     => 'mikasa',
						),
					),
				),
			),
			true,
			array( 'link', 'keyword' )
		);

		return new ViewsStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), array(), new LassoAmazon(), $views ), new ViewsMapper(), $this->writer, $this->log );
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

	public function test_views_tour_and_boxes_are_copied_and_a_second_run_changes_nothing(): void {
		$lines = self::actions( $this->step()->run() );
		$this->log->ran( Family::Views, 4, 0 );

		self::assertSame( 'create', $lines['2:v-group'] );
		self::assertSame( 'create | filters dropped: dashboard_brand', $lines['2:v-brand'] );
		self::assertSame( 569, $this->writer->views[2]['v-group']['filters']['group'] );
		self::assertSame( 'amazon', $this->writer->views[2]['v-brand']['filters']['link_type'] );
		self::assertTrue( $this->writer->tour );
		self::assertSame( array( 'domains', 'links' ), $this->writer->boxes, 'A box Affibloom closed stays closed.' );
		self::assertStringStartsWith( 'skip', $lines['box:keyword'] );

		$again = self::actions( $this->step()->run() );
		self::assertSame( 'unchanged', $again['2:v-group'] );
		self::assertStringStartsWith( 'unchanged', $again['2:v-brand'] );
		self::assertStringStartsWith( 'unchanged', $again['tour'] );
		self::assertStringStartsWith( 'unchanged', $again['boxes'] );
	}

	public function test_a_view_changed_in_affibloom_is_kept_on_a_later_run_unless_update_and_the_dry_run_writes_nothing(): void {
		$this->step()->run();
		$this->log->ran( Family::Views, 4, 0 );
		$this->writer->views[2]['v-group']['name'] = 'Renamed here';

		self::assertStringStartsWith( 'skip', self::actions( $this->step()->run() )['2:v-group'] );
		self::assertSame( 'update', self::actions( $this->step()->plan( true ) )['2:v-group'] );
		self::assertSame( 'Renamed here', $this->writer->views[2]['v-group']['name'] );
		self::assertSame( 'update', self::actions( $this->step()->run( true ) )['2:v-group'] );
		self::assertSame( 'Shoes group', $this->writer->views[2]['v-group']['name'] );
	}
}
