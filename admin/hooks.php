<?php

/**
 * Handle AJAX activation request
 */
function meta_age_activate_site()
{
    if (empty($_POST['email']) || empty($_POST['plugin'])) {
        exit(json_encode([
            'success' => false,
            'message' => __('Please enter your email address!', AGE_PLUGIN)
        ]));
    }
    MetaAgeApi::setupKeypair();
    $email = sanitize_email($_POST['email']);
    $plugin = sanitize_title($_POST['plugin']);
    $address = sanitize_text_field($_POST['address']);
    $ticker = sanitize_text_field($_POST['ticker']);
    $status = MetaAgeApi::getActivationStatus($plugin);

    if (!$status) {
        $status = MetaAgeApi::registerSite($address, $plugin, $email, $ticker);
        sleep(1);
        if ($status) {
            if ($status === 'registered') {
                update_option('meta_age_mail_sent', 'yes');
                exit(json_encode([
                    'success' => true,
                    'message' => __('The plugin has been activated successfully!', AGE_PLUGIN)
                ]));
            }

        } else {
            exit(json_encode([
                'success' => false,
                'message' => __('Failed to activate the plugin. Please try again!', AGE_PLUGIN)
            ]));
        }
    }
}
add_action('wp_ajax_meta_age_activate_site', 'meta_age_activate_site');