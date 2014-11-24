<?php
// Not much to scaffold when you're generating the entire thing!

// If we're missing the libs for some reason, we should just get a function warning instead of crashing through the scaffolds. Which is pretty okay.
// Of course, if we're missing the config we'll still explode at that point.
require_once("./ame-libs.php");
ame_rss();
?>