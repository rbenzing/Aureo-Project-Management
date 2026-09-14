<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpResponse::class)]
final class HttpResponseTest extends TestCase
{
    public function testJsonCarriesStatusHeadersAndEncodedBody(): void
    {
        $response = HttpResponse::json(['a' => 1], 201);

        $this->assertSame(201, $response->status());
        $this->assertSame('application/json', $response->headers()['Content-Type']);
        $this->assertSame('{"a":1}', $response->body());
    }

    public function testJsonDefaultsTo200(): void
    {
        $this->assertSame(200, HttpResponse::json([])->status());
    }

    /**
     * json() sets only Content-Type. Cache-Control/Expires and the JSON
     * encoding flags are per-caller policy (Response adds no-cache headers,
     * ApiResponse doesn't; ApiResponse passes JSON_THROW_ON_ERROR, Response
     * doesn't) - see ResponseTest/ApiResponseTest for those, not here.
     */
    public function testJsonSetsOnlyContentTypeByDefault(): void
    {
        $this->assertSame(['Content-Type' => 'application/json'], HttpResponse::json([])->headers());
    }

    /** Default flags preserve the JSON_UNESCAPED_* behaviour Response::json() used. */
    public function testJsonLeavesUnicodeAndSlashesUnescapedByDefault(): void
    {
        $response = HttpResponse::json(['url' => 'https://a/b', 'name' => 'café']);

        $this->assertStringContainsString('https://a/b', $response->body());
        $this->assertStringContainsString('café', $response->body());
    }

    /** A caller-supplied flags argument overrides the default. */
    public function testJsonHonoursACallerSuppliedFlags(): void
    {
        $response = HttpResponse::json(['url' => 'https://a/b'], 200, 0);

        $this->assertStringContainsString('https:\/\/a\/b', $response->body());
    }

    public function testRedirectCarriesLocationAndDefault302(): void
    {
        $response = HttpResponse::redirect('/login');

        $this->assertSame(302, $response->status());
        $this->assertSame('/login', $response->headers()['Location']);
        $this->assertSame('', $response->body());
    }

    public function testRedirectAcceptsAnExplicitStatus(): void
    {
        $this->assertSame(301, HttpResponse::redirect('/x', 301)->status());
    }

    public function testTextCarriesPlainContentType(): void
    {
        $response = HttpResponse::text('hello', 200);

        $this->assertSame('text/plain', $response->headers()['Content-Type']);
        $this->assertSame('hello', $response->body());
    }

    public function testHtmlCarriesHtmlContentType(): void
    {
        $response = HttpResponse::html('<p>hi</p>');

        $this->assertSame('text/html', $response->headers()['Content-Type']);
        $this->assertSame('<p>hi</p>', $response->body());
    }

    public function testNoContentIs204WithEmptyBody(): void
    {
        $response = HttpResponse::noContent();

        $this->assertSame(204, $response->status());
        $this->assertSame('', $response->body());
    }

    public function testWithHeaderReturnsACopyAndLeavesTheOriginalUnchanged(): void
    {
        $original = HttpResponse::json([]);
        $copy = $original->withHeader('Location', '/created/1');

        $this->assertSame('/created/1', $copy->headers()['Location']);
        $this->assertArrayNotHasKey('Location', $original->headers());
        $this->assertNotSame($original, $copy);
    }

    public function testWithHeaderOverwritesAnExistingHeader(): void
    {
        $response = HttpResponse::json([])->withHeader('Content-Type', 'application/problem+json');

        $this->assertSame('application/problem+json', $response->headers()['Content-Type']);
    }
}
