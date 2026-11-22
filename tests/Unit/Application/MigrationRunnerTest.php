<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\MigrationRunner;
use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Application\RunLocked;
use AffibloomLassoMigrator\Application\RunStep;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\RunState;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 D13, AC22 (Task 26O): one family per request in the fixed order; the families still to run kept between
 * requests; a lock held by another request refuses; an abandoned lock is taken over.
 */
final class MigrationRunnerTest extends TestCase {

	/** The families each run saw, with the update flag. @var list<string> */
	public array $ran = array();

	private RunState $state;

	protected function setUp(): void {
		parent::setUp();
		$this->state = new class() implements RunState {
			public int $locked = 0;
			/** @var array{families: list<string>, update: bool}|null */
			public ?array $run = null;
			public function lock( int $now, int $ttl ): bool {
				if ( 0 !== $this->locked && $now - $this->locked < $ttl ) {
					return false;
				}
				$this->locked = $now;

				return true;
			}
			public function unlock(): void {
				$this->locked = 0;
			}
			public function current(): ?array {
				return $this->run;
			}
			public function save( array $families, bool $update ): void {
				$this->run = array() === $families ? null : array(
					'families' => $families,
					'update'   => $update,
				);
			}
		};
	}

	private function runner(): MigrationRunner {
		$steps = array();
		foreach ( Family::cases() as $family ) {
			$steps[] = new class( $family, $this->ran ) implements RunStep {
				/** @param list<string> $ran Runs seen. */
				public function __construct( private Family $family, private array &$ran ) {}
				public function family(): Family {
					return $this->family;
				}
				public function plan( bool $update = false, bool $force = false ): array {
					return array();
				}
				public function run( bool $update = false, bool $force = false ): array {
					$this->ran[] = $this->family->value . ( $update ? ' update' : '' );

					return array( new MigrationLine( $this->family, '1', '', '', '', Action::Create, '', '' ) );
				}
			};
		}

		return new MigrationRunner( new MigrationService( $steps ), $this->state );
	}

	public function test_each_request_runs_the_next_family_until_all_have_run(): void {
		$first = $this->runner()->batch( false, 1000 );

		self::assertSame( 'settings', $first['family'] );
		self::assertSame( 1, $first['counts']['create'] );
		self::assertFalse( $first['done'] );
		self::assertSame( count( Family::cases() ), $first['total'] );

		// The next press takes an update flag, but the run going on keeps its own.
		$second = $this->runner()->batch( true, 1001 );
		self::assertSame( 'display', $second['family'] );
		self::assertSame( array( 'settings', 'display' ), $this->ran );

		do {
			$result = $this->runner()->batch( false, 1002 );
		} while ( ! $result['done'] );
		self::assertSame( array_map( static fn ( Family $family ): string => $family->value, Family::cases() ), $this->ran );
		self::assertNull( $this->state->current() );

		$this->runner()->batch( true, 1003 );
		self::assertSame( 'settings update', end( $this->ran ) );
	}

	public function test_a_lock_held_by_another_request_refuses_and_an_abandoned_one_is_taken_over(): void {
		$this->state->lock( 1000, MigrationRunner::LOCK_TTL );

		try {
			$this->runner()->batch( false, 1100 );
			self::fail( 'The second request ran.' );
		} catch ( RunLocked ) {
			self::assertSame( array(), $this->ran );
		}

		self::assertSame( 'settings', $this->runner()->batch( false, 1000 + MigrationRunner::LOCK_TTL )['family'] );
	}
}
