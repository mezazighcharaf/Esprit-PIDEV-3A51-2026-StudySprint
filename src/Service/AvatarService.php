<?php

namespace App\Service;

/**
 * Avatar service for consistent avatar handling across the application
 * Generates initials and colors for user avatars
 */
class AvatarService
{
    /**
     * Get 2-letter initials from a name
     * Examples:
     * - "John Doe" → "JD"
     * - "John" → "JO"
     * - "J" → "JJ"
     */
    public function getInitials(string $name): string
    {
        if (empty(trim($name))) {
            return 'XX';
        }

        // Split by space and filter empty parts
        $parts = array_filter(
            array_map('trim', explode(' ', trim($name))),
            fn($part) => !empty($part)
        );

        if (count($parts) >= 2) {
            // Use first letter of first and last name
            $first = strtoupper(substr($parts[0], 0, 1));
            $last = strtoupper(substr($parts[count($parts) - 1], 0, 1));
            return $first . $last;
        }

        if (count($parts) === 1) {
            // Use first two letters
            $name = $parts[0];
            if (strlen($name) >= 2) {
                return strtoupper(substr($name, 0, 2));
            }

            // Repeat single letter
            return strtoupper(str_repeat($name[0], 2));
        }

        return 'XX';
    }

    /**
     * Generate consistent background color for avatar based on name
     * Uses CRC32 hash for deterministic color assignment
     *
     * @return string Color class name: 'primary', 'secondary', 'success', 'warning', 'error', 'info'
     */
    public function getAvatarColor(string $name): string
    {
        $colors = ['primary', 'secondary', 'success', 'warning', 'error', 'info'];

        if (empty(trim($name))) {
            return $colors[0];
        }

        $hash = abs(crc32(strtolower(trim($name))));
        return $colors[$hash % count($colors)];
    }

    /**
     * Generate avatar CSS class
     */
    public function getAvatarClass(string $name, string $size = 'md'): string
    {
        $color = $this->getAvatarColor($name);
        return "avatar avatar-{$size} avatar-{$color}";
    }

    /**
     * Generate placeholder avatar URL using UI Avatars API
     * Useful for profile pictures without uploading
     */
    public function getPlaceholderUrl(string $name, int $size = 40, ?string $backgroundColor = null): string
    {
        $initials = $this->getInitials($name);

        // Map color names to hex codes
        $colorMap = [
            'primary' => '3B82F6',
            'secondary' => '8B5CF6',
            'success' => '10B981',
            'warning' => 'F59E0B',
            'error' => 'EF4444',
            'info' => '06B6D4',
        ];

        $bgColor = $backgroundColor ?? $this->getAvatarColor($name);
        $hexColor = $colorMap[$bgColor] ?? 'F3F4F6';

        return sprintf(
            'https://ui-avatars.com/api/?name=%s&size=%d&background=%s&color=fff&bold=true',
            urlencode($initials),
            $size,
            $hexColor
        );
    }
}
