# Search for Bludit

#### Instructions:
* Copy search.php to root of Bludit installation.
* Voila! :D Open domain.com/bludit_directory(if any)/search.php
* Bonus: Works on local Bludit installations.
* Configuration Options:
	* $minimumCharactersAllowed: Set minimum characters required in query.
	* $searchUsername - bool
	* $searchDescription - bool
	* $enableRecursiveSearch - bool - If performance degrades badly, set to false. _Example: "hello world" is further searched as "hello", "world" if enabled. Default: true_
	* $enableStopWordFilter - bool - Skip common words
	* $stopWordsFilter . Uses InnoDb Stopwords by default

#### Current bugs:
None.

#### Some additional information:
Tested on PHP 7+
Bludit v1.6.2

NOTES:

1. Warning: This script searches through all the entries - even the unpublished ones (scheduled posts & drafts). Consider to use this only for private purposes.
2. If you want to add the search feature in your Bludit Theme, copy the below snippet in the sidebar section.
```
<!-- Search -->
<form role="search" action="<?php echo $Site->url() . 'search.php';?>" target="_blank">
	<input type="text" name="q" placeholder="Search...">
</form>
```
