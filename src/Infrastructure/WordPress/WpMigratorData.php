<?php
/**
 * The migrator's options and post meta.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Infrastructure\WordPress;

use AffibloomLassoMigrator\Domain\Migration\MigratorData;
use AffibloomLassoMigrator\Infrastructure\Lasso\LassoIdMap;

/**
 * Every option and post meta the migrator writes (Task LM10), by the constant of the class that writes it; a drift test
 * (`WpMigratorDataTest`) keeps the lists complete. Finish erases them and sets `affibloom_lasso_finished`; uninstall
 * erases them and that mark too. Lasso's own data and Affibloom's are not on the lists.
 */
final class WpMigratorData implements MigratorData {

	/** The time Finish ran (autoloaded: the plugin reads it on every load). */
	public const FINISHED = 'affibloom_lasso_finished';

	/** The migrator's options: its log, run states, locks, the conversion's site-wide copies, the one-time import's record. */
	public const OPTIONS = array(
		OptionMigrationLog::OPTION,
		OptionRunState::RUN,
		OptionRunState::LOCK,
		OptionContentRunState::OPTION,
		OptionConversionLock::LOCK,
		WidgetTexts::COPY,
		CustomCssTexts::COPY,
		CustomCssTexts::WRITTEN,
		LassoIdMap::IMPORT_OPTION,
	);

	/** The migrator's post meta: the ID map and marks, the conversion's copies and md5s, the stamp ties. */
	public const META = array(
		LassoIdMap::META_KEY,
		LassoIdMap::MARK_KEY,
		PostTexts::COPY,
		PostTexts::WRITTEN,
		PostTexts::STAMPS_COPY,
		PostTexts::STAMPS_WRITTEN,
		PostTexts::BLOCKS_COPY,
		PostTexts::BLOCKS_WRITTEN,
		ElementorTexts::COPY,
		ElementorTexts::WRITTEN,
		PostMetaTieLog::KEY,
	);

	/**
	 * When the migration was finished, a Unix time; 0 while it is not.
	 */
	public function finished(): int {
		return (int) get_option( self::FINISHED, 0 );
	}

	/**
	 * Erases every option and post meta on the lists.
	 *
	 * @return array<string, int>
	 */
	public function erase(): array {
		global $wpdb;
		$removed = array();
		foreach ( self::OPTIONS as $name ) {
			if ( false !== get_option( $name, false ) ) {
				delete_option( $name );
				$removed[ $name ] = 1;
			}
		}
		foreach ( self::META as $key ) {
			$rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one count per key, before deleting it.
			if ( $rows > 0 ) {
				// Deletes every row of the key and clears each post's meta cache.
				delete_post_meta_by_key( $key );
				$removed[ $key ] = $rows;
			}
		}

		return $removed;
	}

	/**
	 * Marks the migration finished.
	 *
	 * @param int $now The time.
	 */
	public function finish( int $now ): void {
		update_option( self::FINISHED, $now, true );
	}

	/**
	 * Uninstall: everything on the lists and the finished mark.
	 */
	public function forget(): void {
		$this->erase();
		delete_option( self::FINISHED );
	}
}
