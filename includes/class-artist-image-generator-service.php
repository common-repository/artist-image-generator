<?php

use Artist_Image_Generator_Constant as Constants;
use Artist_Image_Generator_Setter as Setter;

class Artist_Image_Generator_Service 
{
    const API_AIG_URL = 'https://artist-image-generator.com/?rest_route=/wp/v2/users/me&context=view&_fields=id,meta';
    const API_SERVICE_URL = 'https://cron.urfram.com/?action=do_upscale';
    const API_SERVICE_URL_GENERATIVE = 'https://cron.urfram.com/?action=do_generate';
    const META_KEY_BALANCE = '_aig_balance';

    /**
     * Get user balance.
     *
     * @return int Le nombre de crédits.
     */
    public static function get_my_credits(): float {
        $options = Setter::get_options();
        $jwt = $options[Constants::ACCOUNT_JWT];

        if (empty($jwt)) {
            return 0;
        }

        try {
            $balance = self::get_user_meta_balance($jwt);
        } catch (\Exception $exception) {
            error_log($exception->getMessage());
            return 0;
        }

        return $balance;
    }

    /**
     * Get user balance API call
     *
     * @param string $jwt Le JWT de l'utilisateur.
     * @return int Le solde des crédits.
     */
    private static function get_user_meta_balance(string $jwt): float {
        $options = Setter::get_options();
        $jwt = $options[Constants::ACCOUNT_JWT];

        if (empty($jwt)) {
            return 0;
        }

        $url = self::API_AIG_URL . '&JWT=' . $jwt;
     
        $result = self::call('GET', $url, [], ['Content-Type: application/json']);
        $data = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Response is not a valid JSON: " . json_last_error_msg());
        }
    
        if (isset($data['success']) && $data['success'] === false) {
            throw new \Exception($data['message']);
        }
    
        if (empty($data) || !isset($data['meta'][self::META_KEY_BALANCE])) {
           return 0;
        }

        return $data['meta'][self::META_KEY_BALANCE];
    }

    /**
     * Send image to API and get the processed image.
     *
     * @param string $imagePath Le chemin de l'image à envoyer.
     * @param string $destinationPath Le chemin où enregistrer l'image traitée.
     * @return string Le contenu de l'image renvoyée par l'API.
     * @throws \Exception Si une erreur survient lors de la requête.
     */
    public function upscale_my_image(string $imagePath, string $destinationPath): string {
        $options = Setter::get_options();
        $jwt = $options[Constants::ACCOUNT_JWT];

        try {
            
            if (empty($jwt)) {
                throw new \Exception("This is an AIG premium feature. Please buy credits here: https://artist-image-generator.com/product/credits/.");
            }

            $url = self::API_SERVICE_URL . '&JWT=' . $jwt;

            // Lire le contenu de l'image à partir de $imagePath
            $imageContent = file_get_contents($imagePath);
            if ($imageContent === false) {
                throw new \Exception("File not found $imagePath");
            }

            // Préparer les données pour l'envoi
            $boundary = wp_generate_password(24, false);
            $body = "--$boundary\r\n";
            $body .= 'Content-Disposition: form-data; name="image"; filename="' . basename($imagePath) . '"' . "\r\n";
            $body .= "Content-Type: image/jpeg\r\n\r\n";
            $body .= $imageContent . "\r\n";
            $body .= "--$boundary--\r\n";

            // Préparer les en-têtes
            $headers = [
                'Accept' => 'image/*',
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ];

            $response = self::call('POST', $url, $body, $headers, true, $destinationPath);
    
            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message() . ' (' . $response->get_error_code() . ')');
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($destinationPath);
    
            if (strpos($mimeType, 'image/') !== 0) {
                $errorMessage = file_get_contents($destinationPath);
                throw new \Exception($errorMessage);
            }
    
            return file_get_contents($destinationPath);
    
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Send image to API and get the processed image.
     *
     * @param string|null $base64Image Les données de l'image en base64 (peut être null).
     * @param string $prompt Le prompt utilisateur.
     * @param string $filename Le nom du fichier.
     * @return string $filename Le nom du fichier.
     * @throws \Exception Si une erreur survient lors de la requête.
     */
    public function generate_my_image(?string $base64Image, string $prompt, string $filename): string {
        $options = Setter::get_options();
        $jwt = $options[Constants::ACCOUNT_JWT];

        try {
            if (empty($jwt)) {
                throw new \Exception("This is an AIG premium feature. Please buy credits here: https://artist-image-generator.com/product/credits/.");
            }

            $url = self::API_SERVICE_URL_GENERATIVE . '&JWT=' . $jwt;

            $boundary = wp_generate_password(24, false);
            $body = '';

            if (!empty($base64Image)) {
                // Decode base64
                $imageContent = base64_decode($base64Image);
                if ($imageContent === false) {
                    throw new \Exception("Invalid base64 image data");
                }

                $body .= "--$boundary\r\n";
                $body .= 'Content-Disposition: form-data; name="image"; filename="' . $filename . '"' . "\r\n";
                $body .= "Content-Type: image/png\r\n\r\n";
                $body .= $imageContent . "\r\n";
            }

            $body .= "--$boundary\r\n";
            $body .= 'Content-Disposition: form-data; name="prompt"' . "\r\n\r\n";
            $body .= $prompt . "\r\n";
            $body .= "--$boundary--\r\n";

            $headers = [
                'Accept' => 'image/*',
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ];

            $response = self::call('POST', $url, $body, $headers, false);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message() . ' (' . $response->get_error_code() . ')');
            }

            if (is_string($response) && filter_var($response, FILTER_VALIDATE_URL)) {
                // La réponse est une URL valide
                return json_encode([
                    'data' => [
                        ['url' => $response]
                    ]
                ]);
            } else {
                // La réponse n'est pas une URL, extraire le message d'erreur
                $responseBody = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($responseBody['errors'])) {
                    $errorMessage = implode(', ', $responseBody['errors']);
                } else {
                    $errorMessage = 'Unknown error' . $responseBody;
                }
                return json_encode([
                    'error' => [
                        'type' => 'aig_server_error',
                        'message' => $errorMessage
                    ]
                ]);
            }

        } catch (\Exception $e) {
            return json_encode([
                'error' => [
                    'type' => 'aig_server_error',
                    'message' => $e->getMessage()
                ]
            ]);
        }
    }

    private static function call($method, $endpoint, $data = array(), $headers = array(), $stream = false, $filename = null)
    {
        $args = array(
            'method'  => strtoupper($method),
            'headers' => $headers,
            'sslverify' => false,
            'timeout' => 120
        );
    
        if ($method === 'POST' && !empty($data)) {
            $args['body'] = $data;
        }

        if ($stream) {
            $args['stream'] = true;
            if ($filename) {
                $args['filename'] = $filename;
            }
        }
    
        $response = wp_remote_request($endpoint, $args);
    
        if (is_wp_error($response)) {
            return $response;
        }
        
        return wp_remote_retrieve_body($response);
    }
}