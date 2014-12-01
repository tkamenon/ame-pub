<?php
// ------------------------------------------------------------------------- //
// The ame-pub webcomic publishing thingamajig (Not quite a name, really)    //
// ------------------------------------------------------------------------- //
// By: amenon (I may be found on irc.nightstar.net. Or not.)		     //
// Possibly found at: https://github.com/tkamenon/ame-pub		     //
// ------------------------------------------------------------------------- //
// Based off these previous works by kyl191 (http://code.kyl191.net/):	     //
//  - Image Gallery (which was in turn based off of Comic Gallery 1.2)	     //
//  - tk_rss								     //
// ------------------------------------------------------------------------- //
// This program is free software; you can redistribute it and/or modify      //
// it under the terms of the GNU General Public License as published by      //
// the Free Software Foundation; either version 2 of the License, or         //
// (at your option) any later version.                                       //
//  A summary is available at http://creativecommons.org/licenses/GPL/2.0/   //
// ------------------------------------------------------------------------- //

require_once("./ame-config.php");

// All configuration is in ame-config.php
// Operating instructions can be found in README.md


// Set the timezone as configured in ame-config
date_default_timezone_set($ame_timezone);

// Helper function to parse a YYYYMMDD date into a suitable display format, either with css embellishment or not
function parseDate($reference, $css = true) {
	$unixtime = DateTime::createFromFormat("Ymd", substr($reference,0,8));
	if($css) {
		return $unixtime->format("l, F j")."<span style='font-size:xx-small; vertical-align:top;'>".$unixtime->format("S")."</span>".$unixtime->format(", Y");
	} else {
		return $unixtime->format("l, F j") . $unixtime->format("S") . $unixtime->format(", Y");
	}
}

// LIBDEX TODO:
//  - Excise the subindex silliness and write something like getImages($anchor, $around)?
class libdex {

	// Configuration comes in via ame-config.php and libdex->configure(mode)

	// Your images directory, relative to the script being called
	public $imagedir;

	// File to store the lists of filenames, relative to the script being called
	private $filelist;
	
	// If set to true, will try to return results even if they have to be read live from the filesystem every time.
	private $tryhard;
	
	private $rebuild_type;

	// If type is "timer", rebuild index every x seconds
	private $rebuild_time;

	// If type is "reference", compare against this file
	private $rebuild_reference;
	
	// Whether to allow manual rebuilding, regardless of the rebuild type
	private $allow_manual_rebuild;

	public $images = array();
	
	public $mtime = "";
	public $debug = "";
	public $warnings = "";
		
	// Setup the filelist handling functions.
	// Dump all filenames to an array
	public function readFilesFromDrive(){
		
		// Open the directory	
		if (is_dir($this->imagedir)){
			$dir=opendir($this->imagedir);
			// Read directory into image array
			while (($file = readdir($dir))!==false) {
			    // filter for jpg, gif or png files... 
			    // However, we're also doing numeric comparisons to grab the date!
			    if ((strcasecmp(substr($file,-4),".jpg") == 0 || strcasecmp(substr($file,-4),".gif") == 0 || strcasecmp(substr($file,-4),".png") == 0 )) {
				$datestamp = substr($file,0,8);
				if (is_numeric($datestamp)) {
					// Handling for multiple files on the same date. Those need to be ordered with a-z.
					if(preg_match("/^\d{8}[a-z]{1}(_|\.)/", $file)) {
						$index = substr($file,0,9);
					} else {
						$index = (int) $datestamp;
					}
					$this->images[$index] = array($file, 0, 0);
				}
			    }
			}
			closedir($dir);

			// The directory read-in order probably wasn't useful for us, so sort the array based on keys.
			ksort($this->images);
			
			// Link each entry to next and previous. This is a pretty silly thing to do.
			$keys = array_keys($this->images);
			
			$prev = 0;
			foreach($keys as $key) {
				if($prev != 0) {
					$this->images[$key][1] = $prev;
					$this->images[$prev][2] = $key;
				}
				$prev = $key;
			}
		} else {
			$this->warnings .= "Oops. Can't find the directory $this->imagedir. Might want to check it.\n";
		}			
	}

	// Dump the array to the file
	private function writeFileList() {
		// But use an exclusive lock so we don't have race conditions.
		return file_put_contents($this->filelist,serialize($this->images),LOCK_EX);
	}

	// Read the array from the file
	private function readFileList() {
		$this->images = unserialize(file_get_contents($this->filelist));
	}

	// Finds the position of $index in the array
	function getOrdinalFromIndex($index) {
		$i = 1;
		foreach($this->images as $key => $value) {
			
			if($key == $index) {
				return $i;
			}	
			$i++;
		}	
		return 0;
	}

	// Finds the $indexth key in the array
	function getIndexFromOrdinal($ordinal) {
		$i = 1;
		foreach($this->images as $key => $value) {

			if($i == $ordinal) {
				return $key;
			}
			$i++;
		}	
		return 0;
	}
	
