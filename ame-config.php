<?php

// Begin Glog configuration
// Glog is a custom error handler to catch easily catchable PHP errors, to aid in 
// debugging in situations where you don't have access to the proper logs.
// It's set up first so it can help with possible errors in this file as well.

// Whether to use glog
$glog_activate = false;

// If active, whether to suppress the errors from normal logging.
$glog_suppress = true;

// End Glog configuration

$glog_caught = "";

function glog($errno, $errstr, $errfile, $errline) {

	global $glog_caught;
	global $glog_suppress;
	
	$glog_caught .= $errfile . ":" . $errline . " :: " . $errno . " :: " . $errstr . "\n";

	return $glog_suppress;
}

if($glog_activate === true) {
	set_error_handler("glog");
}

// Begin Ame configuration
// This is basically a general config section for stuff that's a bit more general than $archive_, $rss_, or $poster_ stuff

// Whether to allow debugging (by adding ?debug). The output is printed into a html comment.
// Recommended to set to false when not needed.
$ame_allow_debug = false;

// Whether to allow manual rebuilding of file caches (by adding ?rebuild)
$ame_allow_rebuild = false;

// How to decide when to rebuild the caches
// - "triggered" refers to having to manually trigger a rebuild (by adding ?rebuild)
// - "timer" refers to automatically rebuilding the list every x seconds, as specified in $ame_rebuild_time
// - "reference" refers to automatically rebuilding the list if the file named in $ame_rebuild_reference is newer than the cache
$ame_rebuild_type = "reference";

// The time between rebuilds if mode is 'timer', in seconds
$ame_rebuild_time = 300;

// The file to use as a reference if mode is 'reference', relative or absolute path
// Should probably be set to the same as $poster_currentfile
$ame_rebuild_reference = "current.txt";

// Whether to do our best to return results, even if we have to read the directory live for every request
$ame_tryhard = true;

// Timezone to operate in. Used for figuring out the date for the autoposter.
// This will work correctly if the server clock is set correctly, regardless of what timezone the server itself is in.
// If the server clock is incorrect, well, you'll have to compensate for it.
// See http://php.net/manual/en/timezones.php for the possible values.
$ame_timezone = "America/New_York";

// The name of the comic author. Used in the RSS feed, and the archive copyright notice (if configured)
$ame_comic_author = "Author Authington";

// Title of the comic. Probably only used in RSS, but I guess you could use it somewhere else as well!
$ame_comic_title = "Example Comic";

// As above
$ame_comic_subtitle = "A comic by Author Authington";

// How to link to the 'home' page (wherever the newest comic can be seen)
// This should be an absolute link (e.g. http://www.example.com/ ) because of how it's used in the RSS feed.
// We, um, take a guess. You might want to set it manually.
$ame_home_page = "http://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/index.php";

// Directory where the archived comics live (everything that's been published, except the newest page)
// Must be a relative path (used for both filesystem lookups and building links)
$ame_archivedir = "archive";

// Directory where the newest published comics live (These are what the RSS looks at)
// Must be a relative path (used for both filesystem lookups and building links)
$ame_imagesdir = "images";

// Directory where future updates live
// Can be an absolute or relative path. If it's in the documentroot, you should probably guard it with a .htaccess file or equivalent!
$ame_updatesdir = "updates";

// Addressing mode to use. "d" for date-based, anything else for ordinals
$ame_addressing_mode = "d";

// Backup image to display on the front page instead of a comic if we can't find anything to display.
$ame_oops_image = "";

// Title text for same (Shown on image hover)
$ame_oops_title = "We seem to not have any comics. Have this instead!";

// Date for same (Comic Page for <>)
$ame_oops_date = "???";

// If the comic has a license (e.g. Creative Commons), a link to the license explanation
$ame_license_link = "";

// The text of the license link
$ame_license_link_text = "";

// End Ame configuration

// Begin Archive configuration
// Config for the archive/imagegallery parts

// A list of chapter breaks in the comic. The index is generally just the YYYYMMDD date,
// but can also be slightly different if there's multiple pages on a single day.
// Example of how to use it:
// $archive_chapters = array(
//	'20141020' => 'Prologue: The Prologuening',
//	'20141102' => 'Chapter 1: The Second Prologue',
//	'20141120a' => 'Chapter 2: The Plot Thickens',
//	);
$archive_chapters = array(

	);

// Link to the page that displays the archive index. This one isn't used in anything nitpicky, so it can be relative or absolute.
$archive_index = "archive_index.php";

// Use if you have a static image server somewhere else, otherwise leave it blank.
// (Should probably point to the root of the server, like so: http://example.com/)
$archive_cdn_prefix = "";

// How to link to archive pages.
// This too should be an absolute link (e.g., http://www.example.com/archive.php ) because of the RSS feed.
// We, um, take a guess. You might want to set it manually.
$archive_link_prefix = "http://" . $_SERVER['SERVER_NAME'] . dirname($_SERVER['SCRIPT_NAME']) . "/archive.php";


// Where in the filesystem to keep the cached data for the archive. Both relative and absolute are okay.
// Doesn't need to be in the webroot, but on the other hand it doesn't really matter if anyone sees this.
$archive_cachefile = "cache/archive.dat";

// If someone just hits up the archive page with no requested page, do we show them the first page or the last?
$archive_default_to_page = "last";

// What to use as a divider in the archive output.
$archive_divider = "&middot;";

// Show arrows in the archive navigation
$archive_show_arrows = true;

// Show 'back' and 'next' links in the archive navigation
$archive_show_backnext = true;

// show 'first' and 'last' links in the archive navigation
$archive_show_firstlast = true;

// show direct links to this many comics before and after the current one.
// For 'none' use 0, for 'all' use any negative value.
$archive_nav_numbers = 7;

// If showing direct links, show this many per line
$archive_nav_linelength = 15;

// Should the navigation be above or below the comic image?
$archive_nav_placement = "below";

// Show a copyright notice in the archive
$archive_show_copyright = false;

// End Archive configuration

// Begin RSS configuration
// An RSS (ATOM, if we're being perfectly frank) feed generator

// Where in the filesystem to keep the cached data for the RSS. Both relative and absolute are okay.
// Doesn't need to be in the webroot, but on the other hand it doesn't really matter if anyone sees this.
$rss_cachefile = "cache/rss.dat";

// How many entries to show by default
$rss_show_default = 5;

// How many entries to allow at most? (with ?number=n in the url)
$rss_show_maximum = 25;

// Show image enclosures by default?
$rss_show_images = false;

// Allow enclosures? (with ?show_image or ?show_images in the url)
$rss_allow_images = true;

// End RSS configuration

// Begin Poster configuration
// An automated posting system for a more civilized age

// Directory to use as an old-school semaphore. Gets created and destroyed when work is done.
// If you see this stick around, something got stuck.
// Relative or absolute path is okay. Inside the updates directory by default, but doesn't need to be.
$poster_lockdir = "updates/working";

// File where the current image/alt text is stored. 
// Probably used as a rebuild reference by the rss and archive, as well.
// (You may want to make sure this matches with $ame_rebuild_reference)
$poster_currentfile = "current.txt";

// A temporary file to be used when recreating $currentfile.
// Gets written to and then moved to overwrite $poster_currentfile
$poster_tempfile = "current.tmp";

// How long to delay before updating when seeing new files. This is to protect against
// partial uploads and such. Time in seconds.
$poster_delay = 10;

// End Poster configuration
?>