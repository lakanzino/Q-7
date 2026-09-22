<?php
/**
 * JSON-LD schema generator for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates Article, Person, CollectionPage and WebSite JSON-LD.
 */
class Schema {

    /**
     * Singleton instance.
     *
     * @var Schema|null
     */
    private static $instance = null;

    /**
     * Whether front-end hooks were registered.
     *
     * @var bool
     */
    private static $booted = false;

    /**
     * Site display name.
     *
     * @var string
     */
    const SITE_NAME = 'Qpedia Farsi';

    /**
     * Default absolute home URL.
     *
     * @var string
     */
    const SITE_URL = 'https://qpedia.ir';

    /**
     * Get singleton instance.
     *
     * @return Schema
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor. Registers wp_head injection.
     */
    public function __construct() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;
        add_action('wp_head', array($this, 'inject'), 21);
    }

    /**
     * Inject JSON-LD into the document head.
     *
     * Article/Person are skipped when Rank Math is active and
     * skip_schema_if_rank_math is enabled. CollectionPage and WebSite
     * still print when inject_schema is on.
     *
     * @return void
     */
    public function inject() {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }

        if (!$this->get_setting('inject_schema', true)) {
            return;
        }

        $skip_article_person = false;
        if ($this->get_setting('skip_schema_if_rank_math', true) && $this->is_rank_math_active()) {
            $skip_article_person = true;
        }

        $payloads = array();

        if (function_exists('is_singular') && is_singular('quantum_article') && !$skip_article_person) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $payloads[] = $this->article_schema($post);
            }
        } elseif (function_exists('is_singular') && is_singular('quantum_scientist') && !$skip_article_person) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $payloads[] = $this->scientist_schema($post);
            }
        }

        if (function_exists('is_tax') && is_tax('quantum_category')) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $payloads[] = $this->term_schema($term);
            }
        }

        if ((function_exists('is_front_page') && is_front_page()) || (function_exists('is_page') && is_page('home'))) {
            $payloads[] = $this->website_schema();
        }

        foreach ($payloads as $data) {
            if (empty($data) || !is_array($data)) {
                continue;
            }
            $check = $this->validate_schema($data);
            if (empty($check['valid'])) {
                continue;
            }
            $json = wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (false === $json) {
                continue;
            }
            echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * Build Article / ScholarlyArticle schema plus breadcrumb.
     *
     * @param \WP_Post|int $post Post object or ID.
     * @return array
     */
    public function article_schema($post) {
        $post = $this->resolve_post($post);
        if (!$post) {
            return array();
        }

        $home      = $this->home_url();
        $permalink = $this->post_url($post);
        $org       = $this->organization_node();
        $headline  = $this->post_seo_title($post);
        $desc      = $this->post_seo_description($post);
        $section   = $this->article_section($post);
        $keywords  = $this->article_keywords($post);
        $image     = $this->image_object($post);
        $faq_raw   = get_post_meta($post->ID, 'rank_math_schema_FAQPage', true);

        $article = array(
            '@type'            => array('Article', 'ScholarlyArticle'),
            '@id'              => $permalink . '#article',
            'headline'         => $headline,
            'name'             => $headline,
            'description'      => $desc,
            'datePublished'    => $this->iso_date($post->post_date_gmt ? $post->post_date_gmt : $post->post_date),
            'dateModified'     => $this->iso_date($post->post_modified_gmt ? $post->post_modified_gmt : $post->post_modified),
            'inLanguage'       => 'fa',
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => $permalink,
                'url'   => $permalink,
            ),
            'author'           => $org,
            'publisher'        => $org,
            'isPartOf'         => array(
                '@type' => 'WebSite',
                '@id'   => $home . '/#website',
                'name'  => self::SITE_NAME,
                'url'   => $home,
            ),
        );

        if ($section) {
            $article['articleSection'] = $section;
        }
        if ($keywords) {
            $article['keywords'] = $keywords;
        }
        if (!empty($image)) {
            $article['image'] = $image;
        }

        $word_count = $this->word_count($post->post_content);
        if ($word_count > 0) {
            $article['wordCount'] = $word_count;
        }

        $graph = array($article, $this->breadcrumb_list($this->article_crumbs($post, $section, $headline, $permalink)));

        // Preserve Rank Math FAQPage — never duplicate it.
        if (!empty($faq_raw)) {
            $graph[0]['hasPart'] = array(
                '@type' => 'FAQPage',
                '@id'   => $permalink . '#faq',
            );
        }

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        );
    }

    /**
     * Build Person schema for a scientist plus breadcrumb.
     *
     * @param \WP_Post|int $post Post object or ID.
     * @return array
     */
    public function scientist_schema($post) {
        $post = $this->resolve_post($post);
        if (!$post) {
            return array();
        }

        $home       = $this->home_url();
        $permalink  = $this->post_url($post);
        $fullname   = $this->meta($post->ID, '_scientist_fullname', $post->post_title);
        $en_name    = $this->meta($post->ID, '_scientist_en_name', '');
        $born_died  = $this->meta($post->ID, '_scientist_born_died', '');
        $dates      = $this->parse_born_died($born_died);
        $birthplace = $this->meta($post->ID, '_scientist_birthplace', '');
        $inst_raw   = $this->meta($post->ID, '_scientist_institutions', '');
        $nobel      = $this->meta($post->ID, '_scientist_nobel', '');
        $concepts   = $this->meta($post->ID, '_scientist_concepts', '');
        $desc       = $this->meta($post->ID, '_qpedia_meta_description', '');
        if ($desc === '') {
            $desc = $this->post_seo_description($post);
        }
        $image = $this->image_object($post);

        $person = array(
            '@type'            => 'Person',
            '@id'              => $permalink . '#person',
            'name'             => $fullname,
            'description'      => $desc,
            'url'              => $permalink,
            'mainEntityOfPage' => array(
                '@type' => 'WebPage',
                '@id'   => $permalink,
                'url'   => $permalink,
            ),
            'sameAs'           => array(),
        );

        if ($en_name !== '') {
            $person['alternateName'] = $en_name;
        }
        if (!empty($dates['birthDate'])) {
            $person['birthDate'] = $dates['birthDate'];
        }
        if (!empty($dates['deathDate'])) {
            $person['deathDate'] = $dates['deathDate'];
        }
        if ($birthplace !== '') {
            $person['birthPlace'] = array(
                '@type' => 'Place',
                'name'  => $birthplace,
            );
        }

        $affiliations = $this->split_list($inst_raw);
        if (!empty($affiliations)) {
            $orgs = array();
            foreach ($affiliations as $name) {
                $orgs[] = array(
                    '@type' => 'Organization',
                    'name'  => $name,
                );
            }
            $person['affiliation'] = $orgs;
        }

        if ($nobel !== '' && $this->is_truthy_nobel($nobel)) {
            $person['award'] = $nobel;
        }

        $knows = $this->split_list($concepts);
        if (!empty($knows)) {
            $person['knowsAbout'] = $knows;
        }
        if (!empty($image)) {
            $person['image'] = $image;
        }

        $scientists_url = trailingslashit($home) . 'scientists/';
        $crumbs         = array(
            array('name' => __('Home', 'qpedia-seo-pro'), 'url' => $home . '/'),
            array('name' => __('Scientists', 'qpedia-seo-pro'), 'url' => $scientists_url),
            array('name' => $fullname, 'url' => $permalink),
        );

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => array($person, $this->breadcrumb_list($crumbs)),
        );
    }

    /**
     * Build CollectionPage schema for a quantum_category term.
     *
     * @param \WP_Term|int $term Term object or ID.
     * @return array
     */
    public function term_schema($term) {
        if (is_numeric($term)) {
            $term = get_term((int) $term, 'quantum_category');
        }
        if (!$term || is_wp_error($term) || !($term instanceof \WP_Term)) {
            return array();
        }

        $home = $this->home_url();
        $url  = $this->term_url($term);

        $page = array(
            '@type'       => 'CollectionPage',
            '@id'         => $url . '#collection',
            'name'        => $term->name,
            'description' => $this->plain_text($term->description),
            'url'         => $url,
            'inLanguage'  => 'fa',
            'isPartOf'    => array(
                '@type' => 'WebSite',
                '@id'   => $home . '/#website',
                'name'  => self::SITE_NAME,
                'url'   => $home,
            ),
        );

        $count = isset($term->count) ? (int) $term->count : 0;
        if ($count > 0) {
            $page['numberOfItems'] = $count;
        }

        $topics_url = trailingslashit($home) . 'topic/';
        $crumbs     = array(
            array('name' => __('Home', 'qpedia-seo-pro'), 'url' => $home . '/'),
            array('name' => __('Topics', 'qpedia-seo-pro'), 'url' => $topics_url),
            array('name' => $term->name, 'url' => $url),
        );

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => array($page, $this->breadcrumb_list($crumbs)),
        );
    }

    /**
     * Build WebSite + Organization + SearchAction schema.
     *
     * @return array
     */
    public function website_schema() {
        $home = $this->home_url();
        $logo = $this->site_logo_url();
        $org  = $this->organization_node();
        if ($logo) {
            $org['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $logo,
            );
            $org['image'] = $logo;
        }

        $website = array(
            '@type'           => 'WebSite',
            '@id'             => $home . '/#website',
            'name'            => self::SITE_NAME,
            'url'             => $home,
            'inLanguage'      => 'fa',
            'publisher'       => array('@id' => $home . '/#organization'),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => array(
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $home . '/?s={search_term_string}',
                ),
                'query-input' => 'required name=search_term_string',
            ),
        );

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => array($website, $org),
        );
    }

    /**
     * Validate a schema array before printing.
     *
     * @param array $arr Schema payload.
     * @return array {valid:bool, errors:string[]}
     */
    public function validate_schema($arr) {
        $errors = array();

        if (!is_array($arr) || empty($arr)) {
            return array(
                'valid'  => false,
                'errors' => array(__('Schema payload is empty.', 'qpedia-seo-pro')),
            );
        }

        if (empty($arr['@context'])) {
            $errors[] = __('Missing @context.', 'qpedia-seo-pro');
        }

        $nodes = array();
        if (!empty($arr['@graph']) && is_array($arr['@graph'])) {
            $nodes = $arr['@graph'];
        } else {
            $nodes = array($arr);
        }

        $has_type = false;
        foreach ($nodes as $i => $node) {
            if (!is_array($node)) {
                $errors[] = sprintf(__('Graph node %d is not an object.', 'qpedia-seo-pro'), $i);
                continue;
            }
            $type = $this->node_types($node);
            if (empty($type)) {
                $errors[] = sprintf(__('Graph node %d is missing @type.', 'qpedia-seo-pro'), $i);
                continue;
            }
            $has_type = true;
            $errors   = array_merge($errors, $this->validate_node($node, $type, $i));
        }

        if (!$has_type && empty($arr['@type'])) {
            $errors[] = __('Missing @type.', 'qpedia-seo-pro');
        }

        return array(
            'valid'  => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Validate required fields for a single node.
     *
     * @param array    $node  Schema node.
     * @param string[] $types Normalized types.
     * @param int      $index Graph index.
     * @return string[]
     */
    private function validate_node($node, $types, $index) {
        $errors = array();
        $prefix = sprintf(__('Node %d (%s): ', 'qpedia-seo-pro'), $index, implode(',', $types));

        if (in_array('Article', $types, true) || in_array('ScholarlyArticle', $types, true)) {
            foreach (array('headline', 'datePublished', 'author') as $field) {
                if (empty($node[$field])) {
                    $errors[] = $prefix . sprintf(__('missing required field %s.', 'qpedia-seo-pro'), $field);
                }
            }
        }
        if (in_array('Person', $types, true) && empty($node['name'])) {
            $errors[] = $prefix . __('missing required field name.', 'qpedia-seo-pro');
        }
        if (in_array('CollectionPage', $types, true)) {
            foreach (array('name', 'url') as $field) {
                if (empty($node[$field])) {
                    $errors[] = $prefix . sprintf(__('missing required field %s.', 'qpedia-seo-pro'), $field);
                }
            }
        }
        if (in_array('WebSite', $types, true)) {
            foreach (array('name', 'url') as $field) {
                if (empty($node[$field])) {
                    $errors[] = $prefix . sprintf(__('missing required field %s.', 'qpedia-seo-pro'), $field);
                }
            }
        }
        if (in_array('Organization', $types, true) && empty($node['name'])) {
            $errors[] = $prefix . __('missing required field name.', 'qpedia-seo-pro');
        }
        if (in_array('BreadcrumbList', $types, true) && empty($node['itemListElement'])) {
            $errors[] = $prefix . __('missing itemListElement.', 'qpedia-seo-pro');
        }

        return $errors;
    }

    /**
     * Parse born/died strings into birthDate / deathDate.
     *
     * Supports 1901–1954, 1901-1954, ۱۹۰۱-۱۹۵۴ and single years.
     *
     * @param string $value Raw meta.
     * @return array {birthDate:?string, deathDate:?string}
     */
    public function parse_born_died($value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return array(
                'birthDate' => null,
                'deathDate' => null,
            );
        }

        $value = $this->fa_to_en_digits($value);
        $value = str_replace(array('–', '—', '−', '‐', 'ـ', '/', 'تا'), '-', $value);
        $value = preg_replace('/\s+/u', ' ', $value);

        if (preg_match('/(\d{3,4})\s*-\s*(\d{3,4})/u', $value, $m)) {
            return array(
                'birthDate' => $this->normalize_year($m[1]),
                'deathDate' => $this->normalize_year($m[2]),
            );
        }
        if (preg_match('/(\d{3,4})/u', $value, $m)) {
            return array(
                'birthDate' => $this->normalize_year($m[1]),
                'deathDate' => null,
            );
        }

        return array(
            'birthDate' => null,
            'deathDate' => null,
        );
    }

    /**
     * Organization node shared by Article and WebSite.
     *
     * @return array
     */
    public function organization_node() {
        $home = $this->home_url();
        $node = array(
            '@type' => 'Organization',
            '@id'   => $home . '/#organization',
            'name'  => self::SITE_NAME,
            'url'   => $home,
        );
        $logo = $this->site_logo_url();
        if ($logo) {
            $node['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $logo,
            );
        }
        return $node;
    }

    /**
     * Build a BreadcrumbList node.
     *
     * @param array $crumbs List of {name,url}.
     * @return array
     */
    public function breadcrumb_list($crumbs) {
        $elements = array();
        $pos      = 1;
        foreach ($crumbs as $crumb) {
            if (empty($crumb['name'])) {
                continue;
            }
            $item = array(
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => $crumb['name'],
            );
            if (!empty($crumb['url'])) {
                $item['item'] = $crumb['url'];
            }
            $elements[] = $item;
            $pos++;
        }

        return array(
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        );
    }

    /**
     * Sample schemas for export (first matching published entity).
     *
     * @return array
     */
    public function sample_schemas() {
        $out = array(
            'article'   => array(),
            'scientist' => array(),
            'category'  => array(),
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
            $out['article'] = $this->article_schema($article[0]);
        }

        $scientist = get_posts(
            array(
                'post_type'      => 'quantum_scientist',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        if (!empty($scientist[0])) {
            $out['scientist'] = $this->scientist_schema($scientist[0]);
        }

        $terms = get_terms(
            array(
                'taxonomy'   => 'quantum_category',
                'hide_empty' => false,
                'number'     => 1,
            )
        );
        if (!is_wp_error($terms) && !empty($terms[0])) {
            $out['category'] = $this->term_schema($terms[0]);
        }

        return $out;
    }

    /**
     * Audit generated schemas for a set of posts/terms.
     *
     * @param int $article_limit Max articles to validate.
     * @return array
     */
    public function audit_generated($article_limit = 25) {
        $results = array(
            'checked' => 0,
            'valid'   => 0,
            'invalid' => array(),
        );

        $articles = get_posts(
            array(
                'post_type'      => 'quantum_article',
                'post_status'    => 'publish',
                'posts_per_page' => (int) $article_limit,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        foreach ($articles as $post) {
            $schema = $this->article_schema($post);
            $check  = $this->validate_schema($schema);
            $results['checked']++;
            if (!empty($check['valid'])) {
                $results['valid']++;
            } else {
                $results['invalid'][] = array(
                    'type'   => 'article',
                    'id'     => (int) $post->ID,
                    'title'  => $post->post_title,
                    'errors' => $check['errors'],
                );
            }
        }

        $scientists = get_posts(
            array(
                'post_type'      => 'quantum_scientist',
                'post_status'    => 'publish',
                'posts_per_page' => 20,
            )
        );
        foreach ($scientists as $post) {
            $schema = $this->scientist_schema($post);
            $check  = $this->validate_schema($schema);
            $results['checked']++;
            if (!empty($check['valid'])) {
                $results['valid']++;
            } else {
                $results['invalid'][] = array(
                    'type'   => 'scientist',
                    'id'     => (int) $post->ID,
                    'title'  => $post->post_title,
                    'errors' => $check['errors'],
                );
            }
        }

        $website = $this->website_schema();
        $check   = $this->validate_schema($website);
        $results['checked']++;
        if (!empty($check['valid'])) {
            $results['valid']++;
        } else {
            $results['invalid'][] = array(
                'type'   => 'website',
                'id'     => 0,
                'title'  => self::SITE_NAME,
                'errors' => $check['errors'],
            );
        }

        return $results;
    }

    /**
     * Article breadcrumb crumbs: home > topic > article.
     *
     * @param \WP_Post $post      Post.
     * @param string   $section   Category name.
     * @param string   $headline  Title.
     * @param string   $permalink URL.
     * @return array
     */
    private function article_crumbs($post, $section, $headline, $permalink) {
        $home   = $this->home_url();
        $crumbs = array(
            array('name' => __('Home', 'qpedia-seo-pro'), 'url' => $home . '/'),
        );

        $terms = get_the_terms($post->ID, 'quantum_category');
        if (!is_wp_error($terms) && !empty($terms[0])) {
            $crumbs[] = array(
                'name' => $terms[0]->name,
                'url'  => $this->term_url($terms[0]),
            );
        } elseif ($section) {
            $crumbs[] = array(
                'name' => is_array($section) ? implode(', ', $section) : $section,
                'url'  => trailingslashit($home) . 'topic/',
            );
        }

        $crumbs[] = array(
            'name' => $headline,
            'url'  => $permalink,
        );
        return $crumbs;
    }

    /**
     * Article section names from quantum_category.
     *
     * @param \WP_Post $post Post.
     * @return string|string[]
     */
    private function article_section($post) {
        $terms = get_the_terms($post->ID, 'quantum_category');
        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }
        $names = array();
        foreach ($terms as $term) {
            $names[] = $term->name;
        }
        if (count($names) === 1) {
            return $names[0];
        }
        return $names;
    }

    /**
     * Keywords from Rank Math focus keyword plus tags.
     *
     * @param \WP_Post $post Post.
     * @return string
     */
    private function article_keywords($post) {
        $parts = array();
        $focus = $this->meta($post->ID, 'rank_math_focus_keyword', '');
        if ($focus === '') {
            $focus = $this->meta($post->ID, '_qpedia_focus_keyphrase', '');
        }
        if ($focus !== '') {
            foreach ($this->split_list($focus) as $kw) {
                $parts[] = $kw;
            }
        }

        $tags = get_the_terms($post->ID, 'post_tag');
        if (is_wp_error($tags) || empty($tags)) {
            $legacy = $this->meta($post->ID, '_qpedia_tags', '');
            if ($legacy !== '') {
                $parts = array_merge($parts, $this->split_list($legacy));
            }
        } else {
            foreach ($tags as $tag) {
                $parts[] = $tag->name;
            }
        }

        $parts = array_values(array_unique(array_filter(array_map('trim', $parts))));
        return implode(', ', $parts);
    }

    /**
     * ImageObject from the featured image.
     *
     * @param \WP_Post $post Post.
     * @return array
     */
    private function image_object($post) {
        $thumb_id = (int) get_post_thumbnail_id($post->ID);
        if ($thumb_id < 1) {
            $thumb_id = (int) get_post_meta($post->ID, '_thumbnail_id', true);
        }
        if ($thumb_id < 1) {
            return array();
        }

        $src = wp_get_attachment_image_src($thumb_id, 'full');
        if (empty($src[0])) {
            return array();
        }

        $alt = get_post_meta($thumb_id, '_wp_attachment_image_alt', true);
        $img = array(
            '@type' => 'ImageObject',
            'url'   => $src[0],
        );
        if (!empty($src[1])) {
            $img['width'] = (int) $src[1];
        }
        if (!empty($src[2])) {
            $img['height'] = (int) $src[2];
        }
        if (is_string($alt) && $alt !== '') {
            $img['caption'] = $alt;
            $img['name']    = $alt;
        }
        return $img;
    }

    /**
     * SEO title: Rank Math, then Qpedia, then post title.
     *
     * @param \WP_Post $post Post.
     * @return string
     */
    private function post_seo_title($post) {
        $title = $this->meta($post->ID, 'rank_math_title', '');
        if ($title === '') {
            $title = $this->meta($post->ID, '_qpedia_seo_title', '');
        }
        if ($title === '') {
            $title = $post->post_title;
        }
        $title = $this->replace_rank_math_vars($title, $post);
        return $this->plain_text($title);
    }

    /**
     * SEO description: Rank Math, excerpt, then first 160 chars.
     *
     * @param \WP_Post $post Post.
     * @return string
     */
    private function post_seo_description($post) {
        $desc = $this->meta($post->ID, 'rank_math_description', '');
        if ($desc === '') {
            $desc = $this->meta($post->ID, '_qpedia_meta_description', '');
        }
        if ($desc === '') {
            $desc = $post->post_excerpt;
        }
        if ($desc === '') {
            $plain = $this->plain_text($post->post_content);
            $desc  = $this->truncate($plain, 160);
        }
        $desc = $this->replace_rank_math_vars($desc, $post);
        return $this->plain_text($desc);
    }

    /**
     * Replace a few Rank Math template variables.
     *
     * @param string   $text Text.
     * @param \WP_Post $post Post.
     * @return string
     */
    private function replace_rank_math_vars($text, $post) {
        if (!is_string($text) || strpos($text, '%') === false) {
            return $text;
        }
        $map = array(
            '%title%'     => $post->post_title,
            '%sitename%'  => self::SITE_NAME,
            '%sep%'       => '|',
            '%excerpt%'   => $this->plain_text($post->post_excerpt),
            '%post_url%'  => $this->post_url($post),
            '%name%'      => $post->post_title,
        );
        return strtr($text, $map);
    }

    /**
     * Absolute post URL.
     *
     * @param \WP_Post $post Post.
     * @return string
     */
    private function post_url($post) {
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
     * Topic archive URL /topic/{slug}/.
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
     * Site logo URL.
     *
     * @return string
     */
    private function site_logo_url() {
        $custom = (int) get_theme_mod('custom_logo');
        if ($custom) {
            $src = wp_get_attachment_image_url($custom, 'full');
            if ($src) {
                return $src;
            }
        }
        if (function_exists('get_site_icon_url')) {
            $icon = get_site_icon_url(512);
            if ($icon) {
                return $icon;
            }
        }
        return '';
    }

    /**
     * Normalize @type to a list of strings.
     *
     * @param array $node Node.
     * @return string[]
     */
    private function node_types($node) {
        if (empty($node['@type'])) {
            return array();
        }
        if (is_array($node['@type'])) {
            return array_map('strval', $node['@type']);
        }
        return array((string) $node['@type']);
    }

    /**
     * Resolve WP_Post from mixed input.
     *
     * @param mixed $post Post or ID.
     * @return \WP_Post|null
     */
    private function resolve_post($post) {
        if ($post instanceof \WP_Post) {
            return $post;
        }
        if (is_numeric($post)) {
            $obj = get_post((int) $post);
            return $obj instanceof \WP_Post ? $obj : null;
        }
        if (is_array($post) && !empty($post['ID'])) {
            $obj = get_post((int) $post['ID']);
            return $obj instanceof \WP_Post ? $obj : null;
        }
        if (is_array($post) && !empty($post['id'])) {
            $obj = get_post((int) $post['id']);
            return $obj instanceof \WP_Post ? $obj : null;
        }
        return null;
    }

    /**
     * Trimmed post meta string.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Meta key.
     * @param string $default Default.
     * @return string
     */
    private function meta($post_id, $key, $default = '') {
        $val = get_post_meta($post_id, $key, true);
        if (is_array($val)) {
            $val = implode(', ', array_filter(array_map('strval', $val)));
        }
        if (!is_string($val) || trim($val) === '') {
            return $default;
        }
        return trim($val);
    }

    /**
     * Split a list on comma, Arabic comma, newline, semicolon.
     *
     * @param string $raw Raw list.
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
        return array_values(array_unique($out));
    }

    /**
     * Convert Persian/Arabic-Indic digits to Latin.
     *
     * @param string $s Input.
     * @return string
     */
    private function fa_to_en_digits($s) {
        $fa = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩');
        $en = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        return str_replace($fa, $en, $s);
    }

    /**
     * Pad a year to 4 digits when reasonable.
     *
     * @param string $year Year.
     * @return string
     */
    private function normalize_year($year) {
        $year = preg_replace('/\D+/', '', (string) $year);
        if (strlen($year) === 3) {
            return '0' . $year;
        }
        return $year;
    }

    /**
     * ISO 8601 date from a MySQL datetime (GMT preferred).
     *
     * @param string $mysql Datetime.
     * @return string
     */
    private function iso_date($mysql) {
        if (!is_string($mysql) || $mysql === '' || $mysql === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = strtotime($mysql . ' UTC');
        if (!$ts) {
            $ts = strtotime($mysql);
        }
        if (!$ts) {
            return $mysql;
        }
        return gmdate('c', $ts);
    }

    /**
     * Truncate UTF-8 text on a word boundary.
     *
     * @param string $text   Text.
     * @param int    $length Max chars.
     * @return string
     */
    private function truncate($text, $length) {
        $text = trim($text);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text, 'UTF-8') <= $length) {
                return $text;
            }
            $cut = mb_substr($text, 0, $length, 'UTF-8');
            $sp  = mb_strrpos($cut, ' ', 0, 'UTF-8');
            if (false !== $sp && $sp > ($length * 0.6)) {
                $cut = mb_substr($cut, 0, $sp, 'UTF-8');
            }
            return rtrim($cut, ' ،,;؛.') . '…';
        }
        if (strlen($text) <= $length) {
            return $text;
        }
        return rtrim(substr($text, 0, $length)) . '…';
    }

    /**
     * Nobel field is considered present unless empty / 0 / no / No.
     *
     * @param string $nobel Raw meta.
     * @return bool
     */
    private function is_truthy_nobel($nobel) {
        $n = trim(wp_strip_all_tags((string) $nobel));
        if ($n === '') {
            return false;
        }
        $falsey = array('0', 'no', 'none', '-', 'No', 'ندارد', 'false');
        return !in_array(mb_strtolower($n, 'UTF-8'), $falsey, true);
    }

    /**
     * Plugin setting with Core fallback.
     *
     * @param string $key     Setting key.
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
            if (method_exists(Core::class, 'get_setting')) {
                return Core::get_setting($key, $default);
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
     * Absolute home URL.
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

    /**
     * Whether Rank Math is active.
     *
     * @return bool
     */
    private function is_rank_math_active() {
        if (class_exists(__NAMESPACE__ . '\\Core') && method_exists(Core::class, 'is_rank_math_active')) {
            return (bool) Core::is_rank_math_active();
        }
        if (defined('RANK_MATH_VERSION') || class_exists('RankMath') || class_exists('\\RankMath\\Helper')) {
            return true;
        }
        if (!function_exists('is_plugin_active')) {
            $plugin_php = ABSPATH . 'wp-admin/includes/plugin.php';
            if (is_readable($plugin_php)) {
                include_once $plugin_php;
            }
        }
        return function_exists('is_plugin_active') && is_plugin_active('seo-by-rank-math/rank-math.php');
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
        if (function_exists('wp_strip_all_tags')) {
            $text = wp_strip_all_tags($text, true);
        } else {
            $text = strip_tags($text);
        }
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Word count helper.
     *
     * @param string $html HTML or text.
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
}
