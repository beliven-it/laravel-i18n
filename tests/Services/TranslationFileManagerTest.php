<?php

use Beliven\I18n\Services\TranslationFileManager;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->manager = new TranslationFileManager();
    $this->testLangPath = lang_path();
});

afterEach(function () {
    // Clean up test files
    if (File::exists(lang_path('en/test.php'))) {
        File::delete(lang_path('en/test.php'));
    }
    if (File::exists(lang_path('fr/test.php'))) {
        File::delete(lang_path('fr/test.php'));
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

test('fileExists returns false when file does not exist', function () {
    expect($this->manager->fileExists('en', 'nonexistent'))->toBeFalse();
});

test('fileExists returns true when file exists', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/test.php'), '<?php return ["key" => "value"];');

    expect($this->manager->fileExists('en', 'test'))->toBeTrue();
});

test('keyExists returns false when file does not exist', function () {
    expect($this->manager->keyExists('en', 'test', 'some.key'))->toBeFalse();
});

test('keyExists returns false when key does not exist', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/test.php'), '<?php return ["other" => "value"];');

    expect($this->manager->keyExists('en', 'test', 'nonexistent'))->toBeFalse();
});

test('keyExists returns true when key exists', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/test.php'), '<?php return ["welcome" => "Welcome"];');

    expect($this->manager->keyExists('en', 'test', 'welcome'))->toBeTrue();
});

test('keyExists works with nested keys', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/test.php'), '<?php return ["messages" => ["welcome" => "Welcome"]];');

    expect($this->manager->keyExists('en', 'test', 'messages.welcome'))->toBeTrue();
    expect($this->manager->keyExists('en', 'test', 'messages.nothere'))->toBeFalse();
});

test('loadFile returns empty array when file does not exist', function () {
    expect($this->manager->loadFile('en', 'nonexistent'))->toBe([]);
});

test('loadFile returns translations when file exists', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/test.php'), '<?php return ["key" => "value", "nested" => ["item" => "data"]];');

    $result = $this->manager->loadFile('en', 'test');

    expect($result)->toBe([
        'key' => 'value',
        'nested' => ['item' => 'data'],
    ]);
});

test('saveFile creates directory if not exists', function () {
    $this->manager->saveFile('en', 'test', ['key' => 'value']);

    expect(File::exists(lang_path('en')))->toBeTrue();
    expect(File::exists(lang_path('en/test.php')))->toBeTrue();
});

test('saveFile writes translations correctly', function () {
    $this->manager->saveFile('en', 'test', ['welcome' => 'Hello', 'goodbye' => 'Bye']);

    $content = $this->manager->loadFile('en', 'test');

    expect($content)->toBe(['welcome' => 'Hello', 'goodbye' => 'Bye']);
});

test('saveFile handles nested arrays', function () {
    $this->manager->saveFile('en', 'test', [
        'messages' => [
            'welcome' => 'Hello',
            'errors' => [
                'not_found' => 'Not found',
            ],
        ],
    ]);

    $content = $this->manager->loadFile('en', 'test');

    expect($content)->toBe([
        'messages' => [
            'welcome' => 'Hello',
            'errors' => [
                'not_found' => 'Not found',
            ],
        ],
    ]);
});

test('addKey adds new key to existing file', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/test.php'), '<?php return ["existing" => "value"];');

    $this->manager->addKey('en', 'test', 'new_key', 'new value');

    $content = $this->manager->loadFile('en', 'test');

    expect($content)->toHaveKey('existing');
    expect($content)->toHaveKey('new_key');
    expect($content['new_key'])->toBe('new value');
});

test('addKey creates file if not exists', function () {
    $this->manager->addKey('en', 'test', 'key', 'value');

    expect(File::exists(lang_path('en/test.php')))->toBeTrue();
    expect($this->manager->loadFile('en', 'test'))->toBe(['key' => 'value']);
});

test('addKey uses key as default value when value is null', function () {
    $this->manager->addKey('en', 'test', 'my.nested.key');

    $content = $this->manager->loadFile('en', 'test');

    expect($content['my']['nested']['key'])->toBe('my.nested.key');
});

test('addKey handles nested keys', function () {
    $this->manager->addKey('en', 'test', 'messages.welcome', 'Hello');

    $content = $this->manager->loadFile('en', 'test');

    expect($content['messages']['welcome'])->toBe('Hello');
});

test('getFilePath returns correct path', function () {
    $path = $this->manager->getFilePath('en', 'messages');

    expect($path)->toBe(lang_path('en/messages.php'));
});

test('getAvailableLocales returns empty array when lang directory does not exist', function () {
    $tempPath = sys_get_temp_dir() . '/nonexistent_' . uniqid();

    // Mock lang_path to return non-existent directory
    expect($this->manager->getAvailableLocales())->toBeArray();
});

test('getAvailableLocales returns locales excluding vendor', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::ensureDirectoryExists(lang_path('fr'));
    File::ensureDirectoryExists(lang_path('vendor'));

    $locales = $this->manager->getAvailableLocales();

    expect($locales)->toContain('en');
    expect($locales)->toContain('fr');
    expect($locales)->not->toContain('vendor');
});

test('getAllTranslationFiles returns empty array when locale does not exist', function () {
    $files = $this->manager->getAllTranslationFiles('nonexistent');

    expect($files)->toBe([]);
});

test('getAllTranslationFiles returns all PHP files in locale directory', function () {
    File::ensureDirectoryExists(lang_path('en'));
    File::put(lang_path('en/messages.php'), '<?php return [];');
    File::put(lang_path('en/auth.php'), '<?php return [];');

    $files = $this->manager->getAllTranslationFiles('en');

    expect($files)->toContain('messages');
    expect($files)->toContain('auth');
});

