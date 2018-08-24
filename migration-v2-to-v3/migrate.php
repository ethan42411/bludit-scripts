<?php
/**
 * Bludit migration script for v2.3.4 to v3.0
 * Tested on PHP7+
 */

// Set PHP max execution time to infinite, since script might take a lot of time depending on no. of posts/pages.
ini_set('max_execution_time', 0);

/**
 * Config
 */
define('FILENAME', 'index.txt'); // Set the filename used by your Bludit installation
define('DS', DIRECTORY_SEPARATOR);
define('CHARSET', 'UTF-8');
$migrationDirectoryName = 'migrations';
$contentDirectoryName = 'bl-content';
$migratedContentPath = $migrationDirectoryName . DS . $contentDirectoryName;

function msg($string)
{
    // Determine if CLI and set end of line according to the way script is called.
    // This is for logging output.
    $breakLine = (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) ? PHP_EOL : '<br>';
    echo $string . $breakLine;
}

// Core plugins/databases only.
// Not available in v3: discovery
// Available only in v3: robots, simple-stats, updater
$pluginsWhiteList = [
    'about',
    'api',
    'backup',
    'categories',
    'disqus',
    'html-code',
    'links',
    'maintenance-mode',
    'navigation',
    'opengraph',
    'rss',
    'simplemde',
    'sitemap',
    'static-pages',
    'tags',
    'tinymce',
    'twitter-cards',
    'version'
];

$databasesWhiteList = [
    'categories.php',
    'pages.php',
    'security.php',
    'site.php',
    'tags.php',
    'users.php',
    'syslog.php'
];

/**
 * Helper Functions
 * 1. Recursive Copy. Thanks http://php.net/manual/en/function.copy.php
 * 2. Recusive Delete recurse_delete() rm -rf. Thanks https://stackoverflow.com/questions/3338123/how-do-i-recursively-delete-a-directory-and-its-entire-contents-files-sub-dir
 * 3. dd() Pretty print_r
 * 4. stripFirstLine() : Removes mandatory BLUDIT string
 * 5. insert() : Inserts mandatory BLUDIT string + encoded Json back to database
 */
function recurse_copy($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ( $file = readdir($dir))) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if (is_dir($src . '/' . $file)) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}

// Recursive delete
function recurse_delete($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir."/".$object)) {
                    recurse_delete($dir."/".$object);
                } else {
                    unlink($dir."/".$object);
                }
            }
        }
        rmdir($dir);
    }
}

/* Dump and die */
function dd($variable, $die = false)
{
    echo "<pre>";
    ($die) ? die(print_r($variable)) : print_r($variable);
    echo "</pre>";
}

// Remove first line function. Helper function to remove string "<?php defined('BLUDIT')" from db
// Thanks ComFreek http://stackoverflow.com/questions/7740405/php-delete-the-first-line-of-a-text-and-return-the-rest
function stripFirstLine($text)
{
    return substr($text, strpos($text, "\n")+1);
}

// Insert to database
function insert($filePath, $data)
{
    $compulsoryBluditLine = "<?php defined('BLUDIT') or die('Bludit CMS.'); ?>".PHP_EOL;
    $content = $compulsoryBluditLine . json_encode($data, JSON_PRETTY_PRINT);
    file_put_contents($filePath, $content);
}

// Title
msg('================================');
msg('Bludit Migration from v2.3.4 to v3');
msg('================================');

/**
 * First we check if script was copied to the right directory.
 */
if (!file_exists($contentDirectoryName)) {
    die("Failed: Script was copied to the wrong directory. Make sure it is copied where $contentDirectoryName exists.");
}

/**
 * Create migrations directory (Delete if it already exists)
 */
if (file_exists($migrationDirectoryName)) {
    /**
     * Empty the folder recursively
     * Thanks https://stackoverflow.com/questions/4594180/deleting-all-files-from-a-folder-using-php
     */
    recurse_delete($migrationDirectoryName);
}

if (!mkdir('migrations', 0755)) {
    die('Failed to create directory. No write permission available.');
} else {
    msg("Created Migration Directory...");
}

/**
 * Copy everything from bl-content to migrations/
 */
recurse_copy($contentDirectoryName, $migratedContentPath);

$allPages = array_diff(scandir($migratedContentPath . '/pages'), array('.', '..'));

// Delete plugins/databases not present in white list
$allDatabases = array_diff(scandir($migratedContentPath . '/databases'), array('.', '..', 'plugins')); // Ignore plugins directory
$allPlugins = array_diff(scandir($migratedContentPath . '/databases/plugins'), array('.', '..'));

// dd($allDatabases);
// dd($allPlugins);

