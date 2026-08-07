<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ExposureProbe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Services\Support\ExposureProbeBuiltinToggles as Toggles;

require_once __DIR__ . '/Support/ExposureProbeBuiltinOverrides.php';

#[CoversClass(ExposureProbe::class)]
final class ExposureProbeTest extends TestCase
{
    protected function setUp(): void
    {
        Toggles::reset();
    }

    protected function tearDown(): void
    {
        Toggles::reset();
    }

    /** @param array<string,?int> $statuses keyed by full URL */
    private function probe(array $statuses, int $default = 403): ExposureProbe
    {
        return new ExposureProbe(
            static fn (string $url): ?int => array_key_exists($url, $statuses) ? $statuses[$url] : $default
        );
    }

    public function testEveryPathIsProbedRelativeToTheBaseUrl(): void
    {
        $requested = [];

        $probe = new ExposureProbe(static function (string $url) use (&$requested): ?int {
            $requested[] = $url;

            return 403;
        });

        $probe->run('http://example.test');

        $expected = array_map(static fn (string $p): string => 'http://example.test' . $p, ExposureProbe::PATHS);
        $this->assertSame($expected, $requested);
    }

    public function testAllDeniedMeansVerifiedAndNothingExposed(): void
    {
        $result = $this->probe([], 403)->run('http://example.test');

        $this->assertTrue($result['verified']);
        $this->assertSame([], $result['exposed']);
        $this->assertSame([], $result['unreachable']);
        $this->assertSame(ExposureProbe::PATHS, $result['safe']);
    }

    public function testNotFoundCountsAsSafe(): void
    {
        $result = $this->probe([], 404)->run('http://example.test');

        $this->assertTrue($result['verified']);
        $this->assertSame([], $result['exposed']);
    }

    public function testATwoHundredMarksThePathExposed(): void
    {
        $result = $this->probe(['http://example.test/.env' => 200])->run('http://example.test');

        $this->assertSame(['/.env'], $result['exposed']);
        $this->assertTrue($result['verified']);
        $this->assertNotContains('/.env', $result['safe']);
    }

    /**
     * A 301/302 to a login page is a redirect away from the file, not a
     * disclosure. Treating it as exposure would block installation on every
     * host with a canonical-host redirect.
     */
    public function testRedirectsCountAsSafe(): void
    {
        $result = $this->probe(['http://example.test/.env' => 302])->run('http://example.test');

        $this->assertSame([], $result['exposed']);
    }

    /**
     * A 500 means the request reached PHP, so the file was not served as text.
     * Not a disclosure, but not a demonstrated denial either - report it as
     * unreachable so the operator has to acknowledge it.
     */
    public function testServerErrorsAreReportedAsUnreachable(): void
    {
        $result = $this->probe(['http://example.test/.env' => 500])->run('http://example.test');

        $this->assertSame([], $result['exposed']);
        $this->assertSame(['/.env'], $result['unreachable']);
        $this->assertFalse($result['verified']);
    }

    public function testATransportFailureMarksThePathUnreachableAndUnverified(): void
    {
        $result = $this->probe(['http://example.test/.git/config' => null])->run('http://example.test');

        $this->assertSame(['/.git/config'], $result['unreachable']);
        $this->assertFalse($result['verified']);
        $this->assertSame([], $result['exposed']);
    }

    /**
     * Hosts that block loopback HTTP fail every probe. That must read as "could
     * not verify", never as "verified safe".
     */
    public function testLoopbackBlockedEverywhereIsNeverReportedAsVerified(): void
    {
        $result = (new ExposureProbe(static fn (string $url): ?int => null))->run('http://example.test');

        $this->assertFalse($result['verified']);
        $this->assertSame(ExposureProbe::PATHS, $result['unreachable']);
        $this->assertSame([], $result['exposed']);
    }

    /**
     * Exposure is decided independently of reachability: one readable file
     * blocks the install even if the rest of the probe was inconclusive.
     */
    public function testExposureIsReportedEvenWhenOtherProbesFail(): void
    {
        $result = $this->probe([
            'http://example.test/.env' => 200,
            'http://example.test/.git/config' => null,
        ], 403)->run('http://example.test');

        $this->assertSame(['/.env'], $result['exposed']);
        $this->assertSame(['/.git/config'], $result['unreachable']);
        $this->assertFalse($result['verified']);
    }

    public function testTrailingSlashOnTheBaseUrlDoesNotProduceADoubleSlash(): void
    {
        $requested = [];

        $probe = new ExposureProbe(static function (string $url) use (&$requested): ?int {
            $requested[] = $url;

            return 403;
        });

        $probe->run('http://example.test/');

        $this->assertSame('http://example.test/.env', $requested[0]);
    }

    public function testBaseUrlIsBuiltFromTheServerArray(): void
    {
        $url = ExposureProbe::baseUrlFromGlobals(
            ['HTTP_HOST' => 'example.test', 'HTTPS' => 'on'],
            ''
        );

        $this->assertSame('https://example.test', $url);
    }

