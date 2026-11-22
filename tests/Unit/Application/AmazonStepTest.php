<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\AmazonStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoAmazon;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\AmazonWriter;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLassoSource;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeLog;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 R17, AC19 (Task 26L): a cached Lasso row goes to Affibloom's store unless Affibloom's is as new or newer; the
 * data not copied each has its line; Creators credentials are never copied.
 */
final class AmazonStepTest extends TestCase {

	/** Affibloom's store. @var array<string, array<string, mixed>> */
	private array $store = array();

	private FakeLog $log;

	protected function setUp(): void {
		parent::setUp();
		$this->log = new FakeLog();
	}

	private static function row( string $asin, string $updated ): array {
		return array(
			'amazon_id'            => $asin,
			'country'              => 'us',
			'default_product_name' => "Shoe $asin",
			'latest_price'         => '$10',
			'is_prime'             => '1',
			'features'             => '["Light"]',
			'last_updated'         => $updated,
		);
	}

	private function step( bool $creators = false ): AmazonStep {
		$store  = &$this->store;
		$writer = new class( $store ) implements AmazonWriter {
			/** @param array<string, array<string, mixed>> $store */
			public function __construct( private array &$store ) {}
			public function product( string $asin, string $country ): ?array {
				return $this->store[ "$asin:$country" ] ?? null;
			}
			public function import( string $asin, string $country, array $data ): void {
				$this->store[ "$asin:$country" ] = $data;
			}
		};
		$amazon = new LassoAmazon( array( self::row( 'B0AAAAAAAA', '2025-01-01 00:00:00' ), self::row( 'B0BBBBBBBB', '2025-01-01 00:00:00' ) ), 0, 4, $creators );

		return new AmazonStep( new FakeLassoSource( array(), new LassoInventory( array() ), new LassoSettings( array() ), new LassoFields(), array(), $amazon ), $writer, $this->log );
	}

	/**
	 * Each line's action, by key.
	 *
	 * @param list<MigrationLine> $lines The lines.
	 * @return array<string, string>
	 */
	private static function actions( array $lines ): array {
		return array_combine( array_column( $lines, 'key' ), array_map( static fn ( MigrationLine $line ): string => $line->action->value, $lines ) );
	}

	public function test_a_row_is_stored_unless_affibloom_s_is_as_new_and_a_second_run_changes_nothing(): void {
		$this->store['B0BBBBBBBB:us'] = array( 'last_updated' => '2026-11-01 00:00:00' );

		$lines = self::actions( $this->step()->run() );
		$this->log->ran( Family::Amazon, 1, 0 );

		self::assertSame( 'create', $lines['B0AAAAAAAA:us'] );
		self::assertSame( 'unchanged', $lines['B0BBBBBBBB:us'] );
		self::assertSame( 'skip', $lines['tracking ids'] );
		self::assertSame( 'skip', $lines['other stores'] );
		self::assertSame( 'Shoe B0AAAAAAAA', $this->store['B0AAAAAAAA:us']['name'] );
		self::assertTrue( $this->store['B0AAAAAAAA:us']['is_prime'] );
		self::assertSame( array( 'Light' ), $this->store['B0AAAAAAAA:us']['features'] );

		self::assertSame( 'unchanged', self::actions( $this->step()->run() )['B0AAAAAAAA:us'] );
	}

	public function test_creators_credentials_in_lasso_are_an_error_line_and_never_copied(): void {
		$lines = self::actions( $this->step( true )->run() );

		self::assertSame( 'error', $lines['creators'] );
	}
}
