<?php
/**
 * Taxonomy SEO auditor for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Audits quantum_category (28), post_tag, Persian slugs and hierarchy.
 */
class Taxonomy_SEO {

    /**
     * Singleton instance.
     *
     * @var Taxonomy_SEO|null
     */
    private static $instance = null;

    /**
     * Known Persian slug → suggested Latin slug.
     *
     * @var array
     */
    private $known_slugs = array(
        'اپتیک-و-آشکارسFromی-کوانتومی'                 => 'optics-quantum-detection',
        'ارتباطات-و-سختافزار-کوانتومی'               => 'quantum-communications-hardware',
        'سختافزار-و-ماده-کوانتومی'                   => 'quantum-hardware-matter',
        'ماده،-بنیانها-و-مفاهیم-کوانتومی'            => 'quantum-matter-foundations-concepts',
        'ذرات-بنیادی-و-کیهانشناسی-کوانتومی'          => 'elementary-particles-quantum-cosmology',
        'کیهانشناسی-و-فناوری-کوانتومی'               => 'quantum-cosmology-technology',
        'فرهنگ،-آینده-و-جامعه-کوانتومی'              => 'quantum-culture-future-society',
        'اپتیک-و-حالتهای-کوانتومی'                   => 'quantum-optics-states',
        'زیستشناسی،-پزشکی-و-ترمودیNameیک-کوان'        => 'quantum-biology-medicine-thermodynamics',
        'زیستشناسی-و-مبانی-کوانتومی'                 => 'quantum-biology-fundamentals',
        'مبانی-و-پارادوکسهای-کوانتومی'               => 'quantum-fundamentals-paradoxes',
        'ذرات-بنیادی-و-نظریه-میدان-کوانتومی'         => 'elementary-particles-quantum-field-theory',
        'پدیدهها-و-فناوری-کوانتومی'                  => 'quantum-phenomena-technology',
        'پدیدهها-و-ترمودیNameیک-کوانتومی'             => 'quantum-phenomena-thermodynamics',
        'ترمودیNameیک-و-اطلاعات-کوانتومی'             => 'quantum-thermodynamics-information',
    );

    /**
     * Word-level transliteration map.
     *
     * @var array
     */
    private $word_map = array(
        'اپتیک'         => 'optics',
        'آشکارسFromی'     => 'detection',
        'کوانتومی'      => 'quantum',
        'کوانتوم'       => 'quantum',
        'ارتباطات'      => 'communications',
        'سختافزار'      => 'hardware',
        'ماده'          => 'matter',
        'بنیانها'       => 'foundations',
        'مفاهیم'        => 'concepts',
        'ذرات'          => 'particles',
        'بنیادی'        => 'elementary',
        'کیهانشناسی'    => 'cosmology',
        'فناوری'        => 'technology',
        'فرهنگ'         => 'culture',
        'آینده'         => 'future',
        'جامعه'         => 'society',
        'حالتهای'       => 'states',
        'زیستشناسی'     => 'biology',
        'پزشکی'         => 'medicine',
        'ترمودیNameیک'   => 'thermodynamics',
        'مبانی'         => 'fundamentals',
        'پارادوکسهای'   => 'paradoxes',
        'نظریه'         => 'theory',
        'میدان'         => 'field',
        'پدیدهها'       => 'phenomena',
        'اطلاعات'       => 'information',
        'تاریخ'         => 'history',
        'آزمایشهای'     => 'experiments',
        'تفسیرها'       => 'interpretations',
        'فلسفه'         => 'philosophy',
        'نقد'           => 'critique',
        'شTo‌علم'       => 'pseudoscience',
        'شToعلم'        => 'pseudoscience',
        'رایانش'        => 'computing',
        'روزمره'        => 'everyday',
    );

