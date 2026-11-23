<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\Conversion\LeftoverCheck;
use AffibloomLassoMigrator\Application\Finish;
use AffibloomLassoMigrator\Application\NotClean;
use AffibloomLassoMigrator\Application\RunLocked;
use AffibloomLassoMigrator\Domain\Conversion\AffibloomRecords;
use AffibloomLassoMigrator\Domain\Conversion\ContentPlaces;
use AffibloomLassoMigrator\Domain\Migration\MigratorData;
use AffibloomLassoMigrator\Domain\Migration\RunState;
use AffibloomLassoMigrator\Tests\Unit\Fakes\FakeConversionLock;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM10: Finish is refused while LM9's check finds anything or another run holds a lock; otherwise it erases the
 * migrator's data, marks the site finished and gives the locks back. Once finished, it does nothing again.
 */
final class FinishTest extends TestCase {

	private FakeConversionLock $convert;

	/** @var RunState&object{held: bool} */
	private RunState $run;

	/** @var MigratorData&object{erased: int, finished_at: int} */
	private MigratorData $data;

	protected function setUp(): void {
		parent::setUp();
		$this->convert = new FakeConversionLock();
		$this->run     = new class() implements RunState {
			public bool $held = false;
			public function lock( int $now, int $ttl ): bool {
				if ( $this->held ) {
					return false;
				}
				$this->held = true;

				return true;
			}
			public function unlock(): void {
				$this->held = false;
			}
			public function current(): ?array {
				return null;
			}
			public function save( array $families, bool $update ): void {}
		};
		$this->data    = new class() implements MigratorData {
			public int $erased      = 0;
			public int $finished_at = 0;
			public function finished(): int {
				return $this->finished_at;
			}
			public function erase(): array {
				++$this->erased;

				return array(
					'_affibloom_lasso_id'    => 256,
					'affibloom_lasso_import' => 1,
				);
			}
			public function finish( int $now ): void {
				$this->finished_at = $now;
			}
		};
	}

	/**
	 * Finish over a site whose content holds these texts.
	 *
	 * @param list<string> $texts The places' texts.
	 */
	private function finish( array $texts ): Finish {
		$places  = new class( $texts ) implements ContentPlaces {
			public function __construct( private array $texts ) {}
			public function places(): iterable {
				foreach ( $this->texts as $index => $text ) {
					yield array(
						'title' => "Post {$index}",
						'edit'  => "https://site.example/edit/{$index}",
						'text'  => $text,
						'css'   => false,
					);
				}
			}
		};
		$records = new class() implements AffibloomRecords {
			public function exists( string $kind, string $ref ): bool {
				return 'product 7' === "$kind $ref";
			}
		};

		return new Finish( new LeftoverCheck( $places, $records ), $this->data, $this->run, $this->convert );
	}

	public function test_a_clean_site_is_finished_its_data_erased_and_the_locks_given_back(): void {
		$removed = $this->finish( array( '<!-- wp:affibloom/product-box {"type":"single","productId":7} /-->' ) )->finish( 1_800_000_000 );

		self::assertSame( array( '_affibloom_lasso_id' => 256, 'affibloom_lasso_import' => 1 ), $removed );
		self::assertSame( 1, $this->data->erased );
		self::assertSame( 1_800_000_000, $this->data->finished_at );
		self::assertFalse( $this->run->held, "The migration's lock is given back." );
		self::assertFalse( $this->convert->held, "The conversion's lock is given back." );
	}

	public function test_anything_left_of_lasso_refuses_and_erases_nothing(): void {
		try {
			$this->finish( array( '<p>[lasso id="7"]</p>', '<!-- wp:affibloom/product-box {"type":"single","productId":99} /-->' ) )->finish( 1_800_000_000 );
			self::fail( 'Finish ran over content that still needs Lasso.' );
		} catch ( NotClean $refused ) {
			self::assertSame( 2, $refused->findings, "LM9's findings: Lasso's shortcode and the box naming nothing." );
		}
		self::assertSame( 0, $this->data->erased );
		self::assertSame( 0, $this->data->finished_at );
		self::assertFalse( $this->run->held );
		self::assertFalse( $this->convert->held );
	}

	public function test_another_run_or_command_refuses(): void {
		$this->convert->held = true;
		try {
			$this->finish( array() )->finish( 1_800_000_000 );
			self::fail( 'Finish ran beside a conversion.' );
		} catch ( RunLocked ) {
			self::assertSame( 0, $this->data->erased );
			self::assertFalse( $this->run->held, "The migration's lock taken first is given back." );
		}

		$this->convert->held = false;
		$this->run->held     = true;
		$this->expectException( RunLocked::class );
		$this->finish( array() )->finish( 1_800_000_000 );
	}

	public function test_a_finished_site_is_not_finished_again(): void {
		$this->data->finished_at = 1_700_000_000;

		self::assertSame( array(), $this->finish( array( '<p>[lasso id="7"]</p>' ) )->finish( 1_800_000_000 ) );
		self::assertSame( 0, $this->data->erased );
		self::assertSame( 1_700_000_000, $this->data->finished_at, 'The first Finish keeps its time.' );
	}
}
