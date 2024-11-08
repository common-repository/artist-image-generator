<?php

use Orhanerday\OpenAi\OpenAi;
use Artist_Image_Generator_Constant as Constants;
use Artist_Image_Generator_Setter as Setter;
use Artist_Image_Generator_Service as Service;

class Artist_Image_Generator_Dalle
{
    private const AUTHORIZED_DALLE_FIELDS = [
        'generate', 
        'variate', 
        'edit', 
        'prompt', 
        'size', 
        'n', 
        'model', 
        'quality', 
        'style',
        'user_limit',
        'user_limit_duration',
        'user_img',
        'id'
    ];
    private const ERROR_MSG_PROMPT = 'The Prompt input must be filled in order to generate an image.';
    private const ERROR_MSG_USER_IMG = 'An image must be sent for the generation.';
    private const ERROR_MSG_IMAGE = 'A .png square (1:1) image of maximum 4MB needs to be uploaded in order to generate a variation of this image.';
    private const ERROR_TYPE_INVALID_FORM = 'invalid_form_error';
    private const ERROR_TYPE_AIG_SERVER = 'aig_server_error';
    private const DEFAULT_SIZE_INPUT = '1024x1024';

    private array $options;

    public function __construct()
    {
       $this->options = Setter::get_options();
    }

