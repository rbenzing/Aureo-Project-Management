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
