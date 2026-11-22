<?php
/**
 * Where Lasso's display settings go.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;

/**
 * Lasso's display keys per type → Affibloom's display model (`affibloom_display`: per type `theme`, `palette`,
 * `elements`, `density`, `layout`, and the grid's `columns`), read as Lasso reads them (F10 R20, D9; analysis S22-S28):
 * the flat colours fill Single only when `display_colors` was never stored, `disclosure` is not an element, and a site
 * whose advanced customisation was on and never upgraded keeps "custom" density presets.
 */
final class DisplayMapper {

	/** Lasso's per-type keys → Affibloom's per-type field. */
	private const PER_TYPE = array(
		'theme_name'       => 'theme',
		'display_colors'   => 'palette',
		'display_elements' => 'elements',
		'display_density'  => 'density',
		'display_layout'   => 'layout',
	);

	/** Lasso's one-time upgrade flags (class-setting.php 970-993). */
	private const FLAGS = array( 'display_advanced_customization', 'display_advanced_customization_migrated' );

	/**
	 * Whether the key is a display key (the display family's, not the settings family's).
	 *
	 * @param string $key A key of Lasso's settings.
	 */
	public static function owns( string $key ): bool {
		return isset( self::PER_TYPE[ $key ] ) || isset( LassoSettings::FLAT_COLORS[ $key ] ) || in_array( $key, self::FLAGS, true );
	}

	/**
	 * Each type's Affibloom field and value, by line key (`theme_name.single`, `display_layout.columns`): what the
	 * display family writes (Task 26D). The columns are the grid's.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 * @return array<string, array{0: string, 1: string, 2: mixed}> Type, field, value.
	 */
	public function targets( LassoSettings $settings ): array {
		$targets = array();
		foreach ( self::PER_TYPE as $key => $field ) {
			$read = $settings->display( $key );
			foreach ( LassoSettings::TYPES as $type ) {
				$targets[ $key . '.' . $type ] = array( $type, $field, $this->value( $settings, $key, $type, $read[ $type ] ?? null ) );
			}
		}
		$targets['display_layout.columns'] = array( 'grid', 'columns', (int) ( $settings->display( 'display_layout' )['columns'] ?? 3 ) );

		return $targets;
	}

	/**
	 * One line per type and field, the grid's columns, then the flat keys and flags with why they are not carried alone.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 * @return list<MigrationLine>
	 */
	public function lines( LassoSettings $settings ): array {
		$lines = array();
		foreach ( self::PER_TYPE as $key => $field ) {
			$read = $settings->display( $key );
			foreach ( LassoSettings::TYPES as $type ) {
				$value   = $this->value( $settings, $key, $type, $read[ $type ] ?? null );
				$lines[] = self::of( $key . '.' . $type, ValueText::of( $read[ $type ] ?? null ), Action::Create, $type . '.' . $field . ' = ' . ValueText::of( $value ), $this->note( $settings, $key, $type ) );
			}
		}
		$columns = $settings->display( 'display_layout' )['columns'] ?? 3;
		$lines[] = self::of( 'display_layout.columns', ValueText::of( $columns ), Action::Create, 'columns = ' . ValueText::of( (int) $columns ) );

		foreach ( $settings->keys() as $key ) {
			if ( isset( LassoSettings::FLAT_COLORS[ $key ] ) ) {
				$note    = $settings->stored( 'display_colors' )
					? "Single's palette comes from display_colors, as Lasso reads it"
					: 'read into single.palette (display_colors was never stored)';
				$lines[] = self::of( $key, ValueText::of( $settings->raw( $key ) ), Action::Skip, '', $note );
			} elseif ( in_array( $key, self::FLAGS, true ) ) {
				$lines[] = self::of( $key, ValueText::of( $settings->raw( $key ) ), Action::Skip, '', "Lasso's one-time upgrade flag: its rule is applied to the density presets" );
			}
		}

		return $lines;
	}

	/**
	 * A type's value as Affibloom will get it.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 * @param string        $key      The Lasso key.
	 * @param string        $type     The display type.
	 * @param mixed         $value    Lasso's value for the type.
	 */
	private function value( LassoSettings $settings, string $key, string $type, mixed $value ): mixed {
		if ( 'display_colors' === $key && 'single' === $type && ! $settings->stored( 'display_colors' ) ) {
			$palette = is_array( $value ) ? $value : array();
			foreach ( LassoSettings::FLAT_COLORS as $flat => $field ) {
				if ( $settings->stored( $flat ) ) {
					$palette[ $field ] = $settings->raw( $flat );
				}
			}
			return $palette;
		}
		if ( 'display_elements' === $key && is_array( $value ) ) {
			unset( $value['disclosure'] );
			return array_map( 'boolval', $value );
		}
		if ( 'display_density' === $key && is_array( $value ) && $this->custom_presets( $settings ) ) {
			$value['preset'] = 'custom';
		}

		return $value;
	}

	/**
	 * Why a type's value differs from what Lasso stores, when it does.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 * @param string        $key      The Lasso key.
	 * @param string        $type     The display type.
	 */
	private function note( LassoSettings $settings, string $key, string $type ): string {
		return match ( true ) {
			'display_colors' === $key && 'single' === $type && ! $settings->stored( 'display_colors' ) => "Lasso's flat colours, as display_colors was never stored",
			'display_elements' === $key => 'disclosure is not an element (Lasso drops it)',
			'display_density' === $key && $this->custom_presets( $settings ) => "advanced customisation was on and never upgraded: Lasso's preset is custom",
			! $settings->stored( $key ) => "Lasso's default",
			default => '',
		};
	}

	/**
	 * Whether Lasso reads every density preset as "custom": advanced customisation on and its upgrade never run.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 */
	private function custom_presets( LassoSettings $settings ): bool {
		return $settings->bool( 'display_advanced_customization' ) && ! $settings->bool( 'display_advanced_customization_migrated' );
	}

	/**
	 * A display line.
	 *
	 * @param string $key    The Lasso key (and type).
	 * @param string $shown  Lasso's value, as printed.
	 * @param Action $action What the migration does.
	 * @param string $target The Affibloom field and value.
	 * @param string $note   Why.
	 */
	private static function of( string $key, string $shown, Action $action, string $target, string $note = '' ): MigrationLine {
		return new MigrationLine( Family::Display, $key, '', '', '', $action, $target, $note, $shown );
	}
}
