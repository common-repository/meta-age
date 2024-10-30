<?php

/**
 * Common hooks
 */

/**
 * Sync data with server
 */
function meta_age_sync_data_with_server()
{
    global $wpdb;

    $sync_status = 0;

    $sessions = $wpdb->get_results(sprintf("SELECT * FROM meta_age_sessions WHERE synced='%s' ORDER BY id DESC;", $sync_status));

    if ($sessions) {
        $auth_token = MetaAgeApi::getAuthToken(AGE_PLUGIN);
        set_time_limit(120);
        foreach ($sessions as $session) {
            $resp = MetaAgeApi::request('/v1/data', 'PUT', [
                'ip' => $session->ip,
                'email' => $session->email,
                'wallet' => $session->wallet_address,
                'balance' => floatval($session->balance),
                'userAgent' => $session->agent,
                'walletType' => $session->wallet_type,
                'articleUrl' => $session->link,
            ], $auth_token);

            if ($resp['status'] == 200) {
                $id = $session->id;
                $value = 1;

                $wpdb->query($wpdb->prepare("UPDATE meta_age_sessions set synced ='%d' where id='%s'", $value, $id));
            }

        }
    }
}
add_action('meta_age_sync_data', 'meta_age_sync_data_with_server');

/**
 * Handle login AJAX request
 */
function meta_age_on_verify()
{
    setcookie(
        'metaAgeRedirectedPayload',
        '',
        array(
            'path' => '/',
            'secure' => is_ssl(),
            'expires' => time() - 3600,
        )
    );

    if (!empty($_COOKIE['isValidMetaAge'])) {
        exit(json_encode([
            'success' => true,
            'message' => __('Successfully verified your age!', 'mega-age-verification')
        ]));
    }

    if (!isset($_POST['account']) || !isset($_POST['balance'])) {
        exit(json_encode([
            'success' => false,
            'message' => __('Bad request!', AGE_PLUGIN)
        ]));
    }

    global $wpdb;
    $account = sanitize_text_field($_POST['account']);
    $auth_token = MetaAgeApi::getAuthToken(AGE_PLUGIN);
    $ticker = sanitize_text_field($_POST['ticker']);

    $resp = MetaAgeApi::request("/v2/wallet-auth/nonce?address=$account&ticker=$ticker", 'GET', [], $auth_token);
    if ($resp) {
        $response = [
            'success' => true,
            'nonce' => json_decode($resp['body'])->nonce
        ];

        exit(json_encode($response));
    }



}
add_action('wp_ajax_meta_age_verify_client', 'meta_age_on_verify');
add_action('wp_ajax_nopriv_meta_age_verify_client', 'meta_age_on_verify');



