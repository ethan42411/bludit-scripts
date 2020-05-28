<?php

require __DIR__ . '/vendor/autoload.php';

use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;

$parser = new JsonParser();

function validateDate(string $date, string $format = 'Y-m-d'): bool
{
    $d = DateTime::createFromFormat($format, $date);
    // The Y ( 4 digits year ) returns TRUE for any integer with any number of digits so changing the comparison from == to === fixes the issue.
    return $d && $d->format($format) === $date;
}

/**
 * Iterate through each file in repos/*, Find .json files and lint them
 */
$directoryToSearch = __DIR__ . '/repos';
$it = new RecursiveDirectoryIterator($directoryToSearch);
$allowed = ['json'];
foreach (new RecursiveIteratorIterator($it) as $file) {
    if (in_array(substr($file, strrpos($file, '.') + 1), $allowed)) {
        $friendlyFileName = str_replace($directoryToSearch, '', $file);

        /**
         * Find broken json
         */
        try {
            $parser->parse(file_get_contents($file), JsonParser::DETECT_KEY_CONFLICTS);
        } catch (ParsingException $e) {
            $exceptionType = get_class($e);
            echo "PROBLEM $exceptionType : $friendlyFileName " . PHP_EOL;
            $details = $e->getDetails();
            echo json_encode($details, JSON_PRETTY_PRINT);
            echo PHP_EOL;
        }

        /**
         * Check for broken release_date
         */
        $releaseDateCheckDirectories = [
            'plugins-repository/items',
            'themes-repository/items',
        ];
        foreach ($releaseDateCheckDirectories as $dir) {
            if (strpos($file, $dir) !== false) {
                $json = json_decode(file_get_contents($file), true);
                if (isset($json['release_date'])) {
                    if (!validateDate($json['release_date'])) {
                        echo "Invalid release_date Found in file: $friendlyFileName" . PHP_EOL;
                    }
                }
            }
        }
    }
}
