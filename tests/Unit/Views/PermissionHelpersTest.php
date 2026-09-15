<?php

declare(strict_types=1);

namespace Tests\Unit\Views;

use PHPUnit\Framework\TestCase;

/**
 * The permission helpers are plain functions in a view file rather than class
 * methods, so they carry no CoversClass — same precedent as FormComponentsTest.
 *
 * They are the authorization primitive the views actually call, and they had no
 * tests at all: replacing hasUserPermission()'s body with `return true` — every
 * permission granted to everyone — left the entire suite green. src/Views is
 * excluded from coverage in phpunit.xml, so nothing flagged the gap either.
 *
 * The deny paths are what matter here. A test that only asserts the "allow"
 * branch cannot tell a working check from one that always returns true.
 */
final class PermissionHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 3) . '/public');
        }

        require_once dirname(__DIR__, 3) . '/src/Views/Layouts/ViewHelpers.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        unset($_SESSION['user'], $_SESSION['csrf_token'], $_SESSION['active_timer']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        parent::tearDown();
    }

    /** @param list<string> $permissions */
    private function grant(array $permissions): void
    {
        $_SESSION['user'] = ['permissions' => $permissions];
    }

    // ---- hasUserPermission() --------------------------------------------

    public function testHasUserPermissionGrantsAHeldPermission(): void
    {
        $this->grant(['view_projects', 'edit_tasks']);

        $this->assertTrue(hasUserPermission('edit_tasks'));
    }

    public function testHasUserPermissionDeniesAPermissionNotHeld(): void
    {
        $this->grant(['view_projects']);

        $this->assertFalse(hasUserPermission('delete_projects'));
    }

    public function testHasUserPermissionDeniesWhenTheSessionCarriesNoPermissions(): void
    {
        $this->assertFalse(hasUserPermission('view_projects'));
    }

    public function testHasUserPermissionDeniesWhenThePermissionListIsEmpty(): void
    {
        $this->grant([]);

        $this->assertFalse(hasUserPermission('view_projects'));
    }

    /**
     * The lookup is strict, so a loosely-equal value must not slip through.
     * Without strictness in_array('0' == 0) style coercion would grant a
     * permission nobody holds.
     */
    public function testHasUserPermissionComparesStrictly(): void
    {
        $_SESSION['user'] = ['permissions' => [0]];

        $this->assertFalse(hasUserPermission('view_projects'));
    }

    // ---- hasAnyUserPermission() -----------------------------------------

    public function testHasAnyUserPermissionGrantsWhenOneMatches(): void
    {
        $this->grant(['edit_tasks']);

        $this->assertTrue(hasAnyUserPermission(['delete_tasks', 'edit_tasks']));
    }

    public function testHasAnyUserPermissionDeniesWhenNoneMatch(): void
    {
        $this->grant(['view_projects']);

        $this->assertFalse(hasAnyUserPermission(['delete_tasks', 'edit_tasks']));
    }

    public function testHasAnyUserPermissionDeniesForAnEmptyRequestList(): void
    {
        $this->grant(['view_projects']);

        $this->assertFalse(hasAnyUserPermission([]));
    }

    // ---- hasAllUserPermissions() ----------------------------------------

    public function testHasAllUserPermissionsGrantsWhenEveryPermissionIsHeld(): void
    {
        $this->grant(['view_projects', 'edit_tasks']);

        $this->assertTrue(hasAllUserPermissions(['view_projects', 'edit_tasks']));
    }

    public function testHasAllUserPermissionsDeniesWhenOneIsMissing(): void
    {
        $this->grant(['view_projects']);

        $this->assertFalse(hasAllUserPermissions(['view_projects', 'edit_tasks']));
    }

    public function testHasAllUserPermissionsDeniesWhenTheUserHoldsNone(): void
    {
        $this->assertFalse(hasAllUserPermissions(['view_projects']));
    }

    // ---- renderTimerControls() ------------------------------------------

    /**
     * renderTimerControls() read $csrfToken from inside its own function
     * body, where a caller's local variables are not visible, so the field it
     * emitted was always empty and the form it belongs to could never pass
     * validation. Same defect class as renderCSRFToken(), fixed the same way:
     * read the value CsrfMiddleware itself writes.
     */
    public function testRenderTimerControlsEmitsTheSessionCsrfToken(): void
    {
        $this->grant(['view_time_tracking']);
        $_SESSION['csrf_token'] = 'token-from-the-session';
        $_SESSION['active_timer'] = ['task_id' => 7];

        $html = renderTimerControls(7);

        $this->assertStringContainsString('value="token-from-the-session"', $html);
    }

    public function testRenderTimerControlsEmitsTheTokenOnTheStartForm(): void
    {
        $this->grant(['create_time_tracking']);
        $_SESSION['csrf_token'] = 'start-form-token';
        unset($_SESSION['active_timer']);

        $html = renderTimerControls(7);

        $this->assertStringContainsString('value="start-form-token"', $html);
    }

    public function testRenderTimerControlsEscapesTheToken(): void
    {
        $this->grant(['view_time_tracking']);
        $_SESSION['csrf_token'] = 'a"b<c';
        unset($_SESSION['active_timer']);

        $this->assertStringContainsString('a&quot;b&lt;c', renderTimerControls(7));
    }

    public function testRenderTimerControlsRendersNothingWithoutPermission(): void
    {
        $this->grant(['view_projects']);

        $this->assertSame('', renderTimerControls(7));
    }

    /**
     * Documents the vacuous-truth case: "holds all of nothing" is true. Callers
     * that build the list dynamically must not treat an empty list as a gate.
     */
    public function testHasAllUserPermissionsGrantsForAnEmptyRequestList(): void
    {
        $this->grant([]);

        $this->assertTrue(hasAllUserPermissions([]));
    }
}
