<?php
/**
 * The settings family.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\Family;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Domain\Migration\MigrationLog;
use AffibloomLassoMigrator\Domain\Migration\SettingsWriter;

/**
 * The settings family (F10 R20, D9; Tasks 25B, 26C): every key of Lasso's settings and the blacklist with its
 * Affibloom setting, written through the Settings screens' rules one tab at a time. A key whose value Affibloom already
 * has is unchanged. The first run writes the others; a later run keeps Affibloom's value where it differs and reports
 * it, unless --update. A value Affibloom refuses is an error line; the tab's other values are saved.
 */
final class SettingsStep implements RunStep {

	/** Affibloom's settings by the tab that saves them, in the order they are saved (the import tab last). */
	private const TABS = array(
		'general' => array( 'new_tab', 'nofollow', 'sponsored', 'show_disclosure', 'allow_duplicates', 'link_cloaking', 'detect_opportunities', 'auto_detect_links', 'ga_tracking_id', 'ga_click_tracking', 'ga_pageview', 'ga_anonymize_ip', 'click_tracking', 'link_prefix', 'smart_css', 'permission', 'cpu_threshold', 'scan_post_types', 'scan_custom_fields', 'disable_help_tips', 'keep_original_domains', 'cron_interval', 'manual_process_limit', 'process_limit', 'fontawesome_svg' ),
		'display' => array( 'disclosure_text', 'custom_css', 'show_price', 'show_disclosure_grid', 'show_disclosure_table', 'link_from_display_title', 'keep_site_stripe_ui' ),
		'amazon'  => array( 'tracking_id', 'multiple_tracking_ids', 'tracking_id_whitelist', 'auto_monetize_amazon', 'show_prime', 'show_discount_pricing', 'update_pricing_daily', 'pricing_locations_only', 'keep_title_when_url_replaced', 'shorten_amazon_links', 'keep_genius_formatting', 'scan_sitestripe_in_post_content', 'days_before_out_of_stock_alerts', 'default_country' ),
		// The Logs page's switch (F37 R2), saved through its own hidden tab.
		'logs'    => array( 'enable_logs' ),
		'import'  => array( 'tracking_id_blacklist', 'default_thumbnail' ),
	);

	/**
	 * Builds the step.
	 *
	 * @param LassoSource    $source Lasso's data.
	 * @param SettingsMapper $mapper Lasso's keys → Affibloom's settings.
	 * @param SettingsWriter $writer Affibloom's settings.
	 * @param MigrationLog   $log    Whether the family ran before.
	 */
	public function __construct( private LassoSource $source, private SettingsMapper $mapper, private SettingsWriter $writer, private MigrationLog $log ) {}

	/**
	 * The family it migrates.
	 */
	public function family(): Family {
		return Family::Settings;
	}

	/**
	 * One line per key, nothing written.
	 *
	 * @param bool $update Take Lasso's value where Affibloom's differs (--update).
	 * @param bool $force  Unused: settings keep no edit marks, --update decides.
	 * @return list<MigrationLine>
	 */
	public function plan( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		return array_column( $this->decisions( $update ), 'line' );
	}

	/**
	 * Writes the keys to import, one tab at a time.
	 *
	 * @param bool $update Take Lasso's value where Affibloom's differs (--update).
	 * @param bool $force  Unused, as in plan().
	 * @return list<MigrationLine>
	 */
	public function run( bool $update = false, bool $force = false ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- RunStep's signature.
		$decisions = $this->decisions( $update );
		$writes    = array();
		foreach ( $decisions as $decision ) {
			if ( Action::Create === $decision['line']->action && null !== $decision['target'] ) {
				$writes[ self::tab( $decision['target'][0] ) ][ $decision['target'][0] ] = $decision['target'][1];
			}
		}
		$refused = array();
		foreach ( array_keys( self::TABS ) as $tab ) {
			if ( isset( $writes[ $tab ] ) ) {
				array_push( $refused, ...$this->writer->save( $tab, $writes[ $tab ] ) );
			}
		}

		$lines = array();
		foreach ( $decisions as $decision ) {
			$line    = $decision['line'];
			$lines[] = null !== $decision['target'] && Action::Create === $line->action && in_array( $decision['target'][0], $refused, true )
				? new MigrationLine( Family::Settings, $line->key, '', '', '', Action::Error, $line->target, 'Affibloom refused this value; its own is kept', $line->value )
				: $line;
		}

		return $lines;
	}

