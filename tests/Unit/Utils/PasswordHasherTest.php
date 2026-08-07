<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\PasswordHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordHasher::class)]
final class PasswordHasherTest extends TestCase
{
    public function testHashProducesAVerifiableHash(): void
    {
        $hash = PasswordHasher::hash('correct horse battery staple');

        $this->assertTrue(password_verify('correct horse battery staple', $hash));
    }

    public function testHashRejectsTheWrongPassword(): void
    {
        $this->assertFalse(password_verify('wrong', PasswordHasher::hash('right')));
    }

    public function testTwoHashesOfTheSamePasswordDiffer(): void
    {
        $this->assertNotSame(PasswordHasher::hash('same'), PasswordHasher::hash('same'));
    }

    /**
     * The entire point of the class: never name an algorithm the runtime
     * cannot provide. password_hash() throws a ValueError - not a warning -
     * for an unavailable algorithm, so getting this wrong is a fatal, not a
     * degradation.
     */
    public function testTheChosenAlgorithmIsOneThisRuntimeActuallyProvides(): void
    {
        $this->assertContains(PasswordHasher::algorithm(), password_algos());
    }

    /**
     * Guarded rather than unconditional: on a PHP built without libargon2
     * this assertion would be wrong, and asserting it anyway would make the
     * suite pass vacuously on exactly the hosts this class exists for.
     */
    public function testArgon2idIsPreferredWhenTheRuntimeHasIt(): void
    {
        if (!\in_array(PASSWORD_ARGON2ID, password_algos(), true)) {
            $this->markTestSkipped('This PHP build has no libargon2, so there is no preference to assert.');
        }

        $this->assertSame(PASSWORD_ARGON2ID, PasswordHasher::algorithm());
    }

    public function testTheFallbackAlgorithmIsUsableWhereverArgon2idIsAbsent(): void
    {
        if (\in_array(PASSWORD_ARGON2ID, password_algos(), true)) {
            $this->assertNotSame(PASSWORD_DEFAULT, PasswordHasher::algorithm());
        }

        // Whichever branch this runtime takes, the fallback must be a real
        // algorithm here too - PASSWORD_DEFAULT is guaranteed present by the
        // language, and this pins that guarantee rather than assuming it.
        $this->assertContains(PASSWORD_DEFAULT, password_algos());
    }

    /**
     * A database may hold hashes from both branches - an installation that
     * moves to a host with different extensions, or a restored backup.
     * password_verify() reads the algorithm out of the hash itself, so both
     * must keep working side by side. If this ever fails, changing the
     * algorithm has locked existing users out.
     */
    public function testHashesFromEitherAlgorithmVerifyInterchangeably(): void
    {
        $this->assertTrue(password_verify('secret', password_hash('secret', PASSWORD_DEFAULT)));

        if (\in_array(PASSWORD_ARGON2ID, password_algos(), true)) {
            $this->assertTrue(password_verify('secret', password_hash('secret', PASSWORD_ARGON2ID)));
        }
    }

    public function testAlgorithmIsStableAcrossCalls(): void
    {
        $this->assertSame(PasswordHasher::algorithm(), PasswordHasher::algorithm());
    }
}
