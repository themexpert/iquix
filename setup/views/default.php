<?php
/**
* @package		Quix
* @copyright	Copyright (C) 2010 - 2017 ThemeXpert.com. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Quix is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
defined('_JEXEC') or die('Unauthorized Access');
?>
<!DOCTYPE html>
<html class="demo-mobile-horizontal" lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Installing Quix Visual Builder</title>


	<link href="<?php echo QX_SETUP_URL;?>/assets/images/quix-logo.png" rel="shortcut icon" type="image/vnd.microsoft.icon"/>

	<link rel="stylesheet" href="//stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
	<link type="text/css" href="<?php echo JURI::root(true);?>/media/jui/css/icomoon.css" rel="stylesheet" />

	<link href="//fonts.googleapis.com/css?family=Poppins:300,400" rel="stylesheet">

	<link type="text/css" href="<?php echo QX_SETUP_URL;?>/assets/styles/style.css" rel="stylesheet" />
	
	<script src="//code.jquery.com/jquery-3.3.1.min.js" crossorigin="anonymous"></script>
	<script src="//cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js" integrity="sha384-cs/chFZiN24E4KMATLdqdvsezGxaGsi4hLGOzlXwp5UZB1LY//20VyM2taTB4QvJ" crossorigin="anonymous"></script>
	<script src="//stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm" crossorigin="anonymous"></script>

	<script type="text/javascript">jQuery(function($){ $(".hasTooltip").tooltip({"html": true,"container": "body"}); });</script>

	<script src="<?php echo JURI::root(true);?>/administrator/components/com_iquix/setup/assets/scripts/script.js" type="text/javascript"></script>
</head>

<body class="step<?php echo $active;?>">
	<div class="container">
		<header class="header mb-3">
			<div class="row">
				<div class="col text-center">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300" width="40" height="40">
						<g fill="#5D3ED2">
							<path d="M26.6 181.7h13.2v13.2H26.6zM124.958 11.528l13.106-1.567 1.567 13.106-13.106 1.567zM83.09 40.166l13.044 2.026-2.027 13.044-13.044-2.026zM76.448 60.511l12.617-3.879 3.88 12.617-12.617 3.879zM35.724 52.324l11.761-5.991 5.992 11.761-11.761 5.992zM122.3 37.8h13.2V51h-13.2zM98.236 42.122l13.034-2.087 2.086 13.034-13.033 2.087z"/>
							<path d="M117.892 30.75l13.157-1.062 1.063 13.157-13.158 1.063zM49.694 100.392l12.812-3.177 3.177 12.812-12.811 3.177zM100.786 23.666l13.138-1.273 1.272 13.138-13.138 1.273zM35.9 172.8h13.2V186H35.9zM16.104 86.94l8.907 9.74-9.741 8.908-8.908-9.742zM60.654 50.86l13.076-1.802 1.802 13.076-13.076 1.802zM63.5 76.9h13.2v13.2H63.5zM35.203 88.596l12.22 4.995-4.996 12.22-12.219-4.995zM33.455 111.935l12.62-3.874 3.874 12.62-12.62 3.873z"/>
							<path d="M44.575 83.318l12.915-2.73 2.73 12.914-12.914 2.731zM83.475 18.291l12.924 2.687-2.687 12.924-12.924-2.687zM28.133 65.268l12.907 2.764-2.764 12.907-12.907-2.764zM25.771 116.525l12.99 2.346-2.345 12.99-12.99-2.346zM18.043 153.36l13.002 2.274-2.274 13.002-13.002-2.275zM41.112 121.957l12.826-3.117 3.117 12.827-12.827 3.116zM6.649 110.7l12.655 3.755-3.756 12.655-12.654-3.755zM25.373 134.924l13.097 1.646-1.646 13.097-13.098-1.646zM34.649 155.489l13.167-.937.937 13.167-13.167.937zM62.55 29.303l13.057 1.938-1.937 13.057-13.058-1.938zM55.644 63.298l12.465 4.345-4.346 12.465L51.3 75.762zM7.048 133.895l12.464 4.347-4.347 12.463-12.463-4.346zM21.982 172.356l12.956 2.525-2.526 12.956-12.955-2.525z"/>
							<path d="M202.3 231.4l-41.7-36.9 29.2-33 107.3 95.2-29.2 33L238 263l-.2.2c-22.7 16.8-51 26.8-81.4 26.8-61 0-112.7-40-130.5-95.1v-15.1h13.2v-39.5h13.2V114h13.2V87.3h13.2V74.1h13.7l-.1-13.2h12.8V47.7h26.4V21.3h13.2V17c4-.3 7.8-.5 11.7-.5 75.5 0 136.8 61.2 136.8 136.7 0 24-6.1 46.6-17 66.2l-35.9-31.8c4.4-10.6 6.8-22.2 6.8-34.4 0-50-40.5-90.5-90.5-90.5S66 103.2 66 153.2s40.5 90.5 90.5 90.5c16.6 0 32.2-4.5 45.6-12.3h.2z"/>
						</g>
					</svg>
				</div>
			</div>
		</header> <!-- header -->

		<div class="main">
			<div class="row">
				<div class="col-8">
					<div class="content shadow p-5 bg-white rounded">
						<?php include(__DIR__ . '/steps/' . $activeStep->template . '.php'); ?>

						<div class="footer">
							<?php include(dirname(__FILE__) . '/default.footer.php'); ?>
						</div>
					</div>
				</div>

				<div class="col-4">
					<div class="steps">
						<?php include(__DIR__ . '/default.steps.php'); ?>
					</div>
				</div>
			</div>
		</div> <!-- main -->
	</div> <!-- container -->
</body>
</html>
