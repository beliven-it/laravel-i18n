<?php

namespace Beliven\I18n\Services;

use Illuminate\Support\Facades\File;

class TranslationScanner
{
    protected array $foundKeys = [];

    public function scan(array $paths = ['app', 'resources']): array
    {
        $this->foundKeys = [];

        foreach ($paths as $path) {
            $fullPath = base_path($path);
            if (File::exists($fullPath)) {
                $this->scanDirectory($fullPath);
            }
        }

        return $this->foundKeys;
    }

    protected function scanDirectory(string $directory): void
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $this->scanFile($file->getPathname());
            }
        }
    }

    protected function scanFile(string $filePath): void
    {
        $content = File::get($filePath);

        // Pattern for __('key') and trans('key')
        $patterns = [
            "/__\(['\"]([^'\"]+)['\"]\)/",
            "/trans\(['\"]([^'\"]+)['\"]\)/",
            "/@lang\(['\"]([^'\"]+)['\"]\)/",
        ];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);

            foreach ($matches[1] as $key) {
                $this->addKey($key, $filePath);
            }
        }
    }

    protected function addKey(string $key, string $filePath): void
    {
        if (! isset($this->foundKeys[$key])) {
            $this->foundKeys[$key] = [];
        }

        if (! in_array($filePath, $this->foundKeys[$key])) {
            $this->foundKeys[$key][] = $filePath;
        }
    }

    public function parseKey(string $key): array
    {
        // Parse keys like 'clinic/detail.welcome' or 'messages.success'
        $parts = explode('.', $key);

        if (count($parts) === 1) {
            // Simple key without dot notation (e.g., 'Welcome', 'My Translation')
            // These should go to JSON file
            return [
                'type' => 'json',
                'file' => null,
                'key' => $key,
            ];
        }

        // Keys with dots go to PHP files (e.g., 'messages.welcome.user')
        return [
            'type' => 'php',
            'file' => $parts[0],
            'key' => implode('.', array_slice($parts, 1)),
        ];
    }
}