	// We set up our configuration by reading global variables from ame-config.php, based on which mode we were given
	function configure($mode) {
	
		global $ame_allow_rebuild;
		global $ame_rebuild_type;
		global $ame_rebuild_time;
		global $ame_rebuild_reference;
		global $ame_tryhard;
		
		$this->allow_manual_rebuild = $ame_allow_rebuild;
		$this->rebuild_reference = $ame_rebuild_reference;
		$this->rebuild_time = $ame_rebuild_time;
		$this->rebuild_type = $ame_rebuild_type;
		$this->tryhard = $ame_tryhard;
	
		if($mode == "archive") {		
			$this->debug .= "Configuring libdex as archive\n";
			global $ame_archivedir;
			global $archive_cachefile;
			
			$this->imagedir = $ame_archivedir;
			$this->filelist = $archive_cachefile;
		} else if($mode == "rss") {		
			$this->debug .= "Configuring libdex as rss\n";
			global $ame_imagesdir;
			global $rss_cachefile;
			
			$this->imagedir = $ame_imagesdir;
			$this->filelist = $rss_cachefile;		
		} else {
			$this->warnings .= "Invalid configuration mode for libdex! ($mode)\n";
			$this->imagesdir = "";
			$this->filelist = "";
		}
		
		$this->debug .= "Libdex configured as follows:\n Images from: $this->imagedir\n Cache at: $this->filelist\n Rebuild type: $this->rebuild_type\n";
		if($this->rebuild_type == "reference") {
			$this->debug .= "  Reference file: $this->rebuild_reference\n";
		} else if($this->rebuild_type == "timer") {
			$this->debug .= "  Reload every: $this->rebuild_time seconds\n";
		}
		
	}
	
	function load() {
	
		// Signals if the filelist needs to be rebuilt, for now we'll assume no.
		$rebuild = false;

		// Establish whether the filelist is present and/or writable.
		$fileokay = file_exists($this->filelist);

		if($fileokay) {		
			// If it isn't writable, we can't exactly use it for anything, so check that too.
			
			$fileokay = is_writable($this->filelist);
			
			if($fileokay) {
				$this->debug .= "Filelist looks a-ok\n";
			}

		} else {

			$this->debug .= "Filelist missing, trying to create\n";
		
			// If it's not present, try to create it! Duh!
			$dirpath = dirname($this->filelist);
			
			// Does the path have a directory component?
			if($dirpath) {

				// It does. Is the directory present?
				if (!is_dir($dirpath)) {
					// Try to create it
					mkdir($dirpath, 0777, true);
				}
			}

			// Try to create the file
			if(touch($this->filelist)) {
				$fileokay = true;
				$rebuild = true;
				$this->debug .= "Created filelist\n";
			} else {
				$this->debug .= "Couldn't create the filelist\n";
			}
		}
		
		// At this point we want to read it, regenerate it, or give up.
		if(!$fileokay) {
			// The file doesn't exist and cannot be created, or exists but cannot be written to.
			if($this->tryhard) {
				// Since we really want results, we'll just have to read the filesystem live.
				$this->readFilesFromDrive();
				$this->mtime = time();
				if($this->images) {
					$this->debug .= "Tryhard invoked, reading live.\n";
					$this->warnings .= "Running in tryhard mode with no cache\n";				
				} else {
					$this->debug .= "Tried hard, still no go.\n";
					$this->warnings .= "Tried to run in tryhard mode with no cache, still got nothing.\n";
				}

			} else {			
				$this->debug .= "Not tryharding, bailing.\n";
				$this->warnings .= "Unable to create a cache and tryhard not set, bailing.\n";
			}
			// We can't do anything constructive past this point since the cache is unwritable...
			// ... but this debug stuff might be of interest
			$this->debug .= "Images in $this->imagedir (or cache): " . count($this->images) . "\n";
			$this->debug .= "Filelist age: " . (time() - $this->mtime) . " seconds\n";			
			return;
		} else if(!$rebuild) {

			// Determine the type of rebuild procedure and check if a rebuild is required
			if (strcasecmp($this->rebuild_type, "timer")==0) {			
				// If the type is timer, check if the last modified time exceeds the maximum age between rebuilds of the list. 
				$rebuild = ((time()-filemtime($filelist))>$rebuild_time);				
				if($rebuild) {
					$this->debug .= "Rebuild on timer, rebuilding.\n";
				} else {
					$this->debug .= "Rebuild on timer, not rebuilding right now.\n";
				}
			} else if (strcasecmp($this->rebuild_type, "triggered")==0) {
				// If the type is triggered, check what the caller said
				$rebuild = $please_rebuild;
				if($rebuild) {
					$this->debug .= "Rebuild on triggered, rebuilding.\n";
				} else {
					$this->debug .= "Rebuild on triggered, not rebuilding right now.\n";
				}
			} else if (strcasecmp($this->rebuild_type, "reference")==0) {
				// If the type is reference, we compare the mtimes to decide...
				// after checking we actually have something to compare against.
				if (file_exists($this->rebuild_reference)) {
					$rebuild = (filemtime($this->filelist) < filemtime($this->rebuild_reference));

					if($rebuild) {
						$this->debug .= "Rebuild on reference, rebuilding.\n";
					} else {
						$this->debug .= "Rebuild on reference, not rebuilding right now.\n";
					}
				} else {						
					$this->debug .= "Rebuild type is reference, but reference file missing.\n";
					$this->warning .= "Rebuild type is set to reference but the reference file doesn't exist. Cache will go stale!\n";
				}
			} else {
				$this->debug .= "Invalid rebuild type $this->rebuild_type\n";
				$this->warning .= "rebuild_type is not set to a supported option. Please check your configuration.\n";
			}			
		}
		
		// Force rebuild if the user asks and it's allowed
		if($this->allow_manual_rebuild === true && isset($_GET['rebuild'])){
		    $rebuild = true;
		    $this->debug .= "Manual rebuild invoked.\n";
		}
		
		// Try a load if we're not rebuilding
		if(!$rebuild) {
			$this->readFileList();
			if(!$this->images) {
				// Possibly corrupt, possibly just empty. At any rate, try to rebuild it.
				$rebuild = true;			
				$this->debug .= "Load failure, forcing a rebuild.\n";
			} else {
				$this->mtime = filemtime($this->filelist);
			}
		}
		
		// Rebuild if deemed necessary
		if ($rebuild){
			$this->readFilesFromDrive();
			if($this->writeFileList()) {
				$this->debug .= "Rebuilt and wrote the filelist.\n";
				$this->mtime = filemtime($this->filelist);
			} else {
				$this->debug .= "Failed to write the filelist!\n";		
				$this->warning .= "Failed to write the filelist!\n";
				$this->mtime = time();
			}
		}
		
		// Some final debug info
		$this->debug .= "Images in $this->imagedir (or cache): " . count($this->images) . "\n";
		$this->debug .= "Filelist age: " . (time() - $this->mtime) . " seconds\n";
	}
}

