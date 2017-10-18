<?php
/**
 * Migration Script for Bludit v1.6.2 to v2.0
 * Tested on PHP 7+
 */

// Set PHP max execution time to infinite, since script might take a lot of time depending on no. of posts/pages.
ini_set('max_execution_time', 0);

/**
 * Config
 */
define('FILENAME', 'index.txt'); // Set the filename used by your Bludit installation
$migrationDirectoryName = 'migrations';
$failedMigrationDirectoryName = $migrationDirectoryName . '/failed-posts';
$contentDirectoryName = 'bl-content';
$migratedContentPath = $migrationDirectoryName . '/' . $contentDirectoryName;
$breakLine = '------------------------------';
// Core plugins/databases only.
$pluginsWhiteList = ['about', 'simplemde', 'tags'];
$databasesWhiteList = [
    'categories.php',
    'pages.php',
    'posts.php',
    'security.php',
    'site.php',
    'tags.php',
    'users.php',
    'syslog.php' // v2.0 NEW!
];

/**
 * Helper Functions
 * 1. Recursive Copy. Thanks http://php.net/manual/en/function.copy.php
 * 2. Recusive Delete rrmdir() rm -rf. Thanks https://stackoverflow.com/questions/3338123/how-do-i-recursively-delete-a-directory-and-its-entire-contents-files-sub-dir
 * 3. dd() Pretty print_r
 * 4. stripFirstLine() : Removes mandatory BLUDIT string
 * 5. insert() : Inserts mandatory BLUDIT string + encoded Json back to database
 */
function recurse_copy($src,$dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recurse_copy($src . '/' . $file,$dst . '/' . $file);
            }
            else {
                copy($src . '/' . $file,$dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// Recursive delete
function rrmdir($dir) {
    if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            if (is_dir($dir."/".$object))
                rrmdir($dir."/".$object);
            else
                unlink($dir."/".$object);
            }
     }
    rmdir($dir);
    }
}

/* Dump and die */
function dd($variable, $die = false) {
    echo "<pre>";
    ($die) ? die( print_r($variable) ) : print_r($variable);
    echo "</pre>";
}

// Remove first line function. Helper function to remove string "<?php defined('BLUDIT')" from db
// Thanks ComFreek http://stackoverflow.com/questions/7740405/php-delete-the-first-line-of-a-text-and-return-the-rest
function stripFirstLine($text)
{
    return substr( $text, strpos($text, "\n")+1 );
}

// Insert to database
function insert($filePath, $data) {
    $compulsoryBluditLine = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>".PHP_EOL;
    $content = $compulsoryBluditLine . json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents($filePath, $content);
}

/**
 * First we check if script was copied to the right directory.
 */
if( !file_exists($contentDirectoryName) ){
    die("Failed: Script was copied to the wrong directory. Make sure it is copied where $contentDirectoryName exists.");
}

/**
 * Create migrations directory (Delete if it already exists)
 */
if( file_exists($migrationDirectoryName) ) {
    /**
     * Empty the folder recursively
     * Thanks https://stackoverflow.com/questions/4594180/deleting-all-files-from-a-folder-using-php
     */
    rrmdir($migrationDirectoryName);
}

if ( !mkdir('migrations', 0755) ) {
    die('Failed to create directory. No write permission available.');
} else {
    echo "<br>Successfuly created Migration Directory...";
}

if ( !mkdir($failedMigrationDirectoryName, 0755) ) {
    die('Failed to create directory. No write permission available.');
}

/**
 * Copy everything from bl-content to migrations/
 * If a post has a name of an existing page name move to failed-posts and add to failedPosts array.
 */
recurse_copy($contentDirectoryName, $migratedContentPath);

$allPages = array_diff(scandir($migratedContentPath . '/pages'), array('.', '..'));
$allPosts = array_diff(scandir($migratedContentPath . '/posts'), array('.', '..'));
$failedPosts = [];
// dd($allPages);

foreach ($allPosts as $post) {
    if ( !in_array($post, $allPages) ) {
        recurse_copy($migratedContentPath . "/posts/" . $post, $migratedContentPath . "/pages/" . $post);
    } else {
        recurse_copy($migratedContentPath . "/posts/" . $post, $failedMigrationDirectoryName . "/" . $post);
        echo "<br>Conflict: Page name already exists. Post '$post' moved to failed-posts";
        $failedPosts[] = $post;
    }
}

