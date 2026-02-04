<?php

if (! defined('ABSPATH')) {
    exit;
}

class SATR_Context_Finder
{

    /**
     * Get the context for an image in a specific post.
     *
     * @param int $post_id
     * @param int $image_id
     * @return string
     */
    public function get_image_context($post_id, $image_id)
    {
        $post = get_post($post_id);
        if (! $post) {
            return '';
        }

        $content = $post->post_content;

        // Attempt to find the block or content surrounding the image.
        // This is a simplified regex approach. For full Gutenberg parsing, parse_blocks() is better.

        if (has_blocks($content)) {
            $blocks = parse_blocks($content);
            return $this->find_context_in_blocks($blocks, $image_id);
        }

        // Fallback for Classic Editor or raw content
        return $this->find_context_in_text($content, $image_id);
    }

    private function find_context_in_blocks($blocks, $image_id)
    {
        $context = '';
        // Flatten blocks for easier traversal
        // Ideally, we search for the image block and look at prev/next blocks.

        for ($i = 0; $i < count($blocks); $i++) {
            $block = $blocks[$i];

            // Check if this block contains the image
            $contains_image = false;
            if ('core/image' === $block['blockName'] && isset($block['attrs']['id']) && $block['attrs']['id'] == $image_id) {
                $contains_image = true;
            } elseif (strpos($block['innerHTML'], 'wp-image-' . $image_id) !== false) {
                // Fallback check in HTML
                $contains_image = true;
            }

            if ($contains_image) {
                // Get previous block text
                if (isset($blocks[$i - 1])) {
                    $context .= wp_strip_all_tags(render_block($blocks[$i - 1])) . " ";
                }

                // Get next block text
                if (isset($blocks[$i + 1])) {
                    $context .= wp_strip_all_tags(render_block($blocks[$i + 1])) . " ";
                }

                // Also include the post title as fallback or addition?
                // $context .= get_the_title( ... );

                break; // Stop after finding the first occurrence
            }

            if (! empty($block['innerBlocks'])) {
                $inner_context = $this->find_context_in_blocks($block['innerBlocks'], $image_id);
                if (! empty($inner_context)) {
                    return $inner_context;
                }
            }
        }

        return trim($context);
    }

    private function find_context_in_text($content, $image_id)
    {
        // Classic editor context finding - complex with just regex, mostly just return excerpt or full content
        // For now, let's return a chunk of text around the image tag
        // This is just a placeholder logic
        return wp_trim_words(strip_shortcodes($content), 50);
    }
}
