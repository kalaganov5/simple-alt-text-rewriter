<?php

if (! defined('ABSPATH')) {
    exit;
}

class SATR_Api_Client
{

    private $api_key;

    public function __construct()
    {
        $this->api_key = get_option('satr_gemini_api_key', '');
    }

    public function generate_text($image_context, $current_text, $post_title, $image_path = null, $type = 'alt')
    {
        if (empty($this->api_key)) {
            return "Error: Gemini API Key is missing. Please check settings.";
        }

        // Using 'gemini-2.5-flash' based on 2026 documentation.
        $model = 'gemini-2.5-flash';

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->api_key;

        if ($type === 'description') {
            $prompt_template = get_option('satr_description_prompt');
            if (empty($prompt_template)) {
                $prompt_template = "You are a content writer. Generate a detailed and engaging description in Russian for the provided image.\nContext details:\n- Article Title: {post_title}\n- Surrounding Text: {image_context}\n\nThe description should provide more detail than alt text, setting the scene or explaining the visual content in relation to the article. Return ONLY the description.";
            }
        } else {
            $prompt_template = get_option('satr_custom_prompt');
            if (empty($prompt_template)) {
                $prompt_template = "You are a W3C Web Accessibility and SEO expert. Generate high-quality alt text in Russian for the provided image.\n\nContext:\n- Title: {post_title}\n- Text: {image_context}\n- Old Alt: {current_alt}\n\nStrict Rules:\n1. NO Filler Phrases: NEVER start with \"Image of\", \"Picture of\", \"Show\", \"На изображении\", \"Фото\", \"Картинка\", \"Здесь мы видим\". Start directly with the subject.\n2. Accessibility First: If the image contains text, transcribe it. If it conveys information, describe the meaning, not just the visuals.\n3. SEO: Integrate relevant keywords from the context naturally, but do not stuff.\n4. Length: Concise (max 125 chars).\n5. Context: Ensure the alt fills the gap in the surrounding text.\n\nReturn ONLY the alt text.";
            }
        }

        $prompt_text = str_replace(
            array('{post_title}', '{image_context}', '{current_alt}'),
            array($post_title, $image_context, $current_text),
            $prompt_template
        );

        $parts = array(
            array('text' => $prompt_text)
        );

        if ($image_path && file_exists($image_path)) {
            $filetype = wp_check_filetype($image_path);
            $mime_type = $filetype['type'];
            // Fallback if wp_check_filetype fails or returns null
            if (!$mime_type) {
                $mime_type = mime_content_type($image_path);
            }

            $image_data = base64_encode(file_get_contents($image_path));

            // Add image to the beginning of parts
            array_unshift($parts, array(
                'inline_data' => array(
                    'mime_type' => $mime_type,
                    'data'      => $image_data
                )
            ));
        }

        $body = array(
            'contents' => array(
                array(
                    'parts' => $parts
                )
            )
        );

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode($body),
            'timeout' => 30 // Increased timeout for image upload
        ));

        if (is_wp_error($response)) {
            return "Error calling Gemini API: " . $response->get_error_message();
        }

        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($data['candidates'][0]['content']['parts'][0]['text']);
        } else {
            // Handle error response from Gemini
            if (isset($data['error']['message'])) {
                return "Gemini Error: " . $data['error']['message'];
            }
            return "Error: Unexpected response format from Gemini.";
        }
    }
}
