<?php
/**
 * Plugin Name: Qpedia Remove Four Obsolete Links
 * Description: One-time, update-only cleanup that removes four obsolete internal anchors while preserving their visible text.
 * Version: 1.0.0
 * Author: Qpedia
 */
if (!defined('ABSPATH')) exit;

final class Qpedia_Remove_Four_Obsolete_Links {
    const OPTION = 'qpedia_remove_four_obsolete_links_v1';
    const NONCE  = 'qpedia_remove_four_obsolete_links_execute';

    private static function jobs() {
        return array(
            array('id'=>303, 'slug'=>'vacuum-fluctuations', 'href'=>'https://qpedia.ir/lamb-shift/'),
            array('id'=>311, 'slug'=>'many-worlds-interpretation', 'href'=>'https://qpedia.ir/grw-collapse/'),
            array('id'=>312, 'slug'=>'qubit', 'href'=>'https://qpedia.ir/qubit-types-compared/'),
            array('id'=>313, 'slug'=>'how-lasers-work', 'href'=>'https://qpedia.ir/stimulated-emission/'),
        );
    }

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_qpedia_remove_four_links', array(__CLASS__, 'execute'));
    }

    public static function menu() {
        add_management_page('Qpedia Link Cleanup', 'Qpedia Link Cleanup', 'manage_options', 'qpedia-remove-four-links', array(__CLASS__, 'page'));
    }

    public static function page() {
        if (!current_user_can('manage_options')) return;
        $state = get_option(self::OPTION, array());
        echo '<div class="wrap"><h1>Qpedia: حذف چهار لینک منسوخ</h1>';
        echo '<p>این ابزار فقط تگ لینک را حذف می‌کند و متن قابل مشاهده، ساختار مقاله و تاریخ انتشار را نگه می‌دارد.</p>';
        echo '<p>Post IDهای هدف: <code>303, 311, 312, 313</code></p>';
        if (!empty($state['completed'])) {
            echo '<div class="notice notice-success"><p>عملیات قبلاً با موفقیت کامل شده است. افزونه را می‌توانید غیرفعال و حذف کنید.</p></div></div>';
            return;
        }
        if (!empty($state['errors'])) {
            echo '<div class="notice notice-error"><p>'.esc_html(implode(' | ', $state['errors'])).'</p></div>';
        }
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field(self::NONCE);
        echo '<input type="hidden" name="action" value="qpedia_remove_four_links">';
        submit_button('بررسی و حذف چهار لینک', 'primary');
        echo '</form></div>';
    }

    public static function execute() {
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);
        check_admin_referer(self::NONCE);
        $prepared = array(); $errors = array();

        // Preflight all four articles before changing any post.
        foreach (self::jobs() as $job) {
            $post = get_post($job['id']);
            if (!$post || $post->post_type !== 'quantum_article' || $post->post_name !== $job['slug']) {
                $errors[] = 'عدم تطبیق نوشته '.$job['id'].' / '.$job['slug'];
                continue;
            }
            $quoted = preg_quote($job['href'], '~');
            $pattern = '~<a\s+[^>]*href=(?:"|\')'.$quoted.'(?:"|\')[^>]*>(.*?)</a>~isu';
            $new_content = preg_replace($pattern, '$1', $post->post_content, 1, $count);
            if ($count !== 1 || $new_content === null) {
                $errors[] = 'لینک هدف در نوشته '.$job['id'].' دقیقاً یک‌بار پیدا نشد';
                continue;
            }
            $prepared[] = array('ID'=>$job['id'], 'post_content'=>$new_content);
        }

        if ($errors || count($prepared) !== 4) {
            update_option(self::OPTION, array('completed'=>false, 'errors'=>$errors), false);
            wp_safe_redirect(admin_url('tools.php?page=qpedia-remove-four-links')); exit;
        }

        foreach ($prepared as $update) {
            $result = wp_update_post(wp_slash($update), true);
            if (is_wp_error($result)) $errors[] = 'خطا در به‌روزرسانی نوشته '.$update['ID'].': '.$result->get_error_message();
        }
        update_option(self::OPTION, array('completed'=>empty($errors), 'errors'=>$errors, 'completed_at'=>current_time('mysql')), false);
        wp_safe_redirect(admin_url('tools.php?page=qpedia-remove-four-links')); exit;
    }
}
Qpedia_Remove_Four_Obsolete_Links::boot();
