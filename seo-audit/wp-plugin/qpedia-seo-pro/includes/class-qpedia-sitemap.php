<?php
/**
 * Sitemap auditor for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fetches, parses and compares XML sitemaps against published content.
 */
class Sitemap {

    /**
     * Singleton instance.
     *
     * @var Sitemap|null
     */
    private static $instance = null;

    /**
     * Default home URL.
     *
     * @var string
     */
    const SITE_URL = 'https://qpedia.ir';

    /**
     * Get singleton instance.
     *
     * @return Sitemap
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
     * Audit live sitemaps against published posts and terms.
     *
     * @return array
     */
    public function audit_sitemaps() {
        $home = $this->home_url();

        $targets = array(
            'sitemap_index.xml'           => trailingslashit($home) . 'sitemap_index.xml',
            'wp-sitemap.xml'              => trailingslashit($home) . 'wp-sitemap.xml',
            'quantum_category-sitemap.xml' => trailingslashit($home) . 'quantum_category-sitemap.xml',
        );

        $fetched = array();
        $all_locs = array();

        foreach ($targets as $key => $url) {
            $parsed = $this->fetch_and_parse($url);
            $fetched[$key] = $parsed;
            if (!empty($parsed['locs']) && is_array($parsed['locs'])) {
                foreach ($parsed['locs'] as $entry) {
                    $norm = $this->normalize_url($entry['loc']);
                    if ($norm === '') {
                        continue;
                    }
                    if (!isset($all_locs[$norm])) {
                        $all_locs[$norm] = array(
                            'loc'     => $entry['loc'],
                            'lastmod' => isset($entry['lastmod']) ? $entry['lastmod'] : '',
                            'sources' => array(),
                            'count'   => 0,
                        );
                    }
                    $all_locs[$norm]['count']++;
                    $all_locs[$norm]['sources'][] = $key;
                    if ($all_locs[$norm]['lastmod'] === '' && !empty($entry['lastmod'])) {
                        $all_locs[$norm]['lastmod'] = $entry['lastmod'];
                    }
                }
            }
        }

        $published = $this->collect_published_urls();
        $published_map = array();
        foreach ($published as $item) {
            $published_map[$item['norm']] = $item;
        }

        $missing   = array();
        $extra     = array();
        $duplicate = array();
        $lastmod   = array();

        foreach ($published as $item) {
            if (!isset($all_locs[$item['norm']])) {
                $missing[] = array(
                    'url'        => $item['url'],
                    'type'       => $item['type'],
                    'id'         => $item['id'],
                    'title'      => $item['title'],
                    'modified'   => $item['modified'],
                );
            } else {
                $entry = $all_locs[$item['norm']];
                $note  = $this->lastmod_note($entry['lastmod'], $item['modified']);
                if ($note !== '') {
                    $lastmod[] = array(
                        'url'      => $item['url'],
                        'type'     => $item['type'],
                        'id'       => $item['id'],
                        'lastmod'  => $entry['lastmod'],
                        'modified' => $item['modified'],
                        'note'     => $note,
                    );
                }
            }
        }

        foreach ($all_locs as $norm => $entry) {
            if ($entry['count'] > 1) {
                $duplicate[] = array(
                    'url'     => $entry['loc'],
                    'count'   => $entry['count'],
                    'sources' => array_values(array_unique($entry['sources'])),
                );
            }
            if (!isset($published_map[$norm]) && !$this->is_child_sitemap_url($entry['loc'])) {
                $extra[] = array(
                    'url'     => $entry['loc'],
                    'sources' => array_values(array_unique($entry['sources'])),
                    'lastmod' => $entry['lastmod'],
                );
            }
        }

        $availability = array();
        foreach ($fetched as $key => $parsed) {
            $availability[$key] = array(
                'url'          => $targets[$key],
                'ok'           => !empty($parsed['ok']),
                'status'       => isset($parsed['status']) ? (int) $parsed['status'] : 0,
                'error'        => isset($parsed['error']) ? $parsed['error'] : '',
                'url_count'    => isset($parsed['url_count']) ? (int) $parsed['url_count'] : 0,
                'is_index'     => !empty($parsed['is_index']),
                'child_fetched'=> isset($parsed['child_fetched']) ? (int) $parsed['child_fetched'] : 0,
            );
        }

        $issues = array();
        foreach ($availability as $key => $info) {
            if (!$info['ok']) {
                $issues[] = sprintf(
                    /* translators: 1: sitemap file, 2: HTTP status or error */
                    __('Sitemap %1$s is not reachable (%2$s).', 'qpedia-seo-pro'),
                    $key,
                    $info['status'] ? (string) $info['status'] : $info['error']
                );
            }
        }
        if (count($missing) > 0) {
            $issues[] = sprintf(
                /* translators: %d: number of missing URLs */
                __('%d published URLs are missing from sitemaps.', 'qpedia-seo-pro'),
                count($missing)
            );
        }
        if (count($duplicate) > 0) {
            $issues[] = sprintf(
                /* translators: %d: duplicate count */
                __('%d duplicate sitemap locations found.', 'qpedia-seo-pro'),
                count($duplicate)
            );
        }

        return array(
            'generated_at'   => current_time('mysql'),
            'home'           => $home,
            'availability'   => $availability,
            'published_count'=> count($published),
            'sitemap_count'  => count($all_locs),
            'missing'        => $missing,
            'extra'          => $extra,
            'duplicate'      => $duplicate,
            'lastmod_notes'  => $lastmod,
            'issues'         => $issues,
            'counts'         => array(
                'missing'   => count($missing),
                'extra'     => count($extra),
                'duplicate' => count($duplicate),
                'lastmod'   => count($lastmod),
            ),
        );
    }

