<?php
namespace AffibloomLassoMigrator\Tests\Unit\Infrastructure\WordPress;

use AffibloomLassoMigrator\Infrastructure\WordPress\PostTexts;
use AffibloomLassoMigrator\Tests\Unit\TestCase;

/**
 * A conversion or a revert keeps the post's "Updated on" date (Tasks LM3-LM6A; the staging rehearsal's finding S2).
 */
final class PostTextsTest extends TestCase {

	/** The row wp_update_post() would store now. */
	private const ROW = array(
		'post_content'      => 'converted',
		'post_modified'     => '2026-11-24 21:56:04',
		'post_modified_gmt' => '2026-11-24 14:56:04',
	);

	public function test_the_written_post_keeps_its_modified_date(): void {
		$row = PostTexts::keep_modified( self::ROW, array( 'ID' => 4583 ), 4583, '2026-02-21 18:41:15', '2026-02-21 11:41:15' );

		self::assertSame( '2026-02-21 18:41:15', $row['post_modified'] );
		self::assertSame( '2026-02-21 11:41:15', $row['post_modified_gmt'] );
		self::assertSame( 'converted', $row['post_content'] );
	}

	public function test_another_row_such_as_the_revision_is_left_as_it_is(): void {
		self::assertSame( self::ROW, PostTexts::keep_modified( self::ROW, array( 'post_parent' => 4583 ), 4583, '2026-02-21 18:41:15', '2026-02-21 11:41:15' ) );
		self::assertSame( self::ROW, PostTexts::keep_modified( self::ROW, array( 'ID' => 12 ), 4583, '2026-02-21 18:41:15', '2026-02-21 11:41:15' ) );
	}

	public function test_a_post_with_no_date_takes_wordpress_s(): void {
		self::assertSame( self::ROW, PostTexts::keep_modified( self::ROW, array( 'ID' => 4583 ), 4583, '', '' ) );
	}
}
