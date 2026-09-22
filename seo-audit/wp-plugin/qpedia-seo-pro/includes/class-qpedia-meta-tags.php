<?php
/**
 * Meta tag auditor for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Audits title/description/canonical/OG/Twitter/robots and meta conflicts.
 * Never overwrites Rank Math fields.
 */
class Meta_Tags {

    /**
     * Singleton instance.
     *
     * @var Meta_Tags|null
     */
    private static $instance = null;

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
     * @return Meta_Tags
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
     * Audit meta tags for posts from scan data or a live query.
     *
     * @param array $scan Optional collector payload.
     * @return array
     */
    public function audit_meta_tags($scan = array()) {
        $posts = $this->collect_posts($scan);
        $rows  = array();
        $summary = array(
            'total'              => 0,
            'missing_title'      => 0,
            'title_too_long'     => 0,
            'missing_description'=> 0,
            'desc_too_short'     => 0,
            'desc_too_long'      => 0,
            'missing_canonical'  => 0,
            'missing_og'         => 0,
            'missing_twitter'    => 0,
            'noindex'            => 0,
        );

        foreach ($posts as $item) {
            $row = $this->audit_one($item);
            $rows[] = $row;
            $summary['total']++;
            foreach (array('missing_title', 'title_too_long', 'missing_description', 'desc_too_short', 'desc_too_long', 'missing_canonical', 'missing_og', 'missing_twitter', 'noindex') as $k) {
                if (!empty($row['flags'][$k])) {
                    $summary[$k]++;
                }
            }
        }

        return array(
            'generated_at' => current_time('mysql'),
            'summary'      => $summary,
            'items'        => $rows,
        );
    }

