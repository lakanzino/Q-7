<?php
/**
 * Image auditor for Qpedia SEO Pro.
 *
 * @package QpediaSEO
 */

namespace QpediaSEO;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Audits attachment alt text, dimensions, featured usage and filesize.
 */
class Image_Audit {

    /**
     * Singleton instance.
     *
     * @var Image_Audit|null
     */
    private static $instance = null;

    /**
     * Oversized threshold in bytes (200 KB).
     *
     * @var int
     */
    const OVERSIZE_BYTES = 204800;

    /**
     * Alt length warning.
     *
     * @var int
     */
    const ALT_MAX = 125;

    /**
     * Get singleton instance.
     *
     * @return Image_Audit
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
     * Audit all image attachments.
     *
     * @param array $scan Optional collector images list.
     * @return array
     */
    public function audit_all_images($scan = array()) {
        $images = $this->collect_images($scan);
        $featured_ids = $this->featured_ids();

        $items   = array();
        $summary = array(
            'total'            => 0,
            'missing_alt'      => 0,
            'alt_too_long'     => 0,
            'unattached'       => 0,
            'featured'         => 0,
            'oversized'        => 0,
            'no_dimensions'    => 0,
            'webp'             => 0,
        );

        foreach ($images as $img) {
            $id      = (int) $img['id'];
            $alt     = isset($img['alt']) ? (string) $img['alt'] : '';
            $alt_len = $this->strlen($alt);
            $parent  = isset($img['parent']) ? (int) $img['parent'] : 0;
            $width   = isset($img['width']) ? (int) $img['width'] : 0;
            $height  = isset($img['height']) ? (int) $img['height'] : 0;
            $bytes   = isset($img['filesize']) ? (int) $img['filesize'] : 0;
            $mime    = isset($img['mime']) ? (string) $img['mime'] : '';
            $is_feat = isset($featured_ids[$id]);

            $issues = array();
            if (trim($alt) === '') {
                $issues[] = 'missing_alt';
                $summary['missing_alt']++;
            } elseif ($alt_len > self::ALT_MAX) {
                $issues[] = 'alt_too_long';
                $summary['alt_too_long']++;
            }
            if ($parent < 1) {
                $issues[] = 'unattached';
                $summary['unattached']++;
            }
            if ($is_feat) {
                $summary['featured']++;
            }
            if ($bytes > self::OVERSIZE_BYTES) {
                $issues[] = 'oversized';
                $summary['oversized']++;
            }
            if ($width < 1 || $height < 1) {
                $issues[] = 'no_dimensions';
                $summary['no_dimensions']++;
            }
            if (stripos($mime, 'webp') !== false || (isset($img['file']) && preg_match('/\.webp$/i', $img['file']))) {
                $summary['webp']++;
            }

            $keyword_hit = false;
            if ($alt !== '' && $parent > 0) {
                $keyword_hit = $this->alt_has_parent_keyword($alt, $parent);
                if (!$keyword_hit) {
                    $issues[] = 'alt_missing_keyword';
                }
            }

            $summary['total']++;
            $items[] = array(
                'id'            => $id,
                'title'         => isset($img['title']) ? $img['title'] : '',
                'url'           => isset($img['url']) ? $img['url'] : '',
                'file'          => isset($img['file']) ? $img['file'] : '',
                'alt'           => $alt,
                'alt_length'    => $alt_len,
                'width'         => $width,
                'height'        => $height,
                'filesize'      => $bytes,
                'filesize_kb'   => round($bytes / 1024, 1),
                'mime'          => $mime,
                'post_parent'   => $parent,
                'is_featured'   => $is_feat,
                'keyword_in_alt'=> $keyword_hit,
                'issues'        => $issues,
            );
        }

        return array(
            'generated_at' => current_time('mysql'),
            'summary'      => $summary,
            'items'        => $items,
        );
    }

    /**
     * Articles and scientists without a featured image.
     *
     * @return array
     */
    public function find_missing_thumbnails() {
        $missing = array();
        foreach (array('quantum_article', 'quantum_scientist') as $type) {
            $posts = get_posts(
                array(
                    'post_type'      => $type,
                    'post_status'    => array('publish', 'draft'),
                    'posts_per_page' => -1,
                )
            );
            foreach ($posts as $post) {
                $thumb = (int) get_post_thumbnail_id($post->ID);
                if ($thumb < 1) {
                    $thumb = (int) get_post_meta($post->ID, '_thumbnail_id', true);
                }
                if ($thumb < 1) {
                    $missing[] = array(
                        'id'         => (int) $post->ID,
                        'title'      => $post->post_title,
                        'post_type'  => $post->post_type,
                        'status'     => $post->post_status,
                        'permalink'  => get_permalink($post),
                    );
                }
            }
        }
        return array(
            'generated_at' => current_time('mysql'),
            'count'        => count($missing),
            'items'        => $missing,
        );
    }

    /**
     * Images larger than 200 KB.
     *
     * @param array $scan Optional scan.
     * @return array
     */
    public function find_oversized_images($scan = array()) {
        $audit = $this->audit_all_images($scan);
        $rows  = array();
        foreach ($audit['items'] as $img) {
            if ($img['filesize'] > self::OVERSIZE_BYTES) {
                $rows[] = array(
                    'id'          => $img['id'],
                    'title'       => $img['title'],
                    'url'         => $img['url'],
                    'filesize'    => $img['filesize'],
                    'filesize_kb' => $img['filesize_kb'],
                    'width'       => $img['width'],
                    'height'      => $img['height'],
                    'suggestion'  => __('Compress or serve a resized WebP derivative under 200 KB.', 'qpedia-seo-pro'),
                );
            }
        }
        usort(
            $rows,
            function ($a, $b) {
                return $b['filesize'] - $a['filesize'];
            }
        );
        return array(
            'generated_at' => current_time('mysql'),
            'threshold_kb' => 200,
            'count'        => count($rows),
            'items'        => $rows,
        );
    }

