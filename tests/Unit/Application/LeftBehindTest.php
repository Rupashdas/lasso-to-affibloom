<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\LeftBehind;
use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Application\WaitingStep;
use AffibloomLassoMigrator\Domain\Lasso\LassoFields;
use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Interface\Cli\MigrateCommand;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * F10 analysis A, G, H, D15, AC5 (Task 25C): every Lasso table has a family or a reason; an unknown one is an error; a
 * family still to be built says what it found and which task moves it.
 */
final class LeftBehindTest extends TestCase {

	/** Analysis table A: Lasso's 30 table names (T1-T30; T13 is gone above version 298). */
	private const ANALYSIS_TABLES = 'lasso_url_details lasso_amazon_products lasso_extend_products lasso_fields lasso_field_mapping lasso_metadata lasso_category_order lasso_url_variants lasso_table_details lasso_table_mapping lasso_table_field_group lasso_table_field_group_detail lasso_table_title_field lasso_url_issues lasso_url_issue_definitions lasso_amazon_tracking_ids lasso_amazon_shortened_url lasso_amazon_affiliate_plus lasso_link_updates lasso_link_locations lasso_link_locations_map lasso_content lasso_tracked_keywords lasso_keyword_locations lasso_auto_monetize lasso_revert lasso_post_content_history lasso_affiliate_programs lasso_link_syncing lasso_background_process_runs';

	/**
	 * A source with this inventory.
	 *
	 * @param array<string, int> $tables Tables => rows.
	 * @param array<string, int> $counts Other counts.
	 */
	private static function source( array $tables, array $counts = array() ): LassoSource {
		return new class( new LassoInventory( $tables, $counts ) ) implements LassoSource {
			public function __construct( private LassoInventory $inventory ) {}
			public function ids(): array {
				return array();
			}
			public function records( array $ids ): array {
				return array();
			}
			public function settings(): LassoSettings {
				return new LassoSettings( array() );
			}
			public function inventory(): LassoInventory {
				return $this->inventory;
			}
			public function fields(): LassoFields {
				return new LassoFields();
			}
			public function groups(): array {
				return array();
			}
			public function amazon(): \AffibloomLassoMigrator\Domain\Lasso\LassoAmazon {
				return new \AffibloomLassoMigrator\Domain\Lasso\LassoAmazon();
			}
			public function variants(): array {
				return array();
			}
			public function slugs(): array {
				return array();
			}
			public function views(): \AffibloomLassoMigrator\Domain\Lasso\LassoViews {
				return new \AffibloomLassoMigrator\Domain\Lasso\LassoViews();
			}
		};
	}

	public function test_every_table_of_the_analysis_has_a_family_or_a_reason(): void {
		$tables = explode( ' ', self::ANALYSIS_TABLES );
		self::assertCount( 30, $tables );
		self::assertSame( array(), array_values( array_diff( $tables, array_keys( LeftBehind::TABLES ) ) ) );

		$items = ( new LeftBehind( self::source( array_fill_keys( $tables, 1 ) ) ) )->items();
		foreach ( $items as $item ) {
			self::assertTrue( $item->known, $item->name );
			self::assertTrue( null !== $item->family || '' !== $item->reason, $item->name );
		}
	}

	public function test_a_table_the_migrator_does_not_know_is_an_error(): void {
		$service = new MigrationService( array(), new LeftBehind( self::source( array( 'lasso_new_thing' => 4 ) ) ) );
		$report  = $service->plan( Family::cases() );

		self::assertTrue( $report->has_errors() );
		self::assertStringContainsString( '1 tables the migrator does not know', MigrateCommand::left_summary( $report ) );
	}

	public function test_the_data_list_comes_only_with_every_family(): void {
		$service = new MigrationService( array(), new LeftBehind( self::source( array( 'lasso_content' => 444 ) ) ) );

		self::assertNotSame( array(), $service->plan( Family::cases() )->data );
		self::assertSame( array(), $service->plan( array( Family::Products ) )->data );
	}

	public function test_a_waiting_family_counts_its_tables_and_names_its_task(): void {
		$data   = new LeftBehind(
			self::source(
				array(
					'lasso_category_order' => 0,
					'lasso_content'        => 444,
				),
				array( LassoInventory::GROUPS => 1 )
			)
		);
		$report = ( new MigrationService( array( new WaitingStep( Family::Groups, $data, '26F' ) ) ) )->plan( array( Family::Groups ) );

		self::assertSame( Action::Waiting, $report->lines[0]->action );
		self::assertSame( 'groups: found lasso_category_order 0, lasso-cat terms 1; moved by Task 26F.', MigrateCommand::summary( $report, Family::Groups ) );
	}
}
