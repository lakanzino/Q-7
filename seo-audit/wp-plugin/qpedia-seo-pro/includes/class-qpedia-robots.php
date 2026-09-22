<?php
/**
 * robots.txt and crawlability auditor for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Parses robots.txt, proposes an optimal file, and checks redirect chains.
 */
class Robots {

    /**
     * Singleton instance.
     *
     * @var Robots|null
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
     * @return Robots
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
     * Fetch and parse robots.txt, then score directives.
     *
     * @return array
     */
    public function audit_robots() {
        $home = $this->home_url();
        $url  = trailingslashit($home) . 'robots.txt';

        $raw      = '';
        $status   = 0;
        $error    = '';
        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 15,
                'redirection' => 3,
                'sslverify'   => true,
                'user-agent'  => 'QpediaSEOPro/1.0; ' . $home,
            )
        );

        if (is_wp_error($response)) {
            $error = $response->get_error_message();
        } else {
            $status = (int) wp_remote_retrieve_response_code($response);
            $raw    = (string) wp_remote_retrieve_body($response);
            if ($status < 200 || $status >= 400) {
                $error = sprintf(
                    /* translators: %d: HTTP status */
                    __('HTTP %d when fetching robots.txt.', 'qpedia-seo-pro'),
                    $status
                );
            }
        }

        $parsed = $this->parse_robots($raw);
        $issues = array();

        if ($raw === '' || $error !== '') {
            $issues[] = array(
                'severity' => 'critical',
                'code'     => 'robots_unreachable',
                'message'  => $error ? $error : __('robots.txt is empty.', 'qpedia-seo-pro'),
            );
        }

        if (empty($parsed['sitemaps'])) {
            $issues[] = array(
                'severity' => 'high',
                'code'     => 'missing_sitemap',
                'message'  => __('No Sitemap directive in robots.txt.', 'qpedia-seo-pro'),
            );
        }

        $disallow_admin = $this->has_disallow($parsed, '/wp-admin');
        $disallow_login = $this->has_disallow($parsed, '/wp-login.php');
        if (!$disallow_admin) {
            $issues[] = array(
                'severity' => 'high',
                'code'     => 'wp_admin_not_disallowed',
                'message'  => __('Disallow /wp-admin/ is missing.', 'qpedia-seo-pro'),
            );
        }
        if (!$disallow_login) {
            $issues[] = array(
                'severity' => 'medium',
                'code'     => 'wp_login_not_disallowed',
                'message'  => __('Disallow /wp-login.php is missing.', 'qpedia-seo-pro'),
            );
        }

        $blocks_all = $this->blocks_entire_site($parsed);
        if ($blocks_all) {
            $issues[] = array(
                'severity' => 'critical',
                'code'     => 'disallow_all',
                'message'  => __('A Disallow: / rule blocks the entire site.', 'qpedia-seo-pro'),
            );
        }

        $allows_ajax = $this->has_allow($parsed, '/wp-admin/admin-ajax.php');
        if ($disallow_admin && !$allows_ajax) {
            $issues[] = array(
                'severity' => 'low',
                'code'     => 'admin_ajax_not_allowed',
                'message'  => __('Consider Allow: /wp-admin/admin-ajax.php.', 'qpedia-seo-pro'),
            );
        }

        $attachment_noindex = $this->attachment_noindex_status();
        if (empty($attachment_noindex['ok'])) {
            $issues[] = array(
                'severity' => 'medium',
                'code'     => 'attachment_indexable',
                'message'  => __('Attachment pages should be noindexed (or redirected to the file).', 'qpedia-seo-pro'),
                'data'     => $attachment_noindex,
            );
        }

        return array(
            'generated_at'         => current_time('mysql'),
            'url'                  => $url,
            'status'               => $status,
            'error'                => $error,
            'raw'                  => $raw,
            'parsed'               => $parsed,
            'has_sitemap'          => !empty($parsed['sitemaps']),
            'sitemaps'             => $parsed['sitemaps'],
            'disallow_wp_admin'    => $disallow_admin,
            'disallow_wp_login'    => $disallow_login,
            'allow_admin_ajax'     => $allows_ajax,
            'blocks_entire_site'   => $blocks_all,
            'attachment_noindex'   => $attachment_noindex,
            'issues'               => $issues,
            'proposed_robots_txt'  => $this->proposed_robots_txt(),
        );
    }

    /**
     * Follow redirect chain for /sitemap.xml → sitemap_index.xml and key URLs.
     *
     * @return array
     */
    public function audit_crawlability() {
        $home = $this->home_url();

        $targets = array(
            'home'          => $home . '/',
            'sitemap.xml'   => trailingslashit($home) . 'sitemap.xml',
            'sitemap_index' => trailingslashit($home) . 'sitemap_index.xml',
            'robots'        => trailingslashit($home) . 'robots.txt',
        );

        $article = get_posts(
            array(
                'post_type'      => 'quantum_article',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        if (!empty($article[0])) {
            $targets['sample_article'] = get_permalink($article[0]);
        }

        $checks = array();
        foreach ($targets as $key => $url) {
            $checks[$key] = $this->follow_redirects($url);
        }

        $sitemap_chain = $checks['sitemap.xml'];
        $hops          = isset($sitemap_chain['hops']) ? $sitemap_chain['hops'] : array();
        $final         = isset($sitemap_chain['final_url']) ? $sitemap_chain['final_url'] : '';
        $expected      = trailingslashit($home) . 'sitemap_index.xml';

        $notes = array();
        $ok    = true;

        if (!empty($sitemap_chain['error'])) {
            $ok      = false;
            $notes[] = $sitemap_chain['error'];
        } else {
            $final_norm = $this->normalize_url($final);
            $exp_norm   = $this->normalize_url($expected);
            if ($final_norm !== $exp_norm && $this->normalize_url($targets['sitemap.xml']) !== $final_norm) {
                $notes[] = sprintf(
                    /* translators: 1: final URL, 2: expected URL */
                    __('sitemap.xml ended at %1$s (expected %2$s).', 'qpedia-seo-pro'),
                    $final,
                    $expected
                );
            }
            if (count($hops) === 0 && $final_norm !== $exp_norm) {
                $notes[] = __('sitemap.xml does not redirect to sitemap_index.xml.', 'qpedia-seo-pro');
            }
            if (count($hops) > 1) {
                $ok      = false;
                $notes[] = sprintf(
                    /* translators: %d: hop count */
                    __('Redirect chain is longer than one hop (%d). Flatten it.', 'qpedia-seo-pro'),
                    count($hops)
                );
            }
            if (count($hops) === 1) {
                $notes[] = __('sitemap.xml 301 → sitemap_index.xml as expected.', 'qpedia-seo-pro');
            }
        }

        $important = array('home', 'sample_article', 'robots');
        foreach ($important as $key) {
            if (empty($checks[$key])) {
                continue;
            }
            $c = $checks[$key];
            if (!empty($c['error']) || (isset($c['final_status']) && (int) $c['final_status'] >= 400)) {
                $ok      = false;
                $notes[] = sprintf(
                    /* translators: 1: target key, 2: status */
                    __('%1$s is not crawlable (status %2$s).', 'qpedia-seo-pro'),
                    $key,
                    isset($c['final_status']) ? (string) $c['final_status'] : 'error'
                );
            }
            if (!empty($c['hops']) && count($c['hops']) > 2) {
                $notes[] = sprintf(
                    /* translators: 1: target, 2: hop count */
                    __('%1$s has a redirect chain of %2$d hops.', 'qpedia-seo-pro'),
                    $key,
                    count($c['hops'])
                );
            }
        }

        return array(
            'generated_at'           => current_time('mysql'),
            'ok'                     => $ok,
            'checks'                 => $checks,
            'sitemap_xml_chain'      => $sitemap_chain,
            'redirects_to_index'     => $this->normalize_url($final) === $this->normalize_url($expected),
            'notes'                  => $notes,
            'redirects_table'        => $this->flatten_redirects($checks),
        );
    }

    /**
     * Proposed optimal robots.txt contents.
     *
     * @return string
     */
    public function proposed_robots_txt() {
        $home     = $this->home_url();
        $sitemap  = trailingslashit($home) . 'sitemap_index.xml';
        $lines    = array(
            '# Qpedia SEO Pro — ' . $home,
            '# Generated suggestion — review before deploying.',
            'User-agent: *',
            'Disallow: /wp-admin/',
            'Allow: /wp-admin/admin-ajax.php',
            'Disallow: /wp-login.php',
            'Disallow: /xmlrpc.php',
            'Disallow: /?s=',
            'Disallow: /search/',
            'Disallow: *&s=',
            'Disallow: /feed/',
            'Disallow: */feed/',
            'Disallow: /comments/',
            'Disallow: /cgi-bin/',
            '',
            '# Attachment templates should not compete with the media file.',
            'Disallow: /attachment/',
            'Disallow: /*?attachment_id=',
            '',
            'Sitemap: ' . $sitemap,
            '',
        );
        return implode("\n", $lines);
    }

    /**
     * Parse robots.txt into groups, sitemaps and comments.
     *
     * @param string $raw File body.
     * @return array
     */
    public function parse_robots($raw) {
        $out = array(
            'groups'   => array(),
            'sitemaps' => array(),
            'comments' => 0,
            'unknown'  => array(),
        );
        if (!is_string($raw) || trim($raw) === '') {
            return $out;
        }

        $group = array(
            'user_agents' => array(),
            'rules'       => array(),
        );

        $lines = preg_split("/\r\n|\n|\r/", $raw);
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                if (!empty($group['user_agents']) || !empty($group['rules'])) {
                    $out['groups'][] = $group;
                    $group           = array(
                        'user_agents' => array(),
                        'rules'       => array(),
                    );
                }
                continue;
            }
            if (isset($trim[0]) && $trim[0] === '#') {
                $out['comments']++;
                continue;
            }
            $hash = strpos($trim, '#');
            if (false !== $hash) {
                $trim = trim(substr($trim, 0, $hash));
            }
            if (!preg_match('/^([A-Za-z-]+)\s*:\s*(.*)$/', $trim, $m)) {
                $out['unknown'][] = $trim;
                continue;
            }
            $dir  = strtolower($m[1]);
            $val  = trim($m[2]);
            if ($dir === 'user-agent') {
                if (!empty($group['rules']) && !empty($group['user_agents'])) {
                    $out['groups'][] = $group;
                    $group           = array(
                        'user_agents' => array(),
                        'rules'       => array(),
                    );
                }
                $group['user_agents'][] = $val;
            } elseif ($dir === 'disallow' || $dir === 'allow' || $dir === 'crawl-delay') {
                $group['rules'][] = array(
                    'directive' => $dir,
                    'value'     => $val,
                );
            } elseif ($dir === 'sitemap') {
                $out['sitemaps'][] = $val;
            } else {
                $out['unknown'][] = $trim;
            }
        }
        if (!empty($group['user_agents']) || !empty($group['rules'])) {
            $out['groups'][] = $group;
        }

        $out['sitemaps'] = array_values(array_unique($out['sitemaps']));
        return $out;
    }

    /**
     * Follow Location headers without letting WP auto-follow.
     *
     * @param string $url Start URL.
     * @param int    $max Max hops.
     * @return array
     */
    public function follow_redirects($url, $max = 8) {
        $hops        = array();
        $current     = $url;
        $seen        = array();
        $final_status = 0;
        $error       = '';

        for ($i = 0; $i <= $max; $i++) {
            $norm = $this->normalize_url($current);
            if (isset($seen[$norm])) {
                $error = __('Redirect loop detected.', 'qpedia-seo-pro');
                break;
            }
            $seen[$norm] = true;

            $response = wp_remote_head(
                $current,
                array(
                    'timeout'     => 15,
                    'redirection' => 0,
                    'sslverify'   => true,
                    'user-agent'  => 'QpediaSEOPro/1.0; ' . $this->home_url(),
                )
            );
            if (is_wp_error($response)) {
                $response = wp_remote_get(
                    $current,
                    array(
                        'timeout'     => 15,
                        'redirection' => 0,
                        'sslverify'   => true,
                        'user-agent'  => 'QpediaSEOPro/1.0; ' . $this->home_url(),
                    )
                );
            }
            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                break;
            }

            $status   = (int) wp_remote_retrieve_response_code($response);
            $location = wp_remote_retrieve_header($response, 'location');
            $final_status = $status;

            if ($status >= 300 && $status < 400 && is_string($location) && $location !== '') {
                $next = $this->absolutize($current, $location);
                $hops[] = array(
                    'from'   => $current,
                    'to'     => $next,
                    'status' => $status,
                );
                $current = $next;
                continue;
            }
            break;
        }

        return array(
            'start_url'    => $url,
            'final_url'    => $current,
            'final_status' => $final_status,
            'hops'         => $hops,
            'hop_count'    => count($hops),
            'error'        => $error,
        );
    }

    /**
     * Flatten redirect hops into a CSV-friendly table.
     *
     * @param array $checks Crawl checks.
     * @return array
     */
    private function flatten_redirects($checks) {
        $rows = array();
        foreach ($checks as $key => $check) {
            if (empty($check['hops'])) {
                $rows[] = array(
                    'target' => $key,
                    'from'   => isset($check['start_url']) ? $check['start_url'] : '',
                    'to'     => isset($check['final_url']) ? $check['final_url'] : '',
                    'status' => isset($check['final_status']) ? $check['final_status'] : '',
                    'hops'   => 0,
                );
                continue;
            }
            foreach ($check['hops'] as $hop) {
                $rows[] = array(
                    'target' => $key,
                    'from'   => $hop['from'],
                    'to'     => $hop['to'],
                    'status' => $hop['status'],
                    'hops'   => count($check['hops']),
                );
            }
        }
        return $rows;
    }

    /**
     * Whether any Disallow matches a path prefix.
     *
     * @param array  $parsed Parsed robots.
     * @param string $path   Path prefix.
     * @return bool
     */
    private function has_disallow($parsed, $path) {
        foreach ($parsed['groups'] as $group) {
            foreach ($group['rules'] as $rule) {
                if ($rule['directive'] !== 'disallow') {
                    continue;
                }
                $val = $rule['value'];
                if ($val === $path || strpos($val, $path) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Whether any Allow matches a path.
     *
     * @param array  $parsed Parsed robots.
     * @param string $path   Path.
     * @return bool
     */
    private function has_allow($parsed, $path) {
        foreach ($parsed['groups'] as $group) {
            foreach ($group['rules'] as $rule) {
                if ($rule['directive'] === 'allow' && strpos($rule['value'], $path) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Detect Disallow: / for a wildcard user-agent.
     *
     * @param array $parsed Parsed robots.
     * @return bool
     */
    private function blocks_entire_site($parsed) {
        foreach ($parsed['groups'] as $group) {
            $agents = array_map('strtolower', $group['user_agents']);
            $applies = empty($agents) || in_array('*', $agents, true);
            if (!$applies) {
                continue;
            }
            foreach ($group['rules'] as $rule) {
                if ($rule['directive'] === 'disallow' && $rule['value'] === '/') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Attachment noindex suggestion based on options / Rank Math / WP.
     *
     * @return array
     */
    private function attachment_noindex_status() {
        $rank_math = false;
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'is_rank_math_active')) {
            $rank_math = (bool) Core::is_rank_math_active();
        } elseif (defined('RANK_MATH_VERSION')) {
            $rank_math = true;
        }

        $wp_media_pages = get_option('wp_attachment_pages_enabled', null);

        $ok = false;
        $reason = __('Attachment pages may still be indexable.', 'qpedia-seo-pro');

        if ($wp_media_pages === '0' || $wp_media_pages === 0) {
            $ok     = true;
            $reason = __('WordPress attachment pages are disabled.', 'qpedia-seo-pro');
        } elseif ($rank_math) {
            $rm = get_option('rank-math-options-general', array());
            if (is_array($rm) && isset($rm['noindex_attachment']) && $rm['noindex_attachment'] === 'on') {
                $ok     = true;
                $reason = __('Rank Math noindexes attachment pages.', 'qpedia-seo-pro');
            } else {
                $reason = __('Enable Rank Math noindex for attachments, or disable attachment pages.', 'qpedia-seo-pro');
            }
        }

        return array(
            'ok'                    => $ok,
            'reason'                => $reason,
            'rank_math_active'      => $rank_math,
            'wp_attachment_pages'   => $wp_media_pages,
            'suggestion'            => __('Set attachment pages to noindex or redirect them to the media file.', 'qpedia-seo-pro'),
        );
    }

    /**
     * Resolve a possibly relative Location header.
     *
     * @param string $from Current URL.
     * @param string $to   Location.
     * @return string
     */
    private function absolutize($from, $to) {
        $to = trim($to);
        if ($to === '') {
            return $from;
        }
        if (preg_match('#^https?://#i', $to)) {
            return $to;
        }
        $parts = wp_parse_url($from);
        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $host   = isset($parts['host']) ? $parts['host'] : 'qpedia.ir';
        if (isset($to[0]) && $to[0] === '/') {
            return $scheme . '://' . $host . $to;
        }
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $dir  = preg_replace('#/[^/]*$#', '/', $path);
        return $scheme . '://' . $host . $dir . $to;
    }

    /**
     * Normalize URL for comparison.
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
