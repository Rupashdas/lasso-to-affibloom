<?php
/**
 * Every kind of Lasso's data and its verdict.
 *
 * @package AffibloomLassoMigrator
 */

declare( strict_types=1 );

namespace AffibloomLassoMigrator\Application;

use AffibloomLassoMigrator\Domain\Lasso\LassoInventory;
use AffibloomLassoMigrator\Domain\Lasso\LassoSource;
use AffibloomLassoMigrator\Domain\Migration\DataItem;
use AffibloomLassoMigrator\Domain\Migration\Family;

/**
 * Every Lasso table and option group with its verdict (F10 analysis tables A, G, H; D15; AC5): the family that moves
 * it, or why nothing of it is copied. A Lasso table on the site that is not listed is an error, so a newer Lasso's
 * data is never dropped unseen.
 */
final class LeftBehind {

	/** Lasso's tables (without the prefix) → the family that moves them, or why they are left. */
	public const TABLES = array(
		'lasso_url_details'              => Family::Products,
		'lasso_amazon_products'          => Family::Amazon,
		'lasso_extend_products'          => Family::Amazon,
		'lasso_amazon_tracking_ids'      => Family::Amazon,
		'lasso_fields'                   => Family::Fields,
		'lasso_field_mapping'            => Family::Fields,
		'lasso_metadata'                 => Family::Fields,
		'lasso_category_order'           => Family::Groups,
		'lasso_url_variants'             => Family::Variants,
		'lasso_table_details'            => Family::Tables,
		'lasso_table_mapping'            => Family::Tables,
		'lasso_table_field_group'        => Family::Tables,
		'lasso_table_field_group_detail' => Family::Tables,
		'lasso_url_issues'               => Family::Issues,
		'lasso_link_updates'             => Family::Opportunities,
		'lasso_link_locations'           => Family::Opportunities,
		'lasso_table_title_field'        => 'dropped by Lasso at version 298; its data is in lasso_table_field_group_detail',
		'lasso_url_issue_definitions'    => "Lasso's fixed issue texts; Affibloom has its own",
		'lasso_amazon_shortened_url'     => 'a cache: Affibloom resolves short links itself (D15)',
		'lasso_amazon_affiliate_plus'    => "Lasso's cloud (commission upgrades)",
		'lasso_link_locations_map'       => "rebuilt by Affibloom's link index (D15)",
		'lasso_content'                  => 'rebuilt: Affibloom counts its own (D15)',
		'lasso_tracked_keywords'         => "keyword opportunities are left out (the user's decision)",
		'lasso_keyword_locations'        => "keyword opportunities are left out (the user's decision)",
		'lasso_auto_monetize'            => 'how Lasso detected links that are products already; Affibloom detects with its own rules',
		'lasso_revert'                   => "Lasso's import from other plugins, dropped with F33 (deviation 146)",
		'lasso_post_content_history'     => "Lasso's content history: Affibloom keeps none (F34 N1)",
		'lasso_affiliate_programs'       => "Lasso's cloud catalogue of affiliate programmes",
		'lasso_link_syncing'             => "Lasso's cloud sync",
		'lasso_background_process_runs'  => "Lasso's job log",
	);

	/** The other kinds of data (analysis B, C, E, G, H) → the family that moves them, or why they are left. */
	private const OTHERS = array(
		LassoInventory::RECORDS     => array( 'lasso-urls posts', Family::Products ),
		LassoInventory::OLD_SLUGS   => array( 'old slugs (post meta)', Family::Slugs ),
		LassoInventory::GROUPS      => array( 'lasso-cat terms', Family::Groups ),
		LassoInventory::SAVED_VIEWS => array( 'saved Dashboard views (user meta)', Family::Views ),
		LassoInventory::DISMISSED   => array( 'dismissed link notices (post meta)', Family::Issues ),
		LassoInventory::OPTIONS     => array( 'options lasso_*', 'versions, one-time flags, licence, cloud sync, notices and job state (analysis G); the settings and blacklist go with the settings family' ),
		LassoInventory::JOB_OPTIONS => array( 'options wp_lasso_*', "Lasso's job runner state" ),
		LassoInventory::TRANSIENTS  => array( 'transients lasso_*', 'caches and locks' ),
		LassoInventory::CLOUD_META  => array( 'hub and cloud post meta', "Lasso's hub and commission upgrades (analysis C K31-K32)" ),
		LassoInventory::USER_STATE  => array( 'tour and banner user meta', "Lasso's tour and banner state" ),
	);

	/**
	 * Builds the list.
	 *
	 * @param LassoSource $source Lasso's data.
	 */
	public function __construct( private LassoSource $source ) {}

	/**
	 * Every table on the site and every other kind of data, with its verdict.
	 *
	 * @return list<DataItem>
	 */
	public function items(): array {
		$inventory = $this->source->inventory();
		$items     = array();
		foreach ( $inventory->tables as $table => $rows ) {
			$verdict = self::TABLES[ $table ] ?? null;
			$items[] = match ( true ) {
				null === $verdict           => new DataItem( $table, $rows, null, 'a Lasso table the migrator does not know: check it by hand', false ),
				$verdict instanceof Family => new DataItem( $table, $rows, $verdict ),
				default                    => new DataItem( $table, $rows, null, $verdict ),
			};
		}
		foreach ( self::OTHERS as $count => $about ) {
			list( $name, $verdict ) = $about;
			$items[]                = $verdict instanceof Family
				? new DataItem( $name, $inventory->count( $count ), $verdict )
				: new DataItem( $name, $inventory->count( $count ), null, $verdict );
		}

		return $items;
	}

	/**
	 * What a family moves, with its counts on the site, for its line before its task is built.
	 *
	 * @param Family $family The family.
	 * @return list<DataItem>
	 */
	public function of( Family $family ): array {
		return array_values( array_filter( $this->items(), static fn ( DataItem $item ): bool => $item->family === $family ) );
	}
}
