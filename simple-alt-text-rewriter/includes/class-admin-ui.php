<?php

if (! defined('ABSPATH')) {
    exit;
}

class SATR_Admin_UI
{

    private $api_client;
    private $context_finder;

    public function __construct($api_client, $context_finder)
    {
        $this->api_client = $api_client;
        $this->context_finder = $context_finder;

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu()
    {
        add_options_page(
            'Simple Alt Text Rewriter',
            'Alt Text Rewriter',
            'manage_options',
            'simple-alt-text-rewriter',
            array($this, 'settings_page_html')
        );
    }

    public function register_settings()
    {
        register_setting('satr_settings_group', 'satr_gemini_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ));
        register_setting('satr_settings_group', 'satr_api_base_url', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'https://generativelanguage.googleapis.com'
        ));
        register_setting('satr_settings_group', 'satr_custom_prompt', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => ''
        ));
        register_setting('satr_settings_group', 'satr_description_prompt', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => ''
        ));

        add_settings_section(
            'satr_general_section',
            'API Settings',
            null,
            'simple-alt-text-rewriter'
        );

        add_settings_field(
            'satr_gemini_api_key',
            'Gemini API Key',
            array($this, 'api_key_field_html'),
            'simple-alt-text-rewriter',
            'satr_general_section'
        );

        add_settings_field(
            'satr_api_base_url',
            'API Base URL',
            array($this, 'api_base_url_field_html'),
            'simple-alt-text-rewriter',
            'satr_general_section'
        );

        add_settings_field(
            'satr_custom_prompt',
            'Alt Text Prompt',
            array($this, 'prompt_field_html'),
            'simple-alt-text-rewriter',
            'satr_general_section'
        );

        add_settings_field(
            'satr_description_prompt',
            'Description Prompt',
            array($this, 'description_prompt_field_html'),
            'simple-alt-text-rewriter',
            'satr_general_section'
        );
    }

    public function api_key_field_html()
    {
        $api_key = get_option('satr_gemini_api_key');
?>
        <input type="password" name="satr_gemini_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
        <p class="description">Enter your Google Gemini API Key here.</p>
    <?php
    }

    public function api_base_url_field_html()
    {
        $base_url = get_option('satr_api_base_url', 'https://generativelanguage.googleapis.com');
    ?>
        <input type="text" name="satr_api_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text">
        <p class="description">Default: <code>https://generativelanguage.googleapis.com</code>. Change if you need to use a proxy (e.g. for restricted regions).</p>
    <?php
    }

    public function prompt_field_html()
    {
        $prompt = get_option('satr_custom_prompt');
        $default_prompt = "You are a W3C Web Accessibility and SEO expert. Generate high-quality alt text in Russian for the provided image.\n\nContext:\n- Title: {post_title}\n- Text: {image_context}\n- Old Alt: {current_alt}\n\nStrict Rules:\n1. NO Filler Phrases: NEVER start with \"Image of\", \"Picture of\", \"Show\", \"На изображении\", \"Фото\", \"Картинка\", \"Здесь мы видим\". Start directly with the subject.\n2. Accessibility First: If the image contains text, transcribe it. If it conveys information, describe the meaning, not just the visuals.\n3. SEO: Integrate relevant keywords from the context naturally, but do not stuff.\n4. Length: Concise (max 125 chars).\n5. Context: Ensure the alt fills the gap in the surrounding text.\n\nReturn ONLY the alt text.";

        if (empty($prompt)) {
            $prompt = $default_prompt;
        }
    ?>
        <textarea name="satr_custom_prompt" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($prompt); ?></textarea>
        <p class="description">
            Instructions for <strong>Alt Text</strong>.<br>
            <strong>Variables:</strong> <code>{post_title}</code>, <code>{image_context}</code>, <code>{current_alt}</code>.
        </p>
    <?php
    }

    public function description_prompt_field_html()
    {
        $prompt = get_option('satr_description_prompt');
        $default_prompt = "You are a professional copywriter. Generate an engaging caption in Russian for the provided image.\n\nContext:\n- Title: {post_title}\n- Text: {image_context}\n\nRules:\n1. Non-obvious: Do NOT describe what is already clearly visible. Focus on location, context, date, or event.\n2. Length: 2-3 short, punchy sentences.\n3. Keywords: Use LSI (Latent Semantic Indexing) phrases naturally.\n4. Value: Explain WHY this image matters here.\n\nReturn ONLY the caption text.";

        if (empty($prompt)) {
            $prompt = $default_prompt;
        }
    ?>
        <textarea name="satr_description_prompt" rows="5" cols="50" class="large-text code"><?php echo esc_textarea($prompt); ?></textarea>
        <p class="description">
            Instructions for <strong>Image Description</strong>.<br>
            <strong>Variables:</strong> <code>{post_title}</code>, <code>{image_context}</code>.
        </p>
    <?php
    }

    public function settings_page_html()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
    ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('satr_settings_group');
                do_settings_sections('simple-alt-text-rewriter');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
<?php    }

    public function enqueue_assets($hook_suffix)
    {
        // Enqueue on Block Editor and Media Library
        // $hook_suffix == 'post.php', 'post-new.php', 'upload.php'

        $should_enqueue = false;
        $screen = get_current_screen();

        if ($screen && $screen->is_block_editor) {
            $should_enqueue = true;
        }
        if ('upload.php' === $hook_suffix) {
            $should_enqueue = true;
        }
        if ('post.php' === $hook_suffix || 'post-new.php' === $hook_suffix) {
            // Classic editor might use media modal too
            $should_enqueue = true;
        }

        if (! $should_enqueue) {
            return;
        }

        // Enqueue the build file from assets/js
        wp_enqueue_script(
            'satr-admin-script',
            SATR_URL . 'assets/js/admin-script.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-editor', 'wp-data', 'wp-compose', 'jquery', 'wp-api-fetch'),
            filemtime(SATR_PATH . 'assets/js/admin-script.js'),
            true
        );

        wp_enqueue_style(
            'satr-admin-style',
            SATR_URL . 'assets/css/admin-style.css',
            array(),
            filemtime(SATR_PATH . 'assets/css/admin-style.css')
        );
    }

    public function register_rest_routes()
    {
        register_rest_route('satr/v1', '/generate-alt', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_generate_alt'),
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ));
    }

    public function handle_generate_alt($request)
    {
        $params = $request->get_json_params();
        $image_id = isset($params['imageId']) ? intval($params['imageId']) : 0;
        $post_id = isset($params['postId']) ? intval($params['postId']) : 0;
        $context = isset($params['context']) ? sanitize_text_field($params['context']) : '';
        $type = isset($params['type']) ? sanitize_text_field($params['type']) : 'alt'; // 'alt' or 'description'
        $current_text = isset($params['currentAlt']) ? sanitize_text_field($params['currentAlt']) : ''; // Legacy name, holds text

        if (! $image_id) {
            return new WP_Error('missing_params', 'Image ID is required', array('status' => 400));
        }

        $post = get_post($post_id);
        $post_title = $post ? $post->post_title : '';

        if (empty($context) && $post_id) {
            $context = $this->context_finder->get_image_context($post_id, $image_id);
        }

        $image_path = get_attached_file($image_id);
        // We use a unified method now, renamed to generate_text in API client or we adapt generate_alt_text
        // Let's assume we rename api method to `generate_text` for clarity, but I will do it in next step.
        // For now calling generate_alt_text but passing type
        $generated = $this->api_client->generate_text($context, $current_text, $post_title, $image_path, $type);

        return rest_ensure_response(array(
            'text' => $generated,
            'type' => $type,
            'context_used' => $context
        ));
    }
}
