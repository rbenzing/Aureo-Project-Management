<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\AuthorizationException;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Covers the named-constructor factory methods and default-argument
 * constructors that tests/Unit/ExceptionsTest.php does not exercise.
 */
#[CoversClass(AuthorizationException::class)]
#[CoversClass(BusinessRuleException::class)]
#[CoversClass(NotFoundException::class)]
#[CoversClass(ValidationException::class)]
class ExceptionFactoryMethodsTest extends TestCase
{
    public function testAuthorizationExceptionDefaultConstructor(): void
    {
        $exception = new AuthorizationException();

        $this->assertSame('You do not have permission to perform this action', $exception->getMessage());
        $this->assertSame(403, $exception->getCode());
    }

    public function testAuthorizationExceptionForPermission(): void
    {
        $exception = AuthorizationException::forPermission('edit_task');

        $this->assertStringContainsString('edit_task', $exception->getMessage());
        $this->assertStringContainsString('permission', $exception->getMessage());
        $this->assertSame(403, $exception->getCode());
    }

    public function testAuthorizationExceptionForAction(): void
    {
        $exception = AuthorizationException::forAction('delete', 'project');

        $this->assertSame('You are not authorized to delete this project', $exception->getMessage());
        $this->assertSame(403, $exception->getCode());
    }

    public function testBusinessRuleExceptionDefaultConstructor(): void
    {
        $exception = new BusinessRuleException();

        $this->assertSame('Business rule violation', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
    }

    public function testBusinessRuleExceptionInvalidStatusTransition(): void
    {
        $exception = BusinessRuleException::invalidStatusTransition('open', 'closed');

        $this->assertSame("Cannot transition from 'open' to 'closed'", $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
    }

    public function testBusinessRuleExceptionDuplicateResource(): void
    {
        $exception = BusinessRuleException::duplicateResource('Project', 'ABC-123');

        $this->assertSame("Project 'ABC-123' already exists", $exception->getMessage());
    }

    public function testBusinessRuleExceptionCircularReference(): void
    {
        $exception = BusinessRuleException::circularReference('Task');

        $this->assertSame('Circular reference detected in Task hierarchy', $exception->getMessage());
    }

    public function testNotFoundExceptionDefaultConstructor(): void
    {
        $exception = new NotFoundException();

        $this->assertSame('Resource not found', $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
    }

    public function testNotFoundExceptionForModel(): void
    {
        $exception = NotFoundException::forModel('Sprint', 55);

        $this->assertSame('Sprint with ID 55 not found', $exception->getMessage());
        $this->assertSame(404, $exception->getCode());
    }

    public function testValidationExceptionDefaultConstructor(): void
    {
        $exception = new ValidationException();

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame(422, $exception->getCode());
        $this->assertSame([], $exception->getErrors());
    }

    public function testValidationExceptionHasErrorReturnsTrueWhenFieldPresent(): void
    {
        $exception = ValidationException::withErrors(['email' => ['Email is invalid']]);

        $this->assertTrue($exception->hasError('email'));
        $this->assertFalse($exception->hasError('password'));
    }

    public function testValidationExceptionGetFieldErrorsReturnsFieldMessages(): void
    {
        $exception = ValidationException::withErrors(['email' => ['Email is invalid', 'Email is required']]);

        $this->assertSame(['Email is invalid', 'Email is required'], $exception->getFieldErrors('email'));
    }

    public function testValidationExceptionGetFieldErrorsReturnsEmptyArrayForUnknownField(): void
    {
        $exception = ValidationException::withErrors(['email' => ['Email is invalid']]);

        $this->assertSame([], $exception->getFieldErrors('nonexistent'));
    }
}