// Delete posts directory
rrmdir($migratedContentPath . "/posts/");

// Delete plugins/databases not present in white list
$allDatabases = array_diff(scandir($migratedContentPath . '/databases'), array('.', '..', 'plugins')); // Ignore plugins directory
$allPlugins = array_diff(scandir($migratedContentPath . '/databases/plugins'), array('.', '..'));

// dd($allDatabases);
// dd($allPlugins);

/* Delete non bludit core plugin/database configs */
foreach ($allDatabases as $database) {
    if (! in_array($database, $databasesWhiteList)) {
        if(is_dir ($migratedContentPath . '/databases/' . $database) ){
            rrmdir($migratedContentPath . '/databases/' . $database);
        } else {
            unlink($migratedContentPath . '/databases/' . $database);
        }
        echo "<br>Removed non Bludit core database config: $database";
    }
}

foreach ($allPlugins as $plugin) {
    if (! in_array($plugin, $pluginsWhiteList)) {
        rrmdir($migratedContentPath . '/databases/plugins/' . $plugin);
        echo "<br>Removed non Bludit core plugin config: $plugin";
    }
}

// dd($failedPosts);

// Remove Error page
if (file_exists ($migratedContentPath . "/pages/error") ) {
    rrmdir($migratedContentPath . "/pages/error");
}

// Migrate Core Plugins to v2.0
// Get Fresh List
$allPlugins = array_diff(scandir($migratedContentPath . '/databases/plugins'), array('.', '..'));
foreach ($allPlugins as $plugin) {
    $tmp = $migratedContentPath . '/databases/plugins/' . $plugin . '/';
    switch($plugin) {
        case "tags":
            if( file_exists($tmp . 'db.php') ){
                $data = stripFirstLine( file_get_contents($tmp . 'db.php') );
                $json = json_decode($data);
                $json->position= 2;
            } else{
                echo "<br>Plugin $plugin db.php not found";
            }
            // Insert to db
            insert($tmp . 'db.php', $json);
            // dd($json);
            break;

        // case about and simplemde are not needed since nothing is changed. pages plugin is deprecated.
    }
}

// Migrate Core Databases to v2.0
// Get Fresh List
$allDatabases = array_diff(scandir($migratedContentPath . '/databases'), array('.', '..', 'plugins')); // Ignore plugins directory

// Add new syslog.php
$tmp = $migratedContentPath . '/databases/syslog.php';
insert($tmp, []);

// Add new default categories
$tmp = $migratedContentPath . '/databases/categories.php';
$RAW_CATEGORIES_PHP_FROM_V2 =
<<<'RAW'
<?php defined('BLUDIT') or die('Bludit CMS.'); ?>
{
    "general": {
        "name": "General",
        "list": [
            "a-page-under-general-category",
            "welcome"
        ]
    },
    "music": {
        "name": "Music",
        "list": []
    },
    "videos": {
        "name": "Videos",
        "list": []
    }
}
RAW;
file_put_contents($tmp, $RAW_CATEGORIES_PHP_FROM_V2);

