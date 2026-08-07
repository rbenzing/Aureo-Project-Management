<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\InstallGate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallGate::class)]
final class InstallGateTest extends TestCase
{
    public function testLockFilePassesThroughEvenOnTheInstallRoute(): void
    {
        $this->assertSame(
            InstallGate::DECISION_PASS_THROUGH,
            InstallGate::decide(true, true, 'install', 0)
        );
    }

    /**
     * A lock beats everything, including an unresolvable configuration. An
     * operator who locked the installation and then broke their config wants a
     * boot error, not an installer that will overwrite what is left.
     */
    public function testLockFileBeatsAnUnresolvableConfiguration(): void
    {
        $this->assertSame(
            InstallGate::DECISION_PASS_THROUGH,
            InstallGate::decide(true, false, 'install', null)
        );
    }

    public function testNoConfigurationRunsTheInstallerOnAnyRoute(): void
    {
        $this->assertSame(InstallGate::DECISION_RUN, InstallGate::decide(false, false, '', null));
        $this->assertSame(InstallGate::DECISION_RUN, InstallGate::decide(false, false, 'dashboard', null));
        $this->assertSame(InstallGate::DECISION_RUN, InstallGate::decide(false, false, 'install', null));
    }

    public function testAWorkingConfigurationPassesThroughOutsideTheInstallRoute(): void
    {
        $this->assertSame(
            InstallGate::DECISION_PASS_THROUGH,
            InstallGate::decide(false, true, 'dashboard', 5)
        );
    }

    /**
     * The takeover case. Every pre-1.2.0 installation has a working config and
     * a populated database and no lock file.
     */
    public function testAPopulatedDatabaseRefusesTheInstallRoute(): void
    {
        $this->assertSame(
            InstallGate::DECISION_REFUSE,
            InstallGate::decide(false, true, 'install', 1)
        );
    }

    /**
     * Config points at a database that has been created but not migrated - a
     * half-finished install. Let the operator finish it.
     */
    public function testAnEmptyDatabaseAllowsTheInstallRoute(): void
    {
        $this->assertSame(InstallGate::DECISION_RUN, InstallGate::decide(false, true, 'install', 0));
    }

    /**
     * Unknown means the users table is absent or the database is unreachable.
     * Neither is evidence of an existing installation.
     */
    public function testAnUnknownUserCountAllowsTheInstallRoute(): void
    {
        $this->assertSame(InstallGate::DECISION_RUN, InstallGate::decide(false, true, 'install', null));
    }

    public function testUserCheckIsOnlyNeededForTheInstallRouteOnAConfiguredSite(): void
    {
        $this->assertTrue(InstallGate::needsUserCheck(false, true, 'install'));

        $this->assertFalse(InstallGate::needsUserCheck(true, true, 'install'), 'locked');
        $this->assertFalse(InstallGate::needsUserCheck(false, false, 'install'), 'no configuration');
        $this->assertFalse(InstallGate::needsUserCheck(false, true, 'dashboard'), 'not the install route');
    }

    public function testTheInstallSegmentConstantIsTheRouteName(): void
    {
        $this->assertSame('install', InstallGate::SEGMENT);
    }
}