    /**
     * Get singleton instance.
     *
     * @return Taxonomy_SEO
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
     * Audit all quantum_category terms.
     *
     * Accepts a Collector scan payload, a term list, or queries WordPress.
     *
     * @param array $scan Optional scan or term list.
     * @return array
     */
    public function audit_categories($scan = array()) {
        $terms = $this->collect_categories($scan);
        $items = array();
        $summary = array(
            'total'        => 0,
            'expected'     => 28,
            'empty'        => 0,
            'missing_desc' => 0,
            'persian_slug' => 0,
            'short_desc'   => 0,
        );

        foreach ($terms as $t) {
            $slug    = (string) $t['slug'];
            $desc    = $this->plain_text($t['description']);
            $count   = (int) $t['count'];
            $persian = $this->slug_is_persian($slug);
            $latin   = $persian ? $this->suggest_latin_slug($slug, $t['name']) : $slug;
            $issues  = array();

            if ($count < 1) {
                $issues[] = 'empty';
                $summary['empty']++;
            }
            if ($desc === '') {
                $issues[] = 'missing_description';
                $summary['missing_desc']++;
            } elseif ($this->word_count($desc) < 20) {
                $issues[] = 'short_description';
                $summary['short_desc']++;
            }
            if ($persian || !$this->is_latin_slug($slug)) {
                if ($persian) {
                    $issues[] = 'persian_slug';
                    $summary['persian_slug']++;
                }
            }

            $summary['total']++;
            $items[] = array(
                'id'                   => (int) $t['id'],
                'name'                 => $t['name'],
                'slug'                 => $slug,
                'description'          => $desc,
                'description_words'    => $this->word_count($desc),
                'count'                => $count,
                'parent'               => (int) $t['parent'],
                'slug_is_persian'      => $persian,
                'latin'                => !$persian && $this->is_latin_slug($slug),
                'suggested_latin_slug' => $latin,
                'archive_url'          => $t['url'],
                'issues'               => $issues,
            );
        }

        usort(
            $items,
            function ($a, $b) {
                return $a['id'] - $b['id'];
            }
        );

        return array(
            'generated_at' => current_time('mysql'),
            'summary'      => $summary,
            'items'        => $items,
        );
    }

