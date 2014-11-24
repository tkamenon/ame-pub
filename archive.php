<!DOCTYPE HTML>
<html>
<head>
	<meta charset="UTF-8">
	<title>Archive scaffolding</title>
	<style type="text/css">
		body {
			background-color: #111111;
			color: #fff;
			min-width: 1250px;
		}

		.archive-cont {
			padding-bottom: 10px;
			width: 950px;
			margin: auto;
		}

		.arch-header {
			text-align: center;
			width: 950px;
			margin-top: 10px;
			color: #fff;
			font-size: 17px;
			font-weight: bold;
		}

		.arch-cont {
			width: 825px;
			margin: auto;
			text-align: center;
			margin-bottom: 10px;
			padding: 20px 10px 0 10px;
		}

		.comic {
			font-size: 16px;
		}

		.comic img {
			padding: 10px 0;
			margin-bottom: 10px;
		}

		a, a:visited {
			color: #0066CC;
			text-decoration: none;
		}

		a:hover {
			text-decoration: underline;
		}

		.date {
			margin-bottom: 10px;
		}

		.arch-disc {
			font-size: 12px;
			margin-top: 20px;
		}
	</style>	
</head>
<body>
	<div class="archive-cont">
		<div class="arch-cont" style="position:absolute;top:140px;width:950px;">
			<div class="comic">
<?php
// If we're missing the libs for some reason, we should just get a function warning instead of crashing through the scaffolds. Which is pretty okay.
// Of course, if we're missing the config we'll still explode at that point.
include("./ame-libs.php");
ame_archive();
?>
			</div>
		</div>
	</div>
</body>
</html>