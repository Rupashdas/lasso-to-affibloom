<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\DisplayMapper;
use AffibloomLassoMigrator\Application\SettingsMapper;
use AffibloomLassoMigrator\Application\ValueText;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * F10 R20, D9, D10, AC4 (Task 25B): every key of Lasso's settings has an Affibloom setting or a reason; no secret's
 * value is printed; Lasso's fallbacks apply.
 */
final class SettingsMapperTest extends TestCase {

	/**
	 * The 102 keys of `lasso_settings` on the site (Lasso 370; the names of tests/fixtures/inventory/settings.json in
	 * Affibloom's repository plus the 7 secrets). Names only: no site value is kept here.
	 */
	private const SITE_KEYS = 'amazon_access_key_id amazon_creators_credential_id amazon_creators_secret amazon_creators_version amazon_creators_partner_tag amazon_secret_key amazon_tracking_id amazon_tracking_id_whitelist amazon_multiple_tracking_id amazon_update_pricing_hourly amazon_pricing_locations_only amazon_default_tracking_country enable_amazon_prime auto_monetize_amazon general_disable_amazon_notifications show_amazon_discount_pricing cron_time_interval auto_upgrade_eligible_links general_performance_mode performance_event_tracking stop_lasso_analytics analytics_enable_click_tracking analytics_google_tracking_id analytics_enable_ip_anonymization analytics_enable_send_pageview general_disable_notification general_disable_tooltip general_bad_link_check cron_prioritize_check io_import_data_from io_import_options io_import_options_revert custom_css_default segment_analytics processes_execute_limit fontawesome_js_svg disable_aawp_description smart_css_loading keep_original_url auto_monetize_affiliates force_change_from_link_details keep_title_when_url_replaced shorten_amazon_affiliate_links amazon_mobile_deep_linking international_click days_before_out_of_stock_alerts keep_genius_formatting enable_site_stripe_post_content_scan enable_nofollow open_new_tab enable_nofollow2 open_new_tab2 enable_sponsored check_duplicate_link show_price show_disclosure show_disclosure_grid show_disclosure_list show_disclosure_table keep_site_stripe_ui theme_name display_colors display_color_main display_color_title display_color_background display_color_button display_color_secondary_button display_color_button_text display_color_pros display_color_cons default_thumbnail primary_button_text secondary_button_text lasso_affiliate_URL badge_text disclosure_text enable_brag_mode custom_css display_advanced_customization display_density display_elements display_layout license_serial site_id lasso_permission cpu_threshold enable_logs rewrite_slug restrict_prefix enable_history cpt_support custom_fields_support manually_background_process_limit link_from_display_title link_cloaking detect_opportunities amazon_add_tracking_id_to_attribution enable_webp undefined display_type count_all_pages_posts intercom_user_jwt';

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	/**
	 * The settings lines, by Lasso key.
	 *
	 * @param array<string, mixed> $stored    The stored option.
	 * @param list<string>         $blacklist The blacklist option.
	 * @return array<string, MigrationLine>
	 */
	private static function lines( array $stored, array $blacklist = array() ): array {
		$lines = array();
		foreach ( ( new SettingsMapper() )->lines( new LassoSettings( $stored, $blacklist ) ) as $line ) {
			$lines[ $line->key ] = $line;
		}

		return $lines;
	}

	public function test_every_key_on_the_site_has_a_family_and_a_target_or_a_reason(): void {
		$keys = explode( ' ', self::SITE_KEYS );
		self::assertCount( 102, $keys );

		$unknown = array_values( array_filter( $keys, static fn ( string $key ): bool => ! DisplayMapper::owns( $key ) && ! in_array( $key, SettingsMapper::known(), true ) ) );
		self::assertSame( array(), $unknown );

		$stored = array_fill_keys( $keys, '' );
		foreach ( self::lines( $stored ) as $line ) {
			self::assertNotSame( Action::Error, $line->action, $line->key . ': ' . $line->note );
			self::assertTrue( '' !== $line->target || '' !== $line->note, $line->key . ' has neither a target nor a reason' );
		}
	}

	public function test_no_secret_value_is_printed_and_an_unknown_key_is_an_error(): void {
		$lines = self::lines(
			array(
				'amazon_secret_key' => null,
				'license_serial'    => null,
				'something_new'     => 'x',
			)
		);

		self::assertSame( ValueText::SECRET, $lines['amazon_secret_key']->value );
		self::assertSame( Action::Skip, $lines['license_serial']->action );
		self::assertSame( Action::Error, $lines['something_new']->action );
	}

	public function test_values_are_read_as_lasso_reads_them(): void {
		$lines = self::lines(
			array(
				'enable_sponsored'             => '0',
				'show_disclosure'              => false,
				'cpu_threshold'                => '80',
				'cpt_support'                  => array(),
				'amazon_tracking_id_whitelist' => array( 'vbvault-20', '' ),
				'lasso_permission'             => 'Administrator',
				'disclosure_text'              => ' ',
				'amazon_tracking_id'           => 'vbvault-20',
			),
			array( 'vbvault-20' )
		);

		self::assertSame( 'sponsored = false', $lines['enable_sponsored']->target );
		self::assertSame( 'cpu_threshold = 80', $lines['cpu_threshold']->target );
		self::assertSame( 'scan_post_types = ["post","page","wp_block"]', $lines['cpt_support']->target );
		self::assertSame( 'tracking_id_whitelist = ["vbvault-20"]', $lines['amazon_tracking_id_whitelist']->target );
		self::assertSame( 'permission = administrator', $lines['lasso_permission']->target );
		self::assertStringStartsWith( 'disclosure_text = We earn a commission', $lines['disclosure_text']->target );
		self::assertStringContainsString( "the site's own tracking ID", $lines['lasso_amazon_tracking_id_blacklist']->note );
	}

	public function test_show_disclosure_list_follows_show_disclosure_when_never_stored(): void {
		self::assertFalse( ( new LassoSettings( array( 'show_disclosure' => false ) ) )->bool( 'show_disclosure_list' ) );
		self::assertTrue( ( new LassoSettings( array( 'show_disclosure' => false, 'show_disclosure_list' => true ) ) )->bool( 'show_disclosure_list' ) );
	}

	public function test_lasso_s_placeholder_thumbnail_and_an_empty_default_css_are_left(): void {
		$lines = self::lines(
			array(
				'default_thumbnail'  => 'https://site.example/wp-content/plugins/lasso/admin/assets/images/lasso-no-thumbnail.jpg',
				'custom_css_default' => '',
			)
		);

		self::assertSame( Action::Skip, $lines['default_thumbnail']->action );
		self::assertSame( Action::Skip, $lines['custom_css_default']->action );
	}

	public function test_an_unknown_or_empty_role_is_administrators_only_as_lasso_fails_closed(): void {
		self::assertSame( 'permission = administrator', self::lines( array( 'lasso_permission' => 'Shop Manager' ) )['lasso_permission']->target );
		self::assertSame( 'permission = administrator', self::lines( array( 'lasso_permission' => '' ) )['lasso_permission']->target );
		self::assertSame( 'permission = editor', self::lines( array( 'lasso_permission' => 'Editor' ) )['lasso_permission']->target );
	}
}
