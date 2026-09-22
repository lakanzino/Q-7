<?php
/**
 * Dashboard stats assembler.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Prepares the $data array consumed by views/dashboard.php.
 */
class Dashboard {

	/**
	 * Singleton (Core boot calls instance()).
	 *
	 * @var Dashboard|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return Dashboard
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Optional boot hook.
	 */
	public function init() {}

	/**
	 * Build dashboard view payload from scan data.
	 *
	 * @return array
	 */
	public static function prepare() {
		$scan = array();
		if ( class_exists( __NAMESPACE__ . '\\Core' ) && method_exists( Core::class, 'get_scan_data' ) ) {
			$got = Core::get_scan_data();
			if ( is_array( $got ) ) {
				$scan = $got;
			}
		}
		if ( empty( $scan ) ) {
			$tmp = get_transient( 'qpedia_seo_pro_scan_data' );
			if ( is_array( $tmp ) ) {
				$scan = $tmp;
			}
		}

		$has_scan = ! empty( $scan ) && (
			! empty( $scan['last_scan'] )
			|| ! empty( $scan['collected_at'] )
			|| ! empty( $scan['summary']['analyzed_at'] )
			|| ! empty( $scan['articles'] )
			|| ! empty( $scan['scientists'] )
		);

		$score = Admin::site_score_int( $scan );
		$grade = '';
		if ( ! empty( $scan['summary']['site_grade'] ) ) {
			$grade = (string) $scan['summary']['site_grade'];
		} elseif ( ! empty( $scan['site_score']['grade'] ) ) {
			$grade = (string) $scan['site_score']['grade'];
		} elseif ( ! empty( $scan['grade'] ) && ! is_array( $scan['grade'] ) ) {
			$grade = (string) $scan['grade'];
		} elseif ( class_exists( __NAMESPACE__ . '\\Core' ) ) {
			$grade = Core::grade( $score );
		} else {
			$grade = Admin::instance()->grade( $score );
		}

		$articles   = ( ! empty( $scan['articles'] ) && is_array( $scan['articles'] ) ) ? $scan['articles'] : array();
		$scientists = ( ! empty( $scan['scientists'] ) && is_array( $scan['scientists'] ) ) ? $scan['scientists'] : array();
		$images     = ( ! empty( $scan['images'] ) && is_array( $scan['images'] ) ) ? $scan['images'] : array();
		$terms      = Admin::category_terms( $scan );
		$tag_n      = 0;
		if ( isset( $scan['counts']['tags'] ) ) {
			$tag_n = (int) $scan['counts']['tags'];
		} elseif ( isset( $scan['tags']['total'] ) ) {
			$tag_n = (int) $scan['tags']['total'];
		} elseif ( isset( $scan['terms']['post_tag'] ) ) {
			$tag_n = count( $scan['terms']['post_tag'] );
		} elseif ( isset( $scan['counts']['terms_tag'] ) ) {
			$tag_n = (int) $scan['counts']['terms_tag'];
		}

		$links_n = 0;
		if ( isset( $scan['counts']['links'] ) ) {
			$links_n = (int) $scan['counts']['links'];
		} elseif ( ! empty( $scan['links']['top_incoming'] ) ) {
			foreach ( $scan['links']['top_incoming'] as $row ) {
				$links_n += isset( $row['incoming'] ) ? (int) $row['incoming'] : 0;
			}
		} elseif ( ! empty( $scan['raw_links'] ) ) {
			$links_n = count( $scan['raw_links'] );
		}

		$counts = array(
			'articles'   => isset( $scan['counts']['articles'] ) ? (int) $scan['counts']['articles'] : count( $articles ),
			'scientists' => isset( $scan['counts']['scientists'] ) ? (int) $scan['counts']['scientists'] : count( $scientists ),
			'terms'      => isset( $scan['counts']['terms'] ) ? (int) $scan['counts']['terms'] : ( isset( $scan['counts']['terms_category'] ) ? (int) $scan['counts']['terms_category'] : count( $terms ) ),
			'tags'       => $tag_n,
			'images'     => isset( $scan['counts']['images'] ) ? (int) $scan['counts']['images'] : count( $images ),
			'links'      => $links_n,
			'pages'      => isset( $scan['counts']['pages'] ) ? (int) $scan['counts']['pages'] : ( ! empty( $scan['pages'] ) ? count( $scan['pages'] ) : 0 ),
		);

		$dist = array(
			'A' => 0,
			'B' => 0,
			'C' => 0,
			'D' => 0,
			'F' => 0,
		);
		if ( ! empty( $scan['score_distribution'] ) && is_array( $scan['score_distribution'] ) ) {
			$dist = array_merge( $dist, $scan['score_distribution'] );
		} elseif ( ! empty( $scan['summary']['grades'] ) && is_array( $scan['summary']['grades'] ) ) {
			$dist = array_merge( $dist, $scan['summary']['grades'] );
		} else {
			foreach ( array_merge( $articles, $scientists ) as $row ) {
				$g = Admin::row_grade( $row );
				if ( isset( $dist[ $g ] ) ) {
					$dist[ $g ]++;
				}
			}
		}

		$tallies = array(
			'critical' => 0,
			'high'     => 0,
			'medium'   => 0,
			'low'      => 0,
			'total'    => 0,
		);
		if ( ! empty( $scan['issue_tallies'] ) && is_array( $scan['issue_tallies'] ) ) {
			$tallies = array_merge( $tallies, $scan['issue_tallies'] );
		} elseif ( isset( $scan['summary']['issues_count'] ) ) {
			$tallies['total']  = (int) $scan['summary']['issues_count'];
			$tallies['medium'] = $tallies['total'];
		} else {
			foreach ( array_merge( $articles, $scientists, $terms ) as $row ) {
				foreach ( Admin::row_issues( $row ) as $issue ) {
					$sev = Admin::issue_severity( $issue );
					if ( ! isset( $tallies[ $sev ] ) ) {
						$sev = 'medium';
					}
					$tallies[ $sev ]++;
					$tallies['total']++;
				}
			}
		}

		$recent = array();
		if ( ! empty( $scan['recent_issues'] ) && is_array( $scan['recent_issues'] ) ) {
			$recent = $scan['recent_issues'];
		} else {
			foreach ( array_merge( $articles, $scientists ) as $row ) {
				foreach ( Admin::row_issues( $row ) as $issue ) {
					$recent[] = array(
						'severity'  => Admin::issue_severity( $issue ),
						'message'   => Admin::issue_message( $issue ),
						'title'     => isset( $row['title'] ) ? $row['title'] : ( isset( $row['name'] ) ? $row['name'] : '' ),
						'edit_link' => isset( $row['edit_link'] ) ? $row['edit_link'] : ( Admin::row_id( $row ) ? get_edit_post_link( Admin::row_id( $row ), 'raw' ) : '' ),
					);
					if ( count( $recent ) >= 8 ) {
						break 2;
					}
				}
			}
		}

		$last_scan = '';
		if ( ! empty( $scan['last_scan'] ) && is_string( $scan['last_scan'] ) ) {
			$last_scan = $scan['last_scan'];
		} elseif ( ! empty( $scan['summary']['analyzed_at'] ) ) {
			$last_scan = (string) $scan['summary']['analyzed_at'];
		} elseif ( ! empty( $scan['collected_at'] ) ) {
			$last_scan = (string) $scan['collected_at'];
		}

		$history = array();
		if ( ! empty( $scan['history'] ) && is_array( $scan['history'] ) ) {
			$history = $scan['history'];
		} else {
			$opt = get_option( 'qpedia_seo_pro_scan_history', array() );
			if ( is_array( $opt ) ) {
				$history = $opt;
			}
		}

		$spark = array();
		foreach ( $history as $point ) {
			if ( isset( $point['site_score'] ) ) {
				$spark[] = (int) $point['site_score'];
			} elseif ( isset( $point['score'] ) ) {
				$spark[] = (int) $point['score'];
			}
		}
		if ( empty( $spark ) && $has_scan ) {
			$spark[] = $score;
		}

		$circumference = 2 * M_PI * 52;
		$offset        = $circumference * ( 1 - min( 100, max( 0, $score ) ) / 100 );

		return array(
			'has_scan'           => $has_scan,
			'site_score'         => $score,
			'grade'              => strtoupper( $grade ),
			'counts'             => $counts,
			'issue_tallies'      => $tallies,
			'score_distribution' => $dist,
			'recent_issues'      => array_slice( $recent, 0, 8 ),
			'last_scan'          => $last_scan,
			'last_scan_display'  => $last_scan ? self::format_datetime( $last_scan ) : '',
			'history'            => $history,
			'spark'              => $spark,
			'ring_circumference' => $circumference,
			'ring_offset'        => $offset,
			'scan'               => $scan,
		);
	}

	/**
	 * Locale datetime.
	 *
	 * @param string $mysql Mysql datetime.
	 * @return string
	 */
	private static function format_datetime( $mysql ) {
		$ts = strtotime( $mysql );
		if ( ! $ts ) {
			return $mysql;
		}
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return date_i18n( $format, $ts );
	}
}
