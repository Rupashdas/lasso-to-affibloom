<?php
/**
 * Lasso's settings, read as Lasso reads them.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Domain\Lasso;

/**
 * Lasso's settings option `lasso_settings` and the option `lasso_amazon_tracking_id_blacklist`, read the way Lasso
 * reads them (F10 R20, D9): its defaults under the stored values (admin/lasso-constant.php 107-386), the display keys
 * decoded when stored as JSON text, the per-type buckets merged over the defaults, and the fallbacks Lasso applies.
 * The secret keys (D10) never hold a value here: the source leaves them out and only says they are there.
 */
final class LassoSettings {

	/** The secret keys (D10): the old PA-API pair, the Creators credentials, Lasso's account. Never read. */
	public const SECRETS = array(
		'amazon_access_key_id',
		'amazon_secret_key',
		'amazon_creators_credential_id',
		'amazon_creators_secret',
		'license_serial',
		'site_id',
		'intercom_user_jwt',
	);

	/** The display types Lasso keeps one bucket for. */
	public const TYPES = array( 'single', 'grid', 'list' );

	/** Lasso's defaults for the keys whose fallback the migration reads (SETTINGS_DEFAULT). */
	private const DEFAULTS = array(
		'amazon_tracking_id'                   => '',
		'amazon_tracking_id_whitelist'         => array(),
		'amazon_multiple_tracking_id'          => true,
		'amazon_update_pricing_hourly'         => true,
		'amazon_pricing_locations_only'        => true,
		'amazon_default_tracking_country'      => 'us',
		'enable_amazon_prime'                  => true,
		'auto_monetize_amazon'                 => false,
		'general_disable_amazon_notifications' => false,
		'show_amazon_discount_pricing'         => false,
		'cron_time_interval'                   => 0,
		'auto_upgrade_eligible_links'          => true,
		'general_performance_mode'             => 'balanced',
		'performance_event_tracking'           => true,
		'analytics_enable_click_tracking'      => false,
		'analytics_google_tracking_id'         => '',
		'analytics_enable_ip_anonymization'    => false,
		'analytics_enable_send_pageview'       => false,
		'general_disable_notification'         => false,
		'general_disable_tooltip'              => false,
		'custom_css_default'                   => '',
		'processes_execute_limit'              => false,
		'fontawesome_js_svg'                   => false,
		'disable_aawp_description'             => false,
		'smart_css_loading'                    => false,
		'keep_original_url'                    => array(),
		'auto_monetize_affiliates'             => true,
		'force_change_from_link_details'       => false,
		'keep_title_when_url_replaced'         => true,
		'shorten_amazon_affiliate_links'       => true,
		'amazon_mobile_deep_linking'           => true,
		'international_click'                  => true,
		'days_before_out_of_stock_alerts'      => 0,
		'keep_genius_formatting'               => true,
		'enable_site_stripe_post_content_scan' => false,
		'enable_nofollow'                      => true,
		'open_new_tab'                         => true,
		'enable_sponsored'                     => true,
		'check_duplicate_link'                 => true,
		'show_price'                           => true,
		'show_disclosure'                      => true,
		'show_disclosure_grid'                 => false,
		'keep_site_stripe_ui'                  => false,
		'primary_button_text'                  => 'Buy Now',
		'secondary_button_text'                => 'Our Review',
		'badge_text'                           => '',
		'disclosure_text'                      => 'We earn a commission if you make a purchase, at no additional cost to you.',
		'custom_css'                           => '',
		'display_advanced_customization'       => false,
		'lasso_permission'                     => 'Administrator',
		'cpu_threshold'                        => 65,
		'enable_logs'                          => false,
		'rewrite_slug'                         => '',
		'restrict_prefix'                      => false,
		'cpt_support'                          => array( 'post', 'page', 'wp_block' ),
		'custom_fields_support'                => array(),
		'manually_background_process_limit'    => 2,
		'link_from_display_title'              => true,
		'link_cloaking'                        => true,
		'detect_opportunities'                 => true,
	);

	/** Lasso's flat legacy colour keys → Single's palette keys (class-setting.php 856-859). */
	public const FLAT_COLORS = array(
		'display_color_main'             => 'main',
		'display_color_title'            => 'title',
		'display_color_background'       => 'background',
		'display_color_button'           => 'button',
		'display_color_secondary_button' => 'secondary_button',
		'display_color_button_text'      => 'button_text',
		'display_color_pros'             => 'pros',
		'display_color_cons'             => 'cons',
		'primary_button_text'            => 'primary_button_text',
		'secondary_button_text'          => 'secondary_button_text',
		'badge_text'                     => 'badge_text',
	);

	/**
	 * Builds the settings.
	 *
	 * @param array<string, mixed> $stored    The stored option, the secret keys' values left out (their keys kept).
	 * @param list<string>         $blacklist The option `lasso_amazon_tracking_id_blacklist`.
	 */
	public function __construct( private array $stored, public readonly array $blacklist = array() ) {}

	/**
	 * Every key Lasso stores here, in the stored order.
	 *
	 * @return list<string>
	 */
	public function keys(): array {
		return array_map( 'strval', array_keys( $this->stored ) );
	}

	/**
	 * Whether the key was stored (not just defaulted).
	 *
	 * @param string $key The key.
	 */
	public function stored( string $key ): bool {
		return array_key_exists( $key, $this->stored );
	}

