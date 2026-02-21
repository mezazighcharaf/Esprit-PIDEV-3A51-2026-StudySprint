<?php

namespace App\Twig;

use App\Service\AvatarService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension for avatar-related filters
 * 
 * Registers filters:
 * - initials: Generate initials from a name
 * - avatar_color: Get avatar color class name
 * - avatar_class: Get full avatar CSS class
 */
class AvatarExtension extends AbstractExtension
{
    public function __construct(private AvatarService $avatarService)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('initials', [$this, 'getInitials']),
            new TwigFilter('avatar_color', [$this, 'getAvatarColor']),
            new TwigFilter('avatar_class', [$this, 'getAvatarClass']),
        ];
    }

    /**
     * Get initials from a name string
     * 
     * Usage in templates:
     * {{ user.name|initials }}  // "John Doe" → "JD"
     */
    public function getInitials(?string $name): string
    {
        if (!$name) {
            return '';
        }

        return $this->avatarService->getInitials($name);
    }

    /**
     * Get avatar color class name
     * 
     * Usage in templates:
     * <div style="background-color: var(--color-{{ user.name|avatar_color }})">
     *   {{ user.name|initials }}
     * </div>
     */
    public function getAvatarColor(?string $name): string
    {
        if (!$name) {
            return 'primary';
        }

        return $this->avatarService->getAvatarColor($name);
    }

    /**
     * Get full avatar CSS class string
     * 
     * Usage in templates:
     * <div class="{{ user.name|avatar_class('md') }}">
     *   {{ user.name|initials }}
     * </div>
     */
    public function getAvatarClass(?string $name, string $size = 'md'): string
    {
        if (!$name) {
            return "avatar avatar-{$size}";
        }

        return $this->avatarService->getAvatarClass($name, $size);
    }
}
