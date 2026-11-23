<?php
/**
 * The composition root.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Application\AmazonStep;
use AffibloomLassoMigrator\Application\Conversion\BlockRepairer;
use AffibloomLassoMigrator\Application\Conversion\ContentConverter;
use AffibloomLassoMigrator\Application\Conversion\ContentRun;
use AffibloomLassoMigrator\Application\Conversion\CssTranslator;
use AffibloomLassoMigrator\Application\Conversion\LeftoverCheck;
use AffibloomLassoMigrator\Application\Conversion\MigrationMaps;
use AffibloomLassoMigrator\Application\Conversion\StampCleaner;
use AffibloomLassoMigrator\Application\DisplayMapper;
use AffibloomLassoMigrator\Application\DisplayStep;
use AffibloomLassoMigrator\Application\Finish;
use AffibloomLassoMigrator\Application\FieldsStep;
use AffibloomLassoMigrator\Application\GroupsStep;
use AffibloomLassoMigrator\Application\LassoRecordMapper;
use AffibloomLassoMigrator\Application\IssuesStep;
use AffibloomLassoMigrator\Application\LeftBehind;
use AffibloomLassoMigrator\Application\LocationsStep;
use AffibloomLassoMigrator\Application\OpportunitiesStep;
use AffibloomLassoMigrator\Application\MigrationRunner;
use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Application\ProductsStep;
use AffibloomLassoMigrator\Application\SettingsMapper;
use AffibloomLassoMigrator\Application\SettingsStep;
use AffibloomLassoMigrator\Application\SlugsStep;
use AffibloomLassoMigrator\Application\TablesStep;
use AffibloomLassoMigrator\Application\VariantsStep;
use AffibloomLassoMigrator\Application\ViewsMapper;
use AffibloomLassoMigrator\Application\ViewsStep;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiAddresses;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiAiTexts;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiAmazon;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiBlocks;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiDisplay;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiFields;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiGroups;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiIssues;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiLinkTies;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiOpportunities;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiOriginalLinks;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiProducts;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiRecords;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiSettings;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiTables;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiVariants;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiViews;
use AffibloomLassoMigrator\Infrastructure\Lasso\LassoIdMap;
use AffibloomLassoMigrator\Infrastructure\Lasso\LassoInstall;
use AffibloomLassoMigrator\Infrastructure\Lasso\WpLassoSource;
use AffibloomLassoMigrator\Infrastructure\WordPress\CustomCssTexts;
use AffibloomLassoMigrator\Infrastructure\WordPress\ElementorTexts;
use AffibloomLassoMigrator\Infrastructure\WordPress\OptionContentRunState;
use AffibloomLassoMigrator\Infrastructure\WordPress\OptionConversionLock;
use AffibloomLassoMigrator\Infrastructure\WordPress\PostMetaTieLog;
use AffibloomLassoMigrator\Infrastructure\WordPress\PostTexts;
use AffibloomLassoMigrator\Infrastructure\WordPress\WidgetTexts;
use AffibloomLassoMigrator\Infrastructure\WordPress\WpAttributeParser;
use AffibloomLassoMigrator\Infrastructure\WordPress\WpContentPlaces;
use AffibloomLassoMigrator\Infrastructure\WordPress\WpMigratorData;
use AffibloomLassoMigrator\Infrastructure\WordPress\OptionMigrationLog;
use AffibloomLassoMigrator\Infrastructure\WordPress\OptionRunState;
use AffibloomLassoMigrator\Interface\Rest\ContentController;
use AffibloomLassoMigrator\Interface\Rest\FinishController;
use AffibloomLassoMigrator\Interface\Rest\MigrationController;
use AffibloomLassoMigrator\Interface\Admin\MigrationScreen;
use AffibloomLassoMigrator\Interface\Admin\ReportDownload;
use AffibloomLassoMigrator\Interface\Cli\BlocksCommand;
use AffibloomLassoMigrator\Interface\Cli\ConvertCommand;
use AffibloomLassoMigrator\Interface\Cli\CssCommand;
use AffibloomLassoMigrator\Interface\Cli\FinishCommand;
use AffibloomLassoMigrator\Interface\Cli\MigrateCommand;
use AffibloomLassoMigrator\Interface\Cli\StampsCommand;
use AffibloomLassoMigrator\Interface\Cli\VerifyCommand;

/**
 * The composition root: everything the migrator needs, built once, and hooked into WordPress only when Affibloom hands
 * over its extension API (`affibloom_ready`) at the version the migrator was built for. Without it, the migrator shows
 * one notice and does nothing.
 */
final class Plugin {

	/**
	 * The oldest Affibloom with the extension API the migrator calls.
	 */
	public const REQUIRES = '1.0.0-rc.1';

