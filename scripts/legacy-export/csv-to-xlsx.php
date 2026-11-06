<?php

// Second step of the legacy export (see export-mdb.ps1): turns the CSVs into the workbooks that
// app:import-legacy-data reads, then deletes the CSVs. Prints row counts only.

require __DIR__.'/../../vendor/autoload.php';

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

$target = dirname(__DIR__, 2).'/storage/app/legacy-export';
$csv = $target.'/csv';

$workbooks = [
    '1_العملاء_وجهات_الاتصال.xlsx' => ['العملاء_وجهات_الاتصال' => 'adres__tel'],
    '2_كتالوج_الأصناف_وقطع_الغيار.xlsx' => [
        'الأصناف_وقطع_الغيار' => 'all__main',
        'مواقع_الأصناف_بالمخزن' => 'all__main2',
        'المخازن' => 'all__stor',
        'الأقسام_والأنواع' => 'all__kind',
    ],
    '3_فواتير_المبيعات_الجملة.xlsx' => [
        'بنود_المبيعات_والفواتير' => 'invo_w__sadr',
        'سندات_التحصيل_والدفعات' => 'invo_w__seq',
        'الخصومات' => 'invo_w__diss',
        'المرتجعات' => 'invo_w__return',
        'سندات_الصرف' => 'invo_w__sarf',
    ],
    '4_فواتير_المبيعات_القطاعي.xlsx' => [
        'بنود_فواتير_القطاعي' => 'invo_s__sadr',
        'التحصيلات_النقدية' => 'invo_s__seq',
        'المرتجعات' => 'invo_s__return',
        'سندات_الصرف' => 'invo_s__sarf',
    ],
    '5_الموظفين_والمصروفات.xlsx' => [
        'الموظفين' => 'eaco__emp',
        'الحضور_والانصراف' => 'eaco__hodor',
        'المصروفات' => 'eaco__cost',
    ],
];

foreach ($workbooks as $file => $sheets) {
    $writer = new Writer;
    $writer->openToFile($target.'/'.$file);

    $first = true;
    foreach ($sheets as $sheet => $source) {
        if (! $first) {
            $writer->addNewSheetAndMakeItCurrent();
        }
        $writer->getCurrentSheet()->setName($sheet);
        $first = false;

        $handle = fopen($csv.'/'.$source.'.csv', 'r');
        if ($handle === false) {
            throw new RuntimeException("Missing export: {$source}.csv");
        }

        $rows = -1;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $writer->addRow(Row::fromValues(array_map(fn ($cell) => (string) $cell, $cells)));
            $rows++;
        }
        fclose($handle);

        echo "{$source}: {$rows} rows\n";
    }

    $writer->close();
}

foreach (glob($csv.'/*.csv') as $path) {
    unlink($path);
}
rmdir($csv);
