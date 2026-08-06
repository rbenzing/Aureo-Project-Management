<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

/**
 * renderCSRFToken() is a plain function in a view file rather than a class method,
 * so it carries no CoversClass - same precedent as AssetUrlTest/PathCaseSensitivityTest.
 *
 * BaseController::render() extract()s $csrfToken into *view* scope, but
 * renderCSRFToken() is a function body, which PHP never gives caller-scope
 * variables to. Reading $csrfToken there always fell through to '' and every
 * form submitted via this helper was rejected by CsrfMiddleware. The fix reads
 * $_SESSION['csrf_token'], the value CsrfMiddleware::generateToken() actually
 * writes and the value CsrfMiddleware::validateToken() actually checks against.
 */
final class FormComponentsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 3) . '/public');
        }

        require_once dirname(__DIR__, 3) . '/src/Views/Layouts/FormComponents.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        unset($_SESSION['csrf_token']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['csrf_token']);

        parent::tearDown();
    }

    public function testRendersTheSessionTokenValue(): void
    {
        $_SESSION['csrf_token'] = 'abc123def456';

        $this->assertSame(
            '<input type="hidden" name="csrf_token" value="abc123def456">',
            renderCSRFToken()
        );
    }

    public function testRendersAnEmptyValueWithoutWarningOrThrowingWhenNoSessionTokenExists(): void
    {
        $this->assertSame(
            '<input type="hidden" name="csrf_token" value="">',
            renderCSRFToken()
        );
    }

    public function testEscapesTheTokenValue(): void
    {
        // Not a realistic CSRF token (real ones are hex from bin2hex()), but proves
        // the output goes through htmlspecialchars() rather than being trusted raw.
        $_SESSION['csrf_token'] = '"><script>alert(1)</script>';

        $this->assertSame(
            '<input type="hidden" name="csrf_token" value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;">',
            renderCSRFToken()
        );
    }

    /**
     * Guard against recurrence: the bug was reading a bare $csrfToken inside a
     * function body, where BaseController::render()'s extract() can never reach
     * it. Asserting the identifier is entirely absent from the file is simpler
     * and just as effective as parsing function boundaries.
     */
    public function testFormComponentsDoesNotReferenceTheBareCsrfTokenVariable(): void
    {
        $contents = (string) file_get_contents(dirname(__DIR__, 3) . '/src/Views/Layouts/FormComponents.php');

        $this->assertDoesNotMatchRegularExpression('/\$csrfToken\b/', $contents);
    }
}
