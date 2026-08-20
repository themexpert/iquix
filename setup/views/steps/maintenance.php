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
<form name="installation" data-installation-form>

	<div class="text-center mb-5">
		<h2>Maintenance</h2>
		<p class="text-muted">We will need to update some scripts for installation.</p>
	</div>


	<div class="card" data-sync-progress>
		<ul class="list-group list-group-flush install-logs list-reset" data-progress-logs>
			<li class="list-group-item pending" data-progress-finalizing>
				<b class="split__title">Finalizing installation</b>
				<span class="progress-state float-right">Executing</span>
				<div class="notes">
					<ul data-progress-syncuser-items></ul>
				</div>
			</li>
			<li class="list-group-item pending" data-progress-updaterecord>
				<b class="split__title">Remove update record</b>
				<span class="progress-state float-right">Executing</span>
				<div class="notes">
					<ul data-progress-syncuser-items></ul>
				</div>
			</li>
			<li class="list-group-item pending" data-progress-updateassets>
				<b class="split__title">Update assets</b>
				<span class="progress-state float-right">Executing</span>
				<div class="notes">
					<ul data-progress-syncuser-items></ul>
				</div>
			</li>
		</ul>
	</div>

	<input type="hidden" name="option" value="com_iquix" />
	<input type="hidden" name="active" value="<?php echo $active; ?>" />
</form>

<script type="text/javascript">
$(document).ready(function(){
	jQuery('[data-installation-submit]').addClass('d-none');
	qx.maintenance.init();
});
</script>