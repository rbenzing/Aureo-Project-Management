<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ConfigLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(ConfigLoader::class)]
final class ConfigLoaderTest extends TestCase
{
    private string $root;

    /** @var array<string, string> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/aureo-config-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/config', 0777, true);

        $this->originalEnv = $_ENV;

        foreach (ConfigLoader::REQUIRED as $key) {
            unset($_ENV[$key], $_SERVER[$key]);

            // phpunit.xml's <php><env> block calls putenv() once at process
            // start (PHPUnit\TextUI\Configuration\PhpHandler::handleEnvVariables()),
            // so clearing only the superglobals above would leave getenv()
            // still returning the suite-wide test DB credentials for the rest
            // of this process - environmentIsComplete() would then never see
            // rung 1 as incomplete and every file-based rung below it would
            // go untested.
            putenv($key);
        }

        unset($_ENV['AUREO_CONFIG'], $_SERVER['AUREO_CONFIG']);
    }

    protected function tearDown(): void
    {
        $_ENV = $this->originalEnv;

        foreach (ConfigLoader::REQUIRED as $key) {
            putenv(isset($this->originalEnv[$key]) ? "{$key}={$this->originalEnv[$key]}" : $key);
        }

        $this->removeTree($this->root);

        parent::tearDown();
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }

        rmdir($path);
    }

    /** @param array<string, string> $values */
    private function writePhpConfig(string $path, array $values): void
    {
        file_put_contents($path, '<?php return ' . var_export($values, true) . ';');
    }

    /** @return array<string, string> */
    private function completeValues(): array
    {
        return [
            'APP_DEBUG' => 'false',
            'DB_HOST' => 'localhost',
            'DB_NAME' => 'aureo_db',
            'DB_USERNAME' => 'aureo',
            'DB_PASSWORD' => 'secret',
        ];
    }

    public function testRealEnvironmentWinsAndReadsNoFile(): void
    {
        foreach ($this->completeValues() as $key => $value) {
            $_ENV[$key] = $value;
        }

        // No config file exists anywhere under $this->root.
        $this->assertSame('environment', ConfigLoader::load($this->root));
    }

    /**
     * PHP's default variables_order (GPCS - no E) never populates $_ENV from
     * the real process environment, so a real container has every required
     * key visible via getenv() while $_ENV stays empty. Every other test in
     * this class populates $_ENV directly, which proves nothing about this
     * case - see the class-level history in task-5-report.md for the live
     * failure this reproduces (empty DB_NAME reaching phinx as `_test`).
     */
    public function testValuesOnlyInGetenvAreHydratedIntoEnv(): void
    {
        // setUp() already cleared $_ENV/$_SERVER/getenv() for every REQUIRED
        // key; populate all five via putenv() only. tearDown() restores or
        // clears putenv() for every REQUIRED key unconditionally, so no
        // local cleanup is needed here.
        foreach ($this->completeValues() as $key => $value) {
            putenv("{$key}={$value}");
        }

        $this->assertSame('environment', ConfigLoader::load($this->root));

        foreach ($this->completeValues() as $key => $value) {
            $this->assertSame($value, $_ENV[$key] ?? null, "Expected \$_ENV['{$key}'] to be hydrated from getenv().");
        }
    }

    public function testExistingEnvironmentValueWinsOverGetenv(): void
    {
        // DB_NAME is already in $_ENV; the other four required keys plus a
        // *conflicting* DB_NAME are visible only via getenv() - same
        // immutable precedence loadPhpFile() already gives $_ENV over a file.
        $_ENV['DB_NAME'] = 'from_env';

        foreach (['DB_NAME' => 'from_getenv'] + $this->completeValues() as $key => $value) {
            putenv("{$key}={$value}");
        }

        $this->assertSame('environment', ConfigLoader::load($this->root));

        $this->assertSame('from_env', $_ENV['DB_NAME']);
    }

    public function testOptionalKeyOnlyInGetenvIsHydrated(): void
    {
        // A container that sets real env vars sets more than the five
        // REQUIRED keys - PASSWORD_PEPPER, SMTP_*, APP_SCHEME, etc. are all
        // read straight from $_ENV elsewhere in the app. Rung 1 has to carry
        // those across too, not just the required set.
        foreach ($this->completeValues() as $key => $value) {
            putenv("{$key}={$value}");
        }
        putenv('PASSWORD_PEPPER=from_getenv_optional');

        try {
            $this->assertSame('environment', ConfigLoader::load($this->root));

            $this->assertSame('from_getenv_optional', $_ENV['PASSWORD_PEPPER'] ?? null);
        } finally {
            // PASSWORD_PEPPER is not in ConfigLoader::REQUIRED, so tearDown()'s
            // generic putenv restore does not know to clear it.
            putenv('PASSWORD_PEPPER');
        }
    }

    public function testExplicitOverridePathIsUsed(): void
    {
        $override = $this->root . '/elsewhere.php';
        $this->writePhpConfig($override, ['DB_NAME' => 'from_override'] + $this->completeValues());
        $_ENV['AUREO_CONFIG'] = $override;

        ConfigLoader::load($this->root);

        $this->assertSame('from_override', $_ENV['DB_NAME']);
    }

