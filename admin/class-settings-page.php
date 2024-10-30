<?php

/**
 * Settings Page
 */

/**
 * Meta_Age_Settings_Page
 */
final class Meta_Age_Settings_Page
{
    /**
     * @var string
     */
    const SLUG = 'meta-age-settings';

    /**
     * @var string
     */
    const SETTING_KEY = 'meta_age_verification_settings';

    /**
     * @var string
     */
    const SETTING_GROUP = 'meta_age_verification_settings_group';

    /**
     * @var array
     */
    private $settings;

    /**
     * Singleton
     */
    public static function init()
    {
        static $self = null;

        if (null === $self) {
            $self = new self;
            add_action('admin_menu', array($self, 'add_menu_page'));
            add_action('admin_init', array($self, 'register_setting_group'), 10, 0);
        }
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->settings = array_merge(
            array(
                'delay' => 0,
                'cookie_duration' => 4380,
                'min_age' => 18,
                'min_balance' => 0,
                'deny_message' => __('Sorry, you are not allowed to access this site!', AGE_PLUGIN),
            ),
            (array) get_option(self::SETTING_KEY)
        );
    }

    /**
     * Add page
     *
     * @return void
     */
    public function add_menu_page()
    {
        $this->hook_name = add_submenu_page('meta-age-tos', __('Settings', AGE_PLUGIN), __('Settings', AGE_PLUGIN), 'manage_options', self::SLUG, array($this, 'render'));
    }

    /**
     * Register setting group
     *
     * @internal Used as a callback
     */
    public function register_setting_group()
    {
        register_setting(self::SETTING_GROUP, self::SETTING_KEY, array($this, 'sanitize'));
    }

    /**
     * Sanitize form data
     *
     * @internal Used as a callback
     * @var array $data Submiting data
     */
    public function sanitize(array $data)
    {
        if (!empty($data['delay'])) {
            $data['delay'] = intval($data['delay']);
        }

        if (!empty($data['min_age'])) {
            $data['min_age'] = intval($data['min_age']);
        }

        if (!empty($data['cookie_duration'])) {
            $data['cookie_duration'] = intval($data['cookie_duration']);
        }

        if (!empty($data['min_balance'])) {
            $data['min_balance'] = floatval($data['min_balance']);
        }

        if (!empty($data['deny_message'])) {
            $data['deny_message'] = sanitize_text_field($data['deny_message']);
        }

        return $data;
    }

    /**
     * Render
     *
     * @internal  Callback.
     */
    public function render($page_data)
    {
        ?>
        <div class="wrap">
            <h1>
                <?= __('Meta Age Settings', AGE_PLUGIN); ?>
            </h1>
            <form method="post" action="options.php" novalidate="novalidate">
                <?php settings_fields(self::SETTING_GROUP); ?>
                <div class="settings-tab">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?= __('Infura Project API-Key', AGE_PLUGIN); ?>
                            </th>
                            <td>
                                <input style="width:300px" type="text" name="<?= $this->get_name('infura_project_id') ?>"
                                    value="<?= $this->get_value('infura_project_id') ?>">
                                <p class="description">
                                    <?= __('Get your infura project API-KEY by signing up  <a href="https://infura.io/register" target="_blank"> here</a>. Choose <b>Web3 API</b> as <b>network</b> and give a nice <b>name</b> of your choice. Copy the API-KEY from the next window.', AGE_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?= __('Popup Delay', AGE_PLUGIN); ?>
                            </th>
                            <td>
                                <input type="number" name="<?= $this->get_name('delay'); ?>"
                                    value="<?= $this->get_value('delay'); ?>">
                                <p class="description">
                                    <?= __('Delay time before showing the verification popup, in seconds.', AGE_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?= __('Minimum Age', AGE_PLUGIN); ?>
                            </th>
                            <td>
                                <input type="number" name="<?= $this->get_name('min_age'); ?>"
                                    value="<?= $this->get_value('min_age'); ?>">
                                <p class="description">
                                    <?= __('Minimum required age.', AGE_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?= __('Cookie Duration', AGE_PLUGIN); ?>
                            </th>
                            <td>
                                <input type="number" name="<?= $this->get_name('cookie_duration'); ?>"
                                    value="<?= $this->get_value('cookie_duration'); ?>">
                                <p class="description">
                                    <?= __('How long before revalidating a verified user again, in hours. Default is 6 months (~4380 hours).', AGE_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?= __('Minimum Balance', AGE_PLUGIN); ?>
                            </th>
                            <td>
                                <input type="number" name="<?= $this->get_name('min_balance'); ?>"
                                    value="<?= $this->get_value('min_balance'); ?>">
                                <p class="description">
                                    <?= __('Minimum required balance.', AGE_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?= __('Deny Message', AGE_PLUGIN); ?>
                            </th>
                            <td>
                                <textarea name="<?= $this->get_name('deny_message'); ?>" cols="60"
                                    rows="5"><?= $this->get_value('deny_message'); ?></textarea>
                                <p class="description">
                                    <?= __('The message showing when verification failed.', AGE_PLUGIN); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(); ?>
            </form>
            <?php
    }

    /**
     * Get name
     *
     * @param  string $field  Key name.
     *
     * @return  string
     */
    private function get_name($key)
    {
        return self::SETTING_KEY . '[' . $key . ']';
    }

    /**
     * Get value
     *
     * @param  string $key  Key name.
     *
     * @return  mixed
     */
    private function get_value($key)
    {
        return isset($this->settings[$key]) ? sanitize_text_field($this->settings[$key]) : '';
    }
}

// Singleton.
Meta_Age_Settings_Page::init();