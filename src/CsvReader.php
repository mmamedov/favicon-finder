<?php

namespace App;

class CsvReader
{
    private $inputFile;
    private $csvArray;

    // domain + rank should not exceed this length
    private const CSV_MAX_LINE_LENGTH = 255;

    public function __construct(string $file)
    {
        $this->inputFile = $file;
    }

    /**
     * @return array
     */
    public function getDataArray() : array
    {
        return $this->csvArray ?? [];
    }

    /**
     * Load rows from CSV, $offset many records from $start index. (start row = 1)
     * File is large, to save memory we only load relevant rows.
     *
     * @param int $start
     * @param int $offset
     * @throws \Exception
     */
    public function loadRange(int $start, int $offset): void
    {
        if ($start < 1 || $offset < 1)
        {
            throw new \Exception('$start and $offset must be 1 or greater');
        }

        $handle = fopen($this->inputFile, 'rb');
        if ($handle === false) {
            throw new \Exception(
                sprintf('CSV file could not be loaded from %s with offset %s: %s', $start, $offset, $this->inputFile)
            );
        }

        $currentRow = 0;
        while (($data = fgetcsv($handle, self::CSV_MAX_LINE_LENGTH, ',')) !== false) {
            ++$currentRow;
            if ($currentRow < $start) {
                continue;
            }
            if ($offset < 1) {
                break;
            }
            $this->csvArray[] = $data;
            --$offset;
        }
        fclose($handle);
    }
}