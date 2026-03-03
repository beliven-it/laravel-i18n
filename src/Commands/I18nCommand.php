<?php

namespace Beliven\I18n\Commands;

use Beliven\I18n\Services\TranslationExporter;
use Beliven\I18n\Services\TranslationFileManager;
use Beliven\I18n\Services\TranslationImporter;
use Beliven\I18n\Services\TranslationScanner;
use Illuminate\Console\Command;

class I18nCommand extends Command
{
    protected $signature = 'i18n:manage
                            {action : The action to perform (scan, export, import)}
                            {--locale= : Locale to use (for scan)}
                            {--output= : Output file path (for export)}
                            {--input= : Input file path (for import)}
                            {--overwrite : Overwrite existing translations (for import)}';

    protected $description = 'Manage translation files (scan, export, import)';

    public function handle(
        TranslationScanner $translationScanner,
        TranslationFileManager $translationFileManager,
        TranslationExporter $translationExporter,
        TranslationImporter $translationImporter,
    ): int {
        $action = $this->argument('action');

        return match ($action) {
            'scan' => $this->handleScan(
                $translationScanner,
                $translationFileManager,
            ),
            'export' => $this->handleExport($translationExporter),
            'import' => $this->handleImport($translationImporter),
            default => $this->error(
                sprintf(
                    'Invalid action: %s. Use: scan, export, or import.',
                    $action,
                ),
            ) ?? 1,
        };
    }

    protected function handleScan(
        TranslationScanner $translationScanner,
        TranslationFileManager $translationFileManager,
    ): int {
        $this->info('Scanning for translation keys...');

        $foundKeys = $translationScanner->scan();
        $locales = $this->option('locale')
            ? [$this->option('locale')]
            : $translationFileManager->getAvailableLocales();

        if ($locales === []) {
            $this->error(
                'No locales found. Please create at least one locale directory in lang/',
            );

            return 1;
        }

        $stats = [
            'total_keys' => count($foundKeys),
            'files_created' => 0,
            'keys_added' => 0,
            'keys_existing' => 0,
        ];

        foreach (array_keys($foundKeys) as $key) {
            $parsed = $translationScanner->parseKey($key);

            foreach ($locales as $locale) {
                $fileExists = $translationFileManager->fileExists(
                    $locale,
                    $parsed['file'],
                );

                if ($parsed['key'] === null) {
                    // Simple key without nested structure
                    continue;
                }

                if (! $fileExists) {
                    $stats['files_created']++;
                    $translationFileManager->addKey(
                        $locale,
                        $parsed['file'],
                        $parsed['key'],
                    );
                    $stats['keys_added']++;
                    $this->line(
                        sprintf(
                            '  <fg=green>✓</> Created %s/%s.php -> %s',
                            $locale,
                            $parsed['file'],
                            $parsed['key'],
                        ),
                    );
                } elseif (
                    ! $translationFileManager->keyExists(
                        $locale,
                        $parsed['file'],
                        $parsed['key'],
                    )
                ) {
                    $translationFileManager->addKey(
                        $locale,
                        $parsed['file'],
                        $parsed['key'],
                    );
                    $stats['keys_added']++;
                    $this->line(
                        sprintf(
                            '  <fg=green>+</> Added %s/%s.php -> %s',
                            $locale,
                            $parsed['file'],
                            $parsed['key'],
                        ),
                    );
                } else {
                    $stats['keys_existing']++;
                }
            }
        }

        $this->newLine();
        $this->info('Scan complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total keys found', $stats['total_keys']],
                ['Files created', $stats['files_created']],
                ['Keys added', $stats['keys_added']],
                ['Keys already existing', $stats['keys_existing']],
            ],
        );

        return 0;
    }

    protected function handleExport(
        TranslationExporter $translationExporter,
    ): int {
        $output =
            $this->option('output') ?? storage_path('app/translations.xlsx');

        $this->info('Exporting translations to: '.$output);

        $result = $translationExporter->export($output);

        $this->newLine();
        $this->info('Export complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Locales', $result['locales']],
                ['Total rows', $result['rows']],
                ['Output file', $result['file']],
            ],
        );

        return 0;
    }

    protected function handleImport(
        TranslationImporter $translationImporter,
    ): int {
        $input =
            $this->option('input') ?? storage_path('app/translations.xlsx');
        $overwrite = $this->option('overwrite');

        if (! file_exists($input)) {
            $this->error('Input file not found: '.$input);

            return 1;
        }

        $mode = $overwrite ? 'overwrite' : 'merge';
        $this->info(
            sprintf(
                'Importing translations from: %s (mode: %s)',
                $input,
                $mode,
            ),
        );

        $stats = $translationImporter->import($input, $overwrite);

        $this->newLine();
        $this->info('Import complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files created', $stats['files_created']],
                ['Keys added', $stats['keys_added']],
                ['Keys updated', $stats['keys_updated']],
            ],
        );

        return 0;
    }
}
