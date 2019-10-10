<?php
/**
 * Mandatory Bludit Stuff. session_start() is called by init.php, Hence this is placed at the
 * top before content.
 *
 * Set PHP max execution time to infinite, since it loops through all files and may
 * take a lot of time if number of total pages is too high
 */
ini_set('max_execution_time', 0);

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
 * Helper function to remove string "<?php defined('BLUDIT')" from db
 * Thanks ComFreek
 * http://stackoverflow.com/questions/7740405/php-delete-the-first-line-of-a-text-and-return-the-rest
 */
function stripFirstLine(string $text)
{
    return substr($text, strpos($text, "\n")+1);
}

/**
 * Usage Example:
 *
 * $results = search($searchQuery);
 * var_dump($results);
 *
 * Overriding Config example:
 * $results = search($searchQuery, [
 *     'searchHiddenContent' => true
 * ]);
 *
 * Score System:
 *
 * title        +15 points
 * tags         +10 points
 * category     +10 points
 * description  +5 points
 * username     +3 points
 * content      +1 point each line
 * If recusiveSearch is enabled. Divide each by 20.
 */
function search(string $searchQuery, array $overrideConfig = [])
{

    // Variable scope fix
    global $site;

    // Init variables
    $entryCount       = 0;
    $resultCount      = 0;
    $resultsArray     = [];
    $validTokensArray = [];
    $response         = [];

    // Config
    $defaultConfig = [
        'showEditLinks' => true,
        // Include scheduled and draft content in results
        'searchHiddenContent' => false,
        // Set minimum characters required for search
        'minimumCharactersAllowed' => 3,
        // Set maximum characters allowed for search
        'maximumCharactersAllowed' => 1000,
        'searchUsername' => true,
        'searchDescription' => true,
        // Recusively search by exploding "hello world" to "hello", "world"
        'enableRecursiveSearch' => true,
        // Only for recursive search tokens
        'enableStopWordFilter' => true,
        // Use InnoDb Stopwords https://mariadb.com/kb/en/mariadb/stopwords/
        // Removed 'about'
        'stopWordsFilter' => [
            'a', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from',
            'how', 'i', 'in', 'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was',
            'what', 'when', 'where', 'who', 'will', 'with', 'und', 'the', 'www'
        ],
    ];

    $config = array_replace($defaultConfig, $overrideConfig);

    // Reject Query if its length is too small
    if (strlen($searchQuery) < $config['minimumCharactersAllowed']) {
        $response['errors'] = 'ERROR: Too few characters. Add more characters to widen search.';
        return $response;
    } elseif (strlen($searchQuery) > $config['maximumCharactersAllowed']) {
        $response['errors'] = "ERROR: Maximum characters exceeded ({$config['maximumCharactersAllowed']}). Shorten your query.";
        return $response;
    }

    // Break the query into several words
    $tokensArray = explode(' ', $searchQuery);
    $enoughTokensAvailable = false;

    foreach ($tokensArray as $token) {
        $trimmedToken = trim($token);
        if (!empty($trimmedToken) && strlen($trimmedToken) >= $config['minimumCharactersAllowed']) {
            /**
             * Tokens with stopWordFilter enabled
             */
            if ($config['enableStopWordFilter']) {
                if (!in_array($trimmedToken, $config['stopWordsFilter'])) {
                    array_push($validTokensArray, $trimmedToken);
                }
            } else {
                /**
                 * Tokens with stopWordFilter disabled
                 */
                array_push($validTokensArray, $trimmedToken);
            }
        }
    }

    // Get Unique values
    $validTokensArray = array_unique($validTokensArray);

    // Use recursive search only when enoughTokensAvailable is true.
    if (count($validTokensArray) > 0) {
        $enoughTokensAvailable = true;
    }

    $json = stripFirstLine(file_get_contents(DB_PAGES));
    $json = json_decode($json);

    foreach ($json as $obj => $values) {
        // Initialise
        $score = 0;
        $results = array();
        // $obj holds the page slug/key

        // Values to search
        $contentPath = PATH_PAGES . $obj . DS . FILENAME; // index.txt
        $title       = $values->title;
        $description = $values->description;
        $username    = $values->username;
        $tagsArray   = (array)$values->tags;
        $category    = $values->category;
        $tagsString  = implode(' ', $tagsArray);
        $type        = $values->type;
        $coverImage  = isset($values->coverImage) ? $values->coverImage : false;
        if ($coverImage) {
            $coverImage = HTML_PATH_UPLOADS . $values->coverImage;
        }

        /**
         * Skip hiddenContent if searchHiddenContent is false
         */
        if (! $config['searchHiddenContent']) {
            $hiddenTypeList = ['scheduled', 'draft'];
            if (in_array($type, $hiddenTypeList)) {
                continue;
            }
        }

        // Search Title
        if (stripos($title, $searchQuery)!==false) {
            array_push($results, "<b>Title:</b> $title<br>");
            $score += 15;
        }

        // Recursive Search Title
        if ($config['enableRecursiveSearch'] && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if (stripos($title, $token)!==false) {
                    array_push($results, "<b>Title:</b> $title<br>");
                    $score += 0.75;
                }
            }
        }

        // Search Description
        if ($config['searchDescription'] && stripos($description, $searchQuery)!==false) {
            array_push($results, '<b>Description:</b>' . htmlspecialchars($description) . '<br>');
            $score += 5;
        }

        // Recursive Search Description
        if ($config['enableRecursiveSearch'] && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if ($config['searchDescription'] && stripos($description, $token)!==false) {
                    array_push($results, '<b>Description:</b>' . htmlspecialchars($description) . '<br>');
                    $score += 0.25;
                }
            }
        }

        // Search Username
        if ($config['searchUsername'] && stripos($username, $searchQuery)!==false) {
            array_push($results, '<b>Username: </b>' . htmlspecialchars($username) . '<br>');
            $score += 3;
        }

        // Recursive Search Username
        if ($config['enableRecursiveSearch'] && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if ($config['searchUsername'] && stripos($username, $token)!==false) {
                    array_push($results, '<b>Username: </b>' . htmlspecialchars($username) . '<br>');
                    $score += 0.15;
                }
            }
        }

        // Search Tags
        if (stripos($tagsString, $searchQuery)!==false) {
            array_push($results, '<b>Tag:</b> ' . htmlspecialchars($tagsString) . '<br>');
            $score += 10;
        }

        // Recursive Search Tags
        if ($config['enableRecursiveSearch'] && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if (stripos($tagsString, $token)!==false) {
                    array_push($results, '<b>Tag:</b> ' . htmlspecialchars($tagsString) . '<br>');
                    $score += 0.5;
                }
            }
        }

        // Search Category
        if (stripos($category, $searchQuery)!==false) {
            array_push($results, "<b>Category:</b> $category<br>");
            $score += 10;
        }

        // Recursive Search Category
        if ($config['enableRecursiveSearch'] && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if (stripos($category, $token)!==false) {
                    array_push($results, "<b>Category:</b> $category<br>");
                    $score += 0.5;
                }
            }
        }

        /**
         * Search Content (Case insensitive)
         * Thanks ghbarratt's | http://stackoverflow.com/questions/8032312/find-specific-text-in-multiple-txt-files-in-php
         */
        foreach (file($contentPath) as $fli => $fl) {
            if (stripos($fl, $searchQuery)!==false) {
                array_push($results, '<b>Line ' . ($fli+1) . '</b> ' . htmlspecialchars($fl) . '<br>');
                $score += 1;
            }

            // Recursive content search
            if ($config['enableRecursiveSearch'] && $enoughTokensAvailable) {
                foreach ($validTokensArray as $token) {
                    if (stripos($fl, $token)!==false) {
                        array_push($results, '<b>Line ' . ($fli+1) . '</b> ' . htmlspecialchars($fl) . '<br>');
                        $score += 0.05;
                    }
                }
            }
            // End Recusive content search
        }

        // Since we use recursive search, there might be duplicate records. Below is to clean it.
        $results = array_unique($results);

        // Increment search pages count
        ++$entryCount;

        // Add to results only if score is not 0
        if ($score != 0) {
            array_push($resultsArray, array(
                'title'      => $title,
                'slug'       => $obj,
                'url'        => DOMAIN_PAGES . $obj,
                'editUrl'    => DOMAIN_ADMIN . 'edit-content' . DS . $obj,
                'results'    => $results,
                'score'      => $score,
                'coverImage' => $coverImage,
                'createdAt'  => $values->date
            ));
            ++$resultCount;
        }
    }

    // Reverse sort based on score - PHP 7+ only. Requires spaceship operator.
    usort($resultsArray, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return [
        'meta' => [
            'showEditLinks'      => $config['showEditLinks'],
            'query'              => $searchQuery,
            'queryTokens'        => $validTokensArray,
            'resultsCount'       => $resultCount,
            'entryCount'         => $entryCount,
            'timeTakenInSeconds' => round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]), 3)
        ],
        'results' => $resultsArray,
    ];
}

function getSiteInfo()
{
    global $site;
    return [
        'siteTitle'  => $site->title(),
        'siteUrl'    => $site->url(),
        'siteFooter' => $site->footer()
    ];
}
