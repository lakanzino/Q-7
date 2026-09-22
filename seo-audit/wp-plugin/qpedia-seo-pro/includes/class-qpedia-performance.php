<?php
/**
 * Performance probes for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Measures response times and inspects caching / compression headers.
 */
class Performance {

    /**
     * Singleton instance.
     *
     * @var Performance|null
     */
    private static $instance = null;

    /**
     * Default home URL.
     *
     * @var string
     */
    const SITE_URL = 'https://qpedia.ir';

    /**
     * Option key for last measurement.
     *
     * @var string
     */
    const OPTION_KEY = 'qpedia_seo_pro_performance';

    /**
     * Get singleton instance.
     *
     * @return Performance
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
     * Measure TTFB-ish elapsed times for home, one article, one topic archive.
     *
     * @return array
     */
    public function measure_response_times() {
        $home = $this->home_url();
        $targets = array(
            'home'    => array(
                'label' => __('Home page', 'qpedia-seo-pro'),
                'url'   => $home . '/',
            ),
            'article' => array(
                'label' => __('Sample article', 'qpedia-seo-pro'),
                'url'   => '',
            ),
            'topic'   => array(
                'label' => __('Topic archive', 'qpedia-seo-pro'),
                'url'   => '',
            ),
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
            $targets['article']['url']   = get_permalink($article[0]);
            $targets['article']['title'] = $article[0]->post_title;
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'quantum_category',
                'hide_empty' => true,
                'number'     => 1,
            )
        );
        if (!is_wp_error($terms) && !empty($terms[0])) {
            $link = get_term_link($terms[0]);
            $targets['topic']['url']   = is_wp_error($link) ? trailingslashit($home) . 'topic/' . $terms[0]->slug . '/' : $link;
            $targets['topic']['title'] = $terms[0]->name;
        }

        $results = array();
        foreach ($targets as $key => $target) {
            if (empty($target['url'])) {
                $results[$key] = array(
                    'label'   => $target['label'],
                    'url'     => '',
                    'ok'      => false,
                    'error'   => __('No sample URL available.', 'qpedia-seo-pro'),
                    'elapsed' => 0,
                    'status'  => 0,
                );
                continue;
            }
            $results[$key] = $this->probe_url($target['url'], $target['label']);
            if (!empty($target['title'])) {
                $results[$key]['title'] = $target['title'];
            }
        }

        $payload = array(
            'generated_at' => current_time('mysql'),
            'targets'      => $results,
            'slowest_ms'   => $this->slowest($results),
            'average_ms'   => $this->average($results),
        );

