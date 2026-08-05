<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * asset() is a plain function in a view file rather than a class method, so it
 * carries no CoversClass; UsesClass covers the Config it reads.
 */
#[UsesClass(Config::class)]
final class AssetUrlTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2) . '/public');
        }

        if (!defined('AUREO_ASSET_PREFIX')) {
            define('AUREO_ASSET_PREFIX', '/assets');
        }

        require_once dirname(__DIR__, 2) . '/src/Views/Layouts/ViewHelpers.php';
    }

    protected function tearDown(): void
    {
        Config::setBasePath('');

        parent::tearDown();
    }

    public function testDomainRootInstallProducesRootAbsoluteUrl(): void
    {
        Config::setBasePath('');

        $this->assertSame('/assets/css/styles.css', asset('css/styles.css'));
    }

    public function testSubdirectoryInstallPrefixesTheMountPoint(): void
    {
        Config::setBasePath('/aureo');

        $this->assertSame('/aureo/assets/css/styles.css', asset('css/styles.css'));
    }

    public function testLeadingSlashInTheArgumentDoesNotDoubleUp(): void
    {
        Config::setBasePath('');

        $this->assertSame('/assets/js/scripts.js', asset('/js/scripts.js'));
    }

    /**
     * Every view must go through asset(); a single missed hardcoded URL is an
     * unstyled page on subdirectory and root-docroot installs, and it will not
     * show up on a developer machine where the base path is empty.
     */
    public function testNoViewStillHardcodesAnAssetUrl(): void
    {
        $offenders = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src/Views', \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('#(href|src)="/assets/#', $contents) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Views with hardcoded asset URLs:\n  " . implode("\n  ", $offenders)
        );
    }
}
