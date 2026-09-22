<?php
/**
 * Sidebar SEO analysis metabox for Qpedia post types.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metabox controller.
 */
class Metabox {

	/**
	 * Singleton.
	 *
	 * @var Metabox|null
	 */
	private static $instance = null;

	/**
	 * Get singleton and hook.
	 *
	 * @return Metabox
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Boot hook used by Core.
	 */
	public function init() {}

	/**
	 * Register the side metabox.
	 */
	public function add_meta_boxes() {
		foreach ( array( 'quantum_article', 'quantum_scientist' ) as $type ) {
			add_meta_box(
				'qpedia-seo-analysis',
				__( 'Qpedia SEO analysis', 'qpedia-seo-pro' ),
				array( $this, 'render' ),
				$type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render score, bar, issues, suggestions, reanalyze button.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$analysis = get_post_meta( $post->ID, Admin::ANALYSIS_META, true );
		if ( ! is_array( $analysis ) ) {
			$analysis = $this->analysis_from_scan( $post );
		}
		if ( ! is_array( $analysis ) ) {
			$analysis = array();
		}

		$score       = isset( $analysis['score'] ) ? (int) $analysis['score'] : 0;
		$grade       = isset( $analysis['grade'] ) ? (string) $analysis['grade'] : ( class_exists( __NAMESPACE__ . '\\Core' ) ? Core::grade( $score ) : 'F' );
		$issues      = isset( $analysis['issues'] ) && is_array( $analysis['issues'] ) ? $analysis['issues'] : array();
		$suggestions = isset( $analysis['suggestions'] ) && is_array( $analysis['suggestions'] ) ? $analysis['suggestions'] : array();
		$has         = ! empty( $analysis );

		echo '<div class="qpedia-metabox" data-post-id="' . esc_attr( (string) $post->ID ) . '">';

		if ( ! $has ) {
			echo '<p class="qpedia-metabox-empty">' . esc_html__( 'This post has not been analyzed yet.', 'qpedia-seo-pro' ) . '</p>';
		} else {
			echo '<div class="qpedia-metabox-score">';
			echo '<span class="qpedia-metabox-number">' . esc_html( (string) $score ) . '</span>';
			echo Admin::grade_badge( $grade ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			echo '<div class="qpedia-score-bar" aria-hidden="true"><span class="qpedia-score-bar-fill qpedia-grade-' . esc_attr( strtolower( $grade ) ) . '" style="width:' . esc_attr( (string) max( 0, min( 100, $score ) ) ) . '%"></span></div>';

			echo '<h4>' . esc_html__( 'Issues', 'qpedia-seo-pro' ) . '</h4>';
			if ( empty( $issues ) ) {
				echo '<p class="qpedia-ok">' . esc_html__( 'No issues found.', 'qpedia-seo-pro' ) . '</p>';
			} else {
				echo '<ul class="qpedia-issue-list">';
				foreach ( $issues as $issue ) {
					$sev = Admin::issue_severity( $issue );
					$msg = Admin::issue_message( $issue );
					if ( '' === $msg ) {
						continue;
					}
					echo '<li class="qpedia-sev-' . esc_attr( $sev ) . '">' . esc_html( $msg ) . '</li>';
				}
				echo '</ul>';
			}

			echo '<h4>' . esc_html__( 'Suggestions', 'qpedia-seo-pro' ) . '</h4>';
			if ( empty( $suggestions ) ) {
				echo '<p class="qpedia-ok">' . esc_html__( 'No suggestions.', 'qpedia-seo-pro' ) . '</p>';
			} else {
				echo '<ul class="qpedia-suggest-list">';
				foreach ( $suggestions as $s ) {
					$msg = Admin::issue_message( $s );
					if ( '' === $msg ) {
						continue;
					}
					echo '<li>' . esc_html( $msg ) . '</li>';
				}
				echo '</ul>';
			}
		}

		echo '<p class="qpedia-metabox-actions">';
		echo '<button type="button" class="button button-primary qpedia-reanalyze" data-post-id="' . esc_attr( (string) $post->ID ) . '">';
		echo esc_html__( 'Reanalyze', 'qpedia-seo-pro' );
		echo '</button></p>';
		echo '<p class="qpedia-metabox-status" hidden></p>';
		echo '</div>';
	}

	/**
	 * Pull last scan analysis for this post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array
	 */
	private function analysis_from_scan( $post ) {
		$scan = array();
		if ( class_exists( __NAMESPACE__ . '\\Core' ) && method_exists( Core::class, 'get_scan_data' ) ) {
			$got = Core::get_scan_data();
			if ( is_array( $got ) ) {
				$scan = $got;
			}
		}
		$key = ( 'quantum_scientist' === $post->post_type ) ? 'scientists' : 'articles';
		if ( empty( $scan[ $key ] ) || ! is_array( $scan[ $key ] ) ) {
			return array();
		}
		foreach ( $scan[ $key ] as $row ) {
			$id = Admin::row_id( $row );
			if ( $id !== (int) $post->ID ) {
				continue;
			}
			if ( isset( $row['analysis'] ) && is_array( $row['analysis'] ) ) {
				return $row['analysis'];
			}
			return array(
				'score'       => Admin::row_score( $row ),
				'grade'       => Admin::row_grade( $row ),
				'issues'      => Admin::row_issues( $row ),
				'suggestions' => isset( $row['suggestions'] ) ? $row['suggestions'] : array(),
			);
		}
		return array();
	}
}
