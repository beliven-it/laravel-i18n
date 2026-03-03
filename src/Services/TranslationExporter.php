<?php

namespace Beliven\I18n\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TranslationExporter
{
    public function __construct(
        protected TranslationFileManager $fileManager,
    ) {}

    /**
     * @return array<string, string|int>
     */
    public function export(string $outputPath): array
    {
        $locales = $this->fileManager->getAvailableLocales();
        $data = $this->collectTranslations($locales);

        $this->writeExcel($data, $outputPath);

        return [
            "locales" => count($locales),
            "rows" => count($data),
            "file" => $outputPath,
        ];
    }

    /**
     * @param  string[]  $locales
     */
    protected function collectTranslations(array $locales): array
    {
        $data = [];

        foreach ($locales as $locale) {
            // Export JSON translations
            if ($this->fileManager->jsonFileExists($locale)) {
                $jsonTranslations = $this->fileManager->loadJsonFile($locale);

                foreach ($jsonTranslations as $key => $value) {
                    $data[] = [
                        "locale" => $locale,
                        "path" => sprintf("lang/%s.json", $locale),
                        "key" => $key,
                        "value" => $value ?? "",
                    ];
                }
            }

            // Export PHP file translations
            $files = $this->fileManager->getAllTranslationFiles($locale);

            foreach ($files as $file) {
                $translations = $this->fileManager->loadFile($locale, $file);
                $flattened = $this->fileManager->flattenArray($translations);

                foreach ($flattened as $key => $value) {
                    $data[] = [
                        "locale" => $locale,
                        "path" => sprintf("lang/%s/%s.php", $locale, $file),
                        "key" => $key,
                        "value" => $value ?? "",
                    ];
                }
            }
        }

        return $data;
    }

    protected function writeExcel(array $data, string $outputPath): void
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();

        // Headers
        $worksheet->setCellValue("A1", "Locale");
        $worksheet->setCellValue("B1", "Path");
        $worksheet->setCellValue("C1", "Key");
        $worksheet->setCellValue("D1", "Value");

        // Style headers
        $worksheet->getStyle("A1:D1")->getFont()->setBold(true);

        // Data
        $row = 2;
        foreach ($data as $item) {
            $worksheet->setCellValue("A" . $row, $item["locale"]);
            $worksheet->setCellValue("B" . $row, $item["path"]);
            $worksheet->setCellValue("C" . $row, $item["key"]);
            $worksheet->setCellValue("D" . $row, $item["value"]);
            $row++;
        }

        // Auto-size columns
        foreach (["A", "B", "C", "D"] as $column) {
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }

        $xlsx = new Xlsx($spreadsheet);
        $xlsx->save($outputPath);
    }
}
