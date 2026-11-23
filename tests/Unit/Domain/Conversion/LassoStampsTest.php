<?php
namespace AffibloomLassoMigrator\Tests\Unit\Domain\Conversion;

use AffibloomLassoMigrator\Domain\Conversion\LassoStamps;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * Task LM6 (F34 AC6; F21 R3, R37): Lasso's stamps go, every other byte stays; the links Lasso tied to a record are
 * named. Fixtures from this site's guides (paths and long block settings shortened).
 */
final class LassoStampsTest extends TestCase {

	/** A GenerateBlocks button with an Amazon icon, tied by Lasso (post 9492). */
	private const BUTTON = "<!-- wp:generateblocks/button {\"uniqueId\":\"4755f6cd\",\"hasUrl\":true,\"relNoFollow\":true,\"borderTopColor\":\"var(\\u002d\\u002dcontrast-2)\"} -->\n"
		. '<a class="gb-button gb-button-4755f6cd" href="https://www.amazon.com/dp/B0F5BNQLFZ/?tag=vbvault-20" target="_blank" rel="nofollow noopener" data-lasso-name="ASICS Sky Elite FF 3" data-lasso-id="7411" data-lasso-lid="9701"><span class="gb-icon"><svg xmlns="https://www.w3.org/2000/svg" width="24" height="24" viewbox="0 0 24 24"><path d="M13.958 10.09c0 1.232z" fill-rule="evenodd"></path></svg></span><span class="gb-button-text"><strong>Check On Amazon</strong></span></a>'
		. "\n<!-- /wp:generateblocks/button -->";

	/** A reusable block's link to a guide (wp_block 2333). */
	private const REUSABLE = "<!-- wp:paragraph -->\n<p>Read <a href=\"http://volleyballvault.local/volleyball-drills-training/\" data-lasso-id=\"8284\">our drills</a>.</p>\n<!-- /wp:paragraph -->";

	/** An inline SVG in a button to a page (post 2270). */
	private const SVG = '<a class="gb-button gb-button-e047e22e" href="/about/" rel="noopener noreferrer" data-lasso-id="3"><span class="gb-icon"><svg aria-hidden="true" role="img" height="1em" width="1em" viewbox="0 0 256 512" xmlns="https://www.w3.org/2000/svg"><path fill="currentColor" d="M224.3 273l-136 136z"></path></svg></span><span class="gb-button-text">Learn more about me...</span></a>';

	public function test_a_button_loses_its_stamps_and_names_its_tie(): void {
		$cleaned = LassoStamps::clean( self::BUTTON );

		self::assertSame( str_replace( ' data-lasso-name="ASICS Sky Elite FF 3" data-lasso-id="7411" data-lasso-lid="9701"', '', self::BUTTON ), $cleaned['text'] );
		self::assertSame(
			array(
				array(
					'url'   => 'https://www.amazon.com/dp/B0F5BNQLFZ/?tag=vbvault-20',
					'lasso' => 9701,
					'name'  => 'ASICS Sky Elite FF 3',
				),
			),
			$cleaned['ties']
		);
		self::assertSame( array( 1, 1, 1, 0, 0 ), array_values( $cleaned['counts'] ) );
	}

	public function test_a_reusable_block_and_an_svg_keep_every_other_byte(): void {
		$reusable = LassoStamps::clean( self::REUSABLE );
		$svg      = LassoStamps::clean( self::SVG );

		self::assertSame( str_replace( ' data-lasso-id="8284"', '', self::REUSABLE ), $reusable['text'] );
		self::assertSame( str_replace( ' data-lasso-id="3"', '', self::SVG ), $svg['text'] );
		self::assertSame( array(), $svg['ties'] );
		self::assertSame( 1, $svg['counts'][ LassoStamps::ID ] );
	}

	/** A Rank Math FAQ keeps its answer as HTML in its JSON (post 3893); both forms of the stamp go. */
	public function test_stamps_in_a_block_s_json_go_in_each_quoting(): void {
		$json = '<!-- wp:rank-math/faq-block {"questions":[{"content":"See our <a data-lasso-id="15036" href="http://volleyballvault.local/best-shoes/" data-lasso-lid="17" data-lasso-name="Tom &amp; Co">guide</a>"}]} -->'
			. '<div><a data-lasso-id="15036" href="http://volleyballvault.local/best-shoes/">guide</a></div>'
			. '<!-- wp:x {"html":"<a href=\"https://x.example/\" data-lasso-id=\"5\">x</a>"} /-->';

		$cleaned = LassoStamps::clean( $json );

		self::assertSame(
			'<!-- wp:rank-math/faq-block {"questions":[{"content":"See our <a href="http://volleyballvault.local/best-shoes/">guide</a>"}]} -->'
			. '<div><a href="http://volleyballvault.local/best-shoes/">guide</a></div>'
			. '<!-- wp:x {"html":"<a href=\"https://x.example/\">x</a>"} /-->',
			$cleaned['text']
		);
		self::assertSame(
			array(
				array(
					'url'   => 'http://volleyballvault.local/best-shoes/',
					'lasso' => 17,
					'name'  => 'Tom & Co',
				),
			),
			$cleaned['ties']
		);
		self::assertSame( 3, $cleaned['counts'][ LassoStamps::ID ] );
	}

	public function test_lasso_s_classes_go_or_become_affibloom_s(): void {
		$text = '<!-- wp:aawp/box {"className":"lasso-ll-do-not-delete-12"} /--><div class="aawp lasso-ll-do-not-delete-12"></div>'
			. '<p class="lasso-ll-do-not-delete-3 note">x</p><img src="a.gif" class="1 lasso-scan-ignore"><a class="my-lasso-scan-ignore-x">y</a>';

		$cleaned = LassoStamps::clean( $text );

		self::assertSame(
			'<!-- wp:aawp/box {"className":""} /--><div class="aawp"></div><p class="note">x</p><img src="a.gif" class="1 affibloom-scan-ignore"><a class="my-lasso-scan-ignore-x">y</a>',
			$cleaned['text']
		);
		self::assertSame( 3, $cleaned['counts'][ LassoStamps::KEEP_CLASS ] );
		self::assertSame( 1, $cleaned['counts'][ LassoStamps::IGNORE ] );
	}

	public function test_a_text_without_stamps_is_unchanged(): void {
		$text = '<p>The data-lasso-id="1" attribute, in prose.</p>';

		self::assertSame( $text, LassoStamps::clean( $text )['text'] );
	}
}