    /**
     * Detect conflicts between rank_math_*, _qpedia_*, _yoast_*, _jetica_*.
     *
     * @param array $scan Optional scan data.
     * @return array
     */
    public function detect_conflicts($scan = array()) {
        global $wpdb;

        $conflicts = array();
        $legacy    = array();

        $like_keys = array(
            $wpdb->esc_like('rank_math_') . '%',
            $wpdb->esc_like('_qpedia_') . '%',
            $wpdb->esc_like('_yoast_') . '%',
            $wpdb->esc_like('_jetica_') . '%',
            $wpdb->esc_like('qpt_') . '%',
            $wpdb->esc_like('_qpt_') . '%',
        );

        $sql = "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE ";
        $parts = array();
        $args  = array();
        foreach ($like_keys as $like) {
            $parts[] = 'meta_key LIKE %s';
            $args[]  = $like;
        }
        $sql     .= implode(' OR ', $parts);
        $prepared = $wpdb->prepare($sql, $args);
        $rows     = $wpdb->get_results($prepared);

        $by_post = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $pid = (int) $row->post_id;
                if (!isset($by_post[$pid])) {
                    $by_post[$pid] = array();
                }
                $by_post[$pid][$row->meta_key] = $row->meta_value;
            }
        }

        $title_keys = array(
            'rank_math' => array('rank_math_title'),
            'qpedia'    => array('_qpedia_seo_title', '_qpedia_title'),
            'yoast'     => array('_yoast_wpseo_title'),
            'jetica'    => array('_jetica_title', '_jetica_seo_title'),
        );
        $desc_keys = array(
            'rank_math' => array('rank_math_description'),
            'qpedia'    => array('_qpedia_meta_description', '_qpedia_description'),
            'yoast'     => array('_yoast_wpseo_metadesc'),
            'jetica'    => array('_jetica_description', '_jetica_meta_description'),
        );
        $kw_keys = array(
            'rank_math' => array('rank_math_focus_keyword'),
            'qpedia'    => array('_qpedia_focus_keyphrase', '_qpedia_focus_keyword'),
            'yoast'     => array('_yoast_wpseo_focuskw'),
            'jetica'    => array('_jetica_focus_keyword', '_jetica_keyphrase'),
        );

        $families = array(
            'title'       => $title_keys,
            'description' => $desc_keys,
            'keyword'     => $kw_keys,
        );

        foreach ($by_post as $pid => $meta) {
            $post = get_post($pid);
            $title = $post ? $post->post_title : '';
            $type  = $post ? $post->post_type : '';

            foreach ($meta as $key => $val) {
                $family = $this->meta_family($key);
                if (in_array($family, array('yoast', 'jetica', 'qpt', 'qpedia'), true) && $this->meta_nonempty($val)) {
                    $legacy[] = array(
                        'post_id'    => $pid,
                        'post_title' => $title,
                        'post_type'  => $type,
                        'meta_key'   => $key,
                        'family'     => $family,
                        'length'     => $this->strlen($this->plain_text(is_string($val) ? $val : wp_json_encode($val))),
                    );
                }
            }

            foreach ($families as $field => $groups) {
                $present = array();
                $values  = array();
                foreach ($groups as $family => $keys) {
                    foreach ($keys as $key) {
                        if (isset($meta[$key]) && $this->meta_nonempty($meta[$key])) {
                            $present[]        = $family;
                            $values[$family]  = $this->plain_text(is_string($meta[$key]) ? $meta[$key] : '');
                            break;
                        }
                    }
                }
                $present = array_values(array_unique($present));
                if (count($present) < 2) {
                    continue;
                }
                $conflicts[] = array(
                    'post_id'    => $pid,
                    'post_title' => $title,
                    'post_type'  => $type,
                    'field'      => $field,
                    'families'   => $present,
                    'values'     => $values,
                    'winner'     => in_array('rank_math', $present, true) ? 'rank_math' : $present[0],
                    'action'     => __('Do not overwrite Rank Math. Clean legacy keys after backup.', 'qpedia-seo-pro'),
                );
            }
        }

        return array(
            'generated_at'     => current_time('mysql'),
            'conflicts'        => $conflicts,
            'legacy'           => $legacy,
            'conflict_count'   => count($conflicts),
            'legacy_count'     => count($legacy),
            'posts_with_meta'  => count($by_post),
        );
    }

    /**
     * Suggest title/description for posts missing them. Does not write Rank Math.
     *
     * @param array $scan Optional scan data.
     * @param bool  $persist_qpedia Write to empty _qpedia_* keys only.
     * @return array
     */
    public function generate_missing_meta($scan = array(), $persist_qpedia = false) {
        $posts       = $this->collect_posts($scan);
        $suggestions = array();

        foreach ($posts as $item) {
            $id    = (int) $item['id'];
            $title = $item['title'];
            $has_t = $this->meta_nonempty($item['seo_title']);
            $has_d = $this->meta_nonempty($item['seo_description']);

            if ($has_t && $has_d) {
                continue;
            }

            $title_s = $has_t ? $item['seo_title'] : $this->suggest_title($title);
            $desc_s  = $has_d ? $item['seo_description'] : $this->suggest_description($item);

            $row = array(
                'post_id'                 => $id,
                'post_type'               => $item['post_type'],
                'title'                   => $title,
                'had_title'               => $has_t,
                'had_description'         => $has_d,
                'title_suggestion'        => $title_s,
                'description_suggestion'  => $desc_s,
                'persisted_qpedia'        => false,
            );

            if ($persist_qpedia) {
                if (!$has_t && $this->meta($id, '_qpedia_seo_title') === '') {
                    update_post_meta($id, '_qpedia_seo_title', $title_s);
                    $row['persisted_qpedia'] = true;
                }
                if (!$has_d && $this->meta($id, '_qpedia_meta_description') === '') {
                    update_post_meta($id, '_qpedia_meta_description', $desc_s);
                    $row['persisted_qpedia'] = true;
                }
            }

            $suggestions[] = $row;
        }

        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($suggestions),
            'items'        => $suggestions,
            'note'         => __('Suggestions only. Rank Math fields are never overwritten.', 'qpedia-seo-pro'),
        );
    }

    /**
     * Audit a single normalized post item.
     *
     * @param array $item Normalized post.
     * @return array
     */
    public function audit_one($item) {
        $title = $item['seo_title'] !== '' ? $item['seo_title'] : $item['title'];
        $desc  = $item['seo_description'];
        $kw    = $item['focus_keyword'];
        $tlen  = $this->strlen($title);
        $dlen  = $this->strlen($desc);

        $issues = array();
        $flags  = array(
            'missing_title'       => false,
            'title_too_long'      => false,
            'title_no_keyword'    => false,
            'missing_description' => false,
            'desc_too_short'      => false,
            'desc_too_long'       => false,
            'desc_no_keyword'     => false,
            'missing_canonical'   => false,
            'canonical_mismatch'  => false,
            'missing_og'          => false,
            'missing_twitter'     => false,
            'noindex'             => false,
        );

        if ($item['seo_title'] === '') {
            $flags['missing_title'] = true;
            $issues[] = __('SEO title is missing (falls back to post title).', 'qpedia-seo-pro');
        }
        if ($tlen > 60) {
            $flags['title_too_long'] = true;
            $issues[] = sprintf(
                /* translators: %d: character length */
                __('SEO title is %d characters (max 60).', 'qpedia-seo-pro'),
                $tlen
            );
        }
        if ($kw !== '' && !$this->contains_keyword($title, $kw)) {
            $flags['title_no_keyword'] = true;
            $issues[] = __('Focus keyword is not in the title.', 'qpedia-seo-pro');
        }

        if ($desc === '') {
            $flags['missing_description'] = true;
            $issues[] = __('Meta description is missing.', 'qpedia-seo-pro');
        } else {
            if ($dlen < 120) {
                $flags['desc_too_short'] = true;
                $issues[] = sprintf(
                    /* translators: %d: length */
                    __('Meta description is %d characters (min 120).', 'qpedia-seo-pro'),
                    $dlen
                );
            }
            if ($dlen > 160) {
                $flags['desc_too_long'] = true;
                $issues[] = sprintf(
                    /* translators: %d: length */
                    __('Meta description is %d characters (max 160).', 'qpedia-seo-pro'),
                    $dlen
                );
            }
            if ($kw !== '' && !$this->contains_keyword($desc, $kw)) {
                $flags['desc_no_keyword'] = true;
                $issues[] = __('Focus keyword is not in the description.', 'qpedia-seo-pro');
            }
        }

        $canonical = $item['canonical'];
        if ($canonical === '') {
            $flags['missing_canonical'] = true;
            $issues[] = __('Canonical URL is not stored (theme/Rank Math may still output one).', 'qpedia-seo-pro');
        } elseif ($item['permalink'] !== '' && $this->normalize_url($canonical) !== $this->normalize_url($item['permalink'])) {
            $flags['canonical_mismatch'] = true;
            $issues[] = __('Canonical does not match the permalink.', 'qpedia-seo-pro');
        }

        $og_ok = $item['og_title'] !== '' && $item['og_description'] !== '' && $item['og_image'] !== '';
        if (!$og_ok) {
            $flags['missing_og'] = true;
            $issues[] = __('Open Graph fields are incomplete (og:title, og:description, og:image).', 'qpedia-seo-pro');
        }
        if ($item['og_locale'] !== '' && $item['og_locale'] !== 'fa_IR' && $item['og_locale'] !== 'fa-IR') {
            $issues[] = sprintf(
                /* translators: %s: locale */
                __('og:locale is %s (expected fa_IR).', 'qpedia-seo-pro'),
                $item['og_locale']
            );
        }

        $tw_ok = $item['twitter_card'] !== '' && $item['twitter_title'] !== '';
        if (!$tw_ok) {
            $flags['missing_twitter'] = true;
            $issues[] = __('Twitter Card fields are incomplete.', 'qpedia-seo-pro');
        }

        if ($this->is_noindex($item['robots'])) {
            $flags['noindex'] = true;
            $issues[] = __('Robots meta contains noindex.', 'qpedia-seo-pro');
        }

        return array(
            'post_id'     => (int) $item['id'],
            'post_type'   => $item['post_type'],
            'title'       => $item['title'],
            'permalink'   => $item['permalink'],
            'seo_title'   => $title,
            'title_len'   => $tlen,
            'description' => $desc,
            'desc_len'    => $dlen,
            'keyword'     => $kw,
            'canonical'   => $canonical !== '' ? $canonical : $item['permalink'],
            'og'          => array(
                'title'       => $item['og_title'],
                'description' => $item['og_description'],
                'image'       => $item['og_image'],
                'type'        => $item['og_type'],
                'url'         => $item['og_url'],
                'locale'      => $item['og_locale'] !== '' ? $item['og_locale'] : 'fa_IR',
            ),
            'twitter'     => array(
                'card'        => $item['twitter_card'] !== '' ? $item['twitter_card'] : 'summary_large_image',
                'title'       => $item['twitter_title'],
                'description' => $item['twitter_description'],
                'image'       => $item['twitter_image'],
            ),
            'robots'      => $item['robots'] !== '' ? $item['robots'] : 'index, follow',
            'flags'       => $flags,
            'issues'      => $issues,
            'issue_count' => count($issues),
        );
    }

    /**
     * Collect posts from scan or WP.
     *
     * @param array $scan Scan data.
     * @return array
     */
    private function collect_posts($scan) {
        $items = array();
        if (is_array($scan) && (!empty($scan['articles']) || !empty($scan['scientists']) || !empty($scan['pages']))) {
            foreach (array('articles', 'scientists', 'pages') as $bucket) {
                if (empty($scan[$bucket]) || !is_array($scan[$bucket])) {
                    continue;
                }
                foreach ($scan[$bucket] as $row) {
                    $items[] = $this->normalize_scan_item($row, $bucket);
                }
            }
            if (!empty($items)) {
                return $items;
            }
        }

        $types = array(
            'quantum_article'   => 'articles',
            'quantum_scientist' => 'scientists',
            'page'              => 'pages',
        );
        foreach ($types as $type => $bucket) {
            $posts = get_posts(
                array(
                    'post_type'      => $type,
                    'post_status'    => 'publish',
                    'posts_per_page' => -1,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                )
            );
            foreach ($posts as $post) {
                $items[] = $this->normalize_wp_post($post);
            }
        }
        return $items;
    }

    /**
     * Normalize a collector row.
     *
     * @param array  $row    Row.
     * @param string $bucket Bucket name.
     * @return array
     */
    private function normalize_scan_item($row, $bucket) {
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
        $get = function ($keys, $default = '') use ($row, $meta) {
            foreach ((array) $keys as $k) {
                if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) {
                    return $row[$k];
                }
                if (isset($meta[$k]) && $meta[$k] !== '' && $meta[$k] !== null) {
                    return $meta[$k];
                }
            }
            return $default;
        };

        $type = 'quantum_article';
        if ($bucket === 'scientists') {
            $type = 'quantum_scientist';
        } elseif ($bucket === 'pages') {
            $type = 'page';
        }
        if (!empty($row['post_type'])) {
            $type = $row['post_type'];
        } elseif (!empty($row['type'])) {
            $type = $row['type'];
        }

        $permalink = $get(array('permalink', 'url', 'link'));
        if ($permalink === '' && $id) {
            $permalink = get_permalink($id);
        }

        $content = $get(array('content', 'post_content'));
        $excerpt = $get(array('excerpt', 'post_excerpt'));
        $thumb   = '';
        if (isset($row['thumbnail']['url'])) {
            $thumb = $row['thumbnail']['url'];
        } elseif (isset($row['thumbnail_url'])) {
            $thumb = $row['thumbnail_url'];
        }

        $og_image = $get(array('rank_math_facebook_image', 'og_image'));
        if ($og_image === '' && $thumb) {
            $og_image = $thumb;
        }

        return array(
            'id'                   => $id,
            'post_type'            => $type,
            'title'                => (string) $get(array('title', 'post_title')),
            'content'              => (string) $content,
            'excerpt'              => (string) $excerpt,
            'permalink'            => (string) $permalink,
            'seo_title'            => $this->plain_text((string) $get(array('rank_math_title', 'seo_title', '_qpedia_seo_title'))),
            'seo_description'      => $this->plain_text((string) $get(array('rank_math_description', 'seo_description', '_qpedia_meta_description'))),
            'focus_keyword'        => $this->plain_text((string) $get(array('rank_math_focus_keyword', 'focus_keyword', '_qpedia_focus_keyphrase'))),
            'canonical'            => (string) $get(array('rank_math_canonical_url', 'canonical', '_yoast_wpseo_canonical')),
            'og_title'             => (string) $get(array('rank_math_facebook_title', 'og_title')),
            'og_description'       => (string) $get(array('rank_math_facebook_description', 'og_description')),
            'og_image'             => (string) $og_image,
            'og_type'              => (string) $get(array('og_type'), $type === 'page' ? 'website' : 'article'),
            'og_url'               => (string) $get(array('og_url'), $permalink),
            'og_locale'            => (string) $get(array('og_locale'), 'fa_IR'),
            'twitter_card'         => (string) $get(array('rank_math_twitter_card_type', 'twitter_card')),
            'twitter_title'        => (string) $get(array('rank_math_twitter_title', 'twitter_title')),
            'twitter_description'  => (string) $get(array('rank_math_twitter_description', 'twitter_description')),
            'twitter_image'        => (string) $get(array('rank_math_twitter_image', 'twitter_image')),
            'robots'               => $this->stringify_robots($get(array('rank_math_robots', 'robots', '_yoast_wpseo_meta-robots-noindex'))),
        );
    }

    /**
     * Normalize a WP_Post into the audit shape.
     *
     * @param \WP_Post $post Post.
     * @return array
     */
    private function normalize_wp_post($post) {
        $id        = (int) $post->ID;
        $permalink = get_permalink($post);
        $thumb_id  = (int) get_post_thumbnail_id($id);
        $thumb     = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'full') : '';

        $seo_title = $this->meta($id, 'rank_math_title');
        if ($seo_title === '') {
            $seo_title = $this->meta($id, '_qpedia_seo_title');
        }
        $seo_desc = $this->meta($id, 'rank_math_description');
        if ($seo_desc === '') {
            $seo_desc = $this->meta($id, '_qpedia_meta_description');
        }
        $kw = $this->meta($id, 'rank_math_focus_keyword');
        if ($kw === '') {
            $kw = $this->meta($id, '_qpedia_focus_keyphrase');
        }

        $og_image = $this->meta($id, 'rank_math_facebook_image');
        if ($og_image === '' && $thumb) {
            $og_image = $thumb;
        }
        $tw_image = $this->meta($id, 'rank_math_twitter_image');
        if ($tw_image === '' && $og_image) {
            $tw_image = $og_image;
        }

        $robots = get_post_meta($id, 'rank_math_robots', true);

        return array(
            'id'                   => $id,
            'post_type'            => $post->post_type,
            'title'                => $post->post_title,
            'content'              => $post->post_content,
            'excerpt'              => $post->post_excerpt,
            'permalink'            => is_string($permalink) ? $permalink : '',
            'seo_title'            => $this->plain_text($seo_title),
            'seo_description'      => $this->plain_text($seo_desc),
            'focus_keyword'        => $this->plain_text($kw),
            'canonical'            => $this->meta($id, 'rank_math_canonical_url'),
            'og_title'             => $this->meta($id, 'rank_math_facebook_title'),
            'og_description'       => $this->meta($id, 'rank_math_facebook_description'),
            'og_image'             => $og_image,
            'og_type'              => $post->post_type === 'page' ? 'website' : 'article',
            'og_url'               => is_string($permalink) ? $permalink : '',
            'og_locale'            => 'fa_IR',
            'twitter_card'         => $this->meta($id, 'rank_math_twitter_card_type'),
            'twitter_title'        => $this->meta($id, 'rank_math_twitter_title'),
            'twitter_description'  => $this->meta($id, 'rank_math_twitter_description'),
            'twitter_image'        => $tw_image,
            'robots'               => $this->stringify_robots($robots),
        );
    }

    /**
     * Suggest an SEO title from the post title.
     *
     * @param string $title Title.
     * @return string
     */
    private function suggest_title($title) {
        $title = $this->plain_text($title);
        $suffix = ' | ' . self::SITE_NAME;
        $combined = $title . $suffix;
        if ($this->strlen($combined) <= 60) {
            return $combined;
        }
        if ($this->strlen($title) <= 60) {
            return $title;
        }
        return $this->truncate($title, 60);
    }

    /**
     * Suggest a meta description from excerpt or first 160 chars.
     *
     * @param array $item Item.
     * @return string
     */
    private function suggest_description($item) {
        $excerpt = $this->plain_text(isset($item['excerpt']) ? $item['excerpt'] : '');
        if ($excerpt !== '') {
            return $this->truncate($excerpt, 160);
        }
        $content = $this->plain_text(isset($item['content']) ? $item['content'] : '');
        return $this->truncate($content, 160);
    }

    /**
     * Rank Math robots may be serialized array.
     *
     * @param mixed $robots Robots meta.
     * @return string
     */
    private function stringify_robots($robots) {
        if (is_array($robots)) {
            $flat = array();
            foreach ($robots as $k => $v) {
                if (is_string($v) && $v !== '' && $v !== 'off') {
                    $flat[] = $v;
                } elseif ($v === 'on' && is_string($k)) {
                    $flat[] = $k;
                } elseif (is_string($k) && $v) {
                    $flat[] = $k;
                }
            }
            return implode(', ', $flat);
        }
        if (is_string($robots)) {
            $maybe = maybe_unserialize($robots);
            if (is_array($maybe)) {
                return $this->stringify_robots($maybe);
            }
            return $robots;
        }
        return '';
    }

    /**
     * Whether robots string contains noindex.
     *
     * @param string $robots Robots.
     * @return bool
     */
    private function is_noindex($robots) {
        return is_string($robots) && stripos($robots, 'noindex') !== false;
    }

    /**
     * Keyword appears in text (case-insensitive, first phrase).
     *
     * @param string $text Text.
     * @param string $kw   Keyword.
     * @return bool
     */
    private function contains_keyword($text, $kw) {
        $kw = trim(strtok(str_replace('،', ',', $kw), ','));
        if ($kw === '') {
            return false;
        }
        if (function_exists('mb_stripos')) {
            return false !== mb_stripos($text, $kw, 0, 'UTF-8');
        }
        return false !== stripos($text, $kw);
    }

    /**
     * Family of a meta key.
     *
     * @param string $key Meta key.
     * @return string
     */
    private function meta_family($key) {
        if (strpos($key, 'rank_math_') === 0) {
            return 'rank_math';
        }
        if (strpos($key, '_yoast_') === 0) {
            return 'yoast';
        }
        if (strpos($key, '_jetica_') === 0) {
            return 'jetica';
        }
        if (strpos($key, '_qpedia_') === 0) {
            return 'qpedia';
        }
        if (strpos($key, 'qpt_') === 0 || strpos($key, '_qpt_') === 0) {
            return 'qpt';
        }
        return 'other';
    }

    /**
     * Non-empty meta value.
     *
     * @param mixed $val Value.
     * @return bool
     */
    private function meta_nonempty($val) {
        if ($val === null || $val === false || $val === '') {
            return false;
        }
        if (is_array($val)) {
            return !empty($val);
        }
        return trim((string) $val) !== '';
    }

    /**
     * Single meta string.
     *
     * @param int    $post_id Post ID.
     * @param string $key     Key.
     * @return string
     */
    private function meta($post_id, $key) {
        $val = get_post_meta($post_id, $key, true);
        if (is_array($val)) {
            return trim(implode(', ', array_filter(array_map('strval', $val))));
        }
        return is_string($val) ? trim($val) : '';
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
     * Truncate on a word boundary.
     *
     * @param string $text   Text.
     * @param int    $length Length.
     * @return string
     */
    private function truncate($text, $length) {
        $text = trim($text);
        if ($this->strlen($text) <= $length) {
            return $text;
        }
        if (function_exists('mb_substr') && function_exists('mb_strrpos')) {
            $cut = mb_substr($text, 0, $length, 'UTF-8');
            $sp  = mb_strrpos($cut, ' ', 0, 'UTF-8');
            if (false !== $sp && $sp > ($length * 0.6)) {
                $cut = mb_substr($cut, 0, $sp, 'UTF-8');
            }
            return rtrim($cut, ' ،,;؛.') . '…';
        }
        return rtrim(substr($text, 0, $length)) . '…';
    }

    /**
     * Normalize URL.
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
            $path .= '/';
        }
        return $host . $path;
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
