# EXPERIMENTAL: Take a backup before you proceed. 

## Migration Script for Bludit v2.3.4 to v3.0

* Script is tested on PHP7+
* Only `bl-content` is migrated.
* Script migrates only the Core plugin data and user data (pages, uploads etc.) to the new db structure.
* Third party plugin data is not migrated intentionally. We do not know if v2 plugins are compatible with v3.
* Script sets the theme to "blogx" for safer/better compatibility. Change your custom/stock theme manually after migration.

### Instructions:
* Take a backup of current installation.
* Copy migrate.php to root of Bludit v2.3.4 installation.
* Run it and Copy freshly generated `bl-content` directory from migrations/* to the new Bludit v3 installation.
* Done!

### I have already migrated to v3 RC2 with this script ? Can I use it again for v3 final release?
No. For final release -> Deactivate and activate the following plugins if you use them: `simple-stats, rss, sitemap, backup` to get rid of any errors.

### I found a bug:
* Report an issue describing it as verbosely as possible.


