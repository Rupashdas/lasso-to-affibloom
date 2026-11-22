<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\SlugsStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoAddress;
use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoViews;
use AffibloomLassoMigrator\Domain\Migration\AddressWriter;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationMark;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeIdMap;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use InvalidArgumentException;

/**
 * F10 R4, R8, AC14 (Task 26G): Lasso's slug adopted with Affibloom's kept as an old slug, Lasso's old slugs added, the
 * cloaking switch copied; a collision is an error line; a second run changes nothing; a later change waits for --update.
 */
final class SlugsStepTest extends TestCase {

	/** Affibloom's addresses by product. @var array<int, array{slug: string, old_slugs: list<string>, cloak: bool}> */
	public array $addresses = array();

	private FakeLog $log;

	private FakeIdMap $map;

	protected function setUp(): void {
		parent::setUp();
		$this->log       = new FakeLog();
		$this->map       = new FakeIdMap();
		$this->addresses = array(
			100 => array(
				'slug'      => 'asics-gel-rocket-12',
				'old_slugs' => array(),
				'cloak'     => true,
			),
			101 => array(
				'slug'      => 'amzn-net',
				'old_slugs' => array(),
				'cloak'     => true,
			),
			102 => array(
				'slug'      => 'ball',
				'old_slugs' => array(),
				'cloak'     => true,
			),
		);
		foreach ( array( 10 => 100, 11 => 101, 12 => 102 ) as $lasso => $product ) {
			$this->map->link( $product, $lasso, new MigrationMark( '', '' ) );
		}
	}

	private function step( array $more = array() ): SlugsStep {
		$addresses = &$this->addresses;
		$writer    = new class( $addresses ) implements AddressWriter {
			/** @param array<int, array{slug: string, old_slugs: list<string>, cloak: bool}> $addresses */
			public function __construct( private array &$addresses ) {}
			public function address( int $product ): ?array {
				return $this->addresses[ $product ] ?? null;
			}
			public function holder( string $slug ): int {
				foreach ( $this->addresses as $id => $address ) {
					if ( $address['slug'] === $slug || in_array( $slug, $address['old_slugs'], true ) ) {
						return $id;
					}
				}

				return 0;
			}
			public function set( int $product, string $slug, array $old_slugs, bool $cloak ): void {
				foreach ( $this->addresses as $id => $address ) {
					if ( $id !== $product && ( $address['slug'] === $slug || in_array( $slug, $address['old_slugs'], true ) ) ) {
						throw new InvalidArgumentException( "slug: taken ($slug, product $id)" );
					}
				}
				$this->addresses[ $product ] = array(
					'slug'      => $slug,
					'old_slugs' => $old_slugs,
					'cloak'     => $cloak,
				);
			}
		};
		$slugs     = array(
			new LassoAddress( 10, 'amzn-asics-gel-rocket-12-khaki', 'publish', false, array( 'gel-rocket', 'amzn-asics-gel-rocket-12-khaki' ) ),
			new LassoAddress( 11, 'amzn-net', 'publish', true ),
			new LassoAddress( 12, 'amzn-net', 'publish', true ),
			new LassoAddress( 13, 'gone__trashed', 'trash', true ),
			...$more,
		);

		return new SlugsStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), array(), new LassoAmazon(), new LassoViews(), $slugs ), $this->map, $writer, $this->log );
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

	public function test_lasso_s_address_is_adopted_a_collision_is_reported_and_a_second_run_changes_nothing(): void {
		$lines = self::actions( $this->step()->run() );
		$this->log->ran( Family::Slugs, 5, 1 );

		self::assertSame( 'update | slug adopted (asics-gel-rocket-12 kept as an old slug); cloaking off; 1 old slug added', $lines['10'] );
		self::assertSame(
			array(
				'slug'      => 'amzn-asics-gel-rocket-12-khaki',
				'old_slugs' => array( 'asics-gel-rocket-12', 'gel-rocket' ),
				'cloak'     => false,
			),
			$this->addresses[100]
		);
		self::assertSame( 'unchanged', $lines['11'] );
		self::assertSame( 'error | collision: product 101 answers at amzn-net', $lines['12'] );
		self::assertSame( 'ball', $this->addresses[102]['slug'] );
		self::assertStringStartsWith( 'skip', $lines['13'] );

		self::assertSame( 'unchanged', self::actions( $this->step()->run() )['10'] );
	}

	public function test_a_slug_another_record_s_product_holds_is_taken_once_that_product_takes_its_own(): void {
		$this->addresses[101]['slug'] = 'amzn-asics-gel-rocket-12-khaki';

		$lines = self::actions( $this->step()->run() );

		self::assertSame( 'update | slug adopted (asics-gel-rocket-12 kept as an old slug); cloaking off; 1 old slug added', $lines['10'] );
		self::assertSame( 'update | slug adopted', $lines['11'] );
		self::assertSame( 'amzn-net', $this->addresses[101]['slug'] );
		self::assertSame( array(), $this->addresses[101]['old_slugs'] );
	}

	public function test_the_dry_run_counts_a_slug_held_until_the_release_and_an_old_slug_another_product_has(): void {
		$this->addresses[101]['slug']      = 'amzn-asics-gel-rocket-12-khaki';
		$this->addresses[102]['old_slugs'] = array( 'gel-rocket' );

		$lines = self::actions( $this->step()->plan() );

		self::assertSame( 'update | slug adopted (asics-gel-rocket-12 kept as an old slug); cloaking off; old slug gel-rocket stays with product 102', $lines['10'] );
		self::assertSame( "update | slug adopted (amzn-asics-gel-rocket-12-khaki left to Lasso's record 10)", $lines['11'] );
		self::assertSame( 'amzn-asics-gel-rocket-12-khaki', $this->addresses[101]['slug'] );
	}

	public function test_a_slug_a_retired_record_owns_stays_with_the_product_that_holds_it(): void {
		$this->map->link( 103, 15, new MigrationMark( '', '' ) );

		$lines = self::actions( $this->step( array( new LassoAddress( 15, 'ball', 'lasso_delete', true ), new LassoAddress( 16, 'ball-2', 'publish', true ) ) )->run() );

		self::assertSame( 'ball', $this->addresses[102]['slug'], 'Product 102 keeps the slug record 15 cannot take.' );
		self::assertStringStartsWith( 'skip', $lines['15'] );
	}

	public function test_an_address_changed_in_affibloom_is_kept_on_a_later_run_unless_update_and_the_dry_run_writes_nothing(): void {
		$this->step()->run();
		$this->log->ran( Family::Slugs, 5, 1 );
		$this->addresses[100]['cloak'] = true;

		self::assertStringStartsWith( 'skip', self::actions( $this->step()->run() )['10'] );
		self::assertSame( 'update | cloaking off', self::actions( $this->step()->plan( true ) )['10'] );
		self::assertTrue( $this->addresses[100]['cloak'] );
		$this->step()->run( true );
		self::assertFalse( $this->addresses[100]['cloak'] );
	}
}
