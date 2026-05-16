<?php
/**
 * Demo content seeding on activation.
 *
 * - Idempotent: an option flag prevents re-seeding on reactivation.
 * - All seeded entities are tagged with `_wm_demo = 1` meta so
 *   uninstall.php can clean them up without affecting user content.
 * - Featured images are SVG attachments generated locally — no network
 *   dependency, deterministic across environments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wm_PB_Activator {

	const SEEDED_OPTION   = 'wm_pb_seeded';
	const DEMO_META_KEY   = '_wm_demo';
	const DEMO_PAGE_SLUG  = 'wm-posts-demo';

	public static function activate() {
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}

		$category_ids = self::seed_categories();
		$tag_ids      = self::seed_tags();
		$post_ids     = self::seed_posts( $category_ids, $tag_ids );
		self::seed_demo_page();

		update_option( self::SEEDED_OPTION, 1 );
	}

	protected static function seed_categories() {
		$names = array( 'Product News', 'Tutorials', 'Case Studies', 'Opinion' );
		$ids   = array();
		foreach ( $names as $name ) {
			$term = term_exists( $name, 'category' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'category' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				$ids[ $name ] = $term_id;
				update_term_meta( $term_id, self::DEMO_META_KEY, 1 );
			}
		}
		return $ids;
	}

	protected static function seed_tags() {
		$names = array( 'WordPress', 'React', 'PHP', 'JavaScript', 'CSS', 'Performance', 'Accessibility', 'DevOps' );
		$ids   = array();
		foreach ( $names as $name ) {
			$term = term_exists( $name, 'post_tag' );
			if ( ! $term ) {
				$term = wp_insert_term( $name, 'post_tag' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				$ids[ $name ] = $term_id;
				update_term_meta( $term_id, self::DEMO_META_KEY, 1 );
			}
		}
		return $ids;
	}

	protected static function seed_posts( $category_ids, $tag_ids ) {
		$cat_keys = array_keys( $category_ids );
		$tag_keys = array_keys( $tag_ids );

		$titles = array(
			'Getting Started with Gutenberg Blocks',
			'A Deep Dive into the Block Editor API',
			'Performance Tips for WordPress at Scale',
			'How We Migrated 1M Posts to a New Theme',
			'Accessibility in Modern WordPress Themes',
			'Building Dynamic Blocks with PHP and React',
			'A Case for the WordPress REST API in 2026',
			'Patterns vs. Templates: Choosing the Right Tool',
			'Why We Switched to wp-env for Local Development',
			'Editing the Site Editor: Tips for Customers',
			'Scaling WooCommerce During Black Friday',
			'Lessons Learned Shipping a Plugin to 100k Sites',
		);

		$excerpt = 'A short demo excerpt describing this post. Filters can be combined to show different subsets of these demo posts. ';

		$post_ids = array();
		foreach ( $titles as $i => $title ) {
			$existing = get_page_by_path( sanitize_title( $title ), OBJECT, 'post' );
			if ( $existing ) {
				continue;
			}

			// Deterministic-ish category/tag assignment for meaningful filter results.
			$picked_cats = array_slice(
				self::rotate( $cat_keys, $i ),
				0,
				1 + ( $i % 2 )
			);
			$picked_tags = array_slice(
				self::rotate( $tag_keys, $i * 3 ),
				0,
				2 + ( $i % 3 )
			);

			$post_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content' => "<p>{$excerpt}{$excerpt}</p><p>This is seeded content for the WM assessment.</p>",
					'post_excerpt' => $excerpt,
					'post_status'  => 'publish',
					'post_type'    => 'post',
					'post_author'  => 1,
					'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( "-{$i} days" ) ),
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, self::DEMO_META_KEY, 1 );

			wp_set_post_terms(
				$post_id,
				array_map(
					function ( $k ) use ( $category_ids ) {
						return $category_ids[ $k ];
					},
					$picked_cats
				),
				'category'
			);
			wp_set_post_terms(
				$post_id,
				array_map(
					function ( $k ) use ( $tag_ids ) {
						return $tag_ids[ $k ];
					},
					$picked_tags
				),
				'post_tag'
			);

			$attachment_id = self::create_svg_thumbnail( $title, $i );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}

			$post_ids[] = $post_id;
		}

		return $post_ids;
	}

	protected static function rotate( array $arr, $offset ) {
		if ( empty( $arr ) ) {
			return $arr;
		}
		$offset = $offset % count( $arr );
		return array_merge( array_slice( $arr, $offset ), array_slice( $arr, 0, $offset ) );
	}

	/**
	 * Generate a colored SVG and register it as an attachment. Used as
	 * featured image. Local-only, no network — safe for offline activation.
	 */
	protected static function create_svg_thumbnail( $title, $i ) {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return 0;
		}

		$palette = array(
			'#1e3a8a', '#9333ea', '#0f766e', '#b91c1c',
			'#ca8a04', '#0369a1', '#be185d', '#15803d',
			'#7c2d12', '#4338ca', '#0e7490', '#a16207',
		);
		$bg    = $palette[ $i % count( $palette ) ];
		$label = strtoupper( substr( preg_replace( '/[^A-Za-z0-9]/', '', $title ), 0, 2 ) );

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 400"><rect width="600" height="400" fill="%1$s"/><text x="50%%" y="50%%" font-family="Helvetica, Arial, sans-serif" font-size="160" font-weight="700" fill="rgba(255,255,255,0.9)" text-anchor="middle" dominant-baseline="central">%2$s</text></svg>',
			esc_attr( $bg ),
			esc_html( $label )
		);

		$filename = 'wm-demo-' . ( $i + 1 ) . '.svg';
		$path     = trailingslashit( $uploads['path'] ) . $filename;
		$url      = trailingslashit( $uploads['url'] ) . $filename;

		// Some installs disallow SVG MIME; we register it temporarily for our own attachment only.
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_mime' ) );

		if ( false === file_put_contents( $path, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			remove_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_mime' ) );
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'guid'           => $url,
				'post_mime_type' => 'image/svg+xml',
				'post_title'     => sanitize_text_field( $title ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$path
		);

		remove_filter( 'upload_mimes', array( __CLASS__, 'allow_svg_mime' ) );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		// SVGs need manual metadata — WP can't introspect them by default,
		// so we set explicit dimensions matching the viewBox.
		$meta = array(
			'width'    => 600,
			'height'   => 400,
			'file'     => _wp_relative_upload_path( $path ),
			'filesize' => filesize( $path ),
			'sizes'    => array(),
		);
		wp_update_attachment_metadata( $attachment_id, $meta );

		update_post_meta( $attachment_id, self::DEMO_META_KEY, 1 );

		return (int) $attachment_id;
	}

	public static function allow_svg_mime( $mimes ) {
		$mimes['svg'] = 'image/svg+xml';
		return $mimes;
	}

	/**
	 * Create a demo page with both blocks pre-placed.
	 */
	protected static function seed_demo_page() {
		$existing = get_page_by_path( self::DEMO_PAGE_SLUG, OBJECT, 'page' );
		if ( $existing ) {
			return;
		}

		$query_id = 'demo-' . substr( md5( 'wm-demo' ), 0, 8 );

		$content  = "<!-- wp:wm/posts-filter {\"targetQueryId\":\"{$query_id}\"} /-->\n";
		$content .= "<!-- wp:wm/posts-grid {\"queryId\":\"{$query_id}\",\"columns\":3,\"postsPerPage\":6} -->\n";
		$content .= "<!-- wp:wm/posts-pagination /-->\n";
		$content .= "<!-- /wp:wm/posts-grid -->";

		$page_id = wp_insert_post(
			array(
				'post_title'   => 'WM Posts Demo',
				'post_name'    => self::DEMO_PAGE_SLUG,
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => 1,
			),
			true
		);

		if ( ! is_wp_error( $page_id ) && $page_id ) {
			update_post_meta( $page_id, self::DEMO_META_KEY, 1 );
		}
	}
}
