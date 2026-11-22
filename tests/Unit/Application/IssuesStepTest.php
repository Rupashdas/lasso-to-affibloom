<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\IssuesStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoIssues;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoViews;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\IssueWriter;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAffibloom;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R16, D12, AC18 (Task 26K): Lasso's rows in Affibloom's keys (open out of stock, open 404, resolved 200); a
 * row is stored unless Affibloom's check is as new; a closed bar stays closed; a second run changes nothing.
 */
final class IssuesStepTest extends TestCase {

	/** Affibloom's rows. @var array<int, array<string, mixed>> */
	public array $rows = array();

	/** Affibloom's closed bars. @var array<string, int> */
	public array $closed = array();

	private FakeLog $log;

	private FakeIdMap $map;

	protected function setUp(): void {
		parent::setUp();
		$this->log = new FakeLog();
		$this->map = new FakeIdMap();
		foreach ( array( 10 => 100, 11 => 101, 12 => 102 ) as $lasso => $product ) {
			$this->map->link( $product, $lasso, new MigrationMark( '', '' ) );
		}
	}

	private static function row( int $id, string $type, string $resolved, string $at ): array {
		return array(
			'id'                => (string) $id,
			'issue_type'        => $type,
			'issue_slug'        => "https://shop.example/$id",
			'detection_date'    => '2026-09-05 00:06:36',
			'issue_resolved'    => $resolved,
			'issue_resolved_dt' => $at,
		);
	}

	private function step(): IssuesStep {
		$rows   = &$this->rows;
		$closed = &$this->closed;
		$writer = new class( $rows, $closed ) implements IssueWriter {
			/**
			 * @param array<int, array<string, mixed>> $rows   Rows.
			 * @param array<string, int>               $closed Bars.
			 */
			public function __construct( private array &$rows, private array &$closed ) {}
			public function issue( int $product ): ?array {
				return $this->rows[ $product ] ?? null;
			}
			public function import( int $product, array $row ): void {
				$this->rows[ $product ] = $row;
			}
			public function dismissed_until( int $product, string $bar ): int {
				return $this->closed[ "$product:$bar" ] ?? 0;
			}
			public function dismiss( int $product, string $bar, int $until ): void {
				$this->closed[ "$product:$bar" ] = $until;
			}
		};
		$issues = new LassoIssues(
			array( self::row( 10, '000', '0', '2026-09-24 00:03:16' ), self::row( 11, '404', '0', '2026-09-24 00:03:31' ), self::row( 12, '200', '1', '2026-09-20 10:00:00' ), self::row( 13, '000', '0', '' ) ),
			array(
				10 => array(
					'broken'       => 1700000000,
					'out-of-stock' => 1900000000,
				),
			)
		);

		$products = new FakeAffibloom();
		foreach ( array( 100, 101, 102 ) as $id ) {
			$products->products[ $id ] = array(
				'status'  => 'publish',
				'fields'  => array( 'name' => "Product $id" ),
				'to'      => 0,
				'created' => '',
			);
		}

		return new IssuesStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), array(), new LassoAmazon(), new LassoViews(), array(), array(), $issues ), $this->map, $writer, $products, $this->log, 1800000000 );
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

	public function test_a_lasso_row_in_affibloom_s_keys(): void {
		self::assertSame(
			array(
				'issue_type'      => '200',
				'checked_url'     => 'https://shop.example/12',
				'detection_date'  => '2026-09-05 00:06:36',
				'resolved'        => true,
				'resolved_at'     => '2026-09-20 10:00:00',
				'last_checked_at' => '2026-09-20 10:00:00',
			),
			IssuesStep::mapped( self::row( 12, '200', '1', '2026-09-20 10:00:00' ) )
		);
		self::assertSame( '', IssuesStep::mapped( self::row( 10, '000', '0', '2026-09-24 00:03:16' ) )['resolved_at'] );
	}

	public function test_rows_and_a_closed_bar_carry_over_affibloom_s_newer_check_wins_and_a_second_run_changes_nothing(): void {
		$this->rows[101] = array( 'last_checked_at' => '2026-11-20 00:00:00' );

		$lines = self::actions( $this->step()->run() );
		$this->log->ran( Family::Issues, 5, 0 );

		self::assertSame( 'create', $lines['10'] );
		self::assertSame( "unchanged | Affibloom's check is as new or newer", $lines['11'] );
		self::assertSame( 'create', $lines['12'] );
		self::assertStringStartsWith( 'skip | no product', $lines['13'] );
		self::assertSame( 'update | closed until 2030-03-17 17:46 UTC', $lines['10:out-of-stock'] );
		self::assertSame( '000', $this->rows[100]['issue_type'] );
		self::assertSame( 1900000000, $this->closed['100:out-of-stock'] );
		self::assertStringStartsWith( 'skip | expired', $lines['10:broken'] );

		$again = self::actions( $this->step()->run() );
		self::assertSame( 'unchanged', $again['10'] );
		self::assertSame( 'unchanged', $again['10:out-of-stock'] );
	}
}