	/**
	 * Whether the key is one of the secrets, whose value is never read.
	 *
	 * @param string $key The key.
	 */
	public static function is_secret( string $key ): bool {
		return in_array( $key, self::SECRETS, true );
	}

	/**
	 * The value as Lasso reads it: the stored one, else Lasso's default; null for a secret or an unknown key.
	 *
	 * @param string $key The key.
	 */
	public function raw( string $key ): mixed {
		if ( self::is_secret( $key ) ) {
			return null;
		}

		return $this->stored[ $key ] ?? self::DEFAULTS[ $key ] ?? null;
	}

	/**
	 * A switch, as Lasso forces it to bool. `show_disclosure_list` and `_table` default from `show_disclosure` (R20).
	 *
	 * @param string $key The key.
	 */
	public function bool( string $key ): bool {
		if ( in_array( $key, array( 'show_disclosure_list', 'show_disclosure_table' ), true ) && ! $this->stored( $key ) ) {
			return $this->bool( 'show_disclosure' );
		}
		$value = $this->raw( $key );

		return is_string( $value ) ? in_array( strtolower( $value ), array( '1', 'true', 'on', 'yes' ), true ) : (bool) $value;
	}

	/**
	 * A number, as Lasso forces it to int.
	 *
	 * @param string $key The key.
	 */
	public function int( string $key ): int {
		$value = $this->raw( $key );

		return is_numeric( $value ) ? (int) $value : 0;
	}

	/**
	 * A text. An empty button text, disclosure or default tracking country falls back to Lasso's default (R20).
	 *
	 * @param string $key The key.
	 */
	public function string( string $key ): string {
		$value = $this->raw( $key );
		$text  = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $text && in_array( $key, array( 'primary_button_text', 'secondary_button_text', 'disclosure_text', 'amazon_default_tracking_country' ), true ) ) {
			return (string) self::DEFAULTS[ $key ];
		}

		return $text;
	}

	/**
	 * A list of texts, empty entries left out. An empty `cpt_support` is Lasso's default (R20).
	 *
	 * @param string $key The key.
	 * @return list<string>
	 */
	public function list( string $key ): array {
		$value = $this->raw( $key );
		$list  = array_values( array_filter( array_map( static fn ( mixed $item ): string => is_scalar( $item ) ? trim( (string) $item ) : '', is_array( $value ) ? $value : array() ), static fn ( string $item ): bool => '' !== $item ) );
		if ( array() === $list && 'cpt_support' === $key ) {
			return self::DEFAULTS['cpt_support'];
		}

		return $list;
	}

	/**
	 * A display key (`theme_name`, `display_colors`, `display_density`, `display_elements`, `display_layout`) as Lasso
	 * reads it: JSON text decoded, then merged over Lasso's defaults per type (R20). Unreadable text is the default.
	 *
	 * @param string $key The key.
	 * @return array<string, mixed>
	 */
	public function display( string $key ): array {
		$defaults = self::display_defaults()[ $key ] ?? array();
		$value    = $this->stored[ $key ] ?? null;
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			$value   = is_array( $decoded ) ? $decoded : ( 'theme_name' === $key && '' !== trim( $value ) ? array_fill_keys( self::TYPES, trim( $value ) ) : null );
		}

		return is_array( $value ) ? array_replace_recursive( $defaults, $value ) : $defaults;
	}

	/**
	 * Lasso's display defaults per type (SETTINGS_DEFAULT).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function display_defaults(): array {
		$colors   = array(
			'main'                  => 'black',
			'title'                 => 'black',
			'background'            => 'white',
			'button'                => '#22BAA0',
			'secondary_button'      => '#22BAA0',
			'button_text'           => 'white',
			'pros'                  => '#22BAA0',
			'cons'                  => '#E06470',
			'primary_button_text'   => 'Buy Now',
			'secondary_button_text' => 'Our Review',
			'badge_text'            => '',
		);
		$density  = array(
			'preset' => 'balanced',
			'values' => array(
				'gap'          => 20,
				'padding'      => 16,
				'spaceBetween' => 10,
				'titleSize'    => 26,
				'titleLines'   => 0,
				'descSize'     => 16,
				'descLines'    => 0,
				'priceSize'    => 16,
				'buttonSize'   => 15,
				'imageHeight'  => 280,
			),
		);
		$elements = array_fill_keys( array( 'badge', 'image', 'title', 'price', 'prime', 'desc', 'proscons', 'primary', 'secondary', 'timestamp' ), true );
		$beside   = array(
			'orientation'    => 'side-by-side',
			'imagePosition'  => 'right',
			'align'          => 'left',
			'buttonAlign'    => 'spread',
			'ctaWidth'       => 'hug',
			'mobileOverflow' => 'stack',
			'imageFit'       => 'contain',
		);

		return array(
			'theme_name'       => array_fill_keys( self::TYPES, 'Cactus' ),
			'display_colors'   => array_fill_keys( self::TYPES, $colors ),
			'display_density'  => array_fill_keys( self::TYPES, $density ),
			'display_elements' => array_fill_keys( self::TYPES, $elements ),
			'display_layout'   => array(
				'columns' => 3,
				'single'  => $beside,
				'grid'    => array_merge(
					$beside,
					array(
						'orientation'   => 'stacked',
						'imagePosition' => 'top',
						'align'         => 'center',
					)
				),
				'list'    => $beside,
			),
		);
	}
}
