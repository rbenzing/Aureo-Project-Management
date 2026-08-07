<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\ConfigLoader;
use App\Core\InstallGate;
use App\Services\InstallerService;
use App\Services\PreflightService;
use App\Utils\PasswordHasher;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(InstallerService::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(InstallGate::class)]
#[UsesClass(PreflightService::class)]
#[UsesClass(PasswordHasher::class)]
final class InstallerServiceTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/aureo-installer-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);

        parent::tearDown();
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }

        rmdir($path);
    }

    private function service(): InstallerService
    {
        return new InstallerService($this->root);
    }

    public function testConfigTargetsPrefersTheDirectoryAboveTheDocumentRoot(): void
    {
        $targets = $this->service()->configTargets('/home/site/public_html');

        $this->assertSame('/home/site/aureo-config.php', $targets[0]);
        $this->assertStringEndsWith('/config/config.php', $targets[1]);
    }

    public function testConfigTargetsFallsBackToTheInTreeLocationWithoutADocumentRoot(): void
    {
        $targets = $this->service()->configTargets(null);

        $this->assertCount(1, $targets);
        $this->assertStringEndsWith('/config/config.php', $targets[0]);
    }

    /**
     * dirname('/') is '/'. Writing the credentials file to the filesystem root
     * is never right, so that candidate must be dropped rather than offered.
     */
    public function testConfigTargetsNeverProposesTheFilesystemRoot(): void
    {
        $targets = $this->service()->configTargets('/');

        $this->assertCount(1, $targets);
        $this->assertStringEndsWith('/config/config.php', $targets[0]);
    }

    public function testFirstWritableTargetPicksTheInTreeLocationWhenTheParentIsNotWritable(): void
    {
        $target = $this->service()->firstWritableTarget('/proc/nonexistent-document-root');

        // Normalised because firstWritableTarget() delegates to
        // PreflightService::configTargets(), which forward-slashes the app
        // root (see PreflightService context note on Windows separators).
        // sys_get_temp_dir() returns a backslashed path on Windows, so
        // $this->root itself is not normalised - only the candidate the
        // service actually returns is.
        $this->assertSame(str_replace('\\', '/', $this->root) . '/config/config.php', $target);
    }

    public function testRenderConfigProducesAFileThatRequireReturnsAsAnArray(): void
    {
        $php = $this->service()->renderConfig(['DB_NAME' => 'aureo', 'DB_PASSWORD' => "it's \"quoted\"\n\$x"]);

        $path = $this->root . '/rendered.php';
        file_put_contents($path, $php);

        $loaded = require $path;

        $this->assertIsArray($loaded);
        $this->assertSame('aureo', $loaded['DB_NAME']);
        $this->assertSame("it's \"quoted\"\n\$x", $loaded['DB_PASSWORD']);
    }

    public function testRenderConfigStartsWithAPhpOpeningTag(): void
    {
        $this->assertStringStartsWith('<?php', $this->service()->renderConfig(['DB_NAME' => 'x']));
    }

    public function testWriteConfigCreatesTheFile(): void
    {
        $path = $this->root . '/config/config.php';
        $this->service()->writeConfig($path, ['DB_NAME' => 'aureo']);

        $this->assertFileExists($path);
        $this->assertSame('aureo', (require $path)['DB_NAME']);
    }

    public function testWritePointerProducesAFileReturningTheTargetPath(): void
    {
        $target = '/home/site/aureo-config.php';
        $this->service()->writePointer($target);

        $pointer = $this->root . '/' . InstallerService::POINTER_FILE;
        $this->assertFileExists($pointer);
        $this->assertSame($target, require $pointer);
    }

    public function testWriteLockCreatesTheLockFile(): void
    {
        $this->service()->writeLock('1.2.0');

        $this->assertFileExists($this->root . '/' . InstallGate::LOCK_FILE);
        $this->assertStringContainsString('1.2.0', (string) file_get_contents($this->root . '/' . InstallGate::LOCK_FILE));
    }

    public function testLockPathIsRelativeToTheApplicationRoot(): void
    {
        $this->assertSame($this->root . '/' . InstallGate::LOCK_FILE, $this->service()->lockPath());
    }

    public function testBuildConfigValuesCoversEveryKeyTheLoaderRequires(): void
    {
        $values = $this->service()->buildConfigValues([
            'db_host' => 'localhost:3306',
            'db_name' => 'aureo',
            'db_user' => 'aureo',
            'db_password' => 'secret',
            'domain' => 'example.com',
            'scheme' => 'https',
            'timezone' => 'UTC',
            'company' => 'Acme',
        ]);

        foreach (ConfigLoader::REQUIRED as $key) {
            $this->assertArrayHasKey($key, $values, "buildConfigValues() omitted required key {$key}");
        }
    }

    public function testBuildConfigValuesCarriesTheOperatorsAnswers(): void
    {
        $values = $this->service()->buildConfigValues([
            'db_host' => 'db.internal:3307',
            'db_name' => 'aureo',
            'db_user' => 'aureo',
            'db_password' => 'secret',
            'domain' => 'example.com',
            'scheme' => 'https',
            'timezone' => 'Europe/Berlin',
            'company' => 'Acme',
        ]);

        $this->assertSame('db.internal:3307', $values['DB_HOST']);
        $this->assertSame('aureo', $values['DB_NAME']);
        $this->assertSame('secret', $values['DB_PASSWORD']);
        $this->assertSame('example.com', $values['APP_DOMAIN']);
        $this->assertSame('https', $values['APP_SCHEME']);
        $this->assertSame('Europe/Berlin', $values['APP_TIMEZONE']);
        $this->assertSame('Acme', $values['APP_COMPANY']);
    }

    /**
     * A production install over plain HTTP would set secure cookies that the
     * browser then refuses to send, and the operator would see login silently
     * failing. Derive it rather than asking.
     */
    public function testSessionSecureFollowsTheScheme(): void
    {
        $answers = [
            'db_host' => 'localhost:3306', 'db_name' => 'a', 'db_user' => 'u', 'db_password' => 'p',
            'domain' => 'example.com', 'timezone' => 'UTC', 'company' => 'Acme',
        ];

        $this->assertSame('true', $this->service()->buildConfigValues($answers + ['scheme' => 'https'])['SESSION_SECURE']);
        $this->assertSame('false', $this->service()->buildConfigValues($answers + ['scheme' => 'http'])['SESSION_SECURE']);
    }

    public function testBuildConfigValuesGeneratesADistinctPepperEachTime(): void
    {
        $answers = [
            'db_host' => 'localhost:3306', 'db_name' => 'a', 'db_user' => 'u', 'db_password' => 'p',
            'domain' => 'example.com', 'scheme' => 'https', 'timezone' => 'UTC', 'company' => 'Acme',
        ];

        $first = $this->service()->buildConfigValues($answers)['PASSWORD_PEPPER'];
        $second = $this->service()->buildConfigValues($answers)['PASSWORD_PEPPER'];

        $this->assertNotSame($first, $second);
        $this->assertSame(64, strlen($first));
    }

    public function testGeneratePepperIsHexAndLongEnough(): void
    {
        $pepper = InstallerService::generatePepper();

        $this->assertSame(64, strlen($pepper));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $pepper);
    }

    public function testSplitHostAndPortHandlesAnExplicitPort(): void
    {
        $this->assertSame(['host' => 'localhost', 'port' => 3307], InstallerService::splitHostAndPort('localhost:3307'));
    }

    public function testSplitHostAndPortDefaultsToTheMysqlPort(): void
    {
        $this->assertSame(['host' => 'localhost', 'port' => 3306], InstallerService::splitHostAndPort('localhost'));
    }

    /**
     * cPanel hands out socket-style hosts and IPv4 literals; neither carries a
     * port, and splitting on the wrong colon would produce port 0.
     */
    public function testSplitHostAndPortHandlesAnIpv4Literal(): void
    {
        $this->assertSame(['host' => '127.0.0.1', 'port' => 3306], InstallerService::splitHostAndPort('127.0.0.1'));
        $this->assertSame(['host' => '127.0.0.1', 'port' => 3306], InstallerService::splitHostAndPort('127.0.0.1:3306'));
    }

    public function testSplitHostAndPortIgnoresANonNumericSuffix(): void
    {
        $this->assertSame(['host' => 'localhost:sock', 'port' => 3306], InstallerService::splitHostAndPort('localhost:sock'));
    }

    public function testHashPasswordProducesAVerifiableHash(): void
    {
        $hash = InstallerService::hashPassword('correct horse battery staple');

        $this->assertTrue(password_verify('correct horse battery staple', $hash));
        $this->assertFalse(password_verify('wrong', $hash));
    }

    /**
     * The canonical migration seeds its admin with PASSWORD_ARGON2ID, which
     * throws a ValueError on any PHP built without libargon2 - i.e. the
     * install path itself was not portable. Both sites now pick an algorithm
     * that is actually present.
     */
    public function testPreferredAlgorithmIsOneThatThisRuntimeSupports(): void
    {
        $algorithm = InstallerService::preferredPasswordAlgorithm();

        $this->assertContains($algorithm, password_algos() + [PASSWORD_DEFAULT]);
    }

    // --- Database-touching methods -----------------------------------------
    //
    // The plan for this class treats connectToServer()/connectToDatabase()/
    // databaseExists()/createDatabase()/runMigrations()/updateAdministrator()
    // as covered later, by InstallControllerTest and the CI smoke job - both
    // outside this task. But this file's own coverage gate runs at the end of
    // THIS task, in isolation, and a ~140-line class left 58% uncovered blows
    // through the ratchet's 0.5-point slack on its own. Two strategies close
    // the gap without waiting for either later piece:
    //
    //   - databaseExists()/createDatabase()/updateAdministrator() take a PDO
    //     directly, so a PDO::class mock exercises them with no real
    //     connection at all (matching DatabaseTest/UserCoverageTest, which
    //     never let a real connection happen either).
    //   - connectToServer()/connectToDatabase()/countUsers()/runMigrations()
    //     open their own PDO internally, so they need a live server. Rather
    //     than depending on the shared fixture database (which, on this
    //     machine, phpunit.xml points at "pms_test" - a database that does
    //     not exist locally, only in CI's provisioned MySQL service), each
    //     test creates a throwaway scratch database against the same
    //     root@127.0.0.1:3306 every other DB-touching test in this suite
    //     already assumes, and drops it afterwards. A server that refuses the
    //     connection entirely is treated as "no local MySQL", same as
    //     Tests\Support\TestCase::requireDatabase().

    private function realCredentials(string $name): array
    {
        return ['host' => '127.0.0.1:3306', 'name' => $name, 'user' => 'root', 'password' => ''];
    }

    /** @return array{host:string,name:string,user:string,password:string}|null */
    private function scratchDatabase(InstallerService $bootstrapper, string $name): ?array
    {
        $credentials = $this->realCredentials($name);

        try {
            $server = $bootstrapper->connectToServer($credentials);
        } catch (Throwable $e) {
            $this->markTestSkipped('No local MySQL server reachable at 127.0.0.1:3306: ' . $e->getMessage());

            return null;
        }

        $bootstrapper->createDatabase($server, $name);

        return $credentials;
    }

    public function testConnectToServerThrowsWhenTheHostRefusesTheConnection(): void
    {
        $this->expectException(\PDOException::class);

        $this->service()->connectToServer($this->unreachableCredentials());
    }

    private function unreachableCredentials(): array
    {
        // Port 1 on the loopback address is refused instantly by the OS
        // whether or not MySQL is installed at all, so this test needs no
        // server and cannot hang on a DNS timeout.
        return ['host' => '127.0.0.1:1', 'name' => 'irrelevant', 'user' => 'root', 'password' => ''];
    }

    public function testCountUsersReturnsNullWhenTheDatabaseIsUnreachable(): void
    {
        $this->assertNull($this->service()->countUsers($this->unreachableCredentials()));
    }

    public function testDatabaseExistsQueriesInformationSchema(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->expects($this->once())->method('execute')->with([':schema_name' => 'aureo']);
        $statement->method('fetchColumn')->willReturn('1');

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('prepare')->willReturn($statement);

        $this->assertTrue($this->service()->databaseExists($pdo, 'aureo'));
    }

    public function testDatabaseExistsReturnsFalseWhenTheSchemaIsAbsent(): void
    {
        $statement = $this->createMock(PDOStatement::class);
        $statement->method('fetchColumn')->willReturn('0');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($statement);

        $this->assertFalse($this->service()->databaseExists($pdo, 'aureo'));
    }

    public function testCreateDatabaseRejectsAnUnsafeName(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('exec');

        $this->expectException(RuntimeException::class);

        $this->service()->createDatabase($pdo, 'aureo`; DROP TABLE users; --');
    }

    public function testCreateDatabaseIssuesCreateDatabaseForASafeName(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('exec')
            ->with($this->stringContains('CREATE DATABASE `aureo_test_db`'));

        $this->service()->createDatabase($pdo, 'aureo_test_db');
    }

    public function testUpdateAdministratorUpdatesRowOneWhenItExists(): void
    {
        $updateStatement = $this->createMock(PDOStatement::class);
        $updateStatement->method('execute')->willReturn(true);
        $updateStatement->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())->method('prepare')->willReturn($updateStatement);
        $pdo->expects($this->never())->method('query');

        $this->service()->updateAdministrator($pdo, 'admin@example.test', 'Ada', 'Lovelace', 'secret');
    }

    /**
     * rowCount() 0 also happens when the submitted values already match what
     * is stored, so the method must confirm row 1 is genuinely absent before
     * it inserts - this exercises that confirmation short-circuiting the
     * insert.
     */
    public function testUpdateAdministratorSkipsInsertWhenRowOneStillExistsDespiteAZeroRowCount(): void
    {
        $updateStatement = $this->createMock(PDOStatement::class);
        $updateStatement->method('execute')->willReturn(true);
        $updateStatement->method('rowCount')->willReturn(0);

        $existsStatement = $this->createMock(PDOStatement::class);
        $existsStatement->method('fetchColumn')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($updateStatement);
        $pdo->expects($this->once())->method('query')->willReturn($existsStatement);

        $this->service()->updateAdministrator($pdo, 'admin@example.test', 'Ada', 'Lovelace', 'secret');
    }

    public function testUpdateAdministratorInsertsWhenRowOneIsGenuinelyAbsent(): void
    {
        $updateStatement = $this->createMock(PDOStatement::class);
        $updateStatement->method('execute')->willReturn(true);
        $updateStatement->method('rowCount')->willReturn(0);

        $existsStatement = $this->createMock(PDOStatement::class);
        $existsStatement->method('fetchColumn')->willReturn(0);

        $insertStatement = $this->createMock(PDOStatement::class);
        $insertStatement->expects($this->once())->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls($updateStatement, $insertStatement);
        $pdo->method('query')->willReturn($existsStatement);

        $this->service()->updateAdministrator($pdo, 'admin@example.test', 'Ada', 'Lovelace', 'secret');
    }

    /**
     * The one test that actually runs the canonical migration end to end,
     * against a throwaway database - which is what proves the Step 5 fix
     * (preferredPasswordAlgorithm() instead of a hardcoded PASSWORD_ARGON2ID)
     * really does let the seed migration run on this runtime. countUsers()
     * and connectToDatabase() ride along for free: there is no cheaper way to
     * get a `users` table with a real row 1 than to actually migrate.
     */
    public function testRunMigrationsAppliesTheSchemaAndUpdateAdministratorUpdatesTheSeededRow(): void
    {
        $bootstrapper = $this->service();
        $name = 'aureo_installer_test_' . bin2hex(random_bytes(4));

        $credentials = $this->scratchDatabase($bootstrapper, $name);
        if ($credentials === null) {
            return;
        }

        try {
            $realService = new InstallerService(\dirname(__DIR__, 3));

            $output = $realService->runMigrations($credentials);
            $this->assertIsString($output);

            // The canonical migration seeds exactly one user: admin@aureo.us.
            $this->assertSame(1, $realService->countUsers($credentials));

            $db = $realService->connectToDatabase($credentials);
            $realService->updateAdministrator($db, 'owner@example.test', 'Ada', 'Lovelace', 'correct horse battery staple');

            $this->assertSame('owner@example.test', $db->query('SELECT `email` FROM `users` WHERE `id` = 1')->fetchColumn());
            $this->assertSame(1, $realService->countUsers($credentials));
        } finally {
            $bootstrapper->connectToServer($credentials)->exec('DROP DATABASE IF EXISTS `' . $name . '`');
        }
    }
}
