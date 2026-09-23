<?php
/**
 * Plugin Name: Qpedia Category Tree Installer
 * Description: ساخت یک‌بارمصرف ساختار نهایی دسته‌بندی مقاله‌ها: ۴ دسته مادر و ۱۲ دسته عمومی، بدون تغییر نوشته‌ها یا پیوند یکتای مقاله‌ها.
 * Version: 1.0.0
 * Author: Qpedia Editorial
 */

if (!defined('ABSPATH')) exit;

const QPCT_OPTION = 'qpedia_category_tree_installed_v1';
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
    return array('created' => $created, 'updated' => $updated, 'total' => 16);
}

function qpct_render_page() {
    if (!current_user_can('manage_options')) return;

    echo '<div class="wrap" dir="rtl"><h1>ساختار دسته‌بندی مقاله‌های Qpedia</h1>';
    echo '<p>این ابزار فقط ۴ دسته مادر و ۱۲ دسته عمومی را در <code>quantum_category</code> ایجاد یا اصلاح می‌کند.</p>';
    echo '<p><strong>هیچ مقاله‌ای ساخته، حذف یا ویرایش نمی‌شود و پیوند یکتای تخت مقاله‌ها به شکل <code>qpedia.ir/postname/</code> تغییر نمی‌کند.</strong></p>';

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
            echo '<div class="notice notice-success"><p>انجام شد: '.(int)$result['created'].' دسته ساخته و '.(int)$result['updated'].' دسته موجود اصلاح شد؛ مجموع ساختار ۱۶ دسته است.</p></div>';
            echo '<p><strong>افزونه را اکنون غیرفعال و حذف کنید.</strong></p></div>';
            return;
        }
    }

    echo '<form method="post">';
    wp_nonce_field('qpct_install_tree');
    submit_button('ایجاد ۴ دسته مادر و ۱۲ دسته عمومی', 'primary', 'qpct_run');
    echo '</form></div>';
}
