<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Index scaffolding</title>
	<style type="text/css">
		body {
			background-color: #111111;
			color: #fff;
			min-width: 1250px;
		}		
		a {
			color: inherit;
			text-decoration: inherit;
		}
		li {
			list-style: none;
		}
		#menuetc {
			float: left;
		}		
		#cont {
			font-family: monospace;
			position: absolute;
			left: 10%;
			top: 2%;
			width: 60%;
		}
		img {
			horizontal-align: middle;
		}
	</style>	
</head>
<body>
	<div id="menuetc">
		<ul>
			<li><a href="rss.php">RSS</a></li>
			<li>---</li>
			<li><a href="index.php">Home</a></li>
			<li><a href="archive_index.php">Archive</a></li>
		</ul>
	</div>
	<div id="cont">
<?php

// If we're missing the libs for some reason, we should just get a function warning instead of crashing through the scaffolds. Which is pretty okay.
// Of course, if we're missing the config we'll still explode at that point.
include("./ame-libs.php");
ame_index();
?>
	</div>
</body>
</html>