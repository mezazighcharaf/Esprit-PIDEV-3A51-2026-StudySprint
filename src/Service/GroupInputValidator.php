<?php

namespace App\Service;

use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Centralized input validation for groups module
 * Ensures consistent validation rules across controllers and services
 */
class GroupInputValidator
{
    private const DISPOSABLE_DOMAINS = [
        'tempmail.com',
        'guerrillamail.com',
        '10minutemail.com',
        'throwaway.email',
        'mailinator.com',
        'temp-mail.org',
        'yopmail.com',
        'sharklasers.com',
    ];

    private const VALID_ROLES = ['admin', 'moderator', 'member'];
    private const VALID_PRIVACY = ['public', 'private'];
    private const VALID_POST_TYPES = ['text', 'link', 'file'];
    private const VALID_SORT_OPTIONS = ['date', 'likes', 'comments', 'rating'];

    private const MAX_EMAILS_PER_INVITE = 50;
    private const MAX_EMAIL_LENGTH = 255;
    private const MAX_COMMENT_LENGTH = 2000;
    private const MAX_POST_BODY_LENGTH = 10000;

    public function __construct(
        /** @phpstan-ignore-next-line */
        private ValidatorInterface $validator
    ) {}

    /**
     * Validate and sanitize email addresses
     * Returns array with 'valid' and 'invalid' email lists
     *
     * @param string[] $emails
     * @return array{valid: string[], invalid: string[]}
     */
    public function validateEmails(array $emails, int $maxCount = self::MAX_EMAILS_PER_INVITE): array
    {
        if (count($emails) > $maxCount) {
            throw new \InvalidArgumentException(
                sprintf('Cannot invite more than %d users at once', $maxCount)
            );
        }

        $valid = [];
        $invalid = [];

        foreach ($emails as $email) {
            $email = strtolower(trim($email));

            // Skip empty
            if (empty($email)) {
                continue;
            }

            // Check length
            if (strlen($email) > self::MAX_EMAIL_LENGTH) {
                $invalid[] = $email;
                continue;
            }

            // Validate format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;
                continue;
            }

            // Check for disposable email domains
            if ($this->isDisposableEmail($email)) {
                $invalid[] = $email;
                continue;
            }

            $valid[] = $email;
        }

        return [
            'valid' => array_unique($valid),
            'invalid' => array_unique($invalid),
        ];
    }

    /**
     * Validate role string
     *
     * @throws \InvalidArgumentException
     */
    public function validateRole(string $role): void
    {
        if (!in_array($role, self::VALID_ROLES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid role: %s. Valid roles: %s', $role, implode(', ', self::VALID_ROLES))
            );
        }
    }

    /**
     * Validate privacy setting
     *
     * @throws \InvalidArgumentException
     */
    public function validatePrivacy(string $privacy): void
    {
        if (!in_array($privacy, self::VALID_PRIVACY, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid privacy: %s. Valid options: %s', $privacy, implode(', ', self::VALID_PRIVACY))
            );
        }
    }

    /**
     * Validate post type
     *
     * @throws \InvalidArgumentException
     */
    public function validatePostType(string $postType): void
    {
        if (!in_array($postType, self::VALID_POST_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid post type: %s. Valid types: %s', $postType, implode(', ', self::VALID_POST_TYPES))
            );
        }
    }

    /**
     * Validate sort option
     *
     * @throws \InvalidArgumentException
     */
    public function validateSortOption(string $sort): string
    {
        if (!in_array($sort, self::VALID_SORT_OPTIONS, true)) {
            return 'date'; // Default to date if invalid
        }
        return $sort;
    }

    /**
     * Validate comment body length
     *
     * @throws \InvalidArgumentException
     */
    public function validateCommentBody(string $body): void
    {
        $body = trim($body);

        if (empty($body)) {
            throw new \InvalidArgumentException('Le commentaire ne peut pas être vide');
        }

        if (mb_strlen($body) > self::MAX_COMMENT_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Le commentaire ne peut pas dépasser %d caractères', self::MAX_COMMENT_LENGTH)
            );
        }
    }

    /**
     * Validate post body length
     *
     * @throws \InvalidArgumentException
     */
    public function validatePostBody(string $body): void
    {
        $body = trim($body);

        if (empty($body)) {
            throw new \InvalidArgumentException('Le contenu du post ne peut pas être vide');
        }

        if (mb_strlen($body) > self::MAX_POST_BODY_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Le contenu ne peut pas dépasser %d caractères', self::MAX_POST_BODY_LENGTH)
            );
        }
    }

    /**
     * Validate positive integer ID
     */
    public function isValidId(mixed $id): bool
    {
        return is_numeric($id) && (int) $id > 0;
    }

    /**
     * Validate and sanitize invitation code
     */
    public function validateInvitationCode(string $code): void
    {
        if (empty($code)) {
            throw new \InvalidArgumentException('Code d\'invitation requis');
        }

        // Code should be INV-XXXXXXXX format (INV- + 8 hex chars)
        if (!preg_match('/^INV-[A-F0-9]{8}$/i', $code)) {
            throw new \InvalidArgumentException('Code d\'invitation invalide');
        }
    }

    /**
     * Check if email belongs to disposable email provider
     */
    private function isDisposableEmail(string $email): bool
    {
        $atPos = strrchr($email, '@');
        $domain = $atPos ? strtolower(substr($atPos, 1)) : '';
        return in_array($domain, self::DISPOSABLE_DOMAINS, true);
    }

    /**
     * Get all constants for reference
     */
    /**
     * @return array<string, mixed>
     */
    public static function getConstants(): array
    {
        return [
            'VALID_ROLES' => self::VALID_ROLES,
            'VALID_PRIVACY' => self::VALID_PRIVACY,
            'VALID_POST_TYPES' => self::VALID_POST_TYPES,
            'VALID_SORT_OPTIONS' => self::VALID_SORT_OPTIONS,
            'MAX_EMAILS_PER_INVITE' => self::MAX_EMAILS_PER_INVITE,
            'MAX_COMMENT_LENGTH' => self::MAX_COMMENT_LENGTH,
            'MAX_POST_BODY_LENGTH' => self::MAX_POST_BODY_LENGTH,
        ];
    }
}