function meta_age_wallet_verify()
{
    $settings = array_merge([
        'min_age' => 18,
        'cookie_duration' => 4380,
        'deny_message' => __('Sorry, you are not allowed to access this site!', AGE_PLUGIN)
    ], (array) get_option('meta_age_verification_settings'));

    if (!empty($_COOKIE['isValidMetaAge'])) {
        exit(json_encode([
            'success' => true,
            'message' => __('Successfully verified your age!', 'meta-age-verification')
        ]));
    }

    global $wpdb;
    $agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
    $ip_addr = meta_age_get_client_ip();
    $balance = floatval($_POST['balance']);
    $wallet_type = ucfirst(sanitize_text_field($_POST['walletType']));
    $auth_token = MetaAgeApi::getAuthToken(AGE_PLUGIN);

    $clientUrl = esc_url($_POST['clientUrl']);
    $signature = sanitize_text_field($_POST['signature']);
    $wallet_type = ucfirst(sanitize_text_field($_POST['walletType']));
    $ticker = sanitize_text_field($_POST['ticker']);
    $address = sanitize_text_field($_POST['address']);

    $data = array(
        'wallet_type' => $wallet_type,
        'clientUrl' => $clientUrl,
        'address' => $address,
        'balance' => $balance,
        'plugin_name' => AGE_PLUGIN,
        'plugin_table' => AGE_TABLE,
    );
    $settings = array_merge($settings, $data);




    $auth_token = MetaAgeApi::getAuthToken(AGE_PLUGIN);

    $resp = MetaAgeApi::request('/v2/wallet-auth/verify', 'POST', [
        'address' => $address,
        'ticker' => $ticker,
        'signature' => $signature,
        'clientUrl' => $clientUrl,
    ], $auth_token);
    if (200 === $resp['status']) {

        $resp = MetaAgeApi::request('/v3/data', 'PUT', [
            'wallet' => $address,
            'ticker' => $ticker,
            'balance' => 0,
            'data' => [
                [
                    'key' => 'ip',
                    'value' => $ip_addr,
                ],
                [
                    'key' => 'userAgent',
                    'value' => $agent,
                ],
                [
                    'key' => 'walletType',
                    'value' => $wallet_type,
                ],
                [
                    'key' => 'articleUrl',
                    'value' => $clientUrl,
                ],
            ],
            'signature' => $signature,
        ], $auth_token);


        if (201 !== $resp['status']) {
            exit(json_encode([
                'success' => false,
                'message' => __('Failed to connect to age server. Please try again!', AGE_PLUGIN)
            ]));
        } else {
            if (
                !$wpdb->insert(
                    AGE_TABLE,
                    array(
                        'ip' => $ip_addr,
                        'agent' => truncate($agent, 500),
                        'link' => $clientUrl,
                        'email' => 'N/A',
                        'balance' => $balance,
                        'wallet_type' => $wallet_type,
                        'synced' => 1,
                        'wallet_address' => $address
                    )
                )
            ) {
                exit(
                    json_encode(
                        array(
                            'success' => false,
                            'message' => htmlspecialchars($wpdb->last_error),
                        )
                    )
                );
            }
            $session_id = $wpdb->insert_id;
            $inserted = $wpdb->insert(
                "meta_wallet_connections",
                array(
                    'plugin_name' => AGE_PLUGIN,
                    'session_table' => AGE_TABLE,
                    'session_id' => $session_id,
                    'wallet_type' => $wallet_type,
                    'ticker' => $ticker,
                    'wallet_address' => $address
                )
            );
            if ($inserted) {
                $inserted_id = $wpdb->insert_id;
                $settings['metaSessionId'] = $inserted_id;
                $settings['address'] = $address;
                $settings['clientUrl'] = $clientUrl;
                $settings['walletType'] = $wallet_type;

                $result = perform_age_verification($settings);
                exit($result);
            }
        }
    }
}

add_action('wp_ajax_meta_age_wallet_verify', 'meta_age_wallet_verify');
add_action('wp_ajax_nopriv_meta_age_wallet_verify', 'meta_age_wallet_verify');

