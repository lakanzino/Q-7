<?php
/**
 * Plugin Name: QP Scientist 301 Redirects — Fix Typos & Old Slugs v1.1
 * Description: 301 redirects for scientist typos: max-plank → max-planck (FA+EN), albert-einstein-2 → albert-einstein, schrodingerr → erwin-schrodinger, ervin-schrodinger → erwin-schrodinger and nested schrodingerr/* → /* . Fixes https://qpedia.ir/scientists/max-plank/ 404. Must stay active.
 * Version: 1.1.0
 * Author: Arena Agent for qpedia.ir
 */
if (!defined('ABSPATH')) exit;

function qp_301_redirects_list() {
    return [
        // max-planck typo - FA
        ['/scientists/max-plank/', '/scientists/max-planck/'],
        ['/scientists/max-plank', '/scientists/max-planck/'],
        // max-planck typo - EN (Polylang)
        ['/en/scientists/max-plank/', '/en/scientists/max-planck/'],
        ['/en/scientists/max-plank', '/en/scientists/max-planck/'],
        // albert-einstein -2 and nested
        ['/scientists/albert-einstein-2/', '/scientists/albert-einstein/'],
        ['/scientists/albert-einstein-2', '/scientists/albert-einstein/'],
        ['/scientists/albert-einstein-2/albert-einstein-3/', '/scientists/albert-einstein/'],
        ['/scientists/albert-einstein-2/albert-einstein-3', '/scientists/albert-einstein/'],
        ['/scientists/albert-einstein-3/', '/scientists/albert-einstein/'],
        ['/scientists/albert-einstein-3', '/scientists/albert-einstein/'],
        ['/en/scientists/albert-einstein-2/', '/en/scientists/albert-einstein/'],
        ['/en/scientists/albert-einstein-2', '/en/scientists/albert-einstein/'],
        // schrodinger typos
        ['/scientists/schrodingerr/', '/scientists/erwin-schrodinger/'],
        ['/scientists/schrodingerr', '/scientists/erwin-schrodinger/'],
        ['/scientists/ervin-schrodinger/', '/scientists/erwin-schrodinger/'],
        ['/scientists/ervin-schrodinger', '/scientists/erwin-schrodinger/'],
        ['/scientists/schrodingerr/ervin-schrodinger/', '/scientists/erwin-schrodinger/'],
        ['/scientists/schrodingerr/ervin-schrodinger', '/scientists/erwin-schrodinger/'],
        ['/en/scientists/schrodingerr/', '/en/scientists/erwin-schrodinger/'],
        ['/en/scientists/ervin-schrodinger/', '/en/scientists/erwin-schrodinger/'],
        // nested under schrodingerr
        ['/scientists/schrodingerr/arnold-sommer-feld/', '/scientists/arnold-sommerfeld/'],
        ['/scientists/schrodingerr/arnold-sommer-feld', '/scientists/arnold-sommerfeld/'],
        ['/scientists/schrodingerr/herman-weyl/', '/scientists/hermann-weyl/'],
        ['/scientists/schrodingerr/herman-weyl', '/scientists/hermann-weyl/'],
        ['/scientists/schrodingerr/john-bell-3/', '/scientists/john-bell/'],
        ['/scientists/schrodingerr/john-bell-3', '/scientists/john-bell/'],
    ];
}

function qp_301_do_redirect() {
    if (is_admin()) return;
    $request = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($request, PHP_URL_PATH);
    if (!$path) return;
    $path_lower = strtolower($path);

    // exact matches first
    foreach (qp_301_redirects_list() as $pair) {
        $old = strtolower($pair[0]);
        $new = $pair[1];
        if ($path_lower === $old || $path_lower === rtrim($old,'/')) {
            wp_redirect(home_url($new), 301);
            exit;
        }
    }

    // wildcard: /scientists/schrodingerr/xxx -> /scientists/xxx
    if (strpos($path_lower, '/scientists/schrodingerr/') === 0) {
        $rest = substr($path, strlen('/scientists/schrodingerr/'));
        $rest = ltrim($rest, '/');
        $rest = str_replace(['ervin-schrodinger','arnold-sommer-feld','herman-weyl','john-bell-3'], ['erwin-schrodinger','arnold-sommerfeld','hermann-weyl','john-bell'], strtolower($rest));
        if ($rest) {
            wp_redirect(home_url('/scientists/' . $rest), 301);
            exit;
        } else {
            wp_redirect(home_url('/scientists/erwin-schrodinger/'), 301);
            exit;
        }
    }
    if (strpos($path_lower, '/en/scientists/schrodingerr/') === 0) {
        $rest = substr($path, strlen('/en/scientists/schrodingerr/'));
        $rest = ltrim($rest, '/');
        $rest = str_replace(['ervin-schrodinger','arnold-sommer-feld','herman-weyl','john-bell-3'], ['erwin-schrodinger','arnold-sommerfeld','hermann-weyl','john-bell'], strtolower($rest));
        wp_redirect(home_url($rest ? '/en/scientists/' . $rest : '/en/scientists/erwin-schrodinger/'), 301);
        exit;
    }

    // wildcard: /scientists/albert-einstein-2/xxx -> /scientists/albert-einstein/
    if (strpos($path_lower, '/scientists/albert-einstein-2/') === 0) {
        wp_redirect(home_url('/scientists/albert-einstein/'), 301);
        exit;
    }
    if (strpos($path_lower, '/en/scientists/albert-einstein-2/') === 0) {
        wp_redirect(home_url('/en/scientists/albert-einstein/'), 301);
        exit;
    }
    // max-plank any sub
    if (strpos($path_lower, '/scientists/max-plank/') === 0) {
        wp_redirect(home_url('/scientists/max-planck/'), 301);
        exit;
    }
    if (strpos($path_lower, '/en/scientists/max-plank/') === 0) {
        wp_redirect(home_url('/en/scientists/max-planck/'), 301);
        exit;
    }
}
add_action('template_redirect', 'qp_301_do_redirect', 1);

// Also fix on save: if someone tries to create max-plank again, force to max-planck
function qp_301_force_correct_slug($data, $postarr) {
    if ($data['post_type'] === 'quantum_scientist') {
        if ($data['post_name'] === 'max-plank') $data['post_name'] = 'max-planck';
        if ($data['post_name'] === 'max-plank-en') $data['post_name'] = 'max-planck-en';
        if ($data['post_name'] === 'albert-einstein-2') $data['post_name'] = 'albert-einstein';
        if ($data['post_name'] === 'albert-einstein-3') $data['post_name'] = 'albert-einstein';
        if ($data['post_name'] === 'schrodingerr') $data['post_name'] = 'erwin-schrodinger';
        if ($data['post_name'] === 'ervin-schrodinger') $data['post_name'] = 'erwin-schrodinger';
    }
    return $data;
}
add_filter('wp_insert_post_data', 'qp_301_force_correct_slug', 10, 2);
