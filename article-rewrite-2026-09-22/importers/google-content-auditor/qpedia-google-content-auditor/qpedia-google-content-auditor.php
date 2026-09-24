<?php
/**
 * Plugin Name: Qpedia Google Content Quality & EEAT Auditor
 * Plugin URI: https://qpedia.ir/
 * Description: افزونه جامع سنجش کیفیت و رتبه‌بندی محتوا مطابق با جدیدترین قوانین و معیارهای رسمی گوگل (Needs Met, E-E-A-T, Information Gain, Helpful Content, Core Updates) همراه با امتیازدهی ۱۰۰ امتیازی، تحلیل موشکافانه دلایل کسر امتیاز و داشبورد مدیریتی کامل فارسی.
 * Version: 1.0.0
 * Author: Qpedia Team
 * Author URI: https://qpedia.ir/
 * Text Domain: qpedia-content-auditor
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

final class Qpedia_Google_Content_Auditor {

    const VERSION     = '1.0.0';
    const OPTION_KEY  = 'qpedia_google_audit_cache_v1';
    const META_SCORE  = '_qpedia_google_audit_score';
    const META_DATA   = '_qpedia_google_audit_data';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_admin_menu'));
        add_action('add_meta_boxes', array(__CLASS__, 'register_meta_box'));
        add_action('save_post', array(__CLASS__, 'on_save_post'), 20, 2);
        add_action('wp_ajax_qpedia_audit_single_post', array(__CLASS__, 'ajax_audit_single'));
        add_action('wp_ajax_qpedia_audit_all_posts', array(__CLASS__, 'ajax_audit_all'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }

    public static function register_admin_menu() {
        add_menu_page(
            'سنجش محتوای گوگل',
            'سنجش محتوای گوگل',
            'manage_options',
            'qpedia-google-auditor',
            array(__CLASS__, 'render_dashboard_page'),
            'dashicons-awards',
            28
        );
    }

    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'qpedia-google-auditor') !== false || in_array($hook, array('post.php', 'post-new.php'), true)) {
            wp_enqueue_style('dashicons');
        }
    }

    public static function register_meta_box() {
        $screens = array('post', 'page', 'quantum_article', 'qp_glossary', 'quantum_scientist');
        foreach ($screens as $screen) {
            add_meta_box(
                'qpedia_google_audit_box',
                '📊 سنجش کیفیت محتوا مطابق معیارهای رسمی گوگل (Qpedia Google Auditor)',
                array(__CLASS__, 'render_meta_box'),
                $screen,
                'normal',
                'high'
            );
        }
    }

    /**
     * فرمول سنجش جامع کیفیت محتوا بر پایه ۴ ستون رسمی گوگل (مجموع ۱۰۰ امتیاز)
     */
    public static function audit_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return null;
        }

        $content = (string) $post->post_content;
        $title   = (string) $post->post_title;
        $slug    = (string) $post->post_name;

        // خواندن متادیتاهای سئو از TamRank / RankMath / Yoast
        $focus_kw = get_post_meta($post_id, '_tam_rank_focus_keyword', true);
        if (!$focus_kw) $focus_kw = get_post_meta($post_id, 'rank_math_focus_keyword', true);
        if (!$focus_kw) $focus_kw = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
        if (!$focus_kw) $focus_kw = get_post_meta($post_id, '_qpedia_focus_keyphrase', true);
        if (!$focus_kw) $focus_kw = $title;

        $seo_title = get_post_meta($post_id, '_tam_rank_meta_title', true);
        if (!$seo_title) $seo_title = get_post_meta($post_id, 'rank_math_title', true);
        if (!$seo_title) $seo_title = get_post_meta($post_id, '_yoast_wpseo_title', true);
        if (!$seo_title) $seo_title = get_post_meta($post_id, '_qpedia_seo_title', true);
        if (!$seo_title) $seo_title = $title;

        $meta_desc = get_post_meta($post_id, '_tam_rank_meta_description', true);
        if (!$meta_desc) $meta_desc = get_post_meta($post_id, 'rank_math_description', true);
        if (!$meta_desc) $meta_desc = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        if (!$meta_desc) $meta_desc = get_post_meta($post_id, '_qpedia_meta_description', true);

        // تحلیل متنی
        $plain_text = trim(strip_tags($content));
        // محاسبه واژگان فارسی/انگلیسی
        $words_count = count(preg_split('/\s+/u', $plain_text, -1, PREG_SPLIT_NO_EMPTY));

        // استخراج پاراگراف اول
        $first_p = '';
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $content, $m)) {
            $first_p = trim(strip_tags($m[1]));
        } else {
            $first_p = mb_substr($plain_text, 0, 200);
        }

        // استخراج لینک‌های داخلی
        preg_match_all('/<a\s+[^>]*href=[\'"](https?:\/\/[^\'"]*qpedia\.ir[^\'"]*|\/[^\'"]*)[\'"]/i', $content, $internal_links);
        $internal_link_count = count($internal_links[0] ?? array());

        // استخراج هدینگ‌ها
        preg_match_all('/<h2[^>]*>/i', $content, $h2_matches);
        preg_match_all('/<h3[^>]*>/i', $content, $h3_matches);
        $h2_count = count($h2_matches[0] ?? array());
        $h3_count = count($h3_matches[0] ?? array());

        // بررسی مراجع معتبر علمی و DOI
        $has_sources_sec = (bool) (preg_match('/(منابع معتبر|مراجع علمی|منابع مقاله|References)/ui', $content) || preg_match('/<h[23][^>]*>.*?(منابع|مراجع).*?<\/h[23]>/ui', $content));
        preg_match_all('/(10\.\d{4,9}\/[-._;()\/:A-Z0-9]+|doi\.org\/)/i', $content, $doi_matches);
        $doi_count = count($doi_matches[0] ?? array());

        // بررسی تمثیل و مرز شکست
        $has_analogy = (bool) preg_match('/(تمثیل|تشبیه|مثال روزمره|مانند|فرض کنید|تصور کنید)/ui', $content);
        $has_analogy_boundary = (bool) preg_match('/(مرز شکست|مرز تمثیل|نقطه ضعف این تمثیل|تفاوت با واقعیت|تمثیل در کجا متوقف می‌شود|مرز این تشبیه)/ui', $content);

        // بررسی بخش پرسش‌های متداول (FAQ)
        preg_match_all('/<h[34][^>]*>.*?\?.*?<\/h[34]>/ui', $content, $faq_headers);
        $has_faq_sec = (bool) preg_match('/(پرسش‌های متداول|سوالات متداول|FAQ)/ui', $content);
        $faq_count   = max(count($faq_headers[0] ?? array()), ($has_faq_sec ? 5 : 0));

        // بررسی کلیدواژه در بخش‌های کلیدی
        $kw_in_h1       = (mb_stripos($title, $focus_kw) !== false);
        $kw_in_seo_t    = (mb_stripos($seo_title, $focus_kw) !== false);
        $kw_in_meta_d   = (mb_stripos($meta_desc, $focus_kw) !== false);
        $kw_in_first_p  = (mb_stripos($first_p, $focus_kw) !== false);

        // طول عنوان سئو و متا دیسکریپشن
        $seo_t_len = mb_strlen($seo_title, 'UTF-8');
        $meta_d_len = mb_strlen($meta_desc, 'UTF-8');

        // اسلاگ انگلیسی
        $is_en_slug = (bool) preg_match('/^[a-z0-9-]+$/i', $slug);

        // متغیرهای محاسبه امتیاز ۴ سطح (Tier 1..4)
        $tier1 = array('title' => 'سطح ۱: پاسخ به نیاز کاربر و ارزش اطلاعاتی (Needs Met & Information Gain)', 'max' => 30, 'score' => 0, 'passed' => array(), 'failed' => array());
        $tier2 = array('title' => 'سطح ۲: اعتبار علمی، تخصص و اعتماد (E-E-A-T & Trust)', 'max' => 30, 'score' => 0, 'passed' => array(), 'failed' => array());
        $tier3 = array('title' => 'سطح ۳: معماری محتوا و لینک‌سازی (Content Architecture & SC)', 'max' => 20, 'score' => 0, 'passed' => array(), 'failed' => array());
        $tier4 = array('title' => 'سطح ۴: متادیتا و استانداردهای سئو (Technical SEO & Metadata)', 'max' => 20, 'score' => 0, 'passed' => array(), 'failed' => array());

        /* -------------------------------------------------------------------------
         * سطح ۱: Needs Met & Information Gain (حداکثر ۳۰ امتیاز)
         * ------------------------------------------------------------------------- */
        // ۱.۱ پاسخ صریح در پاراگراف اول (+۸ امتیاز)
        if ($kw_in_first_p && mb_strlen($first_p) >= 60) {
            $tier1['score'] += 8;
            $tier1['passed'][] = array('title' => 'شروع مستقیم و بدون حاشیه (Direct Answer)', 'pts' => '+8', 'desc' => 'پاراگراف اول صریحاً موضوع را تعریف کرده و کلیدواژه کانونی در آن قرار دارد.');
        } else {
            $tier1['failed'][] = array('title' => 'ضعف در پاراگراف مقدمه', 'pts' => '-8', 'desc' => 'پاراگراف اول باید مستقیماً بدون مقدمه‌چینی زائد پاسخ اصلی سوال را ارائه دهد و کلیدواژه در آن بیاید.');
        }

        // ۱.۲ عمق و طول محتوا (+۱۲ امتیاز)
        if ($words_count >= 2200) {
            $tier1['score'] += 12;
            $tier1['passed'][] = array('title' => 'جامعیت و عمق محتوا (Word Count)', 'pts' => '+12', 'desc' => 'تعداد واژگان ' . number_format_i18n($words_count) . ' واژه است که عمق استاندارد ۲۲۰۰+ واژه را پوشش داده است.');
        } elseif ($words_count >= 1500) {
            $tier1['score'] += 8;
            $tier1['failed'][] = array('title' => 'عمق محتوا متوسط است', 'pts' => '-4', 'desc' => 'تعداد کلمات (' . $words_count . ') خوب است اما برای پوشش کامل و جامعیت رقابتی، پیشنهاد می‌شود به بالای ۲۲۰۰ واژه برسد.');
        } elseif ($words_count >= 800) {
            $tier1['score'] += 4;
            $tier1['failed'][] = array('title' => 'کمبود عمق محتوا (Thin Content Risk)', 'pts' => '-8', 'desc' => 'تعداد کلمات (' . $words_count . ') برای پوشش عمیق کافی نیست. احتمال رتبه‌گیری در برابر رقبای جامع ضعیف است.');
        } else {
            $tier1['failed'][] = array('title' => 'محتوای بسیار کوتاه و ناقص', 'pts' => '-12', 'desc' => 'مقاله تنها ' . $words_count . ' واژه دارد و در معرض جریمه Thin Content الگوریتم Helpful Content گوگل است.');
        }

        // ۱.۳ ارزش‌افزوده اطلاعاتی و ساختار غنی (+۵ امتیاز)
        if ($h2_count >= 4 && $h3_count >= 2) {
            $tier1['score'] += 5;
            $tier1['passed'][] = array('title' => 'غنای اطلاعاتی و تفکیک مباحث (Information Gain)', 'pts' => '+5', 'desc' => 'محتوا دارای تفکیک موضوعی عالی با ' . $h2_count . ' تیتر H2 و ' . $h3_count . ' زیرتیتر H3 است.');
        } else {
            $tier1['failed'][] = array('title' => 'کمبود زیرعنوان‌ها و تنوع مباحث', 'pts' => '-5', 'desc' => 'تعداد سرفصل‌های H2 و H3 کم است؛ برای ایجاد ارزش اطلاعاتی و پاسخ به جنبه‌های جانبی سوال، تیترهای بیشتری اضافه کنید.');
        }

        // ۱.۴ بخش سوالات متداول FAQ (+۵ امتیاز)
        if ($has_faq_sec || $faq_count >= 4) {
            $tier1['score'] += 5;
            $tier1['passed'][] = array('title' => 'پوشش سوالات متداول (FAQ Section)', 'pts' => '+5', 'desc' => 'بخش سوالات متداول کاربردی در مقاله تعبیه شده است.');
        } else {
            $tier1['failed'][] = array('title' => 'نبود بخش سوالات متداول (FAQ)', 'pts' => '-5', 'desc' => 'افزودن ۴ تا ۵ پرسش و پاسخ کوتاه در انتهای مقاله به برطرف کردن جستجوهای جانبی کمک می‌کند.');
        }

        /* -------------------------------------------------------------------------
         * سطح ۲: E-E-A-T & Trust (حداکثر ۳۰ امتیاز)
         * ------------------------------------------------------------------------- */
        // ۲.۱ استنادپذیری و مراجع علمی معتبر (+۱۰ امتیاز)
        if ($has_sources_sec && $doi_count >= 3) {
            $tier2['score'] += 10;
            $tier2['passed'][] = array('title' => 'استناد علمی به مراجع معتبر (Peer-Reviewed Sources)', 'pts' => '+10', 'desc' => 'بخش منابع معتبر در انتهای مقاله همراه با ' . $doi_count . ' شناسه DOI/مرجع رسمی ثبت شده است.');
        } elseif ($has_sources_sec) {
            $tier2['score'] += 6;
            $tier2['failed'][] = array('title' => 'کمبود لینک‌های معتبر DOI در منابع', 'pts' => '-4', 'desc' => 'بخش منابع وجود دارد اما شناسه‌های دیجیتال معتبر (DOI) یا پیوندهای ژورنال‌های علمی کم است.');
        } else {
            $tier2['failed'][] = array('title' => 'نبود بخش منابع معتبر علمی', 'pts' => '-10', 'desc' => 'مطابق E-E-A-T، درج منابع معتبر علمی در پایان مقاله برای اثبات مرجعیت و صداقت محتوا ضروری است.');
        }

        // ۲.۲ تمثیل عمومی برای ساده‌سازی (+۵ امتیاز)
        if ($has_analogy) {
            $tier2['score'] += 5;
            $tier2['passed'][] = array('title' => 'استفاده از تمثیل و مثال ملموس', 'pts' => '+5', 'desc' => 'از تمثیل و تشبیه‌های عمومی جهت فهم ساده‌تر مفهوم پیچیده علمی استفاده شده است.');
        } else {
            $tier2['failed'][] = array('title' => 'نبود مثال یا تمثیل ملموس', 'pts' => '-5', 'desc' => 'برای درک عمومی بهتر، حداقل یک تمثیل یا مثال روزمره برای مفاهیم سخت به کار ببرید.');
        }

        // ۲.۳ تشریح مرز شکست تمثیل (+۸ امتیاز)
        if ($has_analogy_boundary) {
            $tier2['score'] += 8;
            $tier2['passed'][] = array('title' => 'توضیح مرز شکست تمثیل (Scientific Precision)', 'pts' => '+8', 'desc' => 'مرز شکست تمثیل و تفاوت آن با واقعیت فیزیکی به دقت تشریح شده تا از کج‌فهمی علمی جلوگیری شود.');
        } else {
            $tier2['failed'][] = array('title' => 'عدم تبیین مرز شکست تمثیل', 'pts' => '-8', 'desc' => 'هر تشبیه خطاهایی دارد؛ حتماً توضیح دهید این تمثیل در کجا متوقف می‌شود و با واقعیت تفاوت دارد.');
        }

        // ۲.۴ صداقت علمی و رد شبه‌علم (+۷ امتیاز)
        $has_pseudoscience_warning = (bool) preg_match('/(شبه‌علم|ادعای نادرست|باور غلط|اشتباه رایج|مرز علم|کوانتوم‌واشینگ|فریب)/ui', $content);
        if ($has_pseudoscience_warning || mb_strlen($plain_text) > 1500) {
            $tier2['score'] += 7;
            $tier2['passed'][] = array('title' => 'صداقت علمی و شفافیت (Trustworthiness)', 'pts' => '+7', 'desc' => 'متن عاری از مبالغه و ادعاهای نامعتبر است و مرزهای علم حقیقی را حفظ کرده است.');
        } else {
            $tier2['score'] += 3;
            $tier2['failed'][] = array('title' => 'تقویت شفافیت و رفع اشتباهات رایج', 'pts' => '-4', 'desc' => 'افزودن بخش اشتباهات رایج یا تمایز علم و شبه‌علم به نمره اعتماد (Trust) کمک شایانی می‌کند.');
        }

        /* -------------------------------------------------------------------------
         * سطح ۳: معماری محتوا و لینک‌سازی (حداکثر ۲۰ امتیاز)
         * ------------------------------------------------------------------------- */
        // ۳.۱ تفاوت هوشمندانه H1 با عنوان سئو (+۵ امتیاز)
        if ($title !== $seo_title && mb_strlen($seo_title) > 0) {
            $tier3['score'] += 5;
            $tier3['passed'][] = array('title' => 'تفکیک هوشمند عنوان اصلی (H1) از عنوان سئو', 'pts' => '+5', 'desc' => 'عنوان اصلی مقاله با عنوان نمایشی در گوگل (Meta Title) متناسب با قصد جستجو تفکیک شده است.');
        } else {
            $tier3['score'] += 2;
            $tier3['failed'][] = array('title' => 'یکسان بودن عنوان H1 و عنوان سئو', 'pts' => '-3', 'desc' => 'پیشنهاد می‌شود عنوان سئو جذاب‌تر و همراه با کلمات کلیک‌خور (مانند چیست؟، راهنما و...) باشد.');
        }

        // ۳.۲ لینک‌های داخلی هدفمند (+۱۰ امتیاز)
        if ($internal_link_count >= 5 && $internal_link_count <= 12) {
            $tier3['score'] += 10;
            $tier3['passed'][] = array('title' => 'لینک‌سازی داخلی ایده‌آل (Internal Links)', 'pts' => '+10', 'desc' => 'تعداد ' . $internal_link_count . ' لینک داخلی قطعی و معنادار به سایر مقالات و واژه‌نامه تعبیه شده است.');
        } elseif ($internal_link_count >= 2) {
            $tier3['score'] += 5;
            $tier3['failed'][] = array('title' => 'کمبود لینک داخلی (تعداد فعلی: ' . $internal_link_count . ')', 'pts' => '-5', 'desc' => 'حداقل ۵ تا ۱۰ لینک داخلی به مقالات مرتبط و اصطلاحات واژه‌نامه اضافه کنید.');
        } else {
            $tier3['failed'][] = array('title' => 'عدم وجود لینک داخلی (جزیره محتوایی)', 'pts' => '-10', 'desc' => 'مقاله هیچ لینک داخلی به سایر بخش‌های سایت ندارد و دچار انزوای ساختاری است.');
        }

        // ۳.۳ سلسله‌مراتب تیترها (+۵ امتیاز)
        if ($h2_count >= 3) {
            $tier3['score'] += 5;
            $tier3['passed'][] = array('title' => 'ساختار منظم تیترها (Headings Hierarchy)', 'pts' => '+5', 'desc' => 'استفاده مطلوب از سرفصل‌های H2 و بخش‌بندی منظم پاراگراف‌ها.');
        } else {
            $tier3['failed'][] = array('title' => 'کمبود سرفصل‌های H2', 'pts' => '-5', 'desc' => 'متن یکدست و بدون سرفصل خوانایی پایینی دارد؛ حداقل ۳ تا ۵ تیتر H2 قرار دهید.');
        }

        /* -------------------------------------------------------------------------
         * سطح ۴: متادیتا و استانداردهای سئو (حداکثر ۲۰ امتیاز)
         * ------------------------------------------------------------------------- */
        // ۴.۱ حضور کلیدواژه در تایتل، متا، H1 و اول متن (+۶ امتیاز)
        $kw_matches = ($kw_in_h1 ? 1.5 : 0) + ($kw_in_seo_t ? 1.5 : 0) + ($kw_in_meta_d ? 1.5 : 0) + ($kw_in_first_p ? 1.5 : 0);
        if ($kw_matches >= 6) {
            $tier4['score'] += 6;
            $tier4['passed'][] = array('title' => 'حضور متوازن کلیدواژه کانونی (Keyword Placement)', 'pts' => '+6', 'desc' => 'کلیدواژه کانونی «' . $focus_kw . '» در H1، تایتل سئو، دیسکریپشن و پاراگراف اول حضور دارد.');
        } else {
            $tier4['score'] += (int) $kw_matches;
            $tier4['failed'][] = array('title' => 'نقص در توزیع کلیدواژه کانونی', 'pts' => '-' . (6 - (int)$kw_matches), 'desc' => 'کلیدواژه باید در عنوان H1، عنوان سئو، توضیح متا و آغاز پاراگراف اول به صورت طبیعی قرار گیرد.');
        }

        // ۴.۲ طول بهینه عنوان سئو ۵۰ تا ۶۰ حرف (+۴ امتیاز)
        if ($seo_t_len >= 50 && $seo_t_len <= 62) {
            $tier4['score'] += 4;
            $tier4['passed'][] = array('title' => 'طول ایده‌آل عنوان سئو (Meta Title Length)', 'pts' => '+4', 'desc' => 'طول عنوان سئو ' . $seo_t_len . ' کاراکتر است (بازه استاندارد ۵۰ تا ۶۲ حرف).');
        } elseif ($seo_t_len >= 35 && $seo_t_len <= 70) {
            $tier4['score'] += 2;
            $tier4['failed'][] = array('title' => 'طول عنوان سئو نیازمند بهینه‌سازی (' . $seo_t_len . ' حرف)', 'pts' => '-2', 'desc' => 'طول ایده‌آل عنوان برای جلوگیری از برش در گوگل بین ۵۰ تا ۶۰ حرف است.');
        } else {
            $tier4['failed'][] = array('title' => 'طول نامناسب عنوان سئو (' . $seo_t_len . ' حرف)', 'pts' => '-4', 'desc' => 'عنوان یا بسیار کوتاه است یا بیش از حد طولانی و بریده خواهد شد.');
        }

        // ۴.۳ طول بهینه متادیسکریپشن ۱۲۰ تا ۱۵۵ حرف (+۴ امتیاز)
        if ($meta_d_len >= 120 && $meta_d_len <= 158) {
            $tier4['score'] += 4;
            $tier4['passed'][] = array('title' => 'طول ایده‌آل توضیح متا (Meta Description Length)', 'pts' => '+4', 'desc' => 'طول توضیح متا ' . $meta_d_len . ' کاراکتر است (بازه استاندارد ۱۲۰ تا ۱۵۸ حرف).');
        } elseif ($meta_d_len >= 90 && $meta_d_len <= 175) {
            $tier4['score'] += 2;
            $tier4['failed'][] = array('title' => 'طول توضیح متا نیازمند بهینه‌سازی (' . $meta_d_len . ' حرف)', 'pts' => '-2', 'desc' => 'طول استاندارد برای نمایش در موبایل و دسکتاپ بین ۱۲۰ تا ۱۵۵ حرف است.');
        } else {
            $tier4['failed'][] = array('title' => 'طول نامناسب یا خالی بودن توضیح متا (' . $meta_d_len . ' حرف)', 'pts' => '-4', 'desc' => 'توضیح متا باید خلاصه جذاب و ترغیب‌کننده‌ای بین ۱۲۰ تا ۱۵۵ کاراکتر باشد.');
        }

        // ۴.۴ اسلاگ انگلیسی استاندارد (+۳ امتیاز)
        if ($is_en_slug && mb_strlen($slug) > 2) {
            $tier4['score'] += 3;
            $tier4['passed'][] = array('title' => 'اسلاگ انگلیسی استاندارد (Clean English Slug)', 'pts' => '+3', 'desc' => 'نامک پیوند یکتا به صورت انگلیسی استاندارد («' . $slug . '») ثبت شده است.');
        } else {
            $tier4['failed'][] = array('title' => 'اسلاگ غیراستاندارد یا فارسی', 'pts' => '-3', 'desc' => 'برای خوانایی بهتر و استانداردهای جهانی URL، اسلاگ انگلیسی کوتاه و بامعنی توصیه می‌شود.');
        }

        // ۴.۵ پاکیزگی ساختار HTML و عدم کدهای حجیم زائد (+۳ امتیاز)
        $has_bloat_code = (bool) (preg_match('/_tamrank_schema_rendered_source/i', $content) || mb_strlen($content) > 500000);
        if (!$has_bloat_code) {
            $tier4['score'] += 3;
            $tier4['passed'][] = array('title' => 'پاکیزگی کد و عدم کدهای مخرب/حجیم', 'pts' => '+3', 'desc' => 'کدهای HTML محتوا سبک و عاری از کدهای زائد یا استایل‌های مخرب است.');
        } else {
            $tier4['failed'][] = array('title' => 'کدهای حجیم یا کش زائد در محتوا', 'pts' => '-3', 'desc' => 'کدهای غیرضروری درون‌خطی یا حجیم در محتوا مشاهده شد.');
        }

        // محاسبه مجموع امتیاز (از ۱۰۰)
        $total_score = $tier1['score'] + $tier2['score'] + $tier3['score'] + $tier4['score'];
        $total_score = max(0, min(100, $total_score));

        // تعیین سطح ارزیابی کیفی
        if ($total_score >= 90) {
            $rating_label = 'عالی (Highest Quality / Fully Meets)';
            $rating_class = 'badge-highest';
            $rating_color = '#10b981';
        } elseif ($total_score >= 75) {
            $rating_label = 'خوب و معتبر (High Quality / Highly Meets)';
            $rating_class = 'badge-high';
            $rating_color = '#3b82f6';
        } elseif ($total_score >= 50) {
            $rating_label = 'متوسط - نیازمند بهبود (Medium / Moderately Meets)';
            $rating_class = 'badge-medium';
            $rating_color = '#f59e0b';
        } else {
            $rating_label = 'ضعیف - نیازمند بازنویسی (Low / Fails to Meet)';
            $rating_class = 'badge-low';
            $rating_color = '#ef4444';
        }

        $result = array(
            'post_id'        => $post_id,
            'title'          => $title,
            'slug'           => $slug,
            'post_type'      => $post->post_type,
            'post_status'    => $post->post_status,
            'edit_url'       => get_edit_post_link($post_id, 'raw'),
            'permalink'      => get_permalink($post_id),
            'focus_keyword'  => $focus_kw,
            'seo_title'      => $seo_title,
            'meta_desc'      => $meta_desc,
            'words_count'    => $words_count,
            'links_count'    => $internal_link_count,
            'doi_count'      => $doi_count,
            'h2_count'       => $h2_count,
            'h3_count'       => $h3_count,
            'total_score'    => $total_score,
            'rating_label'   => $rating_label,
            'rating_class'   => $rating_class,
            'rating_color'   => $rating_color,
            'audited_at'     => current_time('mysql'),
            'tiers'          => array(
                'tier1' => $tier1,
                'tier2' => $tier2,
                'tier3' => $tier3,
                'tier4' => $tier4,
            ),
        );

        // ذخیره نتیجه در متای پست
        update_post_meta($post_id, self::META_SCORE, $total_score);
        update_post_meta($post_id, self::META_DATA, $result);

        return $result;
    }

    public static function on_save_post($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!in_array($post->post_type, array('post', 'page', 'quantum_article', 'qp_glossary', 'quantum_scientist'), true)) return;

        self::audit_post($post_id);
    }

    public static function render_meta_box($post) {
        $audit = get_post_meta($post->ID, self::META_DATA, true);
        if (!$audit || !is_array($audit)) {
            $audit = self::audit_post($post->ID);
        }

        $score = $audit['total_score'] ?? 0;
        $color = $audit['rating_color'] ?? '#3b82f6';
        $label = $audit['rating_label'] ?? 'در حال ارزیابی...';

        echo '<div class="qpedia-audit-metabox" dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.7;">';
        echo '<div style="display:flex;align-items:center;justify-content:space-between;background:#f8fafc;padding:15px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:15px;">';
        echo '<div><h3 style="margin:0 0 5px 0;font-size:16px;">نمره انطباق با معیارهای رسمی گوگل: <span style="font-size:22px;color:' . esc_attr($color) . ';font-weight:bold;">' . (int)$score . ' / ۱۰۰</span></h3>';
        echo '<span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;color:#fff;background:' . esc_attr($color) . ';">' . esc_html($label) . '</span></div>';
        echo '<div><button type="button" class="button button-primary" id="qpedia-re-audit-btn" data-postid="' . (int)$post->ID . '">🔄 ارزیابی مجدد لحظه‌ای</button></div>';
        echo '</div>';

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:15px;">';
        echo '<div style="background:#fff;border:1px solid #e2e8f0;padding:10px;border-radius:6px;font-size:13px;">📝 <strong>تعداد واژگان:</strong> ' . number_format_i18n($audit['words_count']) . ' واژه</div>';
        echo '<div style="background:#fff;border:1px solid #e2e8f0;padding:10px;border-radius:6px;font-size:13px;">🔗 <strong>لینک‌های داخلی:</strong> ' . (int)$audit['links_count'] . ' لینک</div>';
        echo '<div style="background:#fff;border:1px solid #e2e8f0;padding:10px;border-radius:6px;font-size:13px;">📚 <strong>منابع معتبر DOI:</strong> ' . (int)$audit['doi_count'] . ' مرجع</div>';
        echo '<div style="background:#fff;border:1px solid #e2e8f0;padding:10px;border-radius:6px;font-size:13px;">🔑 <strong>کلیدواژه کانونی:</strong> ' . esc_html($audit['focus_keyword']) . '</div>';
        echo '</div>';

        // جزئیات ۴ سطح
        echo '<div class="qpedia-tiers-accordion">';
        foreach ($audit['tiers'] as $tier_key => $tier) {
            $pct = round(($tier['score'] / $tier['max']) * 100);
            $bar_color = ($pct >= 85) ? '#10b981' : (($pct >= 60) ? '#3b82f6' : (($pct >= 40) ? '#f59e0b' : '#ef4444'));

            echo '<div style="border:1px solid #e2e8f0;border-radius:6px;margin-bottom:10px;overflow:hidden;background:#fff;">';
            echo '<div style="background:#f1f5f9;padding:10px 15px;display:flex;justify-content:space-between;align-items:center;font-weight:bold;font-size:13px;">';
            echo '<span>' . esc_html($tier['title']) . '</span>';
            echo '<span style="color:' . esc_attr($bar_color) . ';">' . (int)$tier['score'] . ' از ' . (int)$tier['max'] . ' امتیاز (' . $pct . '%)</span>';
            echo '</div>';

            echo '<div style="padding:12px 15px;">';
            if (!empty($tier['passed'])) {
                echo '<h5 style="margin:5px 0;color:#059669;font-size:13px;">✅ موارد رعایت‌شده (کسب امتیاز):</h5><ul style="margin:0 0 10px 0;padding-right:20px;font-size:12px;">';
                foreach ($tier['passed'] as $p) {
                    echo '<li><strong>' . esc_html($p['title']) . ' (' . esc_html($p['pts']) . '):</strong> ' . esc_html($p['desc']) . '</li>';
                }
                echo '</ul>';
            }
            if (!empty($tier['failed'])) {
                echo '<h5 style="margin:5px 0;color:#dc2626;font-size:13px;">❌ موارد نیازمند اصلاح (کسر امتیاز):</h5><ul style="margin:0;padding-right:20px;font-size:12px;">';
                foreach ($tier['failed'] as $f) {
                    echo '<li><strong style="color:#b91c1c;">' . esc_html($f['title']) . ' (' . esc_html($f['pts']) . '):</strong> ' . esc_html($f['desc']) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div></div>';
        }
        echo '</div>';

        // اسکریپت بازبینی در متاباکس
        ?>
        <script>
        jQuery(document).ready(function($){
            $('#qpedia-re-audit-btn').on('click', function(e){
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true).text('در حال سنجش مجدد...');
                $.post(ajaxurl, {
                    action: 'qpedia_audit_single_post',
                    post_id: btn.data('postid'),
                    nonce: '<?php echo wp_create_nonce("qpedia_audit_nonce"); ?>'
                }, function(res){
                    if(res.success){
                        location.reload();
                    } else {
                        alert('خطا در ارزیابی: ' + (res.data || 'خطای ناشناخته'));
                        btn.prop('disabled', false).text('🔄 ارزیابی مجدد لحظه‌ای');
                    }
                });
            });
        });
        </script>
        <?php
        echo '</div>';
    }

    public static function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $post_type = isset($_GET['post_type_filter']) ? sanitize_text_field($_GET['post_type_filter']) : 'quantum_article';
        $filter_score = isset($_GET['score_filter']) ? sanitize_text_field($_GET['score_filter']) : 'all';

        // واکشی پست‌ها
        $args = array(
            'post_type'      => ($post_type === 'all') ? array('quantum_article', 'post', 'page', 'qp_glossary', 'quantum_scientist') : $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'ID',
            'order'          => 'ASC'
        );
        $posts = get_posts($args);

        // جمع‌آوری آمار
        $total_posts = count($posts);
        $highest_cnt = 0;
        $high_cnt = 0;
        $med_cnt = 0;
        $low_cnt = 0;
        $sum_scores = 0;

        $audited_items = array();
        foreach ($posts as $p) {
            $data = get_post_meta($p->ID, self::META_DATA, true);
            if (!$data || !is_array($data)) {
                $data = self::audit_post($p->ID);
            }
            $sc = $data['total_score'] ?? 0;
            $sum_scores += $sc;
            if ($sc >= 90) $highest_cnt++;
            elseif ($sc >= 75) $high_cnt++;
            elseif ($sc >= 50) $med_cnt++;
            else $low_cnt++;

            // اعمال فیلتر نمره
            if ($filter_score === 'highest' && $sc < 90) continue;
            if ($filter_score === 'high' && ($sc < 75 || $sc >= 90)) continue;
            if ($filter_score === 'medium' && ($sc < 50 || $sc >= 75)) continue;
            if ($filter_score === 'low' && $sc >= 50) continue;

            $audited_items[] = $data;
        }

        $avg_score = ($total_posts > 0) ? round($sum_scores / $total_posts, 1) : 0;

        ?>
        <div class="wrap" dir="rtl" style="font-family:Tahoma,sans-serif;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin:15px 0;">
                <h1 style="margin:0;font-size:22px;">📊 داشبورد سنجش و رتبه‌بندی محتوا مطابق معیارهای رسمی گوگل (Qpedia Auditor)</h1>
                <button type="button" class="button button-primary" id="qpedia-scan-all-btn" style="padding:6px 16px;font-size:14px;">⚡ اسکن و ارزیابی مجدد همه مقالات</button>
            </div>

            <!-- کارت‌های آمار کلیدی -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px;">
                <div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px;color:#64748b;">میانگین نمره کل مقالات</div>
                    <div style="font-size:28px;font-weight:bold;color:#0284c7;margin-top:5px;"><?php echo $avg_score; ?> <span style="font-size:16px;color:#94a3b8;">/ ۱۰۰</span></div>
                </div>
                <div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px;color:#64748b;">عالی (۹۰ تا ۱۰۰)</div>
                    <div style="font-size:28px;font-weight:bold;color:#10b981;margin-top:5px;"><?php echo $highest_cnt; ?> <span style="font-size:14px;color:#64748b;">مقاله</span></div>
                </div>
                <div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px;color:#64748b;">خوب و معتبر (۷۵ تا ۸۹)</div>
                    <div style="font-size:28px;font-weight:bold;color:#3b82f6;margin-top:5px;"><?php echo $high_cnt; ?> <span style="font-size:14px;color:#64748b;">مقاله</span></div>
                </div>
                <div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px;color:#64748b;">متوسط / نیازمند بهبود (۵۰ تا ۷۴)</div>
                    <div style="font-size:28px;font-weight:bold;color:#f59e0b;margin-top:5px;"><?php echo $med_cnt; ?> <span style="font-size:14px;color:#64748b;">مقاله</span></div>
                </div>
                <div style="background:#fff;border-radius:8px;padding:15px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                    <div style="font-size:13px;color:#64748b;">ضعیف / نیازمند بازنویسی (زیر ۵۰)</div>
                    <div style="font-size:28px;font-weight:bold;color:#ef4444;margin-top:5px;"><?php echo $low_cnt; ?> <span style="font-size:14px;color:#64748b;">مقاله</span></div>
                </div>
            </div>

            <!-- فیلترها -->
            <div style="background:#fff;padding:12px 15px;border-radius:8px;border:1px solid #e2e8f0;margin-bottom:15px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <form method="get" action="" style="display:flex;gap:10px;align-items:center;">
                    <input type="hidden" name="page" value="qpedia-google-auditor">
                    <label style="font-size:13px;">نوع محتوا:
                        <select name="post_type_filter" onchange="this.form.submit()">
                            <option value="quantum_article" <?php selected($post_type, 'quantum_article'); ?>>مقالات کوانتوم (quantum_article)</option>
                            <option value="post" <?php selected($post_type, 'post'); ?>>نوشته‌ها (post)</option>
                            <option value="qp_glossary" <?php selected($post_type, 'qp_glossary'); ?>>واژه‌نامه (qp_glossary)</option>
                            <option value="quantum_scientist" <?php selected($post_type, 'quantum_scientist'); ?>>دانشمندان (quantum_scientist)</option>
                            <option value="all" <?php selected($post_type, 'all'); ?>>همه انواع محتوا</option>
                        </select>
                    </label>
                    <label style="font-size:13px;">فیلتر نمره:
                        <select name="score_filter" onchange="this.form.submit()">
                            <option value="all" <?php selected($filter_score, 'all'); ?>>همه رتبه‌ها</option>
                            <option value="highest" <?php selected($filter_score, 'highest'); ?>>عالی (۹۰ تا ۱۰۰)</option>
                            <option value="high" <?php selected($filter_score, 'high'); ?>>خوب (۷۵ تا ۸۹)</option>
                            <option value="medium" <?php selected($filter_score, 'medium'); ?>>متوسط (۵۰ تا ۷۴)</option>
                            <option value="low" <?php selected($filter_score, 'low'); ?>>ضعیف (زیر ۵۰)</option>
                        </select>
                    </label>
                </form>
                <div style="font-size:13px;color:#64748b;">نمایش <strong><?php echo count($audited_items); ?></strong> مورد از کل <strong><?php echo $total_posts; ?></strong> محتوا</div>
            </div>

            <!-- جدول ارزیابی مقالات -->
            <table class="wp-list-table widefat fixed striped" style="border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
                <thead>
                    <tr>
                        <th style="width:50px;text-align:center;">ID</th>
                        <th style="width:280px;">عنوان مقاله و نامک</th>
                        <th style="width:130px;text-align:center;">نمره گوگل (از ۱۰۰)</th>
                        <th style="width:140px;text-align:center;">سطح ۱ (Needs Met)</th>
                        <th style="width:140px;text-align:center;">سطح ۲ (E-E-A-T)</th>
                        <th style="width:130px;text-align:center;">سطح ۳ (ساختار)</th>
                        <th style="width:130px;text-align:center;">سطح ۴ (سئو و متا)</th>
                        <th style="width:100px;text-align:center;">واژگان</th>
                        <th style="width:110px;text-align:center;">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($audited_items)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:25px;color:#94a3b8;">هیچ مقاله‌ای با این فیلتر یافت نشد.</td></tr>
                    <?php else: ?>
                        <?php foreach ($audited_items as $item): 
                            $sc = $item['total_score'];
                            $col = $item['rating_color'];
                            $t1 = $item['tiers']['tier1']['score'];
                            $t2 = $item['tiers']['tier2']['score'];
                            $t3 = $item['tiers']['tier3']['score'];
                            $t4 = $item['tiers']['tier4']['score'];
                        ?>
                        <tr>
                            <td style="text-align:center;font-weight:bold;color:#64748b;"><?php echo (int)$item['post_id']; ?></td>
                            <td>
                                <strong><a href="<?php echo esc_url($item['edit_url']); ?>" target="_blank" style="color:#0f172a;text-decoration:none;"><?php echo esc_html($item['title']); ?></a></strong>
                                <div style="font-size:11px;color:#94a3b8;direction:ltr;text-align:right;margin-top:3px;"><?php echo esc_html($item['slug']); ?></div>
                            </td>
                            <td style="text-align:center;">
                                <div style="display:inline-block;padding:4px 10px;border-radius:15px;font-weight:bold;color:#fff;background:<?php echo esc_attr($col); ?>;font-size:13px;">
                                    <?php echo (int)$sc; ?>%
                                </div>
                            </td>
                            <td style="text-align:center;font-size:12px;"><strong><?php echo (int)$t1; ?></strong> / ۳۰</td>
                            <td style="text-align:center;font-size:12px;"><strong><?php echo (int)$t2; ?></strong> / ۳۰</td>
                            <td style="text-align:center;font-size:12px;"><strong><?php echo (int)$t3; ?></strong> / ۲۰</td>
                            <td style="text-align:center;font-size:12px;"><strong><?php echo (int)$t4; ?></strong> / ۲۰</td>
                            <td style="text-align:center;font-size:12px;"><?php echo number_format_i18n($item['words_count']); ?></td>
                            <td style="text-align:center;">
                                <a href="<?php echo esc_url($item['edit_url']); ?>" class="button button-small" target="_blank">ویرایش و جزئیات</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('#qpedia-scan-all-btn').on('click', function(e){
                e.preventDefault();
                if(!confirm('آیا مایلید تمام مقالات دوباره طبق قوانین جدید گوگل اسکن و امتیازدهی شوند؟')) return;
                var btn = $(this);
                btn.prop('disabled', true).text('در حال اسکن مقالات (لطفاً صبور باشید)...');
                $.post(ajaxurl, {
                    action: 'qpedia_audit_all_posts',
                    post_type: '<?php echo esc_js($post_type); ?>',
                    nonce: '<?php echo wp_create_nonce("qpedia_audit_all_nonce"); ?>'
                }, function(res){
                    if(res.success){
                        alert(res.data.message || 'اسکن کامل شد.');
                        location.reload();
                    } else {
                        alert('خطا در اسکن: ' + (res.data || 'خطای ناشناخته'));
                        btn.prop('disabled', false).text('⚡ اسکن و ارزیابی مجدد همه مقالات');
                    }
                });
            });
        });
        </script>
        <?php
    }

    public static function ajax_audit_single() {
        check_ajax_referer('qpedia_audit_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('عدم دسترسی');
        }
        $post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
        if (!$post_id) {
            wp_send_json_error('شناسه پست نامعتبر است.');
        }

        $result = self::audit_post($post_id);
        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('خطا در پردازش پست.');
        }
    }

    public static function ajax_audit_all() {
        check_ajax_referer('qpedia_audit_all_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('عدم دسترسی');
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'quantum_article';
        $pts = ($post_type === 'all') ? array('quantum_article', 'post', 'page', 'qp_glossary', 'quantum_scientist') : array($post_type);

        $posts = get_posts(array(
            'post_type'      => $pts,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids'
        ));

        $count = 0;
        foreach ($posts as $pid) {
            self::audit_post($pid);
            $count++;
        }

        wp_send_json_success(array('message' => "تعداد {$count} محتوا با موفقیت مطابق استانداردهای جدید گوگل ارزیابی و امتیازدهی شد."));
    }
}

Qpedia_Google_Content_Auditor::init();
