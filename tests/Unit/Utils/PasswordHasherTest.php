<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\PasswordHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Utils\Support\PasswordHasherBuiltinToggles as Toggles;

require_once __DIR__ . '/Support/PasswordHasherBuiltinOverrides.php';

#[CoversClass(PasswordHasher::class)]
final class PasswordHasherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Toggles::reset();
    }

    protected function tearDown(): void
    {
        Toggles::reset();

        parent::tearDown();
    }

    /**
     * The fallback branch, forced.
     *
     * Which branch algorithm() takes is decided by how PHP was compiled, not
     * by any input, so on a machine with libargon2 - every machine that
     * normally runs this suite - the fallback is unreachable and would ship
     * unexecuted. It is also the branch that only runs on hosts we cannot
     * test on, so leaving it unpinned puts the error somewhere nobody sees it
     * until an install fails.
     */
    public function testTheFallbackIsUsedWhenTheRuntimeLacksArgon2id(): void
    {
        Toggles::$hideArgon2id = true;

        $this->assertSame(PASSWORD_DEFAULT, PasswordHasher::algorithm());
    }

    public function testTheFallbackStillProducesAVerifiableHash(): void
    {
        Toggles::$hideArgon2id = true;

        $hash = PasswordHasher::hash('correct horse battery staple');

        $this->assertTrue(password_verify('correct horse battery staple', $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }

    /**
     * The mixed-database case made concrete: a hash written by the fallback
     * must still verify after the host gains libargon2, and vice versa. If
     * this ever fails, moving a site between hosts locks every user out.
     */
    public function testHashesSurviveTheRuntimeGainingArgon2id(): void
    {
        Toggles::$hideArgon2id = true;
        $fallbackHash = PasswordHasher::hash('secret');

        Toggles::$hideArgon2id = false;

        $this->assertTrue(password_verify('secret', $fallbackHash));
    }

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
