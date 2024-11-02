<?php

use Artist_Image_Generator_License as License;
use Artist_Image_Generator_Constant as Constants;
use Artist_Image_Generator_Setter as Setter;
use Artist_Image_Generator_Dalle as Dalle;


/**
 * The public-facing functionality of the plugin.
 * 
 * @link       https://pierrevieville.fr
 * @since      1.0.0
 * 
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Artist_Image_Generator
 * @subpackage Artist_Image_Generator/public
 * @author     Pierre Viéville <contact@pierrevieville.fr>
 */
class Artist_Image_Generator_Public
{
    private $plugin_name;
    private $version;
    private $avatar_manager;
    private $data_validator;
    private $credits_balance_manager;
    private array $options;

    const DEFAULT_ACTION = 'generate_image';
    const DEFAULT_PROMPT = '';
    const DEFAULT_TOPICS = '';
    const DEFAULT_N = '3';
    const DEFAULT_SIZE = '1024x1024';
    const DEFAULT_MODEL = 'dall-e-2';
    const DEFAULT_DOWNLOAD = 'manual';
    const DEFAULT_QUALITY = 'standard';
    const DEFAULT_STYLE = 'vivid';
    const DEFAULT_LIMIT_PER_USER = 0; // no limit
    const DEFAULT_LIMIT_PER_USER_REFRESH_DURATION = 0; // no refresh; in seconds

    const POSSIBLE_SIZES_DALLE_2 = ['256x256', '512x512', '1024x1024'];
    const POSSIBLE_SIZES_DALLE_3 = ['1024x1024', '1024x1792', '1792x1024'];
    const POSSIBLE_QUALITIES_DALLE_3 = ['standard', 'high'];
    const POSSIBLE_STYLES_DALLE_3 = ['vivid', 'natural'];
    const POSSIBLE_MODELS = ['dall-e-2', 'dall-e-3', 'aig-model'];
    const POSSIBLE_ACTIONS = ['generate_image', 'variate_image'];

    private const ERROR_TYPE_LIMIT_EXCEEDED = 'limit_exceeded_error';
    private const ERROR_TYPE_INVALID_FORM = 'invalid_form_error';

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_shortcode('aig', array($this, 'aig_shortcode'));

        $this->include_required_files();

