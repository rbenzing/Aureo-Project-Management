<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\RequestPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestPath::class)]
final class RequestPathTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}>
     */
    public static function layoutProvider(): array
    {
        return [
            // name => [requestUri, scriptName, basePath, path, usesPathInfo]
            'docroot is public/' => ['/projects', '/index.php', '', 'projects', false],
            'docroot is app root' => ['/projects', '/index.php', '', 'projects', false],
            'subdirectory install' => ['/aureo/projects', '/aureo/index.php', '/aureo', 'projects', false],
            'no rewrite available' => ['/index.php/projects', '/index.php', '', 'projects', true],
            'no rewrite in subdirectory' => ['/aureo/index.php/projects', '/aureo/index.php', '/aureo', 'projects', true],
            'site root' => ['/', '/index.php', '', '', false],
            'subdirectory root' => ['/aureo/', '/aureo/index.php', '/aureo', '', false],
            'script with no path info' => ['/index.php', '/index.php', '', '', true],
            'query string is discarded' => ['/projects?page=2', '/index.php', '', 'projects', false],
            'nested route' => ['/tasks/view/5', '/index.php', '', 'tasks/view/5', false],
            'windows script name' => ['/projects', '\\index.php', '', 'projects', false],
        ];
    }

    #[DataProvider('layoutProvider')]
    public function testResolvesLayout(
        string $requestUri,
        string $scriptName,
        string $expectedBase,
        string $expectedPath,
        bool $expectedPathInfo
    ): void {
        $resolved = new RequestPath($requestUri, $scriptName);

        $this->assertSame($expectedBase, $resolved->basePath(), 'basePath');
        $this->assertSame($expectedPath, $resolved->path(), 'path');
        $this->assertSame($expectedPathInfo, $resolved->usesPathInfo(), 'usesPathInfo');
    }

    /**
     * A naive str_starts_with would strip the base '/a' from '/abc/projects'
     * and route the request to 'bc/projects'. Prefixes must match on segment
     * boundaries.
     */
    public function testPrefixMatchingRespectsSegmentBoundaries(): void
    {
        $resolved = new RequestPath('/abc/projects', '/a/index.php');

        $this->assertSame('', $resolved->basePath());
        $this->assertSame('abc/projects', $resolved->path());
    }

    public function testSegmentsSplitsOnSlash(): void
    {
        $resolved = new RequestPath('/tasks/view/5', '/index.php');

        $this->assertSame(['tasks', 'view', '5'], $resolved->segments());
    }

    /**
     * The router registers the dashboard as the empty route, and the previous
     * inline segmentation produced [''] for '/'. Preserve that exactly.
     */
    public function testSiteRootProducesASingleEmptySegment(): void
    {
        $resolved = new RequestPath('/', '/index.php');

        $this->assertSame([''], $resolved->segments());
    }

    public function testFromGlobalsReadsServerSuperglobal(): void
    {
        $_SERVER['REQUEST_URI'] = '/aureo/projects';
        $_SERVER['SCRIPT_NAME'] = '/aureo/index.php';

        $resolved = RequestPath::fromGlobals();

        $this->assertSame('/aureo', $resolved->basePath());
        $this->assertSame('projects', $resolved->path());
    }

    public function testFromGlobalsToleratesMissingServerKeys(): void
    {
        unset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']);

        $resolved = RequestPath::fromGlobals();

        $this->assertSame('', $resolved->basePath());
        $this->assertSame('', $resolved->path());
    }

    /**
     * parse_url(PHP_URL_PATH) returns null/false (not '/') for these
     * malformed URIs - verified empirically on this PHP build. Under
     * strict_types, the old inline ltrim($uri, '/') let that reach a
     * TypeError and 500 the request; degrading to the site root is
     * deliberate (see the constructor's guard comment). This guards against
     * that fallback being "tidied" away later: the auth gate still fails
     * closed on the resulting [''] segment, since '' is not in
     * $publicPaths, so this is not an auth bypass.
     */
    public static function malformedUriProvider(): array
    {
        return [
            'double leading slash' => ['//projects'],
            'triple leading slash' => ['///projects'],
        ];
    }

    #[DataProvider('malformedUriProvider')]
    public function testMalformedRequestUriDegradesToSiteRootRatherThanFatalling(string $requestUri): void
    {
        $resolved = new RequestPath($requestUri, '/index.php');

        $this->assertSame('', $resolved->basePath());
        $this->assertSame([''], $resolved->segments());
    }
}
