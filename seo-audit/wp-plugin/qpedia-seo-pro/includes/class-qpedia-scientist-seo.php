<?php
/**
 * Scientist SEO module for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Completeness checklist, cross-references to articles, Person schema field gaps.
 */
class Scientist_SEO {

    /**
     * Singleton instance.
     *
     * @var Scientist_SEO|null
     */
    private static $instance = null;

    /**
     * Meta keys in the completeness spec.
     *
     * @var array
     */
    private $fields = array(
        'en_name'          => array('key' => '_scientist_en_name', 'optional' => false, 'label' => 'English name'),
        'fullname'         => array('key' => '_scientist_fullname', 'optional' => false, 'label' => 'Full name'),
        'born_died'        => array('key' => '_scientist_born_died', 'optional' => false, 'label' => 'Born–died', 'format' => 'dates'),
        'birthplace'       => array('key' => '_scientist_birthplace', 'optional' => false, 'label' => 'Birthplace'),
        'institutions'     => array('key' => '_scientist_institutions', 'optional' => false, 'label' => 'Institutions'),
        'achievement'      => array('key' => '_scientist_achievement', 'optional' => false, 'label' => 'Achievement', 'min_chars' => 50),
        'nobel'            => array('key' => '_scientist_nobel', 'optional' => true, 'label' => 'Nobel'),
        'concepts'         => array('key' => '_scientist_concepts', 'optional' => false, 'label' => 'Concepts', 'min_items' => 2),
        'family'           => array('key' => '_scientist_family', 'optional' => true, 'label' => 'Family'),
        'seo_title'        => array('key' => '_qpedia_seo_title', 'optional' => false, 'label' => 'SEO title', 'max_chars' => 60),
        'meta_description' => array('key' => '_qpedia_meta_description', 'optional' => false, 'label' => 'Meta description', 'min_chars' => 120, 'max_chars' => 160),
        'focus_keyphrase'  => array('key' => '_qpedia_focus_keyphrase', 'optional' => false, 'label' => 'Focus keyphrase'),
        'thumbnail'        => array('key' => '_thumbnail_id', 'optional' => false, 'label' => 'Featured image', 'type' => 'thumbnail'),
        'body'             => array('key' => 'post_content', 'optional' => false, 'label' => 'Body content', 'min_words' => 500),
    );

