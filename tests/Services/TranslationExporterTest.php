<?php

use Beliven\I18n\Services\TranslationExporter;
use Beliven\I18n\Services\TranslationFileManager;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->fileManager = new TranslationFileManager();
    $this->exporter = new TranslationExporter($this->fileManager);
    $this->outputPath = sys_get_temp_dir() . '/test_export_' . uniqid() . '.xlsx';
});

afterEach(function () {
    if (File::exists($this->outputPath)) {
        File::delete($this->outputPath);
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

test('export creates excel file', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["welcome" => "Hello"];');

    $this->exporter->export($this->outputPath);

    expect(File::exists($this->outputPath))->toBeTrue();
});

test('export returns correct statistics', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["welcome" => "Hello", "goodbye" => "Bye"];');

    $result = $this->exporter->export($this->outputPath);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('locales');
    expect($result)->toHaveKey('rows');
    expect($result)->toHaveKey('file');
    expect($result['locales'])->toBe(1);
    expect($result['rows'])->toBe(2);
    expect($result['file'])->toBe($this->outputPath);
});

test('export handles multiple locales', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::ensureDirectoryExists(lang_path('fr'));
    File::put(lang_path('en/messages.php'), '<?php return ["welcome" => "Hello"];');
    File::put(lang_path('fr/messages.php'), '<?php return ["welcome" => "Bonjour"];');

    $result = $this->exporter->export($this->outputPath);

    expect($result['locales'])->toBe(2);
    expect($result['rows'])->toBe(2);
});

test('export handles nested translations', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return [
        "welcome" => "Hello",
        "errors" => [
            "not_found" => "Not found",
            "unauthorized" => "Unauthorized"
        ]
    ];');

    $result = $this->exporter->export($this->outputPath);

    expect($result['rows'])->toBe(3); // welcome, errors.not_found, errors.unauthorized
});

test('export creates valid excel with correct headers', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["key" => "value"];');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    expect($worksheet->getCell('A1')->getValue())->toBe('Locale');
    expect($worksheet->getCell('B1')->getValue())->toBe('Path');
    expect($worksheet->getCell('C1')->getValue())->toBe('Key');
    expect($worksheet->getCell('D1')->getValue())->toBe('Value');
});

test('export creates valid excel with correct data', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["welcome" => "Hello World"];');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    expect($worksheet->getCell('A2')->getValue())->toBe('en');
    expect($worksheet->getCell('B2')->getValue())->toBe('lang/en/messages.php');
    expect($worksheet->getCell('C2')->getValue())->toBe('welcome');
    expect($worksheet->getCell('D2')->getValue())->toBe('Hello World');
});

test('export handles multiple files in same locale', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["msg" => "Message"];');
    File::put(lang_path('en/auth.php'), '<?php return ["failed" => "Failed"];');

    $result = $this->exporter->export($this->outputPath);

    expect($result['rows'])->toBe(2);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();

    $paths = array_column(array_slice($data, 1), 1);
    expect($paths)->toContain('lang/en/messages.php');
    expect($paths)->toContain('lang/en/auth.php');
});

test('export handles nested directory structure', function () {
    File::ensureDirectoryExists(lang_path('en/clinic'));
    File::put(lang_path('en/clinic/detail.php'), '<?php return ["title" => "Details"];');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    expect($worksheet->getCell('B2')->getValue())->toBe('lang/en/clinic/detail.php');
});

test('export handles null values', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["key" => null];');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    // PhpSpreadsheet returns null for empty cells, not empty string
    expect($worksheet->getCell('D2')->getValue())->toBeNull();
});

test('export handles empty translation files', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/empty.php'), '<?php return [];');

    $result = $this->exporter->export($this->outputPath);

    expect($result['rows'])->toBe(0);
});

test('export formats headers as bold', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["key" => "value"];');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    expect($worksheet->getStyle('A1')->getFont()->getBold())->toBeTrue();
    expect($worksheet->getStyle('B1')->getFont()->getBold())->toBeTrue();
    expect($worksheet->getStyle('C1')->getFont()->getBold())->toBeTrue();
    expect($worksheet->getStyle('D1')->getFont()->getBold())->toBeTrue();
});

test('export handles special characters in translations', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["special" => "Quote: \"test\", Apostrophe: \'test\'"];');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    expect($worksheet->getCell('D2')->getValue())->toBe('Quote: "test", Apostrophe: \'test\'');
});

test('export handles deeply nested arrays', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return [
        "level1" => [
            "level2" => [
                "level3" => [
                    "level4" => "deep value"
                ]
            ]
        ]
    ];');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    expect($worksheet->getCell('C2')->getValue())->toBe('level1.level2.level3.level4');
    expect($worksheet->getCell('D2')->getValue())->toBe('deep value');
});

// JSON Translation Tests
test('export handles JSON translations', function () {
    File::put(lang_path('en.json'), '{"Welcome": "Hello", "Goodbye": "Bye"}');

    $result = $this->exporter->export($this->outputPath);

    expect($result['rows'])->toBe(2);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();

    $paths = array_column(array_slice($data, 1), 1);
    expect($paths)->toContain('lang/en.json');
});

test('export handles both JSON and PHP translations', function () {
    File::put(lang_path('en.json'), '{"Welcome": "Hello"}');
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return ["key" => "value"];');

    $result = $this->exporter->export($this->outputPath);

    expect($result['rows'])->toBe(2);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();
    $data = $worksheet->toArray();

    $paths = array_column(array_slice($data, 1), 1);
    expect($paths)->toContain('lang/en.json');
    expect($paths)->toContain('lang/en/messages.php');
});

test('export JSON translations have correct structure', function () {
    File::put(lang_path('en.json'), '{"My Translation": "Hello World"}');

    $this->exporter->export($this->outputPath);

    $spreadsheet = IOFactory::load($this->outputPath);
    $worksheet = $spreadsheet->getActiveSheet();

    expect($worksheet->getCell('A2')->getValue())->toBe('en');
    expect($worksheet->getCell('B2')->getValue())->toBe('lang/en.json');
    expect($worksheet->getCell('C2')->getValue())->toBe('My Translation');
    expect($worksheet->getCell('D2')->getValue())->toBe('Hello World');
});
