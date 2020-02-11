<?php
/**
 * Spawns required workers to crawl first 200k rows from domains CSV
 */

// As a precaution require to pass an arbitrary argument
if (!isset($_SERVER['argv'][1])) {
    echo 'Error. Usage: php init_200kworker.php 1';
    exit();
}

for ($i = 1; $i <= 20; $i++) {
    exec(sprintf('php init_10k_worker.php %s > /dev/null &', $i));
}

echo '200k worker initiated ' . PHP_EOL;