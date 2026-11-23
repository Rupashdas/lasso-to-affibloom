<?php
namespace AffibloomLassoMigrator\Tests\Unit\Infrastructure\WordPress;

use AffibloomLassoMigrator\Infrastructure\WordPress\WpMigratorData;
use AffibloomLassoMigrator\Interface\Admin\MigrationScreen;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * Task LM10: Finish and uninstall erase every option and post meta the migrator writes. Each name the code stores is a
 * constant, and each such constant is on the eraser's lists, so a later task's new copy cannot be left behind.
 */
final class WpMigratorDataTest extends TestCase {

	/** Names of the migrator's that are not stored: the report download's admin-post action and nonce. */
	private const NOT_STORED = array( MigrationScreen::DOWNLOAD );

	public function test_every_stored_name_in_the_code_is_erased(): void {
		$names = array();
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__, 4 ) . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $files as $file ) {
			preg_match_all( "/const [A-Z_]+ = '(_?affibloom_[a-z0-9_]+)'/", (string) file_get_contents( (string) $file ), $found );
			$names = array_merge( $names, $found[1] );
		}
		$stored = array_values( array_diff( array_unique( $names ), self::NOT_STORED, array( WpMigratorData::FINISHED ) ) );
		sort( $stored );
		$erased = array_merge( WpMigratorData::OPTIONS, WpMigratorData::META );
		sort( $erased );

		self::assertGreaterThan( 15, count( $stored ), 'The scan finds the names.' );
		self::assertSame( $stored, $erased );
	}

	public function test_erase_removes_the_options_there_and_every_row_of_each_meta(): void {
		global $wpdb;
		$wpdb = new class() {
			public string $postmeta = 'wp_postmeta';
			public function prepare( string $query, string $key ): string {
				return $key;
			}
			public function get_var( string $key ): string {
				return '_affibloom_lasso_id' === $key ? '256' : '0';
			}
		};
		Functions\when( 'get_option' )->alias( static fn ( string $name, $fallback = false ) => in_array( $name, array( 'affibloom_lasso_import', 'affibloom_lasso_migration' ), true ) ? array( 'x' ) : $fallback );
		Functions\expect( 'delete_option' )->twice();
		Functions\expect( 'delete_post_meta_by_key' )->once()->with( '_affibloom_lasso_id' );

		$removed = ( new WpMigratorData() )->erase();

		self::assertSame(
			array(
				'affibloom_lasso_migration' => 1,
				'affibloom_lasso_import'    => 1,
				'_affibloom_lasso_id'       => 256,
			),
			$removed
		);
	}
}
