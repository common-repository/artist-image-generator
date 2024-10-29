<?php

use Artist_Image_Generator_Constant as Constants;
use Artist_Image_Generator_License as License;

class Artist_Image_Generator_Setter {
    /**
     * Return the options.
     *
     * @return array
     */
    public static function get_options(): array {
        return get_option(Constants::OPTION_NAME, []);
    }

    /**
     * Checks if the current page is the artist image generator page.
     *
     * @return bool Whether the current page is the artist image generator page.
     */
    public static function is_artist_image_generator_page(): bool
    {
        global $pagenow;

        $page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);

        return $pagenow === 'upload.php' && $page === Constants::PLUGIN_NAME_UNDERSCORES;
    }

    /**
     * Checks if the current page is the media editor page.
     *
     * @return bool Whether the current page is the media editor page.
     */
    public static function is_media_editor_page(): bool
    {
        global $pagenow;

        $allowedPages = ['post.php', 'post-new.php', 'customize.php', 'profile.php', 'term.php', 'widgets.php'];

        $fl_builder = filter_input(INPUT_GET, 'fl_builder', FILTER_SANITIZE_SPECIAL_CHARS);

        return in_array($pagenow, $allowedPages, true) || $fl_builder !== null;
    }

    /**
     * Creates a link.
     *
     * @param string $url The URL of the link.
     * @param string $title The title of the link.
     * @param string $icon The icon of the link.
     * @return string The created link.
     */
    private static function create_link($url, $title, $icon): string {
        if (empty($icon)) {
            return sprintf(
                '<a href="%s" target="_blank" title="%s">%s</a>',
                esc_url($url),
                esc_attr($title),
                esc_html($title)
            );
        } else {
            return sprintf(
                '<a href="%s" target="_blank" title="%s"><span class="dashicons dashicons-%s"></span> %s</a>',
                esc_url($url),
                esc_attr($title),
                $icon,
                esc_html($title)
            );
        }
    }

    /**
     * Adds meta to the links.
     *
     * @param array $links The links to add meta to.
     * @return array The links with the added meta.
     */
    public static function add_meta($links): array {
        $meta = array(
            'website' => self::create_link("https://artist-image-generator.com/", esc_html__('Website', 'artist-image-generator'), 'admin-links'),            
            'support' => self::create_link("https://wordpress.org/support/plugin/artist-image-generator", esc_html__('Support', 'artist-image-generator'), 'sos'),
            'review' => self::create_link("https://wordpress.org/support/plugin/artist_image_generator/reviews/#new-post", esc_html__('Review', 'artist-image-generator'), 'thumbs-up'),
            'github' => self::create_link("https://github.com/Immolare/artist_image_generator", esc_html__('GitHub', 'artist-image-generator'), 'randomize'),
        );

        return array_merge($links, $meta);
    }

    /**
     * Adds an action to the links.
     *
     * @param array $links The links to add an action to.
     * @param Artist_Image_Generator_Tab $tab The tab to add an action to.
     * @return array The links with the added action.
     */
    public static function add_action($links, Artist_Image_Generator_Tab $tab): array {
        $link = self::create_link($tab->get_admin_tab_url(Constants::ACTION_SETTINGS), esc_html__('Settings', 'artist-image-generator'), '');
        array_unshift($links, $link);

        return $links;
    }

    /**
     * Sets the menu.
     *
     * @param callable $callback The callback to set the menu with.
     */
    public static function set_menu($callback): void {
        add_media_page(
            Constants::PLUGIN_FULL_NAME,
            esc_html(Constants::PLUGIN_FULL_NAME, 'artist-image-generator'),
            'manage_options',
            Constants::PLUGIN_NAME_UNDERSCORES,
            $callback
        );
    }

    /**
     * Sets the settings.
     */
    public static function set_settings(): void {
        register_setting(
            Constants::PLUGIN_NAME_UNDERSCORES . '_option_group', // option_group
            Constants::OPTION_NAME, // option_name
            array(__CLASS__, 'sanitize') // sanitize_callback
        );

        self::add_settings_section();
        self::add_settings_field(Constants::OPENAI_API_KEY, 'OPENAI_KEY', 'openai_api_key_0_callback');
        self::add_settings_field(Constants::LICENCE_KEY, 'AIG_KEY', 'aig_licence_key_0_callback');
        self::add_settings_field(Constants::REFILL_PRODUCT_ID, 'WC_PROD_ID', 'aig_refill_product_id_0_callback');

        self::add_settings_field(Constants::ACCOUNT_JWT, 'Your Token', 'aig_account_jwt_0_callback');
    }

    /**
     * Adds a settings section.
     */
    private static function add_settings_section(): void {
        add_settings_section(
            Constants::PLUGIN_NAME_UNDERSCORES . '_setting_section', // id
            esc_html__('Settings', 'artist-image-generator'), // title
            array(__CLASS__, 'section_info'), // callback
            Constants::PLUGIN_NAME_UNDERSCORES . '-admin' // page
        );
    }

    /**
     * Adds a settings field.
     *
     * @param string $id The ID of the settings field.
     * @param string $title The title of the settings field.
     * @param string $callback The callback of the settings field.
     */
    private static function add_settings_field($id, $title, $callback): void {
        add_settings_field(
            $id, // id
            $title, // title
            array(__CLASS__, $callback), // callback
            Constants::PLUGIN_NAME_UNDERSCORES . '-admin', // page
            Constants::PLUGIN_NAME_UNDERSCORES . '_setting_section' // section
        );
    }
        
    /**
     * Sanitizes the input.
     *
     * @param array $input The input to sanitize.
     * @return array The sanitized input.
     */
    public static function sanitize(array $input): array {
        $sanitizedValues = [];

        if (isset($input[Constants::OPENAI_API_KEY])) {
            $sanitizedValues[Constants::OPENAI_API_KEY] = sanitize_text_field($input[Constants::OPENAI_API_KEY]);
        }

        if (isset($input[Constants::LICENCE_KEY])) {
            $sanitizedValues[Constants::LICENCE_KEY] = self::sanitize_licence_key($input[Constants::LICENCE_KEY]);
        }

        if (isset($input[Constants::ACCOUNT_JWT])) {
            $sanitizedValues[Constants::ACCOUNT_JWT] = sanitize_text_field($input[Constants::ACCOUNT_JWT]);
        }

        if (isset($input[Constants::REFILL_PRODUCT_ID])) {
            $sanitizedValues[Constants::REFILL_PRODUCT_ID] = sanitize_text_field($input[Constants::REFILL_PRODUCT_ID]);
        }

        return $sanitizedValues;
    }

    /**
     * Sanitizes the licence key.
     *
     * @param string $licence_key The licence key to sanitize.
     * @return string The sanitized licence key.
     */
    private static function sanitize_licence_key($licence_key): string {
        $licence_key = sanitize_text_field($licence_key);

        if (empty($licence_key)) {
            return '';
        }

        $is_valid_license = License::license_validate($licence_key);

        if (empty($is_valid_license) || !$is_valid_license['is_valid']) {
            add_settings_error(
                Constants::OPTION_NAME,
                'invalid_license',
                esc_html__("Invalid licence key", 'artist-image-generator'),
                'error'
            );
            return '';
        }

        $is_activated = License::license_activate($licence_key);
        if ($is_activated) {
            add_settings_error(
                Constants::OPTION_NAME,
                'valid_license',
                esc_html__('License key is valid and was activated', 'artist-image-generator'),
                'updated'
            );
            return $licence_key;
        }

        return '';
    }

    /**
     * Displays the section info.
     */
    public static function section_info(): void {
    }

    /**
     * Callback for the OpenAI API key.
     */
    public static function openai_api_key_0_callback(): void {
        self::render_input_field(Constants::OPENAI_API_KEY);
    }

    /**
     * Callback for the AIG licence key.
     */
    public static function aig_licence_key_0_callback(): void {
        self::render_input_field(Constants::LICENCE_KEY);
    }

    /**
     * Callback for the AIG Refil Product ID.
     */
    public static function aig_refill_product_id_0_callback(): void {
        self::render_input_field(Constants::REFILL_PRODUCT_ID);
    }

    /**
     * Callback for the AIG Account JWT.
     */
    public static function aig_account_jwt_0_callback(): void {
        self::render_input_field(Constants::ACCOUNT_JWT);
    }

    /**
     * Renders an input field.
     *
     * @param string $key The key of the input field.
     */
    private static function render_input_field($key): void {
        $options = self::get_options();
        $name = Constants::PLUGIN_NAME_UNDERSCORES . '_option_name[' . $key . ']';
        $id = $key;
        $value = isset($options[$key]) ? $options[$key] : '';
        $type = 'text';
    
        if (in_array($key, [Constants::OPENAI_API_KEY, Constants::LICENCE_KEY, Constants::ACCOUNT_JWT])) {
            $type = 'password';
        }
    
        echo '<div class="aig-field">';
        echo '<input class="regular-text" type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" value="' . esc_attr($value) . '">';
    
        if ($key === Constants::REFILL_PRODUCT_ID) {
            echo '<p class="description">' . sprintf(
                esc_html__('[OPTIONAL] The ID of the WooCommerce product that will be used to use Customer Credit Balance. Leave blank to disable. For more information, visit %s.', 'artist-image-generator'),
                '<a href="' . esc_url('https://artist-image-generator.com/how-to-configure-wc-customer-credit-balance/') . '" target="_blank">' . esc_html__('this page', 'artist-image-generator') . '</a>'
            ) . '</p>';
        } elseif ($key === Constants::ACCOUNT_JWT) {
            echo '<p class="description">' . sprintf(
                esc_html__('[OPTIONAL] Upscale images to stunning 4K resolution using AI. Paste your token here. For more information, visit %s.', 'artist-image-generator'),
                '<a href="' . esc_url(admin_url('admin.php?page=artist_image_generator&action=services')) . '">' . esc_html__('the "services" section', 'artist-image-generator') . '</a>'
            ) . '</p>';
        }
    
        echo '</div>';
    }

    /**
     * Checks if the plugin is setting up.
     *
     * @return bool Whether the plugin is setting up.
     */
    public static function is_setting_up(): bool {
        $options = self::get_options();
        
        return is_array($options) && array_key_exists(Constants::OPENAI_API_KEY, $options);
    }
}