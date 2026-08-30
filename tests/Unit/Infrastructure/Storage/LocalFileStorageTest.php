<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Storage;

use App\Infrastructure\Storage\LocalFileStorage;
use PHPUnit\Framework\TestCase;

final class LocalFileStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pos-storage-test-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->root);
    }

    public function testWriteExistsReadAndDelete(): void
    {
        $storage = new LocalFileStorage($this->root);
        $storage->write('products/1/test/file.txt', 'hello');

        self::assertTrue($storage->exists('products/1/test/file.txt'));
        self::assertSame('hello', file_get_contents($storage->path('products/1/test/file.txt')));

        $storage->delete('products/1/test/file.txt');
        self::assertFalse($storage->exists('products/1/test/file.txt'));
    }

    public function testTraversalIsRejected(): void
    {
        $storage = new LocalFileStorage($this->root);

        $this->expectException(\InvalidArgumentException::class);
        $storage->path('../outside.txt');
    }
}
