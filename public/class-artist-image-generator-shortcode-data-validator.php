<?php
/**
 * Shortcodes Data Validator
 * 
 * @link       https://pierrevieville.fr
 * @since      1.0.18
 * 
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Artist_Image_Generator
 * @subpackage Artist_Image_Generator/public
 * @author     Pierre Viéville <contact@pierrevieville.fr>
 */
class Artist_Image_Generator_Shortcode_Data_Validator {
    public function validateString($value, $possibleValues, $defaultValue)
    {
        $value = strtolower($value);
        return in_array($value, $possibleValues) ? $value : $defaultValue;
    }

    public function validateInt($value, $min, $max, $defaultValue)
    {
        $value = intval($value);
        return ($value >= $min && $value <= $max) ? $value : $defaultValue;
    }

    public function validateSize($size, $possibleSizes, $defaultSize)
    {
        $size = strtolower($size);
        return in_array($size, $possibleSizes) ? $size : $defaultSize;
    }

    public function validateUrls($urls)
    {
        $urls = str_replace(['[', ']'], ['{', '}'], $urls);

        if (!is_array($urls)) {
            return '';
        }
    
        $urlPattern = '/\b(?:https?|ftp):\/\/[a-z0-9-]+(?:\.[a-z0-9-]+)+(?:\/[^\s]*)?\b/i';
        $validUrls = [];
    
        foreach ($urls as $url) {
            if (preg_match($urlPattern, $url)) {
                $validUrls[] = esc_url($url);
            }
        }
            
        return $validUrls;
    }

    public function validateVariationsJSON($json)
    {
        if (!is_string($json)) {
            return '';
        }
        
        $json = str_replace(['[', ']'], ['{', '}'], $json);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return '';
        }

        $validVariations = [];
        foreach ($decoded as $item) {
            if (is_array($item) && isset($item['id'], $item['origin_url'], $item['mask_url'])) {
                $cleanedItem = [
                    'id' => intval($item['id']),
                    'origin_url' => esc_url($item['origin_url']),
                    'mask_url' => esc_url($item['mask_url']),
                ];
                $validVariations[] = $cleanedItem;
            }
        }        

        return $validVariations;
    }
}