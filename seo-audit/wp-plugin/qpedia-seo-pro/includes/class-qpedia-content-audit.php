<?php
/**
 * Content auditor for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Thin content, duplicate titles/descriptions, cannibalization, headings, readability, drafts.
 */
class Content_Audit {

    /**
     * Singleton instance.
     *
     * @var Content_Audit|null
     */
    private static $instance = null;

    /**
     * Expected draft count from the site spec.
     *
     * @var int
     */
    const EXPECTED_DRAFTS = 93;

    /**
     * Get singleton instance.
     *
     * @return Content_Audit
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
    }

    /**
     * Articles under 300 (thin) or 800 (needs boost) words.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function audit_thin_content($scan = array()) {
        $posts = $this->collect_articles($scan);
        $thin  = array();
        $boost = array();

        foreach ($posts as $p) {
            $wc = isset($p['word_count']) ? (int) $p['word_count'] : $this->word_count($p['content']);
            $row = array(
                'id'         => (int) $p['id'],
                'title'      => $p['title'],
                'word_count' => $wc,
                'status'     => isset($p['status']) ? $p['status'] : 'publish',
                'permalink'  => isset($p['permalink']) ? $p['permalink'] : '',
            );
            if ($wc < 300) {
                $row['level'] = 'thin';
                $thin[]       = $row;
            } elseif ($wc < 800) {
                $row['level'] = 'needs_boost';
                $boost[]      = $row;
            }
        }

        return array(
            'generated_at'     => current_time('mysql'),
            'thin'             => $thin,
            'needs_boost'      => $boost,
            'thin_count'       => count($thin),
            'needs_boost_count'=> count($boost),
            'thresholds'       => array(
                'thin'  => 300,
                'boost' => 800,
            ),
        );
    }

    /**
     * Duplicate or near-duplicate titles (UTF-8 Levenshtein <= 3).
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function audit_duplicate_titles($scan = array()) {
        $posts = $this->collect_all_content($scan);
        $pairs = array();
        $n     = count($posts);
        for ($i = 0; $i < $n; $i++) {
            $a = $this->plain_text($posts[$i]['title']);
            for ($j = $i + 1; $j < $n; $j++) {
                $b = $this->plain_text($posts[$j]['title']);
                if ($a === '' || $b === '') {
                    continue;
                }
                if ($this->strlen($a) > 250 || $this->strlen($b) > 250) {
                    if ($a === $b) {
                        $dist = 0;
                    } else {
                        continue;
                    }
                } else {
                    $dist = $this->utf8_levenshtein($a, $b);
                }
                if ($dist <= 3) {
                    $pairs[] = array(
                        'a_id'     => (int) $posts[$i]['id'],
                        'a_title'  => $posts[$i]['title'],
                        'a_type'   => $posts[$i]['post_type'],
                        'b_id'     => (int) $posts[$j]['id'],
                        'b_title'  => $posts[$j]['title'],
                        'b_type'   => $posts[$j]['post_type'],
                        'distance' => $dist,
                    );
                }
            }
        }
        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($pairs),
            'pairs'        => $pairs,
        );
    }

    /**
     * Duplicate meta descriptions.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function audit_duplicate_descriptions($scan = array()) {
        $posts = $this->collect_all_content($scan);
        $map   = array();
        foreach ($posts as $p) {
            $desc = $this->plain_text($p['seo_description']);
            if ($desc === '') {
                continue;
            }
            $key = $this->norm_key($desc);
            if (!isset($map[$key])) {
                $map[$key] = array();
            }
            $map[$key][] = array(
                'id'          => (int) $p['id'],
                'title'       => $p['title'],
                'post_type'   => $p['post_type'],
                'description' => $desc,
            );
        }
        $groups = array();
        foreach ($map as $rows) {
            if (count($rows) > 1) {
                $groups[] = array(
                    'description' => $rows[0]['description'],
                    'count'       => count($rows),
                    'posts'       => $rows,
                );
            }
        }
        usort(
            $groups,
            function ($a, $b) {
                return $b['count'] - $a['count'];
            }
        );
        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($groups),
            'groups'       => $groups,
        );
    }

    /**
     * Same focus_keyword used on more than one post.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function audit_keyword_cannibalization($scan = array()) {
        $posts = $this->collect_all_content($scan);
        $map   = array();
        foreach ($posts as $p) {
            $kws = $this->split_list($p['focus_keyword']);
            if (empty($kws)) {
                continue;
            }
            $primary = $this->norm_key($kws[0]);
            if ($primary === '') {
                continue;
            }
            if (!isset($map[$primary])) {
                $map[$primary] = array(
                    'keyword' => $kws[0],
                    'posts'   => array(),
                );
            }
            $map[$primary]['posts'][] = array(
                'id'        => (int) $p['id'],
                'title'     => $p['title'],
                'post_type' => $p['post_type'],
                'permalink' => isset($p['permalink']) ? $p['permalink'] : '',
            );
        }
        $groups = array();
        foreach ($map as $g) {
            if (count($g['posts']) < 2) {
                continue;
            }
            $titles = array();
            foreach ($g['posts'] as $row) {
                $titles[] = $row['title'];
            }
            $groups[] = array(
                'keyword'  => $g['keyword'],
                'count'    => count($g['posts']),
                'posts'    => $g['posts'],
                'warning'  => sprintf(
                    /* translators: 1: keyword, 2: titles */
                    __('Internal competition between %2$s for keyword "%1$s".', 'qpedia-seo-pro'),
                    $g['keyword'],
                    implode(', ', $titles)
                ),
            );
        }
        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($groups),
            'groups'       => $groups,
        );
    }

    /**
     * Heading outline: single H1, no skipped levels.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function audit_heading_structure($scan = array()) {
        $posts   = $this->collect_articles($scan, true);
        $items   = array();
        $summary = array(
            'checked'        => 0,
            'multiple_h1'    => 0,
            'missing_h2'     => 0,
            'skipped_levels' => 0,
            'no_headings'    => 0,
        );

        foreach ($posts as $p) {
            $heads = $this->extract_headings($p['content']);
            $levels = array();
            foreach ($heads as $h) {
                $levels[] = (int) $h['level'];
            }
            $issues = array();
            $h1     = 0;
            $h2     = 0;
            foreach ($levels as $lv) {
                if ($lv === 1) {
                    $h1++;
                }
                if ($lv === 2) {
                    $h2++;
                }
            }
            if (empty($levels)) {
                $issues[] = 'no_headings';
                $summary['no_headings']++;
            }
            if ($h1 > 1) {
                $issues[] = 'multiple_h1';
                $summary['multiple_h1']++;
            }
            if ($h2 === 0 && $this->word_count($p['content']) >= 400) {
                $issues[] = 'missing_h2';
                $summary['missing_h2']++;
            }
            $skipped = $this->skipped_levels($levels);
            if ($skipped) {
                $issues[] = 'skipped_levels';
                $summary['skipped_levels']++;
            }
            $summary['checked']++;
            $items[] = array(
                'id'       => (int) $p['id'],
                'title'    => $p['title'],
                'headings' => $heads,
                'h1'       => $h1,
                'h2'       => $h2,
                'issues'   => $issues,
                'note'     => __('Theme typically renders the post title as H1; content should start at H2.', 'qpedia-seo-pro'),
            );
        }

        return array(
            'generated_at' => current_time('mysql'),
            'summary'      => $summary,
            'items'        => $items,
        );
    }

    /**
     * Persian readability: avg sentence length, ZWNJ usage, type/token ratio.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function audit_readability_persian($scan = array()) {
        $posts = $this->collect_articles($scan, true);
        $items = array();
        $sum_avg = 0;
        $sum_ttr = 0;
        $n       = 0;

        foreach ($posts as $p) {
            $plain = $this->plain_text($p['content']);
            $stats = $this->persian_stats($plain);
            $issues = array();
            if ($stats['avg_sentence_words'] > 28) {
                $issues[] = 'long_sentences';
            }
            if ($stats['word_count'] > 200 && $stats['zwnj_count'] < 3) {
                $issues[] = 'low_zwnj';
            }
            if ($stats['word_count'] > 200 && $stats['ttr'] < 0.35) {
                $issues[] = 'low_vocabulary_diversity';
            }
            $items[] = array(
                'id'                 => (int) $p['id'],
                'title'              => $p['title'],
                'word_count'         => $stats['word_count'],
                'sentence_count'     => $stats['sentence_count'],
                'avg_sentence_words' => $stats['avg_sentence_words'],
                'zwnj_count'         => $stats['zwnj_count'],
                'unique_words'       => $stats['unique_words'],
                'ttr'                => $stats['ttr'],
                'issues'             => $issues,
            );
            $sum_avg += $stats['avg_sentence_words'];
            $sum_ttr += $stats['ttr'];
            $n++;
        }

        return array(
            'generated_at' => current_time('mysql'),
            'averages'     => array(
                'avg_sentence_words' => $n ? round($sum_avg / $n, 2) : 0,
                'ttr'                => $n ? round($sum_ttr / $n, 3) : 0,
            ),
            'items'        => $items,
        );
    }

    /**
     * Draft quantum_article list. Spec expects 93.
     *
     * @return array
     */
    public function audit_draft_articles() {
        $q = new \WP_Query(
            array(
                'post_type'      => 'quantum_article',
                'post_status'    => 'draft',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            )
        );

        $items = array();
        foreach ($q->posts as $post) {
            $wc  = $this->word_count($post->post_content);
            $kw  = (string) get_post_meta($post->ID, 'rank_math_focus_keyword', true);
            if ($kw === '') {
                $kw = (string) get_post_meta($post->ID, '_qpedia_focus_keyphrase', true);
            }
            $worth = $this->draft_worth($post, $wc, $kw);
            $items[] = array(
                'id'         => (int) $post->ID,
                'title'      => $post->post_title,
                'date'       => $post->post_date,
                'modified'   => $post->post_modified,
                'word_count' => $wc,
                'keyword'    => $kw,
                'worth'      => $worth['score'],
                'reason'     => $worth['reason'],
            );
        }

        usort(
            $items,
            function ($a, $b) {
                if ($a['worth'] === $b['worth']) {
                    return strcmp($a['date'], $b['date']);
                }
                return ($a['worth'] > $b['worth']) ? -1 : 1;
            }
        );

        return array(
            'generated_at'    => current_time('mysql'),
            'count'           => count($items),
            'expected'        => self::EXPECTED_DRAFTS,
            'count_matches'   => count($items) === self::EXPECTED_DRAFTS,
            'items'           => $items,
        );
    }

    /**
     * Run every content audit.
     *
     * @param array $scan Scan.
     * @return array
     */
    public function audit_all($scan = array()) {
        return array(
            'thin'             => $this->audit_thin_content($scan),
            'duplicate_titles' => $this->audit_duplicate_titles($scan),
            'duplicate_desc'   => $this->audit_duplicate_descriptions($scan),
            'cannibalization'  => $this->audit_keyword_cannibalization($scan),
            'headings'         => $this->audit_heading_structure($scan),
            'readability'      => $this->audit_readability_persian($scan),
            'drafts'           => $this->audit_draft_articles(),
        );
    }

    /**
     * Collect published articles (and optionally scientists).
     *
     * @param array $scan            Scan.
     * @param bool  $include_scientists Include scientists.
     * @return array
     */
    private function collect_articles($scan, $include_scientists = false) {
        $all = $this->collect_all_content($scan);
        $out = array();
        foreach ($all as $p) {
            if ($p['post_type'] === 'quantum_article' || ($include_scientists && $p['post_type'] === 'quantum_scientist')) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Collect articles, scientists, pages from scan or WP.
     *
     * @param array $scan Scan.
     * @return array
     */
    private function collect_all_content($scan) {
        $items = array();
        if (is_array($scan) && (!empty($scan['articles']) || !empty($scan['scientists']) || !empty($scan['pages']))) {
            foreach (array('articles' => 'quantum_article', 'scientists' => 'quantum_scientist', 'pages' => 'page') as $bucket => $type) {
                if (empty($scan[$bucket]) || !is_array($scan[$bucket])) {
                    continue;
                }
                foreach ($scan[$bucket] as $row) {
                    $items[] = $this->from_scan_row($row, $type);
                }
            }
            if (!empty($items)) {
                return $items;
            }
        }

        foreach (array('quantum_article', 'quantum_scientist', 'page') as $type) {
            $posts = get_posts(
                array(
                    'post_type'      => $type,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                )
            );
            foreach ($posts as $post) {
                $items[] = $this->from_post($post);
            }
        }
        return $items;
    }

    /**
     * Map collector row.
     *
     * @param array  $row  Row.
     * @param string $type Type.
     * @return array
     */
    private function from_scan_row($row, $type) {
        $id = 0;
        if (isset($row['id'])) {
            $id = (int) $row['id'];
        } elseif (isset($row['ID'])) {
            $id = (int) $row['ID'];
        }
        $meta = array();
        if (isset($row['metas']) && is_array($row['metas'])) {
            $meta = $row['metas'];
        } elseif (isset($row['meta']) && is_array($row['meta'])) {
            $meta = $row['meta'];
        }
        $title = isset($row['title']) ? $row['title'] : (isset($row['post_title']) ? $row['post_title'] : '');
        $content = isset($row['content']) ? $row['content'] : (isset($row['post_content']) ? $row['post_content'] : '');
        $kw = '';
        foreach (array('rank_math_focus_keyword', 'focus_keyword', '_qpedia_focus_keyphrase') as $k) {
            if (!empty($row[$k])) {
                $kw = $row[$k];
                break;
            }
            if (!empty($meta[$k])) {
                $kw = $meta[$k];
                break;
            }
        }
        $desc = '';
        foreach (array('rank_math_description', 'seo_description', '_qpedia_meta_description') as $k) {
            if (!empty($row[$k])) {
                $desc = $row[$k];
                break;
            }
            if (!empty($meta[$k])) {
                $desc = $meta[$k];
                break;
            }
        }
        $wc = isset($row['word_count']) ? (int) $row['word_count'] : $this->word_count($content);
        $url = isset($row['permalink']) ? $row['permalink'] : (isset($row['url']) ? $row['url'] : '');
        return array(
            'id'              => $id,
            'title'           => (string) $title,
            'content'         => (string) $content,
            'post_type'       => isset($row['post_type']) ? $row['post_type'] : $type,
            'status'          => isset($row['status']) ? $row['status'] : 'publish',
            'permalink'       => (string) $url,
            'focus_keyword'   => (string) $kw,
            'seo_description' => (string) $desc,
            'word_count'      => $wc,
        );
    }

    /**
     * Map WP_Post.
     *
     * @param \WP_Post $post Post.
     * @return array
     */
    private function from_post($post) {
        $kw = (string) get_post_meta($post->ID, 'rank_math_focus_keyword', true);
        if ($kw === '') {
            $kw = (string) get_post_meta($post->ID, '_qpedia_focus_keyphrase', true);
        }
        $desc = (string) get_post_meta($post->ID, 'rank_math_description', true);
        if ($desc === '') {
            $desc = (string) get_post_meta($post->ID, '_qpedia_meta_description', true);
        }
        $link = get_permalink($post);
        return array(
            'id'              => (int) $post->ID,
            'title'           => $post->post_title,
            'content'         => $post->post_content,
            'post_type'       => $post->post_type,
            'status'          => $post->post_status,
            'permalink'       => is_string($link) ? $link : '',
            'focus_keyword'   => $kw,
            'seo_description' => $desc,
            'word_count'      => $this->word_count($post->post_content),
        );
    }

    /**
     * Score whether a draft is worth finishing.
     *
     * @param \WP_Post $post Post.
     * @param int      $wc   Word count.
     * @param string   $kw   Keyword.
     * @return array
     */
    private function draft_worth($post, $wc, $kw) {
        $score  = 0;
        $reason = array();
        if ($wc >= 400) {
            $score += 3;
            $reason[] = __('Substantial draft body already exists.', 'qpedia-seo-pro');
        } elseif ($wc >= 150) {
            $score += 1;
            $reason[] = __('Partial draft — finish the outline.', 'qpedia-seo-pro');
        }
        if ($kw !== '') {
            $score += 2;
            $reason[] = __('Has a focus keyword.', 'qpedia-seo-pro');
        }
        $thumb = (int) get_post_thumbnail_id($post->ID);
        if ($thumb) {
            $score += 1;
            $reason[] = __('Has a featured image.', 'qpedia-seo-pro');
        }
        $age_days = (time() - strtotime($post->post_date)) / DAY_IN_SECONDS;
        if ($age_days > 180 && $wc < 150) {
            $score -= 1;
            $reason[] = __('Old stub — confirm it is still relevant.', 'qpedia-seo-pro');
        }
        return array(
            'score'  => $score,
            'reason' => implode(' ', $reason),
        );
    }

    /**
     * Extract headings via Core or regex.
     *
     * @param string $html HTML.
     * @return array
     */
    private function extract_headings($html) {
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'extract_headings')) {
            $raw = Core::extract_headings($html);
            if (is_array($raw) && !empty($raw)) {
                $out = array();
                foreach ($raw as $h) {
                    if (is_array($h)) {
                        $out[] = array(
                            'level' => isset($h['level']) ? (int) $h['level'] : (isset($h['tag']) ? (int) preg_replace('/\D/', '', $h['tag']) : 0),
                            'text'  => isset($h['text']) ? $h['text'] : (isset($h['content']) ? $h['content'] : ''),
                        );
                    }
                }
                if (!empty($out)) {
                    return $out;
                }
            }
        }
        $out = array();
        if (!is_string($html) || $html === '') {
            return $out;
        }
        if (preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $hit) {
                $out[] = array(
                    'level' => (int) $hit[1],
                    'text'  => $this->plain_text($hit[2]),
                );
            }
        }
        return $out;
    }

    /**
     * Whether heading levels skip (e.g. H2 then H4).
     *
     * @param int[] $levels Levels in order.
     * @return bool
     */
    private function skipped_levels($levels) {
        if (empty($levels)) {
            return false;
        }
        $prev = $levels[0];
        foreach ($levels as $lv) {
            if ($lv > $prev + 1) {
                return true;
            }
            $prev = $lv;
        }
        return false;
    }

    /**
     * Persian readability stats.
     *
     * @param string $plain Plain text.
     * @return array
     */
    private function persian_stats($plain) {
        $zwnj = 0;
        if ($plain !== '') {
            $zwnj = substr_count($plain, "\xE2\x80\x8C");
            if (function_exists('mb_substr_count')) {
                $zwnj = mb_substr_count($plain, "\xE2\x80\x8C");
            }
        }
        $norm = str_replace("\xE2\x80\x8C", ' ', $plain);
        $norm = trim(preg_replace('/\s+/u', ' ', $norm));
        $words = $norm === '' ? array() : preg_split('/\s+/u', $norm);
        if (!is_array($words)) {
            $words = array();
        }
        $wc = count($words);

        $sentences = preg_split('/[.!?؟…]+/u', $plain);
        $sc        = 0;
        if (is_array($sentences)) {
            foreach ($sentences as $s) {
                if (trim($s) !== '') {
                    $sc++;
                }
            }
        }
        if ($sc < 1) {
            $sc = $wc ? 1 : 0;
        }

        $freq = array();
        foreach ($words as $w) {
            $k = $this->norm_key($w);
            if ($k === '') {
                continue;
            }
            if (!isset($freq[$k])) {
                $freq[$k] = 0;
            }
            $freq[$k]++;
        }
        $unique = count($freq);
        $ttr    = $wc ? round($unique / $wc, 3) : 0;

        return array(
            'word_count'         => $wc,
            'sentence_count'     => $sc,
            'avg_sentence_words' => $sc ? round($wc / $sc, 2) : 0,
            'zwnj_count'         => $zwnj,
            'unique_words'       => $unique,
            'ttr'                => $ttr,
        );
    }

    /**
     * UTF-8 Levenshtein (dynamic programming).
     *
     * @param string $a A.
     * @param string $b B.
     * @return int
     */
    private function utf8_levenshtein($a, $b) {
        if ($a === $b) {
            return 0;
        }
        $ca = $this->chars($a);
        $cb = $this->chars($b);
        $na = count($ca);
        $nb = count($cb);
        if ($na === 0) {
            return $nb;
        }
        if ($nb === 0) {
            return $na;
        }
        if (abs($na - $nb) > 3 && min($na, $nb) > 8) {
            return 99;
        }
        $prev = range(0, $nb);
        for ($i = 1; $i <= $na; $i++) {
            $cur    = array();
            $cur[0] = $i;
            $minrow = $i;
            for ($j = 1; $j <= $nb; $j++) {
                $cost     = ($ca[$i - 1] === $cb[$j - 1]) ? 0 : 1;
                $cur[$j]  = min($cur[$j - 1] + 1, $prev[$j] + 1, $prev[$j - 1] + $cost);
                if ($cur[$j] < $minrow) {
                    $minrow = $cur[$j];
                }
            }
            if ($minrow > 3) {
                return $minrow;
            }
            $prev = $cur;
        }
        return (int) $prev[$nb];
    }

    /**
     * Split UTF-8 string into characters.
     *
     * @param string $s String.
     * @return string[]
     */
    private function chars($s) {
        if (function_exists('mb_str_split')) {
            $c = mb_str_split($s, 1, 'UTF-8');
            return is_array($c) ? $c : array();
        }
        if (function_exists('preg_split')) {
            $c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
            return is_array($c) ? $c : array();
        }
        return str_split($s);
    }

    /**
     * Split keywords.
     *
     * @param string $raw Raw.
     * @return string[]
     */
    private function split_list($raw) {
        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }
        $parts = preg_split('/[,\n\r;،؛]+/u', $raw);
        return is_array($parts) ? array_values(array_filter(array_map('trim', $parts))) : array();
    }

    /**
     * Normalize for grouping.
     *
     * @param string $s String.
     * @return string
     */
    private function norm_key($s) {
        $s = $this->plain_text($s);
        if (function_exists('mb_strtolower')) {
            $s = mb_strtolower($s, 'UTF-8');
        } else {
            $s = strtolower($s);
        }
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim($s);
    }

    /**
     * UTF-8 length.
     *
     * @param string $text Text.
     * @return int
     */
    private function strlen($text) {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen((string) $text, 'UTF-8');
        }
        return strlen((string) $text);
    }

    /**
     * Word count helper.
     *
     * @param string $html HTML.
     * @return int
     */
    private function word_count($html) {
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'word_count')) {
            return (int) Core::word_count($html);
        }
        $text = $this->plain_text($html);
        $text = str_replace("\xE2\x80\x8C", ' ', $text);
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return 0;
        }
        $parts = preg_split('/\s+/u', $text);
        return is_array($parts) ? count($parts) : 0;
    }

    /**
     * Plain text helper.
     *
     * @param string $html HTML.
     * @return string
     */
    private function plain_text($html) {
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'plain_text')) {
            return (string) Core::plain_text($html);
        }
        $text = is_string($html) ? $html : '';
        $text = wp_strip_all_tags($text, true);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
}
