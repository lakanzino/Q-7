<?php
/**
 * Site-wide data collector for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects articles, scientists, terms, pages, images, sitemaps and robots.txt.
 */
class Collector {

	const BATCH_SIZE = 50;

	/**
	 * Meta keys collected for quantum_article.
	 *
	 * @var string[]
	 */
	private static $article_meta_keys = array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_schema_FAQPage',
		'_thumbnail_id',
		'_qpt_en',
		'_qpt_lead',
		'_qpt_nokat',
		'_qpedia_tags',
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_qpedia_seo_title',
	);

	/**
	 * Meta keys collected for quantum_scientist.
	 *
	 * @var string[]
	 */
	private static $scientist_meta_keys = array(
		'_scientist_en_name',
		'_scientist_fullname',
		'_scientist_born_died',
		'_scientist_birthplace',
		'_scientist_institutions',
		'_scientist_achievement',
		'_scientist_nobel',
		'_scientist_concepts',
		'_scientist_family',
		'_qpedia_seo_title',
		'_qpedia_meta_description',
		'_qpedia_focus_keyphrase',
		'_thumbnail_id',
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
	);

	/**
	 * Meta keys collected for pages.
	 *
	 * @var string[]
	 */
	private static $page_meta_keys = array(
		'rank_math_title',
		'rank_math_description',
		'rank_math_focus_keyword',
		'rank_math_schema_FAQPage',
		'_thumbnail_id',
		'_yoast_wpseo_title',
		'_yoast_wpseo_metadesc',
		'_qpedia_seo_title',
		'_qpedia_meta_description',
	);

	/**
	 * Singleton instance.
	 *
	 * @var Collector|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Collector
	 */
	public static function instance(): Collector {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hidden constructor.
	 */
	private function __construct() {}

	/**
	 * Module boot (no front-end hooks).
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Effective batch size from settings, falling back to BATCH_SIZE.
	 *
	 * @return int
	 */
	public function batch_size(): int {
		$settings = Core::get_settings();
		$size     = isset( $settings['batch_size'] ) ? (int) $settings['batch_size'] : self::BATCH_SIZE;
		return $size > 0 ? $size : self::BATCH_SIZE;
	}

	/**
	 * Count posts of a type/status with a prepared query.
	 *
	 * @param string $type   Post type.
	 * @param string $status Post status.
	 * @return int
	 */
	public function count_posts( string $type, string $status ): int {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
			$type,
			$status
		);

		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Full site collection. Cached via Core::set_scan_data().
	 *
	 * @return array
	 */
	public function collect_all(): array {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		$settings = Core::get_settings();

		$articles   = $this->collect_all_articles();
		$scientists = $this->collect_all_scientists();
		$terms      = $this->collect_all_terms();
		$pages      = $this->collect_all_pages();
		$images     = $this->collect_all_images();
		$sitemaps   = $this->collect_sitemaps();
		$robots     = $this->collect_robots();

		$data = array(
			'collected_at'     => current_time( 'mysql' ),
			'collected_at_gmt' => current_time( 'mysql', true ),
			'site'             => array(
				'url'      => Core::home_url(),
				'name'     => $settings['site_name'],
				'language' => 'fa-IR',
			),
			'counts'           => array(
				'articles'       => count( $articles ),
				'scientists'     => count( $scientists ),
				'pages'          => count( $pages ),
				'images'         => count( $images ),
				'terms_category' => isset( $terms['quantum_category'] ) ? count( $terms['quantum_category'] ) : 0,
				'terms_tag'      => isset( $terms['post_tag'] ) ? count( $terms['post_tag'] ) : 0,
			),
			'articles'         => $articles,
			'scientists'       => $scientists,
			'terms'            => $terms,
			'pages'            => $pages,
			'images'           => $images,
			'sitemaps'         => $sitemaps,
			'robots'           => $robots,
		);

		Core::set_scan_data( $data );

		return $data;
	}

	/**
	 * All published quantum_article rows via batches.
	 *
	 * @return array
	 */
	public function collect_all_articles(): array {
		return $this->collect_all_of_type( 'article' );
	}

	/**
	 * All published quantum_scientist rows via batches.
	 *
	 * @return array
	 */
	public function collect_all_scientists(): array {
		return $this->collect_all_of_type( 'scientist' );
	}

	/**
	 * All attachments via batches.
	 *
	 * @return array
	 */
	public function collect_all_images(): array {
		$all    = array();
		$offset = 0;
		$limit  = $this->batch_size();

		do {
			$batch  = $this->collect_images_batch( $offset, $limit );
			$all    = array_merge( $all, $batch );
			$offset += $limit;
		} while ( count( $batch ) === $limit );

		return $all;
	}

	/**
	 * Batch of published quantum_article posts.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array
	 */
	public function collect_articles_batch( int $offset, int $limit ): array {
		$rows = $this->query_posts_batch( 'quantum_article', 'publish', $offset, $limit );
		$out  = array();
		foreach ( $rows as $row ) {
			$item = $this->hydrate_article( $row );
			if ( null !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Batch of published quantum_scientist posts.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array
	 */
	public function collect_scientists_batch( int $offset, int $limit ): array {
		$rows = $this->query_posts_batch( 'quantum_scientist', 'publish', $offset, $limit );
		$out  = array();
		foreach ( $rows as $row ) {
			$item = $this->hydrate_scientist( $row );
			if ( null !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Batch of attachment posts.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Limit.
	 * @return array
	 */
	public function collect_images_batch( int $offset, int $limit ): array {
		global $wpdb;

		$offset = max( 0, $offset );
		$limit  = max( 1, $limit );

		$sql = $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_parent, guid, post_mime_type, post_date, post_modified
			FROM {$wpdb->posts}
			WHERE post_type = %s AND post_status IN (%s, %s)
			ORDER BY ID ASC
			LIMIT %d OFFSET %d",
			'attachment',
			'inherit',
			'publish',
			$limit,
			$offset
		);

		$rows = $wpdb->get_results( $sql );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[] = $this->hydrate_image( $row );
		}
		return $out;
	}

	/**
	 * Single article by ID (for reanalyze / metabox).
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function collect_article( int $post_id ): ?array {
		$row = $this->query_post_by_id( $post_id, 'quantum_article' );
		return $row ? $this->hydrate_article( $row ) : null;
	}

	/**
	 * Single scientist by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function collect_scientist( int $post_id ): ?array {
		$row = $this->query_post_by_id( $post_id, 'quantum_scientist' );
		return $row ? $this->hydrate_scientist( $row ) : null;
	}

	/**
	 * Single page by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function collect_page( int $post_id ): ?array {
		$row = $this->query_post_by_id( $post_id, 'page' );
		return $row ? $this->hydrate_page( $row ) : null;
	}

	/**
	 * Single image by attachment ID.
	 *
	 * @param int $post_id Attachment ID.
	 * @return array|null
	 */
	public function collect_image( int $post_id ): ?array {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_parent, guid, post_mime_type, post_date, post_modified
			FROM {$wpdb->posts}
			WHERE ID = %d AND post_type = %s
			LIMIT 1",
			$post_id,
			'attachment'
		);
		$row = $wpdb->get_row( $sql );
		return $row ? $this->hydrate_image( $row ) : null;
	}

	/**
	 * All quantum_category (including empty) and post_tag terms.
	 *
	 * @return array { quantum_category: [], post_tag: [] }
	 */
	public function collect_all_terms(): array {
		return array(
			'quantum_category' => $this->collect_taxonomy_terms( 'quantum_category' ),
			'post_tag'         => $this->collect_taxonomy_terms( 'post_tag' ),
		);
	}

	/**
	 * Single term payload.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array|null
	 */
	public function collect_term( int $term_id, string $taxonomy ): ?array {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		return $this->hydrate_term( $term );
	}

	/**
	 * All published pages.
	 *
	 * @return array
	 */
	public function collect_all_pages(): array {
		$rows = $this->query_posts_batch( 'page', 'publish', 0, 200 );
		$out  = array();
		foreach ( $rows as $row ) {
			$item = $this->hydrate_page( $row );
			if ( null !== $item ) {
				$out[] = $item;
			}
		}
		return $out;
	}

	/**
	 * Fetch and parse public sitemaps.
	 *
	 * @return array
	 */
	public function collect_sitemaps(): array {
		$home  = Core::home_url();
		$paths = array(
			'sitemap_index.xml',
			'wp-sitemap.xml',
			'quantum_category-sitemap.xml',
		);

		$out = array(
			'expected' => array(
				'articles'   => $this->count_posts( 'quantum_article', 'publish' ),
				'scientists' => $this->count_posts( 'quantum_scientist', 'publish' ),
				'pages'      => $this->count_posts( 'page', 'publish' ),
			),
			'files'    => array(),
		);

		foreach ( $paths as $path ) {
			$url      = $home . '/' . $path;
			$fetched  = $this->remote_get( $url );
			$locs     = array();
			$lastmods = array();

			if ( $fetched['ok'] && '' !== $fetched['body'] ) {
				if ( preg_match_all( '/<loc>\s*(.*?)\s*<\/loc>/is', $fetched['body'], $matches ) ) {
					foreach ( $matches[1] as $loc ) {
						$locs[] = html_entity_decode( trim( $loc ), ENT_QUOTES, 'UTF-8' );
					}
				}
				if ( preg_match_all( '/<lastmod>\s*(.*?)\s*<\/lastmod>/is', $fetched['body'], $lm ) ) {
					foreach ( $lm[1] as $value ) {
						$lastmods[] = trim( $value );
					}
				}
			}

			$out['files'][ $path ] = array(
				'url'           => $url,
				'ok'            => $fetched['ok'],
				'status'        => $fetched['status'],
				'error'         => $fetched['error'],
				'loc_count'     => count( $locs ),
				'locs'          => $locs,
				'lastmod_count' => count( $lastmods ),
				'redirected'    => $fetched['redirected'],
				'final_url'     => $fetched['final_url'],
			);
		}

		return $out;
	}

	/**
	 * Fetch and parse /robots.txt.
	 *
	 * @return array
	 */
	public function collect_robots(): array {
		$url     = Core::home_url() . '/robots.txt';
		$fetched = $this->remote_get( $url );
		$parsed  = $this->parse_robots( $fetched['body'] );

		return array(
			'url'        => $url,
			'ok'         => $fetched['ok'],
			'status'     => $fetched['status'],
			'error'      => $fetched['error'],
			'body'       => $fetched['body'],
			'sitemaps'   => $parsed['sitemaps'],
			'rules'      => $parsed['rules'],
			'user_agents'=> $parsed['user_agents'],
		);
	}

	/**
	 * Drain every batch of a content kind.
	 *
	 * @param string $kind article|scientist.
	 * @return array
	 */
	private function collect_all_of_type( string $kind ): array {
		$all    = array();
		$offset = 0;
		$limit  = $this->batch_size();

		do {
			if ( 'scientist' === $kind ) {
				$batch = $this->collect_scientists_batch( $offset, $limit );
			} else {
				$batch = $this->collect_articles_batch( $offset, $limit );
			}
			$all    = array_merge( $all, $batch );
			$offset += $limit;
		} while ( count( $batch ) === $limit );

		return $all;
	}

	/**
	 * Prepared SELECT of posts.
	 *
	 * @param string $type   Post type.
	 * @param string $status Status.
	 * @param int    $offset Offset.
	 * @param int    $limit  Limit.
	 * @return array
	 */
	private function query_posts_batch( string $type, string $status, int $offset, int $limit ): array {
		global $wpdb;

		$offset = max( 0, $offset );
		$limit  = max( 1, $limit );

		$sql = $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_content, post_excerpt, post_date, post_modified, post_status, post_type, post_parent
			FROM {$wpdb->posts}
			WHERE post_type = %s AND post_status = %s
			ORDER BY ID ASC
			LIMIT %d OFFSET %d",
			$type,
			$status,
			$limit,
			$offset
		);

		$rows = $wpdb->get_results( $sql );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Single post row of an expected type.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Post type.
	 * @return object|null
	 */
	private function query_post_by_id( int $post_id, string $type ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_content, post_excerpt, post_date, post_modified, post_status, post_type, post_parent
			FROM {$wpdb->posts}
			WHERE ID = %d AND post_type = %s
			LIMIT 1",
			$post_id,
			$type
		);

		$row = $wpdb->get_row( $sql );
		return $row ? $row : null;
	}

	/**
	 * Hydrate a quantum_article row.
	 *
	 * @param object $row DB row.
	 * @return array|null
	 */
	private function hydrate_article( $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$id      = (int) $row->ID;
		$content = (string) $row->post_content;
		$slug    = (string) $row->post_name;
		$metas   = $this->collect_metas( $id, self::$article_meta_keys );

		$categories = $this->post_terms( $id, 'quantum_category' );
		$tags       = $this->post_terms( $id, 'post_tag' );
		if ( empty( $tags ) && ! empty( $metas['_qpedia_tags'] ) ) {
			$tags = $this->tags_from_meta( $metas['_qpedia_tags'] );
		}

		$thumbnail = $this->thumbnail_payload( $id, isset( $metas['_thumbnail_id'] ) ? (int) $metas['_thumbnail_id'] : 0 );

		return array(
			'ID'             => $id,
			'type'           => 'quantum_article',
			'title'          => (string) $row->post_title,
			'slug'           => $slug,
			'permalink'      => Core::permalink_for( $slug, 'article' ),
			'content'        => $content,
			'excerpt'        => (string) $row->post_excerpt,
			'date'           => (string) $row->post_date,
			'modified'       => (string) $row->post_modified,
			'status'         => (string) $row->post_status,
			'metas'          => $metas,
			'categories'     => $categories,
			'tags'           => $tags,
			'thumbnail'      => $thumbnail,
			'word_count'     => Core::word_count( $content ),
			'internal_links' => Core::extract_internal_links( $content ),
			'headings'       => Core::extract_headings( $content ),
		);
	}

	/**
	 * Hydrate a quantum_scientist row.
	 *
	 * @param object $row DB row.
	 * @return array|null
	 */
	private function hydrate_scientist( $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$id      = (int) $row->ID;
		$content = (string) $row->post_content;
		$slug    = (string) $row->post_name;
		$metas   = $this->collect_metas( $id, self::$scientist_meta_keys );
		$thumbnail = $this->thumbnail_payload( $id, isset( $metas['_thumbnail_id'] ) ? (int) $metas['_thumbnail_id'] : 0 );

		return array(
			'ID'             => $id,
			'type'           => 'quantum_scientist',
			'title'          => (string) $row->post_title,
			'slug'           => $slug,
			'permalink'      => Core::permalink_for( $slug, 'scientist' ),
			'content'        => $content,
			'excerpt'        => (string) $row->post_excerpt,
			'date'           => (string) $row->post_date,
			'modified'       => (string) $row->post_modified,
			'status'         => (string) $row->post_status,
			'metas'          => $metas,
			'thumbnail'      => $thumbnail,
			'word_count'     => Core::word_count( $content ),
			'internal_links' => Core::extract_internal_links( $content ),
			'headings'       => Core::extract_headings( $content ),
		);
	}

	/**
	 * Hydrate a page row.
	 *
	 * @param object $row DB row.
	 * @return array|null
	 */
	private function hydrate_page( $row ): ?array {
		if ( ! is_object( $row ) ) {
			return null;
		}

		$id      = (int) $row->ID;
		$content = (string) $row->post_content;
		$slug    = (string) $row->post_name;
		$metas   = $this->collect_metas( $id, self::$page_meta_keys );
		$thumbnail = $this->thumbnail_payload( $id, isset( $metas['_thumbnail_id'] ) ? (int) $metas['_thumbnail_id'] : 0 );

		return array(
			'ID'             => $id,
			'type'           => 'page',
			'title'          => (string) $row->post_title,
			'slug'           => $slug,
			'permalink'      => Core::permalink_for( $slug, 'page' ),
			'content'        => $content,
			'excerpt'        => (string) $row->post_excerpt,
			'date'           => (string) $row->post_date,
			'modified'       => (string) $row->post_modified,
			'status'         => (string) $row->post_status,
			'metas'          => $metas,
			'thumbnail'      => $thumbnail,
			'word_count'     => Core::word_count( $content ),
			'internal_links' => Core::extract_internal_links( $content ),
			'headings'       => Core::extract_headings( $content ),
		);
	}

	/**
	 * Hydrate an attachment row.
	 *
	 * @param object $row DB row.
	 * @return array
	 */
	private function hydrate_image( $row ): array {
		$id       = (int) $row->ID;
		$alt      = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		$meta     = wp_get_attachment_metadata( $id );
		if ( ! is_array( $meta ) ) {
			$meta = array();
		}

		$file_path = get_attached_file( $id );
		$bytes     = 0;
		if ( is_string( $file_path ) && '' !== $file_path && file_exists( $file_path ) ) {
			$size = filesize( $file_path );
			$bytes = false === $size ? 0 : (int) $size;
		}
		if ( $bytes <= 0 && isset( $meta['filesize'] ) ) {
			$bytes = (int) $meta['filesize'];
		}

		$url = wp_get_attachment_url( $id );
		if ( ! is_string( $url ) || '' === $url ) {
			$url = (string) $row->guid;
		}

		$mime = (string) $row->post_mime_type;

		return array(
			'ID'          => $id,
			'title'       => (string) $row->post_title,
			'slug'        => (string) $row->post_name,
			'url'         => $url,
			'file'        => is_string( $file_path ) ? $file_path : '',
			'mime'        => $mime,
			'attached_to' => (int) $row->post_parent,
			'alt'         => $alt,
			'width'       => isset( $meta['width'] ) ? (int) $meta['width'] : 0,
			'height'      => isset( $meta['height'] ) ? (int) $meta['height'] : 0,
			'filesize'    => $bytes,
			'is_webp'     => ( false !== strpos( $mime, 'webp' ) || ( is_string( $file_path ) && (bool) preg_match( '/\.webp$/i', $file_path ) ) ),
			'date'        => (string) $row->post_date,
			'modified'    => (string) $row->post_modified,
			'metadata'    => $meta,
		);
	}

	/**
	 * Terms of a taxonomy (including empty).
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	private function collect_taxonomy_terms( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 0,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = $this->hydrate_term( $term );
		}
		return $out;
	}

	/**
	 * Normalize a WP_Term.
	 *
	 * @param \WP_Term $term Term object.
	 * @return array
	 */
	private function hydrate_term( $term ): array {
		$slug          = (string) $term->slug;
		$is_persian    = (bool) preg_match( '/\p{Arabic}/u', $slug );
		$is_latin      = (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
		$taxonomy      = (string) $term->taxonomy;
		$permalink     = ( 'quantum_category' === $taxonomy )
			? Core::permalink_for( $slug, 'topic' )
			: get_term_link( $term );
		if ( is_wp_error( $permalink ) ) {
			$permalink = '';
		}

		return array(
			'id'              => (int) $term->term_id,
			'term_id'         => (int) $term->term_id,
			'name'            => (string) $term->name,
			'slug'            => $slug,
			'description'     => (string) $term->description,
			'count'           => (int) $term->count,
			'parent'          => (int) $term->parent,
			'taxonomy'        => $taxonomy,
			'permalink'       => (string) $permalink,
			'is_persian_slug' => $is_persian,
			'is_latin_slug'   => $is_latin,
			'word_count'      => Core::word_count( (string) $term->description ),
		);
	}

	/**
	 * Read a whitelist of post meta keys.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $keys    Meta keys.
	 * @return array
	 */
	private function collect_metas( int $post_id, array $keys ): array {
		$out = array();
		foreach ( $keys as $key ) {
			$out[ $key ] = get_post_meta( $post_id, $key, true );
		}
		return $out;
	}

	/**
	 * Terms assigned to a post.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array
	 */
	private function post_terms( int $post_id, string $taxonomy ): array {
		$terms = wp_get_post_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$out = array();
		foreach ( $terms as $term ) {
			$out[] = array(
				'id'     => (int) $term->term_id,
				'name'   => (string) $term->name,
				'slug'   => (string) $term->slug,
				'parent' => (int) $term->parent,
			);
		}
		return $out;
	}

	/**
	 * Parse _qpedia_tags meta (array, JSON, serialized or comma list).
	 *
	 * @param mixed $raw Raw meta.
	 * @return array
	 */
	private function tags_from_meta( $raw ): array {
		$values = array();

		if ( is_array( $raw ) ) {
			$values = $raw;
		} elseif ( is_string( $raw ) && '' !== $raw ) {
			$maybe = maybe_unserialize( $raw );
			if ( is_array( $maybe ) ) {
				$values = $maybe;
			} else {
				$json = json_decode( $raw, true );
				if ( is_array( $json ) ) {
					$values = $json;
				} else {
					$values = preg_split( '/[,،]+/u', $raw );
					if ( ! is_array( $values ) ) {
						$values = array();
					}
				}
			}
		}

		$out = array();
		foreach ( $values as $value ) {
			$name = is_array( $value ) ? ( isset( $value['name'] ) ? (string) $value['name'] : '' ) : trim( (string) $value );
			if ( '' === $name ) {
				continue;
			}
			$out[] = array(
				'id'     => 0,
				'name'   => $name,
				'slug'   => sanitize_title( $name ),
				'parent' => 0,
				'source' => '_qpedia_tags',
			);
		}

		return $out;
	}

	/**
	 * Featured image URL + alt.
	 *
	 * @param int $post_id  Post ID.
	 * @param int $thumb_id Attachment ID.
	 * @return array
	 */
	private function thumbnail_payload( int $post_id, int $thumb_id ): array {
		if ( $thumb_id <= 0 ) {
			$thumb_id = (int) get_post_meta( $post_id, '_thumbnail_id', true );
		}

		if ( $thumb_id <= 0 ) {
			return array(
				'id'  => 0,
				'url' => '',
				'alt' => '',
			);
		}

		$url = wp_get_attachment_image_url( $thumb_id, 'full' );
		if ( ! is_string( $url ) || '' === $url ) {
			$url = (string) get_the_post_thumbnail_url( $post_id, 'full' );
		}

		return array(
			'id'  => $thumb_id,
			'url' => is_string( $url ) ? $url : '',
			'alt' => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * HTTP GET with timeout 8 and sslverify true.
	 *
	 * @param string $url Absolute URL.
	 * @return array
	 */
	private function remote_get( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'sslverify'   => true,
				'redirection' => 5,
				'headers'     => array(
					'Accept' => 'text/plain, application/xml, text/xml, */*',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'         => false,
				'status'     => 0,
				'body'       => '',
				'error'      => $response->get_error_message(),
				'redirected' => false,
				'final_url'  => $url,
			);
		}

		$status     = (int) wp_remote_retrieve_response_code( $response );
		$body       = (string) wp_remote_retrieve_body( $response );
		$final_url  = wp_remote_retrieve_header( $response, 'location' );
		$final_url  = is_string( $final_url ) && '' !== $final_url ? $final_url : $url;

		return array(
			'ok'         => ( $status >= 200 && $status < 400 ),
			'status'     => $status,
			'body'       => $body,
			'error'      => ( $status >= 400 ) ? sprintf( 'HTTP %d', $status ) : '',
			'redirected' => ( $status >= 300 && $status < 400 ),
			'final_url'  => $final_url,
		);
	}

	/**
	 * Parse robots.txt body.
	 *
	 * @param string $body File contents.
	 * @return array
	 */
	private function parse_robots( string $body ): array {
		$sitemaps    = array();
		$rules       = array();
		$user_agents = array();
		$current_ua  = '*';

		$lines = preg_split( '/\r\n|\r|\n/', $body );
		if ( ! is_array( $lines ) ) {
			return array(
				'sitemaps'    => $sitemaps,
				'rules'       => $rules,
				'user_agents' => $user_agents,
			);
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}

			if ( preg_match( '/^User-agent:\s*(.+)$/i', $line, $match ) ) {
				$current_ua    = trim( $match[1] );
				$user_agents[] = $current_ua;
				continue;
			}
			if ( preg_match( '/^Disallow:\s*(.*)$/i', $line, $match ) ) {
				$rules[] = array(
					'user_agent' => $current_ua,
					'type'       => 'disallow',
					'value'      => trim( $match[1] ),
				);
				continue;
			}
			if ( preg_match( '/^Allow:\s*(.*)$/i', $line, $match ) ) {
				$rules[] = array(
					'user_agent' => $current_ua,
					'type'       => 'allow',
					'value'      => trim( $match[1] ),
				);
				continue;
			}
			if ( preg_match( '/^Sitemap:\s*(.+)$/i', $line, $match ) ) {
				$sitemaps[] = trim( $match[1] );
			}
		}

		$user_agents = array_values( array_unique( $user_agents ) );

		return array(
			'sitemaps'    => $sitemaps,
			'rules'       => $rules,
			'user_agents' => $user_agents,
		);
	}
}
