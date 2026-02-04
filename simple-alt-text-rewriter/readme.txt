=== Simple Alt Text Rewriter ===
Contributors: yourname
Tags: seo, accessibility, images, alt text, ai
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Generates SEO-optimized alt text and captions using article context and AI.

== Description ==

Most existing plugins simply "look" at the picture but don't understand its role in the article. Using surrounding text (context) is a "killer feature" for SEO, as it allows you to fit keywords as naturally as possible.

This plugin uses Google Gemini AI to analyze your content and images to generate:
*   High-quality Alt Text based on W3C standards.
*   Engaging Image Captions based on copywriting best practices.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/simple-alt-text-rewriter` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Settings -> Alt Text Rewriter and enter your Google Gemini API Key.

== Frequently Asked Questions ==

= Can I customize the AI prompts? =

Yes. Go to **Settings** -> **Alt Text Rewriter**. You can modify the system prompts used for Alt Text or Descriptions.
Available variables:
* `{post_title}` - Title of the post/page.
* `{image_context}` - Text surrounding the image.
* `{current_alt}` - Current alt text (for rewriting).

= I get "User location is not supported" or "400 Bad Request" error? =

Google Gemini API restricts access from certain countries (e.g. EU, UK, Russia, China).
To resolve this, the plugin allows you to change the **API Base URL**.

You can set up a simple Reverse Proxy (e.g., using Cloudflare Workers) and point the plugin to it:
1. Create a worker in Cloudflare.
2. Paste code to proxy requests to `generativelanguage.googleapis.com`.
3. Enter your Worker URL into the **API Base URL** field in plugin settings.
