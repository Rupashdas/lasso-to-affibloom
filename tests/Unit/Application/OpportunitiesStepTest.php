<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\OpportunitiesStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoIssues;
use AffibloomLassoMigrator\Domain\Lasso\LassoOpportunities;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoViews;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;
use AffibloomLassoMigrator\Domain\Migration\OpportunityWriter;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeAffibloom;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R18, AC21 (Task 26N): a switch turned off carries over, a dismissed link is dismissed, a link not indexed yet
 * waits, the replacements are reported, and a second run changes nothing.
 */
final class OpportunitiesStepTest extends TestCase {

	/** Dismissed links: "post url" => dismissed. @var array<string, bool> */
	public array $index = array(
		'7 https://a.example/x' => false,
	);

	private FakeLog $log;

	private FakeIdMap $map;

	private FakeAffibloom $products;

	protected function setUp(): void {
		parent::setUp();
		$this->log      = new FakeLog();
		$this->map      = new FakeIdMap();
		$this->products = new FakeAffibloom();
		foreach ( array( 10 => 100, 11 => 101 ) as $lasso => $product ) {
			$this->map->link( $product, $lasso, new MigrationMark( '', '' ) );
			$this->products->products[ $product ] = array(
				'status'  => 'publish',
				'fields'  => array( 'detect_opportunities' => true ),
				'to'      => 0,
				'created' => '',
			);
		}
	}

	private function step(): OpportunitiesStep {
		$index  = &$this->index;
		$writer = new class( $index ) implements OpportunityWriter {
			/** @param array<string, bool> $index Links. */
			public function __construct( private array &$index ) {}
			public function dismissed( int $post, string $url ): ?bool {
				return $this->index[ "$post $url" ] ?? null;
			}
			public function dismiss( int $post, string $url ): int {
				$this->index[ "$post $url" ] = true;

				return 1;
			}
		};
		$lasso  = new LassoOpportunities(
			array(
				10 => false,
				11 => true,
			),
			array(
				array(
					'post' => 7,
					'url'  => 'https://a.example/x',
				),
				array(
					'post' => 8,
					'url'  => 'https://b.example/y',
				),
			),
			array(
				array(
					'asin'    => 'B0CWHXVT5Z',
					'country' => 'us',
					'source'  => 14313,
					'target'  => 0,
				),
			)
		);

		return new OpportunitiesStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), array(), new LassoAmazon(), new LassoViews(), array(), array(), new LassoIssues(), array(), $lasso ), $this->map, $this->products, $writer, $this->log );
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

	public function test_switches_and_dismissals_carry_over_and_a_second_run_changes_nothing(): void {
		$lines = self::actions( $this->step()->run() );
		$this->log->ran( Family::Opportunities, 6, 0 );

		self::assertSame( 'update', $lines['10'] );
		self::assertFalse( $this->products->products[100]['fields']['is_opportunity'] );
		self::assertSame( 'unchanged', $lines['11'] );
		self::assertSame( 'update', $lines['post 7'] );
		self::assertTrue( $this->index['7 https://a.example/x'] );
		self::assertStringStartsWith( 'skip | not in Affibloom', $lines['post 8'] );
		self::assertSame( 'skip | no replacement product: Lasso resolves nothing either', $lines['B0CWHXVT5Z:us'] );

		$this->products->products[100]['fields']['detect_opportunities'] = false;
		$again = self::actions( $this->step()->run() );
		self::assertSame( 'unchanged', $again['10'] );
		self::assertSame( 'unchanged', $again['post 7'] );
	}
}
