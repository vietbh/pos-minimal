<?php

declare(strict_types=1);

namespace App\Tests\Frontend;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class UiTranslationContractTest extends TestCase
{
    public function testAllTwigTranslationKeysExistInEnglishAndVietnamese(): void
    {
        $root = dirname(__DIR__, 2);
        $translations = [];

        foreach (['en', 'vi'] as $locale) {
            $translations[$locale] = Yaml::parseFile($root . '/translations/messages.' . $locale . '.yaml');
        }

        $missing = [];
        foreach ($this->twigFiles($root . '/templates') as $file) {
            $contents = file_get_contents($file);
            preg_match_all("/['\"]([A-Za-z0-9_.-]+)['\"]\\|trans/", $contents, $matches);

            foreach (array_unique($matches[1]) as $key) {
                foreach (['en', 'vi'] as $locale) {
                    if (!$this->hasKey($translations[$locale], $key)) {
                        $missing[] = $locale . ':' . $key . ' in ' . str_replace($root . '/', '', $file);
                    }
                }
            }
        }

        self::assertSame([], $missing, "Missing UI translation keys:\n" . implode("\n", $missing));
    }

    /** @return list<string> */
    private function twigFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'twig') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function hasKey(array $translations, string $key): bool
    {
        $value = $translations;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }
}