    /**
     * Suggested optimal sitemap split with priority and changefreq.
     *
     * @return array
     */
    public function generate_optimal_sitemap() {
        $home = $this->home_url();

        $articles = get_posts(
            array(
                'post_type'      => 'quantum_article',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        $scientists = get_posts(
            array(
                'post_type'      => 'quantum_scientist',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $pages = get_posts(
            array(
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            )
        );
        $topics = get_terms(
            array(
                'taxonomy'   => 'quantum_category',
                'hide_empty' => false,
            )
        );
        if (is_wp_error($topics)) {
            $topics = array();
        }

        $files = array(
            'sitemap-articles.xml'   => array(
                'file'       => 'sitemap-articles.xml',
                'type'       => 'articles',
                'priority'   => 0.8,
                'changefreq' => 'weekly',
                'urls'       => array(),
            ),
            'sitemap-scientists.xml' => array(
                'file'       => 'sitemap-scientists.xml',
                'type'       => 'scientists',
                'priority'   => 0.7,
                'changefreq' => 'monthly',
                'urls'       => array(),
            ),
            'sitemap-topics.xml'     => array(
                'file'       => 'sitemap-topics.xml',
                'type'       => 'topics',
                'priority'   => 0.6,
                'changefreq' => 'weekly',
                'urls'       => array(),
            ),
            'sitemap-pages.xml'      => array(
                'file'       => 'sitemap-pages.xml',
                'type'       => 'pages',
                'priority'   => 0.5,
                'changefreq' => 'monthly',
                'urls'       => array(),
            ),
        );

        foreach ($articles as $post) {
            $files['sitemap-articles.xml']['urls'][] = $this->url_entry($this->permalink($post), $post->post_modified_gmt, 0.8, 'weekly');
        }
        foreach ($scientists as $post) {
            $files['sitemap-scientists.xml']['urls'][] = $this->url_entry($this->permalink($post), $post->post_modified_gmt, 0.7, 'monthly');
        }
        foreach ($topics as $term) {
            $files['sitemap-topics.xml']['urls'][] = $this->url_entry($this->term_permalink($term), '', 0.6, 'weekly');
        }
        foreach ($pages as $post) {
            $files['sitemap-pages.xml']['urls'][] = $this->url_entry($this->permalink($post), $post->post_modified_gmt, 0.5, 'monthly');
        }

        foreach ($files as $k => $file) {
            $files[$k]['count'] = count($file['urls']);
        }

        $index = array(
            'file'     => 'sitemap_index.xml',
            'children' => array(),
        );
        foreach ($files as $file) {
            $index['children'][] = array(
                'loc'     => trailingslashit($home) . $file['file'],
                'lastmod' => current_time('c'),
                'count'   => $file['count'],
            );
        }

        return array(
            'generated_at' => current_time('mysql'),
            'index'        => $index,
            'files'        => array_values($files),
            'totals'       => array(
                'articles'   => $files['sitemap-articles.xml']['count'],
                'scientists' => $files['sitemap-scientists.xml']['count'],
                'topics'     => $files['sitemap-topics.xml']['count'],
                'pages'      => $files['sitemap-pages.xml']['count'],
            ),
            'notes'        => array(
                __('Articles: priority 0.8, changefreq weekly.', 'qpedia-seo-pro'),
                __('Scientists: priority 0.7, changefreq monthly.', 'qpedia-seo-pro'),
                __('Topics: priority 0.6, changefreq weekly.', 'qpedia-seo-pro'),
                __('Pages: priority 0.5, changefreq monthly.', 'qpedia-seo-pro'),
            ),
        );
    }

    /**
     * Fetch a sitemap URL and parse loc/lastmod pairs. Follows sitemap indexes.
     *
     * @param string $url Sitemap URL.
     * @return array
     */
    public function fetch_and_parse($url) {
        $result = array(
            'ok'            => false,
            'status'        => 0,
            'error'         => '',
            'is_index'      => false,
            'locs'          => array(),
            'url_count'     => 0,
            'child_fetched' => 0,
        );

        $response = $this->remote_get($url);
        if (is_wp_error($response)) {
            $result['error'] = $response->get_error_message();
            return $result;
        }

        $result['status'] = (int) wp_remote_retrieve_response_code($response);
        $body             = wp_remote_retrieve_body($response);
        if ($result['status'] < 200 || $result['status'] >= 400 || $body === '') {
            $result['error'] = sprintf(
                /* translators: %d: HTTP status */
                __('HTTP %d or empty body.', 'qpedia-seo-pro'),
                $result['status']
            );
            return $result;
        }

        $parsed = $this->parse_xml_locs($body);
        $result['is_index'] = !empty($parsed['is_index']);
        $result['ok']       = true;

        if ($result['is_index']) {
            $child_locs = $parsed['locs'];
            $merged     = array();
            $fetched    = 0;
            foreach ($child_locs as $child) {
                if ($fetched >= 40) {
                    break;
                }
                $child_url = isset($child['loc']) ? $child['loc'] : '';
                if ($child_url === '') {
                    continue;
                }
                $child_res = $this->remote_get($child_url);
                $fetched++;
                if (is_wp_error($child_res)) {
                    continue;
                }
                $code = (int) wp_remote_retrieve_response_code($child_res);
                if ($code < 200 || $code >= 400) {
                    continue;
                }
                $child_parsed = $this->parse_xml_locs(wp_remote_retrieve_body($child_res));
                foreach ($child_parsed['locs'] as $loc) {
                    $merged[] = $loc;
                }
            }
            $result['child_fetched'] = $fetched;
            $result['locs']          = $merged;
        } else {
            $result['locs'] = $parsed['locs'];
        }

        $result['url_count'] = count($result['locs']);
        return $result;
    }

    /**
     * Parse sitemap XML into loc entries.
     *
     * @param string $xml Raw XML.
     * @return array
     */
    public function parse_xml_locs($xml) {
        $out = array(
            'is_index' => false,
            'locs'     => array(),
        );
        if (!is_string($xml) || trim($xml) === '') {
            return $out;
        }

        libxml_use_internal_errors(true);
        $sx = simplexml_load_string($xml);
        libxml_clear_errors();
        if (false === $sx) {
            if (preg_match_all('/<loc>\s*([^<]+)\s*<\/loc>/i', $xml, $m)) {
                foreach ($m[1] as $loc) {
                    $out['locs'][] = array(
                        'loc'     => trim(html_entity_decode($loc, ENT_QUOTES, 'UTF-8')),
                        'lastmod' => '',
                    );
                }
            }
            $out['is_index'] = (stripos($xml, '<sitemapindex') !== false);
            return $out;
        }

        $name = strtolower($sx->getName());
        $out['is_index'] = ($name === 'sitemapindex');

        $sx->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $nodes = $sx->xpath('//sm:url') ?: $sx->xpath('//url');
        if ($out['is_index']) {
            $nodes = $sx->xpath('//sm:sitemap') ?: $sx->xpath('//sitemap');
        }
        if (!is_array($nodes) || empty($nodes)) {
            $nodes = $sx->xpath('//sm:loc') ?: $sx->xpath('//loc');
            if (is_array($nodes)) {
                foreach ($nodes as $loc_node) {
                    $out['locs'][] = array(
                        'loc'     => trim((string) $loc_node),
                        'lastmod' => '',
                    );
                }
            }
            return $out;
        }

        foreach ($nodes as $node) {
            $loc     = '';
            $lastmod = '';
            if (isset($node->loc)) {
                $loc = trim((string) $node->loc);
            } else {
                $loc = trim((string) $node);
            }
            if (isset($node->lastmod)) {
                $lastmod = trim((string) $node->lastmod);
            }
            if ($loc === '') {
                continue;
            }
            $out['locs'][] = array(
                'loc'     => $loc,
                'lastmod' => $lastmod,
            );
        }

        return $out;
    }

    /**
     * Collect published article, scientist, page and topic URLs.
     *
     * @return array
     */
    public function collect_published_urls() {
        $items = array();

        $types = array(
            'quantum_article'   => 'article',
            'quantum_scientist' => 'scientist',
            'page'              => 'page',
        );
        foreach ($types as $post_type => $label) {
            $posts = get_posts(
                array(
                    'post_type'      => $post_type,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                )
            );
            foreach ($posts as $post) {
                $url = $this->permalink($post);
                $items[] = array(
                    'id'       => (int) $post->ID,
                    'type'     => $label,
                    'title'    => $post->post_title,
                    'url'      => $url,
                    'norm'     => $this->normalize_url($url),
                    'modified' => $post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified,
                );
            }
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'quantum_category',
                'hide_empty' => false,
            )
        );
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $url = $this->term_permalink($term);
                $items[] = array(
                    'id'       => (int) $term->term_id,
                    'type'     => 'topic',
                    'title'    => $term->name,
                    'url'      => $url,
                    'norm'     => $this->normalize_url($url),
                    'modified' => '',
                );
            }
        }

        return $items;
    }

    /**
     * Compare sitemap lastmod against post_modified.
     *
     * @param string $lastmod  Sitemap lastmod.
     * @param string $modified Post modified GMT/local.
     * @return string
     */
    private function lastmod_note($lastmod, $modified) {
        if ($lastmod === '') {
            return __('lastmod is missing.', 'qpedia-seo-pro');
        }
        $lm = strtotime($lastmod);
        if (!$lm) {
            return __('lastmod is not a valid date.', 'qpedia-seo-pro');
        }
        if ($lm > (time() + DAY_IN_SECONDS)) {
            return __('lastmod is in the future.', 'qpedia-seo-pro');
        }
        if ($modified !== '') {
            $pm = strtotime($modified . ' UTC');
            if (!$pm) {
                $pm = strtotime($modified);
            }
            if ($pm && ($pm - $lm) > DAY_IN_SECONDS) {
                return __('lastmod is older than post_modified.', 'qpedia-seo-pro');
            }
        }
        return '';
    }

    /**
     * Whether a loc points at another sitemap file rather than a public URL.
     *
     * @param string $url URL.
     * @return bool
     */
    private function is_child_sitemap_url($url) {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return false;
        }
        if (preg_match('/sitemap/i', $path) && preg_match('/\.xml(\.gz)?$/i', $path)) {
            return true;
        }
        if (strpos($path, 'wp-sitemap') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Build a sitemap URL entry.
     *
     * @param string $loc        URL.
     * @param string $modified   GMT datetime.
     * @param float  $priority   Priority.
     * @param string $changefreq Changefreq.
     * @return array
     */
    private function url_entry($loc, $modified, $priority, $changefreq) {
        $lastmod = '';
        if ($modified && $modified !== '0000-00-00 00:00:00') {
            $ts = strtotime($modified . ' UTC');
            if ($ts) {
                $lastmod = gmdate('c', $ts);
            }
        }
        return array(
            'loc'        => $loc,
            'lastmod'    => $lastmod,
            'priority'   => $priority,
            'changefreq' => $changefreq,
        );
    }

    /**
     * Permalink with fallback.
     *
     * @param \WP_Post $post Post.
     * @return string
     */
    private function permalink($post) {
        $link = get_permalink($post);
        if (is_string($link) && $link !== '') {
            return $link;
        }
        $home = $this->home_url();
        if ($post->post_type === 'quantum_scientist') {
            return trailingslashit($home) . 'scientists/' . $post->post_name . '/';
        }
        return trailingslashit($home) . $post->post_name . '/';
    }

    /**
     * Term permalink with /topic/{slug}/ fallback.
     *
     * @param \WP_Term $term Term.
     * @return string
     */
    private function term_permalink($term) {
        $link = get_term_link($term);
        if (!is_wp_error($link) && is_string($link) && $link !== '') {
            return $link;
        }
        return trailingslashit($this->home_url()) . 'topic/' . $term->slug . '/';
    }

    /**
     * Normalize URL for comparison.
     *
     * @param string $url URL.
     * @return string
     */
    private function normalize_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return strtolower(untrailingslashit($url));
        }
        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
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
     * Remote GET wrapper.
     *
     * @param string $url URL.
     * @return array|\WP_Error
     */
    private function remote_get($url) {
        return wp_remote_get(
            $url,
            array(
                'timeout'     => 20,
                'redirection' => 5,
                'sslverify'   => true,
                'headers'     => array(
                    'Accept' => 'application/xml,text/xml,application/xhtml+xml,*/*',
                ),
                'user-agent'  => 'QpediaSEOPro/1.0; ' . $this->home_url(),
            )
        );
    }

    /**
     * Home URL via Core when available.
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
