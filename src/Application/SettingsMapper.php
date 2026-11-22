<?php
/**
 * Where every Lasso setting goes.
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
 * Every key of `lasso_settings` that is not a display key, and the tracking-ID blacklist, with its Affibloom setting
 * (`affibloom_settings`) and value, or the reason it is left (F10 R20, D9, D10; analysis table F; the names from F9's
 * analysis). A key the migrator does not know is an error line, so nothing is dropped unseen.
 */
final class SettingsMapper {

	/** Lasso key => Affibloom setting and how the value is read. */
	private const MAPPED = array(
		'enable_nofollow'                      => array( 'nofollow', 'bool' ),
		'open_new_tab'                         => array( 'new_tab', 'bool' ),
		'enable_sponsored'                     => array( 'sponsored', 'bool' ),
		'show_disclosure'                      => array( 'show_disclosure', 'bool' ),
		'check_duplicate_link'                 => array( 'allow_duplicates', 'bool' ),
		'link_cloaking'                        => array( 'link_cloaking', 'bool' ),
		'detect_opportunities'                 => array( 'detect_opportunities', 'bool' ),
		'auto_monetize_affiliates'             => array( 'auto_detect_links', 'bool' ),
		'analytics_google_tracking_id'         => array( 'ga_tracking_id', 'string' ),
		'analytics_enable_click_tracking'      => array( 'ga_click_tracking', 'bool' ),
		'analytics_enable_send_pageview'       => array( 'ga_pageview', 'bool' ),
		'analytics_enable_ip_anonymization'    => array( 'ga_anonymize_ip', 'bool' ),
		'performance_event_tracking'           => array( 'click_tracking', 'bool' ),
		'rewrite_slug'                         => array( 'link_prefix', 'string' ),
		'smart_css_loading'                    => array( 'smart_css', 'bool' ),
		'general_performance_mode'             => array( 'performance_mode', 'string' ),
		'lasso_permission'                     => array( 'permission', 'role' ),
		'cpu_threshold'                        => array( 'cpu_threshold', 'int' ),
		'cpt_support'                          => array( 'scan_post_types', 'list' ),
		'custom_fields_support'                => array( 'scan_custom_fields', 'list' ),
		'general_disable_amazon_notifications' => array( 'disable_amazon_notice', 'bool' ),
		'general_disable_tooltip'              => array( 'disable_help_tips', 'bool' ),
		'general_disable_notification'         => array( 'disable_import_notice', 'bool' ),
		'enable_logs'                          => array( 'enable_logs', 'bool' ),
		'keep_original_url'                    => array( 'keep_original_domains', 'list' ),
		'cron_time_interval'                   => array( 'cron_interval', 'int' ),
		'manually_background_process_limit'    => array( 'manual_process_limit', 'int' ),
		'restrict_prefix'                      => array( 'restrict_prefix', 'bool' ),
		'processes_execute_limit'              => array( 'process_limit', 'bool' ),
		'fontawesome_js_svg'                   => array( 'fontawesome_svg', 'bool' ),
		'disable_aawp_description'             => array( 'no_feature_description', 'bool' ),
		'disclosure_text'                      => array( 'disclosure_text', 'string' ),
		'link_from_display_title'              => array( 'link_from_display_title', 'bool' ),
		'show_price'                           => array( 'show_price', 'bool' ),
		'show_disclosure_grid'                 => array( 'show_disclosure_grid', 'bool' ),
		'show_disclosure_list'                 => array( 'show_disclosure_list', 'bool' ),
		'show_disclosure_table'                => array( 'show_disclosure_table', 'bool' ),
		'keep_site_stripe_ui'                  => array( 'keep_site_stripe_ui', 'bool' ),
		'custom_css'                           => array( 'custom_css', 'string' ),
		'amazon_tracking_id'                   => array( 'tracking_id', 'string' ),
		'amazon_multiple_tracking_id'          => array( 'multiple_tracking_ids', 'bool' ),
		'amazon_tracking_id_whitelist'         => array( 'tracking_id_whitelist', 'list' ),
		'auto_monetize_amazon'                 => array( 'auto_monetize_amazon', 'bool' ),
		'enable_amazon_prime'                  => array( 'show_prime', 'bool' ),
		'show_amazon_discount_pricing'         => array( 'show_discount_pricing', 'bool' ),
		'auto_upgrade_eligible_links'          => array( 'commission_upgrades', 'bool' ),
		'amazon_update_pricing_hourly'         => array( 'update_pricing_daily', 'bool' ),
		'amazon_pricing_locations_only'        => array( 'pricing_locations_only', 'bool' ),
		'force_change_from_link_details'       => array( 'force_change_from_link_details', 'bool' ),
		'keep_title_when_url_replaced'         => array( 'keep_title_when_url_replaced', 'bool' ),
		'shorten_amazon_affiliate_links'       => array( 'shorten_amazon_links', 'bool' ),
		'amazon_mobile_deep_linking'           => array( 'mobile_deep_linking', 'bool' ),
		'international_click'                  => array( 'international_click', 'bool' ),
		'keep_genius_formatting'               => array( 'keep_genius_formatting', 'bool' ),
		'enable_site_stripe_post_content_scan' => array( 'scan_sitestripe_in_post_content', 'bool' ),
		'days_before_out_of_stock_alerts'      => array( 'days_before_out_of_stock_alerts', 'int' ),
		'amazon_default_tracking_country'      => array( 'default_country', 'string' ),
	);

