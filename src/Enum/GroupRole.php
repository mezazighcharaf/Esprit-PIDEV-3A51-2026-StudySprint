<?php

namespace App\Enum;

/**
 * Enum representing member roles within a study group.
 * Provides role hierarchy and permission helpers.
 */
enum GroupRole: string
{
    case ADMIN = 'admin';
    case MODERATOR = 'moderator';
    case MEMBER = 'member';

    /**
     * Get the role hierarchy level (higher = more permissions)
     */
    public function getLevel(): int
    {
        return match($this) {
            self::ADMIN => 3,
            self::MODERATOR => 2,
            self::MEMBER => 1,
        };
    }

    /**
     * Check if this role can manage group settings
     */
    public function canEditGroup(): bool
    {
        return in_array($this, [self::ADMIN, self::MODERATOR], true);
    }

    /**
     * Check if this role can delete the group
     */
    public function canDeleteGroup(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Check if this role can invite members
     */
    public function canInviteMembers(): bool
    {
        return in_array($this, [self::ADMIN, self::MODERATOR], true);
    }

    /**
     * Check if this role can manage members (change roles, remove)
     */
    public function canManageMembers(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Check if this role can remove regular members
     */
    public function canRemoveMembers(): bool
    {
        return in_array($this, [self::ADMIN, self::MODERATOR], true);
    }

    /**
     * Check if this role can delete any post
     */
    public function canDeleteAnyPost(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Check if this role can delete any comment
     */
    public function canDeleteAnyComment(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Check if this role is higher than another role
     */
    public function isHigherThan(self $other): bool
    {
        return $this->getLevel() > $other->getLevel();
    }

    /**
     * Check if this role is at least as high as another role
     */
    public function isAtLeast(self $other): bool
    {
        return $this->getLevel() >= $other->getLevel();
    }

    /**
     * Get the display label in French
     */
    public function getLabel(): string
    {
        return match($this) {
            self::ADMIN => 'Administrateur',
            self::MODERATOR => 'Modérateur',
            self::MEMBER => 'Membre',
        };
    }

    /**
     * Get the short label for badges
     */
    public function getShortLabel(): string
    {
        return match($this) {
            self::ADMIN => 'Admin',
            self::MODERATOR => 'Mod',
            self::MEMBER => 'Membre',
        };
    }

    /**
     * Get CSS class for badge styling
     */
    public function getBadgeClass(): string
    {
        return match($this) {
            self::ADMIN => 'badge-role-admin',
            self::MODERATOR => 'badge-role-moderator',
            self::MEMBER => 'badge-role-member',
        };
    }

    /**
     * Create from string value, returning null if invalid
     */
    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom($value);
    }

    /**
     * Get all roles as choices for forms
     */
    public static function getChoices(): array
    {
        return [
            'Membre' => self::MEMBER->value,
            'Modérateur' => self::MODERATOR->value,
            'Administrateur' => self::ADMIN->value,
        ];
    }

    /**
     * Get roles that can be assigned by invitation
     */
    public static function getInvitableRoles(): array
    {
        return [
            'Membre' => self::MEMBER->value,
            'Modérateur' => self::MODERATOR->value,
        ];
    }
}
