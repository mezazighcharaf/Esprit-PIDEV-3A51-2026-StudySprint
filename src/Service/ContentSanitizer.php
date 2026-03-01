<?php

namespace App\Service;

/**
 * Content sanitization service for preventing XSS attacks
 * Sanitizes user-generated content (posts, comments) using HTML whitelist
 */
class ContentSanitizer
{
    /**
     * Allowed HTML tags for rich content
     */
    private const ALLOWED_TAGS_RICH = [
        'p', 'br', 'strong', 'em', 'u', 's',
        'a', 'ul', 'ol', 'li', 'blockquote',
        'code', 'pre', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'
    ];

    /**
     * Allowed HTML tags for plain content
     */
    private const ALLOWED_TAGS_PLAIN = ['p', 'br', 'strong', 'em'];

    /**
     * Sanitize rich HTML content (posts)
     * Allows basic formatting and links
     */
    public function sanitizeRich(string $content): string
    {
        if (empty(trim($content))) {
            return '';
        }

        // Remove script tags and event handlers
        $content = $this->stripDangerousElements($content);

        // Decode HTML entities once to process the content
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Strip disallowed tags but keep content
        $content = $this->stripDisallowedTags($content, self::ALLOWED_TAGS_RICH);

        // Remove dangerous attributes from allowed tags
        $content = $this->stripDangerousAttributes($content);

        // Clean up whitespace
        $content = $this->cleanWhitespace($content);

        return trim($content);
    }

    /**
     * Sanitize plain text content (comments)
     * Only allows basic formatting
     */
    public function sanitizePlain(string $content): string
    {
        if (empty(trim($content))) {
            return '';
        }

        // Remove all HTML tags except a few safe ones
        $content = $this->stripDangerousElements($content);
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = $this->stripDisallowedTags($content, self::ALLOWED_TAGS_PLAIN);
        $content = $this->stripDangerousAttributes($content);

        return trim($content);
    }

    /**
     * Escape content for safe display in HTML attributes
     */
    public function escapeAttribute(string $content): string
    {
        return htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape content for safe display in HTML text
     */
    public function escapeText(string $content): string
    {
        return htmlspecialchars($content, ENT_NOQUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Remove script tags and other dangerous elements
     */
    private function stripDangerousElements(string $content): string
    {
        // Remove script tags completely
        $content = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $content) ?? $content;

        // Remove style tags
        $content = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/i', '', $content) ?? $content;

        // Remove iframe tags
        $content = preg_replace('/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/i', '', $content) ?? $content;

        // Remove embed tags
        $content = preg_replace('/<embed\b[^>]*>/i', '', $content) ?? $content;

        // Remove object tags
        $content = preg_replace('/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/i', '', $content) ?? $content;

        return $content;
    }

    /**
     * Strip disallowed HTML tags while keeping content
     *
     * @param array<string> $allowedTags
     */
    private function stripDisallowedTags(string $content, array $allowedTags): string
    {
        $tagPattern = '/<[\/\!]*?[^<>]*?>/';

        $result = preg_replace_callback($tagPattern, function ($matches) use ($allowedTags) {
            $tag = $matches[0];
            // Extract the tag name (e.g., 'a' from '<a href="...">')
            if (preg_match('/^<[\/\!]?([a-z0-9]+)/i', $tag, $tagMatches)) {
                $tagName = strtolower($tagMatches[1]);
                
                // Check if tag is in allowed list
                if (in_array($tagName, $allowedTags, true)) {
                    return $tag;
                }
            }

            return ''; // Remove disallowed tag
        }, $content);

        return $result ?? $content;
    }

    /**
     * Remove dangerous attributes from tags
     */
    private function stripDangerousAttributes(string $content): string
    {
        // 1. Clean up href attributes in links FIRST
        // This prevents 'javascript:alert(1)' from becoming 'alert(1)' before we can catch it
        $content = preg_replace_callback('/<a\s+href\s*=\s*["\']?([^"\'\>\s]+)["\']?/i', function ($matches) {
            $url = $matches[1];

            // Remove dangerous protocols
            if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
                return '<a href="#"';
            }

            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
        }, $content) ?? $content;

        // 2. Remove all event handlers (onclick, onload, etc.)
        $content = preg_replace('/\s*on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $content) ?? $content;

        // 3. Remove general protocol mentions that might be outside of href (e.g. in src or other places)
        $content = preg_replace('/javascript:/i', '', $content) ?? $content;
        $content = preg_replace('/data:text\/html/i', '', $content) ?? $content;
        $content = preg_replace('/vbscript:/i', '', $content) ?? $content;

        return $content;
    }

    /**
     * Clean up excessive whitespace while preserving intentional formatting
     */
    private function cleanWhitespace(string $content): string
    {
        // Remove multiple consecutive spaces (but not in <pre>)
        if (!strpos($content, '<pre')) {
            $content = preg_replace('/  +/', ' ', $content);
        }

        // Remove empty tags
        $content = (string) preg_replace('/<([a-z][a-z0-9]*)\s*><\/\1>/i', '', (string) $content);

        // Trim each line
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", array_filter($lines));

        return $content;
    }
}