        $this->options = Setter::get_options();
        $this->avatar_manager = new Artist_Image_Generator_Shortcode_Avatar_Manager();
        $this->data_validator = new Artist_Image_Generator_Shortcode_Data_Validator();
        $this->credits_balance_manager = new Artist_Image_Generator_Credits_Balance_Manager();
    }

    private function include_required_files()
    {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-artist-image-generator-shortcode-avatar-manager.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-artist-image-generator-shortcode-data-validator.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-artist-image-generator-credits-balance-manager.php';
    }

    private function get_request_number($post_data)
    {
        // DALLE 3 model has a fixed number of requests of 1 (parrallel requests)
        if ($post_data['model'] === Constants::DALL_E_MODEL_3 || $post_data['model'] === Constants::AIG_MODEL) {
            return 1;
        }

        // DALLE 2 model needs to get the number of requests from the post data
        return (int)$post_data['n'];
    }

    private function check_and_update_user_limit(&$post_data)
    {
        // If Constants::REFILL_PRODUCT_ID is set, handle the limit with the refill system
        if(License::license_check_validity() && !empty($this->options[Constants::REFILL_PRODUCT_ID]) && is_user_logged_in()) {
            $user_id = get_current_user_id();
            $current_balance = $this->credits_balance_manager::get_user_balance($user_id);

            $requests = $this->get_request_number($post_data);
            $balance = $current_balance - $requests;

            if ($balance < 0) {
                $requests = $current_balance;
                $balance = $current_balance - $requests;
                $post_data['n'] = $requests;
            }

            if ($balance < 0 || $requests <= 0) {
                $refill_product_id = $this->options[Constants::REFILL_PRODUCT_ID];
                $product_url = get_permalink($refill_product_id);

                $error_message = esc_html__('Not Enough credits. [Link](Click here to buy!)', 'artist-image-generator');
                wp_send_json(array(
                    'error' => array(
                        'type' => self::ERROR_TYPE_LIMIT_EXCEEDED,
                        'message' => $error_message,
                        'product_url' => $product_url,
                        'user_balance' => $current_balance
                    )
                ));
                wp_die();
            }
        }
        else {
            // Check if user limit is reached and update the transient else
            if (isset($post_data['user_limit']) && (int)$post_data['user_limit'] > 0) {
                $form_id = $post_data['id'];
                $user_id = get_current_user_id();
                $user_ip = $_SERVER['REMOTE_ADDR'];
                $user_identifier = $user_id ? 'artist_image_generator_user_' . $form_id . '_' . $user_id : 'artist_image_generator_ip_' . $form_id . '_' . $user_ip;
                $current_balance = get_transient($user_identifier);
                $duration = isset($post_data['user_limit_duration']) && $post_data['user_limit_duration'] > 0 ? (int)$post_data['user_limit_duration'] : 0;
                $expiration = get_option('_transient_timeout_' . $user_identifier);

                if ($current_balance === false || ($duration > 0 && time() > $expiration)) {
                    $current_balance = 0;
                    set_transient($user_identifier, $current_balance, $duration);
                }

                $requests = $this->get_request_number($post_data);
                $balance = $current_balance + $requests;

                if ((int)$post_data['user_limit'] < $balance) {
                    $requests = (int)$post_data['user_limit'] - $current_balance;
                    $balance = $current_balance + $requests;
                    $post_data['n'] = $requests;
                }

                if ((int)$post_data['user_limit'] < $balance || $requests <= 0) {
                    $duration_msg = $duration > 0 ? sprintf(__(' Please try again in %d seconds.', 'artist-image-generator'), $expiration - time()) : '';
                    $error_message = esc_html__('You have reached the limit of requests.', 'artist-image-generator') . $duration_msg;
                    wp_send_json(array(
                        'error' => array(
                            'type' => self::ERROR_TYPE_LIMIT_EXCEEDED,
                            'message' => $error_message
                        )
                    ));
                    wp_die();
                }

                set_transient($user_identifier, $balance, $duration);
            }
        }

        // Ensure that $post_data['n'] is not less than 1
        if ($post_data['n'] < 1) {
            $post_data['n'] = 1;
        }
    }


    public function generate_image()
    {
        $post_data = [];
        $dalle = new Dalle();
        
        if(wp_doing_ajax() && Setter::is_setting_up()) {
            $post_data = $dalle->sanitize_post_data();

            if (!check_ajax_referer('generate_image', '_ajax_nonce', false)) {
                wp_send_json(array(
                    'error' => array(
                        'type' => self::ERROR_TYPE_INVALID_FORM, 
                        'message' => esc_html__('Invalid nonce.', 'artist-image-generator')
                    )
                ));
                wp_die();
            }

            $this->check_and_update_user_limit($post_data);

            if (isset($post_data['generate'])) {
                $response = $dalle->handle_generation($post_data);
            }

            if (isset($response)) {
                list($images, $error) = $dalle->handle_response($response);
            }

            $data = $dalle->prepare_data($images ?? [], $error ?? [], $post_data);

            $n_credits = $this->get_request_number($post_data);

            /*$dummyImages = [];
            
            for ($i = 0; $i < $n_credits; $i++) {
                $dummyImages[] = [
                    'url' => 'https://artist-image-generator.com/wp-content/uploads/img-rck1GT0eGIYLu4oAXFEMqsPT.png'
                ];
            }

            $data = [
                'error' => [],
                'images' => $dummyImages,
                'model_input' => $post_data['model'],
                'prompt_input' => 'Painting of a bird, including following criterias:',
                'size_input' => '1024x1024',
                'n_input' => $n_credits,
                'quality_input' => '',
                'style_input' => ''
            ];*/
            
            if (!empty($this->options[Constants::REFILL_PRODUCT_ID]) && is_user_logged_in()) {
                $user_id = get_current_user_id();

                if (empty($data['error'])) {
                    $newBalance = $this->credits_balance_manager::update_user_credits($user_id, -$n_credits);
                    $data['user_credits_used'] = $n_credits;
                    $data['user_balance'] = $newBalance;
                }
                else {
                    $newBalance = $this->credits_balance_manager::get_user_balance($user_id);
                    $data['user_credits_used'] = 0;
                    $data['user_balance'] = $newBalance;
                }   
            }

            //$data = '{"error":[],"images":[{"url":"https://artist-image-generator.com/wp-content/uploads/img-rck1GT0eGIYLu4oAXFEMqsPT.png"}],"model_input":"dall-e-2","prompt_input":"Painting of a bird, including following criterias:","size_input":"1024x1024","n_input":"1","quality_input":"","style_input":""}';

            //$array = json_decode($data, true);
            wp_send_json($data);
            wp_die();
        }

        wp_die("Should not be reached. Check configuration.");       
    }

    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name.'-swiper', plugin_dir_url(__FILE__) . 'css/artist-image-generator-public-swiper.css', array(), $this->version, 'all');
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/artist-image-generator-public.css', array(), $this->version, 'all'); 
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script($this->plugin_name.'-swiper', plugin_dir_url(__FILE__) . 'js/artist-image-generator-public-swiper.js', array(), $this->version, true);
        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/artist-image-generator-public.js', array('jquery', $this->plugin_name.'-swiper'), $this->version, false);
    }

    public function handle_order_completed($order_id)
    {
        $this->credits_balance_manager->handle_order_completed($order_id);
    }

    public function show_user_balance()
    {
        $this->credits_balance_manager->show_user_balance();
    }

    public function get_avatar_filter($avatar, $id_or_email, $size, $default, $alt)
    {
        $this->avatar_manager->filter($avatar, $id_or_email, $size, $default, $alt);
    }

    public function change_wp_avatar()
    {
        $this->avatar_manager->change();
    }

    private function validate_atts(&$atts)
    {    
        $atts['action'] = $this->data_validator->validateString($atts['action'], self::POSSIBLE_ACTIONS, self::DEFAULT_ACTION);
        $atts['n'] = $this->data_validator->validateInt($atts['n'], 1, 10, self::DEFAULT_N);
        $atts['model'] = $this->data_validator->validateString($atts['model'], self::POSSIBLE_MODELS, self::DEFAULT_MODEL);
        $atts['user_img'] = $this->data_validator->validateString($atts['user_img'], ['true', 'false'], 'false');
    
        if ($atts['model'] === Constants::DALL_E_MODEL_3 || $atts['model'] === Constants::AIG_MODEL) {
            $atts['n'] = $this->data_validator->validateInt($atts['n'], 1, 10, self::DEFAULT_N);
            $atts['size'] = $this->data_validator->validateSize($atts['size'], self::POSSIBLE_SIZES_DALLE_3, self::DEFAULT_SIZE);
            $atts['quality'] = $this->data_validator->validateString($atts['quality'], self::POSSIBLE_QUALITIES_DALLE_3, self::DEFAULT_QUALITY);
            $atts['style'] = $this->data_validator->validateString($atts['style'], self::POSSIBLE_STYLES_DALLE_3, self::DEFAULT_STYLE);
        } else {
            $atts['size'] = $this->data_validator->validateSize($atts['size'], self::POSSIBLE_SIZES_DALLE_2, self::DEFAULT_SIZE);
            $atts['n'] = $this->data_validator->validateInt($atts['n'], 1, 10, self::DEFAULT_N);
            $atts['quality'] = null;
            $atts['style'] = null;
        }

        $atts['user_limit'] = $this->data_validator->validateInt($atts['user_limit'], 0, PHP_INT_MAX, self::DEFAULT_LIMIT_PER_USER);
        $atts['user_limit_duration'] = $this->data_validator->validateInt($atts['user_limit_duration'], 0, PHP_INT_MAX, self::DEFAULT_LIMIT_PER_USER_REFRESH_DURATION);
    }

    private function get_default_atts($atts)
    {
        return shortcode_atts(
            array(
                'action' => self::DEFAULT_ACTION,
                'prompt' => self::DEFAULT_PROMPT,
                'topics' => self::DEFAULT_TOPICS,
                'n' => self::DEFAULT_N,
                'size' => self::DEFAULT_SIZE,
                'model' => self::DEFAULT_MODEL,
                'download' => self::DEFAULT_DOWNLOAD,
                'quality' => self::DEFAULT_QUALITY,
                'style' => self::DEFAULT_STYLE,
                'user_limit' => self::DEFAULT_LIMIT_PER_USER,
                'user_limit_duration' => self::DEFAULT_LIMIT_PER_USER_REFRESH_DURATION,
                'mask_url' => '',
                'origin_url' => '',
                'user_img' => 'false',
                'integrate_google_drive_id' => '',
                'integrate_dropbox_id' => '',
                'uniqid' => uniqid()
            ),
            $atts
        );
    }

    private function generate_shortcode_html($atts)
    {
        $checkLicence = License::license_check_validity();
        if (!$checkLicence && (esc_attr($atts['model']) === Constants::DALL_E_MODEL_3 || esc_attr($atts['model']) === Constants::AIG_MODEL)) {
            $atts['n'] = 1;    
        }

        $nonce_field = wp_nonce_field(esc_attr($atts['action']), '_ajax_nonce', true, false);

        $allowed_html = array(
            'input' => array(
                'type' => array(),
                'id' => array(),
                'name' => array(),
                'value' => array()
            )
        );

        $toggle_label = esc_html('Image N°', 'artist-image-generator');

        ob_start();

        ?>
        <div class="aig-form-container">
            <form method="post" class="aig-form" data-id="<?php echo esc_attr($atts['uniqid']); ?>"
                data-action="<?php echo esc_attr($atts['action']); ?>" 
                data-n="<?php echo esc_attr($atts['n']); ?>" 
                data-size="<?php echo esc_attr($atts['size']); ?>" 
                data-quality="<?php echo esc_attr($atts['quality']); ?>"
                data-style="<?php echo esc_attr($atts['style']); ?>"
                data-model="<?php echo esc_attr($atts['model']); ?>" 
                data-download="<?php echo esc_attr($atts['download']); ?>"
                data-toggle-label="<?php echo esc_attr($toggle_label); ?>"
                action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
                <?php if (!empty($atts['mask_url']) && !empty($atts['origin_url'])) { ?>
                    data-origin-url="<?php echo esc_url($atts['origin_url']); ?>"
                    data-mask-url="<?php echo esc_url($atts['mask_url']); ?>"
                <?php } ?>

                <?php if (esc_attr($atts['model']) === Constants::AIG_MODEL && esc_attr($atts['user_img']) === 'true') { ?>
                    enctype="multipart/form-data"
                <?php } ?>
            >
                <input type="hidden" name="aig_prompt" value="<?php echo esc_attr($atts['prompt']); ?>" />
                <input type="hidden" name="action" value="<?php echo esc_attr($atts['action']); ?>" />
                <input type="hidden" name="id" value="<?php echo esc_attr($atts['uniqid']); ?>" />
                <input type="hidden" name="user_limit" value="<?php echo esc_attr($atts['user_limit']); ?>" />
                <input type="hidden" name="user_limit_duration" value="<?php echo esc_attr($atts['user_limit_duration']); ?>" />

                <?php echo wp_kses($nonce_field, $allowed_html); ?>
                <div class="form-group">
                    <fieldset class="aig-topic-buttons">
                        <legend class="form-label"><?php esc_html_e('Topics:', 'artist-image-generator'); ?></legend>
                        <?php
                        $topics_string = $atts['topics'];
                        if (!empty($topics_string)) {
                            $topics = explode(',', $topics_string);
                            foreach ($topics as $topic) {
                                $topic = trim($topic);
                        ?>
                                <div class="form-check form-check-inline">
                                    <input type="checkbox" name="aig_topics[]" value="<?php echo esc_attr($topic); ?>" class="form-check-input" id="aig_topic_<?php echo esc_attr($topic); ?>">
                                    <label class="form-check-label" for="aig_topic_<?php echo esc_attr($topic); ?>"><?php echo esc_html($topic, 'artist-image-generator'); ?></label>
                                </div>
                        <?php
                            }
                        }
                        ?>
                    </fieldset>
                    <small id="aig_topics_help" class="form-text text-muted"><?php esc_html_e('Select one or more topics for image generation.', 'artist-image-generator'); ?></small>
                </div>
                <hr />
                <?php if (esc_attr($atts['model']) === Constants::AIG_MODEL && esc_attr($atts['user_img']) === 'true') { ?>
                <div class="form-group">
                    <label for="aig_public_user_img" class="aig-drop-container">
                        <span class="aig-drop-title"><?php esc_html_e('Drop your profile image here to faceswap', 'artist-image-generator');?></span>
                        <?php esc_html_e('or', 'artist-image-generator');?>
                        <input type="file" id="aig_public_user_img" class="form-control aig-file-upload" accept="image/jpeg, image/png, image/webp" />
                    </label>
                </div>
                <br/>
                <?php } elseif (esc_attr($atts['user_img']) === 'true') { ?>
                <div class="form-group">
                    <label for="aig_public_user_img" class="aig-drop-container">
                        <span class="aig-drop-title"><?php esc_html_e('Drop your own image here', 'artist-image-generator');?></span>
                        <?php esc_html_e('or', 'artist-image-generator');?>
                        <input type="file" id="aig_public_user_img" class="form-control aig-file-upload" accept="image/jpeg, image/png, image/webp" />
                    </label>
                </div>
                <br/>
                <?php } ?>
                <?php if (!empty(esc_attr($atts['integrate_google_drive_id']))) { ?>
                <div class="form-group">
                    <button id="aig_open_modal_btn_<?php echo esc_attr($atts['uniqid']); ?>" class="btn btn-primary"><?php esc_html_e('Select from gallery', 'artist-image-generator');?></button>
                    <div id="aig-modal_<?php echo esc_attr($atts['uniqid']); ?>" class="aig-modal">
                        <div class="aig-modal-content">
                            <span class="aig-close">&times;</span>
                            <div class="aig-modal-header">
                                <h2><?php esc_html_e('Search and select one or more images to use', 'artist-image-generator'); ?></h2>
                                <input type="text" id="searchInput" placeholder="<?php esc_html_e('Search an image...', 'artist-image-generator');?>">
                            </div>
                            <div class="aig-modal-body">
                                <?php echo do_shortcode('[integrate_google_drive id="'.esc_attr($atts['integrate_google_drive_id']).'"]'); ?>
                            </div>
                        </div>
                    </div>
                    </div>
                    <hr/>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const modal = document.getElementById('aig-modal_<?php echo esc_attr($atts['uniqid']); ?>');
                            const btn = document.getElementById('aig_open_modal_btn_<?php echo esc_attr($atts['uniqid']); ?>');
                            const span = modal.getElementsByClassName('aig-close')[0];

                            btn.onclick = function(e) {
                                e.preventDefault();
                                modal.style.display = 'block';
                            }

                            span.onclick = function() {
                                modal.style.display = 'none';
                            }

                            window.onclick = function(event) {
                                if (event.target == modal) {
                                    modal.style.display = 'none';
                                }
                            }
                        });
                    </script>    
                <?php } elseif (!empty(esc_attr($atts['integrate_dropbox_id']))) { ?>
                    <?php //echo do_shortcode('[integrate_dropbox id="'.esc_attr($atts['integrate_dropbox_id']).'"]'); ?>
                <?php } ?>
                <div class="form-group">
                    <label for="aig_public_prompt" class="form-label"><?php esc_html_e('Describe the image you want:', 'artist-image-generator');?></label>
                    <textarea name="aig_public_prompt" id="aig_public_prompt" class="form-control" placeholder="<?php esc_html_e("Enter a description for the image generation (e.g., 'A beautiful cat').", 'artist-image-generator'); ?>"></textarea>
                    <small id="aig_public_prompt_help" class="aig-public-prompt-help form-text text-muted">
                        <?php esc_html_e('Enter a description for the image generation.', 'artist-image-generator');?>
                    </small>
                </div>
                <br/>
                <?php if ($checkLicence && !empty($this->options[Constants::REFILL_PRODUCT_ID]) && is_user_logged_in()) : 
                    $user_id = get_current_user_id();
                    $user_balance = $this->credits_balance_manager::get_user_balance($user_id);
                    if ($user_balance == 0) : ?>
                        <p>
                            <?php esc_html_e('Not Enough credits.', 'artist-image-generator'); ?>
                            &nbsp;
                            <a href="<?php echo get_permalink($this->options[Constants::REFILL_PRODUCT_ID]); ?>">
                                <?php esc_html_e('Click here to buy!', 'artist-image-generator'); ?>
                            </a>
                        </p>
                    <?php else : ?>
                        <button type="submit" class="btn btn-primary">
                            <?php esc_html_e('Generate Image / Retry', 'artist-image-generator'); ?>
                            &nbsp;
                            <span class="aig-credits-balance">(
                                <span class="aig-credits-balance-value"><?php echo $user_balance; ?></span>
                                <?php esc_html_e('CR', 'artist-image-generator'); ?>
                            )</span>
                        </button>
                    <?php endif; ?>
                <?php else : ?>
                    <button type="submit" class="btn btn-primary">
                        <?php esc_html_e('Generate Image / Retry', 'artist-image-generator'); ?>
                    </button>
                <?php endif; ?>
                <hr class="aig-results-separator" style="display:none" />
                <div class="aig-errors"></div>
                <div class="aig-results"></div>
                <div class="aig-clear" style="display:hidden;">
                    <a href="#" class="aig-clear-button" data-confirm="<?php esc_html_e('Are you sure you want to clear all stored images?', 'artist-image-generator'); ?>">
                        <?php esc_html_e('Clear all stored images', 'artist-image-generator'); ?>
                    </a>
                </div>
            </form>
        </div>
    <?php

        return ob_get_clean();
    }

    public function aig_shortcode($atts)
    {
        $atts = $this->get_default_atts($atts);

        // Validate attributs
        $this->validate_atts($atts);

        return $this->generate_shortcode_html($atts);
    }
}
