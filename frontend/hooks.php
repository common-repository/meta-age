<?php

/**
 * Frontend hooks
 */

/**
 * Enqueue CSS for the login form shortcode
 */
function meta_age_verification_enqueue_scripts()
{
    $symbols = require META_AGE_DIR . 'assets/symbols.php';
    $testnets = require META_AGE_DIR . 'assets/testnets.php';
    wp_enqueue_style('meta-age-popup', META_AGE_URI . 'assets/css/frontend.min.css', [], META_AGE_VER);

    wp_enqueue_script('meta-age-popup', META_AGE_URI . 'assets/js/frontend.min.js', [], META_AGE_VER, true);
    wp_enqueue_script('meta-age-popup', META_AGE_URI . 'assets/js/components/VerificationPopup.js', [], META_AGE_VER, true);
    wp_enqueue_script('meta-age-popup', META_AGE_URI . 'assets/js/components/LazyScriptsLoader.js', [], META_AGE_VER, true);
    wp_enqueue_script('meta-age-popup', META_AGE_URI . 'assets/js/admin.min.js', [], META_AGE_VER, true);
    wp_localize_script('meta-age-popup', 'networkInfo', array('symbols' => $symbols, 'testnets' => $testnets)); // Localize the first script

    wp_localize_script('meta-age-popup', 'metaAge', [
        'ajaxURL' => admin_url('admin-ajax.php'),
        'pluginURI' => META_AGE_URI,
        'isValidMetaAge' => !empty($_COOKIE['isValidMetaAge']),
        'metaAgeRedirectedPayload' => $_COOKIE['metaAgeRedirectedPayload'],
        'settings' => array_merge([
            'delay' => 0,
            'cookie_duration' => 72,
            'min_age' => 18,
            'min_balance' => 0,
            'deny_message' => __('Sorry, you are not allowed to access this site!', AGE_PLUGIN),
        ], (array) get_option('meta_age_verification_settings')),
        'i18n' => [
            'verifyingMessage' => __('Verifying your age... Please wait!', AGE_PLUGIN)
        ]
    ]);
}
add_action('wp_enqueue_scripts', 'meta_age_verification_enqueue_scripts');

/**
 * Include popup
 */
add_action('wp_footer', function () {
    require META_AGE_DIR . 'frontend/templates/popup.php';
});