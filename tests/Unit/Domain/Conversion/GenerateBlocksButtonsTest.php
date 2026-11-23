<?php
namespace AffibloomLassoMigrator\Tests\Unit\Domain\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\GenerateBlocksButtons;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM6A (deviation 160): a GenerateBlocks button Lasso left with settings and HTML that disagree is written as
 * GenerateBlocks saves it, keeping what readers get; a button that agrees is not touched. Fixtures from this site's
 * guides (icons and long settings shortened).
 */
final class GenerateBlocksButtonsTest extends TestCase {

	private static function button( string $json, string $a ): string {
		return "<!-- wp:generateblocks/button {$json} -->\n{$a}<span class=\"gb-button-text\">Check On Amazon</span></a>\n<!-- /wp:generateblocks/button -->";
	}

	/** Post 2622: Lasso dropped `"target":true` and `noreferrer`. */
	public function test_a_new_tab_button_gets_its_target_setting_and_noreferrer(): void {
		$before = self::button( '{"uniqueId":"d1068435","hasUrl":true,"relNoFollow":true,"borderTopColor":"var(\u002d\u002dcontrast-2)"}', '<a class="gb-button gb-button-d1068435" href="https://www.amazon.com/dp/B0F5BNQLFZ/?tag=vbvault-20" target="_blank" rel="nofollow noopener">' );

		$repaired = GenerateBlocksButtons::repair( 'Before ' . $before . ' after' );

		self::assertSame( 'Before ' . self::button( '{"uniqueId":"d1068435","hasUrl":true,"relNoFollow":true,"borderTopColor":"var(\u002d\u002dcontrast-2)","target":true}', '<a class="gb-button gb-button-d1068435" href="https://www.amazon.com/dp/B0F5BNQLFZ/?tag=vbvault-20" target="_blank" rel="nofollow noopener noreferrer">' ) . ' after', $repaired['text'] );
		self::assertSame( 1, $repaired['repaired'] );
	}

	/** Lasso's rel wins over the block's old settings: readers keep nofollow, sponsored, or none. */
	public function test_the_settings_follow_the_html_s_rel(): void {
		$sponsored = self::button( '{"uniqueId":"a1","relNoFollow":true}', '<a class="gb-button gb-button-a1" href="https://x.example/" target="_blank" rel="nofollow sponsored noopener">' );
		$followed  = self::button( '{"uniqueId":"a2","relNoFollow":true}', '<a class="gb-button gb-button-a2" href="https://x.example/" target="_blank" rel="noopener">' );

		self::assertSame(
			self::button( '{"uniqueId":"a1","relNoFollow":true,"target":true,"relSponsored":true}', '<a class="gb-button gb-button-a1" href="https://x.example/" target="_blank" rel="nofollow noopener noreferrer sponsored">' )
			. self::button( '{"uniqueId":"a2","target":true}', '<a class="gb-button gb-button-a2" href="https://x.example/" target="_blank" rel="noopener noreferrer">' ),
			GenerateBlocksButtons::repair( $sponsored . $followed )['text']
		);
	}

	/** Home: a button to a page in the same tab saves no rel. */
	public function test_a_same_tab_button_loses_a_stray_rel(): void {
		$text = self::button( '{"uniqueId":"e047e22e","hasUrl":true}', '<a class="gb-button gb-button-e047e22e" href="/about/" rel="noopener noreferrer">' );

		self::assertSame( self::button( '{"uniqueId":"e047e22e","hasUrl":true}', '<a class="gb-button gb-button-e047e22e" href="/about/">' ), GenerateBlocksButtons::repair( $text )['text'] );
	}

	public function test_a_button_that_agrees_or_has_no_link_is_not_touched(): void {
		$text = self::button( '{"uniqueId":"b1","relNoFollow":true,"target":true}', '<a class="gb-button gb-button-b1" href="https://x.example/" target="_blank" rel="nofollow noopener noreferrer">' )
			. self::button( '{"uniqueId":"b2","relNoFollow":false}', '<a class="gb-button gb-button-b2" href="/x/">' )
			. '<!-- wp:generateblocks/button {"uniqueId":"b3"} --><span class="gb-button gb-button-b3">No link</span><!-- /wp:generateblocks/button -->'
			. '<!-- wp:generateblocks/button {"uniqueId":"b4"} /-->';

		$repaired = GenerateBlocksButtons::repair( $text );

		self::assertSame( $text, $repaired['text'] );
		self::assertSame( 0, $repaired['repaired'] );
	}

	/** Settings WordPress would write differently (an empty object) are reported, not rewritten. */
	public function test_settings_that_would_not_be_written_back_exactly_are_reported(): void {
		$text = self::button( '{"uniqueId":"c1","typography":{}}', '<a class="gb-button gb-button-c1" href="https://x.example/" target="_blank" rel="noopener">' );

		$repaired = GenerateBlocksButtons::repair( $text );

		self::assertSame( $text, $repaired['text'] );
		self::assertSame( array( '{"uniqueId":"c1","typography":{}}' ), $repaired['skipped'] );
	}
}
