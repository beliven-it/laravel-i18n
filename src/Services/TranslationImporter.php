<?php

namespace Beliven\I18n\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class TranslationImporter
{
    public function __construct(
        protected TranslationFileManager $fileManager,
    ) {}

    public function import(string $inputPath, bool $overwrite = false): array
    {
        $data = $this->readExcel($inputPath);
        $grouped = $this->groupByLocaleAndFile($data);

        $stats = [
            'files_created' => 0,
            'keys_added' => 0,
            'keys_updated' => 0,
        ];

        foreach ($grouped as $locale => $files) {
            foreach ($files as $file => $keys) {
                // Handle JSON files
                if ($file === '__json__') {
                    $fileExists = $this->fileManager->jsonFileExists($locale);

                    if (! $fileExists) {
                        $stats['files_created']++;
                    }

                    if ($overwrite) {
                        // Replace entire JSON file
                        $this->fileManager->saveJsonFile($locale, $keys);
                        $stats['keys_added'] += count($keys);
                    } else {
                        // Merge with existing JSON
                        $existing = $this->fileManager->loadJsonFile($locale);

                        foreach ($keys as $key => $value) {
                            if (isset($existing[$key])) {
                                $stats['keys_updated']++;
                            } else {
                                $stats['keys_added']++;
                            }
                        }

                        $merged = array_merge($existing, $keys);
                        $this->fileManager->saveJsonFile($locale, $merged);
                    }
                } else {
                    // Handle PHP files
                    $fileExists = $this->fileManager->fileExists($locale, $file);

                    if (! $fileExists) {
                        $stats['files_created']++;
                    }

                    if ($overwrite) {
                        // Replace entire file
                        $translations = $this->fileManager->unflattenArray($keys);
                        $this->fileManager->saveFile($locale, $file, $translations);
                        $stats['keys_added'] += count($keys);
                    } else {
                        // Merge with existing
                        $existing = $this->fileManager->loadFile($locale, $file);
                        $existingFlat = $this->fileManager->flattenArray($existing);

                        foreach ($keys as $key => $value) {
                            if (isset($existingFlat[$key])) {
                                $stats['keys_updated']++;
                            } else {
                                $stats['keys_added']++;
                            }
                        }

                        $merged = array_merge($existingFlat, $keys);
                        $translations = $this->fileManager->unflattenArray($merged);
                        $this->fileManager->saveFile($locale, $file, $translations);
                    }
                }
            }
        }

        return $stats;
    }

    protected function readExcel(string $inputPath): array
    {
        $spreadsheet = IOFactory::load($inputPath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Remove header row
        array_shift($rows);

        $data = [];
        foreach ($rows as $row) {
            if (! empty($row[0]) && ! empty($row[1]) && ! empty($row[2])) {
                $data[] = [
                    'locale' => $row[0],
                    'path' => $row[1],
                    'key' => $row[2],
                    'value' => $row[3] ?? '',
                ];
            }
        }

        return $data;
    }

    protected function groupByLocaleAndFile(array $data): array
    {
        $grouped = [];

        foreach ($data as $item) {
            $locale = $item['locale'];
            $path = $item['path'];

            // Check if it's a JSON file: lang/en.json
            if (preg_match('/^lang\/[^\/]+\.json$/', $path)) {
                $file = '__json__'; // Special marker for JSON files
            } else {
                // Extract file from path: lang/en/clinic/detail.php -> clinic/detail
                $file = preg_replace(
                    '/^lang\/[^\/]+\/(.+)\.php$/',
                    '$1',
                    (string) $path,
                );
            }

            if (! isset($grouped[$locale])) {
                $grouped[$locale] = [];
            }

            if (! isset($grouped[$locale][$file])) {
                $grouped[$locale][$file] = [];
            }

            $grouped[$locale][$file][$item['key']] = $item['value'];
        }

        return $grouped;
    }
}
