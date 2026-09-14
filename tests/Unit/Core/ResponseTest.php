<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\HttpResponse;
use App\Core\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Response::class)]
#[UsesClass(HttpResponse::class)]
final class ResponseTest extends TestCase
{
    public function testJsonReturnsAnHttpResponse(): void
    {
        $response = Response::json(['a' => 1], 201);

        $this->assertSame(201, $response->status());
        $this->assertSame('{"a":1}', $response->body());
    }

    public function testSuccessWrapsDataInTheSuccessEnvelope(): void
    {
        $response = Response::success(['id' => 7], 'Saved');

        $this->assertSame(200, $response->status());
        $this->assertSame(
            ['success' => true, 'message' => 'Saved', 'data' => ['id' => 7]],
            json_decode($response->body(), true)
        );
    }

    public function testErrorUsesTheErrorEnvelopeAndStatus(): void
    {
        $response = Response::error('Nope', 422);

        $this->assertSame(422, $response->status());
        $this->assertSame(['success' => false, 'error' => 'Nope'], json_decode($response->body(), true));
    }

    public function testErrorIncludesDetailsOnlyWhenPresent(): void
    {
        $withDetails = json_decode(Response::error('Bad', 400, ['f' => 'required'])->body(), true);
        $without = json_decode(Response::error('Bad')->body(), true);

        $this->assertSame(['f' => 'required'], $withDetails['errors']);
        $this->assertArrayNotHasKey('errors', $without);
    }

    public function testErrorDefaultsTo400(): void
    {
        $this->assertSame(400, Response::error('Bad')->status());
    }

    public function testRedirectCarriesTheLocation(): void
    {
        $response = Response::redirect('/dashboard');

        $this->assertSame(302, $response->status());
        $this->assertSame('/dashboard', $response->headers()['Location']);
    }

    public function testTextAndHtmlCarryTheirContentTypes(): void
    {
        $this->assertSame('text/plain', Response::text('hi')->headers()['Content-Type']);
        $this->assertSame('text/html', Response::html('<p>hi</p>')->headers()['Content-Type']);
    }
}