	/**
	 * Whether Affibloom handed over a new enough API.
	 *
	 * @var bool
	 */
	private bool $started = false;

	/**
	 * Builds the composition root.
	 *
	 * @param LassoInstall $lasso Whether the site has Lasso's data.
	 */
	public function __construct( private LassoInstall $lasso = new LassoInstall() ) {}

	/**
	 * Entry point, called once from affibloom-lasso-migrator.php.
	 */
	public static function boot(): void {
		( new self() )->register();
	}

	/**
	 * Waits for Affibloom's API, and says so when it never comes.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'affibloom_ready', array( $this, 'start' ) );
		add_action( 'admin_notices', array( $this, 'too_old_notice' ) );
	}

	/**
	 * Starts on a new enough Affibloom: the Migrate from Lasso page, and no setup wizard on a site with Lasso's data
	 * (deviation 153), as the migration brings its settings.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	public function start( Api $api ): void {
		if ( version_compare( $api->version(), self::REQUIRES, '<' ) ) {
			return;
		}
		$this->started = true;

		// One migration for the page, its download and the command; each runs it only when asked.
		$migration = $this->migration( $api );
		// Task LM8: the content conversion for the page and its routes, built only when one of them needs it.
		$content = fn (): ContentRun => new ContentRun( $this->converter( $api ), $this->stamp_cleaner( $api ), $this->block_repairer(), $this->css_translator(), new OptionRunState(), new OptionContentRunState() );
		// Task LM9: whether anything of Lasso is left, for the page's Check card and the command.
		$check = static fn (): LeftoverCheck => new LeftoverCheck( new WpContentPlaces( array_values( array_map( 'strval', (array) ( $api->settings()['scan_custom_fields'] ?? array() ) ) ) ), new ApiRecords( $api ) );
		// Task LM10: Finish migration, for the page, its route and the command.
		$data     = new WpMigratorData();
		$finish   = static fn (): Finish => new Finish( $check(), $data, new OptionRunState(), new OptionConversionLock() );
		$finished = $data->finished() > 0;
		$screen   = new MigrationScreen(
			$migration,
			$this->lasso,
			dirname( AFFIBLOOM_LASSO_MIGRATOR_FILE ) . '/templates',
			plugins_url( 'assets/admin/migrate.css', AFFIBLOOM_LASSO_MIGRATOR_FILE ),
			AFFIBLOOM_LASSO_MIGRATOR_VERSION,
			plugins_url( 'assets/admin/migrate.js', AFFIBLOOM_LASSO_MIGRATOR_FILE ),
			new OptionMigrationLog( $this->lasso->version() ),
			$content,
			$check,
			$finish
		);
		$screen->register();
		// Task 25D: shown in the menu while Lasso's data exists; its address says so when it does not.
		$api->add_page(
			MigrationScreen::SLUG,
			static fn (): string => __( 'Migrate from Lasso', 'affibloom-lasso-migrator' ),
			static fn (): string => __( "Move this site's Lasso links, settings and content into Affibloom.", 'affibloom-lasso-migrator' ),
			$screen->render( ... ),
			$this->lasso->has_data()
		);
		add_filter( 'affibloom_setup_wanted', array( $this, 'setup_wanted' ) );
		// Task 29D: while Lasso is on, its box draws its own links; Affibloom's render-time link rules leave them.
		add_filter( 'affibloom_leave_link', array( self::class, 'leave_lasso_link' ), 10, 2 );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			( new FinishCommand( $finish() ) )->register();
		}
		// Once finished, the migration's map is gone: nothing may run again, the page only says to delete the plugin.
		if ( $finished ) {
			return;
		}
		// Task 26O: the page's run, one family per request.
		( new MigrationController( new MigrationRunner( $migration, new OptionRunState() ), $api->capability() ) )->register();
		( new ContentController( $content, $api->capability() ) )->register();
		( new FinishController( $finish, $api->capability() ) )->register();
		( new ReportDownload( $migration, $api->capability() ) )->register();
		( new MigrateCommand( $migration, $this->lasso ) )->register();
		// Task LM2: the conversion's dry run, built only for WP-CLI (it reads Lasso's settings).
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			( new ConvertCommand( $this->converter( $api ) ) )->register();
			( new StampsCommand( $this->stamp_cleaner( $api ) ) )->register();
			( new BlocksCommand( $this->block_repairer() ) )->register();
			( new CssCommand( $this->css_translator() ) )->register();
			( new VerifyCommand( $check() ) )->register();
		}
	}

	/**
	 * The repair of the buttons Lasso left for the editor to flag (Task LM6A).
	 */
	private function block_repairer(): BlockRepairer {
		return new BlockRepairer( PostTexts::blocks( ( new WpLassoSource() )->settings()->list( 'cpt_support' ) ), new OptionConversionLock() );
	}

