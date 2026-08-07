<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\PreflightService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PreflightService::class)]
final class PreflightServiceTest extends TestCase
{
    /**
     * A service wired so every check passes. Individual tests override one
     * dependency at a time, which keeps each assertion about exactly one check.
     */
    private function healthy(
        string $phpVersion = '8.2.0',
        ?callable $extensionLoaded = null,
        ?callable $isWritable = null,
        ?callable $pathExists = null,
        string $sessionSavePath = '/tmp'
    ): PreflightService {
        return new PreflightService(
            $phpVersion,
            $extensionLoaded ?? static fn (string $e): bool => true,
            $isWritable ?? static fn (string $p): bool => true,
            $pathExists ?? static fn (string $p): bool => true,
            $sessionSavePath
        );
    }

    /** @param list<array{id:string,severity:string}> $checks */
    private function severityOf(array $checks, string $id): string
    {
        foreach ($checks as $check) {
            if ($check['id'] === $id) {
                return $check['severity'];
            }
        }

        self::fail("No check with id '{$id}' was produced. Got: " . implode(', ', array_column($checks, 'id')));
    }

    public function testAHealthyEnvironmentProducesNoFailures(): void
    {
        $checks = $this->healthy()->run('/app', '/app/public');

        $this->assertFalse(PreflightService::hasFailures($checks));
    }

    public function testEveryCheckCarriesLabelDetailAndRemedy(): void
    {
        foreach ($this->healthy()->run('/app', '/app/public') as $check) {
            $this->assertArrayHasKey('label', $check);
            $this->assertArrayHasKey('detail', $check);
            $this->assertArrayHasKey('remedy', $check);
            $this->assertNotSame('', $check['label']);
        }
    }