        update_option(self::OPTION_KEY, $payload, false);
        return $payload;
    }

    /**
     * Inspect Cache-Control, ETag, Expires and detect cache plugins.
     *
     * @param array $times Optional measure_response_times() result to reuse headers.
     * @return array
     */
    public function check_caching($times = array()) {
        $home = $this->home_url();
        $url  = $home . '/';
        $headers = array();
        $status  = 0;
        $error   = '';

        if (!empty($times['targets']['home']['headers'])) {
            $headers = $times['targets']['home']['headers'];
            $status  = isset($times['targets']['home']['status']) ? (int) $times['targets']['home']['status'] : 0;
        } else {
            $probe = $this->probe_url($url, 'home');
            $headers = isset($probe['headers']) ? $probe['headers'] : array();
            $status  = isset($probe['status']) ? (int) $probe['status'] : 0;
            $error   = isset($probe['error']) ? $probe['error'] : '';
        }

        $cc      = $this->header($headers, 'cache-control');
        $etag    = $this->header($headers, 'etag');
        $expires = $this->header($headers, 'expires');
        $age     = $this->header($headers, 'age');
        $xcache  = $this->header($headers, 'x-cache');
        $cf      = $this->header($headers, 'cf-cache-status');

        $plugins = $this->detect_cache_plugins();

        $issues = array();
        if ($cc === '' && $expires === '' && $etag === '') {
            $issues[] = __('No Cache-Control, Expires or ETag header on the homepage.', 'qpedia-seo-pro');
        }
        if ($cc !== '' && preg_match('/no-store|private/i', $cc) && empty($plugins)) {
            $issues[] = __('Homepage Cache-Control looks uncacheable and no cache plugin was detected.', 'qpedia-seo-pro');
        }
        if (empty($plugins) && $xcache === '' && $cf === '') {
            $issues[] = __('No known page-cache plugin or CDN cache header detected.', 'qpedia-seo-pro');
        }

        return array(
            'generated_at' => current_time('mysql'),
            'url'          => $url,
            'status'       => $status,
            'error'        => $error,
            'headers'      => array(
                'cache-control'   => $cc,
                'etag'            => $etag,
                'expires'         => $expires,
                'age'             => $age,
                'x-cache'         => $xcache,
                'cf-cache-status' => $cf,
            ),
            'plugins'      => $plugins,
            'has_cache'    => (!empty($plugins) || $xcache !== '' || $cf !== '' || $cc !== ''),
            'issues'       => $issues,
        );
    }

    /**
     * Check Content-Encoding for gzip / br.
     *
     * @param array $times Optional measure result.
     * @return array
     */
    public function check_compression($times = array()) {
        $url     = $this->home_url() . '/';
        $headers = array();
        if (!empty($times['targets']['home']['headers'])) {
            $headers = $times['targets']['home']['headers'];
        } else {
            $probe   = $this->probe_url($url, 'home', true);
            $headers = isset($probe['headers']) ? $probe['headers'] : array();
        }

        $encoding = strtolower($this->header($headers, 'content-encoding'));
        $has_gzip = strpos($encoding, 'gzip') !== false || strpos($encoding, 'deflate') !== false;
        $has_br   = strpos($encoding, 'br') !== false;

        $issues = array();
        if (!$has_gzip && !$has_br) {
            $issues[] = __('Content-Encoding is not gzip or Brotli. Enable compression at the server or CDN.', 'qpedia-seo-pro');
        }

        return array(
            'generated_at'      => current_time('mysql'),
            'url'               => $url,
            'content_encoding'  => $encoding,
            'gzip'              => $has_gzip,
            'brotli'            => $has_br,
            'ok'                => $has_gzip || $has_br,
            'issues'            => $issues,
        );
    }

    /**
     * Run all probes and persist.
     *
     * @return array
     */
    public function audit() {
        $times  = $this->measure_response_times();
        $cache  = $this->check_caching($times);
        $compr  = $this->check_compression($times);
        $out    = array(
            'generated_at' => current_time('mysql'),
            'response'     => $times,
            'caching'      => $cache,
            'compression'  => $compr,
        );
        update_option(self::OPTION_KEY, $out, false);
        return $out;
    }

    /**
     * Probe a single URL and return elapsed ms plus headers.
     *
     * @param string $url          URL.
     * @param string $label        Label.
     * @param bool   $ask_encoding Send Accept-Encoding.
     * @return array
     */
    public function probe_url($url, $label = '', $ask_encoding = true) {
        $headers = array(
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        );
        if ($ask_encoding) {
            $headers['Accept-Encoding'] = 'gzip, deflate, br';
        }

        $start    = microtime(true);
        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 20,
                'redirection' => 5,
                'sslverify'   => true,
                'headers'     => $headers,
                'user-agent'  => 'QpediaSEOPro/1.0; ' . $this->home_url(),
                'decompress'  => false,
            )
        );
        $elapsed = (microtime(true) - $start) * 1000;

        if (is_wp_error($response)) {
            return array(
                'label'   => $label,
                'url'     => $url,
                'ok'      => false,
                'error'   => $response->get_error_message(),
                'elapsed' => round($elapsed, 1),
                'status'  => 0,
                'headers' => array(),
                'bytes'   => 0,
            );
        }

        $raw_headers = wp_remote_retrieve_headers($response);
        $flat        = array();
        if (is_object($raw_headers) && method_exists($raw_headers, 'getAll')) {
            $all = $raw_headers->getAll();
            foreach ($all as $k => $v) {
                $flat[strtolower($k)] = is_array($v) ? implode(', ', $v) : (string) $v;
            }
        } elseif (is_array($raw_headers)) {
            foreach ($raw_headers as $k => $v) {
                $flat[strtolower($k)] = is_array($v) ? implode(', ', $v) : (string) $v;
            }
        }

        $body = wp_remote_retrieve_body($response);
        return array(
            'label'   => $label,
            'url'     => $url,
            'ok'      => true,
            'error'   => '',
            'elapsed' => round($elapsed, 1),
            'status'  => (int) wp_remote_retrieve_response_code($response),
            'headers' => $flat,
            'bytes'   => strlen((string) $body),
        );
    }

    /**
     * Detect common cache plugins.
     *
     * @return array List of {slug,name,active}.
     */
    public function detect_cache_plugins() {
        if (!function_exists('is_plugin_active')) {
            $plugin_php = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_readable($plugin_php)) {
                include_once $plugin_php;
            }
        }

        $map = array(
            'wp-rocket/wp-rocket.php'                         => 'WP Rocket',
            'w3-total-cache/w3-total-cache.php'               => 'W3 Total Cache',
            'wp-super-cache/wp-cache.php'                     => 'WP Super Cache',
            'litespeed-cache/litespeed-cache.php'             => 'LiteSpeed Cache',
            'wp-fastest-cache/wpFastestCache.php'             => 'WP Fastest Cache',
            'cache-enabler/cache-enabler.php'                 => 'Cache Enabler',
            'hummingbird-performance/wp-hummingbird.php'      => 'Hummingbird',
            'sg-cachepress/sg-cachepress.php'                 => 'SiteGround Optimizer',
            'nitropack/main.php'                              => 'NitroPack',
            'flying-press/flying-press.php'                   => 'FlyingPress',
            'autoptimize/autoptimize.php'                     => 'Autoptimize',
            'redis-cache/redis-cache.php'                     => 'Redis Object Cache',
            'wp-optimize/wp-optimize.php'                     => 'WP-Optimize',
            'breeze/breeze.php'                               => 'Breeze',
            'comet-cache/comet-cache.php'                     => 'Comet Cache',
        );

        $found = array();
        foreach ($map as $slug => $name) {
            $active = function_exists('is_plugin_active') && is_plugin_active($slug);
            if ($active) {
                $found[] = array(
                    'slug'   => $slug,
                    'name'   => $name,
                    'active' => true,
                );
            }
        }

        if (defined('WP_CACHE') && WP_CACHE) {
            $found[] = array(
                'slug'   => 'WP_CACHE',
                'name'   => 'WP_CACHE constant',
                'active' => true,
            );
        }
        if (defined('LSCWP_V') || defined('LITESPEED_ON')) {
            $this->ensure_named($found, 'LiteSpeed Cache');
        }
        if (defined('WP_ROCKET_VERSION')) {
            $this->ensure_named($found, 'WP Rocket');
        }
        if (defined('W3TC_VERSION')) {
            $this->ensure_named($found, 'W3 Total Cache');
        }

        return $found;
    }

    /**
     * Ensure a plugin name is present in the list.
     *
     * @param array  $found Found plugins (by ref).
     * @param string $name  Name.
     * @return void
     */
    private function ensure_named(&$found, $name) {
        foreach ($found as $row) {
            if ($row['name'] === $name) {
                return;
            }
        }
        $found[] = array(
            'slug'   => sanitize_title($name),
            'name'   => $name,
            'active' => true,
        );
    }

    /**
     * Read a header case-insensitively.
     *
     * @param array  $headers Headers.
     * @param string $name    Name.
     * @return string
     */
    private function header($headers, $name) {
        $name = strtolower($name);
        if (isset($headers[$name])) {
            return (string) $headers[$name];
        }
        return '';
    }

    /**
     * Slowest successful elapsed time.
     *
     * @param array $results Probes.
     * @return float
     */
    private function slowest($results) {
        $max = 0;
        foreach ($results as $row) {
            if (!empty($row['ok']) && isset($row['elapsed']) && $row['elapsed'] > $max) {
                $max = $row['elapsed'];
            }
        }
        return $max;
    }

    /**
     * Average successful elapsed time.
     *
     * @param array $results Probes.
     * @return float
     */
    private function average($results) {
        $sum = 0;
        $n   = 0;
        foreach ($results as $row) {
            if (!empty($row['ok']) && isset($row['elapsed'])) {
                $sum += $row['elapsed'];
                $n++;
            }
        }
        return $n ? round($sum / $n, 1) : 0;
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
