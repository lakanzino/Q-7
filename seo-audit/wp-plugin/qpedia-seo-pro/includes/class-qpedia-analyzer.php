<?php
/**
 * SEO scoring engine for Qpedia content.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analyzes articles, scientists, terms, pages and images (0–100).
 */
class Analyzer {

	const OVERSIZE_BYTES = 204800; // 200 KB.

	/**
	 * Singleton instance.
	 *
	 * @var Analyzer|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Analyzer
	 */
	public static function instance(): Analyzer {
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
	 * Module boot.
	 *
	 * @return void
	 */
	public function init(): void {}

	/**
	 * Attach analysis to a collected dataset and compute site score.
	 *
	 * @param array $collected Collector payload.
	 * @return array
	 */
	public function analyze_all( array $collected ): array {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}

		$issue_total = 0;
		$grades      = array(
			'A' => 0,
			'B' => 0,
			'C' => 0,
			'D' => 0,
			'F' => 0,
		);

		if ( isset( $collected['articles'] ) && is_array( $collected['articles'] ) ) {
			foreach ( $collected['articles'] as $i => $item ) {
				$analysis = $this->analyze_article( $item );
				$collected['articles'][ $i ]['analysis'] = $analysis;
				$issue_total += count( $analysis['issues'] );
				if ( isset( $grades[ $analysis['grade'] ] ) ) {
					$grades[ $analysis['grade'] ]++;
				}
			}
		}

		if ( isset( $collected['scientists'] ) && is_array( $collected['scientists'] ) ) {
			foreach ( $collected['scientists'] as $i => $item ) {
				$analysis = $this->analyze_scientist( $item );
				$collected['scientists'][ $i ]['analysis'] = $analysis;
				$issue_total += count( $analysis['issues'] );
				if ( isset( $grades[ $analysis['grade'] ] ) ) {
					$grades[ $analysis['grade'] ]++;
				}
			}
		}

		if ( isset( $collected['terms'] ) && is_array( $collected['terms'] ) ) {
			foreach ( $collected['terms'] as $tax => $terms ) {
				if ( ! is_array( $terms ) ) {
					continue;
				}
				foreach ( $terms as $i => $item ) {
					$taxonomy = isset( $item['taxonomy'] ) ? (string) $item['taxonomy'] : (string) $tax;
					$analysis = $this->analyze_term( $item, $taxonomy );
					$collected['terms'][ $tax ][ $i ]['analysis'] = $analysis;
					$issue_total += count( $analysis['issues'] );
					if ( isset( $grades[ $analysis['grade'] ] ) ) {
						$grades[ $analysis['grade'] ]++;
					}
				}
			}
		}

		if ( isset( $collected['pages'] ) && is_array( $collected['pages'] ) ) {
			foreach ( $collected['pages'] as $i => $item ) {
				$analysis = $this->analyze_page( $item );
				$collected['pages'][ $i ]['analysis'] = $analysis;
				$issue_total += count( $analysis['issues'] );
				if ( isset( $grades[ $analysis['grade'] ] ) ) {
					$grades[ $analysis['grade'] ]++;
				}
			}
		}

		if ( isset( $collected['images'] ) && is_array( $collected['images'] ) ) {
			foreach ( $collected['images'] as $i => $item ) {
				$analysis = $this->analyze_image( $item );
				$collected['images'][ $i ]['analysis'] = $analysis;
				$issue_total += count( $analysis['issues'] );
				if ( isset( $grades[ $analysis['grade'] ] ) ) {
					$grades[ $analysis['grade'] ]++;
				}
			}
		}

		$site = $this->get_site_score( $collected );

		$collected['site_score'] = $site;
		$collected['summary']    = array(
			'issues_count' => $issue_total,
			'grades'       => $grades,
			'site_score'   => $site['score'],
			'site_grade'   => $site['grade'],
			'analyzed_at'  => current_time( 'mysql' ),
		);

		Core::set_scan_data( $collected );

		return $collected;
	}

	/**
	 * Weighted site score: articles 50, scientists 20, terms 15, pages 5, images 10.
	 *
	 * @param array $data Collected (optionally already analyzed) payload.
	 * @return array
	 */
	public function get_site_score( array $data ): array {
		$groups = array(
			'articles'   => array( 'weight' => 50, 'items' => isset( $data['articles'] ) && is_array( $data['articles'] ) ? $data['articles'] : array() ),
			'scientists' => array( 'weight' => 20, 'items' => isset( $data['scientists'] ) && is_array( $data['scientists'] ) ? $data['scientists'] : array() ),
			'terms'      => array( 'weight' => 15, 'items' => $this->flatten_terms( isset( $data['terms'] ) && is_array( $data['terms'] ) ? $data['terms'] : array() ) ),
			'pages'      => array( 'weight' => 5, 'items' => isset( $data['pages'] ) && is_array( $data['pages'] ) ? $data['pages'] : array() ),
			'images'     => array( 'weight' => 10, 'items' => isset( $data['images'] ) && is_array( $data['images'] ) ? $data['images'] : array() ),
		);

		$details      = array();
		$weighted_sum = 0;
		$weight_total = 0;
		$issues       = array();
		$suggestions  = array();

		foreach ( $groups as $key => $group ) {
			$avg = $this->average_score( $group['items'], $key );
			$details[ $key ] = array(
				'avg'    => $avg,
				'count'  => count( $group['items'] ),
				'weight' => $group['weight'],
			);
			$weighted_sum += $avg * $group['weight'];
			$weight_total += $group['weight'];

			if ( $avg < 70 && count( $group['items'] ) > 0 ) {
				$issues[] = sprintf(
					/* translators: 1: group label, 2: average score */
					__( 'Average score for %1$s is %2$d and needs improvement.', 'qpedia-seo-pro' ),
					$this->group_label( $key ),
					$avg
				);
			}
		}

		$score = ( $weight_total > 0 ) ? (int) round( $weighted_sum / $weight_total ) : 0;
		$grade = Core::grade( $score );

		if ( $score < 90 ) {
			$suggestions[] = __( 'Fix D and F graded articles first; articles weight is 50% of total score.', 'qpedia-seo-pro' );
		}
		if ( isset( $details['images']['avg'] ) && $details['images']['avg'] < 80 ) {
			$suggestions[] = __( 'Check image alt texts and files larger than 200KB.', 'qpedia-seo-pro' );
		}

		return array(
			'score'       => $score,
			'grade'       => $grade,
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => $details,
		);
	}