function meta_age_skip_wallet()
{
    $settings = array_merge([
        'min_age' => 18,
        'cookie_duration' => 4380,
        'deny_message' => __('Sorry, you are not allowed to access this site!', AGE_PLUGIN)
    ], (array) get_option('meta_age_verification_settings'));

    if (!empty($_COOKIE['isValidMetaAge'])) {
        exit(json_encode([
            'success' => true,
            'message' => __('Successfully verified your age!', 'meta-age-verification')
        ]));
    }


    $metaSessionId = $_POST['metaSessionId'];
    global $wpdb;
    $wallet_data = $wpdb->get_results(sprintf("SELECT * FROM meta_wallet_connections WHERE id='%s';", $metaSessionId));
    if (!$wallet_data) {
        exit(json_encode([
            'success' => false,
            'message' => __('meta_wallet_connections not found!', AGE_PLUGIN)
        ]));
    }

    $session_table = $wallet_data[0]->session_table;
    $session_id = $wallet_data[0]->session_id;
    $wallet_type = $wallet_data[0]->wallet_type;
    $ticker = $wallet_data[0]->ticker;
    $wallet_address = $wallet_data[0]->wallet_address;

    $settings = array_merge(
        array('cookie_duration' => 48),
        (array) get_option('metaLockerSettings')
    );

    if (empty($settings['cookie_duration'])) {
        $settings['cookie_duration'] = 48;
    }



    $inserted = $wpdb->get_var(sprintf("SELECT ID FROM meta_wallet_connections WHERE wallet_address='%s' AND plugin_name='%s' LIMIT 1;", $wallet_address, AGE_PLUGIN));
    if ($inserted) {
        exit(json_encode([
            'success' => true,
            'message' => __('Plugin already connected!', AGE_PLUGIN)
        ]));


    }

    $session_data = $wpdb->get_results(sprintf("SELECT * FROM %s WHERE id='%s';", $session_table, $session_id));


    if (empty($session_data)) {
        exit(json_encode([
            'success' => false,
            'message' => __('Data not found in session table!', AGE_PLUGIN)
        ]));

    }

    $ip = $session_data[0]->ip;
    $agent = $session_data[0]->agent;
    $link = $session_data[0]->link;
    if (property_exists($session_data[0], 'email')) {
        $email = $session_data[0]->email;
    }
    $balance = $session_data[0]->balance;
    $wallet_type = $session_data[0]->wallet_type;
    $wallet_address = $session_data[0]->wallet_address;

    $auth_token = MetaAgeApi::getAuthToken(AGE_PLUGIN);

    $data = [
        [
            'key' => 'ip',
            'value' => $ip,
        ],
        [
            'key' => 'userAgent',
            'value' => $agent,
        ],
        [
            'key' => 'walletType',
            'value' => $wallet_type,
        ],
        [
            'key' => 'articleUrl',
            'value' => $link,
        ],
    ];

    if (!empty($email)) {
        $data[] = [
            'key' => 'email',
            'value' => $email,
        ];
    }

    $resp = MetaAgeApi::request('/v3/data/wallet-skip', 'PUT', [
        'wallet' => $wallet_address,
        'ticker' => $ticker,
        'balance' => $balance,
        'data' => $data,
    ], $auth_token);


    if (201 !== $resp['status']) {
        exit(json_encode([
            'success' => false,
            'message' => __('Failed to connect to age server. Please try again!', AGE_PLUGIN)
        ]));
    } else {
        $session_id = $wpdb->insert(
            AGE_TABLE,
            array(
                'ip' => $ip,
                'agent' => $agent,
                'link' => $link,
                'email' => $email,
                'balance' => $balance,
                'wallet_type' => $wallet_type,
                'synced' => 1,
                'wallet_address' => $wallet_address
            )
        );

        $inserted = $wpdb->insert(
            "meta_wallet_connections",
            array(
                'plugin_name' => AGE_PLUGIN,
                'session_table' => AGE_TABLE,
                'session_id' => $session_id,
                'wallet_type' => $wallet_type,
                'ticker' => $ticker,
                'wallet_address' => $wallet_address
            )
        );
        if ($inserted) {

            $inserted_id = $wpdb->insert_id;
            $settings['metaSessionId'] = $inserted_id;
            $settings['address'] = $wallet_address;
            $settings['clientUrl'] = $link;
            $settings['walletType'] = $wallet_type;
            $result = perform_age_verification($settings);
            exit($result);

        }
    }
}


add_action('wp_ajax_meta_age_skip_wallet', 'meta_age_skip_wallet');
add_action('wp_ajax_nopriv_meta_age_skip_wallet', 'meta_age_skip_wallet');