	/** Lasso key => why it is not carried. */
	private const LEFT = array(
		'amazon_access_key_id'                  => "secret, not read: Amazon's old PA-API, which Affibloom does not call",
		'amazon_secret_key'                     => "secret, not read: Amazon's old PA-API, which Affibloom does not call",
		'amazon_creators_credential_id'         => 'secret, not read here: the amazon family moves the Creators credentials',
		'amazon_creators_secret'                => 'secret, not read here: the amazon family moves the Creators credentials',
		'amazon_creators_version'               => 'moved with the Creators credentials by the amazon family',
		'amazon_creators_partner_tag'           => 'moved with the Creators credentials by the amazon family',
		'license_serial'                        => "secret, not read: Lasso's account",
		'site_id'                               => "secret, not read: Lasso's account",
		'intercom_user_jwt'                     => "secret, not read: Lasso's account",
		'stop_lasso_analytics'                  => "Lasso's cloud analytics",
		'segment_analytics'                     => "Lasso's cloud analytics",
		'general_bad_link_check'                => 'not used by Lasso',
		'cron_prioritize_check'                 => 'not used by Lasso',
		'amazon_add_tracking_id_to_attribution' => 'not used by Lasso',
		'enable_webp'                           => 'not used by Lasso',
		'undefined'                             => 'not used by Lasso',
		'count_all_pages_posts'                 => "a counter of Lasso's scan",
		'io_import_data_from'                   => "Lasso's Import screen state",
		'io_import_options'                     => "Lasso's Import screen state",
		'io_import_options_revert'              => "Lasso's Import screen state",
		'display_type'                          => "the Display screen's selected type",
		'enable_history'                        => 'Affibloom keeps no content history (F34 N1)',
		'enable_nofollow2'                      => "no Affibloom setting: a new second button starts with both on, Lasso's default",
		'open_new_tab2'                         => "no Affibloom setting: a new second button starts with both on, Lasso's default",
		'enable_brag_mode'                      => 'brag mode is not in Affibloom (deviation 95)',
		'lasso_affiliate_URL'                   => 'brag mode is not in Affibloom (deviation 95)',
	);