    public function sanitize_post_data(): array
    {
        $post_data = [];

        foreach (self::AUTHORIZED_DALLE_FIELDS as $field) {
            if (isset($_POST[$field])) {
                $post_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }

        return $post_data;
    }

    public function handle_response(array $response): array
    {
        $images = [];
        $error = [];

        if (array_key_exists('error', $response)) {
            if ($response['error']['type'] === self::ERROR_TYPE_AIG_SERVER) {
                $response['error']['message'] .=  esc_html__(' [AIG Server Error]', 'artist-image-generator');
            }
            else if ($response['error']['type'] !== self::ERROR_TYPE_INVALID_FORM) {
                $response['error']['message'] .=  esc_html__(' [OpenAi Error]', 'artist-image-generator');
            }
            $error = $response['error'];
        } else {
            $images = $response;
        }

        return [$images, $error];
    }

    public function handle_generation(array $post_data): array
    {
        if (empty($post_data['prompt'])) {
            return $this->handle_error(self::ERROR_MSG_PROMPT);
        }

        return $this->generate(
            $post_data['prompt'], 
            $post_data['n'], 
            $post_data['size'], 
            $post_data['model'], 
            $post_data['quality'], 
            $post_data['style'],
            $post_data['user_img'] ?? ''
        );
    }

    public function handle_variation(array $post_data): array
    {
        $image_file = $this->validate_image_file($_FILES['image']);

        if (isset($image_file['error']) && $image_file['error'] !== 0) {
            return $image_file;
        }

        return $this->variate($image_file, $post_data['n'], $post_data['size']);
    }

    public function handle_edit(array $post_data): array
    {
        $image_file = $this->validate_image_file($_FILES['image']);
        $mask_file = $this->validate_image_file($_FILES['mask']);

        if (isset($image_file['error']) && $image_file['error'] !== 0) {
            return $image_file;
        }

        if (isset($mask_file['error']) && $image_file['error'] !== 0) {
            return $mask_file;
        }

        return $this->edit($image_file, $mask_file, $post_data['prompt'], $post_data['n'], $post_data['size']);
    }

    private function validate_image_file($image_file): array
    {
        if (empty($image_file) || !is_uploaded_file($image_file['tmp_name'])){
            return $this->handle_error(self::ERROR_MSG_IMAGE);
        }

        $image_mime_type = mime_content_type($image_file['tmp_name']);
        list($image_width, $image_height) = getimagesize($image_file['tmp_name']);
        $image_wrong_size = $image_file['size'] >= ((1024 * 1024) * 4) || $image_file['size'] == 0;
        $allowed_file_types = ['image/png']; // If you want to allow certain files

        if (!in_array($image_mime_type, $allowed_file_types) || $image_wrong_size || $image_height !== $image_width) {
            return $this->handle_error(self::ERROR_MSG_IMAGE);
        }

        return $image_file;
    }

    public function prepare_data($images, array $error, array $post_data): array
    {
        return [
            'error' => $error,
            'images' => count($images) ? $images['data'] : [],
            'model_input' => $post_data['model'] ?? '',
            'prompt_input' => $post_data['prompt'] ?? '',
            'size_input' => $post_data['size'] ?? self::DEFAULT_SIZE_INPUT,
            'n_input' => $post_data['n'] ?? 1,
            'quality_input' => $post_data['quality'] ?? '',
            'style_input' => $post_data['style'] ?? ''
        ];
    }

    public function download_image_and_get_extension($urlOrFilePath): array
    {
        $isUrl = filter_var($urlOrFilePath, FILTER_VALIDATE_URL);

        if ($isUrl) {
            $tmp = download_url($urlOrFilePath);
            if (is_wp_error($tmp)) {
                return array(false, null, null);
            }
            $filename = pathinfo($urlOrFilePath, PATHINFO_FILENAME);
        }else {
            // Assume it's a $_FILES object
            $tmp = $urlOrFilePath['tmp_name'];
            $filename = $urlOrFilePath['name'];
        }

        // Upscaling
        /*if ($may_upscale && !empty($this->options[Constants::ACCOUNT_JWT]) && $this->options[Constants::UPSCALE_IMAGES] === 'all') {
            $service = new Service();
            try {
                $upscaled_image = $service->upscale_my_image($tmp);
                if ($upscaled_image) {
                    $tmp = tempnam(sys_get_temp_dir(), 'upscaled_');
                    file_put_contents($tmp, $upscaled_image);
                    $urlOrFilePath = $tmp;
                }
            } catch (\Exception $e) {
                error_log("Error : " . $e->getMessage());
            }
        }*/

        // Get file type from file content
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $extension = $this->get_extension_from_mime($mime);
        $filename = $isUrl ? pathinfo($urlOrFilePath, PATHINFO_FILENAME) : "mask_".uniqid(); // Generate a unique filename for uploaded files

        if (!$extension) {
            wp_delete_file($tmp);
            return array(false, null, null);
        }

        return array($tmp, $extension, $filename);
    }
    
    private function handle_error(string $message): array
    {
        return [
            'error' => [
                'message' => esc_html($message),
                'type' => self::ERROR_TYPE_INVALID_FORM
            ]
        ];
    }

    private function create_curl_file(array $file): CURLFile
    {
        $tmp_file = $file['tmp_name'];
        $file_name = basename($file['name']);
        return new CURLFile($tmp_file, $file['type'], $file_name);
    }

    private function validate_num_images(int $n_input): int
    {
        return max(1, min(10, $n_input));
    }

    private function generate(
        string $prompt_input, 
        string $n_input, 
        string $size_input, 
        ?string $model_input = '',
        ?string $quality_input = '',
        ?string $style_input = '',
        ?string $user_img = null
    ): array
    {
        $valid_models = [
            Constants::DALL_E_MODEL_2,
            Constants::DALL_E_MODEL_3,
            Constants::AIG_MODEL
        ];

        $model = in_array($model_input, $valid_models) ? $model_input : Constants::DALL_E_MODEL_2;
        $quality = !empty($quality_input) ? $quality_input : 'standard';
        $style = !empty($style_input) ? $style_input : 'vivid';
        $number = $this->validate_num_images((int) $n_input);

        if (in_array($model, [Constants::DALL_E_MODEL_2, Constants::DALL_E_MODEL_3])) {
            $open_ai = new OpenAi($this->options[Constants::OPENAI_API_KEY]);
            $params = [
                "prompt" => $prompt_input,
                "n" => $number,
                "size" => $size_input,
                'model' => $model
            ];
    
            if ($model === Constants::DALL_E_MODEL_3) {
                $params['n'] = 1;
                $params['quality'] = $quality;
                $params['style'] = $style;
            }
    
            $result = $open_ai->image($params);
        }
        else {
            // Use AIG image Service
            $uniqueFilename = 'generated_' . time(). '_' . uniqid() . '.png';

            $result = (new Artist_Image_Generator_Service())->generate_my_image(
                $user_img, 
                $prompt_input, 
                $uniqueFilename
            );
        }

        return json_decode($result, true);
    }

    private function variate(array $image_file, int $n_input, string $size_input): array
    {
        $number = $this->validate_num_images($n_input);
        $open_ai = new OpenAi($this->options[Constants::OPENAI_API_KEY]);
        $image = $this->create_curl_file($image_file);

        $result = $open_ai->createImageVariation([
            "image" => $image,
            "n" => $number,
            "size" => $size_input,
        ]);

        return json_decode($result, true);
    }

    private function edit(array $image_file, array $mask_file, string $prompt_input, int $n_input, string $size_input): array
    {
        $number = $this->validate_num_images($n_input);
        $open_ai = new OpenAi($this->options[Constants::OPENAI_API_KEY]);
        $image = $this->create_curl_file($image_file);
        $mask = $this->create_curl_file($mask_file);

        $result = $open_ai->imageEdit([
            "image" => $image,
            "mask" => $mask,
            "prompt" => $prompt_input,
            "n" => $number,
            "size" => $size_input,
        ]);

        return json_decode($result, true);
    }

    private function get_extension_from_mime(string $tmp): ?string
    {
        $mime = mime_content_type($tmp);
        $mime = is_string($mime) ? sanitize_mime_type($mime) : false;

        $mime_extensions = array(
            'image/jpg'  => 'jpg',
            'image/jpeg' => 'jpeg',
            'image/gif'  => 'gif',
            'image/png'  => 'png'
        );

        return $mime_extensions[$mime] ?? 'png';
    }

    public function include_wordpress_files(): void
    {
        require_once ABSPATH . "/wp-admin/includes/image.php";
        require_once ABSPATH . "/wp-admin/includes/file.php";
        require_once ABSPATH . "/wp-admin/includes/media.php";
    }
}