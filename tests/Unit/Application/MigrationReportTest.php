<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Application\Step;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Interface\Cli\MigrateCommand;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use InvalidArgumentException;

/**
 * F10 D3, D18 (Task 25A): the families in the fixed order, the report's sums, and the families not built yet.
 */
final class MigrationReportTest extends TestCase {

	private static function line( Action $action, string $status = 'publish' ): MigrationLine {
		return new MigrationLine( Family::Products, '1', 'A', 'other', $status, $action );
	}

	public function test_only_names_families_in_the_fixed_order_and_refuses_an_unknown_one(): void {
		self::assertSame( array( Family::Settings, Family::Products ), Family::parse( 'products, settings' ) );
		self::assertSame( Family::cases(), Family::parse( '' ) );

		$this->expectException( InvalidArgumentException::class );
		Family::parse( 'products,clicks' );
	}

	public function test_the_products_summary_sums_each_action_and_the_published_records(): void {
		$step = new class() implements Step {
			public function family(): Family {
				return Family::Products;
			}
			public function plan(): array {
				return array(
					new MigrationLine( Family::Products, '1', 'A', 'other', 'publish', Action::Create ),
					new MigrationLine( Family::Products, '2', 'B', 'other', 'publish', Action::Create ),
					new MigrationLine( Family::Products, '3', 'C', 'other', 'publish', Action::Skip ),
					new MigrationLine( Family::Products, '4', 'D', 'other', 'lasso_delete', Action::Retire ),
					new MigrationLine( Family::Products, '5', 'E', 'other', 'trash', Action::Trash ),
					new MigrationLine( Family::Products, '6', 'F', 'other', 'publish', Action::Unchanged ),
				);
			}
		};

		$report = ( new MigrationService( array( $step ) ) )->plan( array( Family::Settings, Family::Products ) );

		self::assertSame( 'products: 6 records: 2 to create, 1 skipped, 1 to retire, 1 to trash, 1 already migrated, 0 errors (4 published).', MigrateCommand::summary( $report, Family::Products ) );
		self::assertSame( 'settings: not built yet.', MigrateCommand::summary( $report, Family::Settings ) );
		self::assertFalse( $report->has_errors() );
	}

	public function test_an_error_line_makes_the_report_fail(): void {
		$step = new class() implements Step {
			public function family(): Family {
				return Family::Products;
			}
			public function plan(): array {
				return array( new MigrationLine( Family::Products, '1', 'A', 'other', 'publish', Action::Error, '', 'url: invalid_url' ) );
			}
		};

		self::assertTrue( ( new MigrationService( array( $step ) ) )->plan( array( Family::Products ) )->has_errors() );
	}
}
