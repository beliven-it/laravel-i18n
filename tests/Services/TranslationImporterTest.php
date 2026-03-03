<?php

use Beliven\I18n\Services\TranslationImporter;
use Beliven\I18n\Services\TranslationFileManager;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->fileManager = new TranslationFileManager();
    $this->importer = new TranslationImporter($this->fileManager);
    $this->inputPath = sys_get_temp_dir() . '/test_import_' . uniqid() . '.xlsx';
    
    $this->createExcelFile = function (string $path, array $data): void {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Headers
        $worksheet->setCellValue('A1', 'Locale');
        $worksheet->setCellValue('B1', 'Path');
        $worksheet->setCellValue('C1', 'Key');
        $worksheet->setCellValue('D1', 'Value');

        // Data
        $row = 2;
        foreach ($data as $item) {
            $worksheet->setCellValue('A' . $row, $item['locale']);
            $worksheet->setCellValue('B' . $row, $item['path']);
            $worksheet->setCellValue('C' . $row, $item['key']);
            $worksheet->setCellValue('D' . $row, $item['value']);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    };
});

afterEach(function () {
    if (File::exists($this->inputPath)) {
        File::delete($this->inputPath);
    }

    if (File::exists(lang_path('en.json'))) {
        File::delete(lang_path('en.json'));
    }
    if (File::exists(lang_path('fr.json'))) {
        File::delete(lang_path('fr.json'));
    }
    if (File::exists(lang_path('en'))) {
        File::deleteDirectory(lang_path('en'));
    }
    if (File::exists(lang_path('fr'))) {
        File::deleteDirectory(lang_path('fr'));
    }
});

test('import creates translation files from excel', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'welcome', 'value' => 'Hello'],
    ]);

    $this->importer->import($this->inputPath);

    expect(File::exists(lang_path('en/messages.php')))->toBeTrue();
});

test('import returns correct statistics for new files', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'welcome', 'value' => 'Hello'],
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'goodbye', 'value' => 'Bye'],
    ]);

    $result = $this->importer->import($this->inputPath);

    expect($result)->toHaveKey('files_created');
    expect($result)->toHaveKey('keys_added');
    expect($result)->toHaveKey('keys_updated');
    expect($result['files_created'])->toBe(1);
    expect($result['keys_added'])->toBe(2);
    expect($result['keys_updated'])->toBe(0);
});

test('import handles multiple locales', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'welcome', 'value' => 'Hello'],
        ['locale' => 'fr', 'path' => 'lang/fr/messages.php', 'key' => 'welcome', 'value' => 'Bonjour'],
    ]);

    $result = $this->importer->import($this->inputPath);

    expect($result['files_created'])->toBe(2);
    expect(File::exists(lang_path('en/messages.php')))->toBeTrue();
    expect(File::exists(lang_path('fr/messages.php')))->toBeTrue();
});

test('import handles multiple files per locale', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'msg', 'value' => 'Message'],
        ['locale' => 'en', 'path' => 'lang/en/auth.php', 'key' => 'failed', 'value' => 'Failed'],
    ]);

    $result = $this->importer->import($this->inputPath);

    expect($result['files_created'])->toBe(2);
    expect(File::exists(lang_path('en/messages.php')))->toBeTrue();
    expect(File::exists(lang_path('en/auth.php')))->toBeTrue();
});

test('import merges with existing translations by default', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["existing" => "Already here"];');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'new_key', 'value' => 'New value'],
    ]);

    $result = $this->importer->import($this->inputPath);

    $content = $this->fileManager->loadFile('en', 'messages');

    expect($result['files_created'])->toBe(0);
    expect($result['keys_added'])->toBe(1);
    expect($content)->toHaveKey('existing');
    expect($content)->toHaveKey('new_key');
});

test('import updates existing keys when merging', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["key" => "old value"];');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'key', 'value' => 'new value'],
    ]);

    $result = $this->importer->import($this->inputPath);

    $content = $this->fileManager->loadFile('en', 'messages');

    expect($result['keys_updated'])->toBe(1);
    expect($content['key'])->toBe('new value');
});

test('import replaces file when overwrite is true', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["existing" => "Already here"];');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'new_key', 'value' => 'New value'],
    ]);

    $this->importer->import($this->inputPath, overwrite: true);

    $content = $this->fileManager->loadFile('en', 'messages');

    expect($content)->not->toHaveKey('existing');
    expect($content)->toHaveKey('new_key');
});

test('import handles nested keys', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'errors.not_found', 'value' => 'Not found'],
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'errors.unauthorized', 'value' => 'Unauthorized'],
    ]);

    $this->importer->import($this->inputPath);

    $content = $this->fileManager->loadFile('en', 'messages');

    expect($content['errors']['not_found'])->toBe('Not found');
    expect($content['errors']['unauthorized'])->toBe('Unauthorized');
});

test('import handles nested directory structure', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/clinic/detail.php', 'key' => 'title', 'value' => 'Details'],
    ]);

    $this->importer->import($this->inputPath);

    expect(File::exists(lang_path('en/clinic/detail.php')))->toBeTrue();
    $content = $this->fileManager->loadFile('en', 'clinic/detail');
    expect($content['title'])->toBe('Details');
});

test('import handles empty values', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'empty', 'value' => ''],
    ]);

    $this->importer->import($this->inputPath);

    $content = $this->fileManager->loadFile('en', 'messages');
    expect($content['empty'])->toBe('');
});

