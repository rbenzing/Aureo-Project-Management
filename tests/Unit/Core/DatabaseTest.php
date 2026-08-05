<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use App\Core\ConfigLoader;
use App\Core\Database;
use App\Services\SecurityService;
use Error;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tests\Unit\Core\Support\ConfigBuiltinToggles;

require_once __DIR__ . '/Support/ConfigBuiltinToggles.php';
require_once __DIR__ . '/Support/ConfigBuiltinOverrides.php';

/**
 * No test here ever lets Database::connect() run: every test either supplies
 * credentials that fail validation before a connection would be attempted,
 * or injects a mocked PDO directly into the private $pdo property via
 * reflection so getConnection() short-circuits past connect() entirely.
 * SecurityService is likewise replaced (via its own singleton) so the
 * PDOException-handling branch of executeQuery() never has to reach a real
 * database either.
 */
#[CoversClass(Database::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(SecurityService::class)]
final class DatabaseTest extends TestCase
{
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['DB_HOST', 'DB_NAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET', 'APP_DEBUG'] as $key) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['DB_HOST'] = '127.0.0.1:3306';
        $_ENV['DB_NAME'] = 'pms_test';
        $_ENV['DB_USERNAME'] = 'root';
        $_ENV['DB_PASSWORD'] = '';
        $_ENV['DB_CHARSET'] = 'utf8mb4';
        $_ENV['APP_DEBUG'] = 'true'; // not production -> password not required

        ConfigBuiltinToggles::reset();
        $this->resetConfigState();
        $this->resetDatabaseState();
        $this->seedSecurityService(null);
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        ConfigBuiltinToggles::reset();
        $this->resetConfigState();
        $this->resetDatabaseState();
        $this->seedSecurityService(null);

        parent::tearDown();
    }

    private function resetConfigState(): void
    {
        $ref = new ReflectionClass(Config::class);

        $configProp = $ref->getProperty('config');
        $configProp->setValue(null, []);

        $initProp = $ref->getProperty('isInitialized');
        $initProp->setValue(null, false);
    }

    private function resetDatabaseState(): void
    {
        $ref = new ReflectionClass(Database::class);

        $instanceProp = $ref->getProperty('instance');
        $instanceProp->setValue(null, null);

        $logProp = $ref->getProperty('queryLog');
        $logProp->setValue(null, []);

        $logFlagProp = $ref->getProperty('logQueries');
        $logFlagProp->setValue(null, false);
    }

    private function seedSecurityService(?SecurityService $service): void
    {
        $ref = new ReflectionClass(SecurityService::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $service);
    }

    private function injectPdo(Database $db, PDO $pdo): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('pdo');
        $prop->setValue($db, $pdo);
    }

    /**
     * @return mixed
     */
    private function getPrivate(object $object, string $name)
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($name);

        return $prop->getValue($object);
    }

    /**
     * @return mixed
     */
    private function callPrivate(object $object, string $method, array $args = [])
    {
        $ref = new ReflectionClass($object);
        $m = $ref->getMethod($method);

        return $m->invokeArgs($object, $args);
    }

    // --- constructor / credential validation ---

    public function testConstructorAcceptsExplicitCredentials(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);

        $credentials = $this->getPrivate($db, 'credentials');

        $this->assertSame('foo', $credentials['dbname']);
        $this->assertSame('bar', $credentials['username']);
    }

    public function testConstructorThrowsWhenDbnameMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required database credential: dbname');

        new Database(['username' => 'bar']);
    }

    public function testConstructorThrowsWhenUsernameMissing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required database credential: username');

        new Database(['dbname' => 'foo']);
    }

    public function testConstructorRequiresPasswordInProduction(): void
    {
        $_ENV['APP_DEBUG'] = 'false';
        $this->resetConfigState();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required database credential: password');

        new Database(['dbname' => 'foo', 'username' => 'bar']);
    }

    public function testConstructorAllowsMissingPasswordOutsideProduction(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);

        $this->assertInstanceOf(Database::class, $db);
    }

    public function testConstructorLoadsConfigurationFromEnvironmentWhenNoCredentialsGiven(): void
    {
        $db = new Database();

        $credentials = $this->getPrivate($db, 'credentials');

        $this->assertSame('127.0.0.1:3306', $credentials['host']);
        $this->assertSame('pms_test', $credentials['dbname']);
        $this->assertSame('root', $credentials['username']);
        $this->assertSame('utf8mb4', $credentials['charset']);
    }

    public function testConstructorWrapsLoadConfigurationFailure(): void
    {
        unset($_ENV['DB_NAME']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to load database configuration: Missing required database credential: dbname'
        );

        new Database();
    }

    public function testSetDefaultOptionsIncludesCorePdoAttributes(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $options = $this->getPrivate($db, 'options');

        $this->assertSame(PDO::ERRMODE_EXCEPTION, $options[PDO::ATTR_ERRMODE]);
        $this->assertSame(PDO::FETCH_ASSOC, $options[PDO::ATTR_DEFAULT_FETCH_MODE]);
        $this->assertFalse($options[PDO::ATTR_EMULATE_PREPARES]);
        $this->assertFalse($options[PDO::ATTR_PERSISTENT]);
    }

    public function testSetDefaultOptionsUsesPdoMysqlConstantWhenClassAvailable(): void
    {
        // This environment ships ext-pdo_mysql, so \Pdo\Mysql really exists
        // and setDefaultOptions() takes its first branch.
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $options = $this->getPrivate($db, 'options');

        $this->assertArrayHasKey(\Pdo\Mysql::ATTR_INIT_COMMAND, $options);
    }

    /**
     * PDO::MYSQL_ATTR_INIT_COMMAND itself is deprecated as of PHP 8.5 (in
     * favour of Pdo\Mysql::ATTR_INIT_COMMAND) — the very thing that makes
     * this elseif branch exist. Reaching it here necessarily triggers that
     * deprecation on 8.5+ runtimes, which is exactly the legacy path being
     * verified, not an accident to silence globally.
     */
    #[IgnoreDeprecations]
    public function testSetDefaultOptionsFallsBackToPdoConstantWhenPdoMysqlClassMissing(): void
    {
        ConfigBuiltinToggles::$forcePdoMysqlClassMissing = true;

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $options = $this->getPrivate($db, 'options');

        // Pdo\Mysql::ATTR_INIT_COMMAND and PDO::MYSQL_ATTR_INIT_COMMAND share
        // the same underlying attribute id, so the meaningful assertion here
        // is that the elseif branch still populated the option at all (the
        // key's presence, not its numeric identity, is what distinguishes
        // this from the "class missing entirely" case).
        $this->assertArrayHasKey(PDO::MYSQL_ATTR_INIT_COMMAND, $options);
        $this->assertCount(5, $options);
    }

    // --- singleton / factory ---

    public function testGetInstanceReturnsSameSingleton(): void
    {
        $a = Database::getInstance();
        $b = Database::getInstance();

        $this->assertSame($a, $b);
    }

    public function testCreateReturnsFreshInstanceNotSharedWithSingleton(): void
    {
        $a = Database::getInstance();
        $b = Database::create();

        $this->assertNotSame($a, $b);
    }

    public function testWakeupThrows(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot unserialize singleton');

        $db->__wakeup();
    }

    public function testCloningFromOutsideTheClassIsBlocked(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);

        // __clone() is private specifically to make the singleton uncloneable;
        // PHP must refuse the call before ever entering the method body.
        $this->expectException(Error::class);

        clone $db;
    }

    public function testCloneHookIsANoOp(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);

        // Reflection bypasses the visibility guard exercised above so the
        // (intentionally empty) hook body itself can be verified directly.
        $result = (new ReflectionMethod($db, '__clone'))->invoke($db);

        $this->assertNull($result);
    }

    // --- getConnection() / close() / __destruct() via an injected double ---

    public function testGetConnectionReturnsInjectedPdoWithoutReconnecting(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $pdo = $this->createMock(PDO::class);
        $this->injectPdo($db, $pdo);

        $this->assertSame($pdo, $db->getConnection());
    }

    public function testCloseClearsConnection(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $this->createMock(PDO::class));

        $db->close();

        $this->assertNull($this->getPrivate($db, 'pdo'));
    }

    public function testDestructClearsConnection(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $this->createMock(PDO::class));

        $db->__destruct();

        $this->assertNull($this->getPrivate($db, 'pdo'));
    }

    // --- executeQuery() ---

    public function testExecuteQueryBindsSanitizedParamNamesAndReturnsStatement(): void
    {
        $boundParams = [];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('bindValue')->willReturnCallback(function (string $param, $value) use (&$boundParams) {
            $boundParams[$param] = $value;

            return true;
        });
        $stmt->expects($this->once())->method('setFetchMode')->with(PDO::FETCH_ASSOC);
        $stmt->expects($this->once())->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM x WHERE id = :id AND name = :name')
            ->willReturn($stmt);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $result = $db->executeQuery(
            'SELECT * FROM x WHERE id = :id AND name = :name',
            [':id' => 1, 'name' => 'Ann']
        );

        $this->assertSame($stmt, $result);
        $this->assertSame([':id' => 1, ':name' => 'Ann'], $boundParams);
    }

    public function testExecuteQueryThrowsWhenExecuteReturnsFalse(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('setFetchMode')->willReturn(true);
        $stmt->method('execute')->willReturn(false);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Query execution failed');

        $db->executeQuery('SELECT 1');
    }

    public function testExecuteQueryWrapsPdoExceptionUsingSecurityService(): void
    {
        $securityMock = $this->createMock(SecurityService::class);
        $securityMock->method('getSafeErrorMessage')->willReturn('irrelevant, always overwritten');
        $this->seedSecurityService($securityMock);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('syntax error'));

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database query failed');

        $db->executeQuery('BAD SQL');
    }

    public function testExecuteQueryFallsBackWhenSecurityServiceItselfThrows(): void
    {
        $securityMock = $this->createMock(SecurityService::class);
        $securityMock->method('getSafeErrorMessage')->willThrowException(new RuntimeException('security down'));
        $this->seedSecurityService($securityMock);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('syntax error'));

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database query failed');

        $db->executeQuery('BAD SQL');
    }

    public function testExecuteQueryLogsWhenLoggingEnabled(): void
    {
        Database::enableQueryLog(true);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('setFetchMode')->willReturn(true);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $db->executeQuery('SELECT 1', [':id' => 5]);

        $log = Database::getQueryLog();

        $this->assertCount(1, $log);
        $this->assertSame('SELECT 1', $log[0]['query']);
        $this->assertArrayHasKey('execution_time', $log[0]);
        $this->assertArrayHasKey('time', $log[0]);

        Database::enableQueryLog(false);
    }

    // --- executeInsertUpdate() ---

    public function testExecuteInsertUpdateReturnsSuccessFlag(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->with(['name' => 'Ann'])->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->assertTrue($db->executeInsertUpdate('UPDATE x SET name = :name', ['name' => 'Ann']));
    }

    public function testExecuteInsertUpdateWrapsPdoException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(new PDOException('duplicate entry'));

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insert/Update execution failed: duplicate entry');

        $db->executeInsertUpdate('INSERT INTO x (name) VALUES (:name)', ['name' => 'Ann']);
    }

    public function testExecuteInsertUpdateLogsWhenLoggingEnabled(): void
    {
        Database::enableQueryLog(true);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $db->executeInsertUpdate('UPDATE x SET name = :name', ['name' => 'Ann']);

        $log = Database::getQueryLog();

        $this->assertCount(1, $log);
        $this->assertSame('UPDATE x SET name = :name', $log[0]['query']);

        Database::enableQueryLog(false);
    }

    // --- lastInsertId() / transactions ---

    public function testLastInsertIdCastsToInt(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('lastInsertId')->willReturn('42');

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->assertSame(42, $db->lastInsertId());
    }

    public function testBeginTransactionDelegatesToPdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('beginTransaction')->willReturn(true);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->assertTrue($db->beginTransaction());
    }

    public function testCommitDelegatesToPdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('commit')->willReturn(true);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->assertTrue($db->commit());
    }

    public function testRollBackDelegatesToPdo(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('rollBack')->willReturn(true);

        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);
        $this->injectPdo($db, $pdo);

        $this->assertTrue($db->rollBack());
    }

    // --- sanitizeParams() ---

    public function testSanitizeParamsRedactsSensitiveKeysOnly(): void
    {
        $db = new Database(['dbname' => 'foo', 'username' => 'bar']);

        $result = $this->callPrivate($db, 'sanitizeParams', [[
            ':password' => 'secret',
            ':reset_password_token' => 'tok',
            ':api_key' => 'k',
            ':username' => 'ann',
        ]]);

        $this->assertSame('[REDACTED]', $result[':password']);
        $this->assertSame('[REDACTED]', $result[':reset_password_token']);
        $this->assertSame('[REDACTED]', $result[':api_key']);
        $this->assertSame('ann', $result[':username']);
    }
}
