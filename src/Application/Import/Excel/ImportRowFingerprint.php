<?php

declare(strict_types=1);

namespace App\Application\Import\Excel;

final class ImportRowFingerprint
{
    /**
     * Build a stable fingerprint for an import row.
     *
     * Doctrine/MySQL JSON persistence may reorder object keys. Hashing the raw
     * json_encode() output would therefore make an unchanged row look different
     * after it is reloaded by the Messenger worker. Canonicalize keys and scalar
     * values before hashing so the fingerprint is stable across persistence.
     *
     * @param array<string,mixed> $row
     */
    public function hash(array $row): string
    {
        $canonical = $this->canonicalize($row);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_scalar($value) || $value === null ? (string) $value : $value;
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = $this->canonicalize($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }
}