class poster {

	// Configuration comes in via ame-config.php and poster->load()

	// Directory where the future lives
	private $updatesdir;

	// Directory where the present lives
	private $imagesdir;

	// Directory where the past lives
	private $comicsdir;

	// Directory to use as an old school semaphore. If you ever see this stick around, something got stuck.
	private $lockdir;

	// File where the current image/alt text is stored. Probably used as a reference by the rss and archive, too.
	private $currentfile ;

	// A temporary file to be used when recreating $currentfile
	private $tempfile;
	
	// How old do files need to be to get picked up? This is a grace period to protect against in-progress uploads.
	// Time in seconds.
	private $delay;

	/// End configuration
	
	public $current = false;
	public $warnings = "";
	public $debug = "";

	function load() {
	
		// Grab our config from the global variables set in ame-config.php
		
		global $poster_lockdir;
		global $poster_currentfile;
		global $poster_tempfile;
		global $poster_delay;
		global $ame_archivedir;
		global $ame_imagesdir;
		global $ame_updatesdir;
		
		$this->updatesdir = $ame_updatesdir;
		$this->imagesdir = $ame_imagesdir;
		$this->comicsdir = $ame_archivedir;
		$this->lockdir = $poster_lockdir;
		$this->currentfile = $poster_currentfile;
		$this->tempfile = $poster_tempfile;
		$this->delay = $poster_delay;
	
		if(file_exists($this->currentfile)) {
			$this->current = unserialize(file_get_contents($this->currentfile));
		}
	
		if(!$this->current) {
			// Something's wrong with the file. That could be a problem. Let's see if we can use libdex to find the newest image.
			
			$this->debug .= "$this->currentfile missing, attempting to regenerate based on the RSS libdex\n";
			
			$libdex = new libdex();
			$libdex->configure("rss");
			$libdex->load();
			
			if($libdex->images) {

				$last = end($libdex->images);
				
				$image = $last[0];
				$baredate = parseDate($image, false);
				$cssdate = parseDate($image, true);
				
				$this->current = array($cssdate, "$this->imagesdir/$image", $baredate);
				
				$this->debug .= "Managed to gather data from $this->imagesdir, trying to write it out\n";
				
				// Okay, we were able to piece together $current. Can we write it back out?
				if(touch($this->currentfile) && touch($this->tempfile)) {
				
					// We can write to the files at least. Let's see if we can get a work lock going
					if(mkdir($this->lockdir,0777,true)) {
						
						file_put_contents($this->tempfile,serialize($this->current));
						
						if(rename($this->tempfile,$this->currentfile)) {
							$this->debug .= "Successfully rebuilt $this->currentfile from $this->imagesdir\n";
						} else {
							$this->debug .= "Can't rename $this->tempfile to $this->currentfile for some reason.\n";
							$this->warnings .= "Unable to rename the tempfile, please check configuration.\n";
						}
						
						if(!rmdir($this->lockdir)) {
							$this->debug .= "Failed to remove the lockdir: $this->lockdir \n";
							$this->warnings .= "Can't remove the lockdir. All further automation will be prevented.\n";
						}
					} else {
						$this->debug .= "Unable to get lock, $this->lockdir either exists already or cannot be created.\n";
					}
				} else {
					$this->debug .= "Can't write to either $this->currentfile or $this->tempfile, we're essentially running live.\n";
					$this->warnings .= "Unable to write down the current page, please check configuration.\n";
				}
			} else {
				$this->debug .= "No $this->currentfile, nothing in $this->imagesdir. Can't do anything here.\n";
				$this->warnings .= "Unable to find a current file to display.\n";
			}
		}

		// Look for an update to do
		$today = date("Ymd");
		$newfiles = glob("$this->updatesdir/$today*");

		if($newfiles === false) {
			$this->debug .= "$this->updatesdir seems to not exist or be accessible.\n";
			$this->warnings .= "The dir for future updates seems to not be there. Check configuration?\n";
		} else if($newfiles) {

			$age = (time() - filemtime($newfiles[0]));
			$this->debug .= "Found new files, age reads as $age seconds.\n";
			
			if($age < $this->delay) {
				$this->debug .= "Age less than the set delay ($this->delay), won't try to update yet.\n";
				return;
			}
			
			if(mkdir($this->lockdir,0777,true)) {
				// Mkdir is atomic, so if it succeeded we got the lock and need to do the work
				// If it didn't succeed, we just fall through to business as usual
				
				// It's possible someone just scooped us, so now that we have the lock we check that the files are still there
				clearstatcache();
				$newfiles = glob("$this->updatesdir/$today*");
				
				if($newfiles) {

					$this->debug .= "Got lock, working\n";

					// Okay, we still have work to do.
					// First we sort the files based on suffix
					$newimages = array();
					$newtxts = array();
					
					foreach($newfiles as $filename) {

						if(is_dir($filename)) {
							$this->debug .= "There's a directory gumming up the autoposter works: $filename\n";
							$this->warnings .= "There's a directory interfering with the autoposter.\n";
							continue;						
						}

						$suffix = substr($filename,-4);
						
						if (strcasecmp($suffix, ".jpg") == 0 || strcasecmp($suffix,".gif") == 0 || strcasecmp($suffix,".png") == 0 ) {
							$this->debug .= "Found new imagefile: $filename\n";
							array_push($newimages, $filename);
						} else if(strcasecmp($suffix, ".txt") == 0) {
							$this->debug .= "Found new textfile: $filename\n";
							array_push($newtxts, $filename);
						} else {
							$this->debug .= "Found new file we don't know what it is: $filename\n";
							$this->warnings .= "Found a mystery file we don't know how to process while looking for updates. Please check what's up.\n";
						}
					}
					
					if((count($newimages) > 1) || (count($newtxts) > 1)) {
						// What's the business logic for multiple updates in a single day?
						// Actually, should probably just correlate the txts to the images and make it show multiples...
						// Just go from arrays to arrays of arrays and you can foreach this stuff neatly
						// RSS would get complicated though. Requires actual thought.
						// RSS could depend on current.txt to figure out what's on the front page...
						$this->debug .= "Found multiple txts or images, unable to proceed with autopost.\n";
						$this->warnings .= "Found multiple txts or images, unable to proceed with autopost.\n";
						return;
					}
			
					$newcomic = false;
					$newtitle = false;
			
					if(count($newimages) == 1) {
						// We have a picture we actually know what to do with

						$newfile = basename($newimages[0]);

						if($this->current) {
						
							$oldfile = basename($this->current[1]);
						
							if($oldfile != $newfile) {
								// It's not a new version of the same page, copy the old one into the archives
								$cpfn = $this->current[1];

								if(!is_dir($this->comicsdir)) {
									// Doesn't look like the dir exists yet, try to create it
									if(mkdir($this->comicsdir, 0777, true)) {
										$this->debug .= "Successfully created $this->comicsdir\n";
									} else {
										$this->debug .= "Failed to create $this->comicsdir!\n";
										$this->warnings .= "Unable to create directory for archives.\n";									
									}
								}

								if(copy($cpfn,"$this->comicsdir/$oldfile")) {
									$this->debug .= "Succeeded in copying $cpfn to $this->comicsdir/$oldfile.\n";
								} else {
									$this->debug .= "Unable to copy $cpfn to $this->comicsdir/$oldfile!\n";
									$this->warnings .= "Couldn't copy the old current page to the archives!\n";
								}							
								
							} else {
								$this->debug .= "Filename match with current page. Will replace.\n";
							}
						}

						if(!is_dir($this->imagesdir)) {
							// Seems like we don't have a directory for new images yet, try to create
							if(mkdir($this->imagesdir, 0777, true)) {
								$this->debug .= "Successfully created $this->imagesdir\n";
							} else {
								$this->debug .= "Failed to create $this->imagesdir!\n";
								$this->warnings .= "Unable to create directory for new files.\n";
							}
						
						}
						
						// Move the new images to the images dir
						if(rename($newimages[0], "$this->imagesdir/$newfile")) {
						
							if(!$this->current) {
								// If we don't have a current set up yet, we start with blanks.
								// If we do, we just end up overwriting what changes.
								$this->current = array("", "", "");
							}
						
							$this->current[0] = parseDate($newfile, true);
							$this->current[1] = "$this->imagesdir/$newfile";
							$this->debug .= "Successfully moved $newimages[0] to $this->imagesdir/$newfile\n";
							$newcomic = true;
						} else {
							$this->debug .= "Failed to move $newimages[0] to $this->imagesdir/$newfile\n";
							$this->warnings .= "Found a new page, but unable to autopost it. Please check configuration.\n";
						}
					}
					
					if(count($newtxts) == 1) {
						// We have a title text we actually know what to do with
						
						if($this->current) {
							$newtitle = rtrim(file_get_contents($newtxts[0]));
							$this->current[2] = $newtitle;
							$this->debug .= "Set the title text to: $newtitle\n";
						} else {
							$this->debug .= "New title text found, but nothing to set it on.\n";
						}
					}
					
					// Try to write stuff out if something changed
					if($newcomic || $newtitle) {
					
						if(file_put_contents($this->tempfile,serialize($this->current))) {
							if(rename($this->tempfile,$this->currentfile)) {
								$this->debug .= "Successfully autoposted.\n";
								if($newtitle) {
									if(unlink($newtxts[0])) {
										$this->debug .= "Cleaned up the title text file.\n";
									} else {								
										$this->debug .= "Couldn't remove $newtxts[0]!\n";
										$this->warnings .= "Unable to clean up title text file!\n";
									}
								}
							} else {
								$this->debug .= "Can't rename $this->tempfile to $this->currentfile for some reason.\n";
								$this->warnings .= "Unable to rename the tempfile, please check configuration.\n";
							}
						} else {
							$this->debug .= "Can't write to $this->tempfile!\n";
							$this->warnings .=" Unable to write to the temporary file.\n";
						}
					}
				}
				
				if(!rmdir($this->lockdir)) {
					$this->debug .= "Failed to remove the lockdir: $this->lockdir \n";
					$this->warnings .= "Can't remove the lockdir. All further automation will be prevented.\n";
				}
			} else {
				$this->debug .= "Unable to get lock, $this->lockdir either exists already or cannot be created.\n";
			}
		}
	}
}

