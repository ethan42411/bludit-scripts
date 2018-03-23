<?php
/**
 * Bludit Scalability Test Script
 *
 * Usage Example:
 *
 * Pass no. of pages needed as argument.
 *
 * php scalabilityTest.php 1000
 */

// Fix Bludit notice in url.class.php
$_SERVER['REQUEST_URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'cli';

/**
 * Get Bludit Config
 */
if (!file_exists('bl-content/databases/site.php')) {
    $base = dirname($_SERVER['SCRIPT_NAME']);
    $base = rtrim($base, '/');
    header('Location:'.$base.'/install.php');
    exit('<a href="./install.php">Install Bludit first.</a>');
}

// Load time init
$loadTime = microtime(true);

// Security constant
define('BLUDIT', true);

// Directory separator
define('DS', DIRECTORY_SEPARATOR);

// PHP paths for init
define('PATH_ROOT', __DIR__.DS);
define('PATH_BOOT', PATH_ROOT.'bl-kernel'.DS.'boot'.DS);

// Init
require(PATH_BOOT.'init.php');

/**
 * Scalability test code starts here:
 */
require_once 'lib/LoremIpsum.php';
$lipsum = new joshtronic\LoremIpsum();

$fakeDataCount = isset($argv[1]) ? intval($argv[1]) : 10;

for ($i = 0; $i < $fakeDataCount; $i++) {
    echo "Generating $i / $fakeDataCount pages" . PHP_EOL;
    $page = [
        'title' => ucfirst($lipsum->words(5)),
        'content' => $lipsum->paragraphs(5)
    ];
    $dbPages->add($page);
}

echo PHP_EOL . "Successfully added $fakeDataCount pages."  . PHP_EOL;
