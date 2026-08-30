<?php

declare(strict_types=1);

namespace App\Application\Common\Storage;

interface FileStorageInterface
{
    public function putFile(string $sourcePath, string $storageKey): void;

    public function write(string $storageKey, string $contents): void;

    public function delete(string $storageKey): void;

    public function exists(string $storageKey): bool;

    public function path(string $storageKey): string;
}
