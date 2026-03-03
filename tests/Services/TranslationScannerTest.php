<?php

use Beliven\I18n\Services\TranslationScanner;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->scanner = new TranslationScanner();
    $this->testPath = 'test_scan_' . uniqid();
    $this->testFullPath = base_path($this->testPath);
});

afterEach(function () {
    if (File::exists($this->testFullPath)) {
        File::deleteDirectory($this->testFullPath);
    }
});

test('scan returns empty array when no translation keys found', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php echo "no translations here";');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toBe([]);
});

test('scan finds __ function calls', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php echo __("welcome.message");');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('welcome.message');
    expect($result['welcome.message'])->toContain($this->testFullPath . '/test.php');
});

test('scan finds trans function calls', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php echo trans("auth.failed");');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('auth.failed');
});

test('scan finds @lang directive calls', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/view.php', '@lang("messages.welcome")');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('messages.welcome');
});

test('scan finds multiple keys in same file', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php
        echo __("key1");
        echo __("key2");
        echo trans("key3");
    ');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('key1');
    expect($result)->toHaveKey('key2');
    expect($result)->toHaveKey('key3');
});

test('scan handles single quotes', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', "<?php echo __('messages.hello');");

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('messages.hello');
});

test('scan handles double quotes', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php echo __("messages.hello");');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('messages.hello');
});

test('scan processes multiple files', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/file1.php', '<?php echo __("key.in.file1");');
    File::put($this->testFullPath . '/file2.php', '<?php echo __("key.in.file2");');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('key.in.file1');
    expect($result)->toHaveKey('key.in.file2');
});

test('scan handles nested directories', function () {
    File::ensureDirectoryExists($this->testFullPath . '/subdir');
    File::put($this->testFullPath . '/subdir/test.php', '<?php echo __("nested.key");');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('nested.key');
});

test('scan tracks multiple occurrences of same key', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/file1.php', '<?php echo __("shared.key");');
    File::put($this->testFullPath . '/file2.php', '<?php echo __("shared.key");');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result['shared.key'])->toHaveCount(2);
    expect($result['shared.key'])->toContain($this->testFullPath . '/file1.php');
    expect($result['shared.key'])->toContain($this->testFullPath . '/file2.php');
});

test('scan does not duplicate file paths for same key in same file', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php
        echo __("repeated");
        echo __("repeated");
        echo __("repeated");
    ');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result['repeated'])->toHaveCount(1);
});

test('scan only processes PHP files', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php echo __("php.key");');
    File::put($this->testFullPath . '/test.txt', '__("txt.key")');
    File::put($this->testFullPath . '/test.html', '__("html.key")');

    $result = $this->scanner->scan([$this->testPath]);

    expect($result)->toHaveKey('php.key');
    expect($result)->not->toHaveKey('txt.key');
    expect($result)->not->toHaveKey('html.key');
});

test('scan handles multiple paths', function () {
    $path1 = 'test_scan_1_' . uniqid();
    $path2 = 'test_scan_2_' . uniqid();
    $fullPath1 = base_path($path1);
    $fullPath2 = base_path($path2);

    File::ensureDirectoryExists($fullPath1);
    File::ensureDirectoryExists($fullPath2);
    File::put($fullPath1 . '/test.php', '<?php echo __("key.from.path1");');
    File::put($fullPath2 . '/test.php', '<?php echo __("key.from.path2");');

    $result = $this->scanner->scan([$path1, $path2]);

    expect($result)->toHaveKey('key.from.path1');
    expect($result)->toHaveKey('key.from.path2');

    File::deleteDirectory($fullPath1);
    File::deleteDirectory($fullPath2);
});

test('scan skips non-existent paths', function () {
    File::ensureDirectoryExists($this->testFullPath);
    File::put($this->testFullPath . '/test.php', '<?php echo __("existing.key");');

    $result = $this->scanner->scan([$this->testPath, 'nonexistent_path_' . uniqid()]);

    expect($result)->toHaveKey('existing.key');
});

test('parseKey handles simple key without dots', function () {
    $result = $this->scanner->parseKey('Welcome');

    expect($result)->toBe([
        'type' => 'json',
        'file' => null,
        'key' => 'Welcome',
    ]);
});

test('parseKey handles key with single dot', function () {
    $result = $this->scanner->parseKey('messages.welcome');

    expect($result)->toBe([
        'type' => 'php',
        'file' => 'messages',
        'key' => 'welcome',
    ]);
});

test('parseKey handles key with multiple dots', function () {
    $result = $this->scanner->parseKey('messages.errors.not_found');

    expect($result)->toBe([
        'type' => 'php',
        'file' => 'messages',
        'key' => 'errors.not_found',
    ]);
});

test('parseKey handles file path with dots', function () {
    $result = $this->scanner->parseKey('clinic/detail.welcome.message');

    expect($result)->toBe([
        'type' => 'php',
        'file' => 'clinic/detail',
        'key' => 'welcome.message',
    ]);
});
