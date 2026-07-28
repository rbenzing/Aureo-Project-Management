<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\AuthorizationException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthorizationException::class)]
#[CoversClass(BusinessRuleException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(ValidationException::class)]
class ExceptionsTest extends TestCase
{
    public function testNotFoundExceptionForModel(): void
    {
        $exception = NotFoundException::forModel('User', 123);

        $this->assertInstanceOf(NotFoundException::class, $exception);
        $this->assertStringContainsString('User', $exception->getMessage());
        $this->assertStringContainsString('123', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testNotFoundExceptionWithStringId(): void
    {
        $exception = NotFoundException::forModel('Project', 'ABC-123');

        $this->assertStringContainsString('Project', $exception->getMessage());
        $this->assertStringContainsString('ABC-123', $exception->getMessage());
    }

    public function testValidationExceptionWithErrors(): void
    {
        $errors = [
            'email' => 'Email is required',
            'password' => 'Password must be at least 8 characters',
        ];

        $exception = ValidationException::withErrors($errors);

        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals(422, $exception->getCode());
        $this->assertStringContainsString('validation', strtolower($exception->getMessage()));
    }

    public function testValidationExceptionGetErrors(): void
    {
        $errors = ['field' => 'error message'];
        $exception = ValidationException::withErrors($errors);

        $this->assertIsArray($exception->getErrors());
        $this->assertArrayHasKey('field', $exception->getErrors());
    }

    public function testAuthorizationExceptionCode(): void
    {
        $exception = new AuthorizationException('You are not authorized');

        $this->assertEquals(403, $exception->getCode());
        $this->assertEquals('You are not authorized', $exception->getMessage());
    }

    public function testAuthorizationExceptionInheritance(): void
    {
        $exception = new AuthorizationException('Forbidden');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testBusinessRuleExceptionCode(): void
    {
        $exception = new BusinessRuleException('Cannot complete closed task');

        $this->assertEquals(400, $exception->getCode());
        $this->assertEquals('Cannot complete closed task', $exception->getMessage());
    }

    public function testBusinessRuleExceptionInheritance(): void
    {
        $exception = new BusinessRuleException('Business rule violated');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testAllExceptionsAreThrowable(): void
    {
        $this->expectException(NotFoundException::class);

        throw NotFoundException::forModel('Test', 1);
    }
}