	/**
	 * Score a quantum_article. Accepts post ID or collected array.
	 *
	 * Weights: title 15, description 15, keyword 10, content 10, headings 10,
	 * thumbnail 10, internal links 10, slug 5, FAQ 5, category 5, freshness 5.
	 *
	 * @param mixed $post_or_array Post ID or collected row.
	 * @return array
	 */
	public function analyze_article( $post_or_array ): array {
		$item = $this->resolve_article( $post_or_array );
		if ( empty( $item ) ) {
			return $this->empty_result( __( 'Article not found.', 'qpedia-seo-pro' ) );
		}

		$metas     = isset( $item['metas'] ) && is_array( $item['metas'] ) ? $item['metas'] : array();
		$title     = $this->meta_string( $metas, 'rank_math_title' );
		if ( '' === $title ) {
			$title = $this->meta_string( $metas, '_qpedia_seo_title' );
		}
		$desc      = $this->meta_string( $metas, 'rank_math_description' );
		$keyword   = $this->meta_string( $metas, 'rank_math_focus_keyword' );
		$faq       = isset( $metas['rank_math_schema_FAQPage'] ) ? $metas['rank_math_schema_FAQPage'] : '';
		$post_title = isset( $item['title'] ) ? (string) $item['title'] : '';
		$slug      = isset( $item['slug'] ) ? (string) $item['slug'] : '';
		$content   = isset( $item['content'] ) ? (string) $item['content'] : '';
		$headings  = isset( $item['headings'] ) && is_array( $item['headings'] ) ? $item['headings'] : Core::extract_headings( $content );
		$links     = isset( $item['internal_links'] ) && is_array( $item['internal_links'] ) ? $item['internal_links'] : Core::extract_internal_links( $content );
		$words     = isset( $item['word_count'] ) ? (int) $item['word_count'] : Core::word_count( $content );
		$thumb     = isset( $item['thumbnail'] ) && is_array( $item['thumbnail'] ) ? $item['thumbnail'] : array();
		$cats      = isset( $item['categories'] ) && is_array( $item['categories'] ) ? $item['categories'] : array();
		$modified  = isset( $item['modified'] ) ? (string) $item['modified'] : '';
		$self_path = isset( $item['permalink'] ) ? Core::normalize_path( (string) $item['permalink'] ) : '/' . $slug;

		$issues      = array();
		$suggestions = array();
		$details     = array();
		$score       = 0;

		$title_part = $this->score_seo_title( $title, $keyword, 15, $issues, $suggestions );
		$score     += $title_part['score'];
		$details['title'] = $title_part;

		$desc_part = $this->score_seo_description( $desc, $keyword, 15, $issues, $suggestions );
		$score    += $desc_part['score'];
		$details['description'] = $desc_part;

		$kw_part = $this->score_keyword_presence( $keyword, $post_title, $title, $headings, $content, 10, $issues, $suggestions );
		$score  += $kw_part['score'];
		$details['keyword'] = $kw_part;

		$content_part = $this->score_article_length( $words, $issues, $suggestions );
		$score       += $content_part['score'];
		$details['content'] = $content_part;

		$heading_part = $this->score_headings( $headings, 10, $issues, $suggestions );
		$score       += $heading_part['score'];
		$details['headings'] = $heading_part;

		$thumb_part = $this->score_thumbnail( $thumb, $keyword, 10, $issues, $suggestions );
		$score     += $thumb_part['score'];
		$details['thumbnail'] = $thumb_part;

		$link_part = $this->score_internal_links( $links, $self_path, 10, 2, $issues, $suggestions );
		$score    += $link_part['score'];
		$details['internal_links'] = $link_part;

		$slug_part = $this->score_slug( $slug, $keyword, 5, $issues, $suggestions );
		$score    += $slug_part['score'];
		$details['slug'] = $slug_part;

		$faq_score = $this->has_faq( $faq ) ? 5 : 0;
		if ( 0 === $faq_score ) {
			$issues[]      = __( 'FAQPage schema is not set.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Add a few Q&A items in Rank Math FAQ.', 'qpedia-seo-pro' );
		}
		$score += $faq_score;
		$details['faq'] = array(
			'score' => $faq_score,
			'max'   => 5,
			'has'   => $faq_score > 0,
		);

		$cat_score = count( $cats ) > 0 ? 5 : 0;
		if ( 0 === $cat_score ) {
			$issues[]      = __( 'No quantum_category assigned.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Select at least one topic from Qpedia taxonomy.', 'qpedia-seo-pro' );
		}
		$score += $cat_score;
		$details['category'] = array(
			'score' => $cat_score,
			'max'   => 5,
			'count' => count( $cats ),
		);

		$fresh = $this->score_freshness( $modified, 5, $issues, $suggestions );
		$score += $fresh['score'];
		$details['freshness'] = $fresh;

		$score = $this->clamp( $score );

		return array(
			'score'       => $score,
			'grade'       => Core::grade( $score ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => $details,
		);
	}

	/**
	 * Score a quantum_scientist. Accepts post ID or collected array.
	 *
	 * Weights: title 15, description 15, keyword 10, en_name 5, fullname 5,
	 * bio 9 (3×3), achievements 12 (3×4), thumbnail 10, content 10, links 9.
	 *
	 * @param mixed $post_or_array Post ID or collected row.
	 * @return array
	 */
	public function analyze_scientist( $post_or_array ): array {
		$item = $this->resolve_scientist( $post_or_array );
		if ( empty( $item ) ) {
			return $this->empty_result( __( 'Scientist page not found.', 'qpedia-seo-pro' ) );
		}

		$metas      = isset( $item['metas'] ) && is_array( $item['metas'] ) ? $item['metas'] : array();
		$title      = $this->meta_string( $metas, '_qpedia_seo_title' );
		if ( '' === $title ) {
			$title = $this->meta_string( $metas, 'rank_math_title' );
		}
		$desc       = $this->meta_string( $metas, '_qpedia_meta_description' );
		if ( '' === $desc ) {
			$desc = $this->meta_string( $metas, 'rank_math_description' );
		}
		$keyword    = $this->meta_string( $metas, '_qpedia_focus_keyphrase' );
		if ( '' === $keyword ) {
			$keyword = $this->meta_string( $metas, 'rank_math_focus_keyword' );
		}
		$en_name    = $this->meta_string( $metas, '_scientist_en_name' );
		$fullname   = $this->meta_string( $metas, '_scientist_fullname' );
		$born       = $this->meta_string( $metas, '_scientist_born_died' );
		$place      = $this->meta_string( $metas, '_scientist_birthplace' );
		$inst       = $this->meta_string( $metas, '_scientist_institutions' );
		$achieve    = $this->meta_string( $metas, '_scientist_achievement' );
		$nobel      = $this->meta_string( $metas, '_scientist_nobel' );
		$concepts   = $this->meta_string( $metas, '_scientist_concepts' );
		$post_title = isset( $item['title'] ) ? (string) $item['title'] : '';
		$content    = isset( $item['content'] ) ? (string) $item['content'] : '';
		$words      = isset( $item['word_count'] ) ? (int) $item['word_count'] : Core::word_count( $content );
		$links      = isset( $item['internal_links'] ) && is_array( $item['internal_links'] ) ? $item['internal_links'] : Core::extract_internal_links( $content );
		$thumb      = isset( $item['thumbnail'] ) && is_array( $item['thumbnail'] ) ? $item['thumbnail'] : array();
		$self_path  = isset( $item['permalink'] ) ? Core::normalize_path( (string) $item['permalink'] ) : '';

		$issues      = array();
		$suggestions = array();
		$details     = array();
		$score       = 0;

		$title_part = $this->score_seo_title( $title, $keyword, 15, $issues, $suggestions );
		$score     += $title_part['score'];
		$details['title'] = $title_part;

		$desc_part = $this->score_seo_description( $desc, $keyword, 15, $issues, $suggestions );
		$score    += $desc_part['score'];
		$details['description'] = $desc_part;

		$kw_score = 0;
		if ( '' !== $keyword ) {
			$kw_score += 5;
			if ( $this->contains_keyword( $title, $keyword ) || $this->contains_keyword( $post_title, $keyword ) ) {
				$kw_score += 5;
			} else {
				$issues[]      = __( 'Focus keyword not in scientist title.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Include focus keyword in SEO title.', 'qpedia-seo-pro' );
			}
		} else {
			$issues[]      = __( 'Focus keyword (_qpedia_focus_keyphrase) is empty.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Enter a keyphrase like the scientist name.', 'qpedia-seo-pro' );
		}
		$score += $kw_score;
		$details['keyword'] = array(
			'score' => $kw_score,
			'max'   => 10,
			'value' => $keyword,
		);

		$en_score = ( '' !== $en_name ) ? 5 : 0;
		if ( 0 === $en_score ) {
			$issues[] = __( 'Scientist English name is not set.', 'qpedia-seo-pro' );
		}
		$score += $en_score;
		$details['en_name'] = array(
			'score' => $en_score,
			'max'   => 5,
			'value' => $en_name,
		);

		$fn_score = ( '' !== $fullname ) ? 5 : 0;
		if ( 0 === $fn_score ) {
			$issues[] = __( 'Scientist full name is not set.', 'qpedia-seo-pro' );
		}
		$score += $fn_score;
		$details['fullname'] = array(
			'score' => $fn_score,
			'max'   => 5,
			'value' => $fullname,
		);

		$bio_score = 0;
		$bio_fields = array(
			'born_died'    => $born,
			'birthplace'   => $place,
			'institutions' => $inst,
		);
		foreach ( $bio_fields as $key => $value ) {
			if ( '' !== $value ) {
				$bio_score += 3;
			} else {
				$issues[] = sprintf(
					/* translators: %s: field name */
					__( 'Biography field is empty: %s', 'qpedia-seo-pro' ),
					$key
				);
			}
		}
		$score += $bio_score;
		$details['bio'] = array(
			'score'  => $bio_score,
			'max'    => 9,
			'fields' => $bio_fields,
		);

		$ach_score  = 0;
		$ach_fields = array(
			'achievement' => $achieve,
			'nobel'       => $nobel,
			'concepts'    => $concepts,
		);
		foreach ( $ach_fields as $key => $value ) {
			if ( '' !== $value ) {
				$ach_score += 4;
			} else {
				if ( 'nobel' === $key ) {
					$suggestions[] = __( 'If Nobel laureate, fill _scientist_nobel field.', 'qpedia-seo-pro' );
				} else {
					$issues[] = sprintf(
						/* translators: %s: field name */
						__( 'Achievement field is empty: %s', 'qpedia-seo-pro' ),
						$key
					);
				}
			}
		}
		$score += $ach_score;
		$details['achievements'] = array(
			'score'  => $ach_score,
			'max'    => 12,
			'fields' => $ach_fields,
		);

		$thumb_part = $this->score_thumbnail( $thumb, $keyword, 10, $issues, $suggestions );
		$score     += $thumb_part['score'];
		$details['thumbnail'] = $thumb_part;

		$c_score = 0;
		if ( $words >= 500 ) {
			$c_score = 10;
		} elseif ( $words >= 250 ) {
			$c_score = 5;
			$issues[]      = __( 'Scientist content is less than 500 words.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Expand biography to at least 500 words.', 'qpedia-seo-pro' );
		} else {
			$issues[]      = __( 'Scientist content is very short.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Write at least 500 words about life and works.', 'qpedia-seo-pro' );
		}
		$score += $c_score;
		$details['content'] = array(
			'score'      => $c_score,
			'max'        => 10,
			'word_count' => $words,
		);

		$link_part = $this->score_internal_links( $links, $self_path, 9, 1, $issues, $suggestions );
		$score    += $link_part['score'];
		$details['internal_links'] = $link_part;

		$score = $this->clamp( $score );

		return array(
			'score'       => $score,
			'grade'       => Core::grade( $score ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => $details,
		);
	}

	/**
	 * Score a taxonomy term. Accepts term ID or collected array.
	 *
	 * Checks: description ≥100 words, count ≥3, latin slug.
	 *
	 * @param mixed  $term_or_array Term ID or collected row.
	 * @param string $taxonomy      Taxonomy slug when ID is passed.
	 * @return array
	 */
	public function analyze_term( $term_or_array, string $taxonomy = 'quantum_category' ): array {
		$item = $this->resolve_term( $term_or_array, $taxonomy );
		if ( empty( $item ) ) {
			return $this->empty_result( __( 'Term not found.', 'qpedia-seo-pro' ) );
		}

		$description = isset( $item['description'] ) ? (string) $item['description'] : '';
		$words       = isset( $item['word_count'] ) ? (int) $item['word_count'] : Core::word_count( $description );
		$count       = isset( $item['count'] ) ? (int) $item['count'] : 0;
		$slug        = isset( $item['slug'] ) ? (string) $item['slug'] : '';
		$is_latin    = isset( $item['is_latin_slug'] ) ? (bool) $item['is_latin_slug'] : (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
		$is_persian  = isset( $item['is_persian_slug'] ) ? (bool) $item['is_persian_slug'] : (bool) preg_match( '/\p{Arabic}/u', $slug );

		$issues      = array();
		$suggestions = array();
		$score       = 0;

		$desc_score = 0;
		if ( $words >= 100 ) {
			$desc_score = 50;
		} elseif ( $words >= 40 ) {
			$desc_score = 25;
			$issues[]      = __( 'Term description is less than 100 words.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Expand /topic/ archive description to at least 100 words.', 'qpedia-seo-pro' );
		} elseif ( $words > 0 ) {
			$desc_score = 10;
			$issues[]      = __( 'Term description is very short.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Write at least a 100-word descriptive paragraph.', 'qpedia-seo-pro' );
		} else {
			$issues[]      = __( 'Term description is empty.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Write a description for the topic archive page.', 'qpedia-seo-pro' );
		}
		$score += $desc_score;

		$count_score = 0;
		if ( $count >= 3 ) {
			$count_score = 30;
		} elseif ( $count >= 1 ) {
			$count_score = 15;
			$issues[]      = __( 'Less than 3 contents assigned to this term.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Publish more related articles in this topic.', 'qpedia-seo-pro' );
		} else {
			$issues[]      = __( 'This term is empty (count = 0).', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Fill the empty term or delete if unused.', 'qpedia-seo-pro' );
		}
		$score += $count_score;

		$slug_score = 0;
		if ( $is_latin ) {
			$slug_score = 20;
		} elseif ( $is_persian ) {
			$issues[]      = __( 'Slug is Persian; Latin kebab-case is better for URL.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Change slug to English equivalent with hyphens.', 'qpedia-seo-pro' );
		} else {
			$slug_score = 8;
			$issues[] = __( 'Term slug is not standard Latin kebab-case.', 'qpedia-seo-pro' );
		}
		$score += $slug_score;

		$score = $this->clamp( $score );

		return array(
			'score'       => $score,
			'grade'       => Core::grade( $score ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => array(
				'description' => array(
					'score'      => $desc_score,
					'max'        => 50,
					'word_count' => $words,
				),
				'count'       => array(
					'score' => $count_score,
					'max'   => 30,
					'value' => $count,
				),
				'slug'        => array(
					'score'           => $slug_score,
					'max'             => 20,
					'value'           => $slug,
					'is_latin_slug'   => $is_latin,
					'is_persian_slug' => $is_persian,
				),
			),
		);
	}

	/**
	 * Simpler page scoring. Accepts post ID or collected array.
	 *
	 * @param mixed $post_or_array Post ID or collected row.
	 * @return array
	 */
	public function analyze_page( $post_or_array ): array {
		$item = $this->resolve_page( $post_or_array );
		if ( empty( $item ) ) {
			return $this->empty_result( __( 'Page not found.', 'qpedia-seo-pro' ) );
		}

		$metas      = isset( $item['metas'] ) && is_array( $item['metas'] ) ? $item['metas'] : array();
		$title      = $this->meta_string( $metas, 'rank_math_title' );
		if ( '' === $title ) {
			$title = $this->meta_string( $metas, '_qpedia_seo_title' );
		}
		$desc       = $this->meta_string( $metas, 'rank_math_description' );
		$keyword    = $this->meta_string( $metas, 'rank_math_focus_keyword' );
		$content    = isset( $item['content'] ) ? (string) $item['content'] : '';
		$headings   = isset( $item['headings'] ) && is_array( $item['headings'] ) ? $item['headings'] : Core::extract_headings( $content );
		$words      = isset( $item['word_count'] ) ? (int) $item['word_count'] : Core::word_count( $content );
		$thumb      = isset( $item['thumbnail'] ) && is_array( $item['thumbnail'] ) ? $item['thumbnail'] : array();
		$slug       = isset( $item['slug'] ) ? (string) $item['slug'] : '';
		$modified   = isset( $item['modified'] ) ? (string) $item['modified'] : '';

		$issues      = array();
		$suggestions = array();
		$details     = array();
		$score       = 0;

		$title_part = $this->score_seo_title( $title, $keyword, 20, $issues, $suggestions );
		$score     += $title_part['score'];
		$details['title'] = $title_part;

		$desc_part = $this->score_seo_description( $desc, $keyword, 20, $issues, $suggestions );
		$score    += $desc_part['score'];
		$details['description'] = $desc_part;

		$c_score = 0;
		if ( $words >= 800 ) {
			$c_score = 20;
		} elseif ( $words >= 300 ) {
			$c_score = 14;
		} elseif ( $words >= 100 ) {
			$c_score = 8;
			$issues[] = __( 'Page content is relatively short.', 'qpedia-seo-pro' );
		} else {
			$issues[]      = __( 'Page content is very short.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Expand page content.', 'qpedia-seo-pro' );
		}
		$score += $c_score;
		$details['content'] = array(
			'score'      => $c_score,
			'max'        => 20,
			'word_count' => $words,
		);

		$heading_part = $this->score_headings( $headings, 15, $issues, $suggestions );
		$score       += $heading_part['score'];
		$details['headings'] = $heading_part;

		$thumb_part = $this->score_thumbnail( $thumb, $keyword, 10, $issues, $suggestions );
		$score     += $thumb_part['score'];
		$details['thumbnail'] = $thumb_part;

		$slug_part = $this->score_slug( $slug, $keyword, 10, $issues, $suggestions );
		$score    += $slug_part['score'];
		$details['slug'] = $slug_part;

		$fresh = $this->score_freshness( $modified, 5, $issues, $suggestions );
		$score += $fresh['score'];
		$details['freshness'] = $fresh;

		$score = $this->clamp( $score );

		return array(
			'score'       => $score,
			'grade'       => Core::grade( $score ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => $details,
		);
	}

	/**
	 * Score an attachment. Accepts ID or collected array.
	 *
	 * Checks: alt exists, alt length, attached, oversized >200KB.
	 *
	 * @param mixed $image_or_id Attachment ID or collected row.
	 * @return array
	 */
	public function analyze_image( $image_or_id ): array {
		$item = $this->resolve_image( $image_or_id );
		if ( empty( $item ) ) {
			return $this->empty_result( __( 'Image not found.', 'qpedia-seo-pro' ) );
		}

		$alt      = isset( $item['alt'] ) ? trim( (string) $item['alt'] ) : '';
		$attached = isset( $item['attached_to'] ) ? (int) $item['attached_to'] : 0;
		$bytes    = isset( $item['filesize'] ) ? (int) $item['filesize'] : 0;
		$alt_len  = Core::length( $alt );

		$issues      = array();
		$suggestions = array();
		$score       = 0;

		$alt_exist_score = 0;
		if ( '' !== $alt ) {
			$alt_exist_score = 40;
		} else {
			$issues[]      = __( 'Alt text is empty.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Write a descriptive alt relevant to image topic.', 'qpedia-seo-pro' );
		}
		$score += $alt_exist_score;

		$alt_len_score = 0;
		if ( '' !== $alt ) {
			if ( $alt_len >= 5 && $alt_len <= 125 ) {
				$alt_len_score = 20;
			} elseif ( $alt_len > 125 ) {
				$alt_len_score = 8;
				$issues[]      = __( 'Alt is longer than 125 characters.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Keep alt short and descriptive.', 'qpedia-seo-pro' );
			} else {
				$alt_len_score = 8;
				$issues[] = __( 'Alt is too short.', 'qpedia-seo-pro' );
			}
		}
		$score += $alt_len_score;

		$attach_score = 0;
		if ( $attached > 0 ) {
			$attach_score = 20;
		} else {
			$issues[]      = __( 'Image is not attached to any post.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Attach image to related article or scientist.', 'qpedia-seo-pro' );
		}
		$score += $attach_score;

		$size_score = 0;
		if ( $bytes <= 0 ) {
			$size_score = 10;
			$suggestions[] = __( 'File size unavailable; check metadata.', 'qpedia-seo-pro' );
		} elseif ( $bytes <= self::OVERSIZE_BYTES ) {
			$size_score = 20;
		} else {
			$issues[]      = __( 'Image is larger than 200KB.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Compress image or create optimized webp.', 'qpedia-seo-pro' );
		}
		$score += $size_score;

		$score = $this->clamp( $score );

		return array(
			'score'       => $score,
			'grade'       => Core::grade( $score ),
			'issues'      => $issues,
			'suggestions' => $suggestions,
			'details'     => array(
				'alt_exists' => array(
					'score' => $alt_exist_score,
					'max'   => 40,
					'value' => $alt,
				),
				'alt_length' => array(
					'score'  => $alt_len_score,
					'max'    => 20,
					'length' => $alt_len,
				),
				'attached'   => array(
					'score' => $attach_score,
					'max'   => 20,
					'parent'=> $attached,
				),
				'filesize'   => array(
					'score'  => $size_score,
					'max'    => 20,
					'bytes'  => $bytes,
					'limit'  => self::OVERSIZE_BYTES,
				),
			),
		);
	}

	/**
	 * Title scoring (existence + length ≤60 + keyword).
	 *
	 * @param string $title       SEO title.
	 * @param string $keyword     Focus keyword.
	 * @param int    $max         Max points.
	 * @param array  $issues      Issues (by ref).
	 * @param array  $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_seo_title( string $title, string $keyword, int $max, array &$issues, array &$suggestions ): array {
		$exist = (int) floor( $max * 5 / 15 );
		$len_p = (int) floor( $max * 5 / 15 );
		$kw_p  = $max - $exist - $len_p;
		$score = 0;
		$length = Core::length( $title );

		if ( '' !== $title ) {
			$score += $exist;
			if ( $length > 0 && $length <= 60 ) {
				$score += $len_p;
			} elseif ( $length <= 70 ) {
				$score += (int) floor( $len_p / 2 );
				$issues[]      = __( 'SEO title is slightly long (over 60 chars).', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Shorten title to 60 characters or less.', 'qpedia-seo-pro' );
			} else {
				$issues[]      = __( 'SEO title is too long.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Shorten SEO title to avoid truncation in SERP.', 'qpedia-seo-pro' );
			}
			if ( '' !== $keyword && $this->contains_keyword( $title, $keyword ) ) {
				$score += $kw_p;
			} elseif ( '' !== $keyword ) {
				$issues[]      = __( 'Keyword not in SEO title.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Include focus keyword in SEO title.', 'qpedia-seo-pro' );
			}
		} else {
			$issues[]      = __( 'SEO title is not set.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Fill rank_math_title or _qpedia_seo_title field.', 'qpedia-seo-pro' );
		}

		return array(
			'score'       => $score,
			'max'         => $max,
			'length'      => $length,
			'has_keyword' => ( '' !== $keyword && $this->contains_keyword( $title, $keyword ) ),
			'value'       => $title,
		);
	}

	/**
	 * Description scoring (existence + 120–160 + keyword).
	 *
	 * @param string $desc        Meta description.
	 * @param string $keyword     Focus keyword.
	 * @param int    $max         Max points.
	 * @param array  $issues      Issues (by ref).
	 * @param array  $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_seo_description( string $desc, string $keyword, int $max, array &$issues, array &$suggestions ): array {
		$exist  = (int) floor( $max * 5 / 15 );
		$len_p  = (int) floor( $max * 5 / 15 );
		$kw_p   = $max - $exist - $len_p;
		$score  = 0;
		$length = Core::length( $desc );

		if ( '' !== $desc ) {
			$score += $exist;
			if ( $length >= 120 && $length <= 160 ) {
				$score += $len_p;
			} elseif ( $length >= 80 && $length <= 200 ) {
				$score += (int) floor( $len_p / 2 );
				$issues[]      = __( 'SEO description length outside ideal 120-160 chars.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Keep description between 120-160 chars.', 'qpedia-seo-pro' );
			} else {
				$issues[]      = __( 'SEO description length is not optimal.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Write meta description between 120-160 chars.', 'qpedia-seo-pro' );
			}
			if ( '' !== $keyword && $this->contains_keyword( $desc, $keyword ) ) {
				$score += $kw_p;
			} elseif ( '' !== $keyword ) {
				$issues[]      = __( 'Keyword not in SEO description.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Include keyword naturally in description.', 'qpedia-seo-pro' );
			}
		} else {
			$issues[]      = __( 'SEO description is not set.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Write a meta description between 120-160 chars.', 'qpedia-seo-pro' );
		}

		return array(
			'score'       => $score,
			'max'         => $max,
			'length'      => $length,
			'has_keyword' => ( '' !== $keyword && $this->contains_keyword( $desc, $keyword ) ),
			'value'       => $desc,
		);
	}

	/**
	 * Keyword presence in H1 / title / first paragraph. Max 10.
	 *
	 * @param string $keyword     Focus keyword.
	 * @param string $post_title  Post title.
	 * @param string $seo_title   SEO title.
	 * @param array  $headings    Heading list.
	 * @param string $content     HTML content.
	 * @param int    $max         Max points.
	 * @param array  $issues      Issues (by ref).
	 * @param array  $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_keyword_presence( string $keyword, string $post_title, string $seo_title, array $headings, string $content, int $max, array &$issues, array &$suggestions ): array {
		$score = 0;
		$in_h1 = false;
		$in_title = false;
		$in_first = false;

		if ( '' === $keyword ) {
			$issues[]      = __( 'Focus keyword is not set.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Select a focus keyword in Rank Math.', 'qpedia-seo-pro' );
			return array(
				'score'     => 0,
				'max'       => $max,
				'in_h1'     => false,
				'in_title'  => false,
				'in_first'  => false,
				'value'     => '',
			);
		}

		$score += 4;
		$in_title = $this->contains_keyword( $post_title, $keyword ) || $this->contains_keyword( $seo_title, $keyword );
		if ( $in_title ) {
			$score += 2;
		}

		foreach ( $headings as $heading ) {
			if ( isset( $heading['tag'] ) && 'h1' === $heading['tag'] && $this->contains_keyword( (string) $heading['text'], $keyword ) ) {
				$in_h1 = true;
				break;
			}
		}
		if ( $in_h1 ) {
			$score += 2;
		}

		$first = $this->first_paragraph( $content );
		$in_first = $this->contains_keyword( $first, $keyword );
		if ( $in_first ) {
			$score += 2;
		}

		if ( ! $in_title && ! $in_h1 && ! $in_first ) {
			$issues[]      = __( 'Keyword not in H1, title or first paragraph.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Repeat keyword in title and first sentence.', 'qpedia-seo-pro' );
		} elseif ( ! $in_first ) {
			$suggestions[] = __( 'Include keyword in first paragraph.', 'qpedia-seo-pro' );
		}

		if ( $score > $max ) {
			$score = $max;
		}

		return array(
			'score'    => $score,
			'max'      => $max,
			'in_h1'    => $in_h1,
			'in_title' => $in_title,
			'in_first' => $in_first,
			'value'    => $keyword,
		);
	}

	/**
	 * Article body length: 0 <300, 5 <800, 8 <1500, 10 ≥1500.
	 *
	 * @param int   $words       Word count.
	 * @param array $issues      Issues (by ref).
	 * @param array $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_article_length( int $words, array &$issues, array &$suggestions ): array {
		$score = 0;
		if ( $words >= 1500 ) {
			$score = 10;
		} elseif ( $words >= 800 ) {
			$score = 8;
			$suggestions[] = __( 'For full score, expand content to 1500+ words.', 'qpedia-seo-pro' );
		} elseif ( $words >= 300 ) {
			$score = 5;
			$issues[]      = __( 'Content is less than 800 words and needs expansion.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Add explanatory sections, examples and FAQ.', 'qpedia-seo-pro' );
		} else {
			$score = 0;
			$issues[]      = __( 'Thin content: less than 300 words.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Expand article to at least 800 words (ideal 1500).', 'qpedia-seo-pro' );
		}

		return array(
			'score'      => $score,
			'max'        => 10,
			'word_count' => $words,
		);
	}

	/**
	 * Headings: H2 + H3 present, no H3 before first H2.
	 *
	 * @param array $headings    Heading list.
	 * @param int   $max         Max points.
	 * @param array $issues      Issues (by ref).
	 * @param array $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_headings( array $headings, int $max, array &$issues, array &$suggestions ): array {
		$has_h2        = false;
		$has_h3        = false;
		$h3_before_h2  = false;
		$seen_h2       = false;
		$h2_points     = (int) round( $max * 0.4 );
		$h3_points     = (int) round( $max * 0.3 );
		$hier_points   = $max - $h2_points - $h3_points;
		$score         = 0;

		foreach ( $headings as $heading ) {
			$tag = isset( $heading['tag'] ) ? strtolower( (string) $heading['tag'] ) : '';
			if ( 'h2' === $tag ) {
				$has_h2  = true;
				$seen_h2 = true;
			} elseif ( 'h3' === $tag ) {
				$has_h3 = true;
				if ( ! $seen_h2 ) {
					$h3_before_h2 = true;
				}
			}
		}

		if ( $has_h2 ) {
			$score += $h2_points;
		} else {
			$issues[]      = __( 'No H2 heading in content.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Structure content with several H2 headings.', 'qpedia-seo-pro' );
		}
		if ( $has_h3 ) {
			$score += $h3_points;
		} else {
			$suggestions[] = __( 'Use H3 for subsections.', 'qpedia-seo-pro' );
		}
		if ( $has_h2 && ! $h3_before_h2 ) {
			$score += $hier_points;
		} elseif ( $h3_before_h2 ) {
			$issues[]      = __( 'Heading hierarchy is incorrect (H3 before H2).', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Place H2 first, then H3s for that section.', 'qpedia-seo-pro' );
		}

		return array(
			'score'         => $score,
			'max'           => $max,
			'has_h2'        => $has_h2,
			'has_h3'        => $has_h3,
			'h3_before_h2'  => $h3_before_h2,
			'heading_count' => count( $headings ),
		);
	}

	/**
	 * Featured image: exists + meaningful alt + keyword in alt.
	 *
	 * @param array  $thumb       Thumbnail payload.
	 * @param string $keyword     Focus keyword.
	 * @param int    $max         Max points.
	 * @param array  $issues      Issues (by ref).
	 * @param array  $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_thumbnail( array $thumb, string $keyword, int $max, array &$issues, array &$suggestions ): array {
		$url   = isset( $thumb['url'] ) ? (string) $thumb['url'] : '';
		$alt   = isset( $thumb['alt'] ) ? trim( (string) $thumb['alt'] ) : '';
		$id    = isset( $thumb['id'] ) ? (int) $thumb['id'] : 0;
		$exist = (int) floor( $max * 4 / 10 );
		$alt_p = (int) floor( $max * 3 / 10 );
		$kw_p  = $max - $exist - $alt_p;
		$score = 0;

		if ( $id > 0 && '' !== $url ) {
			$score += $exist;
			if ( '' !== $alt && Core::length( $alt ) >= 3 ) {
				$score += $alt_p;
				if ( '' !== $keyword && $this->contains_keyword( $alt, $keyword ) ) {
					$score += $kw_p;
				} elseif ( '' !== $keyword ) {
					$suggestions[] = __( 'Include keyword in featured image alt.', 'qpedia-seo-pro' );
				}
			} else {
				$issues[]      = __( 'Featured image alt is not meaningful.', 'qpedia-seo-pro' );
				$suggestions[] = __( 'Write a descriptive alt for featured image.', 'qpedia-seo-pro' );
			}
		} else {
			$issues[]      = __( 'Featured image is not set.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Select a webp featured image with proper alt.', 'qpedia-seo-pro' );
		}

		return array(
			'score'       => $score,
			'max'         => $max,
			'id'          => $id,
			'has_url'     => ( '' !== $url ),
			'alt'         => $alt,
			'has_keyword' => ( '' !== $keyword && $this->contains_keyword( $alt, $keyword ) ),
		);
	}

	/**
	 * Internal links to other qpedia articles/scientists.
	 *
	 * @param array  $links       Extracted links.
	 * @param string $self_path   Current path.
	 * @param int    $max         Max points.
	 * @param int    $needed      Minimum unique targets.
	 * @param array  $issues      Issues (by ref).
	 * @param array  $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_internal_links( array $links, string $self_path, int $max, int $needed, array &$issues, array &$suggestions ): array {
		$paths = array();
		foreach ( $links as $link ) {
			$path = isset( $link['path'] ) ? (string) $link['path'] : '';
			if ( '' === $path || $path === $self_path || '/' === $path ) {
				continue;
			}
			if ( 0 === strpos( $path, '/wp-admin' ) || 0 === strpos( $path, '/wp-content' ) || 0 === strpos( $path, '/wp-login' ) ) {
				continue;
			}
			$paths[ $path ] = true;
		}
		$count = count( $paths );
		$score = 0;

		if ( $count >= $needed ) {
			$score = $max;
		} elseif ( $count > 0 && $needed > 1 ) {
			$score = (int) round( $max * ( $count / $needed ) );
			$issues[]      = __( 'Not enough internal links to other articles/scientists.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Add at least two links to related qpedia.ir content.', 'qpedia-seo-pro' );
		} else {
			$issues[]      = __( 'No internal link to other articles or scientists found.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Link to related articles and scientists.', 'qpedia-seo-pro' );
		}

		return array(
			'score'  => $score,
			'max'    => $max,
			'count'  => $count,
			'needed' => $needed,
			'paths'  => array_keys( $paths ),
		);
	}

	/**
	 * Slug: latin kebab, ≤75 chars, contains latin keyword when present.
	 *
	 * @param string $slug        Post slug.
	 * @param string $keyword     Focus keyword.
	 * @param int    $max         Max points.
	 * @param array  $issues      Issues (by ref).
	 * @param array  $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_slug( string $slug, string $keyword, int $max, array &$issues, array &$suggestions ): array {
		$latin_ok = (bool) preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
		$short    = Core::length( $slug ) <= 75 && '' !== $slug;
		$latin_kw = $this->latin_keyword( $keyword );
		$has_kw   = false;
		$score    = 0;

		$base = (int) floor( $max * 2 / 5 );
		$lenp = (int) floor( $max * 1 / 5 );
		$kwp  = $max - $base - $lenp;

		if ( $latin_ok ) {
			$score += $base;
		} else {
			$issues[]      = __( 'Slug is not Latin kebab-case.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Convert slug to lowercase English with hyphens.', 'qpedia-seo-pro' );
		}
		if ( $short ) {
			$score += $lenp;
		} else {
			$issues[] = __( 'Slug is longer than 75 characters.', 'qpedia-seo-pro' );
		}

		if ( '' !== $latin_kw ) {
			$has_kw = ( false !== strpos( $slug, str_replace( ' ', '-', $latin_kw ) ) ) || $this->slug_has_latin_parts( $slug, $latin_kw );
			if ( $has_kw ) {
				$score += $kwp;
			} else {
				$suggestions[] = __( 'If you have an English keyword, include it in slug.', 'qpedia-seo-pro' );
			}
		} elseif ( $latin_ok ) {
			$score += $kwp;
		}

		return array(
			'score'     => $score,
			'max'       => $max,
			'value'     => $slug,
			'is_latin'  => $latin_ok,
			'has_kw'    => $has_kw,
			'latin_kw'  => $latin_kw,
		);
	}

	/**
	 * Freshness: modified within 6 months.
	 *
	 * @param string $modified    MySQL datetime.
	 * @param int    $max         Max points.
	 * @param array  $issues      Issues (by ref).
	 * @param array  $suggestions Suggestions (by ref).
	 * @return array
	 */
	private function score_freshness( string $modified, int $max, array &$issues, array &$suggestions ): array {
		$ts  = $modified ? strtotime( $modified ) : false;
		$cut = strtotime( '-6 months' );
		$ok  = ( false !== $ts && false !== $cut && $ts >= $cut );

		if ( ! $ok ) {
			$issues[]      = __( 'Last edit was more than 6 months ago.', 'qpedia-seo-pro' );
			$suggestions[] = __( 'Review content and update edit date.', 'qpedia-seo-pro' );
		}

		return array(
			'score'    => $ok ? $max : 0,
			'max'      => $max,
			'modified' => $modified,
			'fresh'    => $ok,
		);
	}

	/**
	 * Flatten term groups for averaging.
	 *
	 * @param array $terms terms[taxonomy][] .
	 * @return array
	 */
	private function flatten_terms( array $terms ): array {
		$flat = array();
		foreach ( $terms as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}
			foreach ( $group as $term ) {
				$flat[] = $term;
			}
		}
		return $flat;
	}

	/**
	 * Average analysis.score of a list; analyze on the fly when missing.
	 *
	 * @param array  $items Items.
	 * @param string $kind  articles|scientists|terms|pages|images.
	 * @return int
	 */
	private function average_score( array $items, string $kind ): int {
		if ( empty( $items ) ) {
			return 0;
		}

		$sum = 0;
		$n   = 0;
		foreach ( $items as $item ) {
			if ( isset( $item['analysis']['score'] ) ) {
				$sum += (int) $item['analysis']['score'];
				$n++;
				continue;
			}
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( 'articles' === $kind ) {
				$analysis = $this->analyze_article( $item );
			} elseif ( 'scientists' === $kind ) {
				$analysis = $this->analyze_scientist( $item );
			} elseif ( 'terms' === $kind ) {
				$tax      = isset( $item['taxonomy'] ) ? (string) $item['taxonomy'] : 'quantum_category';
				$analysis = $this->analyze_term( $item, $tax );
			} elseif ( 'pages' === $kind ) {
				$analysis = $this->analyze_page( $item );
			} else {
				$analysis = $this->analyze_image( $item );
			}
			$sum += (int) $analysis['score'];
			$n++;
		}

		return $n > 0 ? (int) round( $sum / $n ) : 0;
	}

	/**
	 * Human group label.
	 *
	 * @param string $key Group key.
	 * @return string
	 */
	private function group_label( string $key ): string {
		$map = array(
			'articles'   => __( 'Articles', 'qpedia-seo-pro' ),
			'scientists' => __( 'Scientists', 'qpedia-seo-pro' ),
			'terms'      => __( 'Terms', 'qpedia-seo-pro' ),
			'pages'      => __( 'Pages', 'qpedia-seo-pro' ),
			'images'     => __( 'Images', 'qpedia-seo-pro' ),
		);
		return isset( $map[ $key ] ) ? $map[ $key ] : $key;
	}

	/**
	 * Load article payload from ID or array.
	 *
	 * @param mixed $post_or_array Input.
	 * @return array
	 */
	private function resolve_article( $post_or_array ): array {
		if ( is_array( $post_or_array ) ) {
			return $post_or_array;
		}
		$id = (int) $post_or_array;
		if ( $id <= 0 ) {
			return array();
		}
		$item = Collector::instance()->collect_article( $id );
		return is_array( $item ) ? $item : array();
	}

	/**
	 * Load scientist payload from ID or array.
	 *
	 * @param mixed $post_or_array Input.
	 * @return array
	 */
	private function resolve_scientist( $post_or_array ): array {
		if ( is_array( $post_or_array ) ) {
			return $post_or_array;
		}
		$id = (int) $post_or_array;
		if ( $id <= 0 ) {
			return array();
		}
		$item = Collector::instance()->collect_scientist( $id );
		return is_array( $item ) ? $item : array();
	}

	/**
	 * Load page payload from ID or array.
	 *
	 * @param mixed $post_or_array Input.
	 * @return array
	 */
	private function resolve_page( $post_or_array ): array {
		if ( is_array( $post_or_array ) ) {
			return $post_or_array;
		}
		$id = (int) $post_or_array;
		if ( $id <= 0 ) {
			return array();
		}
		$item = Collector::instance()->collect_page( $id );
		return is_array( $item ) ? $item : array();
	}

	/**
	 * Load term payload from ID or array.
	 *
	 * @param mixed  $term_or_array Input.
	 * @param string $taxonomy      Taxonomy.
	 * @return array
	 */
	private function resolve_term( $term_or_array, string $taxonomy ): array {
		if ( is_array( $term_or_array ) ) {
			return $term_or_array;
		}
		$id = (int) $term_or_array;
		if ( $id <= 0 ) {
			return array();
		}
		$item = Collector::instance()->collect_term( $id, $taxonomy );
		return is_array( $item ) ? $item : array();
	}

	/**
	 * Load image payload from ID or array.
	 *
	 * @param mixed $image_or_id Input.
	 * @return array
	 */
	private function resolve_image( $image_or_id ): array {
		if ( is_array( $image_or_id ) ) {
			return $image_or_id;
		}
		$id = (int) $image_or_id;
		if ( $id <= 0 ) {
			return array();
		}
		$item = Collector::instance()->collect_image( $id );
		return is_array( $item ) ? $item : array();
	}

	/**
	 * Empty analysis result.
	 *
	 * @param string $message Issue message.
	 * @return array
	 */
	private function empty_result( string $message ): array {
		return array(
			'score'       => 0,
			'grade'       => Core::grade( 0 ),
			'issues'      => array( $message ),
			'suggestions' => array(),
			'details'     => array(),
		);
	}

	/**
	 * String meta helper.
	 *
	 * @param array  $metas Meta map.
	 * @param string $key   Key.
	 * @return string
	 */
	private function meta_string( array $metas, string $key ): string {
		if ( ! isset( $metas[ $key ] ) ) {
			return '';
		}
		$value = $metas[ $key ];
		if ( is_array( $value ) ) {
			return trim( implode( ' ', $value ) );
		}
		return trim( (string) $value );
	}

	/**
	 * Whether FAQ schema meta is non-empty.
	 *
	 * @param mixed $raw Raw meta.
	 * @return bool
	 */
	private function has_faq( $raw ): bool {
		if ( empty( $raw ) ) {
			return false;
		}
		if ( is_array( $raw ) ) {
			return ! empty( $raw );
		}
		if ( is_string( $raw ) ) {
			$trim = trim( $raw );
			if ( '' === $trim || '[]' === $trim || '{}' === $trim || 'a:0:{}' === $trim || 'N;' === $trim ) {
				return false;
			}
			$maybe = maybe_unserialize( $trim );
			if ( is_array( $maybe ) ) {
				return ! empty( $maybe );
			}
			$json = json_decode( $trim, true );
			if ( is_array( $json ) ) {
				return ! empty( $json );
			}
			return true;
		}
		return true;
	}

	/**
	 * Keyword in haystack (comma-separated keywords supported).
	 *
	 * @param string $haystack Haystack.
	 * @param string $keyword  Keyword or list.
	 * @return bool
	 */
	private function contains_keyword( string $haystack, string $keyword ): bool {
		$haystack = trim( $haystack );
		$keyword  = trim( $keyword );
		if ( '' === $haystack || '' === $keyword ) {
			return false;
		}

		$parts = preg_split( '/[,،]+/u', $keyword );
		if ( ! is_array( $parts ) ) {
			$parts = array( $keyword );
		}

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' !== $part && Core::contains( $haystack, $part ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * First paragraph plain text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function first_paragraph( string $html ): string {
		if ( preg_match( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $match ) ) {
			return Core::plain_text( $match[1] );
		}
		$text  = Core::plain_text( $html );
		$parts = preg_split( '/[\r\n]+/u', $text );
		if ( is_array( $parts ) && isset( $parts[0] ) ) {
			return $parts[0];
		}
		return $text;
	}

	/**
	 * Extract latin tokens from a focus keyword.
	 *
	 * @param string $keyword Keyword.
	 * @return string
	 */
	private function latin_keyword( string $keyword ): string {
		if ( ! preg_match_all( '/[A-Za-z][A-Za-z0-9\-]*/', $keyword, $matches ) ) {
			return '';
		}
		return strtolower( implode( ' ', $matches[0] ) );
	}

	/**
	 * Whether slug contains any latin keyword token.
	 *
	 * @param string $slug     Slug.
	 * @param string $latin_kw Space-separated latin tokens.
	 * @return bool
	 */
	private function slug_has_latin_parts( string $slug, string $latin_kw ): bool {
		$tokens = preg_split( '/\s+/', $latin_kw, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $tokens ) ) {
			return false;
		}
		$slug = strtolower( $slug );
		foreach ( $tokens as $token ) {
			$token = strtolower( str_replace( ' ', '-', $token ) );
			if ( strlen( $token ) >= 3 && false !== strpos( $slug, $token ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Clamp 0–100.
	 *
	 * @param int $score Score.
	 * @return int
	 */
	private function clamp( int $score ): int {
		if ( $score < 0 ) {
			return 0;
		}
		if ( $score > 100 ) {
			return 100;
		}
		return $score;
	}
}
