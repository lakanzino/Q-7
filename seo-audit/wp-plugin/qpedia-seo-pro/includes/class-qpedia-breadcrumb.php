<?php
/**
 * Breadcrumb builder for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds HTML (microdata) and JSON-LD breadcrumbs for each content type.
 */
class Breadcrumb {

    /**
     * Singleton instance.
     *
     * @var Breadcrumb|null
     */
    private static $instance = null;

    /**
     * Whether hooks were registered.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Default home URL.
     *
     * @var string
     */
    const SITE_URL = 'https://qpedia.ir';

    /**
     * Site name.
     *
     * @var string
     */
    const SITE_NAME = 'Qpedia Farsi';

    /**
     * Get singleton instance.
     *
     * @return Breadcrumb
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor. Hooks print when inject_breadcrumb is enabled.
     */
    public function __construct() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        if ($this->get_setting('inject_breadcrumb', true)) {
            add_action('wp_footer', array($this, 'print_html'), 5);
            add_action('wp_head', array($this, 'print_jsonld'), 22);
        }
    }

    /**
     * Current request breadcrumb items.
     *
     * Filtered by `qpedia_seo_breadcrumb_items`.
     *
     * @return array List of {name,url,current}.
     */
    public function get_items() {
        $home  = $this->home_url();
        $items = array(
            array(
                'name'    => __('Home', 'qpedia-seo-pro'),
                'url'     => $home . '/',
                'current' => false,
            ),
        );

        if (function_exists('is_singular') && is_singular('quantum_article')) {
            $post  = get_queried_object();
            $terms = $post ? get_the_terms($post->ID, 'quantum_category') : array();
            if (!is_wp_error($terms) && !empty($terms[0])) {
                $items[] = array(
                    'name'    => $terms[0]->name,
                    'url'     => $this->term_url($terms[0]),
                    'current' => false,
                );
            }
            if ($post) {
                $items[] = array(
                    'name'    => $post->post_title,
                    'url'     => get_permalink($post),
                    'current' => true,
                );
            }
        } elseif (function_exists('is_singular') && is_singular('quantum_scientist')) {
            $post = get_queried_object();
            $items[] = array(
                'name'    => __('Scientists', 'qpedia-seo-pro'),
                'url'     => trailingslashit($home) . 'scientists/',
                'current' => false,
            );
            if ($post) {
                $name = get_post_meta($post->ID, '_scientist_fullname', true);
                if (!is_string($name) || $name === '') {
                    $name = $post->post_title;
                }
                $items[] = array(
                    'name'    => $name,
                    'url'     => get_permalink($post),
                    'current' => true,
                );
            }
        } elseif (function_exists('is_tax') && is_tax('quantum_category')) {
            $term = get_queried_object();
            $items[] = array(
                'name'    => __('Topics', 'qpedia-seo-pro'),
                'url'     => trailingslashit($home) . 'topic/',
                'current' => false,
            );
            if ($term instanceof \WP_Term) {
                $ancestors = array_reverse(get_ancestors($term->term_id, 'quantum_category'));
                foreach ($ancestors as $aid) {
                    $parent = get_term($aid, 'quantum_category');
                    if ($parent && !is_wp_error($parent)) {
                        $items[] = array(
                            'name'    => $parent->name,
                            'url'     => $this->term_url($parent),
                            'current' => false,
                        );
                    }
                }
                $items[] = array(
                    'name'    => $term->name,
                    'url'     => $this->term_url($term),
                    'current' => true,
                );
            }
        } elseif (function_exists('is_singular') && is_singular('page')) {
            $post = get_queried_object();
            if ($post && $post->post_name !== 'home') {
                $items[] = array(
                    'name'    => $post->post_title,
                    'url'     => get_permalink($post),
                    'current' => true,
                );
            }
        } elseif (function_exists('is_post_type_archive') && is_post_type_archive('quantum_scientist')) {
            $items[] = array(
                'name'    => __('Scientists', 'qpedia-seo-pro'),
                'url'     => trailingslashit($home) . 'scientists/',
                'current' => true,
            );
        } elseif (function_exists('is_search') && is_search()) {
            $items[] = array(
                'name'    => __('Search', 'qpedia-seo-pro'),
                'url'     => '',
                'current' => true,
            );
        }

        /**
         * Filter breadcrumb items.
         *
         * @param array $items Items.
         */
        $items = apply_filters('qpedia_seo_breadcrumb_items', $items);
        if (!is_array($items)) {
            $items = array();
        }
        return $items;
    }

    /**
     * Build items for an arbitrary post or term (used by export / schema).
     *
     * @param mixed $object WP_Post, WP_Term, or null for current.
     * @return array
     */
    public function build_items($object = null) {
        if (null === $object) {
            return $this->get_items();
        }

        $home  = $this->home_url();
        $items = array(
            array(
                'name'    => __('Home', 'qpedia-seo-pro'),
                'url'     => $home . '/',
                'current' => false,
            ),
        );

        if ($object instanceof \WP_Post) {
            if ($object->post_type === 'quantum_article') {
                $terms = get_the_terms($object->ID, 'quantum_category');
                if (!is_wp_error($terms) && !empty($terms[0])) {
                    $items[] = array(
                        'name'    => $terms[0]->name,
                        'url'     => $this->term_url($terms[0]),
                        'current' => false,
                    );
                }
                $items[] = array(
                    'name'    => $object->post_title,
                    'url'     => get_permalink($object),
                    'current' => true,
                );
            } elseif ($object->post_type === 'quantum_scientist') {
                $items[] = array(
                    'name'    => __('Scientists', 'qpedia-seo-pro'),
                    'url'     => trailingslashit($home) . 'scientists/',
                    'current' => false,
                );
                $name = get_post_meta($object->ID, '_scientist_fullname', true);
                $items[] = array(
                    'name'    => ($name ? $name : $object->post_title),
                    'url'     => get_permalink($object),
                    'current' => true,
                );
            } else {
                $items[] = array(
                    'name'    => $object->post_title,
                    'url'     => get_permalink($object),
                    'current' => true,
                );
            }
        } elseif ($object instanceof \WP_Term) {
            $items[] = array(
                'name'    => __('Topics', 'qpedia-seo-pro'),
                'url'     => trailingslashit($home) . 'topic/',
                'current' => false,
            );
            $items[] = array(
                'name'    => $object->name,
                'url'     => $this->term_url($object),
                'current' => true,
            );
        }

        return apply_filters('qpedia_seo_breadcrumb_items', $items);
    }

    /**
     * Render HTML nav with schema.org microdata.
     *
     * @param array $items Optional items.
     * @return string
     */
    public function render_html($items = null) {
        if (null === $items) {
            $items = $this->get_items();
        }
        if (empty($items) || count($items) < 2) {
            return '';
        }

        $sep = apply_filters('qpedia_seo_breadcrumb_separator', ' / ');
        $out = '<nav class="qpedia-breadcrumb" aria-label="' . esc_attr__('Breadcrumb', 'qpedia-seo-pro') . '">';
        $out .= '<ol class="qpedia-breadcrumb__list" itemscope itemtype="https://schema.org/BreadcrumbList">';

        $pos   = 1;
        $count = count($items);
        foreach ($items as $item) {
            $name = isset($item['name']) ? $item['name'] : '';
            $url  = isset($item['url']) ? $item['url'] : '';
            $cur  = !empty($item['current']) || $pos === $count;
            $out .= '<li class="qpedia-breadcrumb__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
            if (!$cur && $url) {
                $out .= '<a itemprop="item" href="' . esc_url($url) . '"><span itemprop="name">' . esc_html($name) . '</span></a>';
            } else {
                $out .= '<span itemprop="name" aria-current="page">' . esc_html($name) . '</span>';
                if ($url) {
                    $out .= '<link itemprop="item" href="' . esc_url($url) . '" />';
                }
            }
            $out .= '<meta itemprop="position" content="' . (int) $pos . '" />';
            $out .= '</li>';
            if ($pos < $count) {
                $out .= '<li class="qpedia-breadcrumb__sep" aria-hidden="true">' . esc_html($sep) . '</li>';
            }
            $pos++;
        }

        $out .= '</ol></nav>';
        return $out;
    }

    /**
     * JSON-LD BreadcrumbList array.
     *
     * @param array $items Optional items.
     * @return array
     */
    public function jsonld($items = null) {
        if (null === $items) {
            $items = $this->get_items();
        }
        $elements = array();
        $pos      = 1;
        foreach ($items as $item) {
            if (empty($item['name'])) {
                continue;
            }
            $el = array(
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => $item['name'],
            );
            if (!empty($item['url'])) {
                $el['item'] = $item['url'];
            }
            $elements[] = $el;
            $pos++;
        }
        return array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        );
    }

    /**
     * Echo HTML into the footer.
     *
     * @return void
     */
    public function print_html() {
        $html = $this->render_html();
        if ($html !== '') {
            echo $html . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Echo JSON-LD into the head.
     *
     * @return void
     */
    public function print_jsonld() {
        $data = $this->jsonld();
        if (empty($data['itemListElement'])) {
            return;
        }
        $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (false === $json) {
            return;
        }
        echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Term URL with /topic/{slug}/ fallback.
     *
     * @param \WP_Term $term Term.
     * @return string
     */
    private function term_url($term) {
        $link = get_term_link($term);
        if (!is_wp_error($link) && is_string($link) && $link !== '') {
            return $link;
        }
        return trailingslashit($this->home_url()) . 'topic/' . $term->slug . '/';
    }

    /**
     * Plugin setting.
     *
     * @param string $key     Key.
     * @param mixed  $default Default.
     * @return mixed
     */
    private function get_setting($key, $default = null) {
        if (class_exists(__NAMESPACE__ . '\\Core')) {
            if (method_exists(Core::class, 'instance')) {
                $core = Core::instance();
                if (is_object($core) && method_exists($core, 'get_setting')) {
                    return $core->get_setting($key, $default);
                }
                if (is_object($core) && method_exists($core, 'get_settings')) {
                    $settings = $core->get_settings();
                    if (is_array($settings) && array_key_exists($key, $settings)) {
                        return $settings[$key];
                    }
                }
            }
        }
        $opts = get_option('qpedia_seo_pro_settings', false);
        if (false === $opts) {
            $opts = get_option('qpedia_seo_settings', array());
        }
        if (!is_array($opts)) {
            $opts = array();
        }
        return array_key_exists($key, $opts) ? $opts[$key] : $default;
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
