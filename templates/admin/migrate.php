<?php
/**
 * Migrate from Lasso (F10 D13, D18, AC6; Task 25D): the dry run's report. A summary card with "Download report
 * (CSV)", a card per family in the migration's order (its numbers, or what the site holds while its task is to come,
 * and any error lines), then every kind of Lasso's data with its verdict. Affibloom's Meadow classes and tokens;
 * assets/admin/migrate.css adds the grid, the numbers and the verdict pills.
 *
 * @package AffibloomLassoMigrator
 * @var array{lasso_version: string, records: int, download: string, cards: list<array{family: \AffibloomLassoMigrator\Domain\Migration\Family, waiting: string, stats: list<array{0: string, 1: int, 2: bool}>, errors: list<\AffibloomLassoMigrator\Domain\Migration\MigrationLine>}>, data: list<\AffibloomLassoMigrator\Domain\Migration\DataItem>, left: list<\AffibloomLassoMigrator\Domain\Migration\DataItem>} $view
 */

defined( 'ABSPATH' ) || exit;

$family_names = array(
	'settings'      => __( 'Settings', 'affibloom-lasso-migrator' ),
	'display'       => __( 'Display', 'affibloom-lasso-migrator' ),
	'fields'        => __( 'Fields', 'affibloom-lasso-migrator' ),
	'products'      => __( 'Products', 'affibloom-lasso-migrator' ),
	'groups'        => __( 'Groups', 'affibloom-lasso-migrator' ),
	'slugs'         => __( 'Cloaked addresses', 'affibloom-lasso-migrator' ),
	'variants'      => __( 'URL variants', 'affibloom-lasso-migrator' ),
	'tables'        => __( 'Comparison tables', 'affibloom-lasso-migrator' ),
	'issues'        => __( 'Link alerts', 'affibloom-lasso-migrator' ),
	'amazon'        => __( 'Amazon', 'affibloom-lasso-migrator' ),
	'views'         => __( 'Saved views', 'affibloom-lasso-migrator' ),
	'opportunities' => __( 'Opportunities', 'affibloom-lasso-migrator' ),
	'locations'     => __( 'Original links', 'affibloom-lasso-migrator' ),
);
$left_rows    = array_sum( array_map( static fn ( $item ): int => $item->rows, $view['left'] ) );
?>
<div class="affibloom-migrate">
	<div class="affibloom-card affibloom-migrate__intro">
		<div class="affibloom-migrate__intro-text">
			<p class="affibloom-migrate__intro-title"><?php esc_html_e( 'Dry run: nothing has been changed', 'affibloom-lasso-migrator' ); ?></p>
			<p class="affibloom-migrate__intro-line">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Lasso's version, such as 370; 2: the number of Lasso records. */
						__( 'Read from Lasso %1$s just now: %2$s records. This is what moving to Affibloom will do; reload the page to read again.', 'affibloom-lasso-migrator' ),
						'' === $view['lasso_version'] ? '?' : $view['lasso_version'],
						number_format_i18n( $view['records'] )
					)
				);
				?>
			</p>
		</div>
		<a class="affibloom-button affibloom-button--secondary" href="<?php echo esc_url( $view['download'] ); ?>"><?php esc_html_e( 'Download report (CSV)', 'affibloom-lasso-migrator' ); ?></a>
	</div>

	<?php // Task 26O: the run, one family per request, behind the backup tick. ?>
	<div
		class="affibloom-card affibloom-migrate__run"
		data-affibloom-migrate-run="
		<?php
		echo esc_attr(
			(string) wp_json_encode(
				array(
					'url'   => $view['run_url'],
					'nonce' => $view['nonce'],
					'names' => $family_names,
					'texts' => array(
						/* translators: 1: a family of data, such as "Products"; 2: how many families have run; 3: how many there are. */
						'progress' => __( '%1$s done: %2$d of %3$d', 'affibloom-lasso-migrator' ),
						'done'     => __( 'Migration finished. Reading the report again…', 'affibloom-lasso-migrator' ),
						'failed'   => __( 'The run stopped. Press the button again to continue where it stopped.', 'affibloom-lasso-migrator' ),
					),
				)
			)
		);
		?>
		"
	>
		<div class="affibloom-migrate__intro-text">
			<p class="affibloom-migrate__intro-title"><?php esc_html_e( 'Run the migration', 'affibloom-lasso-migrator' ); ?></p>
			<p class="affibloom-migrate__intro-line"><?php esc_html_e( "Lasso's data is only read. Affibloom's is written, so take a backup of the database first.", 'affibloom-lasso-migrator' ); ?></p>
			<label class="affibloom-migrate__backup">
				<input type="checkbox" data-affibloom-migrate-backup>
				<?php esc_html_e( 'I have a backup of this site’s database.', 'affibloom-lasso-migrator' ); ?>
			</label>
			<p class="affibloom-migrate__status" data-affibloom-migrate-status aria-live="polite"></p>
		</div>
		<div class="affibloom-migrate__run-actions">
			<?php if ( $view['ran'] ) : ?>
				<button type="button" class="affibloom-button affibloom-button--secondary" data-affibloom-migrate-start="update" disabled><?php esc_html_e( 'Update from Lasso', 'affibloom-lasso-migrator' ); ?></button>
			<?php endif; ?>
			<button type="button" class="affibloom-button affibloom-button--primary" data-affibloom-migrate-start="run" disabled><?php esc_html_e( 'Run migration', 'affibloom-lasso-migrator' ); ?></button>
		</div>
	</div>

	<div class="affibloom-migrate__grid">
		<?php foreach ( $view['cards'] as $card ) : ?>
			<?php
			$has_errors = array() !== $card['errors'];
			$state      = $has_errors ? 'errors' : ( '' === $card['waiting'] ? 'ready' : 'waiting' );
			$state_text = array(
				'errors'  => __( 'Needs a look', 'affibloom-lasso-migrator' ),
				'ready'   => __( 'Ready', 'affibloom-lasso-migrator' ),
				/* translators: %s: the task that will move this family, such as "Task 26F". */
				'waiting' => sprintf( __( 'Comes with %s', 'affibloom-lasso-migrator' ), $card['waiting'] ),
			);
			?>
			<section class="affibloom-card affibloom-migrate__family" aria-labelledby="affibloom-migrate-<?php echo esc_attr( $card['family']->value ); ?>">
				<div class="affibloom-migrate__family-head">
					<h2 class="affibloom-card__title" id="affibloom-migrate-<?php echo esc_attr( $card['family']->value ); ?>"><?php echo esc_html( $family_names[ $card['family']->value ] ?? $card['family']->value ); ?></h2>
					<span class="affibloom-migrate__state affibloom-migrate__state--<?php echo esc_attr( $state ); ?>"><?php echo esc_html( $state_text[ $state ] ); ?></span>
				</div>
				<?php if ( '' !== $card['waiting'] ) : ?>
					<p class="affibloom-migrate__found"><?php esc_html_e( 'Found on this site:', 'affibloom-lasso-migrator' ); ?></p>
				<?php endif; ?>
				<dl class="affibloom-migrate__stats">
					<?php foreach ( $card['stats'] as $stat ) : ?>
						<div class="affibloom-migrate__stat<?php echo $stat[2] ? ' affibloom-migrate__stat--total' : ''; ?><?php echo 0 === $stat[1] ? ' affibloom-migrate__stat--zero' : ''; ?>">
							<dt><?php echo esc_html( $stat[0] ); ?></dt>
							<dd><?php echo esc_html( number_format_i18n( $stat[1] ) ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
				<?php if ( $has_errors ) : ?>
					<ul class="affibloom-migrate__errors">
						<?php foreach ( array_slice( $card['errors'], 0, 10 ) as $line ) : ?>
							<li><strong><?php echo esc_html( '' === $line->name ? $line->key : $line->name ); ?></strong> <?php echo esc_html( $line->note ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endforeach; ?>
	</div>

	<?php if ( array() !== $view['data'] ) : ?>
		<div class="affibloom-card affibloom-card--list affibloom-migrate__data">
			<div class="affibloom-card__head">
				<h2 class="affibloom-card__title"><?php esc_html_e( "Lasso's data, and where it goes", 'affibloom-lasso-migrator' ); ?></h2>
				<p class="affibloom-card__intro">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: kinds of data left behind; 2: all kinds of Lasso data; 3: rows left behind. */
							__( 'Left behind: %1$s of %2$s kinds of Lasso data (%3$s rows). Lasso keeps its own copy until it is deleted.', 'affibloom-lasso-migrator' ),
							number_format_i18n( count( $view['left'] ) ),
							number_format_i18n( count( $view['data'] ) ),
							number_format_i18n( $left_rows )
						)
					);
					?>
				</p>
			</div>
			<div class="affibloom-migrate__table-wrap">
				<table class="affibloom-table affibloom-migrate__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Data', 'affibloom-lasso-migrator' ); ?></th>
							<th scope="col" class="affibloom-migrate__rows"><?php esc_html_e( 'Rows', 'affibloom-lasso-migrator' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Where it goes', 'affibloom-lasso-migrator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $view['data'] as $item ) : ?>
							<tr>
								<td><code><?php echo esc_html( $item->name ); ?></code></td>
								<td class="affibloom-migrate__rows"><?php echo esc_html( number_format_i18n( $item->rows ) ); ?></td>
								<td>
									<?php if ( null !== $item->family ) : ?>
										<span class="affibloom-migrate__state affibloom-migrate__state--ready"><?php echo esc_html( $family_names[ $item->family->value ] ?? $item->family->value ); ?></span>
									<?php else : ?>
										<span class="affibloom-migrate__state affibloom-migrate__state--<?php echo $item->known ? 'left' : 'errors'; ?>"><?php echo $item->known ? esc_html__( 'Left', 'affibloom-lasso-migrator' ) : esc_html__( 'Unknown', 'affibloom-lasso-migrator' ); ?></span>
										<span class="affibloom-migrate__reason"><?php echo esc_html( $item->reason ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php endif; ?>
</div>
