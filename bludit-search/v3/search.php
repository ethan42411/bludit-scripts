<?php
// Include Search library
require_once __DIR__ . '/searchLib.php';
$tmp = getSiteInfo();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $tmp['siteTitle']; ?> | Search</title>
    <!-- Responsive -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* {
    font-family: BlinkMacSystemFont, -apple-system, "Segoe UI", "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Fira Sans", "Droid Sans", "Helvetica Neue", "Helvetica", "Arial", sans-serif;
}

body {
    line-height: 1.5;
    max-width: 960px;
    margin: 0 auto;
    padding: 0px 5px;
}

.result {
    margin-bottom: 23px;
    box-shadow: 0 0 5px #000;
    padding: 5px 10px;
}

.title {
    font-size: 1.1em;
    border-bottom: 1px solid grey;
    margin: 0;
    padding: 5px 0;
}

.button {
    background-color: #e7e7e7;
    border: none;
    color: black;
    font-weight: bold;
    padding: 7px 10px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
}

.button:hover {
    background-color: #555555;
    color: white;
}

.edit {
    font-size: small;
    padding: 5px 8px;
}

.search-input {
    padding: 7px 10px;
    margin: 0;
}

.highlight {
    background-color: yellow;
}

.error {
    color:tomato;
}

a {
    text-decoration:none;
    color: #2A5DB0;
}

a:hover {
    color: #000;
}

img {
    max-width: 100%;
}
</style>
</head>

<body>
<?php
echo "<h2><a href='{$tmp['siteUrl']}'>{$tmp['siteTitle']}</a></h2>";

echo "<form method='get' action='". htmlspecialchars($_SERVER['SCRIPT_NAME']) . "'>
<input type='text' name='q' class='search-input' />
<input type='submit' value='Search' class='button'></form>";

if (!empty($_GET['q']) && is_string($_GET['q'])) {
    // Remove spaces from beginning and end.
    $searchQuery = trim($_GET['q']);
    $response = search($searchQuery);

    if (isset($response['errors'])) {
        exit('<p class="error">' . $response['errors'] .'</p>');
    }

    // Prevent XSS: htmlspecialchars()
    echo '<p>Search query: <b>' . htmlspecialchars($searchQuery) . '</b></p>';

    // Display count and wall clock time taken to query data.
    echo "<p>{$response['meta']['resultsCount']} result(s) found. ({$response['meta']['timeTakenInSeconds']} seconds) {$response['meta']['entryCount']}  entries searched.</p>";

    // echo '<pre>';
    // print_r($response);
    // echo '</pre>';

    foreach ($response['results'] as $page) {
        echo '<div class="result">';
        $friendlyTitle = !empty(trim($page['title'])) ? $page['title'] : $page['slug'];
        echo '<p class="title"><a href="' . $page['url'] . '" target="_blank">' . $friendlyTitle . '</a>';
        // echo "<small> Relevancy Score: {$page['score']}</small>";
        if ($response['meta']['showEditLinks']) {
            echo ' <a href="' . $page['editUrl'] . '" target="_blank"><button class="button edit">Edit</button></a>';
        }
        echo '</p>';

        // Optional Cover Image
        // if ($page['coverImage']) {
        //     echo '<img src="' . $page['coverImage'] . '" />';
        // }

        // Add highlights. HTML tags are not highlighted.
        $tmp = $page['results'];
        $tmpWithHighlights = array();

        foreach ($tmp as $tmpResult) {
            $highlighted = str_ireplace($searchQuery, "<span class='highlight'>$searchQuery</span>", $tmpResult);
            /**
             * Highlight tokens if enabled
             * Note: If there are too many tokens, highlight is sometimes buggy. Fix later(minor)
             */
            if (!empty($response['meta']['queryTokens'])) {
                foreach ($response['meta']['queryTokens'] as $token) {
                    $highlighted = str_ireplace($token, "<span class='highlight'>$token</span>", $highlighted);
                }
            }
            array_push($tmpWithHighlights, $highlighted);
        }

        $tmpWithHighlights = implode(' ', $tmpWithHighlights);
        echo "<p>$tmpWithHighlights</p>";
        echo '</div>';
    }
}
?>
</body>
</html>
