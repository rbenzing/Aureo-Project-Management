<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * BaseController::render() now require_once's ViewHelpers.php before every
 * view executes, so no view may load it again itself. `include`/`require`
 * (non-`_once` forms) redeclare every function in the file, which is a fatal
 * "Cannot redeclare" error - this is what happened to five live routes
 * (Activity/index.php, Tasks/backlog.php, Tasks/index.php,
 * Tasks/sprint-planning.php, TimeTracking/index.php) before render() started
 * guaranteeing the load. Even the `_once` forms are now redundant, so this
 * guard forbids re-adding any of them rather than only the unsafe ones.
 *
 * Pure guard test, no CoversClass/UsesClass: it inspects source text rather
 * than exercising any class, matching PathCaseSensitivityTest's precedent.
 */
final class ViewHelperLoadingTest extends TestCase
{
    public function testNoViewLoadsViewHelpersItself(): void
    {
        $offenders = [];

        $viewHelpersPath = dirname(__DIR__, 2) . '/src/Views/Layouts/ViewHelpers.php';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src/Views', \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if ($file->getPathname() === $viewHelpersPath) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('#\b(?:include|include_once|require|require_once)\b[^;]*ViewHelpers\.php#', $contents) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Views that load ViewHelpers.php themselves (BaseController::render() already does this):\n  " . implode("\n  ", $offenders)
        );
    }
}
