<?php
/**
 * IDs Start from 1 to 20 for first 200k domains. You can use all the way to get 1mil scraped.
 * Each worker parses 10k rows and saves into CSV files (existing files will be overridden).
 *
 * CLI:
 *  - php init_10worker.php <ID:int>
 *
 * For example:
 *  - Worker 1 is responsible for spawning 10 workers from 1...10k inclusively
 *  - Worker 2 is responsible for spawning 10 workers from 10001...20k inclusively
 *  - Worker 20 is spawning 10 workers from 190,001 ..200k
 *
 */

/**
 *  Initiate double amount of workers - for faster processing
 *
 *  When true, assigns range to 2 processes inside the loop.
 */
$doubleWorkers = false;

if (!isset($_SERVER['argv'][1]) || !ctype_digit($_SERVER['argv'][1])) {
    echo 'Error. Usage: php init_10worker.php <ID:int>. Each worker ID process scrapes 10k domains.';
    exit();
}

$workerId = $_SERVER['argv'][1];
$startRow = ($workerId - 1) * 10000 + 1;
$endRow   = $workerId * 10000;
$spawnedWorkers=0;

// Spawn new worker for each 1000 domains, or for each 500 when in double mode.
for ($i = $startRow; $i <= $endRow; $i += 1000) {
    if ($doubleWorkers) {
        $doubleWorkersOffset = 500;
        $startRowWorker1 = $i;
        $startRowWorker2 = $i + 500;
        $spawnedWorkers+=2;
        exec(sprintf('php app_csv.php %s %s > /dev/null &', $startRowWorker1, $doubleWorkersOffset));
        exec(sprintf('php app_csv.php %s %s > /dev/null &', $startRowWorker2, $doubleWorkersOffset));
    } else {
        ++$spawnedWorkers;
        exec(sprintf('php app_csv.php %s > /dev/null &', $i));
    }
}

echo sprintf('Worker init %s initiated, %s workers spawned.%s', $workerId, $spawnedWorkers, PHP_EOL);