// Content generation functions follow

// Output the archive index
function ame_archive_index() {

	// Register globals used in the function.
	// Config and explanations in ame-config.php

	global $ame_addressing_mode;
	
	global $ame_allow_debug;
	global $archive_chapters;
	global $archive_link_prefix;
	
	global $glog_caught;

	$libdex = new libdex();
	$libdex->configure("archive");
	$libdex->load();

	if($libdex->images) {
		$going = false;

		$i = 1;
		foreach($libdex->images as $key => $value) {
			if(isset($archive_chapters[$key])) {
			
				if(!$going) {
					$going = true;
				} else {
					echo "</div>\n";
				}
				
				echo "<div class=\"chapter\">\n";
				echo "\t<h4>$archive_chapters[$key]</h4>\n";
			}
			
			// Padding the numbers for a constant width
			$padded = str_pad($i, 3, "0", STR_PAD_LEFT);

			if($ame_addressing_mode == "d") {
				echo "\t<a href=\"$archive_link_prefix?d=$key\">$padded</a>\n";
			} else {
				echo "\t<a href=\"$archive_link_prefix?d=$i\">$padded</a>\n";
			}
			$i++;
		}
	} else {
		// Doesn't seem to be anything in the archive.
		echo "<p>Nothing here yet!</p>";
	}

	// Debug output section
	if($ame_allow_debug && isset($_GET['debug'])) {
		// If asked for and allowed, spill everything we have
		echo "<!--\n";
		$date = getdate();
		if($libdex->warnings != "") {
			echo "\nLibdex warnings:\n$libdex->warnings";
		}
		if($libdex->debug != "") {
			echo "\nLibdex debug:\n$libdex->debug";	
		}
		if($glog_caught != "") {
			echo "\nPHP errors caught by glog:\n$glog_caught\n";
		}
		echo "-->\n";
	} else if($libdex->warnings != "") {
		// Warnings get output even if debug isn't on
		echo "<!--\nLibdex warnings:\n$libdex->warnings-->";
	}
}

