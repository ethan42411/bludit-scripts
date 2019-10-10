# Search for Bludit

#### Installation:
* Copy `search.php` and `searchLib.php` to root of Bludit installation.
* Open `domain.com/bludit_directory(if any)/search.php`

#### Configuration:
* Configuration Options:
    * showEditLinks: bool (Show/hide Edit Links)
    * searchHiddenContent: bool (Search drafts and scheduled content. Default: false to prevent leaks)
    * minimumCharactersAllowed: int (Set minimum characters required in query)
	* maximumCharactersAllowed: int (Set maximum characters allowed in query)
	* searchUsername - bool
	* searchDescription - bool
	* enableRecursiveSearch - bool - If performance degrades badly, set to false. _Example: "hello world" is further searched as "hello", "world" if enabled. Default: true_
	* enableStopWordFilter - bool - Skip common words
	* stopWordsFilter . Uses InnoDb Stopwords by default
* Example: Changing Config 

Change line `$response = search($searchQuery);` from `search.php` to
```
$response = search($searchQuery, [
        'showEditLinks'            => false,
        'maximumCharactersAllowed' => 10
]);
```

#### Changing layout:
* The logic resides in `searchLib.php`. You can theme the layout as per your requirements by editing `search.php`

#### Additional information:
* Tested on PHP 7+ : Bludit v2.3.4
* Bonus: Script works on local Bludit installations.
* If you want to add the search feature in your Bludit Theme, copy the below snippet in the sidebar section.
```
<!-- Search -->
<form action="<?php echo $Site->url() . 'search.php';?>" target="_blank">
	<input type="text" name="q" placeholder="Search...">
</form>
```
