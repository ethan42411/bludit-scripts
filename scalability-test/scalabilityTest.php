<?php

/**
 * Bludit Scalability Test Script
 *
 * Usage Example:
 *
 * Pass no. of pages needed as argument.
 *
 * php scalabilityTest.php 1000
 *
 */

// Fix Bludit notice in url.class.php
$_SERVER['REQUEST_URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

/**
 * Get Bludit Config
 */
if (!file_exists(__DIR__ . '/bl-content/databases/site.php')) {
    $base = dirname($_SERVER['SCRIPT_NAME']);
    $base = rtrim($base, '/');
    header('Location:' . $base . '/install.php');
    exit('<a href="./install.php">Install Bludit first.</a>');
}

// Load time init
$loadTime = microtime(true);

// Security constant
define('BLUDIT', true);

// Directory separator
define('DS', DIRECTORY_SEPARATOR);

// PHP paths for init
define('PATH_ROOT', __DIR__ . DS);
define('PATH_BOOT', PATH_ROOT . 'bl-kernel' . DS . 'boot' . DS);

// Init
require(PATH_BOOT . 'init.php');

/**
 * Scalability test code starts here:
 */
require_once __DIR__ . '/lib/LoremIpsum.php';

$fakeDataCount = isset($argv[1]) ? intval($argv[1]) : 10;

function createPages(int $fakeDataCount, string $type = 'published')
{
    $lipsum = new joshtronic\LoremIpsum();
    global $pages;

    /**
     * Create 10 fake tags
     * @var array
     */
    $fakeTags = [];
    for ($i = 0; $i < 10; $i++) {
        $fakeTags[] = ucfirst($lipsum->words(2));
    }

    // Using default Bludit categories from install.php
    $fakeCategories = ['general', 'music', 'videos'];

    for ($i = 1; $i <= $fakeDataCount; $i++) {
        echo "Generating $i / $fakeDataCount $type pages" . PHP_EOL;
        $page = [
            'type' => $type,
            'username' => 'admin',
            'title' => ucfirst($lipsum->words(5)),
            'content' => $lipsum->paragraphs(5),
            'description' => ucfirst($lipsum->words(10)),
            'category' => $fakeCategories[array_rand($fakeCategories)],
            'tags' => $fakeTags[array_rand($fakeTags)]
        ];
        $pages->add($page);
    }
    echo PHP_EOL . "Successfully added $fakeDataCount $type pages."  . str_repeat(PHP_EOL, 2);
}

createPages($fakeDataCount);
// Create 3 static pages to simulate a navbar.
createPages(3, 'static');

/**
 * Reindex manually.
 */
$tags->reindex();
$categories->reindex();
