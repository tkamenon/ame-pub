What is this?
=============

ame-pub (eh, whatever) is a pretty simple webcomic publishing platform written in PHP, that uses only the filesystem.
Features are:
 - Automated posting of new pages based on date
 - An archive of past pages, with an index, and provisions for chaptering
 - RSS feed

It also comes with really, really rudimentary scaffolding examples to show how the various bits are called.

The files:
==========

 - ame-config.php	- All configuration happens here
 - ame-libs.php		- The brains of the operation
 - archive.php		- Archive page example
 - archive_index.php	- Archive index example
 - index.php		- Main page / newest comic example
 - README.txt		- INFINITE RECURSION
 - rss.php		- ... okay, it's not really an example when the script produces the entire document. The RSS feed.

The default configuration assumes the following layout:
 - All of the files in this package are in the same directory.
 - There's a directory 'images' in the same place, that will contain the newest pages (Can be generated automatically)
 - There's a directory 'archive' in the same place, that will contain all pages except the newest one (Can be generated automatically)
 - There's a directory 'updates' in the same place, where new files will be placed. (Can't really be generated automatically, now can it?)
  - Note! If this isn't moved out of the webroot, it should probably be access-controlled with .htaccess or an equivalent mechanism.
 - A directory 'cache' will be generated with two cache files, one for the archive and one for the RSS.
 - A file 'current.txt' will be generated when autoposting. It's how the system keeps track of what to show on the front page, and
   when to refresh the caches for the archives and RSS.
 
Naming and type constraints:
============================

Comic pages must be named with a YYYYMMDD prefix, and must be either .gif, .jpg, or .png. To have multiple pages on the same date,
you can go from YYYYMMDDa to YYYYMMDDz.
Some legit examples:
 - 20141122.jpg
 - 20141123_whatever.jpg
 - 20141124a.jpg
 - 20141124b_whatever.jpg

Operating instructions:
=======================

Create the updates directory, place a comic page (see above for naming) in there, and navigate to the index.
If directory permissions are appropriate and everything works, you should see the image you just uploaded.
It should also show on the RSS, but the archive will be empty until there's more than one page.

If it doesn't work, you can check the html source for a comment explaining something about what went wrong.
All catastrophic level failures should result in some kind of warning being emitted there. You can also enable debugging and
catching php errors in ame-config.php to get a better idea of what's not working.

If you make some manual changes and want to force the archive or rss cache to update, you can just delete the cache files.

You can't set up an automated update for multiple pages on the same day, the system doesn't know what to do when it finds multiple files
at once. (You could upload YYYYMMDDa.jpg, hit the index, upload YYYYMMDDb.jpg, hit the index... but that's probably not useful)

Title text:
===========

You can also put a YYYYMMDD.txt file in the uploads directory, and it will be used as the title text starting on that date.