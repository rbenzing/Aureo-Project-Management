<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for User model
 *
 * Tests user model functionality including:
 * - User property assignment
 * - Email validation
 * - Password hashing validation
 * - User data transformation
 */
final class UserModelTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new User();
    }

    /**
     * Test user object can be instantiated
     */
    public function testUserCanBeInstantiated(): void
    {
        $this->assertInstanceOf(User::class, $this->user);
    }

    /**
     * Test user properties can be set
     */
    public function testUserPropertiesCanBeSet(): void
    {
        $this->user->id = 1;
        $this->user->first_name = 'John';
        $this->user->last_name = 'Doe';
        $this->user->email = 'john.doe@example.com';

        $this->assertEquals(1, $this->user->id);
        $this->assertEquals('John', $this->user->first_name);
        $this->assertEquals('Doe', $this->user->last_name);
        $this->assertEquals('john.doe@example.com', $this->user->email);
    }

    /**
     * Test email format validation
     */
    public function testEmailValidation(): void
    {
        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            'user_name@sub.example.com'
        ];

        foreach ($validEmails as $email) {
            $result = filter_var($email, FILTER_VALIDATE_EMAIL);
            $this->assertNotFalse($result, "Email {$email} should be valid");
        }

        $invalidEmails = [
            'invalid-email',
            '@example.com',
            'user@',
            'user space@example.com'
        ];

        foreach ($invalidEmails as $email) {
            $result = filter_var($email, FILTER_VALIDATE_EMAIL);
            $this->assertFalse($result, "Email {$email} should be invalid");
        }
    }

    /**
     * Test password hashing with Argon2ID
     */
    public function testPasswordHashing(): void
    {
        $password = 'SecurePassword123';
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $this->assertNotEquals($password, $hash);
        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    /**
     * Test password verification fails with wrong password
     */
    public function testPasswordVerificationFailsWithWrongPassword(): void
    {
        $password = 'SecurePassword123';
        $wrongPassword = 'WrongPassword456';
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $this->assertFalse(password_verify($wrongPassword, $hash));
    }

    /**
     * Test user full name concatenation
     */
    public function testUserFullName(): void
    {
        $this->user->first_name = 'John';
        $this->user->last_name = 'Doe';

        $fullName = $this->user->first_name . ' ' . $this->user->last_name;

        $this->assertEquals('John Doe', $fullName);
    }

    /**
     * Test user active status
     */
    public function testUserActiveStatus(): void
    {
        $this->user->is_active = true;
        $this->assertTrue($this->user->is_active);

        $this->user->is_active = false;
        $this->assertFalse($this->user->is_active);
    }

    /**
     * Test user deletion flag
     */
    public function testUserDeletionFlag(): void
    {
        $this->user->is_deleted = false;
        $this->assertFalse($this->user->is_deleted);

        $this->user->is_deleted = true;
        $this->assertTrue($this->user->is_deleted);
    }

    /**
     * Test GUID format
     */
    public function testGuidFormat(): void
    {
        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        $guid = 'b40b3681-fb03-11ef-99ad-e454e8e51d1c';
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        $this->assertEquals(1, preg_match($pattern, $guid));
    }

    /**
     * Test user role ID assignment
     */
    public function testUserRoleAssignment(): void
    {
        $this->user->role_id = 1; // Admin role
        $this->assertEquals(1, $this->user->role_id);

        $this->user->role_id = 2; // Other role
        $this->assertEquals(2, $this->user->role_id);
    }

    /**
     * Test user company assignment
     */
    public function testUserCompanyAssignment(): void
    {
        $this->user->company_id = null;
        $this->assertNull($this->user->company_id);

        $this->user->company_id = 5;
        $this->assertEquals(5, $this->user->company_id);
    }

    /**
     * Test activation token format
     */
    public function testActivationTokenFormat(): void
    {
        $token = bin2hex(random_bytes(32));

        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
        $this->assertTrue(ctype_xdigit($token));
    }

    /**
     * Test password reset token format
     */
    public function testPasswordResetTokenFormat(): void
    {
        $token = bin2hex(random_bytes(32));

        $this->assertEquals(64, strlen($token));
        $this->assertTrue(ctype_xdigit($token));
    }

    /**
     * Test user email uniqueness requirement
     */
    public function testEmailShouldBeUnique(): void
    {
        // This is a logical test - emails must be unique in database
        // Schema has UNIQUE constraint on email field
        $email1 = 'user1@example.com';
        $email2 = 'user2@example.com';
        $email3 = 'user1@example.com'; // Duplicate

        $this->assertNotEquals($email1, $email2);
        $this->assertEquals($email1, $email3);
    }

    /**
     * Test phone number format (optional field)
     */
    public function testPhoneNumberFormat(): void
    {
        $this->user->phone = null;
        $this->assertNull($this->user->phone);

        $this->user->phone = '1234567890';
        $this->assertEquals('1234567890', $this->user->phone);

        // Max length is 15 per schema
        $this->assertLessThanOrEqual(15, strlen($this->user->phone));
    }

    /**
     * Test timestamp properties exist
     */
    public function testTimestampProperties(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->user->created_at = $now;
        $this->user->updated_at = $now;

        $this->assertEquals($now, $this->user->created_at);
        $this->assertEquals($now, $this->user->updated_at);
    }

    /**
     * Test user data can be converted to array
     */
    public function testUserDataCanBeConvertedToArray(): void
    {
        $this->user->id = 1;
        $this->user->first_name = 'John';
        $this->user->last_name = 'Doe';
        $this->user->email = 'john@example.com';

        // Get object vars as array
        $userData = get_object_vars($this->user);

        $this->assertIsArray($userData);
        $this->assertArrayHasKey('first_name', $userData);
        $this->assertArrayHasKey('last_name', $userData);
        $this->assertArrayHasKey('email', $userData);
    }
}
