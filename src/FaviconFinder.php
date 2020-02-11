<?php

namespace App;

use App\Inspector\HeadersInspector;
use App\Inspector\HtmlInspector;

class FaviconFinder
{
    public const CONFIG_OUTPUT_DIRECTORY_PATH = 'outputDirectoryPath';
    public const CONFIG_SEARCH_DOMAINS_CSV_FILEPATH = 'searchDomainsCsvFilePath';
    public const CONFIG_LOG_FILE_PATH = 'logFilePath';
    public const CONFIG_CSV_SEARCH_OFFSET = 'csvSearchOffset';

    // Path relative to the output directory, for saving worker CSV output
    private const WORKER_CSV_DIRECTORY = '/worker_csv/';

    // Path relative to the output directory, for saving worker logs
    private const WORKER_LOG_DIRECTORY = '/worker_log/';

    // Path relative to the output directory, to record worker execution
    private const WORKERS_RUNTIME_LOG_FILE = '/workers.log';

    /**
     * @var array app settings
     */
    private $configuration;

    /**
     * @var string error file log path, default is config value.
     */
    private $errorLogPath;

    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
        $this->setErrorLogPath($this->configValue(self::CONFIG_LOG_FILE_PATH));
    }

    /**
     * Search in bulk and save into CSV. Saved file will be <$start>.csv
     * If file exists, it will be overwritten.
     *
     * @param array $inputCsvData
     * @param int $start For logging purposes only
     * @param int $offset For logging purposes only
     */
    public function bulkSearchAndRecordIntoCsv(array $inputCsvData, int $start, int $offset): void
    {
        $this->setErrorLogPath(
            $this->configValue(self::CONFIG_OUTPUT_DIRECTORY_PATH)
            . self::WORKER_LOG_DIRECTORY . $start . '.log'
        );

        $fp = fopen($this->configValue(self::CONFIG_OUTPUT_DIRECTORY_PATH)
            . self::WORKER_CSV_DIRECTORY . $start . '.csv', 'wb');

        $start_time = microtime(true);
        foreach ($inputCsvData as $input) {
            fputcsv($fp,
                [
                    $input[0], // rank
                    $input[1], // domain
                    $this->findBySingleDomain($input[1]),
                ]
            );
        }
        $elapsedSeconds = microtime(true) - $start_time;
        fclose($fp);

        $this->logBulkRuntime($elapsedSeconds, $start, $offset);
    }


    /**
     * Get Favicon for single domain
     *
     * @param string $domain Domain (i.e. yahoo.com)
     * @return string favicon URL for domain
     */
    public function findBySingleDomain(string $domain) : string
    {
        $faviconUrl = '';
        try {
            $headersInspector = new HeadersInspector();
            $headersInspector->loadByDomain($domain);
            $faviconUrl = $headersInspector->findFavicon();
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
        }
        if (!empty($faviconUrl)) {
            return $faviconUrl;
        }

        try {
            $htmlInspector = new HtmlInspector();
            $htmlInspector->loadByDomain($domain);
            $faviconUrl = $htmlInspector->findFavicon();
        } catch (\Exception $e) {
            $this->logError($e->getMessage());
        }

        return $faviconUrl;
    }

    /**
     * @param string $parameter Configuration key value
     * @return mixed configuration option
     */
    public function configValue(string $parameter)
    {
        return $this->configuration[$parameter] ?? '';
    }

    /**
     * @param string $path Path for error log file
     */
    public function setErrorLogPath(string $path) : void
    {
        $this->errorLogPath = $path;
    }

    /**
     * @return string Error log path
     */
    public function getErrorLogPath(): string
    {
        return $this->errorLogPath;
    }

    /**
     * Log errors
     * In bulk mode, each worker gets it's own log file
     *
     * @param string $message Error message
     */
    private function logError(string $message): void
    {
        error_log(
            sprintf('%s %s%s', date('Y-m-d H:i:s'), $message, PHP_EOL)
            , 3
            , $this->getErrorLogPath()
        );
    }

    /**
     * @param int $seconds execution time for bulk search in seconds
     * @param int $start start index
     * @param int $offset offset (ending row number)
     */
    private function logBulkRuntime(int $seconds, int $start, int $offset): void
    {
        error_log(
            sprintf('[%s] start:%s, offset:%s completed in %ss.%s'
                , \date('Y-m-d H:i:s'), $start, $offset, $seconds, PHP_EOL)
            , 3
            , $this->configValue(self::CONFIG_OUTPUT_DIRECTORY_PATH) . self::WORKERS_RUNTIME_LOG_FILE
        );
    }
}