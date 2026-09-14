<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ApiResponse;
use App\Core\HttpResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiResponse::class)]
#[UsesClass(HttpResponse::class)]
final class ApiResponseTest extends TestCase
{
    public function testSuccessEnvelope(): void
    {
        $body = json_decode(ApiResponse::success(['id' => 1])->body(), true);

        $this->assertTrue($body['success']);
        $this->assertSame(['id' => 1], $body['data']);
    }

    /**
     * Regression guard: ApiResponse has never sent these headers (unlike
     * Response - see ResponseTest::testJsonSendsNoCacheHeaders). Both classes
     * delegate to HttpResponse::json(), which sets neither by default, so this
     * would only fail if that policy leaked back into the shared value object.
     */
    public function testNeverSendsApiResponseCacheHeaders(): void
    {
        $headers = ApiResponse::success(['id' => 1])->headers();

        $this->assertArrayNotHasKey('Cache-Control', $headers);
        $this->assertArrayNotHasKey('Expires', $headers);
    }

    public function testErrorCarriesMessageAndStatus(): void
    {
        $response = ApiResponse::error('Bad request', 400);

        $this->assertSame(400, $response->status());
        $this->assertFalse(json_decode($response->body(), true)['success']);
    }

    public function testCreatedIs201AndSetsLocationWhenGiven(): void
    {
        $response = ApiResponse::created(['id' => 3], '/tasks/3');

        $this->assertSame(201, $response->status());
        $this->assertSame('/tasks/3', $response->headers()['Location']);
    }

    public function testCreatedOmitsLocationWhenNotGiven(): void
    {
        $this->assertArrayNotHasKey('Location', ApiResponse::created(['id' => 3])->headers());
    }

    public function testNoContentIs204(): void
    {
        $this->assertSame(204, ApiResponse::noContent()->status());
    }

    public function testNotFoundIs404(): void
    {
        $this->assertSame(404, ApiResponse::notFound()->status());
    }

    public function testUnauthorizedIs401AndForbiddenIs403(): void
    {
        $this->assertSame(401, ApiResponse::unauthorized()->status());
        $this->assertSame(403, ApiResponse::forbidden()->status());
    }

    public function testValidationErrorIs422AndCarriesTheErrors(): void
    {
        $response = ApiResponse::validationError(['email' => 'required']);

        $this->assertSame(422, $response->status());
        $this->assertStringContainsString('email', $response->body());
    }

    public function testServerErrorIs500(): void
    {
        $this->assertSame(500, ApiResponse::serverError()->status());
    }

    public function testPaginatedCarriesItemsAndPaginationBlock(): void
    {
        $body = json_decode(ApiResponse::paginated([['id' => 1]], 1, 10, 1)->body(), true);

        $this->assertSame([['id' => 1]], $body['data']);
        $this->assertArrayHasKey('pagination', $body);
    }
}