/* Delete non bludit core plugin/database configs */
foreach ($allDatabases as $database) {
    if (! in_array($database, $databasesWhiteList)) {
        if (is_dir($migratedContentPath . '/databases/' . $database)) {
            recurse_delete($migratedContentPath . '/databases/' . $database);
        } else {
            unlink($migratedContentPath . '/databases/' . $database);
        }
        msg("Removed non Bludit core database config: $database");
    }
}

foreach ($allPlugins as $plugin) {
    if (! in_array($plugin, $pluginsWhiteList)) {
        recurse_delete($migratedContentPath . '/databases/plugins/' . $plugin);
        msg("Removed non Bludit core plugin config: $plugin");
    }
}

// Migrate Core Plugins
// Get Fresh List
msg('Migrating core plugin databases...');
$allPlugins = array_diff(scandir($migratedContentPath . '/databases/plugins'), array('.', '..'));
foreach ($allPlugins as $plugin) {
    $tmp = $migratedContentPath . '/databases/plugins/' . $plugin . '/';
    switch ($plugin) {
        /**
         * amountOfItems was changed to numberOfItems
         */
        case 'api':
        case 'navigation':
        case 'rss':
            if (file_exists($tmp . 'db.php')) {
                $data = stripFirstLine(file_get_contents($tmp . 'db.php'));
                $json = json_decode($data);
                $json->numberOfItems = $json->amountOfItems;
                unset($json->amountOfItems);

                // Insert to db
                insert($tmp . 'db.php', $json);
            } else {
                msg("Plugin $plugin db.php not found");
            }
            // dd($json);
            break;

        case 'tinymce':
            if (file_exists($tmp . 'db.php')) {
                $data = stripFirstLine(file_get_contents($tmp . 'db.php'));
                $json = json_decode($data);
                // New items
                $json->toolbar1 = "formatselect bold italic bullist numlist blockquote alignleft aligncenter alignright link pagebreak image removeformat code";
                $json->toolbar2 = "";
                $json->mobileToolbar = "bold italic bullist formatselect";
                $json->plugins = "code autolink image link pagebreak advlist lists textcolor colorpicker textpattern";

                // Insert to db
                insert($tmp . 'db.php', $json);
            } else {
                msg("Plugin $plugin db.php not found");
            }
            // dd($json);
            break;

        case 'simplemde':
            if (file_exists($tmp . 'db.php')) {
                $data = stripFirstLine(file_get_contents($tmp . 'db.php'));
                $json = json_decode($data);
                // Obsolete
                unset($json->autosave);

                // Insert to db
                insert($tmp . 'db.php', $json);
            } else {
                msg("Plugin $plugin db.php not found");
            }
            // dd($json);
            break;
    }
}

/**
 * Enable simple-stats and robots plugin (Available only on v3)
 */
mkdir($migratedContentPath . '/databases/plugins/robots', 0755);
insert($migratedContentPath . '/databases/plugins/robots/db.php', [
    'position' => 1
]);

mkdir($migratedContentPath . '/databases/plugins/simple-stats', 0755);
insert($migratedContentPath . '/databases/plugins/simple-stats/db.php', [
    'numberOfDays' => 7,
    'label'        => 'Visits',
    'excludeAdmins'=> false,
    'position'     => 1
]);

// Migrate Core Databases to v3.0
// Get Fresh List
$allDatabases = array_diff(scandir($migratedContentPath . '/databases'), array('.', '..', 'plugins')); // Ignore plugins directory