function perform_age_verification($settings)
{
    global $wpdb;

    $wallet_type = $settings['wallet_type'];
    $address = $settings['address'];
    $clientUrl = $settings['clientUrl'];
    $balance = $settings['balance'];
    $metaSessionId = $settings['metaSessionId'];
    $auth_token = MetaAgeApi::getAuthToken(AGE_PLUGIN);

    $resp = MetaAgeApi::request('/v1/age/verify', 'POST', [
        'strict' => false,
        'wallet' => strtolower($address),
        'minimumAge' => intval($settings['min_age']),
        'clientRedirectUrl' => $clientUrl,
    ], $auth_token);

    if (200 === $resp['status']) {
        $body = json_decode($resp['body']);
        switch ($body->action) {
            case 'allow':
                if (
                    setcookie(
                        'isValidMetaAge',
                        1,
                        array(
                            'path' => '/',
                            'secure' => is_ssl(),
                            'expires' => intval($settings['cookie_duration']) * HOUR_IN_SECONDS + strtotime('now')
                        )
                    )
                ) {

                    setcookie(
                        'metaSessionId',
                        $metaSessionId,
                        array(
                            'path' => '/',
                            'secure' => is_ssl(),
                            'expires' => intval($settings['cookie_duration']) * HOUR_IN_SECONDS + strtotime('now'),
                            'httponly' => false,
                            'samesite' => 'Strict'
                        )
                    );


                    return (json_encode([
                        'success' => true,
                        'message' => __('Successfully verified your age!', 'mega-age-verification')
                    ]));
                } else {
                    return (json_encode([
                        'success' => true,
                        'message' => __('Your browser seems to block cookies. Please allow cookies and try again!', AGE_PLUGIN)
                    ]));
                }
                break;

            case 'redirect':
                $redirectPayload = [
                    'balance' => $balance,
                    'walletType' => $wallet_type,
                    'clientUrl' => $clientUrl,
                    'metaSessionId' => $metaSessionId,
                    'address' => $address
                ];
                setcookie(
                    'metaAgeRedirectedPayload',
                    json_encode($redirectPayload),
                    array(
                        'path' => '/',
                        'secure' => is_ssl(),
                        'expires' => intval($settings['cookie_duration']) * HOUR_IN_SECONDS + strtotime('now')
                    )
                );
                return (json_encode([
                    'success' => true,
                    'message' => $body->redirectURL
                ]));
                break;

            case 'deny':
                return (json_encode([
                    'success' => false,
                    'message' => $settings['deny_message']
                ]));
                break;
        }
    } else {
        return (json_encode([
            'success' => false,
            'message' => __('Failed to connect to database server. Please try again!', AGE_PLUGIN)
        ]));
    }
}
function redirect_verification()
{
    global $wpdb;
    $settings = array_merge([
        'min_age' => 18,
        'cookie_duration' => 4380,
        'deny_message' => __('Sorry, you are not allowed to access this site!', AGE_PLUGIN)
    ], (array) get_option('meta_age_verification_settings'));

    if (!empty($_COOKIE['isValidMetaAge'])) {
        exit(json_encode([
            'success' => true,
            'message' => __('Successfully verified your age!', 'meta-age-verification')
        ]));
    }


    $balance = floatval($_POST['balance']);
    $wallet_type = ucfirst(sanitize_text_field($_POST['walletType']));
    $clientUrl = esc_url($_POST['clientUrl']);
    $address = sanitize_text_field($_POST['address']);
    $metaSessionId = sanitize_text_field($_POST['metaSessionId']);

    $data = array(
        'wallet_type' => $wallet_type,
        'clientUrl' => $clientUrl,
        'address' => $address,
        'metaSessionId' => $metaSessionId,
        'balance' => $balance,
        'plugin_name' => AGE_PLUGIN,
        'plugin_table' => AGE_TABLE,
    );
    $settings = array_merge($settings, $data);

    if (isset($_COOKIE['metaAgeRedirectedPayload'])) {
        $result = perform_age_verification($settings);
        exit($result);
    }
}
add_action('wp_ajax_redirect_verification', 'redirect_verification');
add_action('wp_ajax_nopriv_redirect_verification', 'redirect_verification');
/**
 * Bind the `rest_api_init` hook
 *
 * @see https://developer.wordpress.org/reference/hooks/rest_api_init/
 */
function meta_age_on_restapi_init($server)
{
    MetaAgeApi::registerRoutes();
}
add_action('rest_api_init', 'meta_age_on_restapi_init');