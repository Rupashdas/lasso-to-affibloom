<?php
namespace AffibloomLassoMigrator\Tests\Unit;

use Affibloom\Application\Shell\NoticeBoard;
use Affibloom\Application\Products\LinkNormaliser;
use Affibloom\Application\Products\LinkResolver;
use Affibloom\Application\Products\ProductInput;
use Affibloom\Application\Products\ProductService;
use Affibloom\Application\Display\DisplaySettingsService;
use Affibloom\Extension\AmazonAccess;
use Affibloom\Extension\Api;
use Affibloom\Application\Fields\FieldsService;
use Affibloom\Extension\DisplayAccess;
use Affibloom\Application\Groups\GroupService;
use Affibloom\Extension\FieldAccess;
use Affibloom\Extension\GroupAccess;
use Affibloom\Application\Settings\SettingsService;
use Affibloom\Extension\ProductAccess;
use Affibloom\Extension\SettingsAccess;
use Affibloom\Interface\Admin\AdminNavigation;
use Affibloom\Interface\Admin\ExtensionScreen;
use Affibloom\Interface\Admin\NavItem;
use Affibloom\Interface\Template;
use Affibloom\Tests\Unit\Extension\ApiTest;
use Affibloom\Tests\Unit\Fakes\FakePageFetcher;
use Affibloom\Tests\Unit\Fakes\InMemoryAmazonProducts;
use Affibloom\Tests\Unit\Fakes\InMemoryDisplaySettingsRepository;
use Affibloom\Tests\Unit\Fakes\InMemoryFieldRepository;
use Affibloom\Tests\Unit\Fakes\InMemoryFieldUsage;
use Affibloom\Tests\Unit\Fakes\InMemoryGroupRepository;
use Affibloom\Tests\Unit\Fakes\InMemoryProductRepository;
use Affibloom\Tests\Unit\Fakes\InMemorySettingsRepository;
use AffibloomLassoMigrator\Interface\Admin\MigrationScreen;
use AffibloomLassoMigrator\Plugin;
use Brain\Monkey\Functions;

/**
 * Task LM1: the migrator starts only on an Affibloom with the API it was built for; otherwise it says so and does
 * nothing. Started, it adds Migrate from Lasso to Affibloom's menu and keeps the setup wizard shut where Lasso's data is.
 */
final class PluginTest extends TestCase {

	private AdminNavigation $navigation;

	private ExtensionScreen $pages;