foreach ($allDatabases as $database) {
    $tmp = $migratedContentPath . '/databases/' . $database;
    switch ($database) {
        case 'categories.php':
            if (file_exists($tmp)) {
                $data = stripFirstLine(file_get_contents($tmp));
                $json = json_decode($data);
                foreach ($json as $category) {
                    // New values
                    $category->description = '';
                    $category->template = '';
                }
                // Insert to db
                insert($tmp, $json);
            } else {
                msg("Database $database not found");
            }
            break;

        case 'pages.php':
            if (file_exists($tmp)) {
                $data = stripFirstLine(file_get_contents($tmp));
                $json = json_decode($data);

                $count = 0;

                foreach ($json as $key => $page) {
                    $pageObject = new Page($key);

                    // Get title from index.txt
                    $page->title = $pageObject->vars['title'];

                    if (empty($page->title)) {
                        msg('WARNING: Page with empty title found.');
                    }

                    $page->type = $page->status;
                    unset($page->status);

                    // Insert rawContent to index.txt
                    file_put_contents($migratedContentPath.DS.'pages'.DS.$key.DS.FILENAME, $pageObject->vars['contentRaw']);

                    // Recalculate new index.txt md5
                    $page->md5file = md5_file($migratedContentPath.DS.'pages'.DS.$key.DS.FILENAME);
                    $page->noindex = false;
                    $page->nofollow = false;
                    $page->noarchive = false;

                    $count++;
                }
                // Insert to db
                insert($tmp, $json);

                msg("Migrated $count pages...");
            } else {
                msg("Database $database not found");
            }
            break;

        case 'site.php':
            if (file_exists($tmp)) {
                $data = stripFirstLine(file_get_contents($tmp));
                $json = json_decode($data);

                // New values
                // Safer compat
                $json->theme = 'blogx';
                $json->adminTheme = 'booty';
                $json->currentBuild = 20180821;
                $json->instagram = '';
                $json->gitlab = '';
                $json->linkedin = '';
                $json->extremeFriendly = true;
                $json->autosaveInterval = 2;
                $json->titleFormatHomepage = '{{site-slogan}} | {{site-title}}';
                $json->titleFormatPages = '{{page-title}} | {{site-title}}';
                $json->titleFormatCategory = '{{category-name}} | {{site-title}}';
                $json->titleFormatTag = '{{tag-name}} | {{site-title}}';

                // Set uriCategory if not available.
                $json->uriCategory = isset($json->uriCategory) ? $json->uriCategory : '/category/';

                // Insert to db
                insert($tmp, $json);
            } else {
                msg("Database $database not found");
            }
            break;

        case 'users.php':
            if (file_exists($tmp)) {
                $data = stripFirstLine(file_get_contents($tmp));
                $json = json_decode($data);
                foreach ($json as $user) {
                    // New values
                    $user->nickname = ($user === 'admin') ? 'Admin' : $user->firstName;
                    $user->codepen = '';
                    $user->linkedin = '';
                    $user->github = '';
                    $user->gitlab = '';
                }
                // Insert to db
                insert($tmp, $json);
            } else {
                msg("Database $database not found");
            }
            break;

            // No changes in security.php, syslog.php, tags.php
    }
}

msg("Fixing Permissions...");
chmod($contentDirectoryName, 0755);

msg("Successfuly migrated.");

/**
 * Customised Bludit v2.3.4 page.class.php for migration purpose
 */
class Page
{

    public $vars;

    public function __construct($key)
    {
        $this->vars = false;

        if ($this->build($key)) {
            $this->vars['key'] = $key;
        }
    }

    // Parse the content from the file index.txt
    public function build($key)
    {
        // Get path from script.
        global $migratedContentPath;

        $filePath = $migratedContentPath.DS.'pages'.DS.$key.DS.FILENAME;

        // Check if the file exists
        if (!is_file($filePath)) {
            return false;
        }

        $tmp = 0;
        $file = file($filePath);
        foreach ($file as $lineNumber => $line) {
            // Split the line in 2 parts, limiter by :
            $parts = explode(':', $line, 2);

            $field = $parts[0]; // title, date, slug
            $value = isset($parts[1])?$parts[1]:false; // value of title, value of date

            // Remove all characters except letters and dash - from field
            $field = preg_replace('/[^A-Za-z\-]/', '', $field);

            // Field to lowercase
            $field = mb_strtolower($field, CHARSET);

            // Check if the current line start the content of the page
            // We have two breakers, the word content or 3 dash ---
            if ($field==='content') {
                $tmp = $lineNumber;
                $styleTypeUsed = 'Content:';
                break;
            }

            if ($field==='---') {
                $tmp = $lineNumber;
                $styleTypeUsed = '---';
                break;
            }

            if (!empty($field) && !empty($value)) {
                // Remove missing dashs -
                $field = preg_replace('/[^A-Za-z]/', '', $field);

                // Remove <-- and -->
                $value = preg_replace('/<\-\-/', '', $value);
                $value = preg_replace('/\-\->/', '', $value);

                // Remove empty spaces on borders
                $value = trim($value);

                // Position accepts only integers
                if ($field=='position') {
                    $value = preg_replace('/[^0-9]/', '', $value);
                }

                // Sanitize all fields, except the content
                $this->vars[$field] = $value;
            }
        }

        // Process the content
        if ($tmp!==0) {
            // Get all lines starting from "Content:" or "---"
            $content = array_slice($file, $tmp);

            // Remove "Content:" or "---" and keep next characters if there are
            $content[0] = substr($content[0], strpos($content[0], $styleTypeUsed) + strlen($styleTypeUsed));

            $content[0] = ltrim($content[0]);

            // Join lines in one variable, this is RAW content from file
            $this->vars['contentRaw'] = implode($content);
        }

        return true;
    }
}
