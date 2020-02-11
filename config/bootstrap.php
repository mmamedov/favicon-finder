<?php
/**
 * App bootstrap settings
 */

require __DIR__ . '/../vendor/autoload.php';
$config = require  __DIR__.'/params.php';

ini_set('allow_url_fopen','1');

/**
 * Global exception handler
 */
set_exception_handler(function ($exception) use ($config) {
    /** @var Exception $exception */
    error_log(
        sprintf('%s %s - %s%s',
            date('Y-m-d H:i:s'), __CLASS__, $exception->getMessage(), PHP_EOL)
        , 3, $config['logFilePath']
    );
});