    /**
     * Audit post_tag: unused (count 0) and duplicate fa/en pairs.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function audit_tags($scan = array()) {
        $tags = $this->collect_tags($scan);

        $unused   = array();
        $used     = array();
        $by_norm  = array();
        $by_latin = array();

        foreach ($tags as $t) {
            $row = array(
                'id'    => (int) $t['id'],
                'name'  => $t['name'],
                'slug'  => $t['slug'],
                'count' => (int) $t['count'],
            );
            if ($row['count'] < 1) {
                $unused[] = $row;
            } else {
                $used[] = $row;
            }
            $norm = $this->norm_key($t['name']);
            if ($norm !== '') {
                if (!isset($by_norm[$norm])) {
                    $by_norm[$norm] = array();
                }
                $by_norm[$norm][] = $row;
            }
            $latin = $this->slug_is_persian($t['slug']) ? $this->suggest_latin_slug($t['slug'], $t['name']) : $t['slug'];
            $latin = $this->norm_key($latin);
            if ($latin !== '') {
                if (!isset($by_latin[$latin])) {
                    $by_latin[$latin] = array();
                }
                $by_latin[$latin][] = $row;
            }
        }

        $duplicates = array();
        foreach ($by_norm as $norm => $rows) {
            if (count($rows) > 1) {
                $duplicates[] = array(
                    'key'  => $norm,
                    'kind' => 'same_name',
                    'tags' => $rows,
                );
            }
        }
        foreach ($by_latin as $latin => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $has_fa = false;
            $has_en = false;
            foreach ($rows as $r) {
                if ($this->slug_is_persian($r['slug']) || $this->has_persian($r['name'])) {
                    $has_fa = true;
                } else {
                    $has_en = true;
                }
            }
            if ($has_fa && $has_en) {
                $duplicates[] = array(
                    'key'  => $latin,
                    'kind' => 'fa_en',
                    'tags' => $rows,
                );
            }
        }

        return array(
            'generated_at'    => current_time('mysql'),
            'total'           => count($tags),
            'used_count'      => count($used),
            'unused_count'    => count($unused),
            'unused'          => $unused,
            'duplicate_count' => count($duplicates),
            'duplicates'      => $duplicates,
            'note'            => __('WXR may not attach tags to posts; unused list uses term counts and relationships.', 'qpedia-seo-pro'),
        );
    }

    /**
     * Parent-child tree and depth.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function taxonomy_hierarchy($scan = array()) {
        $terms = $this->collect_categories($scan);
        $by_id = array();
        foreach ($terms as $t) {
            $by_id[(int) $t['id']] = array(
                'id'       => (int) $t['id'],
                'name'     => $t['name'],
                'slug'     => $t['slug'],
                'parent'   => (int) $t['parent'],
                'count'    => (int) $t['count'],
                'children' => array(),
            );
        }

        $roots = array();
        foreach ($by_id as $id => $node) {
            $pid = $node['parent'];
            if ($pid && isset($by_id[$pid])) {
                $by_id[$pid]['children'][] = $id;
            } else {
                $roots[] = $id;
            }
        }

        $tree      = array();
        $max_depth = 0;
        $deep      = array();
        foreach ($roots as $rid) {
            $tree[] = $this->build_branch($by_id, $rid, 1, $max_depth, $deep);
        }

        return array(
            'generated_at' => current_time('mysql'),
            'root_count'   => count($roots),
            'max_depth'    => $max_depth,
            'too_deep'     => $deep,
            'tree'         => $tree,
        );
    }

    /**
     * Whether a slug is Latin kebab-case.
     *
     * @param string $slug Slug.
     * @return bool
     */
    public function is_latin_slug($slug) {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $slug);
    }

    /**
     * Slug contains Persian letters.
     *
     * @param string $slug Slug.
     * @return bool
     */
    public function slug_is_persian($slug) {
        return (bool) preg_match('/\p{Arabic}/u', (string) $slug);
    }

    /**
     * Suggest a Latin kebab-case slug.
     *
     * @param string $slug Original slug.
     * @param string $name Term name.
     * @return string
     */
    public function suggest_latin_slug($slug, $name = '') {
        if (isset($this->known_slugs[$slug])) {
            return $this->known_slugs[$slug];
        }
        $source = $slug !== '' ? $slug : $name;
        $source = str_replace(array('،', ',', '؛', ';'), '-', $source);
        $parts  = preg_split('/[-_]+/u', $source);
        $out    = array();
        if (is_array($parts)) {
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '' || in_array($p, array('و', 'یا'), true)) {
                    if ($p === 'و') {
                        $out[] = 'and';
                    }
                    continue;
                }
                if (isset($this->word_map[$p])) {
                    $out[] = $this->word_map[$p];
                    continue;
                }
                if (preg_match('/^[a-z0-9]+$/i', $p)) {
                    $out[] = strtolower($p);
                    continue;
                }
                $tr = $this->transliterate_word($p);
                if ($tr !== '') {
                    $out[] = $tr;
                }
            }
        }
        $slug_out = implode('-', array_filter($out));
        $slug_out = preg_replace('/-+/', '-', $slug_out);
        $slug_out = trim($slug_out, '-');
        if ($slug_out === '') {
            $slug_out = 'topic-' . substr(md5($slug . $name), 0, 8);
        }
        return $slug_out;
    }

    /**
     * Recursively build a tree node.
     *
     * @param array $by_id     Nodes.
     * @param int   $id        Current ID.
     * @param int   $depth     Depth.
     * @param int   $max_depth Max (by ref).
     * @param array $deep      Too-deep list (by ref).
     * @return array
     */
    private function build_branch($by_id, $id, $depth, &$max_depth, &$deep) {
        if ($depth > $max_depth) {
            $max_depth = $depth;
        }
        $node = $by_id[$id];
        if ($depth > 3) {
            $deep[] = array(
                'id'    => $id,
                'name'  => $node['name'],
                'depth' => $depth,
            );
        }
        $children = array();
        foreach ($node['children'] as $cid) {
            if (isset($by_id[$cid])) {
                $children[] = $this->build_branch($by_id, $cid, $depth + 1, $max_depth, $deep);
            }
        }
        return array(
            'id'       => $node['id'],
            'name'     => $node['name'],
            'slug'     => $node['slug'],
            'count'    => $node['count'],
            'depth'    => $depth,
            'children' => $children,
        );
    }

    /**
     * Collect quantum_category terms.
     *
     * @param array $scan Scan.
     * @return array
     */
    private function collect_categories($scan) {
        $from_scan = $this->terms_from_scan($scan, 'quantum_category');
        if (!empty($from_scan)) {
            return $from_scan;
        }
        $terms = get_terms(
            array(
                'taxonomy'   => 'quantum_category',
                'hide_empty' => false,
                'number'     => 0,
            )
        );
        if (is_wp_error($terms)) {
            return array();
        }
        $out = array();
        foreach ($terms as $term) {
            $out[] = $this->from_wp_term($term);
        }
        return $out;
    }

    /**
     * Collect post_tag terms.
     *
     * @param array $scan Scan.
     * @return array
     */
    private function collect_tags($scan) {
        $from_scan = $this->terms_from_scan($scan, 'post_tag');
        if (!empty($from_scan)) {
            return $from_scan;
        }
        $terms = get_terms(
            array(
                'taxonomy'   => 'post_tag',
                'hide_empty' => false,
                'number'     => 0,
            )
        );
        if (is_wp_error($terms)) {
            return array();
        }
        $out = array();
        foreach ($terms as $term) {
            $out[] = $this->from_wp_term($term);
        }
        return $out;
    }

    /**
     * Pull terms out of a Collector payload or a flat list.
     *
     * @param array  $scan     Scan or list.
     * @param string $taxonomy Taxonomy.
     * @return array
     */
    private function terms_from_scan($scan, $taxonomy) {
        if (!is_array($scan) || empty($scan)) {
            return array();
        }
        if (isset($scan['terms'][$taxonomy]) && is_array($scan['terms'][$taxonomy])) {
            return $this->map_scan_terms($scan['terms'][$taxonomy]);
        }
        $bucket = ($taxonomy === 'post_tag') ? 'tags' : 'categories';
        if (isset($scan[$bucket]) && is_array($scan[$bucket]) && $this->looks_like_terms($scan[$bucket])) {
            return $this->map_scan_terms($scan[$bucket]);
        }
        if ($this->looks_like_terms($scan)) {
            return $this->map_scan_terms($scan);
        }
        return array();
    }

    /**
     * Whether an array looks like a list of terms (not a full scan).
     *
     * @param array $arr Array.
     * @return bool
     */
    private function looks_like_terms($arr) {
        if (!is_array($arr) || empty($arr)) {
            return false;
        }
        $first = reset($arr);
        if ($first instanceof \WP_Term) {
            return true;
        }
        if (is_array($first) && (isset($first['slug']) || isset($first['term_id']) || isset($first['name']))) {
            return true;
        }
        return false;
    }

    /**
     * Map scan term rows.
     *
     * @param array $rows Rows.
     * @return array
     */
    private function map_scan_terms($rows) {
        $out = array();
        foreach ($rows as $row) {
            if ($row instanceof \WP_Term) {
                $out[] = $this->from_wp_term($row);
                continue;
            }
            if (!is_array($row)) {
                continue;
            }
            $id   = isset($row['term_id']) ? (int) $row['term_id'] : (isset($row['id']) ? (int) $row['id'] : 0);
            $slug = isset($row['slug']) ? $row['slug'] : '';
            $url  = '';
            if (!empty($row['url'])) {
                $url = $row['url'];
            } elseif (!empty($row['permalink'])) {
                $url = $row['permalink'];
            } else {
                $url = $this->topic_url($slug);
            }
            $out[] = array(
                'id'          => $id,
                'name'        => isset($row['name']) ? $row['name'] : '',
                'slug'        => $slug,
                'description' => isset($row['description']) ? $row['description'] : '',
                'count'       => isset($row['count']) ? (int) $row['count'] : 0,
                'parent'      => isset($row['parent']) ? (int) $row['parent'] : 0,
                'url'         => $url,
            );
        }
        return $out;
    }

    /**
     * Map WP_Term.
     *
     * @param \WP_Term $term Term.
     * @return array
     */
    private function from_wp_term($term) {
        $link = get_term_link($term);
        if (is_wp_error($link)) {
            $link = $this->topic_url($term->slug);
        }
        return array(
            'id'          => (int) $term->term_id,
            'name'        => $term->name,
            'slug'        => $term->slug,
            'description' => $term->description,
            'count'       => (int) $term->count,
            'parent'      => (int) $term->parent,
            'url'         => $link,
        );
    }

    /**
     * /topic/{slug}/ URL.
     *
     * @param string $slug Slug.
     * @return string
     */
    private function topic_url($slug) {
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'permalink_for')) {
            return Core::permalink_for($slug, 'topic');
        }
        $home = 'https://qpedia.ir';
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'home_url')) {
            $h = Core::home_url();
            if (is_string($h) && $h !== '') {
                $home = untrailingslashit($h);
            }
        }
        return trailingslashit($home) . 'topic/' . $slug . '/';
    }

    /**
     * Name contains Persian letters.
     *
     * @param string $name Name.
     * @return bool
     */
    private function has_persian($name) {
        return (bool) preg_match('/\p{Arabic}/u', (string) $name);
    }

    /**
     * Rough letter-level Persian transliteration.
     *
     * @param string $word Word.
     * @return string
     */
    private function transliterate_word($word) {
        $map = array(
            'آ' => 'a', 'ا' => 'a', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
            'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z',
            'ر' => 'r', 'ز' => 'z', 'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's',
            'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f',
            'ق' => 'gh', 'ک' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm', 'ن' => 'n',
            'و' => 'v', 'ه' => 'h', 'ی' => 'y', 'ئ' => 'y', 'ء' => '', 'ة' => 'h',
            'ك' => 'k', 'ي' => 'y', 'ى' => 'y', '۰' => '0', '۱' => '1', '۲' => '2',
            '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        );
        $out   = '';
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($chars)) {
            return sanitize_title($word);
        }
        foreach ($chars as $ch) {
            if (isset($map[$ch])) {
                $out .= $map[$ch];
            } elseif (preg_match('/[a-z0-9]/i', $ch)) {
                $out .= strtolower($ch);
            }
        }
        return $out;
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
        $s = preg_replace('/[^a-z0-9\p{Arabic}]+/u', '', $s);
        return $s;
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
