<?php
/**
 * The composition root.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator;

use Affibloom\Extension\Api;
use AffibloomLassoMigrator\Application\LassoRecordMapper;
use AffibloomLassoMigrator\Application\MigrationService;
use AffibloomLassoMigrator\Application\ProductsStep;
use AffibloomLassoMigrator\Infrastructure\Affibloom\ApiProductRules;
use AffibloomLassoMigrator\Infrastructure\Lasso\LassoIdMap;
use AffibloomLassoMigrator\Infrastructure\Lasso\LassoInstall;
use AffibloomLassoMigrator\Infrastructure\Lasso\WpLassoSource;
use AffibloomLassoMigrator\Interface\Admin\MigrationScreen;
use AffibloomLassoMigrator\Interface\Cli\MigrateCommand;

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

		$screen = new MigrationScreen();
		$api->add_page(
			MigrationScreen::SLUG,
			static fn (): string => __( 'Migrate from Lasso', 'affibloom-lasso-migrator' ),
			static fn (): string => __( "Move this site's Lasso links, settings and content into Affibloom.", 'affibloom-lasso-migrator' ),
			$screen->render( ... )
		);
		add_filter( 'affibloom_setup_wanted', array( $this, 'setup_wanted' ) );
		$this->migrate_command( $api )->register();
	}

	/**
	 * `wp affibloom-lasso migrate` with the families built so far (F10 D3).
	 *
	 * @param Api $api Affibloom's extension API.
	 */
	private function migrate_command( Api $api ): MigrateCommand {
		$steps = array(
			new ProductsStep( new WpLassoSource(), new LassoRecordMapper( site_url() ), new LassoIdMap(), new ApiProductRules( $api ) ),
		);

		return new MigrateCommand( new MigrationService( $steps ), $this->lasso );
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
