<?php

namespace App\Enum;

/**
 * Class representing member roles within a study group.
 * Refactored from Enum to Class for PHP 8.0 compatibility.
 */
class GroupRole
{
    public const ADMIN = 'admin';
    public const MODERATOR = 'moderator';
    public const MEMBER = 'member';

    /**
     * Create from string value, returning null if invalid
     */
    public static function tryFromString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return in_array($value, [self::ADMIN, self::MODERATOR, self::MEMBER], true) ? $value : null;
    }

    /**
     * Get the role hierarchy level (higher = more permissions)
     */
    public static function getLevel(string $role): int
    {
        return match ($role) {
            self::ADMIN => 3,
            self::MODERATOR => 2,
            self::MEMBER => 1,
            default => 0,
        };
    }

    /**
     * Check if this role can manage group settings
     */
    public static function canEditGroup(string $role): bool
    {
        return in_array($role, [self::ADMIN, self::MODERATOR], true);
    }

    /**
     * Check if this role can delete the group
     */
    public static function canDeleteGroup(string $role): bool
    {
        return $role === self::ADMIN;
    }

    /**
     * Check if this role can invite members
     */
    public static function canInviteMembers(string $role): bool
    {
        return in_array($role, [self::ADMIN, self::MODERATOR], true);
    }

    /**
     * Check if this role can manage members (change roles, remove)
     */
    public static function canManageMembers(string $role): bool
    {
        return $role === self::ADMIN;
    }

    /**
     * Check if this role can remove regular members
     */
    public static function canRemoveMembers(string $role): bool
    {
        return in_array($role, [self::ADMIN, self::MODERATOR], true);
    }

    /**
     * Check if this role can delete any post
     */
    public static function canDeleteAnyPost(string $role): bool
    {
        return $role === self::ADMIN;
    }

    /**
     * Check if this role can delete any comment
     */
    public static function canDeleteAnyComment(string $role): bool
    {
        return $role === self::ADMIN;
    }

    /**
     * Check if a role is higher than another role
     */
    public static function isHigherThan(string $role, string $other): bool
    {
        return self::getLevel($role) > self::getLevel($other);
    }

    /**
     * Check if a role is at least as high as another role
     */
    public static function isAtLeast(string $role, string $other): bool
    {
        return self::getLevel($role) >= self::getLevel($other);
    }

    /**
     * Get the display label in French
     */
    public static function getLabel(string $role): string
    {
        return match ($role) {
            self::ADMIN => 'Administrateur',
            self::MODERATOR => 'Modérateur',
            self::MEMBER => 'Membre',
            default => $role,
        };
    }

    /**
     * Get CSS class for badge styling
     */
    public static function getBadgeClass(string $role): string
    {
        return match ($role) {
            self::ADMIN => 'badge-role-admin',
            self::MODERATOR => 'badge-role-moderator',
            self::MEMBER => 'badge-role-member',
            default => 'badge-secondary',
        };
    }

    /**
     * Get all roles as choices for forms
     */
    public static function getChoices(): array
    {
        return [
            'Membre' => self::MEMBER,
            'Modérateur' => self::MODERATOR,
            'Administrateur' => self::ADMIN,
        ];
    }

    /**
     * Get roles that can be assigned by invitation
     */
    public static function getInvitableRoles(): array
    {
        return [
            'Membre' => self::MEMBER,
            'Modérateur' => self::MODERATOR,
        ];
    }
}
