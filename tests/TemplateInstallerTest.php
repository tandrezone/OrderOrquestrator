<?php

declare(strict_types=1);

namespace Tandrezone\OrderOrchestrator\Tests;

use PHPUnit\Framework\TestCase;
use Tandrezone\OrderOrchestrator\TemplateInstaller;

final class TemplateInstallerTest extends TestCase
{
    private string $tmpProjectRoot;

    protected function setUp(): void
    {
        $this->tmpProjectRoot = sys_get_temp_dir() . '/order-orchestrator-tests-' . bin2hex(random_bytes(8));
        mkdir($this->tmpProjectRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tmpProjectRoot);
    }

    public function testInstallCopiesTemplateToTargetDirectory(): void
    {
        $sourceDirectory = $this->tmpProjectRoot . '/resources/templates';
        mkdir($sourceDirectory, 0777, true);

        $sourceFile = $sourceDirectory . '/order-form.html';
        file_put_contents($sourceFile, '<form>Order</form>');

        TemplateInstaller::install($this->tmpProjectRoot);

        $targetFile = $this->tmpProjectRoot . '/templates/order-form.html';

        self::assertFileExists($targetFile);
        self::assertSame('<form>Order</form>', file_get_contents($targetFile));
    }

    public function testInstallWritesErrorWhenSourceFileDoesNotExist(): void
    {
        TemplateInstaller::install($this->tmpProjectRoot);

        self::assertFileDoesNotExist($this->tmpProjectRoot . '/templates/order-form.html');
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
