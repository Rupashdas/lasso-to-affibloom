<?php
namespace AffibloomLassoMigrator\Tests\Unit\Domain\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\LassoCssMap;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM7 (ADR-6, decision C1): the site's `.lasso-*` Additional CSS rules style Affibloom's box and table; a name
 * Affibloom does not print is left and reported. The fixture is the site's "LASSO ALTERATIONS" block.
 */
final class LassoCssMapTest extends TestCase {

	public function test_every_rule_of_the_site_is_translated(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/lasso-alterations.css' );

		$translated = LassoCssMap::translate( $css );

		self::assertSame( 0, preg_match_all( '/\.lasso-/', $translated['css'] ), 'No Lasso class is left.' );
		self::assertSame( array(), $translated['unknown'] );
		self::assertSame( preg_match_all( '/\.lasso-/', $css ), preg_match_all( '/\.affibloom-/', $translated['css'] ) );
		self::assertSame( str_replace( '.lasso-', '.affibloom-', $css ), $translated['css'], 'Every other byte is kept.' );
		// 32 rules, some inside @media; a 33rd, commented out, is translated as text.
		self::assertCount( 32, $translated['rules'] );
		self::assertContains(
			array(
				'lasso'     => '.lasso-display.lasso-cutter a.lasso-button-1',
				'affibloom' => '.affibloom-display.affibloom-cutter a.affibloom-button-1',
			),
			$translated['rules']
		);
		self::assertContains(
			array(
				'lasso'     => '.badge-text-wrapper:after, .lasso-badge::after',
				'affibloom' => '.badge-text-wrapper:after, .affibloom-badge::after',
			),
			$translated['rules']
		);
	}

	public function test_renamed_names_variables_and_data_attributes_follow_the_class_map(): void {
		$css = '.lasso-container .lasso-ds-v2-frontend .lasso-v2-link-badge-override{color:var(--lasso-main)}'
			. '#lasso-anchor-id-5-7,.lasso-url-mikasa-v200w,.lasso-fields-color{x:1}'
			. 'a[data-lasso-lid="9"],a[data-lasso-box-trackable],[data-v2-hide-badge]{x:2}';

		self::assertSame(
			'.affibloom-box .affibloom-advanced .affibloom-badge-override{color:var(--affibloom-main)}'
			. '#affibloom-anchor-id-5-7,.affibloom-url-mikasa-v200w,.affibloom-fields-color{x:1}'
			. 'a[data-affibloom-id="9"],a[data-affibloom-box-trackable],[data-hide-badge]{x:2}',
			LassoCssMap::translate( $css )['css']
		);
	}

	public function test_a_name_affibloom_does_not_print_is_left_and_reported(): void {
		$css = '.lasso-cutter .lasso-gallery-slide,a[data-lasso-id]{x:1}.my-lasso-thing{y:2}';

		$translated = LassoCssMap::translate( $css );

		self::assertSame( '.affibloom-cutter .lasso-gallery-slide,a[data-lasso-id]{x:1}.my-lasso-thing{y:2}', $translated['css'] );
		self::assertSame( array( '.lasso-gallery-slide', 'data-lasso-id' ), $translated['unknown'] );
	}
}
