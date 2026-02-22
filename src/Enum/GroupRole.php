<?php

namespace App\Enum;

enum GroupRole: string
{
    case OWNER     = 'owner';
    case ADMIN     = 'admin';
    case MODERATOR = 'moderator';
    case MEMBER    = 'member';

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }
        return self::tryFrom(strtolower($value));
    }

    public function canEditGroup(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canDeleteGroup(): bool
    {
        return $this === self::OWNER || $this === self::ADMIN;
    }

    public function canManageMembers(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canInviteMembers(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::MODERATOR], true);
    }

    public function canRemoveMembers(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }

    public function canDeleteAnyPost(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::MODERATOR], true);
    }

    public function canDeleteAnyComment(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN, self::MODERATOR], true);
    }

    public function canPost(): bool
    {
        return true; // All members can post
    }
}
