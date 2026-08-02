<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Guards against path references whose casing only works on a case-insensitive
 * filesystem.
 *
 * Windows and macOS resolve 'src/views/Layouts/ViewHelpers.php' to
 * 'src/Views/Layouts/ViewHelpers.php' without complaint; Linux does not. Every
 * such reference is therefore a production fatal that is invisible on a
 * developer machine and only appears once the line executes on a case-sensitive
 * host. Seventeen of them shipped this way, across Dashboard, Projects,
 * Milestones, Tasks, Sprints, Settings, the sidebar and Task::getStatusName() -
 * none of them caught until a test finally executed one on CI.
 *
 * These assertions compare against a case-exact index of what is actually on
 * disk, so they fail on Windows too, where the bug is otherwise unobservable.
 */
final class PathCaseSensitivityTest extends TestCase
{
    private static string $root;

    /** @var array<string,true> Repo-relative paths, forward slashes, exact case. */
    private static array $actualFiles = [];

    public static function setUpBeforeClass(): void
    {
        self::$root = str_replace('\\', '/', dirname(__DIR__, 2));

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$root . '/src', RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', $file->getPathname());
            self::$actualFiles[substr($relative, strlen(self::$root) + 1)] = true;
        }
    }

    /**
     * @return list<string> Every .php file under src/, repo-relative.
     */
    private function sourceFiles(): array
    {
        return array_values(array_filter(
            array_keys(self::$actualFiles),
            static fn (string $path): bool => str_ends_with($path, '.php')
        ));
    }

    public function testEveryBasePathIncludeResolvesWithExactCase(): void
    {
        $unresolved = [];

        foreach ($this->sourceFiles() as $relative) {
            $contents = (string) file_get_contents(self::$root . '/' . $relative);

            preg_match_all(
                '/(?:require|include)(?:_once)?\s+BASE_PATH\s*\.\s*[\'"]([^\'"]+)[\'"]/',
                $contents,
                $matches
            );

            foreach ($matches[1] as $reference) {
                // Interpolated targets (BaseController::render()'s
                // "/../src/Views/{$view}.php") cannot be resolved statically.
                // testEveryRenderedViewResolvesWithExactCase covers those.
                if (str_contains($reference, '$')) {
                    continue;
                }

                // BASE_PATH is <repo>/public, so '/../src/...' is repo-relative.
                $target = preg_replace('#^/\.\./#', '', $reference);

                if (!str_starts_with((string) $target, 'src/')) {
                    continue;
                }

                if (!isset(self::$actualFiles[$target])) {
                    $unresolved[] = "{$relative} -> {$reference}";
                }
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            "Include paths that do not exist with this exact casing:\n  " . implode("\n  ", $unresolved)
        );
    }

    public function testEveryRenderedViewResolvesWithExactCase(): void
    {
        $unresolved = [];

        foreach ($this->sourceFiles() as $relative) {
            if (!str_starts_with($relative, 'src/Controllers/')) {
                continue;
            }

            $contents = (string) file_get_contents(self::$root . '/' . $relative);
            preg_match_all('/->render\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches);

            foreach ($matches[1] as $view) {
                // BaseController::render() includes BASE_PATH . '/../src/Views/{$view}.php'
                $target = "src/Views/{$view}.php";

                if (!isset(self::$actualFiles[$target])) {
                    $unresolved[] = "{$relative} -> render('{$view}') expects {$target}";
                }
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            "Rendered views that do not exist with this exact casing:\n  " . implode("\n  ", $unresolved)
        );
    }

    /**
     * The git index once tracked src/Views/Projects and src/Views/projects as
     * separate directories. No case-insensitive checkout can represent both, so
     * one copy silently rotted. CI has a guard for the tracked-path version of
     * this; here it is asserted against the working tree as well.
     */
    public function testNoTwoSourcePathsDifferOnlyByCase(): void
    {
        $seen = [];
        $collisions = [];

        foreach (array_keys(self::$actualFiles) as $path) {
            $key = strtolower($path);

            if (isset($seen[$key]) && $seen[$key] !== $path) {
                $collisions[] = "{$seen[$key]} vs {$path}";

                continue;
            }

            $seen[$key] = $path;
        }

        $this->assertSame(
            [],
            $collisions,
            "Paths differing only by case:\n  " . implode("\n  ", $collisions)
        );
    }
}
