<?php

namespace App\Tests\Service;

use App\Service\GroupInputValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GroupInputValidatorTest extends TestCase
{
    private GroupInputValidator $validator;

    protected function setUp(): void
    {
        $mockValidator = $this->createMock(ValidatorInterface::class);
        $this->validator = new GroupInputValidator($mockValidator);
    }

    public function testValidateEmailsSuccess(): void
    {
        $emails = ['test@example.com', '  USER@DOMAIN.COM  ', 'duplicate@test.com', 'duplicate@test.com'];
        $result = $this->validator->validateEmails($emails);

        $this->assertCount(3, $result['valid']);
        $this->assertContains('test@example.com', $result['valid']);
        $this->assertContains('user@domain.com', $result['valid']);
        $this->assertContains('duplicate@test.com', $result['valid']);
        $this->assertEmpty($result['invalid']);
    }

    public function testValidateEmailsFiltersInvalid(): void
    {
        $emails = ['invalid-email', 'disposable@yopmail.com', 'too-long-' . str_repeat('a', 250) . '@test.com'];
        $result = $this->validator->validateEmails($emails);

        $this->assertEmpty($result['valid']);
        $this->assertCount(3, $result['invalid']);
    }

    public function testValidateEmailsThrowsOnTooMany(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot invite more than 50 users at once');

        $emails = array_fill(0, 51, 'test@example.com');
        $this->validator->validateEmails($emails);
    }

    public function testValidateRoleSuccess(): void
    {
        $this->validator->validateRole('admin');
        $this->validator->validateRole('member');
        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateRoleFailure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateRole('superadmin');
    }

    public function testValidateInvitationCode(): void
    {
        $this->validator->validateInvitationCode('INV-ABC12345');
        $this->assertTrue(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateInvitationCode('INVALID-CODE');
    }
}