	protected function setUp(): void {
		parent::setUp();
		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'site_url' )->justReturn( 'https://site.example' );
		Functions\when( 'plugins_url' )->justReturn( 'https://site.example/wp-content/plugins/affibloom-lasso-migrator/assets/admin/migrate.css' );
		$this->navigation = new AdminNavigation();
	}

	/** The API of an Affibloom at the version. */
	private function api( string $version ): Api {
		$template = new Template( __DIR__, $this->navigation, new NoticeBoard( array(), static function (): void {} ) );

		$this->pages = new ExtensionScreen( $template );

		$resolver = new LinkResolver( new FakePageFetcher() );
		$settings = new InMemorySettingsRepository();
		$links    = new LinkNormaliser( $resolver, $settings );
		$products = new InMemoryProductRepository();
		$service  = new ProductService( $products, $resolver, $settings, new ProductInput( $links ), $links, InMemoryAmazonProducts::without_amazon() );

		return new Api( $version, $this->navigation, $this->pages, new ProductAccess( $service, $products, static function (): void {} ), new SettingsAccess( new SettingsService( $settings ) ), new DisplayAccess( new DisplaySettingsService( new InMemoryDisplaySettingsRepository(), $settings ) ), new FieldAccess( new FieldsService( new InMemoryFieldRepository(), $products, new InMemoryFieldUsage( $products ) ) ), new GroupAccess( new GroupService( new InMemoryGroupRepository(), $products ) ), new AmazonAccess( new InMemoryAmazonProducts() ), ApiTest::views(), ApiTest::variants(), ApiTest::issues(), ApiTest::tables(), ApiTest::opportunities(), ApiTest::blocks(), ApiTest::ai_texts() );
	}

	/** The menu's slugs. */
	private function menu(): array {
		return array_map( static fn ( NavItem $item ): string => $item->slug, $this->navigation->menu() );
	}

	public function test_it_waits_for_affibloom_s_api(): void {
		$plugin = new Plugin();
		$plugin->register();

		self::assertNotFalse( has_action( 'affibloom_ready', array( $plugin, 'start' ) ) );
		self::assertNotFalse( has_action( 'admin_notices', array( $plugin, 'too_old_notice' ) ) );
	}

	public function test_an_older_affibloom_gets_a_notice_and_nothing_else(): void {
		$plugin = new Plugin();
		$plugin->start( $this->api( '0.9.0' ) );

		self::assertSame( array(), $this->menu() );
		self::assertFalse( has_filter( 'affibloom_setup_wanted', array( $plugin, 'setup_wanted' ) ) );
		$this->expectOutputRegex( '/Affibloom Lasso Migrator needs Affibloom 1\.0\.0-rc\.1 or later\./' );
		$plugin->too_old_notice();
	}

	public function test_without_affibloom_the_notice_shows_too(): void {
		$this->expectOutputRegex( '/needs Affibloom 1\.0\.0-rc\.1 or later/' );
		( new Plugin() )->too_old_notice();
	}

	public function test_a_new_enough_affibloom_lists_migrate_from_lasso_and_no_notice(): void {
		Functions\when( 'get_option' )->alias( static fn ( string $name, $fallback = false ) => 'lasso_version' === $name ? '370' : $fallback );
		$plugin = new Plugin();
		$plugin->start( $this->api( Plugin::REQUIRES ) );

		self::assertSame( array( MigrationScreen::SLUG ), $this->menu() );
		self::assertSame( 'Migrate from Lasso', $this->navigation->current( MigrationScreen::SLUG )?->label() );
		self::assertNotFalse( has_filter( 'affibloom_setup_wanted', array( $plugin, 'setup_wanted' ) ) );
		self::assertNotFalse( has_filter( 'affibloom_leave_link', array( Plugin::class, 'leave_lasso_link' ) ) );
		self::assertSame( array(), $this->pages->unlisted() );
		$this->expectOutputString( '' );
		$plugin->too_old_notice();
	}

	public function test_a_site_not_finished_gets_the_routes_and_the_download(): void {
		Functions\when( 'get_option' )->alias( static fn ( string $name, $fallback = false ) => 'lasso_version' === $name ? '370' : $fallback );
		( new Plugin() )->start( $this->api( Plugin::REQUIRES ) );

		self::assertTrue( has_action( 'rest_api_init' ) );
		self::assertTrue( has_action( 'admin_post_' . MigrationScreen::DOWNLOAD ) );
	}

	/** Task LM10: once finished, the migration's map is gone, so only the page starts, to say the plugin can go. */
	public function test_a_finished_site_starts_the_page_only(): void {
		Functions\when( 'get_option' )->alias(
			static fn ( string $name, $fallback = false ) => match ( $name ) {
				'lasso_version'            => '370',
				'affibloom_lasso_finished' => 1800000000,
				default                    => $fallback,
			}
		);
		( new Plugin() )->start( $this->api( Plugin::REQUIRES ) );

		self::assertSame( array( MigrationScreen::SLUG ), $this->menu() );
		self::assertFalse( has_action( 'rest_api_init' ), 'No route: nothing can run again.' );
		self::assertFalse( has_action( 'admin_post_' . MigrationScreen::DOWNLOAD ), 'No report download.' );
	}

	/** Task 29D: Lasso's own display links are left as Lasso drew them; any other link is Affibloom's to print. */
	public function test_lasso_s_display_links_are_left_as_drawn(): void {
		self::assertTrue( Plugin::leave_lasso_link( false, array( 'class', 'href', 'data-lasso-box-trackable' ) ) );
		self::assertFalse( Plugin::leave_lasso_link( false, array( 'href', 'data-lasso-id' ) ), "A link Lasso's scan stamped in the text is not its display's." );
		self::assertTrue( Plugin::leave_lasso_link( true, array( 'href' ) ), "Another helper's answer stands." );
	}

	public function test_without_lasso_s_data_the_page_is_reached_by_its_address_only(): void {
		Functions\when( 'get_option' )->justReturn( false );
		( new Plugin() )->start( $this->api( Plugin::REQUIRES ) );

		self::assertSame( array( MigrationScreen::SLUG ), $this->pages->unlisted() );
	}

	public function test_the_setup_wizard_stays_shut_where_lasso_s_data_is(): void {
		Functions\when( 'get_option' )->alias( static fn ( string $name, $fallback = false ) => 'lasso_version' === $name ? '370' : $fallback );

		self::assertFalse( ( new Plugin() )->setup_wanted( true ) );
	}

	public function test_without_lasso_s_data_the_wizard_is_left_as_it_was(): void {
		Functions\when( 'get_option' )->justReturn( false );

		self::assertTrue( ( new Plugin() )->setup_wanted( true ) );
		self::assertFalse( ( new Plugin() )->setup_wanted( false ) );
	}
}
