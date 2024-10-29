<?php

class Artist_Image_Generator_Constant {
    public const PLUGIN_FULL_NAME = "Artist Image Generator";
    public const PLUGIN_NAME_UNDERSCORES = "artist_image_generator";
    public const ADMIN_PARTIALS_PATH = "admin/partials/";
    public const ADMIN_DISPLAY_TEMPLATES = [
        'generate' => 'generate',
        'variate' => 'variate',
        'edit' => 'edit',
        'public' => 'public',
        'settings' => 'settings',
        'about' => 'about',
        'main' => 'main',
        'services' => 'services',
    ];
    public const ADMIN_ACTIONS = [
        'generate' => 'generate',
        'variate' => 'variate',
        'edit' => 'edit',
        'public' => 'public',
        'settings' => 'settings',
        'about' => 'about',
        'services' => 'services',
    ];
    public const ADMIN_ACTIONS_LABELS = [
        'generate' => 'Generate',
        'variate' => 'Variate',
        'edit' => 'Edit (Pro)',
        'public' => 'Shortcodes',
        'settings' => 'Settings',
        'about' => 'About',
        'services' => 'Services',
    ];
    public const QUERY_SETUP = 'setup';
    public const QUERY_FIELD_ACTION = 'action';
    public const ACTION_GENERATE = 'generate';
    public const ACTION_VARIATE = 'variate';
    public const ACTION_EDIT = 'edit';
    public const ACTION_PUBLIC = 'public';
    public const ACTION_SETTINGS = 'settings';
    public const ACTION_ABOUT = 'about';
    public const ACTION_SERVICES = 'services';
    public const LAYOUT_MAIN = 'main';
    public const DALL_E_MODEL_3 = "dall-e-3";
    public const DALL_E_MODEL_2 = "dall-e-2";
    public const AIG_MODEL = "aig-model";
    public const DEFAULT_SIZE = '1024x1024';
    public const PLUGIN_NAME = "artist-image-generator";
    public const LICENSE_SERVER = 'https://artist-image-generator.com';
    public const CUSTOMER_KEY = 'ck_204741c9c2c41edb13767f951284d6c57360e0d7';
    public const CUSTOMER_SECRET = 'cs_3f2d6cf0fb6e046e69ef629923a3866716cbad17';
    public const PRODUCT_IDS = [21, 1282, 27158, 27224];
    public const DAYS = 5;

    const OPTION_NAME = self::PLUGIN_NAME_UNDERSCORES . '_option_name';
    const LICENCE_KEY = self::PLUGIN_NAME_UNDERSCORES . '_aig_licence_key_0';
    const LICENCE_OBJECT = self::PLUGIN_NAME_UNDERSCORES . '_aig_licence_object_0';
    const LICENCE_EXPIRING_SOON = self::PLUGIN_NAME_UNDERSCORES . '_aig_license_expiring_soon';
    const LICENCE_INVALID_OR_EXPIRED = self::PLUGIN_NAME_UNDERSCORES . '_aig_license_invalid_or_expired';
    const HIDE_NOTICE = self::PLUGIN_NAME_UNDERSCORES . '_hide_notice';
    const OPENAI_API_KEY = self::PLUGIN_NAME_UNDERSCORES . '_openai_api_key_0';
    const REFILL_PRODUCT_ID = self::PLUGIN_NAME_UNDERSCORES . '_aig_refill_product_id_0';
    const ACCOUNT_JWT = self::PLUGIN_NAME_UNDERSCORES . '_aig_account_jwt_0';
    const LICENCE_KEY_ACTIVATED = self::PLUGIN_NAME_UNDERSCORES . '_aig_licence_key_activated_0';

    const REFILL_WC_PRODUCT_META_KEY = 'credits';
    const REFILL_USER_META_KEY = '_aig_balance';
    const REFILL_USER_META_KEY_VERSION = '_aig_balance_version';
    const REFILL_WC_ORDER_RECHARGED = '_aig_credits_recharged';
}