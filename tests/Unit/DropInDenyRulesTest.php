<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the deny rules that protect the drop-in layout, where the document
 * root IS the application root and every file in the repository sits inside the
 * web root. The recommended layout (document root at public/) needs none of
 * this; the drop-in layout needs all of it.
 *
 * These rules were maintained by hand and verified by nothing: CI's smoke and
 * hardening jobs were removed because they asserted against a synthetic
 * container rather than a real host, so a pass proved nothing about a
 * deployment. That is still true of any test here — Apache and IIS are not
 * running. What this can prove is the part that actually rotted: that the
 * patterns still match what they claim to, and that the two shipped
 * configurations agree with each other. `phinx.php` was served with HTTP 200 in
 * the drop-in layout because it matched neither list.
 *
 * The nginx block in docs/DEPLOYMENT.md is prose, not a shipped artifact, and
 * is not covered here — keep it in step by hand.
 */
final class DropInDenyRulesTest extends TestCase
{
    /** Basenames a drop-in host must never serve. */
    public static function deniedFiles(): array
    {
        return [
            'phinx config' => ['phinx.php'],
            'environment file' => ['.env'],
            'composer manifest' => ['composer.json'],
            'composer lock' => ['composer.lock'],
            'version marker' => ['VERSION'],
            'tailwind config' => ['tailwind.config.js'],
            'sample data' => ['sample-data.sql'],
            'readme' => ['README.md'],
            'phpunit config' => ['phpunit.xml'],
            'application log' => ['aureo.log'],
        ];
    }

    /** Basenames a drop-in host must continue to serve. */
    public static function servedFiles(): array
    {
        return [
            'front controller' => ['index.php'],
            'bundled script' => ['app.js'],
            'bundled stylesheet' => ['styles.css'],
            'an image' => ['logo.png'],
        ];
    }

    /**
     * Apache applies <FilesMatch> to the basename, as PCRE without delimiters.
     */
    private function apacheFilesMatchPattern(): string
    {
        $htaccess = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');

        $this->assertSame(
            1,
            preg_match('/<FilesMatch\s+"([^"]+)"\s*>/', $htaccess, $m),
            'The root .htaccess must carry exactly one <FilesMatch> deny block.'
        );

        return '/' . str_replace('/', '\/', $m[1]) . '/';
    }

    #[DataProvider('deniedFiles')]
    public function testApacheDeniesFile(string $basename): void
    {
        $this->assertSame(
            1,
            preg_match($this->apacheFilesMatchPattern(), $basename),
            "The root .htaccess must deny {$basename} in the drop-in layout."
        );
    }

    #[DataProvider('servedFiles')]
    public function testApacheStillServesFile(string $basename): void
    {
        $this->assertSame(
            0,
            preg_match($this->apacheFilesMatchPattern(), $basename),
            "Denying {$basename} would break the drop-in layout."
        );
    }

    /**
     * IIS has no regex deny list. It blocks a named path segment through
     * hiddenSegments and a suffix through fileExtensions, so a file is denied
     * when either list covers it.
     */
    private function iisDenies(string $basename): bool
    {
        $xml = simplexml_load_file(dirname(__DIR__, 2) . '/web.config');
        $this->assertNotFalse($xml, 'web.config must be parseable XML.');

        $filtering = $xml->xpath('//requestFiltering');
        $this->assertNotEmpty($filtering, 'web.config must configure requestFiltering.');

        foreach ($filtering[0]->hiddenSegments->add ?? [] as $segment) {
            if (strcasecmp((string) $segment['segment'], $basename) === 0) {
                return true;
            }
        }

        foreach ($filtering[0]->fileExtensions->add ?? [] as $extension) {
            if ((string) $extension['allowed'] !== 'false') {
                continue;
            }
            $suffix = (string) $extension['fileExtension'];
            if ($suffix !== '' && str_ends_with(strtolower($basename), strtolower($suffix))) {
                return true;
            }
        }

        return false;
    }

    #[DataProvider('deniedFiles')]
    public function testIisDeniesFile(string $basename): void
    {
        $this->assertTrue(
            $this->iisDenies($basename),
            "web.config must deny {$basename} in the drop-in layout."
        );
    }

    #[DataProvider('servedFiles')]
    public function testIisStillServesFile(string $basename): void
    {
        $this->assertFalse(
            $this->iisDenies($basename),
            "Denying {$basename} would break the drop-in layout under IIS."
        );
    }
}