    public function testBaseUrlIncludesTheMountPoint(): void
    {
        $url = ExposureProbe::baseUrlFromGlobals(['HTTP_HOST' => 'example.test'], '/aureo');

        $this->assertSame('http://example.test/aureo', $url);
    }

    public function testBaseUrlUsesHttpWhenHttpsIsOff(): void
    {
        $url = ExposureProbe::baseUrlFromGlobals(['HTTP_HOST' => 'example.test', 'HTTPS' => 'off'], '');

        $this->assertSame('http://example.test', $url);
    }

    public function testBaseUrlAllowsAnExplicitPort(): void
    {
        $url = ExposureProbe::baseUrlFromGlobals(['HTTP_HOST' => 'example.test:8080'], '');

        $this->assertSame('http://example.test:8080', $url);
    }

    /**
     * HTTP_HOST is attacker-controlled. Anything that is not a plain host or
     * host:port must be refused, or the probe becomes an SSRF primitive that
     * runs on an unauthenticated route.
     */
    public function testBaseUrlRejectsAHostileHostHeader(): void
    {
        $this->assertNull(ExposureProbe::baseUrlFromGlobals(['HTTP_HOST' => 'evil.test/@internal'], ''));
        $this->assertNull(ExposureProbe::baseUrlFromGlobals(['HTTP_HOST' => 'evil.test:80@internal'], ''));
        $this->assertNull(ExposureProbe::baseUrlFromGlobals(['HTTP_HOST' => "example.test\r\nX: y"], ''));
        $this->assertNull(ExposureProbe::baseUrlFromGlobals(['HTTP_HOST' => 'example.test:notaport'], ''));
    }

    public function testBaseUrlIsNullWhenThereIsNoHostHeader(): void
    {
        $this->assertNull(ExposureProbe::baseUrlFromGlobals([], ''));
    }

    /**
     * The transports are only reachable through the zero-argument constructor,
     * which is how public/index.php and bin/preflight.php build the probe.
     * Everything above injects a fetcher, so without these the real request
     * code would ship unexecuted.
     */
    public function testTheCurlTransportReturnsTheResponseStatus(): void
    {
        Toggles::$curlExecResult = '';
        Toggles::$curlStatus = 403;

        $result = (new ExposureProbe())->run('http://example.test');

        $this->assertSame(ExposureProbe::PATHS, $result['safe']);
        $this->assertTrue($result['verified']);
    }

    public function testTheCurlTransportReportsAFailedRequestAsUnreachable(): void
    {
        Toggles::$curlExecResult = false;

        $result = (new ExposureProbe())->run('http://example.test');

        $this->assertFalse($result['verified']);
        $this->assertSame(ExposureProbe::PATHS, $result['unreachable']);
    }

    public function testTheCurlTransportTreatsAZeroStatusAsUnreachable(): void
    {
        Toggles::$curlExecResult = '';
        Toggles::$curlStatus = 0;

        $this->assertFalse((new ExposureProbe())->run('http://example.test')['verified']);
    }

    public function testTheCurlTransportTreatsAnInitFailureAsUnreachable(): void
    {
        Toggles::$curlInitFails = true;

        $this->assertFalse((new ExposureProbe())->run('http://example.test')['verified']);
    }

    public function testTheStreamTransportIsUsedWhenCurlIsUnavailable(): void
    {
        Toggles::$forceCurlMissing = true;
        Toggles::$streamHeaders = ['HTTP/1.1 403 Forbidden'];

        $result = (new ExposureProbe())->run('http://example.test');

        $this->assertTrue($result['verified']);
        $this->assertSame([], $result['exposed']);
    }

    public function testTheStreamTransportReportsAFailedRequestAsUnreachable(): void
    {
        Toggles::$forceCurlMissing = true;
        Toggles::$streamHeaders = false;

        $this->assertFalse((new ExposureProbe())->run('http://example.test')['verified']);
    }

    public function testTheStreamTransportTreatsAnEmptyHeaderListAsUnreachable(): void
    {
        Toggles::$forceCurlMissing = true;
        Toggles::$streamHeaders = [];

        $this->assertFalse((new ExposureProbe())->run('http://example.test')['verified']);
    }

    public function testTheStreamTransportDetectsAnExposedFile(): void
    {
        Toggles::$forceCurlMissing = true;
        Toggles::$streamHeaders = ['HTTP/1.1 200 OK'];

        $this->assertSame(ExposureProbe::PATHS, (new ExposureProbe())->run('http://example.test')['exposed']);
    }

    public function testParseStatusLineReadsTheCode(): void
    {
        $this->assertSame(403, ExposureProbe::parseStatusLine('HTTP/1.1 403 Forbidden'));
        $this->assertSame(200, ExposureProbe::parseStatusLine('HTTP/2 200'));
    }

    public function testParseStatusLineRejectsAnythingThatIsNotAStatusLine(): void
    {
        $this->assertNull(ExposureProbe::parseStatusLine(''));
        $this->assertNull(ExposureProbe::parseStatusLine('garbage'));
        $this->assertNull(ExposureProbe::parseStatusLine('HTTP/1.1 forbidden'));
    }
}