    public function testPointerFileRedirectsToAnAboveDocrootConfig(): void
    {
        $secrets = $this->root . '/aureo-config.php';
        $this->writePhpConfig($secrets, ['DB_NAME' => 'from_pointer'] + $this->completeValues());
        file_put_contents(
            $this->root . '/config/config-path.php',
            '<?php return ' . var_export($secrets, true) . ';'
        );

        ConfigLoader::load($this->root);

        $this->assertSame('from_pointer', $_ENV['DB_NAME']);
    }

    public function testInTreeConfigIsUsedWhenNoPointerExists(): void
    {
        $this->writePhpConfig(
            $this->root . '/config/config.php',
            ['DB_NAME' => 'from_in_tree'] + $this->completeValues()
        );

        ConfigLoader::load($this->root);

        $this->assertSame('from_in_tree', $_ENV['DB_NAME']);
    }

    public function testDotEnvRemainsTheDeveloperDefault(): void
    {
        file_put_contents(
            $this->root . '/.env',
            "APP_DEBUG=false\nDB_HOST=localhost\nDB_NAME=from_dotenv\nDB_USERNAME=aureo\nDB_PASSWORD=secret\n"
        );

        ConfigLoader::load($this->root);

        $this->assertSame('from_dotenv', $_ENV['DB_NAME']);
    }

    public function testPointerTakesPrecedenceOverInTreeConfig(): void
    {
        $secrets = $this->root . '/aureo-config.php';
        $this->writePhpConfig($secrets, ['DB_NAME' => 'from_pointer'] + $this->completeValues());
        file_put_contents(
            $this->root . '/config/config-path.php',
            '<?php return ' . var_export($secrets, true) . ';'
        );
        $this->writePhpConfig(
            $this->root . '/config/config.php',
            ['DB_NAME' => 'from_in_tree'] + $this->completeValues()
        );

        ConfigLoader::load($this->root);

        $this->assertSame('from_pointer', $_ENV['DB_NAME']);
    }

    public function testInTreeConfigTakesPrecedenceOverDotEnv(): void
    {
        $this->writePhpConfig(
            $this->root . '/config/config.php',
            ['DB_NAME' => 'from_in_tree'] + $this->completeValues()
        );
        file_put_contents(
            $this->root . '/.env',
            "APP_DEBUG=false\nDB_HOST=localhost\nDB_NAME=from_dotenv\nDB_USERNAME=aureo\nDB_PASSWORD=secret\n"
        );

        ConfigLoader::load($this->root);

        $this->assertSame('from_in_tree', $_ENV['DB_NAME']);
    }

    /**
     * loadPhpFile()'s "isset($_ENV[$key]) -> continue" branch (the
     * Dotenv-immutable semantics the class docblock promises) only fires when
     * the environment is PARTIALLY populated: some but not all REQUIRED keys
     * present, with a file rung supplying the rest. A fully-set environment
     * short-circuits at rung 1 before any file is read (see
     * testRealEnvironmentWinsAndReadsNoFile); a fully-cleared one (every
     * other test here, via setUp()) never collides with the file at all. This
     * is the only test shape that actually exercises the merge.
     */
    public function testPartialEnvironmentMergesWithFileAndHostValueWinsOnOverlap(): void
    {
        // Only DB_NAME comes from the real environment; setUp() left the
        // other four REQUIRED keys cleared from $_ENV, $_SERVER and getenv().
        $_ENV['DB_NAME'] = 'from_environment';

        $this->writePhpConfig(
            $this->root . '/config/config.php',
            ['DB_NAME' => 'from_file'] + $this->completeValues()
        );

        ConfigLoader::load($this->root);

        // The host-supplied value must survive the merge untouched...
        $this->assertSame('from_environment', $_ENV['DB_NAME']);
        // ...while every key the environment did NOT supply must be pulled
        // from the file - proving this is a real merge, not a no-op.
        $this->assertSame('false', $_ENV['APP_DEBUG']);
        $this->assertSame('localhost', $_ENV['DB_HOST']);
        $this->assertSame('aureo', $_ENV['DB_USERNAME']);
        $this->assertSame('secret', $_ENV['DB_PASSWORD']);
    }

    /**
     * The old failure was 'file not found at <one path>'. With five possible
     * sources, a message naming only one of them sends people to the wrong
     * place.
     */
    public function testFailureMessageNamesEveryPathTried(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#config/config\.php#');
        $this->expectExceptionMessageMatches('#\.env#');

        ConfigLoader::load($this->root);
    }

    public function testPhpConfigMustReturnAnArray(): void
    {
        file_put_contents($this->root . '/config/config.php', '<?php return "not an array";');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#must return an array#');

        ConfigLoader::load($this->root);
    }

    public function testMissingRequiredKeyInPhpConfigIsReported(): void
    {
        $incomplete = $this->completeValues();
        unset($incomplete['DB_PASSWORD']);
        $this->writePhpConfig($this->root . '/config/config.php', $incomplete);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#DB_PASSWORD#');

        ConfigLoader::load($this->root);
    }
}
