<?php

namespace Beliven\I18n\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class TranslationFileManager
{
    public function jsonFileExists(string $locale): bool
    {
        return File::exists($this->getJsonFilePath($locale));
    }

    public function loadJsonFile(string $locale): array
    {
        $path = $this->getJsonFilePath($locale);

        if (!File::exists($path)) {
            return [];
        }

        $content = File::get($path);
        return json_decode($content, true) ?? [];
    }

    public function saveJsonFile(string $locale, array $translations): void
    {
        $path = $this->getJsonFilePath($locale);
        $directory = dirname($path);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        File::put($path, $content);
    }

    public function addJsonKey(string $locale, string $key, ?string $value = null): void
    {
        $translations = $this->loadJsonFile($locale);
        $translations[$key] = $value ?? $key;
        $this->saveJsonFile($locale, $translations);
    }

    public function jsonKeyExists(string $locale, string $key): bool
    {
        $translations = $this->loadJsonFile($locale);
        return array_key_exists($key, $translations);
    }

    public function getJsonFilePath(string $locale): string
    {
        return lang_path(sprintf("%s.json", $locale));
    }

    public function fileExists(string $locale, string $file): bool
    {
        return File::exists($this->getFilePath($locale, $file));
    }

    public function keyExists(string $locale, string $file, string $key): bool
    {
        if (!$this->fileExists($locale, $file)) {
            return false;
        }

        $translations = $this->loadFile($locale, $file);

        return Arr::has($translations, $key);
    }

    public function loadFile(string $locale, string $file): array
    {
        $path = $this->getFilePath($locale, $file);

        if (!File::exists($path)) {
            return [];
        }

        return include $path;
    }

    public function saveFile(
        string $locale,
        string $file,
        array $translations,
    ): void {
        $path = $this->getFilePath($locale, $file);
        $directory = dirname($path);

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $content = $this->arrayToPhpFile($translations);
        File::put($path, $content);

        // Run Pint to format the file
        $this->runPint($path);
    }

    public function addKey(
        string $locale,
        string $file,
        string $key,
        mixed $value = null,
    ): void {
        $translations = $this->loadFile($locale, $file);
        // Use the key itself as default value if no value provided
        Arr::set($translations, $key, $value ?? $key);
        $this->saveFile($locale, $file, $translations);
    }

    public function getFilePath(string $locale, string $file): string
    {
        return lang_path(sprintf("%s/%s.php", $locale, $file));
    }

    /**
     * @return string[]
     */
    public function getAvailableLocales(): array
    {
        $langPath = lang_path();
        $locales = [];

        if (!File::exists($langPath)) {
            return $locales;
        }

        // Get locales from directories
        foreach (File::directories($langPath) as $directory) {
            $locale = basename((string) $directory);
            if ($locale !== "vendor") {
                $locales[] = $locale;
            }
        }

        // Get locales from JSON files
        foreach (File::glob($langPath . '/*.json') as $jsonFile) {
            $locale = basename($jsonFile, '.json');
            if (!in_array($locale, $locales)) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    public function getAllTranslationFiles(string $locale): array
    {
        $localePath = lang_path($locale);
        $files = [];

        if (!File::exists($localePath)) {
            return $files;
        }

        $allFiles = File::allFiles($localePath);

        foreach ($allFiles as $allFile) {
            $relativePath = str_replace(
                $localePath . "/",
                "",
                $allFile->getPathname(),
            );
            $relativePath = str_replace(".php", "", $relativePath);
            $files[] = $relativePath;
        }

        return $files;
    }

    public function flattenArray(array $array, string $prefix = ""): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === "" ? $key : sprintf("%s.%s", $prefix, $key);

            if (is_array($value)) {
                $result = array_merge(
                    $result,
                    $this->flattenArray($value, $newKey),
                );
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    public function unflattenArray(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            Arr::set($result, $key, $value);
        }

        return $result;
    }

    protected function arrayToPhpFile(array $array, int $indent = 0): string
    {
        $content = $indent === 0 ? "<?php\n\nreturn " : "";

        $content .= "[\n";

        foreach ($array as $key => $value) {
            $spaces = str_repeat("    ", $indent + 1);
            $content .= $spaces . var_export($key, true) . " => ";

            if (is_array($value)) {
                $content .= $this->arrayToPhpFile($value, $indent + 1);
            } else {
                $content .= var_export($value, true);
            }

            $content .= ",\n";
        }

        $spaces = str_repeat("    ", $indent);
        $content .= $spaces . "]";

        if ($indent === 0) {
            $content .= ";\n";
        }

        return $content;
    }

    protected function runPint(string $filePath): void
    {
        $pintPath = base_path("vendor/bin/pint");

        if (File::exists($pintPath)) {
            Process::run([$pintPath, $filePath, "--quiet"]);
        }
    }
}
