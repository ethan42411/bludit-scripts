<?php
/* Mandatory Bludit Stuff. session_start() is called by init.php, Hence this is placed at the top before content. */

/* Set PHP max execution time to infinite, since it loops through all files and may take a lot of time if
   number of total posts+pages is too high */
ini_set('max_execution_time', 0);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Bludit Search</title>
    <!-- Responsive -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body{
    max-width:960px;
    margin: 0 auto;
    padding: 10px 5px;
}

.result{
    margin-bottom: 23px;
    box-shadow: 0 0 5px #000;
    padding: 5px 10px;
}

.title{
    font-size:1.1em;
    border-bottom: 1px solid grey;
    margin: 0;
}

.edit, .edit a{
    font-size:small;
    color:grey;
}

.highlight{
    background-color: yellow;
}

.error{
    color:tomato;
}

a{
    text-decoration:none;
}
</style>
</head>

<body>
<?php
// Config
$minimumCharactersAllowed = 3; // Reasonable default to avoid searches like 'aaa'
$searchUsername = true;
$searchDescription = true;
$enableRecursiveSearch = true; // Recusively search by exploding "hello world" to "hello", "world"
$enableStopWordFilter = true;
// Use InnoDb Stopwords https://mariadb.com/kb/en/mariadb/stopwords/
$stopWordsFilter = array('a', 'about', 'an', 'are', 'as', 'at', 'be', 'by', 'com', 'de', 'en', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'la', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'the', 'www');

// Initialise Variables like search count
$entryCount = 0;
$resultCount = 0;
$resultsArray = array();
$postsDb = PATH_DATABASES . 'posts.php';
$pagesDb = PATH_DATABASES . 'pages.php';
$validTokensArray = array();

// Remove first line function. Helper function to remove string "<?php defined('BLUDIT')" from db
// Thanks ComFreek http://stackoverflow.com/questions/7740405/php-delete-the-first-line-of-a-text-and-return-the-rest
function stripFirstLine($text)
{
    return substr( $text, strpos($text, "\n")+1 );
}

// START Search function:

// Usage Example:
// search($pagesDb, "pages");
// search($postsDb, "posts");
// displayResults() must be called after using this function.

// Score system:
// title		+15 points
// tags			+10 points
// description	+5 points
// username		+3 points
// content		+1 point each line
// If recusiveSearch is enabled. Divide each by 20.