// Output an archive page
function ame_archive() {

	// Register globals used within function. Not the prettiest thing I've ever done.
	// Explanations and settings in ame-config.php.
	global $ame_addressing_mode;
	global $ame_allow_debug;
	global $ame_comic_author;
	global $ame_home_page;
	global $ame_license_link;
	global $ame_license_link_text;

	global $archive_cdn_prefix;
	global $archive_default_to_page;
	global $archive_divider;
	global $archive_index;
	global $archive_link_prefix;
	global $archive_nav_linelength;
	global $archive_nav_numbers;
	global $archive_nav_placement;
	global $archive_show_arrows;
	global $archive_show_backnext;
	global $archive_show_copyright;
	global $archive_show_firstlast;

	global $glog_caught;
	
	$copyright = "";
	if($archive_show_copyright) {
		$copyright = $ame_comic_author;	
	}

	$libdex = new libdex();
	$libdex->configure("archive");

	// Have libdex do its thing and load the files, one way or another
	$libdex->load();

	// The filelist should now be in $libdex->images. Get the number of files.
	$filecount = count($libdex->images);	
	
	// Enable debug if it's asked for and allowed. Output is at the end of the file.
	$debug = false;
	if($ame_allow_debug === true && isset($_GET['debug'])) {
		$debug = true;
	}

	if($filecount == 0) {
		echo "<p class=\"warning\">Hmm. We didn't find any images. Did you add any?\n";
		// Output debug and warnings as necessary
		if($debug) {
			echo "\n<!--\n";
			if($glog_caught != "") {
				echo "\nPHP errors caught by glog:\n$glog_caught\n";
			}
			if($libdex->warnings != "") {
				echo "\nLibdex warnings:\n$libdex->warnings";
			}
			if($libdex->debug != "") {
				echo "\nLibdex debug:\n$libdex->debug";	
			}
			echo "-->\n";
		} else if($libdex->warnings != "") {
			echo "<!--\nLibdex warnings:\n$libdex->warnings-->";
		}
		return;
	}

	// First we figure out whether the user is passing us a date index or a page ordinal (or neither)
	$dgiven = false;
	$pgiven = false;
	$dindex = 0;
	$pindex = 0;

	if(isset($_GET['d']) && preg_match("/^\d{8}[a-z]?$/", $_GET['d'])) {
		$dgiven = $_GET['d'];
	}

	if(isset($_GET['p']) && is_numeric($_GET['p'])) {
		$pgiven = (int) $_GET['p'];
	}

	if($dgiven || $pgiven) {
	
		// We got SOMETHING from the user
		if ($dgiven) {
			// Looks like a date index...
			if(isset($libdex->images[$dgiven])) {
				// Bingo!
				$dindex = $dgiven;
			} else {
				// Ruh roh, asking for a page that doesn't exist. We could follow the configuration for what to default to, but
				// being asked for something that doesn't exist is different from not being asked for anything... first page seems most reasonable here.
				// An actual page not found might be better still, though.
				$pindex = 1;
			}
		} else if($pgiven) {
			// That's definitely a number right there.
			if($pgiven > $filecount) {
				// If they're asking for a page past the end of the archive, just give the last page.
				$pindex = $filecount;
			} else if ($pgiven < 1) {
				// Zeroth or less page. Give a negative offset from last, or first if out of bounds.
				if($pgiven > -$filecount) {
					$pindex = $filecount + $pgiven;
				} else {
					$pindex = 1;
				}
			} else {
				// Looks like a perfectly cromulent index
				$pindex = $pgiven;
			}
		}
	} else {
		// We didn't get anything from the user, so we default to either the last or the first page as per our configuration.
		if ($archive_default_to_page != "last") {
			$pindex = 1; 
		} else {
			$pindex = $filecount;
		}
	}
	
	// At this point we have either a valid pindex or a valid dindex. Rather than keeping track of which it is,
	// we'll unify it by finding whatever it is we're looking for and moving forward from there.

	$filename = "";		// Filename of the page we're on
	$index = 0;		// The actual array index (d-style)
	$previndex = 0;		// Likewise for the previous page
	$nextindex = 0;		// And the next
	$ordinal = 0;		// The numerical index. How manieth page we're on.

	if($dindex) {
		$index = $dindex;
		$filename  = $libdex->images[$dindex][0];
		$previndex = $libdex->images[$dindex][1];
		$nextindex = $libdex->images[$dindex][2];
		$ordinal = $libdex->getOrdinalFromIndex($index);
	} else {
		$index = $libdex->getIndexFromOrdinal($pindex);
		$filename  = $libdex->images[$index][0];
		$previndex = $libdex->images[$index][1];
		$nextindex = $libdex->images[$index][2];		
		$ordinal = $pindex;
	}

	$preloadfilename = false;

	// Check the referrer to see which way if any we should be preloading.
	// Basically, load the next page if we came directly from the previous, or the previous page if we came directly from the next.
	// If we're just jumping in somewhere we can't really make a good call, especially if the short hop navigation is on.
	if(isset($_SERVER['HTTP_REFERER'])) {
		$matches = array();
		if(preg_match("/p=(\d+)/", $_SERVER['HTTP_REFERER'], $matches)) {
			$referring = $matches[1];
			
			if(($referring == $ordinal-1) && $nextindex != 0) {
				$preloadfilename = $libdex->images[$nextindex][0];
			} else if (($referring == $ordinal+1) && $previndex != 0) {
				$preloadfilename = $libdex->images[$previndex][0];
			}
		} else if(preg_match("/d=(\d{8})/", $_SERVER['HTTP_REFERER'], $matches)) {
			$referring = $matches[1];	
			
			if(($referring == $previndex) && $nextindex != 0) {
				$preloadfilename = $libdex->images[$nextindex][0];
			} else if(($referring == $nextindex) && $previndex != 0) {
				$preloadfilename = $libdex->images[$previndex][0];			
			}
		}
	}

	// Set up the next and last buttons, and make sure they're sane and mode-appropriate
	$next = "";
	$back = "";

	if($ame_addressing_mode === "d") {
		if($nextindex != 0) { 
			$next = "d=" . $nextindex;
		}
		if($previndex != 0) { 
			$back = "d=" . $previndex;
		}
	} else {
		$next = $ordinal + 1;
		$back = $ordinal - 1;
		
		if ($next > $filecount) { $next = $filecount; }
		if ($back < 1) { $back = 1; }
		
		$next = "p=" . $next;
		$back = "p=" . $back;
	}

	// If debug mode is enabled, make the back and forward links automatically add debug to the url
	if ($debug) {
	    $next .= "&amp;debug";
	    $back .= "&amp;debug";
	}

	// Prepare the image source and links
	$comicwidth=0;
	$comicheight=0;
	$adden="";
	
	$baredate = parseDate($filename,false);
	$cssdate = parseDate($filename,true);

	if($comicwidth==0 || $comicheight==0) {
		list($comicwidth, $comicheight, $itype, $iattr)= getimagesize($libdex->imagedir."/".$filename);
		$adden="style=\"border:0px;width:".$comicwidth."px;height:".$comicheight."px;\"";
	}
	
	// If there's a next page, we link the comic page to it
	if ($nextindex != 0){ 
		$image="<p id=\"cg_img\"><a href=\"$archive_link_prefix?$next\"><img $adden src=\"$archive_cdn_prefix$libdex->imagedir/$filename\" alt=\"Comic for $baredate\" title=\"Next\" /></a></p>\n";
	} else {
		$image="<p id=\"cg_img\"><img $adden src=\"$archive_cdn_prefix$libdex->imagedir/$filename\" alt=\"Comic for $baredate\" title=\"End\" /></p>\n";
	}

	echo "<p class='date'>Comic for $cssdate</p>\n";

	// Note: The navigation bar doesn't move, the image does
	// Display the image before the navigation bar if configured that way
	if ($archive_nav_placement != "above") {
		echo $image;
	}

	// display the navigation bar
	if (($archive_show_backnext != 0 || $archive_show_arrows != 0) && $filecount > 1){

		echo "<p id=\"cg_nav1\">\n";
			
		// Display the 'First Comic' Link if First/Last is enabled
		if ($archive_show_firstlast != 0){ 
			if ($previndex != 0) {
				if($ame_addressing_mode === "d") {
					reset($libdex->images);
					$first = key($libdex->images);					
					echo "\t<a href=\"$archive_link_prefix?d=$first\" id=\"cg_first\"><span>First Comic</span></a>";
				} else {
					echo "\t<a href=\"$archive_link_prefix?p=1\" id=\"cg_first\"><span>First Comic</span></a>";
				}
			} else {
				echo "\t<span id=\"cg_first\"><span>First Comic</span></span>";
			}
			echo "<span class=\"cg_divider\"> $archive_divider </span>\n";
		}
			
		// If there's a previous page, link to it
		if ($previndex != 0){    
			echo "\t<a href=\"$archive_link_prefix?$back\" id=\"cg_back\"><span>";
			if ($archive_show_arrows != 0) { echo "&laquo; "; }
			if ($archive_show_backnext != 0) { echo "Previous Comic"; }
			echo "</span></a>";
		} else { // Otherwise, we're currently showing the first pic, so there's no back link.
			echo "\t<span id=\"cg_back\"><span>";
			if ($archive_show_arrows != 0) { echo "&laquo; "; }
			if ($archive_show_backnext != 0) { echo "Previous Comic"; }
			echo "</span>";
		}
		// Print a link to the archive page
		echo "<span class=\"cg_divider\"> $archive_divider </span>\n";
		echo "\t<a href=\"$archive_index\">Archives</a>";
		echo "<span class=\"cg_divider\"> $archive_divider </span>\n";
		
		// Same thing for the 'Next Comic' links...
		if ($nextindex != 0){
			echo "\t<a href=\"$archive_link_prefix?$next\" id=\"cg_next\"><span>";
			if ($archive_show_backnext != 0) { echo "Next Comic"; }
			if ($archive_show_arrows != 0) { echo " &raquo;"; }
			echo "</span></a>";
		} else {
			echo "\t<span id=\"cg_next\">";
			if ($archive_show_backnext != 0) { echo "Next Comic"; }
			if ($archive_show_arrows != 0) { echo " &raquo;"; }
			echo "</span>";
		}
		
		// Print the link to the newest page
		if ($archive_show_firstlast != 0){ 
			echo "<span class=\"cg_divider\"> $archive_divider </span>\n";
			echo "\t<a href=\"$ame_home_page\" id=\"cg_last\"><span>Newest Comic</span></a>\n";
		}
		echo "</p>\n";
	}

	// Display links to individual pages if configured to
	if ($archive_nav_numbers != 0 && $filecount > 1){
	
		// display textlinks
		echo "<p id=\"cg_nav2\">\n";

		$start = 1;
		$end = $filecount;
		
		// Negative means show all, positive means 'show this many on both sides of current'
		if($archive_nav_numbers > 0) {
			
			if($filecount > $archive_nav_numbers * 2 + 1) {
				// We have enough pages and/or a small enough ask to display a range, rather than all pages

				$start = $ordinal - $archive_nav_numbers;
				$end = $ordinal + $archive_nav_numbers;

				// If start would be out of bounds, shift end farther, and vice versa.
				if($start < 1) {
					$end += -($start-1);
					$start = 1;
				} else if ($end > $filecount) {
					$start -= $end - $filecount;
					$end = $filecount;
				}
			}
		}
		
		$i = 0;
		foreach($libdex->images as $key => $data) {

			// Fast forward until we get into the range we want
			$i++;
			if($i < $start) {
				continue;
			} else if($i > $end) {
				break;
			}

			// 0-padded for consistent ui width
			$padded = str_pad($i, 3, "0", STR_PAD_LEFT);

			if($i == $ordinal) {
				// No link for current page
				echo "\t<b>$padded</b>";
			} else {
				if($ame_addressing_mode === "d") {
					echo "\t<a href=\"$archive_link_prefix?d=$key\">$padded</a>";			
				} else {
					echo "\t<a href=\"$archive_link_prefix?p=$i\">$padded</a>";			
				}
			}

			// add dividers and linebreaks
			if (($start-$i-1) % $archive_nav_linelength == 0) { 
				echo "<br />\n";
			} else if ($i!=$end){
				echo "<span class=\"cg_divider\"> $archive_divider </span>\n";
			}
			
		}
		echo "</p>\n";
	}

	// Display the image below the navigation bar if configured that way
	if ($archive_nav_placement == "above") {
	    echo $image;
	}

	// display license and copyright info, if any
	if($ame_license_link != "" || $copyright != "") {
		echo "<p id=\"cg_credits\">\n";

		if ($ame_license_link != "") {
		    echo "\t<a href=\"$ame_license_link\">$ame_license_link_text</a>";
		}    
		if ($copyright != ""){
			if($ame_license_link != "") {
				// If we have both we want a divider to look pretty
				echo " $archive_divider ";
			}
		    echo "&copy; $copyright\n";
		}
		echo "</p>\n";
	}
	
	// Preload the next comic image if we made a guess
	if ($preloadfilename){
	    echo "
	<script type=\"text/javascript\">function preloader() {
	    if (document.images) {
		var img = new Image();
		img.src = \"$archive_cdn_prefix$libdex->imagedir/$preloadfilename\";
	    }
	}
	function addLoadEvent(func) {
	    var oldonload = window.onload;
	    if (typeof window.onload != 'function') {
		window.onload = func;
	    } else {
		window.onload = function() {
		    if (oldonload) {
			oldonload();
		    }
		    func();
		}
	    }
	}
	addLoadEvent(preloader);
	</script>\n";
	}
	
	// Output debug and warnings as necessary
	if($debug) {
		echo "\n<!--\n";
		if($libdex->warnings != "") {
			echo "\nLibdex warnings:\n$libdex->warnings";
		}
		if($libdex->debug != "") {
			echo "\nLibdex debug:\n$libdex->debug";	
		}
		if($glog_caught != "") {
			echo "\nPHP errors caught by glog:\n$glog_caught\n";
		}
		echo "-->\n";
	} else if($libdex->warnings != "") {
		echo "<!--\nLibdex warnings:\n$libdex->warnings-->";
	}
}

