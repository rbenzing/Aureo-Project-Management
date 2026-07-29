<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\LoggerService;
use JsonSerializable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit tests for LoggerService
 *
 * All tests point the service at throwaway directories under the system
 * temp directory (never BASE_PATH/../log) so the real repo log is never
 * touched, per project convention.
 */
#[CoversClass(LoggerService::class)]
final class LoggerServiceTest extends TestCase
{
    private string $tempRoot;

    /** @var string[] ini settings saved so they can be restored after each test */
    private array $savedIni = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['error_log', 'log_errors', 'display_errors', 'display_startup_errors'] as $key) {
            $this->savedIni[$key] = ini_get($key);
        }

        $this->tempRoot = sys_get_temp_dir() . '/logger_svc_test_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        foreach ($this->savedIni as $key => $value) {
            if ($value !== false) {
                ini_set($key, $value);
            }
        }

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        $this->removeDirectory($this->tempRoot);

        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_dir($path) && !is_link($path)) {
            // Restore write access in case a test locked the directory down.
            @chmod($path, 0755);

            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $this->removeDirectory($path . '/' . $entry);
            }

            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    public function testConstructorCreatesLogDirectoryRecursivelyWhenMissing(): void
    {
        $nested = $this->tempRoot . '/nested/deeper';

        $logger = new LoggerService($nested);

        $this->assertTrue(is_dir($nested));
        $this->assertTrue($logger->isEnabled());
        $this->assertSame($nested . '/aureo.log', $logger->getLogFile());
    }

    public function testConstructorKeepsLoggingEnabledWhenDirectoryAlreadyExists(): void
    {
        mkdir($this->tempRoot, 0755, true);

        $logger = new LoggerService($this->tempRoot);

        $this->assertTrue($logger->isEnabled());
    }

    public function testConstructorDisablesLoggingWhenDirectoryCreationFails(): void
    {
        // Occupy the target path with a regular file so mkdir() cannot create
        // a directory there — this deterministically fails on any platform.
        mkdir($this->tempRoot, 0755, true);
        $blockedPath = $this->tempRoot . '/blocked';
        file_put_contents($blockedPath, 'i am a file, not a directory');

        $logger = new LoggerService($blockedPath);

        $this->assertFalse($logger->isEnabled());
    }

    public function testConstructorDisablesLoggingWhenDirectoryIsNotWritable(): void
    {
        mkdir($this->tempRoot, 0755, true);
        chmod($this->tempRoot, 0444);

        $logger = new LoggerService($this->tempRoot);

        $this->assertFalse($logger->isEnabled());
    }

    public function testLogWritesNothingWhenLoggingIsDisabled(): void
    {
        mkdir($this->tempRoot, 0755, true);
        chmod($this->tempRoot, 0444);

        $logger = new LoggerService($this->tempRoot);
        $logger->error('should never be written');

        $this->assertFalse(file_exists($logger->getLogFile()));
    }

    public function testErrorWritesErrorLevelEntry(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->error('Something broke');

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString('Something broke', $content);
    }

    public function testWarningWritesWarningLevelEntry(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->warning('Careful now');

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString('Careful now', $content);
    }

    public function testInfoWritesInfoLevelEntry(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->info('Just so you know');

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString('Just so you know', $content);
    }

    public function testDebugWritesDebugLevelEntry(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->debug('Verbose detail');

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('[DEBUG]', $content);
        $this->assertStringContainsString('Verbose detail', $content);
    }

    public function testLogIncludesPrettyPrintedContextWhenProvided(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->info('With context', ['role' => 'admin']);

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('Context:', $content);
        $this->assertStringContainsString('"role": "admin"', $content);
    }

    public function testLogOmitsContextSectionWhenContextIsEmpty(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->info('No context here');

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringNotContainsString('Context:', $content);
    }

    public function testExceptionLogsMessageFileLineAndStackTrace(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $exception = new RuntimeException('Test exception message');

        $logger->exception($exception);

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString('Exception: Test exception message', $content);
        $this->assertStringContainsString($exception->getFile() . ':' . $exception->getLine(), $content);
        $this->assertStringContainsString('Stack trace:', $content);
    }

    public function testQueryLogsSqlOnlyWhenNoParamsOrTimeGiven(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->query('SELECT 1');

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('SQL Query: SELECT 1', $content);
        $this->assertStringNotContainsString('Parameters:', $content);
        $this->assertStringNotContainsString('Execution time:', $content);
    }

    public function testQueryLogsParametersAndExecutionTimeWhenGiven(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->query('SELECT * FROM users WHERE id = :id', [':id' => 5], 0.0123);

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('SQL Query: SELECT * FROM users WHERE id = :id', $content);
        $this->assertStringContainsString('Parameters:', $content);
        $this->assertStringContainsString(json_encode([':id' => 5]), $content);
        $this->assertStringContainsString('Execution time: 0.0123s', $content);
    }

    public function testActivityLogsUserIpAndUserAgentDetails(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';

        $logger = new LoggerService($this->tempRoot);
        $logger->activity('did_something', 42, ['foo' => 'bar']);

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('User Activity: did_something', $content);
        $this->assertStringContainsString('"user_id": 42', $content);
        $this->assertStringContainsString('"ip_address": "127.0.0.1"', $content);
        // json_encode() without JSON_UNESCAPED_SLASHES escapes the forward slash.
        $this->assertStringContainsString('"user_agent": "TestAgent\/1.0"', $content);
        $this->assertStringContainsString('"foo": "bar"', $content);
    }

    public function testActivityLogsNullUserIdWhenNotProvided(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        $logger = new LoggerService($this->tempRoot);
        $logger->activity('anonymous_action');

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('"user_id": null', $content);
        $this->assertStringContainsString('"ip_address": "unknown"', $content);
        $this->assertStringContainsString('"user_agent": "unknown"', $content);
    }

    public function testSecurityLogsEventWithIpAndUserAgent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
        $_SERVER['HTTP_USER_AGENT'] = 'SecurityAgent';

        $logger = new LoggerService($this->tempRoot);
        $logger->security('Failed login attempt', ['username' => 'admin']);

        $content = file_get_contents($logger->getLogFile());

        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString('Security Event: Failed login attempt', $content);
        $this->assertStringContainsString('"ip_address": "10.0.0.5"', $content);
        $this->assertStringContainsString('"user_agent": "SecurityAgent"', $content);
        $this->assertStringContainsString('"username": "admin"', $content);
    }

    public function testLogFailsGracefullyWithoutWritingWhenContextSerializationThrows(): void
    {
        $logger = new LoggerService($this->tempRoot);

        $throwing = new class () implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                throw new RuntimeException('cannot serialize');
            }
        };

        // Should not let the exception escape — LoggerService must swallow it
        // internally (falling back to error_log) rather than crashing the caller.
        $logger->error('boom', ['bad' => $throwing]);

        // json_encode() throws before file_put_contents() is reached, so the
        // structured entry is never appended. LoggerService points PHP's error_log
        // at the same file, so the failure still gets recorded there — but in
        // error_log's format, not the app's "[level] ... Context:" format.
        $logFile = $logger->getLogFile();
        $written = file_exists($logFile) ? (string) file_get_contents($logFile) : '';

        $this->assertStringContainsString('LoggerService failed: cannot serialize', $written);
        $this->assertStringNotContainsString('Context:', $written);
    }

    public function testGetLogFileReturnsConfiguredPath(): void
    {
        $logger = new LoggerService($this->tempRoot);

        $this->assertSame($this->tempRoot . '/aureo.log', $logger->getLogFile());
    }

    public function testGetRecentLogsReturnsEmptyArrayWhenLogFileDoesNotExist(): void
    {
        $logger = new LoggerService($this->tempRoot);

        $this->assertSame([], $logger->getRecentLogs());
    }

    public function testGetRecentLogsReturnsOnlyTheLastRequestedLines(): void
    {
        mkdir($this->tempRoot, 0755, true);
        $logger = new LoggerService($this->tempRoot);
        file_put_contents($logger->getLogFile(), "A\nB\nC");

        $result = $logger->getRecentLogs(2);

        $this->assertSame(['B', 'C'], $result);
    }

    public function testClearLogsRemovesExistingFileAndReturnsTrue(): void
    {
        $logger = new LoggerService($this->tempRoot);
        $logger->info('to be cleared');
        $this->assertTrue(file_exists($logger->getLogFile()));

        $result = $logger->clearLogs();

        $this->assertTrue($result);
        $this->assertFalse(file_exists($logger->getLogFile()));
    }

    public function testClearLogsReturnsTrueWhenLogFileDoesNotExist(): void
    {
        $logger = new LoggerService($this->tempRoot);

        $this->assertTrue($logger->clearLogs());
    }

    public function testIsEnabledReflectsConstructionState(): void
    {
        $enabledLogger = new LoggerService($this->tempRoot);
        $this->assertTrue($enabledLogger->isEnabled());
    }
}
