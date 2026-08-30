<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Common\Storage\FileStorageInterface;

final class LocalFileStorage implements FileStorageInterface
{
    public function __construct(
        private readonly string $rootPath,
    ) {
        if (!is_dir($this->rootPath) && !mkdir($this->rootPath, 0775, true) && !is_dir($this->rootPath)) {
            throw new \RuntimeException('Unable to create storage directory.');
        }
    }

    public function putFile(string $sourcePath, string $storageKey): void
    {
        if (!is_file($sourcePath) || !is_readable($sourcePath)) {
            throw new \RuntimeException('Source file is not readable.');
        }

        $target = $this->path($storageKey);
        $this->ensureDirectory(dirname($target));

        if (!copy($sourcePath, $target)) {
            throw new \RuntimeException('Unable to store uploaded file.');
        }
    }

    public function write(string $storageKey, string $contents): void
    {
        $target = $this->path($storageKey);
        $this->ensureDirectory(dirname($target));

        if (file_put_contents($target, $contents, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to write storage object.');
        }
    }

    public function delete(string $storageKey): void
    {
        $path = $this->path($storageKey);
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('Unable to delete storage object.');
        }
    }

    public function exists(string $storageKey): bool
    {
        return is_file($this->path($storageKey));
    }

    public function path(string $storageKey): string
    {
        $storageKey = ltrim($storageKey, '/');

        if ($storageKey === '' || str_contains($storageKey, '\0') || str_contains($storageKey, '..')) {
            throw new \InvalidArgumentException('Invalid storage key.');
        }

        $candidate = $this->rootPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storageKey);
        $root = realpath($this->rootPath);
        if ($root === false) {
            throw new \RuntimeException('Storage root is unavailable.');
        }

        $parent = realpath(dirname($candidate));
        if ($parent !== false) {
            $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $normalizedParent = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (!str_starts_with($normalizedParent, $normalizedRoot)) {
                throw new \InvalidArgumentException('Storage key escapes storage root.');
            }
        }

        return $candidate;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create storage directory.');
        }
    }
}
