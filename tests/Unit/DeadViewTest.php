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

    /** Concatenated source of everything that could reference a view. */
    private function haystack(): string
    {
        $sources = [];

        foreach ([self::$root . '/src', self::$root . '/public'] as $dir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $sources[] = (string) file_get_contents($file->getPathname());
                }
            }
        }

        return implode("\n", $sources);
    }

    public function testEveryViewIsRenderedOrIncluded(): void
    {
        $haystack = $this->haystack();
        $orphans = [];

        foreach ($this->viewFiles() as $view) {
            // 'src/Views/Tasks/index.php' is referenced either as the render()
            // target 'Tasks/index' or as an include ending 'Tasks/index.php'.
            $withoutPrefix = substr($view, strlen('src/Views/'));
            $withoutExtension = substr($withoutPrefix, 0, -strlen('.php'));

            $renderTarget = "'" . $withoutExtension . "'";
            $includeTarget = '/' . $withoutPrefix;

            // A file's own header comment mentions its path; exclude it by
            // requiring the reference to appear somewhere other than that file.
            $selfComment = '//file: Views/' . $withoutPrefix;
            $searchable = str_replace($selfComment, '', $haystack);

            if (!str_contains($searchable, $renderTarget) && !str_contains($searchable, $includeTarget)) {
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