// Output the current comic
function ame_index() {

	// Declare globals used in the function
	// Explanations and configuration in ame-config.php

	global $ame_allow_debug;
	
	global $ame_oops_date;
	global $ame_oops_image;
	global $ame_oops_title;

	global $glog_caught;
	
	$poster = new poster();
	$poster->load();
	
	
	if($poster->current) {
		$dateline = $poster->current[0];
		$image = $poster->current[1];
		$title = $poster->current[2];
	} else {
		$dateline = $ame_oops_date;
		$image = $ame_oops_image;
		$title = $ame_oops_title;
	}

	echo "						<p style=\"font-size: 18px;\">Comic Page for $dateline</p>\n";
	echo "						<img src=\"$image\" alt=\"Newest comic\" title=\"$title\">\n";

	// Debug output section
	if($ame_allow_debug && isset($_GET['debug'])) {
		// If asked for and allowed, spill everything we have
		echo "<!--\n";
		$date = getdate();
		echo "Time now according to us: " , date("Ymd H:i:s") . "\n";
		if($poster->warnings != "") {
			echo "\nAutoposter warnings:\n$poster->warnings";
		}
		if($poster->debug != "") {
			echo "\nAutoposter debug:\n$poster->debug";	
		}
		echo "Data for current page:\n";
		print_r($poster->current);
		if(isset($glog_caught) && ($glog_caught != "")) {
			echo "\nPHP errors caught by glog:\n$glog_caught\n";
		}
		echo "-->\n";
	} else if($poster->warnings != "") {
		// Warnings get output even if debug isn't on
		echo "<!--\nAutoposter warnings:\n$poster->warnings-->";
	}
}