	/** Lasso's roles for its permission setting → Affibloom's (Permission). */
	private const ROLES = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );

	/** Lasso's placeholder image, which lives in Lasso's folder. */
	private const PLACEHOLDER = 'lasso-no-thumbnail';

	/**
	 * One line per key, then the blacklist's.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 * @return list<MigrationLine>
	 */
	public function lines( LassoSettings $settings ): array {
		$lines = array();
		foreach ( $settings->keys() as $key ) {
			if ( DisplayMapper::owns( $key ) ) {
				continue;
			}
			$lines[] = $this->line( $settings, $key );
		}
		$lines[] = $this->blacklist( $settings );

		return $lines;
	}

	/**
	 * Every key this mapper knows (mapped, left, or read by a special rule), for the coverage test.
	 *
	 * @return list<string>
	 */
	public static function known(): array {
		return array_merge( array_keys( self::MAPPED ), array_keys( self::LEFT ), array( 'custom_css_default', 'default_thumbnail' ) );
	}

	/**
	 * One key's line.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 * @param string        $key      The key.
	 */
	private function line( LassoSettings $settings, string $key ): MigrationLine {
		$shown = LassoSettings::is_secret( $key ) ? ValueText::SECRET : ValueText::of( $settings->raw( $key ) );
		if ( isset( self::LEFT[ $key ] ) ) {
			return self::of( $key, $shown, Action::Skip, '', self::LEFT[ $key ] );
		}
		if ( 'custom_css_default' === $key ) {
			return '' === $settings->string( $key )
				? self::of( $key, $shown, Action::Skip, '', 'empty' )
				: self::of( $key, $shown, Action::Create, 'custom_css', 'appended to custom_css, as Lasso adds it to the box CSS' );
		}
		if ( 'default_thumbnail' === $key ) {
			$image = $settings->string( $key );
			return '' === $image || str_contains( $image, self::PLACEHOLDER )
				? self::of( $key, $shown, Action::Skip, '', "Lasso's own placeholder, inside its folder: Affibloom shows its own" )
				: self::of( $key, $shown, Action::Create, 'default_thumbnail = ' . $image );
		}
		if ( ! isset( self::MAPPED[ $key ] ) ) {
			return self::of( $key, $shown, Action::Error, '', 'a Lasso setting the migrator does not know: check it by hand' );
		}

		list( $target, $kind ) = self::MAPPED[ $key ];
		$value                 = match ( $kind ) {
			'bool'   => $settings->bool( $key ),
			'int'    => $settings->int( $key ),
			'list'   => $settings->list( $key ),
			'role'   => strtolower( $settings->string( $key ) ),
			default  => $settings->string( $key ),
		};
		// Lasso fails closed: an empty or unknown role means administrators only (get_lasso_permission_level()).
		if ( 'role' === $kind && ! in_array( $value, self::ROLES, true ) ) {
			return self::of( $key, $shown, Action::Create, $target . ' = administrator', 'not a role Lasso knows: administrators only, as Lasso reads it' );
		}

		return self::of( $key, $shown, Action::Create, $target . ' = ' . ValueText::of( $value ) );
	}

	/**
	 * The tracking-ID blacklist option (analysis O4): flagged when it lists the site's own tracking ID.
	 *
	 * @param LassoSettings $settings Lasso's settings.
	 */
	private function blacklist( LassoSettings $settings ): MigrationLine {
		$key  = 'lasso_amazon_tracking_id_blacklist';
		$own  = $settings->string( 'amazon_tracking_id' );
		$note = '' !== $own && in_array( $own, $settings->blacklist, true ) ? "it lists the site's own tracking ID: check it by hand" : '';

		return self::of( $key, ValueText::of( $settings->blacklist ), Action::Create, 'tracking_id_blacklist = ' . ValueText::of( $settings->blacklist ), $note );
	}

	/**
	 * A settings line.
	 *
	 * @param string $key    The Lasso key.
	 * @param string $shown  Lasso's value, as printed.
	 * @param Action $action What the migration does.
	 * @param string $target The Affibloom setting and value.
	 * @param string $note   Why.
	 */
	private static function of( string $key, string $shown, Action $action, string $target, string $note = '' ): MigrationLine {
		return new MigrationLine( Family::Settings, $key, '', '', '', $action, $target, $note, $shown );
	}
}