	/**
	 * The translation of the site's Lasso CSS rules (Task LM7).
	 */
	private function css_translator(): CssTranslator {
		return new CssTranslator( new CustomCssTexts(), new OptionConversionLock() );
	}

	/**
	 * The removal of Lasso's stamps (Task LM6): the posts of the types Lasso scanned, with their own copy, the products'
	 * map, and Affibloom's link index through its API.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	private function stamp_cleaner( Api $api ): StampCleaner {
		$source = new WpLassoSource();
		$maps   = new MigrationMaps( new LassoIdMap(), new OptionMigrationLog( $this->lasso->version() ), $source, new ApiGroups( $api ) );

		return new StampCleaner( PostTexts::stamps( $source->settings()->list( 'cpt_support' ) ), $maps->read( ... ), new ApiLinkTies( $api ), new PostMetaTieLog(), new OptionConversionLock() );
	}

	/**
	 * The conversion of Lasso's content (Task LM2): the posts of the types Lasso scanned, Affibloom's block through its
	 * API, and the migration's maps, read when a plan starts.
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	private function converter( Api $api ): ContentConverter {
		$source = new WpLassoSource();
		$maps   = new MigrationMaps( new LassoIdMap(), new OptionMigrationLog( $this->lasso->version() ), $source, new ApiGroups( $api ) );

		// Posts, widgets and Elementor's data (Tasks LM3, LM4).
		$stores = array( new PostTexts( $source->settings()->list( 'cpt_support' ) ), new WidgetTexts(), new ElementorTexts() );

		return new ContentConverter( $stores, new ApiBlocks( $api ), $maps->read( ... ), new WpAttributeParser(), new OptionConversionLock(), new ApiAiTexts( $api ) );
	}

	/**
	 * The migration with the families built so far, and the families still to come (F10 D3).
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	private function migration( Api $api ): MigrationService {
		$source   = new WpLassoSource();
		$data     = new LeftBehind( $source );
		$products = new ApiProducts( $api );
		$log      = new OptionMigrationLog( $this->lasso->version() );
		$map      = new LassoIdMap();
		$steps    = array(
			new SettingsStep( $source, new SettingsMapper(), new ApiSettings( $api ), $log ),
			new DisplayStep( $source, new DisplayMapper(), new ApiDisplay( $api ), $log ),
			new FieldsStep( $source, new ApiFields( $api ), $map, $log ),
			new GroupsStep( $source, new ApiGroups( $api ), $map, $products, $log ),
			new AmazonStep( $source, new ApiAmazon( $api ), $log ),
			new ViewsStep( $source, new ViewsMapper(), new ApiViews( $api ), $log ),
			new SlugsStep( $source, $map, new ApiAddresses( $api ), $log ),
			new VariantsStep( $source, $map, new ApiVariants( $api ), $log ),
			new IssuesStep( $source, $map, new ApiIssues( $api ), $products, $log ),
			new TablesStep( $source, $map, $products, new ApiTables( $api ), $log ),
			new OpportunitiesStep( $source, $map, $products, new ApiOpportunities( $api ), $log ),
			new LocationsStep( $source, new ApiOriginalLinks( $api ) ),
			new ProductsStep( $source, new LassoRecordMapper( site_url() ), $map, $products, $products ),
		);
		return new MigrationService( $steps, $data, $log );
	}

	/**
	 * A link Lasso's own display draws (its box, grid, list and table links carry `data-lasso-box-trackable`) is left as
	 * Lasso drew it, so readers see Lasso's boxes unchanged while both plugins are on (Task 29D).
	 *
	 * @param mixed $leave Whether another helper already leaves it.
	 * @param mixed $names The link's attribute names.
	 */
	public static function leave_lasso_link( mixed $leave, mixed $names ): bool {
		return true === $leave || ( is_array( $names ) && in_array( 'data-lasso-box-trackable', $names, true ) );
	}

	/**
	 * No setup wizard where Lasso's data is: the migration brings the settings.
	 *
	 * @param bool $wanted What Affibloom or another plugin decided so far.
	 */
	public function setup_wanted( bool $wanted ): bool {
		return $wanted && ! $this->lasso->has_data();
	}

	/**
	 * Tells those who manage plugins that Affibloom is too old (or inactive), as the migrator then does nothing.
	 */
	public function too_old_notice(): void {
		if ( $this->started || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			/* translators: %s: Affibloom's version, such as 1.0.0. */
			esc_html( sprintf( __( 'Affibloom Lasso Migrator needs Affibloom %s or later.', 'affibloom-lasso-migrator' ), self::REQUIRES ) )
		);
	}

	/**
	 * Loads translations from /languages.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'affibloom-lasso-migrator', false, dirname( plugin_basename( AFFIBLOOM_LASSO_MIGRATOR_FILE ) ) . '/languages' );
	}
}