function search($db, $contentType)
{

    // Variable scope fix
    global $entryCount, $resultCount, $searchQuery, $resultsArray, $searchUsername, $searchDescription, $minimumCharactersAllowed, $enableRecursiveSearch, $enableStopWordFilter, $stopWordsFilter, $validTokensArray;

    // Reject Query if it's length is too small
    if (strlen($searchQuery) < $minimumCharactersAllowed) {
        echo "<p class='error'>ERROR: Too few characters. Add more characters to widen search.</p>";
        exit;
    }

    // Reject Query if function is called in the wrong way
    $type = $contentType;
    // contentType check
    $validTypes = array("pages", "posts");
    if (in_array($type, $validTypes) == false) {
        echo "<p class='error'>ERROR: Invalid search function parameter. Try pages or posts.</p>";
        exit;
    }

    // Break the query into several words
    $tokensArray = explode(' ', $searchQuery);
    $enoughTokensAvailable = false;

    foreach ($tokensArray as $token) {
        $trimmedToken = trim($token);
        if (!empty($trimmedToken) && strlen($trimmedToken) >= $minimumCharactersAllowed) {
            // Do not add if exists in stopWordFilter
            if (in_array($trimmedToken, $stopWordsFilter) && $enableStopWordFilter) {
                // Do nothing
            } else {
                // Add to valid array
                array_push($validTokensArray, $trimmedToken);
            }
        }
    }

    // Get Unique values
    $validTokensArray = array_unique($validTokensArray);

    // Set a variable called enoughTokensAvailable. Use recursive search only when this is true.
    if (count($validTokensArray) > 0) {
        $enoughTokensAvailable = true;
    }

    $json = stripFirstLine(file_get_contents($db));
    $json = json_decode($json);
    //print_r($json);

    foreach ($json as $obj => $values) {
        // Initialise
        $score = 0;
        $results = array();
        //echo $obj . "<br>"; if you need the key

        // Variables to search
        $description = $values->description;
        $username = $values->username;
        $tagsArray = (array)$values->tags;
        $tagsString = implode(" ", $tagsArray);
        $contentPath = "bl-content/" . $type . "/" . $obj . DS . FILENAME; // index.txt
        // Thanks http://stackoverflow.com/questions/4521936/quickest-way-to-read-first-line-from-file
        $title = fgets(fopen($contentPath, 'r'));

        // Search Description
        if ($searchDescription && stripos($description, $searchQuery)!==false) {
            array_push($results, "<b>Description:</b>" . htmlspecialchars($description) . "<br>");
            $score += 5;
        }

        // Recursive Search Description
        if ($enableRecursiveSearch && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if ($searchDescription && stripos($description, $token)!==false) {
                    array_push($results, "<b>Description:</b>" . htmlspecialchars($description) . "<br>");
                    $score += 0.25;
                }
            }
        }
        // End Recusive Search Description

        // Search Username
        if ($searchUsername && stripos($username, $searchQuery)!==false) {
            array_push($results, "<b>Username:</b>" . htmlspecialchars($username) . "<br>");
            $score += 3;
        }

        // Recursive Search Username
        if ($enableRecursiveSearch && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if ($searchUsername && stripos($username, $token)!==false) {
                    array_push($results, "<b>Username:</b>" . htmlspecialchars($username) . "<br>");
                    $score += 0.15;
                }
            }
        }
        // End Recusive Search Username

        // Search Tags
        if (stripos($tagsString, $searchQuery)!==false) {
            array_push($results, "<b>Tag:</b>" . htmlspecialchars($tagsString) . "<br>");
            $score += 10; // Tag found
        }

        // Recursive Search Tags
        if ($enableRecursiveSearch && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if (stripos($tagsString, $token)!==false) {
                    array_push($results, "<b>Tag:</b>" . htmlspecialchars($tagsString) . "<br>");
                    $score += 0.5; // Tag found
                }
            }
        }
        // End Recusive Search Tags

        // Search Title
        if (stripos($title, $searchQuery)!==false) {
            array_push($results, "<b>Title:</b> Search term found.<br>");
            $score += 15;
        }

        // Recursive Search Title
        if ($enableRecursiveSearch && $enoughTokensAvailable) {
            foreach ($validTokensArray as $token) {
                if (stripos($title, $token)!==false) {
                    array_push($results, "<b>Title:</b> Search term found.<br>");
                    $score += 0.75;
                }
            }
        }
        // End Recusive Search Title

        // Search Content
        // Case insensitive search
        // Thanks ghbarratt's | http://stackoverflow.com/questions/8032312/find-specific-text-in-multiple-txt-files-in-php
        foreach (file($contentPath) as $fli => $fl) {
            if (stripos($fl, $searchQuery)!==false) {
                array_push($results, '<b>Line ' . ($fli+1) . "</b> " . htmlspecialchars($fl) . "<br>");
                $score += 1; // Content found
            }

            // Recursive content search
            if ($enableRecursiveSearch && $enoughTokensAvailable) {
                foreach ($validTokensArray as $token) {
                    if (stripos($fl, $token)!==false) {
                        array_push($results, '<b>Line ' . ($fli+1) . "</b> " . htmlspecialchars($fl) . "<br>");
                        $score += 0.05; // Content found
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
            array_push($resultsArray, array("slug"=>$obj, "type"=>$type, "results"=>$results, "score"=>$score));
            ++$resultCount;
        }
    }

    // Reverse sort based on score...
    // Thanks http://stackoverflow.com/questions/2699086/sort-multi-dimensional-array-by-value
    // Slightly modified.

    // PHP 5 version
    // WARNING: Floats are not sorted properly in the PHP 5 function. Avoid using this.
    // usort($resultsArray, function($a, $b) {
    // 	return $b['score'] - $a['score'];
    // });

    // PHP 7+ only. Requires spaceship operator. Works fine.
    usort($resultsArray, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    // For debugging
    // echo "<pre>"; print_r($resultsArray); echo "</pre>";

    return 0;
}
// END Search function

// Display Results Function -> Display the array in a pretty way.
function displayResults()
{
    // Variable Scope fix.
    global $resultsArray, $Site , $searchQuery, $validTokensArray;

    // For debug purpose
    // echo "<pre>"; print_r($validTokensArray); echo "</pre>";

    foreach ($resultsArray as $keys => $values) {
        echo "<div class='result'>";
        if ($values["type"]==="posts") {
            echo "<p class='title'>POST [<span class='edit'><a href='" . $Site->url() . "admin/edit-post/" . $values["slug"] .
            "' target='_blank'>EDIT</a></span>]: <a href='" . $Site->url() . "post/" . $values["slug"] . "' target='_blank'>" . $values["slug"] . "</a></p>"; // <small> Relevancy Score: {$values['score']}</small>
        } elseif ($values["type"]==="pages") {
            echo "<p class='title'>PAGE [<span class='edit'><a href='" . $Site->url() . "admin/edit-page/" . $values["slug"] .
            "' target='_blank'>EDIT</a></span>]: <a href='" . $Site->url() . $values["slug"] . "' target='_blank'>" . $values["slug"] . "</a></p>";
        } else {
            echo "<p class='error'>ERROR: Invalid search function parameter. Try pages or posts.</p>";
            exit;
        }

        // Add highlights. HTML tags are not highlighted...
        $tmp = $values["results"];
        $tmpWithHighlights = array();

        foreach ($tmp as $tmpResult) {
            $highlighted = str_ireplace($searchQuery, "<span class='highlight'>$searchQuery</span>", $tmpResult);
            /* Highlight tokens if enabled */
            /* Note: If there are too many tokens, highlight is sometimes buggy. Fix later. Not a big deal.*/
            if (!empty($validTokensArray)) {
                foreach ($validTokensArray as $token) {
                    $highlighted = str_ireplace($token, "<span class='highlight'>$token</span>", $highlighted);
                }
            }
            array_push($tmpWithHighlights, $highlighted);
        }

        $tmpWithHighlights = implode(' ', $tmpWithHighlights);
        echo "<p>$tmpWithHighlights</p>";
        echo "</div>";
    }
}

echo "<form method='get' action='". htmlspecialchars($_SERVER['SCRIPT_NAME']) . "'>
<input type='text' name='q' />
<input type='submit' value='Search'></form>";

if (isset($_GET['q']) && $_GET['q'] != '') {
    $searchQuery = trim($_GET['q']); // Remove spaces from beginning and end.

    // Prevent XSS: htmlspecialchars()
    echo "<p>Search term: " . htmlspecialchars($searchQuery) . "</p>";
    search($pagesDb, "pages");
    search($postsDb, "posts");
    displayResults();

    // Display count and wall clock time taken to find results.
    // Note: $entryCount might be +1 more than total posts & pages since the "error 404 page" is also included.
    echo "\n<p>" . $resultCount . " result(s) found. (" . round((microtime(true) - $loadTime), 2) . " seconds) $entryCount entries searched." ;
}
?>
</body>
</html>