test('getAllTranslationFiles handles nested directories', function () {
    File::ensureDirectoryExists(lang_path('en/clinic'));
    File::put(lang_path('en/clinic/detail.php'), '<?php return [];');

    $files = $this->manager->getAllTranslationFiles('en');

    expect($files)->toContain('clinic/detail');
});

test('flattenArray flattens simple array', function () {
    $input = ['key1' => 'value1', 'key2' => 'value2'];

    $result = $this->manager->flattenArray($input);

    expect($result)->toBe(['key1' => 'value1', 'key2' => 'value2']);
});

test('flattenArray flattens nested array', function () {
    $input = [
        'level1' => [
            'level2' => [
                'level3' => 'value',
            ],
        ],
    ];

    $result = $this->manager->flattenArray($input);

    expect($result)->toBe(['level1.level2.level3' => 'value']);
});

test('flattenArray handles mixed depth', function () {
    $input = [
        'simple' => 'value1',
        'nested' => [
            'key' => 'value2',
            'deeper' => [
                'key' => 'value3',
            ],
        ],
    ];

    $result = $this->manager->flattenArray($input);

    expect($result)->toBe([
        'simple' => 'value1',
        'nested.key' => 'value2',
        'nested.deeper.key' => 'value3',
    ]);
});

test('unflattenArray converts flat array to nested', function () {
    $input = [
        'level1.level2.level3' => 'value',
        'simple' => 'value2',
    ];

    $result = $this->manager->unflattenArray($input);

    expect($result)->toBe([
        'level1' => [
            'level2' => [
                'level3' => 'value',
            ],
        ],
        'simple' => 'value2',
    ]);
});

test('flattenArray and unflattenArray are inverse operations', function () {
    $original = [
        'messages' => [
            'welcome' => 'Hello',
            'errors' => [
                'not_found' => 'Not found',
                'unauthorized' => 'Unauthorized',
            ],
        ],
        'simple' => 'value',
    ];

    $flattened = $this->manager->flattenArray($original);
    $unflattened = $this->manager->unflattenArray($flattened);

    expect($unflattened)->toBe($original);
});

// JSON File Tests
test('jsonFileExists returns false when JSON file does not exist', function () {
    expect($this->manager->jsonFileExists('en'))->toBeFalse();
});

test('jsonFileExists returns true when JSON file exists', function () {
    File::put(lang_path('en.json'), '{"Welcome": "Hello"}');

    expect($this->manager->jsonFileExists('en'))->toBeTrue();
});

test('loadJsonFile returns empty array when file does not exist', function () {
    expect($this->manager->loadJsonFile('en'))->toBe([]);
});

test('loadJsonFile returns translations when file exists', function () {
    File::put(lang_path('en.json'), '{"Welcome": "Hello", "Goodbye": "Bye"}');

    $result = $this->manager->loadJsonFile('en');

    expect($result)->toBe(['Welcome' => 'Hello', 'Goodbye' => 'Bye']);
});

test('saveJsonFile creates file with correct format', function () {
    $this->manager->saveJsonFile('en', ['Welcome' => 'Hello', 'Goodbye' => 'Bye']);

    expect(File::exists(lang_path('en.json')))->toBeTrue();
    
    $content = $this->manager->loadJsonFile('en');
    expect($content)->toBe(['Welcome' => 'Hello', 'Goodbye' => 'Bye']);
});

test('saveJsonFile handles unicode characters', function () {
    $this->manager->saveJsonFile('fr', ['Bienvenue' => 'Bienvenue', 'Café' => 'Café']);

    $content = $this->manager->loadJsonFile('fr');
    expect($content)->toBe(['Bienvenue' => 'Bienvenue', 'Café' => 'Café']);
});

test('addJsonKey adds new key to existing JSON file', function () {
    File::put(lang_path('en.json'), '{"Existing": "Value"}');

    $this->manager->addJsonKey('en', 'New Key', 'New Value');

    $content = $this->manager->loadJsonFile('en');
    expect($content)->toHaveKey('Existing');
    expect($content)->toHaveKey('New Key');
    expect($content['New Key'])->toBe('New Value');
});

test('addJsonKey creates file if not exists', function () {
    $this->manager->addJsonKey('en', 'Welcome', 'Hello');

    expect(File::exists(lang_path('en.json')))->toBeTrue();
    expect($this->manager->loadJsonFile('en'))->toBe(['Welcome' => 'Hello']);
});

test('addJsonKey uses key as default value when value is null', function () {
    $this->manager->addJsonKey('en', 'My Translation');

    $content = $this->manager->loadJsonFile('en');
    expect($content['My Translation'])->toBe('My Translation');
});

test('jsonKeyExists returns false when file does not exist', function () {
    expect($this->manager->jsonKeyExists('en', 'Some Key'))->toBeFalse();
});

test('jsonKeyExists returns false when key does not exist', function () {
    File::put(lang_path('en.json'), '{"Other": "Value"}');

    expect($this->manager->jsonKeyExists('en', 'Nonexistent'))->toBeFalse();
});

test('jsonKeyExists returns true when key exists', function () {
    File::put(lang_path('en.json'), '{"Welcome": "Hello"}');

    expect($this->manager->jsonKeyExists('en', 'Welcome'))->toBeTrue();
});

test('getJsonFilePath returns correct path', function () {
    $path = $this->manager->getJsonFilePath('en');

    expect($path)->toBe(lang_path('en.json'));
});
