<?php

use Artist_Image_Generator_License as License;
use Artist_Image_Generator_Constant as Constants;
use Artist_Image_Generator_Setter as Setter;

/**
 * The credit - balance manager for the plugin.
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
class Artist_Image_Generator_Credits_Balance_Manager 
{
    private array $options;

    public function __construct()
    {
       $this->options = Setter::get_options();
    }

    public function show_user_balance() {
        if (!License::license_check_validity()) {
            return;
        }
        
        if (empty($this->options[Constants::REFILL_PRODUCT_ID])) {
            return;
        }

        $user_id = get_current_user_id();
        $user_credits = self::get_user_balance($user_id);

        echo '<p class="aig-user-balance">' . sprintf(
            __('You have <strong>%s</strong> credits. <a href="%s">Buy more</a>', 'artist-image-generator'),
            esc_html($user_credits),
            esc_url(get_permalink($this->options[Constants::REFILL_PRODUCT_ID]))
        ) . '</p>';
    }

    public function handle_order_completed($order_id) {
        if (!License::license_check_validity()) {
            return;
        }

        $order = wc_get_order($order_id);
        $refill_product_id = $this->options[Constants::REFILL_PRODUCT_ID];
    
        // Vérifiez si la commande a déjà été traitée
        $order_processed = get_post_meta($order_id, Constants::REFILL_WC_ORDER_RECHARGED, true);
        if ($order_processed) {
            return; // Si la commande a déjà été traitée, ne rien faire
        }
    
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $refill_product_id) {
                $credits = $item->get_meta(Constants::REFILL_WC_PRODUCT_META_KEY);
                $user_id = $order->get_user_id();

                if ($user_id) {
                    $this->update_user_credits($user_id, $credits);
                }
            }
        }
    
        // Marquez la commande comme traitée
        update_post_meta($order_id, Constants::REFILL_WC_ORDER_RECHARGED, true);
    }

    public static function update_user_credits($user_id, $credits) {    
        $current_version = get_user_meta($user_id, Constants::REFILL_USER_META_KEY_VERSION, true);
    
        if ($current_version === '') {
            // The version number doesn't exist yet for the user, so create it
            $current_version = 0;
            add_user_meta($user_id, Constants::REFILL_USER_META_KEY_VERSION, $current_version);
        }
    
        $user_credits = self::get_user_balance($user_id);
        $new_balance = $user_credits + $credits;
        $new_version = $current_version + 1;
        
        $updated = update_user_meta($user_id, Constants::REFILL_USER_META_KEY, $new_balance, $user_credits);
        $version_updated = update_user_meta($user_id, Constants::REFILL_USER_META_KEY_VERSION, $new_version, $current_version);
    
        return self::get_user_balance($user_id);
    }

    public static function get_user_balance($user_id) {
        $user_credits = get_user_meta($user_id, Constants::REFILL_USER_META_KEY, true);

        if (!$user_credits || $user_credits < 0) {
            $user_credits = 0;
        }

        return $user_credits;
    }
}