foreach ( $allDatabases as $database ) {
    $tmp = $migratedContentPath . '/databases/' . $database;
    switch($database) {

        case "security.php":
            if( file_exists($tmp) ){
                $data = stripFirstLine( file_get_contents($tmp) );
                $json = json_decode($data, true);
                $tmpWhiteListed = ['minutesBlocked', 'numberFailuresAllowed', 'blackList'];
                foreach ($json as $key => $values) {
                    if (!in_array($key, $tmpWhiteListed)) {
                        unset($json[$key]);
                    }
                }

            } else{
                echo "<br>Database $database not found";
            }
            // Insert to db
            insert($tmp, $json);
            // dd($json);
            break;

        case "site.php":
            if( file_exists($tmp) ){
                $data = stripFirstLine( file_get_contents($tmp) );
                $json = json_decode($data);
                $json->itemsPerPage = 6;
                $json->language = "en";
                $json->locale = "en, en_US, en_AU, en_CA, en_GB, en_IE, en_NZ";
                $json->pageNotFound = "";
                $json->orderBy = "date";
                $json->theme = "log";
                // Deprecated
                unset($json->postsperpage);
                unset($json->uriPost);
            } else {
                echo "<br>Database $database not found";
            }
            // Insert to db
            insert($tmp, $json);
            // dd($json);
            break;

        case "tags.php":
            if( file_exists($tmp) ){
                $data = stripFirstLine( file_get_contents($tmp) );
                $json = json_decode($data, true);
                $finalValidArray = [];
                // Remove postsIndex
                foreach($json as $key => $values) {
                    if ($key == 'postsIndex') {
                        foreach ($values as $validKey => $validValues) {
                            $finalValidArray[$validKey] = $validValues;
                        }
                    }
                }
                // Rename posts key to v2.0 list
                $tags = array_map(function($tag) {
                    return array(
                        'name' => $tag['name'],
                        'list' => $tag['posts']
                    );
                }, $finalValidArray);

            } else {
                echo "<br>Database $database not found";
            }
            // Insert to db
            insert($tmp, $tags);
            // dd($tags);
            break;

        case "users.php":
            if( file_exists($tmp) ){
                $data = stripFirstLine( file_get_contents($tmp) );
                $json = json_decode($data);
                foreach($json as $user) {
                    // New values
                    $user->tokenAuth = "";
                    $user->tokenAuthTTL = $user->tokenEmailTTL;
                }

            } else {
                echo "<br>Database $database not found";
            }
            // Insert to db
            insert($tmp, $json);
            // dd($json);
            break;
    }
}

/**
 * Now Most of it is done. We only need to migrate pages.php and posts.php
 * Failed posts are available here $failedPosts
 */
$finalPages = [];
if (file_exists ($migratedContentPath . '/databases/pages.php') ){
    $data = stripFirstLine( file_get_contents($migratedContentPath . '/databases/pages.php') );
    $json = json_decode($data, true);
    foreach ($json as $pageKey => $values) {
        // Ignore Error page. Not needed.
        if ($pageKey !== 'error') {
            // Leave drafts as it is. Else Change page type to static.
            $values['status'] = ($values['status'] === 'draft') ? 'draft' : 'static';
            $values['type'] = 'page';
            $values['allowComments'] = 'true';
            $values['md5file'] = md5_file($migratedContentPath . '/pages/' . $pageKey . '/' . FILENAME);
            $finalPages[$pageKey] = $values;
        }
    }
}

// Migrate posts.php to pages.php
$failedPostsMetaData = [];
if (file_exists ($migratedContentPath . '/databases/posts.php') ){
    $data = stripFirstLine( file_get_contents($migratedContentPath . '/databases/posts.php') );
    $json = json_decode($data, true);
    foreach ($json as $pageKey => $values) {

        $values['status'] = ($values['status'] === 'draft') ? 'draft' : 'published';
        $values['type'] = 'post';
        $values['parent'] = isset($values['parent']) ? $values['parent'] : "";
        $values['allowComments'] = 'true';
        $values['slug'] = $pageKey;
        $values['md5file'] = md5_file($migratedContentPath . '/pages/' . $pageKey . '/' . FILENAME);
        // Posts do not have a position by default in v1 but Bludit v2 requires them
        $values['position'] = isset($values['position']) ? $values['position'] : 1;

        // Ignore iteration if pageKey exists in $failedPosts
        if (in_array($pageKey, $failedPosts)) {
            $failedPostsMetaData[$pageKey] = $values;
            continue;
        } else {
            $finalPages[$pageKey] = $values;
        }
    }
}
// Add Failed Meta Data to failed.php if count > 0
if ( count ($failedPostsMetaData) > 0) {
    insert($failedMigrationDirectoryName . '/failed.php', $failedPostsMetaData);
}

// Delete Deprecated posts.php
if (file_exists ($migratedContentPath . '/databases/posts.php') ){
    unlink($migratedContentPath . '/databases/posts.php');
}

// Now Insert
// dd($finalPages);
insert($migratedContentPath . '/databases/pages.php', $finalPages);
echo "<br>$breakLine";
echo "<br>Failed Migrations (Posts): " . count($failedPosts);

if( count($failedPosts) > 0 ){
    echo "<br>Add these manually:";
    echo '<ol>';
    echo '<li>' . implode('<li>', $failedPosts);
    echo "</ol>$breakLine";
}

echo "<br>Fixing Permissions...";
chmod($contentDirectoryName, 0755);

echo "<br>Successfuly migrated.";
