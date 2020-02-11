<?php
/**
 * CSV domain finder
 *
 * cli app:
 * php app_csv.php <start_csv_row> <offset:optional>
 * - example: php app.php 1 //reads default $offset amount from CSV and saves into 1.csv
 * - existing CSV files are overridden
 *
 * cli test native headers:
 * php -r "print_r(get_headers('https://yahoo.com',1));"
 *
 */

require __DIR__ . '/config/bootstrap.php';

/**
 * @var array $config Configuration loaded in bootstrap
 */

use App\CsvReader;
use App\FaviconFinder;

if (!isset($_SERVER['argv'][1]) || !ctype_digit($_SERVER['argv'][1])) {
    echo 'Error! Usage - php app_csv.php <start_csv_row>' . PHP_EOL;
    exit();
}

// Do we have incoming offset value, if not it will be read from config file
$offset = null;
if (isset($_SERVER['argv'][2]) && ctype_digit($_SERVER['argv'][2])) {
    $offset = $_SERVER['argv'][2];
}

$faviconFinder = new FaviconFinder($config);

$importCsvFile = $faviconFinder->configValue(FaviconFinder::CONFIG_SEARCH_DOMAINS_CSV_FILEPATH);
$offset = $offset ?? $faviconFinder->configValue(FaviconFinder::CONFIG_CSV_SEARCH_OFFSET);
$startFromRow = $_SERVER['argv'][1];

$csvReader = new CsvReader($importCsvFile);
$csvReader->loadRange($startFromRow, $offset);

$faviconFinder->bulkSearchAndRecordIntoCsv($csvReader->getDataArray(), $startFromRow, $offset);

unset($csvReader, $faviconFinder);
exit();

