<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every view must be reachable: either a controller renders it, or another
 * view includes it. Orphans accumulate silently because nothing fails when a
 * view stops being referenced - the three TimeTracking views sat unreferenced
 * through a full test-coverage push without anything noticing.
 */
final class DeadViewTest extends TestCase
{
    private static string $root;

    public static function setUpBeforeClass(): void
    {
        self::$root = str_replace('\\', '/', dirname(__DIR__, 2));
    }

    /** @return list<string> Repo-relative paths of every view, forward slashes. */
    private function viewFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$root . '/src/Views', RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $views = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getPathname());
            $views[] = substr($relative, strlen(self::$root) + 1);
        }

        sort($views);

        return $views;
    }

    /**
     * Source of everything that could reference a view, keyed by repo-relative
     * path so a candidate can be checked against every file except itself.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        $sources = [];

        foreach ([self::$root . '/src', self::$root . '/public'] as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relative = str_replace('\\', '/', $file->getPathname());
                    $key = substr($relative, strlen(self::$root) + 1);
                    $sources[$key] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $sources;
    }

    public function testEveryViewIsRenderedOrIncluded(): void
    {
        $sources = $this->sources();
        $orphans = [];

        foreach ($this->viewFiles() as $view) {
            // 'src/Views/Tasks/index.php' is referenced either as the render()
            // target 'Tasks/index' or as an include ending 'Tasks/index.php'.
            $withoutPrefix = substr($view, strlen('src/Views/'));
            $withoutExtension = substr($withoutPrefix, 0, -strlen('.php'));

            $renderTarget = "'" . $withoutExtension . "'";
            $includeTarget = '/' . $withoutPrefix;

            // Most views open with a `// file: Views/...` header naming their
            // own path, which would satisfy the search on its own. Excluding
            // the whole candidate file is the only reliable way to ignore it:
            // stripping the comment text missed the 28 headers written with a
            // space after `//`, so each of those views vouched for itself and
            // a genuine orphan (Sprints/inc/project_header.php) went unseen.
            $haystack = implode("\n", array_values(array_diff_key($sources, [$view => true])));

            if (!str_contains($haystack, $renderTarget) && !str_contains($haystack, $includeTarget)) {
                $orphans[] = $view;
            }
        }

        $this->assertSame(
            [],
            $orphans,
            "Views referenced by nothing:\n  " . implode("\n  ", $orphans)
        );
    }
}