    public function testPhpBelowTheFloorFails(): void
    {
        $checks = $this->healthy(phpVersion: '8.1.29')->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'php_version'));
        $this->assertTrue(PreflightService::hasFailures($checks));
    }

    public function testPhpAtTheFloorPasses(): void
    {
        $checks = $this->healthy(phpVersion: '8.2.0')->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_PASS, $this->severityOf($checks, 'php_version'));
    }

    /**
     * PHP_VERSION carries suffixes such as "8.2.4-1ubuntu2.1" on distro builds.
     * version_compare handles them, but only if we do not pre-trim.
     */
    public function testDistroVersionSuffixesStillCompareCorrectly(): void
    {
        $checks = $this->healthy(phpVersion: '8.2.4-1ubuntu2.1')->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_PASS, $this->severityOf($checks, 'php_version'));
    }

    public function testMissingRequiredExtensionFails(): void
    {
        $checks = $this->healthy(
            extensionLoaded: static fn (string $e): bool => $e !== 'pdo_mysql'
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'ext_pdo_mysql'));
    }

    /**
     * openssl is only needed for SMTP over TLS, which an installation can live
     * without. It must warn rather than block.
     */
    public function testMissingOpensslWarnsButDoesNotBlock(): void
    {
        $checks = $this->healthy(
            extensionLoaded: static fn (string $e): bool => $e !== 'openssl'
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_WARN, $this->severityOf($checks, 'ext_openssl'));
        $this->assertFalse(PreflightService::hasFailures($checks));
    }

    public function testUnwritableLogDirectoryFails(): void
    {
        $checks = $this->healthy(
            isWritable: static fn (string $p): bool => !str_ends_with($p, '/log')
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'writable_log'));
    }

    public function testUnwritableCacheDirectoryFails(): void
    {
        $checks = $this->healthy(
            isWritable: static fn (string $p): bool => !str_ends_with($p, '/var/cache')
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'writable_cache'));
    }

    /**
     * A missing directory that sits under a writable parent is fine - the
     * installer creates it. Only an unwritable parent is fatal.
     */
    public function testMissingLogDirectoryUnderAWritableParentPasses(): void
    {
        $checks = $this->healthy(
            isWritable: static fn (string $p): bool => $p === '/app',
            pathExists: static fn (string $p): bool => !str_ends_with($p, '/log')
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_PASS, $this->severityOf($checks, 'writable_log'));
    }

    public function testMissingVendorAutoloadFails(): void
    {
        $checks = $this->healthy(
            pathExists: static fn (string $p): bool => !str_ends_with($p, '/vendor/autoload.php')
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'vendor'));
    }

    /**
     * Shared hosting has no Node, so the archive ships compiled CSS. If it is
     * absent the site renders unstyled, which reads as "broken" - block early.
     */
    public function testMissingCompiledStylesheetFails(): void
    {
        $checks = $this->healthy(
            pathExists: static fn (string $p): bool => !str_ends_with($p, '/public/assets/css/styles.css')
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'assets'));
    }

    /**
     * Nothing is writable at all, which is the only way to be sure every
     * candidate was rejected. Filtering the mock on the substring "config"
     * does not work: under the recommended layout the first candidate is
     * dirname('/app/public') . '/aureo-config.php', so its directory is plain
     * '/app' - the check would pass on candidate one and never reach
     * '/app/config'.
     */
    public function testNoWritableConfigTargetFails(): void
    {
        $checks = $this->healthy(
            isWritable: static fn (string $p): bool => false
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'config_target'));
    }

    /**
     * The preferred candidate sits above the document root, so an app root
     * that is writable satisfies the check even when config/ is not.
     */
    public function testAWritableDirectoryAboveTheDocumentRootSatisfiesTheCheck(): void
    {
        $checks = $this->healthy(
            isWritable: static fn (string $p): bool => $p === '/app'
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_PASS, $this->severityOf($checks, 'config_target'));
    }

    public function testUnwritableSessionPathFails(): void
    {
        $checks = $this->healthy(
            isWritable: static fn (string $p): bool => $p !== '/var/lib/php/sessions',
            sessionSavePath: '/var/lib/php/sessions'
        )->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_FAIL, $this->severityOf($checks, 'session_path'));
    }

    /**
     * An empty session.save_path means PHP's built-in default, which we cannot
     * inspect. Warn, never block - most hosts are in exactly this state.
     */
    public function testEmptySessionPathWarns(): void
    {
        $checks = $this->healthy(sessionSavePath: '')->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_WARN, $this->severityOf($checks, 'session_path'));
    }

    public function testLayoutCheckReportsTheRecommendedLayout(): void
    {
        $checks = $this->healthy()->run('/app', '/app/public');

        $this->assertSame(PreflightService::SEVERITY_PASS, $this->severityOf($checks, 'layout'));
        foreach ($checks as $check) {
            if ($check['id'] === 'layout') {
                $this->assertStringContainsString('public/', $check['detail']);
            }
        }
    }

    /**
     * The drop-in layout works, but it relies on .htaccess or web.config being
     * honoured - so it is a warning, not a clean pass.
     */
    public function testLayoutCheckWarnsForTheDropInLayout(): void
    {
        $checks = $this->healthy()->run('/app', '/app');

        $this->assertSame(PreflightService::SEVERITY_WARN, $this->severityOf($checks, 'layout'));
    }

    public function testLayoutCheckWarnsWhenTheDocumentRootIsUnknown(): void
    {
        $checks = $this->healthy()->run('/app', null);

        $this->assertSame(PreflightService::SEVERITY_WARN, $this->severityOf($checks, 'layout'));
    }

    /**
     * Windows document roots arrive with backslashes; comparing them naively
     * against a forward-slashed app root reports every Windows install as a
     * subdirectory mount.
     */
    public function testWindowsSeparatorsInTheDocumentRootAreNormalised(): void
    {
        $checks = $this->healthy()->run('C:/app', 'C:\\app\\public');

        $this->assertSame(PreflightService::SEVERITY_PASS, $this->severityOf($checks, 'layout'));
    }

    public function testTrailingSlashesOnTheDocumentRootAreIgnored(): void
    {
        $checks = $this->healthy()->run('/app', '/app/public/');

        $this->assertSame(PreflightService::SEVERITY_PASS, $this->severityOf($checks, 'layout'));
    }

    public function testHasFailuresIgnoresWarnings(): void
    {
        $this->assertFalse(PreflightService::hasFailures([
            ['id' => 'a', 'label' => 'a', 'severity' => PreflightService::SEVERITY_PASS, 'detail' => '', 'remedy' => ''],
            ['id' => 'b', 'label' => 'b', 'severity' => PreflightService::SEVERITY_WARN, 'detail' => '', 'remedy' => ''],
        ]));
    }

    public function testHasFailuresDetectsAFailure(): void
    {
        $this->assertTrue(PreflightService::hasFailures([
            ['id' => 'a', 'label' => 'a', 'severity' => PreflightService::SEVERITY_FAIL, 'detail' => '', 'remedy' => ''],
        ]));
    }
}
