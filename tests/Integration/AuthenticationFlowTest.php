<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

/**
 * Integration tests for the authentication data layer.
 *
 * These exercise the real User model against a real database: token issue /
 * lookup / expiry / clearing, soft-delete filtering, and credential storage.
 *
 * AuthController itself is not driven here — its actions terminate in
 * redirect(): never, so they cannot be invoked in-process without restructuring
 * production code. Everything the controller delegates to IS covered below.
 *
 * Requires a migrated test database; skipped cleanly when none is reachable.
 */
#[CoversClass(User::class)]
#[Group('integration')]
final class AuthenticationFlowTest extends TestCase
{
    private User $userModel;
    private int $testUserId;
    private string $testEmail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->userModel = new User();

        // Unique per test so parallel/repeat runs never collide.
        $this->testEmail = 'authflow_' . bin2hex(random_bytes(6)) . '@example.test';
        $this->testUserId = $this->createTestUser($this->testEmail, 'CorrectHorse9!');
    }

    protected function tearDown(): void
    {
        if ($this->db !== null && isset($this->testUserId)) {
            $this->db->executeInsertUpdate(
                'DELETE FROM users WHERE id = :id',
                [':id' => $this->testUserId]
            );
        }

        parent::tearDown();
    }

    /**
     * Insert a real user row and return its id.
     */
    private function createTestUser(string $email, string $password, int $isDeleted = 0): int
    {
        // The column is password_hash, not password. guid and role_id are both
        // NOT NULL with no default, and role_id carries an FK to roles — role 1
        // ("admin") is seeded by the initial migration alongside the admin user.
        $sql = 'INSERT INTO users
                    (guid, role_id, first_name, last_name, email, password_hash,
                     is_active, is_deleted, created_at, updated_at)
                VALUES
                    (UUID(), :role_id, :first_name, :last_name, :email, :password_hash,
                     :is_active, :is_deleted, NOW(), NOW())';

        $this->db->executeInsertUpdate($sql, [
            ':role_id' => 1,
            ':first_name' => 'Auth',
            ':last_name' => 'Flow',
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            ':is_active' => 1,
            ':is_deleted' => $isDeleted,
        ]);

        return $this->db->lastInsertId();
    }

    // ---------------------------------------------------------------- lookup

    public function testFindByEmailReturnsTheStoredUser(): void
    {
        $user = $this->userModel->findByEmail($this->testEmail);

        $this->assertNotNull($user, 'Seeded user should be findable by email');
        $this->assertSame($this->testUserId, (int) $user->id);
        $this->assertSame($this->testEmail, $user->email);
    }

    public function testFindByEmailReturnsNullForUnknownAddress(): void
    {
        $this->assertNull(
            $this->userModel->findByEmail('definitely-not-registered@example.test')
        );
    }

    public function testFindByEmailIsCaseInsensitivePerMysqlCollation(): void
    {
        // Documents actual behavior: the default utf8mb4_unicode_ci collation makes
        // email lookup case-insensitive, so mixed-case logins resolve to one account.
        $user = $this->userModel->findByEmail(strtoupper($this->testEmail));

        $this->assertNotNull($user);
        $this->assertSame($this->testUserId, (int) $user->id);
    }

    public function testSoftDeletedUsersAreNotReturned(): void
    {
        $deletedEmail = 'deleted_' . bin2hex(random_bytes(6)) . '@example.test';
        $deletedId = $this->createTestUser($deletedEmail, 'Whatever9!', isDeleted: 1);

        try {
            $this->assertNull(
                $this->userModel->findByEmail($deletedEmail),
                'Soft-deleted users must not be authenticatable'
            );
        } finally {
            $this->db->executeInsertUpdate('DELETE FROM users WHERE id = :id', [':id' => $deletedId]);
        }
    }

    // ------------------------------------------------------------ credentials

    public function testStoredPasswordIsArgon2idAndVerifies(): void
    {
        $user = $this->userModel->findByEmail($this->testEmail);

        // getUsersWithDetails() selects u.*, so the hash arrives under the real
        // column name, password_hash.
        $this->assertStringStartsWith('$argon2id$', $user->password_hash);
        $this->assertTrue(password_verify('CorrectHorse9!', $user->password_hash));
        $this->assertFalse(password_verify('WrongPassword1!', $user->password_hash));
    }

    // -------------------------------------------------------- activation flow

    public function testActivationTokenRoundTripsToTheSameUser(): void
    {
        $token = $this->userModel->generateActivationToken($this->testUserId);

        $found = $this->userModel->findByActivationToken($token);

        $this->assertNotNull($found, 'Freshly issued activation token should resolve');
        $this->assertSame($this->testUserId, (int) $found->id);
    }

    public function testUnknownActivationTokenResolvesToNull(): void
    {
        $this->assertNull($this->userModel->findByActivationToken(bin2hex(random_bytes(16))));
    }

    public function testExpiredActivationTokenIsRejected(): void
    {
        $token = $this->userModel->generateActivationToken($this->testUserId);

        // Backdate the expiry past now.
        $this->db->executeInsertUpdate(
            'UPDATE users SET activation_token_expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = :id',
            [':id' => $this->testUserId]
        );

        $this->assertNull(
            $this->userModel->findByActivationToken($token),
            'An expired activation token must not activate an account'
        );
    }

    public function testClearingActivationTokenInvalidatesIt(): void
    {
        $token = $this->userModel->generateActivationToken($this->testUserId);
        $this->assertNotNull($this->userModel->findByActivationToken($token));

        $this->userModel->clearActivationToken($this->testUserId);

        $this->assertNull(
            $this->userModel->findByActivationToken($token),
            'A cleared activation token must not be reusable'
        );
    }

    public function testSuccessiveActivationTokensDiffer(): void
    {
        $first = $this->userModel->generateActivationToken($this->testUserId);
        $second = $this->userModel->generateActivationToken($this->testUserId);

        $this->assertNotSame($first, $second);
        $this->assertNull(
            $this->userModel->findByActivationToken($first),
            'Re-issuing a token must invalidate the previous one'
        );
        $this->assertNotNull($this->userModel->findByActivationToken($second));
    }

    // ---------------------------------------------------- password reset flow

    public function testResetTokenRoundTripsToTheSameUser(): void
    {
        $token = $this->userModel->generatePasswordResetToken($this->testUserId);

        $found = $this->userModel->findByResetToken($token);

        $this->assertNotNull($found);
        $this->assertSame($this->testUserId, (int) $found->id);
    }

    public function testUnknownResetTokenResolvesToNull(): void
    {
        $this->assertNull($this->userModel->findByResetToken(bin2hex(random_bytes(16))));
    }

    public function testExpiredResetTokenIsRejected(): void
    {
        $token = $this->userModel->generatePasswordResetToken($this->testUserId);

        $this->db->executeInsertUpdate(
            'UPDATE users SET reset_password_token_expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE id = :id',
            [':id' => $this->testUserId]
        );

        $this->assertNull(
            $this->userModel->findByResetToken($token),
            'An expired reset token must not permit a password change'
        );
    }

    public function testClearingResetTokenInvalidatesIt(): void
    {
        $token = $this->userModel->generatePasswordResetToken($this->testUserId);
        $this->assertNotNull($this->userModel->findByResetToken($token));

        $this->userModel->clearPasswordResetToken($this->testUserId);

        $this->assertNull(
            $this->userModel->findByResetToken($token),
            'A consumed reset token must not be replayable'
        );
    }

    public function testActivationAndResetTokensAreIndependent(): void
    {
        $activation = $this->userModel->generateActivationToken($this->testUserId);
        $reset = $this->userModel->generatePasswordResetToken($this->testUserId);

        $this->assertNotSame($activation, $reset);
        $this->assertNull(
            $this->userModel->findByResetToken($activation),
            'An activation token must not be usable as a reset token'
        );
        $this->assertNull(
            $this->userModel->findByActivationToken($reset),
            'A reset token must not be usable as an activation token'
        );
    }

    public function testResetTokensAreLongEnoughToResistGuessing(): void
    {
        $token = $this->userModel->generatePasswordResetToken($this->testUserId);

        $this->assertSame(32, strlen($token), 'Expected 16 random bytes hex-encoded');
        $this->assertTrue(ctype_xdigit($token));
    }
}
