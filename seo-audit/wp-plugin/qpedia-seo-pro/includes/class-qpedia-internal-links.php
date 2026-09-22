<?php
/**
 * Internal link graph for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scans internal links, orphans, opportunities, anchors and a simple PageRank.
 */
class Internal_Links {

    /**
     * Singleton instance.
     *
     * @var Internal_Links|null
     */
    private static $instance = null;

    /**
     * Default home URL.
     *
     * @var string
     */
    const SITE_URL = 'https://qpedia.ir';

    /**
     * Cached last scan.
     *
     * @var array
     */
    private $last_scan = array();

    /**
     * Get singleton instance.
     *
     * @return Internal_Links
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
     * Parse HTML of all articles and scientists into a link graph.
     *
     * @param array $scan Optional collector payload.
     * @return array {edges, nodes, stats}
     */
    public function scan_all_internal_links($scan = array()) {
        $nodes = $this->collect_nodes($scan);
        $url_map = array();
        foreach ($nodes as $id => $node) {
            $url_map[$this->normalize_url($node['url'])] = $id;
            if (!empty($node['slug'])) {
                $url_map[$this->normalize_url($this->home_url() . '/' . $node['slug'] . '/')] = $id;
                if ($node['type'] === 'quantum_scientist') {
                    $url_map[$this->normalize_url($this->home_url() . '/scientists/' . $node['slug'] . '/')] = $id;
                }
            }
        }

        $home_host = $this->host($this->home_url());
        $edges     = array();

        foreach ($nodes as $from_id => $node) {
            $links = $this->extract_links($node['content']);
            foreach ($links as $link) {
                $url = $this->absolutize($link['url']);
                if (!$this->is_internal($url, $home_host)) {
                    continue;
                }
                if ($this->is_noise_path($url)) {
                    continue;
                }
                $norm = $this->normalize_url($url);
                $to   = isset($url_map[$norm]) ? $url_map[$norm] : 0;
                if ($to === $from_id) {
                    continue;
                }
                $edges[] = array(
                    'from_id'     => (int) $from_id,
                    'to_id'       => (int) $to,
                    'to_url'      => $url,
                    'anchor_text' => $link['anchor'],
                    'context'     => $link['context'],
                    'resolved'    => $to > 0,
                );
            }
        }

        $in  = array();
        $out = array();
        foreach ($nodes as $id => $node) {
            $in[$id]  = 0;
            $out[$id] = 0;
        }
        foreach ($edges as $e) {
            if ($e['from_id'] && isset($out[$e['from_id']])) {
                $out[$e['from_id']]++;
            }
            if ($e['to_id'] && isset($in[$e['to_id']])) {
                $in[$e['to_id']]++;
            }
        }

        $stats = array(
            'nodes'           => count($nodes),
            'edges'           => count($edges),
            'resolved_edges'  => 0,
            'unresolved'      => 0,
        );
        foreach ($edges as $e) {
            if ($e['resolved']) {
                $stats['resolved_edges']++;
            } else {
                $stats['unresolved']++;
            }
        }

        $this->last_scan = array(
            'generated_at' => current_time('mysql'),
            'nodes'        => $nodes,
            'edges'        => $edges,
            'in_count'     => $in,
            'out_count'    => $out,
            'stats'        => $stats,
        );
        return $this->last_scan;
    }

