<?php
namespace AffibloomLassoMigrator\Tests\Unit\Application;

use AffibloomLassoMigrator\Application\DisplayMapper;
use AffibloomLassoMigrator\Domain\Lasso\LassoSettings;
use AffibloomLassoMigrator\Domain\Migration\Action;
use AffibloomLassoMigrator\Domain\Migration\MigrationLine;
use AffibloomLassoMigrator\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

/**
 * F10 R20, D9, AC4 (Task 25B): Lasso's display keys per type, read as Lasso reads them.
 */
final class DisplayMapperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	/**
	 * The display lines, by key.
	 *
	 * @param array<string, mixed> $stored The stored option.
	 * @return array<string, MigrationLine>
	 */
	private static function lines( array $stored ): array {
		$lines = array();
		foreach ( ( new DisplayMapper() )->lines( new LassoSettings( $stored ) ) as $line ) {
			$lines[ $line->key ] = $line;
		}

		return $lines;
	}

	public function test_json_text_is_decoded_and_merged_over_lasso_s_defaults_per_type(): void {
		$lines = self::lines(
			array(
				'theme_name'     => '{"single":"Lab","grid":"Cutter"}',
				'display_layout' => array( 'columns' => '2' ),
			)
		);

		self::assertSame( 'single.theme = Lab', $lines['theme_name.single']->target );
		self::assertSame( 'grid.theme = Cutter', $lines['theme_name.grid']->target );
		self::assertSame( 'list.theme = Cactus', $lines['theme_name.list']->target );
		self::assertSame( 'columns = 2', $lines['display_layout.columns']->target );
		self::assertStringContainsString( '"orientation":"stacked"', $lines['display_layout.grid']->target );
	}

	public function test_flat_colours_fill_single_only_when_display_colors_was_never_stored(): void {
		$flat = self::lines( array( 'display_color_main' => '#111111' ) );
		self::assertStringContainsString( '"main":"#111111"', $flat['display_colors.single']->target );
		self::assertStringContainsString( '"main":"black"', $flat['display_colors.grid']->target );
		self::assertSame( Action::Skip, $flat['display_color_main']->action );

		$stored = self::lines(
			array(
				'display_color_main' => '#111111',
				'display_colors'     => array( 'single' => array( 'main' => '#222222' ) ),
			)
		);
		self::assertStringContainsString( '"main":"#222222"', $stored['display_colors.single']->target );
	}

	public function test_disclosure_is_not_an_element_and_unchanged_advanced_customisation_makes_presets_custom(): void {
		$lines = self::lines(
			array(
				'display_elements'               => array( 'grid' => array( 'disclosure' => true ) ),
				'display_advanced_customization' => true,
			)
		);

		self::assertStringNotContainsString( 'disclosure', $lines['display_elements.grid']->target );
		self::assertStringContainsString( '"preset":"custom"', $lines['display_density.single']->target );
		self::assertSame( Action::Skip, $lines['display_advanced_customization']->action );
	}
}
