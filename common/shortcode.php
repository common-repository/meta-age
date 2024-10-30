<?php

/**
 * Shortcode
 */

/**
 * Main shortcode
 */
function meta_age_verification_render_shortcode($atts)
{
    wp_enqueue_script('meta-age-verification-login', META_AGE_URI . 'assets/js/login.min.js', [], META_AGE_VER, true);

    wp_localize_script('meta-age-verification-login', 'metaAgeVerification', [
        'settings' => array_merge([
            'ajaxURL' => admin_url('admin-ajax.php'),
            'pluginURI' => META_AGE_URI,
            'signMessage' => 'Please confirm that you own the wallet by signing this message!'
        ], (array) get_option('meta_age_verification_settings')),
    ]);

    ob_end_clean();

    ob_start();

    require META_AGE_DIR . 'common/templates/login-modal.php';

    return ob_get_clean();
}
add_shortcode(AGE_PLUGIN, 'meta_age_verification_render_shortcode');