    /**
     * Collect image records from scan or attachments query.
     *
     * @param array $scan Scan.
     * @return array
     */
    private function collect_images($scan) {
        if (is_array($scan) && !empty($scan['images']) && is_array($scan['images'])) {
            $out = array();
            foreach ($scan['images'] as $row) {
                $out[] = $this->normalize_scan_image($row);
            }
            if (!empty($out)) {
                return $out;
            }
        }

        $query = new \WP_Query(
            array(
                'post_type'      => 'attachment',
                'post_status'    => 'inherit',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'no_found_rows'  => true,
            )
        );

        $out = array();
        foreach ($query->posts as $att) {
            $out[] = $this->normalize_attachment($att);
        }
        return $out;
    }

    /**
     * Normalize collector image row.
     *
     * @param array $row Row.
     * @return array
     */
    private function normalize_scan_image($row) {
        $id = 0;
        if (isset($row['id'])) {
            $id = (int) $row['id'];
        } elseif (isset($row['ID'])) {
            $id = (int) $row['ID'];
        }
        $meta = isset($row['metadata']) && is_array($row['metadata']) ? $row['metadata'] : (isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : array());
        $bytes = 0;
        if (isset($row['filesize'])) {
            $bytes = (int) $row['filesize'];
        } elseif (isset($row['file_size'])) {
            $bytes = (int) $row['file_size'];
        } elseif (isset($meta['filesize'])) {
            $bytes = (int) $meta['filesize'];
        }
        $width  = isset($row['width']) ? (int) $row['width'] : (isset($meta['width']) ? (int) $meta['width'] : 0);
        $height = isset($row['height']) ? (int) $row['height'] : (isset($meta['height']) ? (int) $meta['height'] : 0);
        $alt    = '';
        if (isset($row['alt'])) {
            $alt = $row['alt'];
        } elseif (isset($row['alt_text'])) {
            $alt = $row['alt_text'];
        }
        $parent = 0;
        if (isset($row['parent'])) {
            $parent = (int) $row['parent'];
        } elseif (isset($row['post_parent'])) {
            $parent = (int) $row['post_parent'];
        } elseif (isset($row['attached_to'])) {
            $parent = (int) $row['attached_to'];
        }
        return array(
            'id'       => $id,
            'title'    => isset($row['title']) ? $row['title'] : (isset($row['post_title']) ? $row['post_title'] : ''),
            'url'      => isset($row['url']) ? $row['url'] : (isset($row['src']) ? $row['src'] : ''),
            'file'     => isset($row['file']) ? $row['file'] : (isset($meta['file']) ? $meta['file'] : ''),
            'alt'      => (string) $alt,
            'width'    => $width,
            'height'   => $height,
            'filesize' => $bytes,
            'mime'     => isset($row['mime']) ? $row['mime'] : (isset($row['post_mime_type']) ? $row['post_mime_type'] : ''),
            'parent'   => $parent,
        );
    }

    /**
     * Normalize a WP attachment.
     *
     * @param \WP_Post $att Attachment.
     * @return array
     */
    private function normalize_attachment($att) {
        $id      = (int) $att->ID;
        $meta    = wp_get_attachment_metadata($id);
        if (!is_array($meta)) {
            $meta = array();
        }
        $bytes = 0;
        if (isset($meta['filesize'])) {
            $bytes = (int) $meta['filesize'];
        }
        if ($bytes < 1) {
            $file = get_attached_file($id);
            if (is_string($file) && $file !== '' && file_exists($file)) {
                $bytes = (int) filesize($file);
            }
        }
        $src = wp_get_attachment_url($id);
        return array(
            'id'       => $id,
            'title'    => $att->post_title,
            'url'      => is_string($src) ? $src : '',
            'file'     => isset($meta['file']) ? $meta['file'] : '',
            'alt'      => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
            'width'    => isset($meta['width']) ? (int) $meta['width'] : 0,
            'height'   => isset($meta['height']) ? (int) $meta['height'] : 0,
            'filesize' => $bytes,
            'mime'     => $att->post_mime_type,
            'parent'   => (int) $att->post_parent,
        );
    }

    /**
     * Map of attachment IDs used as featured images.
     *
     * @return array id => true
     */
    private function featured_ids() {
        global $wpdb;
        $ids  = array();
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s AND p.post_status IN (%s,%s) AND p.post_type IN (%s,%s,%s)",
                '_thumbnail_id',
                'publish',
                'draft',
                'quantum_article',
                'quantum_scientist',
                'page'
            )
        );
        if (is_array($rows)) {
            foreach ($rows as $v) {
                $id = (int) $v;
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }
        return $ids;
    }

    /**
     * Whether alt contains parent focus keyword.
     *
     * @param string $alt    Alt.
     * @param int    $parent Parent post ID.
     * @return bool
     */
    private function alt_has_parent_keyword($alt, $parent) {
        $kw = (string) get_post_meta($parent, 'rank_math_focus_keyword', true);
        if ($kw === '') {
            $kw = (string) get_post_meta($parent, '_qpedia_focus_keyphrase', true);
        }
        if ($kw === '') {
            $parent_post = get_post($parent);
            $kw          = $parent_post ? $parent_post->post_title : '';
        }
        $kw = trim(strtok(str_replace('،', ',', $kw), ','));
        if ($kw === '') {
            return false;
        }
        if (function_exists('mb_stripos')) {
            return false !== mb_stripos($alt, $kw, 0, 'UTF-8');
        }
        return false !== stripos($alt, $kw);
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
}