    /**
     * Pages with zero inbound internal links.
     *
     * @param array $scan Optional scan / graph.
     * @return array
     */
    public function find_orphan_pages($scan = array()) {
        $graph = $this->ensure_graph($scan);
        $orphans = array();
        foreach ($graph['nodes'] as $id => $node) {
            $in = isset($graph['in_count'][$id]) ? (int) $graph['in_count'][$id] : 0;
            if ($in === 0) {
                $orphans[] = array(
                    'id'        => (int) $id,
                    'title'     => $node['title'],
                    'type'      => $node['type'],
                    'url'       => $node['url'],
                    'out_count' => isset($graph['out_count'][$id]) ? (int) $graph['out_count'][$id] : 0,
                );
            }
        }
        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($orphans),
            'items'        => $orphans,
        );
    }

    /**
     * Suggest links between items that share a category or keyword.
     *
     * @param array $scan Optional scan / graph.
     * @param int   $limit Max suggestions.
     * @return array
     */
    public function find_link_opportunities($scan = array(), $limit = 200) {
        $graph = $this->ensure_graph($scan);
        $linked = array();
        foreach ($graph['edges'] as $e) {
            if ($e['from_id'] && $e['to_id']) {
                $linked[$e['from_id'] . ':' . $e['to_id']] = true;
            }
        }

        $opps  = array();
        $nodes = $graph['nodes'];
        $ids   = array_keys($nodes);

        foreach ($ids as $from) {
            $a = $nodes[$from];
            foreach ($ids as $to) {
                if ($from === $to) {
                    continue;
                }
                if (isset($linked[$from . ':' . $to])) {
                    continue;
                }
                $b      = $nodes[$to];
                $reasons = array();
                $score   = 0;

                $shared_cats = array_values(array_intersect($a['categories'], $b['categories']));
                if (!empty($shared_cats)) {
                    $reasons[] = 'shared_category';
                    $score    += 3 * count($shared_cats);
                }

                $shared_kw = array_values(array_intersect($a['keywords'], $b['keywords']));
                if (!empty($shared_kw)) {
                    $reasons[] = 'shared_keyword';
                    $score    += 4 * count($shared_kw);
                }

                if ($a['type'] === 'quantum_article' && $b['type'] === 'quantum_scientist') {
                    foreach ($b['keywords'] as $kw) {
                        if ($kw !== '' && $this->contains($a['title'] . ' ' . $a['content_plain'], $kw)) {
                            $reasons[] = 'scientist_concept_in_article';
                            $score    += 5;
                            break;
                        }
                    }
                }
                if ($a['type'] === 'quantum_scientist' && $b['type'] === 'quantum_article') {
                    foreach ($a['keywords'] as $kw) {
                        if ($kw !== '' && $this->contains($b['title'], $kw)) {
                            $reasons[] = 'article_matches_scientist_concept';
                            $score    += 5;
                            break;
                        }
                    }
                }

                if (empty($reasons)) {
                    continue;
                }

                $opps[] = array(
                    'from_id'    => (int) $from,
                    'from_title' => $a['title'],
                    'from_type'  => $a['type'],
                    'to_id'      => (int) $to,
                    'to_title'   => $b['title'],
                    'to_type'    => $b['type'],
                    'to_url'     => $b['url'],
                    'reasons'    => array_values(array_unique($reasons)),
                    'score'      => $score,
                    'suggestion' => sprintf(
                        /* translators: 1: source title, 2: target title */
                        __('«%1$s» To «%2$s» .', 'qpedia-seo-pro'),
                        $a['title'],
                        $b['title']
                    ),
                );
            }
        }

        usort(
            $opps,
            function ($x, $y) {
                if ($x['score'] === $y['score']) {
                    return 0;
                }
                return ($x['score'] > $y['score']) ? -1 : 1;
            }
        );

        if ($limit > 0 && count($opps) > $limit) {
            $opps = array_slice($opps, 0, $limit);
        }

        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($opps),
            'items'        => $opps,
        );
    }

    /**
     * Flag weak anchors and measure diversity.
     *
     * @param array $scan Optional graph.
     * @return array
     */
    public function analyze_anchor_texts($scan = array()) {
        $graph = $this->ensure_graph($scan);
        $weak_phrases = array(
            'اینجا Totalیک کنید',
            'Totalیک کنید',
            'اینجا',
            'read more',
            'click here',
            'more',
            'لینک',
            'ادامه مطلب',
            'بیشتر بخوانید',
            'این صفحه',
            'این مقاله',
        );

        $counts = array();
        $weak   = array();
        $empty  = 0;

        foreach ($graph['edges'] as $e) {
            $anchor = trim((string) $e['anchor_text']);
            $norm   = $this->norm_anchor($anchor);
            if ($norm === '') {
                $empty++;
                $weak[] = array_merge(
                    $e,
                    array(
                        'reason' => 'empty',
                        'flag'   => __('Empty anchor text.', 'qpedia-seo-pro'),
                    )
                );
                continue;
            }
            if (!isset($counts[$norm])) {
                $counts[$norm] = 0;
            }
            $counts[$norm]++;

            foreach ($weak_phrases as $phrase) {
                if ($norm === $this->norm_anchor($phrase) || $anchor === $phrase) {
                    $weak[] = array_merge(
                        $e,
                        array(
                            'reason' => 'generic',
                            'flag'   => sprintf(
                                /* translators: %s: phrase */
                                __('Generic anchor «%s».', 'qpedia-seo-pro'),
                                $phrase
                            ),
                        )
                    );
                    break;
                }
            }
        }

        arsort($counts);
        $unique = count($counts);
        $total  = count($graph['edges']);
        $top    = array();
        $i      = 0;
        foreach ($counts as $text => $n) {
            $top[] = array(
                'anchor' => $text,
                'count'  => $n,
            );
            $i++;
            if ($i >= 30) {
                break;
            }
        }

        return array(
            'generated_at'     => current_time('mysql'),
            'total_anchors'    => $total,
            'unique_anchors'   => $unique,
            'empty_anchors'    => $empty,
            'diversity'        => $total ? round($unique / $total, 3) : 0,
            'weak'             => $weak,
            'weak_count'       => count($weak),
            'top'              => $top,
        );
    }

    /**
     * In-count plus a few PageRank iterations.
     *
     * @param array $scan Optional graph.
     * @return array
     */
    public function link_distribution($scan = array()) {
        $graph = $this->ensure_graph($scan);
        $nodes = $graph['nodes'];
        $ids   = array_keys($nodes);
        $n     = count($ids);
        if ($n === 0) {
            return array(
                'generated_at' => current_time('mysql'),
                'items'        => array(),
                'top'          => array(),
            );
        }

        $index = array_flip($ids);
        $out_links = array();
        foreach ($ids as $id) {
            $out_links[$id] = array();
        }
        foreach ($graph['edges'] as $e) {
            if ($e['from_id'] && $e['to_id'] && isset($out_links[$e['from_id']]) && isset($index[$e['to_id']])) {
                $out_links[$e['from_id']][] = $e['to_id'];
            }
        }

        $d      = 0.85;
        $rank   = array();
        $init   = 1 / $n;
        foreach ($ids as $id) {
            $rank[$id] = $init;
        }
        for ($iter = 0; $iter < 12; $iter++) {
            $next = array();
            foreach ($ids as $id) {
                $next[$id] = (1 - $d) / $n;
            }
            foreach ($ids as $id) {
                $outs = array_values(array_unique($out_links[$id]));
                $c    = count($outs);
                if ($c === 0) {
                    $share = $d * $rank[$id] / $n;
                    foreach ($ids as $j) {
                        $next[$j] += $share;
                    }
                    continue;
                }
                $share = $d * $rank[$id] / $c;
                foreach ($outs as $to) {
                    $next[$to] += $share;
                }
            }
            $rank = $next;
        }

        $items = array();
        foreach ($ids as $id) {
            $items[] = array(
                'id'       => (int) $id,
                'title'    => $nodes[$id]['title'],
                'type'     => $nodes[$id]['type'],
                'url'      => $nodes[$id]['url'],
                'in_count' => isset($graph['in_count'][$id]) ? (int) $graph['in_count'][$id] : 0,
                'out_count'=> isset($graph['out_count'][$id]) ? (int) $graph['out_count'][$id] : 0,
                'pagerank' => round($rank[$id], 6),
            );
        }
        usort(
            $items,
            function ($a, $b) {
                if ($a['pagerank'] === $b['pagerank']) {
                    return $b['in_count'] - $a['in_count'];
                }
                return ($a['pagerank'] > $b['pagerank']) ? -1 : 1;
            }
        );

        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($items),
            'items'        => $items,
            'top'          => array_slice($items, 0, 20),
        );
    }

    /**
     * Ensure we have a graph (from last scan, provided graph, or a new scan).
     *
     * @param array $scan Scan or graph.
     * @return array
     */
    private function ensure_graph($scan) {
        if (is_array($scan) && isset($scan['edges']) && isset($scan['nodes'])) {
            return $scan;
        }
        if (!empty($this->last_scan['edges'])) {
            if (empty($scan) || !is_array($scan) || empty($scan['articles'])) {
                return $this->last_scan;
            }
        }
        return $this->scan_all_internal_links($scan);
    }

    /**
     * Collect article + scientist nodes.
     *
     * @param array $scan Scan data.
     * @return array id => node
     */
    private function collect_nodes($scan) {
        $nodes = array();
        if (is_array($scan) && (!empty($scan['articles']) || !empty($scan['scientists']))) {
            foreach (array('articles' => 'quantum_article', 'scientists' => 'quantum_scientist') as $bucket => $type) {
                if (empty($scan[$bucket]) || !is_array($scan[$bucket])) {
                    continue;
                }
                foreach ($scan[$bucket] as $row) {
                    $node = $this->node_from_scan($row, $type);
                    if ($node['id']) {
                        $nodes[$node['id']] = $node;
                    }
                }
            }
            if (!empty($nodes)) {
                return $nodes;
            }
        }

        foreach (array('quantum_article', 'quantum_scientist') as $type) {
            $posts = get_posts(
                array(
                    'post_type'      => $type,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                )
            );
            foreach ($posts as $post) {
                $nodes[(int) $post->ID] = $this->node_from_post($post);
            }
        }
        return $nodes;
    }

    /**
     * Node from collector row.
     *
     * @param array  $row  Row.
     * @param string $type Post type.
     * @return array
     */
    private function node_from_scan($row, $type) {
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
        $slug = isset($row['slug']) ? $row['slug'] : (isset($row['post_name']) ? $row['post_name'] : '');
        $url = isset($row['permalink']) ? $row['permalink'] : (isset($row['url']) ? $row['url'] : '');
        if ($url === '' && $id) {
            $url = get_permalink($id);
        }

        $cats = array();
        if (!empty($row['categories']) && is_array($row['categories'])) {
            foreach ($row['categories'] as $c) {
                if (is_array($c) && isset($c['name'])) {
                    $cats[] = $c['name'];
                } elseif (is_string($c)) {
                    $cats[] = $c;
                }
            }
        }

        $kws = array();
        $focus = '';
        if (isset($row['rank_math_focus_keyword'])) {
            $focus = $row['rank_math_focus_keyword'];
        } elseif (isset($meta['rank_math_focus_keyword'])) {
            $focus = $meta['rank_math_focus_keyword'];
        } elseif (isset($row['focus_keyword'])) {
            $focus = $row['focus_keyword'];
        } elseif (isset($meta['_qpedia_focus_keyphrase'])) {
            $focus = $meta['_qpedia_focus_keyphrase'];
        }
        $kws = array_merge($kws, $this->split_list((string) $focus));
        if (!empty($meta['_scientist_concepts'])) {
            $kws = array_merge($kws, $this->split_list((string) $meta['_scientist_concepts']));
        }
        if (!empty($row['concepts'])) {
            $kws = array_merge($kws, $this->split_list((string) $row['concepts']));
        }
        $kws = array_values(array_unique(array_filter(array_map('trim', $kws))));

        return array(
            'id'            => $id,
            'title'         => (string) $title,
            'type'          => $type,
            'slug'          => (string) $slug,
            'url'           => (string) $url,
            'content'       => (string) $content,
            'content_plain' => $this->plain_text($content),
            'categories'    => $cats,
            'keywords'      => $kws,
        );
    }

    /**
     * Node from WP_Post.
     *
     * @param \WP_Post $post Post.
     * @return array
     */
    private function node_from_post($post) {
        $cats = array();
        $terms = get_the_terms($post->ID, 'quantum_category');
        if (!is_wp_error($terms) && is_array($terms)) {
            foreach ($terms as $t) {
                $cats[] = $t->name;
            }
        }
        $kws = $this->split_list((string) get_post_meta($post->ID, 'rank_math_focus_keyword', true));
        if (empty($kws)) {
            $kws = $this->split_list((string) get_post_meta($post->ID, '_qpedia_focus_keyphrase', true));
        }
        $concepts = get_post_meta($post->ID, '_scientist_concepts', true);
        if (is_string($concepts) && $concepts !== '') {
            $kws = array_merge($kws, $this->split_list($concepts));
        }
        $kws = array_values(array_unique(array_filter($kws)));
        $link = get_permalink($post);

        return array(
            'id'            => (int) $post->ID,
            'title'         => $post->post_title,
            'type'          => $post->post_type,
            'slug'          => $post->post_name,
            'url'           => is_string($link) ? $link : '',
            'content'       => $post->post_content,
            'content_plain' => $this->plain_text($post->post_content),
            'categories'    => $cats,
            'keywords'      => $kws,
        );
    }

    /**
     * Extract anchors via Core or regex fallback.
     *
     * @param string $html HTML.
     * @return array
     */
    private function extract_links($html) {
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'extract_internal_links')) {
            $raw = Core::extract_internal_links($html);
            if (is_array($raw)) {
                $out = array();
                foreach ($raw as $item) {
                    if (is_array($item)) {
                        $out[] = array(
                            'url'     => isset($item['url']) ? $item['url'] : (isset($item['href']) ? $item['href'] : ''),
                            'anchor'  => isset($item['anchor']) ? $item['anchor'] : (isset($item['anchor_text']) ? $item['anchor_text'] : (isset($item['text']) ? $item['text'] : '')),
                            'context' => isset($item['context']) ? $item['context'] : '',
                        );
                    }
                }
                if (!empty($out)) {
                    return $out;
                }
            }
        }

        $links = array();
        if (!is_string($html) || $html === '') {
            return $links;
        }
        if (!preg_match_all('/<a\s[^>]*href\s*=\s*([\'"])(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return $links;
        }
        foreach ($m as $match) {
            $url    = $match[2][0];
            $anchor = trim($this->plain_text($match[3][0]));
            $pos    = isset($match[0][1]) ? (int) $match[0][1] : 0;
            $start  = max(0, $pos - 90);
            $snip   = $this->plain_text(substr($html, $start, 220));
            $links[] = array(
                'url'     => $url,
                'anchor'  => $anchor,
                'context' => $this->truncate($snip, 140),
            );
        }
        return $links;
    }

    /**
     * Split list by comma / newline / Arabic comma.
     *
     * @param string $raw Raw.
     * @return string[]
     */
    private function split_list($raw) {
        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }
        $parts = preg_split('/[,\n\r;،؛]+/u', $raw);
        if (!is_array($parts)) {
            return array();
        }
        $out = array();
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Internal host check.
     *
     * @param string $url  URL.
     * @param string $host Site host.
     * @return bool
     */
    private function is_internal($url, $host) {
        if ($url === '' || strpos($url, 'mailto:') === 0 || strpos($url, 'tel:') === 0 || strpos($url, '#') === 0) {
            return false;
        }
        if (isset($url[0]) && $url[0] === '/') {
            return true;
        }
        $h = $this->host($url);
        if ($h === '') {
            return true;
        }
        return $h === $host || $h === 'www.' . $host || 'www.' . $h === $host;
    }

    /**
     * Skip media / admin / feed paths.
     *
     * @param string $url URL.
     * @return bool
     */
    private function is_noise_path($url) {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }
        foreach (array('/wp-admin', '/wp-login', '/wp-content/uploads', '/feed', '/wp-json', '/xmlrpc') as $p) {
            if (strpos($path, $p) === 0 || strpos($path, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Host of a URL.
     *
     * @param string $url URL.
     * @return string
     */
    private function host($url) {
        $h = wp_parse_url($url, PHP_URL_HOST);
        $h = is_string($h) ? strtolower($h) : '';
        return preg_replace('/^www\./', '', $h);
    }

    /**
     * Make absolute against home.
     *
     * @param string $url URL.
     * @return string
     */
    private function absolutize($url) {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || $url === '#') {
            return '';
        }
        $hash = strpos($url, '#');
        if (false !== $hash) {
            $url = substr($url, 0, $hash);
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $home = $this->home_url();
        if (isset($url[0]) && $url[0] === '/') {
            return $home . $url;
        }
        return trailingslashit($home) . ltrim($url, '/');
    }

    /**
     * Normalize URL for map lookup.
     *
     * @param string $url URL.
     * @return string
     */
    private function normalize_url($url) {
        $url   = trim((string) $url);
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return strtolower(untrailingslashit($url));
        }
        $host = isset($parts['host']) ? strtolower($parts['host']) : $this->host($this->home_url());
        $host = preg_replace('/^www\./', '', $host);
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $path = '/' . ltrim($path, '/');
        if (substr($path, -1) !== '/') {
            if (!preg_match('/\.[a-z0-9]{2,5}$/i', $path)) {
                $path .= '/';
            }
        }
        return $host . $path;
    }

    /**
     * Normalize anchor for grouping.
     *
     * @param string $anchor Anchor.
     * @return string
     */
    private function norm_anchor($anchor) {
        $anchor = $this->plain_text($anchor);
        if (function_exists('mb_strtolower')) {
            $anchor = mb_strtolower($anchor, 'UTF-8');
        } else {
            $anchor = strtolower($anchor);
        }
        $anchor = preg_replace('/\s+/u', ' ', $anchor);
        return trim($anchor);
    }

    /**
     * Case-insensitive contains.
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
     * Truncate helper.
     *
     * @param string $text   Text.
     * @param int    $length Length.
     * @return string
     */
    private function truncate($text, $length) {
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }
            return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
        }
        if (strlen($text) <= $length) {
            return $text;
        }
        return rtrim(substr($text, 0, $length)) . '…';
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

    /**
     * Home URL.
     *
     * @return string
     */
    private function home_url() {
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'home_url')) {
            $url = Core::home_url();
            if (is_string($url) && $url !== '') {
                return untrailingslashit($url);
            }
        }
        return untrailingslashit(self::SITE_URL);
    }
}
