<?php
/**
 * App Parameters
 */

return [
    // Local Alexa top 1M URL file path, no trailing slash
    'searchDomainsCsvFilePath' => __DIR__.'/../input/top-1m.csv',

    // how many rows assigned to each worker
    'csvSearchOffset' => 1000,

    // Writable directory for CSV output, no trailing slash
    'outputDirectoryPath' => __DIR__.'/../output',

    // Log file path
    'logFilePath' => __DIR__.'/../output/app.log',
];