test('import skips rows with missing required fields', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'valid', 'value' => 'Valid'],
        ['locale' => '', 'path' => 'lang/en/messages.php', 'key' => 'no_locale', 'value' => 'Skip this'],
        ['locale' => 'en', 'path' => '', 'key' => 'no_path', 'value' => 'Skip this'],
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => '', 'value' => 'Skip this'],
    ]);

    $result = $this->importer->import($this->inputPath);

    expect($result['keys_added'])->toBe(1);
    $content = $this->fileManager->loadFile('en', 'messages');
    expect($content)->toHaveKey('valid');
    expect($content)->not->toHaveKey('no_locale');
    expect($content)->not->toHaveKey('no_path');
});

test('import handles deeply nested translations', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'a.b.c.d.e', 'value' => 'deep'],
    ]);

    $this->importer->import($this->inputPath);

    $content = $this->fileManager->loadFile('en', 'messages');
    expect($content['a']['b']['c']['d']['e'])->toBe('deep');
});

test('import statistics correctly count updates vs additions', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return [
        "existing1" => "value1",
        "existing2" => "value2"
    ];');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'existing1', 'value' => 'updated'],
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'new1', 'value' => 'new'],
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'new2', 'value' => 'new'],
    ]);

    $result = $this->importer->import($this->inputPath);

    expect($result['keys_updated'])->toBe(1);
    expect($result['keys_added'])->toBe(2);
});

test('import extracts file correctly from various path formats', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'k1', 'value' => 'v1'],
        ['locale' => 'en', 'path' => 'lang/en/clinic/detail.php', 'key' => 'k2', 'value' => 'v2'],
        ['locale' => 'en', 'path' => 'lang/en/deep/nested/path.php', 'key' => 'k3', 'value' => 'v3'],
    ]);

    $this->importer->import($this->inputPath);

    expect(File::exists(lang_path('en/messages.php')))->toBeTrue();
    expect(File::exists(lang_path('en/clinic/detail.php')))->toBeTrue();
    expect(File::exists(lang_path('en/deep/nested/path.php')))->toBeTrue();
});

test('import with overwrite counts all keys as added', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["existing" => "old"];');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'key1', 'value' => 'v1'],
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'key2', 'value' => 'v2'],
    ]);

    $result = $this->importer->import($this->inputPath, overwrite: true);

    expect($result['keys_added'])->toBe(2);
    expect($result['keys_updated'])->toBe(0);
});

// JSON Translation Tests
test('import creates JSON translation files from excel', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en.json', 'key' => 'Welcome', 'value' => 'Hello'],
        ['locale' => 'en', 'path' => 'lang/en.json', 'key' => 'Goodbye', 'value' => 'Bye'],
    ]);

    $this->importer->import($this->inputPath);

    expect(File::exists(lang_path('en.json')))->toBeTrue();
    $content = $this->fileManager->loadJsonFile('en');
    expect($content)->toBe(['Welcome' => 'Hello', 'Goodbye' => 'Bye']);
});

test('import handles mixed JSON and PHP translations', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en.json', 'key' => 'Welcome', 'value' => 'Hello'],
        ['locale' => 'en', 'path' => 'lang/en/messages.php', 'key' => 'greeting', 'value' => 'Hi'],
    ]);

    $result = $this->importer->import($this->inputPath);

    expect($result['files_created'])->toBe(2);
    expect(File::exists(lang_path('en.json')))->toBeTrue();
    expect(File::exists(lang_path('en/messages.php')))->toBeTrue();
});

test('import merges with existing JSON translations by default', function () {
    File::put(lang_path('en.json'), '{"Existing": "Already here"}');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en.json', 'key' => 'New Key', 'value' => 'New value'],
    ]);

    $result = $this->importer->import($this->inputPath);

    $content = $this->fileManager->loadJsonFile('en');

    expect($result['files_created'])->toBe(0);
    expect($result['keys_added'])->toBe(1);
    expect($content)->toHaveKey('Existing');
    expect($content)->toHaveKey('New Key');
});

test('import updates existing JSON keys when merging', function () {
    File::put(lang_path('en.json'), '{"Welcome": "old value"}');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en.json', 'key' => 'Welcome', 'value' => 'new value'],
    ]);

    $result = $this->importer->import($this->inputPath);

    $content = $this->fileManager->loadJsonFile('en');

    expect($result['keys_updated'])->toBe(1);
    expect($content['Welcome'])->toBe('new value');
});

test('import replaces JSON file when overwrite is true', function () {
    File::put(lang_path('en.json'), '{"Existing": "Already here"}');

    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en.json', 'key' => 'New Key', 'value' => 'New value'],
    ]);

    $this->importer->import($this->inputPath, overwrite: true);

    $content = $this->fileManager->loadJsonFile('en');

    expect($content)->not->toHaveKey('Existing');
    expect($content)->toHaveKey('New Key');
});

test('import handles special characters in JSON translations', function () {
    ($this->createExcelFile)($this->inputPath, [
        ['locale' => 'en', 'path' => 'lang/en.json', 'key' => 'Quote Test', 'value' => 'He said "hello"'],
        ['locale' => 'fr', 'path' => 'lang/fr.json', 'key' => 'Café', 'value' => 'Un café s\'il vous plaît'],
    ]);

    $this->importer->import($this->inputPath);

    $enContent = $this->fileManager->loadJsonFile('en');
    $frContent = $this->fileManager->loadJsonFile('fr');

    expect($enContent['Quote Test'])->toBe('He said "hello"');
    expect($frContent['Café'])->toBe('Un café s\'il vous plaît');
});