    /**
     * Get singleton instance.
     *
     * @return Scientist_SEO
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
     * Completeness checklist for every scientist.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function completeness_check($scan = array()) {
        $scientists = $this->collect_scientists($scan);
        $items      = array();
        $totals     = array(
            'count'          => 0,
            'complete'       => 0,
            'avg_percent'    => 0,
            'missing_counts' => array(),
        );
        foreach (array_keys($this->fields) as $fk) {
            $totals['missing_counts'][$fk] = 0;
        }

        $sum_pct = 0;
        foreach ($scientists as $s) {
            $check = $this->checklist_for($s);
            $items[] = $check;
            $totals['count']++;
            if ($check['complete']) {
                $totals['complete']++;
            }
            $sum_pct += $check['percent'];
            foreach ($check['checklist'] as $key => $ok) {
                if (!$ok && isset($totals['missing_counts'][$key]) && empty($this->fields[$key]['optional'])) {
                    $totals['missing_counts'][$key]++;
                }
            }
        }
        $totals['avg_percent'] = $totals['count'] ? round($sum_pct / $totals['count'], 1) : 0;

        usort(
            $items,
            function ($a, $b) {
                if ($a['percent'] === $b['percent']) {
                    return strcmp($a['title'], $b['title']);
                }
                return ($a['percent'] < $b['percent']) ? -1 : 1;
            }
        );

        return array(
            'generated_at' => current_time('mysql'),
            'totals'       => $totals,
            'items'        => $items,
        );
    }

    /**
     * Cross-reference scientists to articles via concepts / keywords / titles.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function cross_reference($scan = array()) {
        $scientists = $this->collect_scientists($scan);
        $articles   = $this->collect_articles($scan);
        $rows       = array();

        foreach ($scientists as $s) {
            $concepts = $this->split_list($s['concepts']);
            if ($s['en_name'] !== '') {
                $concepts[] = $s['en_name'];
            }
            if ($s['fullname'] !== '') {
                $concepts[] = $s['fullname'];
            }
            $concepts = array_values(array_unique(array_filter($concepts)));

            $matches = array();
            foreach ($articles as $a) {
                $hay = $a['title'] . ' ' . $a['keyword'] . ' ' . $this->truncate($a['content_plain'], 400);
                $hit = array();
                foreach ($concepts as $c) {
                    if ($this->strlen($c) < 3) {
                        continue;
                    }
                    if ($this->contains($hay, $c) || $this->contains($a['title'], $c)) {
                        $hit[] = $c;
                    }
                }
                if (!empty($hit)) {
                    $already = $this->article_links_to($a['content'], $s['id'], $s['url']);
                    $matches[] = array(
                        'article_id'    => (int) $a['id'],
                        'article_title' => $a['title'],
                        'article_url'   => $a['url'],
                        'matched_on'    => array_values(array_unique($hit)),
                        'already_linked'=> $already,
                    );
                }
            }

            $suggestions = array();
            foreach ($matches as $m) {
                if (empty($m['already_linked'])) {
                    $suggestions[] = sprintf(
                        /* translators: 1: article, 2: scientist */
                        __('Link to scientist "%2$s" in article "%1$s".', 'qpedia-seo-pro'),
                        $m['article_title'],
                        $s['title']
                    );
                }
            }

            $rows[] = array(
                'scientist_id'    => (int) $s['id'],
                'title'           => $s['title'],
                'concepts'        => $this->split_list($s['concepts']),
                'related_articles'=> $matches,
                'related_count'   => count($matches),
                'suggestions'     => $suggestions,
            );
        }

        return array(
            'generated_at' => current_time('mysql'),
            'items'        => $rows,
        );
    }

    /**
     * Which Person schema fields are empty per scientist.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function schema_completeness($scan = array()) {
        $scientists = $this->collect_scientists($scan);
        $parser     = class_exists(__NAMESPACE__ . '\\Schema') ? Schema::instance() : null;
        $items      = array();

        foreach ($scientists as $s) {
            $dates = array('birthDate' => null, 'deathDate' => null);
            if ($parser && method_exists($parser, 'parse_born_died')) {
                $dates = $parser->parse_born_died($s['born_died']);
            } else {
                $dates = $this->parse_born_died($s['born_died']);
            }

            $fields = array(
                'name'             => $s['fullname'] !== '' ? $s['fullname'] : $s['title'],
                'alternateName'    => $s['en_name'],
                'birthDate'        => !empty($dates['birthDate']) ? $dates['birthDate'] : '',
                'deathDate'        => !empty($dates['deathDate']) ? $dates['deathDate'] : '',
                'birthPlace'       => $s['birthplace'],
                'affiliation'      => $s['institutions'],
                'award'            => $s['nobel'],
                'description'      => $s['meta_description'],
                'knowsAbout'       => $s['concepts'],
                'image'            => $s['thumbnail_url'],
                'mainEntityOfPage' => $s['url'],
            );

            $empty    = array();
            $optional = array('deathDate', 'award');
            foreach ($fields as $k => $v) {
                if ($this->is_empty_field($v)) {
                    $empty[] = $k;
                }
            }
            $required_empty = array_values(array_diff($empty, $optional));
            $can_generate   = ($fields['name'] !== '' && $fields['mainEntityOfPage'] !== '');

            $items[] = array(
                'id'              => (int) $s['id'],
                'title'           => $s['title'],
                'can_generate'    => $can_generate,
                'fields'          => $fields,
                'empty'           => $empty,
                'required_empty'  => $required_empty,
                'fill_percent'    => round((count($fields) - count($empty)) / max(1, count($fields)) * 100, 1),
            );
        }

        return array(
            'generated_at' => current_time('mysql'),
            'items'        => $items,
        );
    }

    /**
     * Build the boolean checklist for one scientist.
     *
     * @param array $s Normalized scientist.
     * @return array
     */
    private function checklist_for($s) {
        $checklist = array();
        $required_ok = 0;
        $required_n  = 0;
        $notes       = array();

        foreach ($this->fields as $alias => $spec) {
            $ok = false;
            if ($alias === 'thumbnail') {
                $ok = $s['thumbnail_id'] > 0 && $s['thumbnail_alt'] !== '';
                if ($s['thumbnail_id'] > 0 && $s['thumbnail_alt'] === '') {
                    $notes[] = __('Featured image exists but alt text is empty.', 'qpedia-seo-pro');
                    $ok      = false;
                }
            } elseif ($alias === 'body') {
                $ok = $s['word_count'] >= (int) $spec['min_words'];
            } elseif ($alias === 'born_died') {
                $ok = $s['born_died'] !== '' && $this->dates_look_valid($s['born_died']);
                if ($s['born_died'] !== '' && !$ok) {
                    $notes[] = __('born_died present but format was not recognized.', 'qpedia-seo-pro');
                }
            } elseif ($alias === 'concepts') {
                $items = $this->split_list($s['concepts']);
                $ok    = count($items) >= (int) $spec['min_items'];
            } elseif ($alias === 'achievement') {
                $ok = $this->strlen($s['achievement']) >= (int) $spec['min_chars'];
            } elseif ($alias === 'seo_title') {
                $len = $this->strlen($s['seo_title']);
                $ok  = $s['seo_title'] !== '' && $len <= (int) $spec['max_chars'];
                if ($s['seo_title'] !== '' && $len > (int) $spec['max_chars']) {
                    $notes[] = __('SEO title is longer than 60 characters.', 'qpedia-seo-pro');
                    $ok      = false;
                }
            } elseif ($alias === 'meta_description') {
                $len = $this->strlen($s['meta_description']);
                $ok  = $s['meta_description'] !== '' && $len >= (int) $spec['min_chars'] && $len <= (int) $spec['max_chars'];
            } else {
                $map = array(
                    'en_name'         => 'en_name',
                    'fullname'        => 'fullname',
                    'birthplace'      => 'birthplace',
                    'institutions'    => 'institutions',
                    'nobel'           => 'nobel',
                    'family'          => 'family',
                    'focus_keyphrase' => 'focus_keyphrase',
                );
                $field = isset($map[$alias]) ? $map[$alias] : $alias;
                $ok    = isset($s[$field]) && trim((string) $s[$field]) !== '';
            }
            $checklist[$alias] = $ok;
            if (empty($spec['optional'])) {
                $required_n++;
                if ($ok) {
                    $required_ok++;
                }
            }
        }

        $percent = $required_n ? round($required_ok / $required_n * 100, 1) : 0;
        return array(
            'id'         => (int) $s['id'],
            'title'      => $s['title'],
            'url'        => $s['url'],
            'checklist'  => $checklist,
            'required_ok'=> $required_ok,
            'required_n' => $required_n,
            'percent'    => $percent,
            'complete'   => ($required_ok === $required_n),
            'word_count' => $s['word_count'],
            'notes'      => $notes,
        );
    }

    /**
     * Collect scientists from scan or WP.
     *
     * @param array $scan Scan.
     * @return array
     */
    private function collect_scientists($scan) {
        $out = array();
        if (is_array($scan) && !empty($scan['scientists']) && is_array($scan['scientists'])) {
            foreach ($scan['scientists'] as $row) {
                $out[] = $this->from_scan_row($row);
            }
            if (!empty($out)) {
                return $out;
            }
        }
        $posts = get_posts(
            array(
                'post_type'      => 'quantum_scientist',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        foreach ($posts as $post) {
            $out[] = $this->from_post($post);
        }
        return $out;
    }

    /**
     * Collect articles for cross-reference.
     *
     * @param array $scan Scan.
     * @return array
     */
    private function collect_articles($scan) {
        $out = array();
        if (is_array($scan) && !empty($scan['articles']) && is_array($scan['articles'])) {
            foreach ($scan['articles'] as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : (isset($row['ID']) ? (int) $row['ID'] : 0);
                $content = isset($row['content']) ? $row['content'] : (isset($row['post_content']) ? $row['post_content'] : '');
                $kw = '';
                if (!empty($row['rank_math_focus_keyword'])) {
                    $kw = $row['rank_math_focus_keyword'];
                } elseif (!empty($row['meta']['rank_math_focus_keyword'])) {
                    $kw = $row['meta']['rank_math_focus_keyword'];
                }
                $out[] = array(
                    'id'            => $id,
                    'title'         => isset($row['title']) ? $row['title'] : '',
                    'url'           => isset($row['permalink']) ? $row['permalink'] : (isset($row['url']) ? $row['url'] : ''),
                    'keyword'       => (string) $kw,
                    'content'       => (string) $content,
                    'content_plain' => $this->plain_text($content),
                );
            }
            if (!empty($out)) {
                return $out;
            }
        }
        $posts = get_posts(
            array(
                'post_type'      => 'quantum_article',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            )
        );
        foreach ($posts as $post) {
            $link = get_permalink($post);
            $out[] = array(
                'id'            => (int) $post->ID,
                'title'         => $post->post_title,
                'url'           => is_string($link) ? $link : '',
                'keyword'       => (string) get_post_meta($post->ID, 'rank_math_focus_keyword', true),
                'content'       => $post->post_content,
                'content_plain' => $this->plain_text($post->post_content),
            );
        }
        return $out;
    }

    /**
     * Map collector scientist row.
     *
     * @param array $row Row.
     * @return array
     */
    private function from_scan_row($row) {
        $id = isset($row['id']) ? (int) $row['id'] : (isset($row['ID']) ? (int) $row['ID'] : 0);
        $meta = array();
        if (isset($row['metas']) && is_array($row['metas'])) {
            $meta = $row['metas'];
        } elseif (isset($row['meta']) && is_array($row['meta'])) {
            $meta = $row['meta'];
        }
        $g = function ($keys) use ($row, $meta) {
            foreach ((array) $keys as $k) {
                if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) {
                    return $row[$k];
                }
                if (isset($meta[$k]) && $meta[$k] !== '' && $meta[$k] !== null) {
                    return $meta[$k];
                }
            }
            return '';
        };
        $thumb_id = (int) $g(array('_thumbnail_id', 'thumbnail_id'));
        $thumb_url = '';
        $thumb_alt = '';
        if (isset($row['thumbnail']) && is_array($row['thumbnail'])) {
            $thumb_url = isset($row['thumbnail']['url']) ? $row['thumbnail']['url'] : '';
            $thumb_alt = isset($row['thumbnail']['alt']) ? $row['thumbnail']['alt'] : '';
            if (!$thumb_id && !empty($row['thumbnail']['id'])) {
                $thumb_id = (int) $row['thumbnail']['id'];
            }
        }
        $content = (string) $g(array('content', 'post_content'));
        $wc      = isset($row['word_count']) ? (int) $row['word_count'] : $this->word_count($content);
        $url     = (string) $g(array('permalink', 'url'));
        return array(
            'id'               => $id,
            'title'            => (string) $g(array('title', 'post_title')),
            'url'              => $url,
            'content'          => $content,
            'word_count'       => $wc,
            'en_name'          => (string) $g(array('_scientist_en_name', 'en_name')),
            'fullname'         => (string) $g(array('_scientist_fullname', 'fullname')),
            'born_died'        => (string) $g(array('_scientist_born_died', 'born_died')),
            'birthplace'       => (string) $g(array('_scientist_birthplace', 'birthplace')),
            'institutions'     => (string) $g(array('_scientist_institutions', 'institutions')),
            'achievement'      => (string) $g(array('_scientist_achievement', 'achievement')),
            'nobel'            => (string) $g(array('_scientist_nobel', 'nobel')),
            'concepts'         => (string) $g(array('_scientist_concepts', 'concepts')),
            'family'           => (string) $g(array('_scientist_family', 'family')),
            'seo_title'        => (string) $g(array('_qpedia_seo_title', 'seo_title')),
            'meta_description' => (string) $g(array('_qpedia_meta_description', 'meta_description')),
            'focus_keyphrase'  => (string) $g(array('_qpedia_focus_keyphrase', 'focus_keyphrase')),
            'thumbnail_id'     => $thumb_id,
            'thumbnail_url'    => $thumb_url,
            'thumbnail_alt'    => $thumb_alt,
        );
    }

    /**
     * Map WP_Post scientist.
     *
     * @param \WP_Post $post Post.
     * @return array
     */
    private function from_post($post) {
        $id       = (int) $post->ID;
        $thumb_id = (int) get_post_thumbnail_id($id);
        $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'full') : '';
        $thumb_alt = $thumb_id ? (string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true) : '';
        $link     = get_permalink($post);
        return array(
            'id'               => $id,
            'title'            => $post->post_title,
            'url'              => is_string($link) ? $link : '',
            'content'          => $post->post_content,
            'word_count'       => $this->word_count($post->post_content),
            'en_name'          => (string) get_post_meta($id, '_scientist_en_name', true),
            'fullname'         => (string) get_post_meta($id, '_scientist_fullname', true),
            'born_died'        => (string) get_post_meta($id, '_scientist_born_died', true),
            'birthplace'       => (string) get_post_meta($id, '_scientist_birthplace', true),
            'institutions'     => (string) get_post_meta($id, '_scientist_institutions', true),
            'achievement'      => (string) get_post_meta($id, '_scientist_achievement', true),
            'nobel'            => (string) get_post_meta($id, '_scientist_nobel', true),
            'concepts'         => (string) get_post_meta($id, '_scientist_concepts', true),
            'family'           => (string) get_post_meta($id, '_scientist_family', true),
            'seo_title'        => (string) get_post_meta($id, '_qpedia_seo_title', true),
            'meta_description' => (string) get_post_meta($id, '_qpedia_meta_description', true),
            'focus_keyphrase'  => (string) get_post_meta($id, '_qpedia_focus_keyphrase', true),
            'thumbnail_id'     => $thumb_id,
            'thumbnail_url'    => $thumb_url ? $thumb_url : '',
            'thumbnail_alt'    => $thumb_alt,
        );
    }

    /**
     * Whether article HTML already links to the scientist.
     *
     * @param string $html HTML.
     * @param int    $id   Scientist ID.
     * @param string $url  Scientist URL.
     * @return bool
     */
    private function article_links_to($html, $id, $url) {
        if (!is_string($html) || $html === '') {
            return false;
        }
        if ($url && strpos($html, $url) !== false) {
            return true;
        }
        if (preg_match('/\/scientists\/[^"\'\s]+/i', $html) && $url && strpos($html, basename(untrailingslashit($url))) !== false) {
            return true;
        }
        return false;
    }

    /**
     * born_died looks like a year range.
     *
     * @param string $value Value.
     * @return bool
     */
    private function dates_look_valid($value) {
        $d = $this->parse_born_died($value);
        return !empty($d['birthDate']);
    }

    /**
     * Minimal born/died parser.
     *
     * @param string $value Value.
     * @return array
     */
    private function parse_born_died($value) {
        $fa = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
        $en = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $value = str_replace($fa, $en, (string) $value);
        $value = str_replace(array('–', '—', '−', '‐'), '-', $value);
        if (preg_match('/(\d{3,4})\s*-\s*(\d{3,4})/u', $value, $m)) {
            return array('birthDate' => $m[1], 'deathDate' => $m[2]);
        }
        if (preg_match('/(\d{3,4})/u', $value, $m)) {
            return array('birthDate' => $m[1], 'deathDate' => null);
        }
        return array('birthDate' => null, 'deathDate' => null);
    }

    /**
     * Empty schema field?
     *
     * @param mixed $v Value.
     * @return bool
     */
    private function is_empty_field($v) {
        if ($v === null || $v === false) {
            return true;
        }
        if (is_array($v)) {
            return empty($v);
        }
        return trim((string) $v) === '';
    }

    /**
     * Split list.
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
     * Contains helper.
     *
     * @param string $hay Haystack.
     * @param string $n   Needle.
     * @return bool
     */
    private function contains($hay, $n) {
        if ($n === '') {
            return false;
        }
        if (function_exists('mb_stripos')) {
            return false !== mb_stripos($hay, $n, 0, 'UTF-8');
        }
        return false !== stripos($hay, $n);
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
     * Truncate.
     *
     * @param string $text   Text.
     * @param int    $length Length.
     * @return string
     */
    private function truncate($text, $length) {
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }
            return mb_substr($text, 0, $length, 'UTF-8');
        }
        return substr($text, 0, $length);
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
