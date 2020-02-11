<?php
/**
 * Single domain favicon finder
 *
 * cli app:
 * php app.php <domain>
 * - example: php app.php facebook.com
 *
 * cli test native headers:
 * php -r "print_r(get_headers('https://yahoo.com',1));"
 *
 */

require __DIR__ . '/config/bootstrap.php';

/**
 * @var array $config Configuration loaded in bootstrap
 */

use App\FaviconFinder;

if (!isset($_SERVER['argv'][1])) {
    echo 'Error! Usage: php app.php <domain>' . PHP_EOL;
    exit();
}

$faviconFinder = new FaviconFinder($config);
echo sprintf('Favicon for %s: %s%s'
    , $_SERVER['argv'][1]
    , $faviconFinder->findBySingleDomain($_SERVER['argv'][1])
    , PHP_EOL);

