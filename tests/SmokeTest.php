<?php

declare(strict_types=1);

namespace Gardentour\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for the gardentour application.
 *
 * These are syntax + structural checks, not deep behavior tests: every
 * project PHP file must pass `php -l`, and the shared escaping helper must
 * behave as callers expect. No DB connection is required.
 */
class SmokeTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__);
    }

    public static function phpFilesProvider(): array
    {
        $root = dirname(__DIR__);
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            if (str_starts_with($path, $root . '/vendor/')) {
                continue;
            }
            if (str_contains($path, '/.git/')) {
                continue;
            }
            $files[] = [$path];
        }
        sort($files);
        return $files;
    }

    #[DataProvider('phpFilesProvider')]
    public function testPhpFileLints(string $path): void
    {
        $output = [];
        $exitCode = 0;
        exec(
            escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1',
            $output,
            $exitCode
        );

        $this->assertSame(
            0,
            $exitCode,
            "php -l failed for $path:\n" . implode("\n", $output)
        );
    }


}