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

	<div class="text-center mb-5">
		<h2>Installing Quix</h2>
		<p class="text-muted">We are now performing the installation of Quix on the site. This process may take a little
			while depending on the Internet connectivity of your server. While we are at it, you should get some coffee
			...</p>
	</div>

	<div class="mb-2 d-none" data-installation-completed>
		<hr />
		<div class="text-success">Installation completed successfully. Please click on the Next Step button to proceed.
		</div>
	</div>


	<div class="card" data-install-progress>

		<div class="card-header">
			<div class="d-flex justify-content-between mb-2">
				<div data-progress-active-message>Installing Quix Pagebuilder</div>
				<div class="progress-result" data-progress-bar-result="">20%</div>
			</div>
			<div class="progress">
				<div class="progress-bar progress-bar-striped progress-bar-animated" data-progress-bar=""
					style="width: 20%"></div>
			</div>
		</div>


		<ul class="list-group list-group-flush install-logs list-reset" data-progress-logs="">
			<li class="list-group-item active" data-progress-download>
				<b class="split__title">Downloading Installation Files...</b>
				<span class="progress-state text-info float-right">Downloading</span>
				<div class="notes"></div>
			</li>

			<?php include __DIR__ . '/installing.steps.php'; ?>
		</ul>
	</div>



	<input type="hidden" name="option" value="com_iquix" />
	<input type="hidden" name="install" value="1" />
	<input type="hidden" name="active"
		value="<?php echo $active; ?>" />
</form>

<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('[data-installation-submit]').addClass('d-none');
		// Immediately proceed with installation
		qx.installation.download();
	});
</script>