	/**
	 * Each key's decision: the mapper's line, and for a carried key whether Affibloom already has the value.
	 *
	 * @param bool $update With --update.
	 * @return list<array{line: MigrationLine, target: array{0: string, 1: mixed}|null}>
	 */
	private function decisions( bool $update ): array {
		$settings = $this->source->settings();
		$targets  = $this->mapper->targets( $settings );
		$current  = $this->writer->values();
		$first    = ! $this->log->has_run( Family::Settings );

		$decisions = array();
		foreach ( $this->mapper->lines( $settings ) as $line ) {
			$target = $targets[ $line->key ] ?? null;
			if ( null === $target || null === self::tab( $target[0] ) ) {
				$decisions[] = array(
					'line'   => null === $target ? $line : self::with( $line, Action::Error, 'no Affibloom tab saves ' . $target[0] ),
					'target' => null,
				);
				continue;
			}
			$has         = $current[ $target[0] ] ?? null;
			$decisions[] = array(
				'line'   => match ( true ) {
					self::same( $target[1], $has ) => self::with( $line, Action::Unchanged, $line->note ),
					$first || $update              => self::with( $line, Action::Create, '' === $line->note ? 'Affibloom had ' . ValueText::of( $has ) : $line->note ),
					default                        => self::with( $line, Action::Skip, 'Affibloom has ' . ValueText::of( $has ) . ': kept (--update takes Lasso\'s)' ),
				},
				'target' => $target,
			);
		}

		// custom_css_default travels in custom_css's value, so its line says what custom_css's says.
		$css = array_values( array_filter( $decisions, static fn ( array $decision ): bool => 'custom_css' === $decision['line']->key ) );
		foreach ( $decisions as $i => $decision ) {
			if ( 'custom_css_default' === $decision['line']->key && Action::Create === $decision['line']->action && array() !== $css ) {
				$decisions[ $i ]['line'] = self::with( $decision['line'], $css[0]['line']->action, $decision['line']->note );
			}
		}

		return $decisions;
	}

	/**
	 * The tab that saves an Affibloom setting, or null.
	 *
	 * @param string $setting The setting.
	 */
	private static function tab( string $setting ): ?string {
		foreach ( self::TABS as $tab => $settings ) {
			if ( in_array( $setting, $settings, true ) ) {
				return $tab;
			}
		}

		return null;
	}

	/**
	 * Whether Lasso's value is Affibloom's already: switches as switches, numbers as numbers, lists in any order, texts
	 * without the spaces around them.
	 *
	 * @param mixed $lasso     Lasso's value, mapped.
	 * @param mixed $affibloom Affibloom's.
	 */
	private static function same( mixed $lasso, mixed $affibloom ): bool {
		if ( is_array( $lasso ) || is_array( $affibloom ) ) {
			$sorted = static function ( mixed $values_of ): array {
				$values = array_map( 'strval', is_array( $values_of ) ? $values_of : array() );
				sort( $values );
				return $values;
			};
			return $sorted( $lasso ) === $sorted( $affibloom );
		}
		if ( is_bool( $lasso ) || is_bool( $affibloom ) ) {
			return (bool) $lasso === (bool) $affibloom;
		}

		return trim( (string) $lasso ) === trim( (string) $affibloom );
	}

	/**
	 * The line with another action and note.
	 *
	 * @param MigrationLine $line   The mapper's line.
	 * @param Action        $action The action.
	 * @param string        $note   The note.
	 */
	private static function with( MigrationLine $line, Action $action, string $note ): MigrationLine {
		return new MigrationLine( $line->family, $line->key, $line->name, $line->type, $line->status, $action, $line->target, $note, $line->value );
	}
}
