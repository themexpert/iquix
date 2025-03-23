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
<form name="installation" method="post" data-installation-form>
	<p class="section-desc">We are now performing the installation of Quix on the site. This process may take a little
		while depending on the Internet connectivity of your server. While we are at it, you should get some coffee ...
	</p>

	<div data-installation-completed style="display: none;margin-bottom: 20px;">
		<hr />
		<div class="text-success">Installation completed successfully. Please click on the Next Step button to proceed.
		</div>
	</div>

	<div data-install-progress>

		<div class="install-progress">
			<div class="row-table">
				<div class="col-cell">
					<div data-progress-active-message="">Extracting component files</div>
				</div>
				<div class="col-cell cell-result text-right">
					<div class="progress-result" data-progress-bar-result="">20%</div>
				</div>
			</div>

			<div class="progress">
				<div class="progress-bar progress-bar-info progress-bar-striped" data-progress-bar=""
					style="width: 20%">
				</div>
			</div>
		</div>


		<ol class="install-logs list-reset" data-progress-logs="">
			<li class="active" data-progress-extract>
				<b class="split__title">Extracting component files</b>
				<span class="progress-state text-info">Extracting</span>
				<div class="notes"></div>
			</li>

			<?php include __DIR__ . '/installing.steps.php'; ?>
		</ol>
	</div>

	<input type="hidden" name="option" value="com_iquix" />
	<input type="hidden" name="active"
		value="<?php echo $active; ?>" />
</form>

<script type="text/javascript">
	$(document).ready(function() {
		es.installation.extract();
	});
</script>