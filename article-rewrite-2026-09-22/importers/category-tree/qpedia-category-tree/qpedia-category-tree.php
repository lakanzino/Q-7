<?php
/**
 * Plugin Name: Qpedia Category Tree Installer
 * Description: پاک‌سازی کامل دسته‌بندی‌های قبلی مقاله‌ها و ساخت ساختار نهایی: ۴ دسته مادر و ۱۲ دسته عمومی، بدون تغییر نوشته‌ها یا پیوند یکتای مقاله‌ها.
 * Version: 1.1.0
 * Author: Qpedia Editorial
 */

if (!defined('ABSPATH')) exit;

const QPCT_OPTION = 'qpedia_category_tree_installed_v2';
const QPCT_TAXONOMY = 'quantum_category';

add_action('admin_menu', function () {
    add_management_page(
        'ساخت دسته‌بندی Qpedia',
        'ساخت دسته‌بندی Qpedia',
        'manage_options',
        'qpedia-category-tree',
        'qpct_render_page'
    );
});

function qpct_tree() {
    return array(
        array(
            'name' => 'مبانی کوانتوم',
            'slug' => 'quantum-foundations',
            'children' => array(
                array('name' => 'مفاهیم پایه', 'slug' => 'quantum-basics'),
                array('name' => 'فلسفه کوانتوم', 'slug' => 'quantum-philosophy'),
                array('name' => 'ذرات و نیروها', 'slug' => 'particles-and-forces'),
            ),
        ),
        array(
            'name' => 'جهان کوانتومی',
            'slug' => 'quantum-world',
            'children' => array(
                array('name' => 'نور کوانتومی', 'slug' => 'quantum-light'),
                array('name' => 'مواد کوانتومی', 'slug' => 'quantum-materials'),
                array('name' => 'کیهان کوانتومی', 'slug' => 'quantum-universe'),
            ),
        ),
        array(
            'name' => 'فناوری کوانتومی',
            'slug' => 'quantum-technology',
            'children' => array(
                array('name' => 'رایانش کوانتومی', 'slug' => 'quantum-computing'),
                array('name' => 'ارتباطات کوانتومی', 'slug' => 'quantum-communications'),
                array('name' => 'هوش مصنوعی کوانتومی', 'slug' => 'quantum-ai'),
            ),
        ),
        array(
            'name' => 'کوانتوم و زندگی',
            'slug' => 'quantum-and-life',
            'children' => array(
                array('name' => 'حسگرها و ابزارها', 'slug' => 'quantum-tools'),
                array('name' => 'تاریخ و جامعه', 'slug' => 'quantum-history-society'),
                array('name' => 'زیست و پزشکی', 'slug' => 'quantum-biology-medicine'),
            ),
        ),
    );
}

function qpct_upsert_term($name, $slug, $parent) {
    $existing = get_term_by('slug', $slug, QPCT_TAXONOMY);
    if ($existing) {
        $updated = wp_update_term((int)$existing->term_id, QPCT_TAXONOMY, array(
            'name' => $name,
            'slug' => $slug,
            'parent' => (int)$parent,
        ));
        if (is_wp_error($updated)) return $updated;
        return (int)$updated['term_id'];
    }

    $created = wp_insert_term($name, QPCT_TAXONOMY, array(
        'slug' => $slug,
        'parent' => (int)$parent,
    ));
    if (is_wp_error($created)) return $created;
    return (int)$created['term_id'];
}

