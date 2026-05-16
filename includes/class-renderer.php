<?php
/**
 * Server-side rendering for the WalkMe Posts blocks.
 *
 * The same render functions are used by both the initial Gutenberg
 * server-side render AND the REST endpoint (so AJAX updates produce
 * identical HTML — no client-side template drift).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Walkme_PB_Renderer {

	/**
	 * Render the Posts Grid block.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner blocks content (pagination).
	 * @param WP_Block $block      Block instance (may be null in REST context).
	 * @return string
	 */
	public static function render_grid( $attributes, $content = '', $block = null ) {
		$columns       = walkme_pb_clamp_columns( $attributes['columns'] ?? 3 );
		$per_page      = max( 1, absint( $attributes['postsPerPage'] ?? 6 ) );
		$query_id      = sanitize_key( $attributes['queryId'] ?? '' );
		$current_page  = max( 1, absint( $attributes['currentPage'] ?? 1 ) );
		$category_ids  = walkme_pb_parse_term_ids( $attributes['categories'] ?? array() );
		$tag_ids       = walkme_pb_parse_term_ids( $attributes['tags'] ?? array() );

		if ( ! $query_id ) {
			// Editor preview without a saved queryId — synthesize one so attrs travel.
			$query_id = 'preview-' . wp_generate_uuid4();
		}

		$query_args = array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $current_page,
			'ignore_sticky_posts' => true,
		);

		$tax_query = array();
		if ( ! empty( $category_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'category',
				'field'    => 'term_id',
				'terms'    => $category_ids,
				'operator' => 'IN',
			);
		}
		if ( ! empty( $tag_ids ) ) {
			$tax_query[] = array(
				'taxonomy' => 'post_tag',
				'field'    => 'term_id',
				'terms'    => $tag_ids,
				'operator' => 'IN',
			);
		}
		if ( ! empty( $tax_query ) ) {
			$tax_query['relation'] = 'AND';
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$query = new WP_Query( $query_args );

		// Inner blocks (pagination) — only rendered on initial SSR.
		// On REST refresh we render pagination ourselves from the query result.
		$pagination_html = self::render_pagination_html( $query, $current_page );

		$items_html = self::render_grid_items( $query );

		wp_reset_postdata();

		$wrapper_attrs = sprintf(
			'class="walkme-posts-grid walkme-posts-grid--cols-%1$d" data-walkme-grid="1" data-query-id="%2$s" data-columns="%1$d" data-per-page="%3$d" data-current-page="%4$d" data-total-pages="%5$d" data-categories="%6$s" data-tags="%7$s"',
			$columns,
			esc_attr( $query_id ),
			$per_page,
			$current_page,
			max( 1, (int) $query->max_num_pages ),
			esc_attr( implode( ',', $category_ids ) ),
			esc_attr( implode( ',', $tag_ids ) )
		);

		// Items + pagination. We render pagination as part of the grid so REST
		// can return one HTML chunk for the whole "results region".
		return sprintf(
			'<div %1$s><div class="walkme-posts-grid__items">%2$s</div><div class="walkme-posts-grid__pagination">%3$s</div></div>',
			$wrapper_attrs,
			$items_html,
			$pagination_html
		);
	}

	/**
	 * Inner block (pagination) — placeholder for editor save; not rendered standalone
	 * on the frontend, since render_grid embeds pagination itself.
	 */
	public static function render_pagination_placeholder( $attributes, $content = '', $block = null ) {
		// On the frontend, the grid renders pagination directly. The inner block
		// exists as a UX affordance in the editor and as a structural marker.
		return '';
	}

	/**
	 * Render the Posts Filter block.
	 */
	public static function render_filter( $attributes, $content = '', $block = null ) {
		$target_query_id = sanitize_key( $attributes['targetQueryId'] ?? '' );

		$categories = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
			)
		);
		$tags = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
			)
		);

		ob_start();
		?>
		<div class="walkme-posts-filter" data-walkme-filter="1" data-target-query-id="<?php echo esc_attr( $target_query_id ); ?>">
			<fieldset class="walkme-posts-filter__group" data-filter-type="categories">
				<legend class="walkme-posts-filter__legend"><?php esc_html_e( 'Categories', 'walkme-posts-blocks' ); ?></legend>
				<ul class="walkme-posts-filter__list">
					<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
						<?php foreach ( $categories as $term ) : ?>
							<li>
								<label>
									<input type="checkbox" value="<?php echo esc_attr( $term->term_id ); ?>" data-filter-type="categories" />
									<span><?php echo esc_html( $term->name ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</fieldset>
			<fieldset class="walkme-posts-filter__group" data-filter-type="tags">
				<legend class="walkme-posts-filter__legend"><?php esc_html_e( 'Tags', 'walkme-posts-blocks' ); ?></legend>
				<ul class="walkme-posts-filter__list">
					<?php if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) : ?>
						<?php foreach ( $tags as $term ) : ?>
							<li>
								<label>
									<input type="checkbox" value="<?php echo esc_attr( $term->term_id ); ?>" data-filter-type="tags" />
									<span><?php echo esc_html( $term->name ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</fieldset>
			<button type="button" class="walkme-posts-filter__clear"><?php esc_html_e( 'Clear filters', 'walkme-posts-blocks' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the grid items list (used by both SSR and REST).
	 */
	protected static function render_grid_items( WP_Query $query ) {
		if ( ! $query->have_posts() ) {
			return '<p class="walkme-posts-grid__empty">' . esc_html__( 'No posts match your filters.', 'walkme-posts-blocks' ) . '</p>';
		}

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			$thumb = get_the_post_thumbnail( get_the_ID(), 'medium_large', array( 'class' => 'walkme-posts-grid__thumb' ) );
			$placeholder = '';
			if ( ! $thumb ) {
				$placeholder = self::placeholder_thumbnail( get_the_title(), get_the_ID() );
			}
			?>
			<article class="walkme-posts-grid__item">
				<a class="walkme-posts-grid__link" href="<?php the_permalink(); ?>">
					<div class="walkme-posts-grid__media">
						<?php
						if ( $thumb ) {
							echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						} else {
							echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</div>
					<h3 class="walkme-posts-grid__title"><?php the_title(); ?></h3>
					<div class="walkme-posts-grid__excerpt"><?php echo wp_kses_post( get_the_excerpt() ); ?></div>
				</a>
			</article>
			<?php
		}
		return ob_get_clean();
	}

	/**
	 * Render an inline SVG placeholder for posts that have no featured image.
	 * Deterministic — same post always gets the same color + initials.
	 */
	protected static function placeholder_thumbnail( $title, $post_id ) {
		$palette = array(
			'#1e3a8a', '#9333ea', '#0f766e', '#b91c1c',
			'#ca8a04', '#0369a1', '#be185d', '#15803d',
			'#7c2d12', '#4338ca', '#0e7490', '#a16207',
		);
		$bg     = $palette[ absint( $post_id ) % count( $palette ) ];
		$letters = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $title ), 0, 2 ) );
		if ( '' === $letters ) {
			$letters = '?';
		}

		return sprintf(
			'<svg class="walkme-posts-grid__thumb walkme-posts-grid__thumb--placeholder" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400" role="img" aria-label="%3$s"><rect width="600" height="400" fill="%1$s"/><text x="50%%" y="50%%" font-family="Helvetica, Arial, sans-serif" font-size="160" font-weight="700" fill="rgba(255,255,255,0.92)" text-anchor="middle" dominant-baseline="central">%2$s</text></svg>',
			esc_attr( $bg ),
			esc_html( $letters ),
			esc_attr( $title )
		);
	}

	/**
	 * Render the pagination block (numbered).
	 */
	protected static function render_pagination_html( WP_Query $query, $current_page ) {
		$total = max( 1, (int) $query->max_num_pages );
		if ( $total <= 1 ) {
			return '';
		}

		ob_start();
		?>
		<nav class="walkme-posts-pagination" data-walkme-pagination="1" aria-label="<?php esc_attr_e( 'Posts pagination', 'walkme-posts-blocks' ); ?>">
			<button type="button" class="walkme-posts-pagination__btn" data-direction="prev" <?php disabled( $current_page <= 1 ); ?>>
				<?php esc_html_e( 'Previous', 'walkme-posts-blocks' ); ?>
			</button>
			<ul class="walkme-posts-pagination__pages">
				<?php for ( $i = 1; $i <= $total; $i++ ) : ?>
					<li>
						<button type="button" class="walkme-posts-pagination__page<?php echo $i === (int) $current_page ? ' is-current' : ''; ?>" data-page="<?php echo esc_attr( $i ); ?>" <?php echo $i === (int) $current_page ? 'aria-current="page"' : ''; ?>>
							<?php echo esc_html( (string) $i ); ?>
						</button>
					</li>
				<?php endfor; ?>
			</ul>
			<button type="button" class="walkme-posts-pagination__btn" data-direction="next" <?php disabled( $current_page >= $total ); ?>>
				<?php esc_html_e( 'Next', 'walkme-posts-blocks' ); ?>
			</button>
		</nav>
		<?php
		return ob_get_clean();
	}
}