// Output the RSS (Yeah, yeah, ATOM) feed
function ame_rss() {

	// Register globals used within function. Not the prettiest thing I've ever done.
	// Explanations and settings in ame-config.php.
	global $ame_allow_debug;
	global $ame_addressing_mode;
	global $ame_comic_author;
	global $ame_comic_title;
	global $ame_comic_subtitle;
	global $ame_home_page;

	global $archive_link_prefix;

	global $glog_caught;
	
	global $rss_show_maximum;
	global $rss_show_default;
	global $rss_show_images;
	global $rss_allow_images;


	$libdex = new libdex();
	$libdex->configure("rss");
	$libdex->load();

	if($ame_allow_debug === true && isset($_GET['debug'])) {
		header("Content-Type: text/plain; charset=utf-8");
		echo "Debug mode!\n";
		if($libdex->warnings != "") {
			echo "Libdex warnings:\n$libdex->warnings";
		}
		echo "Libdex debug:\n$libdex->debug";
		return;
	}

	// Format the libdex mtime appropriately for ATOM. This is the mtime of the cache file, or time() if we're running live.
	// TODO: That may be a bit of a problem if running in any mode except reference. Should test how RSS clients behave.
	//  - mtime of newest image file might be a suitable approach
	$feedmodified = date(DATE_ATOM, $libdex->mtime);

	// RSS feed header - Outputs the RSS feed formatting headers, but only if debug mode is turned off - which is the normal status anyway though...
	header("Content-Type: application/atom+xml; charset=utf-8");
	echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>
	<feed xmlns=\"http://www.w3.org/2005/Atom\">
		<title>$ame_comic_title</title>
		<link href=\"$ame_home_page\" />
		<author>
			<name>$ame_comic_author</name>
		</author>
		<id>$ame_home_page</id>
		<subtitle>$ame_comic_subtitle</subtitle>
		<rights>© $ame_comic_author</rights>
		<generator version=\"0.2a\">ame-rss</generator>
		<link rel=\"self\" href=\"http://".$_SERVER['SERVER_NAME'] . htmlspecialchars($_SERVER['REQUEST_URI'])."\"  type=\"application/atom+xml\" />
		<updated>$feedmodified</updated>\n";

	// Figure out how many items to show. 
	if (isset($_GET['number']) && is_numeric($_GET['number']) && ($_GET['number'] > 0)) {
		$max_item_counter = $_GET['number'];

		if($max_item_counter > $rss_show_maximum) {
			$max_item_counter = $rss_show_maximum;
		}
	} else {
		$max_item_counter = $rss_show_default;
	}

	// RSS feed items - Outputs the individual RSS feed items (Links to the comics)
	// Start from the last image and work backwards

	end($libdex->images);

	// Convoluted numbering, in case we're using ordinal addressing. Newest page is a special case, second newest is 0, then -1...
	for($i = 1; $i-1 > -$max_item_counter; $i--) {

		// Check we're still in bounds
		if(key($libdex->images)) {
		
			// Get the filename from the cache
			$data = current($libdex->images);
			$filename = $data[0];

			// Convert the filename of the comic into a unix timestamp, then converts the timestamp into a date
			// Used in the title of the rss feed item
			$date = DateTime::createFromFormat("Ymd", substr($filename,0,8))->format("l, F jS, Y"); 
			
			// Use the time the file was modified (i.e. uploaded) as the publish date
			$pub = date(DATE_ATOM, filemtime("$libdex->imagedir/$filename"));
			
			if($ame_addressing_mode == "d") {
				// For date-mode addressing we use the proper links as ids (since they're expected to be unique and static)
				$item_id = $archive_link_prefix . "?d=" . key($libdex->images);
				$item_link = $item_id;
			} else {
				// For negative-index addressing we match the previous implementation that uses the mtime of the file
				$item_id = "$archive_link_prefix?$pub";
				$item_link = "$archive_link_prefix?p=$i";		
			}
			
			// The newest page always links to the main site
			if($i == 1) {
				$item_link = $ame_home_page;
			} 
			
			// Data collected, generate feed item
			echo "	<entry>
			<title>Comic for $date</title>
			<id>$item_id</id>
			<updated>$pub</updated>
			<content type=\"html\">Comic for $date is located at &lt;a href=\"$item_link\"&gt;$item_link&lt;/a&gt;</content>
			<link href=\"$item_link\" rel=\"alternate\" hreflang=\"en-us\" title=\"Comic for $date\"/>\n";
			
			$asked_images = (isset($_GET['show_image']) || isset($_GET['show_images']));

			// Include image enclosures if configured, or asked for and allowed
			if($rss_show_images || ($asked_images && $rss_allow_images)) {
				$filesize = filesize("$libdex->imagedir/$filename");
				
				// Check the MIME type. The other three variables are ignored.
				list($imagewidth, $imageheight, $itype, $iattr) = getimagesize("$libdex->imagedir/$filename");
				switch($itype) {
					case IMAGETYPE_GIF:
						$filetype = "image/gif";
						break;
					case IMAGETYPE_PNG:
						$filetype = "image/png";
						break;
					default:
						$filetype = "image/jpeg";
				}
				echo "\t\t<link rel=\"enclosure\" href=\"$libdex->imagedir/$filename\" length=\"$filesize\" type=\"$filetype\" />\n";
			}
			echo "	</entry>\n";
		}	
			
		// Rewind back one page
		prev($libdex->images);
	}
	echo "</feed>";
}
?>