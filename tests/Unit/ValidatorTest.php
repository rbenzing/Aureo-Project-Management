<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Utils\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(Database::class)]
final class ValidatorTest extends TestCase
{
    public function testRequiredFieldValidation(): void
    {
        $v = new Validator(['name' => ''], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());

        $v2 = new Validator(['name' => null], ['name' => 'required']);
        $this->assertTrue($v2->fails());

        $v3 = new Validator(['name' => 'John Doe'], ['name' => 'required']);
        $this->assertFalse($v3->fails());
    }

    public function testEmailValidation(): void
    {
        $v = new Validator(['email' => 'invalid-email'], ['email' => 'email']);
        $this->assertTrue($v->fails());

        $v2 = new Validator(['email' => 'user@example.com'], ['email' => 'email']);
        $this->assertFalse($v2->fails());

        $v3 = new Validator(['email' => 'user.name+tag@sub.example.com'], ['email' => 'email']);
        $this->assertFalse($v3->fails());
    }

    public function testMinLengthValidation(): void
    {
        $v = new Validator(['password' => '123'], ['password' => 'min:6']);
        $this->assertTrue($v->fails());

        $v2 = new Validator(['password' => '123456'], ['password' => 'min:6']);
        $this->assertFalse($v2->fails());

        $v3 = new Validator(['password' => '12345678'], ['password' => 'min:6']);
        $this->assertFalse($v3->fails());
    }

    public function testMaxLengthValidation(): void
    {
        $v = new Validator(['username' => 'thisusernameiswaytoolong'], ['username' => 'max:10']);
        $this->assertTrue($v->fails());

        $v2 = new Validator(['username' => '1234567890'], ['username' => 'max:10']);
        $this->assertFalse($v2->fails());

        $v3 = new Validator(['username' => 'john'], ['username' => 'max:10']);
        $this->assertFalse($v3->fails());
    }

    public function testIntegerValidation(): void
    {
        $v = new Validator(['age' => 'abc'], ['age' => 'integer']);
        $this->assertTrue($v->fails());

        $v2 = new Validator(['age' => '25'], ['age' => 'integer']);
        $this->assertFalse($v2->fails());

        $v3 = new Validator(['age' => 25], ['age' => 'integer']);
        $this->assertFalse($v3->fails());
    }

    public function testMultipleValidationRules(): void
    {
        $v = new Validator(
            ['email' => 'user@example.com', 'password' => 'SecurePass123', 'age' => '25'],
            ['email' => 'required|email', 'password' => 'required|min:8|max:50', 'age' => 'required|integer']
        );
        $this->assertFalse($v->fails());

        $v2 = new Validator(
            ['email' => 'invalid-email', 'password' => 'short', 'age' => 'not-a-number'],
            ['email' => 'required|email', 'password' => 'required|min:8|max:50', 'age' => 'required|integer']
        );
        $this->assertTrue($v2->fails());
        $errors = $v2->errors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertArrayHasKey('age', $errors);
    }

    public function testValidationPassesWithValidInputs(): void
    {
        $v = new Validator(
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john.doe@example.com', 'password' => 'SecurePassword123'],
            ['first_name' => 'required|min:2|max:50', 'last_name' => 'required|min:2|max:50', 'email' => 'required|email', 'password' => 'required|min:8']
        );
        $this->assertFalse($v->fails());
        $this->assertEmpty($v->errors());
    }

    public function testGetFirstError(): void
    {
        $v = new Validator(
            ['email' => 'invalid', 'password' => '123'],
            ['email' => 'required|email', 'password' => 'required|min:8']
        );
        $this->assertTrue($v->fails());
        $errors = $v->errors();
        $firstError = reset($errors);
        $this->assertNotEmpty($firstError);
        $this->assertIsString($firstError);
    }

    public function testInputSanitization(): void
    {
        $v = new Validator(['name' => '  John Doe  '], ['name' => 'required']);
        $this->assertFalse($v->fails());
    }

    public function testErrorMessagesAreReadable(): void
    {
        $v = new Validator(['email' => ''], ['email' => 'required']);
        $this->assertTrue($v->fails());
        $errors = $v->errors();
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertIsString($errors['email']);
        $this->assertNotEmpty($errors['email']);
    }
}