function qpct_install_tree() {
    if (!taxonomy_exists(QPCT_TAXONOMY)) {
        return new WP_Error('taxonomy_missing', 'تاکسونومی quantum_category در سایت فعال نیست. ابتدا پوسته یا افزونه اصلی Qpedia را فعال کنید.');
    }
    if (!is_taxonomy_hierarchical(QPCT_TAXONOMY)) {
        return new WP_Error('taxonomy_not_hierarchical', 'تاکسونومی quantum_category سلسله‌مراتبی نیست.');
    }

    // کاربر پاک‌سازی کامل را انتخاب کرده است: تمام termهای قدیمی این taxonomy
    // (همراه با رابطه‌های دسته‌بندی نوشته‌ها) حذف می‌شوند؛ خود نوشته‌ها دست‌نخورده می‌مانند.
    $old_terms = get_terms(array(
        'taxonomy' => QPCT_TAXONOMY,
        'hide_empty' => false,
        'fields' => 'ids',
    ));
    if (is_wp_error($old_terms)) return $old_terms;

    $deleted = 0;
    // ابتدا فرزندان حذف می‌شوند تا وابستگی والد مانع پاک‌سازی نشود.
    $old_terms = array_reverse(array_map('intval', $old_terms));
    foreach ($old_terms as $term_id) {
        $result = wp_delete_term($term_id, QPCT_TAXONOMY);
        if (is_wp_error($result)) return $result;
        if ($result !== false) $deleted++;
    }

    $created = 0;
    $updated = 0;
    foreach (qpct_tree() as $mother) {
        $was_existing = (bool)get_term_by('slug', $mother['slug'], QPCT_TAXONOMY);
        $mother_id = qpct_upsert_term($mother['name'], $mother['slug'], 0);
        if (is_wp_error($mother_id)) return $mother_id;
        $was_existing ? $updated++ : $created++;

        foreach ($mother['children'] as $child) {
            $was_existing = (bool)get_term_by('slug', $child['slug'], QPCT_TAXONOMY);
            $child_id = qpct_upsert_term($child['name'], $child['slug'], $mother_id);
            if (is_wp_error($child_id)) return $child_id;
            $was_existing ? $updated++ : $created++;
        }
    }

    clean_term_cache(array(), QPCT_TAXONOMY);
    return array('deleted' => $deleted, 'created' => $created, 'updated' => $updated, 'total' => 16);
}

function qpct_render_page() {
    if (!current_user_can('manage_options')) return;

    echo '<div class="wrap" dir="rtl"><h1>ساختار دسته‌بندی مقاله‌های Qpedia</h1>';
    echo '<p>این ابزار ابتدا <strong>تمام دسته‌های قبلی</strong> در <code>quantum_category</code> و اتصال آن‌ها به مقاله‌ها را حذف می‌کند؛ سپس فقط ۴ دسته مادر و ۱۲ دسته عمومی نهایی را می‌سازد.</p>';
    echo '<p><strong>خود مقاله‌ها ساخته، حذف یا ویرایش نمی‌شوند و پیوند یکتای تخت مقاله‌ها به شکل <code>qpedia.ir/postname/</code> تغییر نمی‌کند.</strong></p>';
    echo '<div class="notice notice-warning inline"><p><strong>هشدار:</strong> پس از اجرا، مقاله‌های قبلی بدون دسته خواهند بود تا آن‌ها را دستی یا با درون‌ریزهای مقاله در دسته‌های نهایی قرار دهید.</p></div>';

    $done = get_option(QPCT_OPTION);
    if ($done) {
        echo '<div class="notice notice-success"><p>ساختار در '.esc_html($done).' نصب شده است. افزونه را غیرفعال و حذف کنید.</p></div></div>';
        return;
    }

    if (isset($_POST['qpct_run'])) {
        check_admin_referer('qpct_install_tree');
        $result = qpct_install_tree();
        if (is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>'.esc_html($result->get_error_message()).'</p></div>';
        } else {
            update_option(QPCT_OPTION, current_time('mysql'), false);
            echo '<div class="notice notice-success"><p>انجام شد: '.(int)$result['deleted'].' دسته قبلی حذف و '.(int)$result['created'].' دسته نهایی ساخته شد؛ مجموع ساختار جدید ۱۶ دسته است.</p></div>';
            echo '<p><strong>افزونه را اکنون غیرفعال و حذف کنید.</strong></p></div>';
            return;
        }
    }

    echo '<form method="post">';
    wp_nonce_field('qpct_install_tree');
    submit_button('حذف دسته‌های قبلی و ساخت ۱۶ دسته نهایی', 'primary', 'qpct_run');
    echo '</form></div>';
}
