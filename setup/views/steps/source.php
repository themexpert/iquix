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
/*
steps: 
// 1. get store quix pro and quix free product id and update url
// 2. first show users to give username and license key
// 3. ajax validate and get the result for quix pro
4. if(pro available ? message to proceed to to install quix pro download)
5. if(pro not availabel ? message to proceed free installation)
6. Move to next step installation
*/
$session = JFactory::getSession();
$hasLicensepro = $session->get('quix.hasLicense', false);
$hasFree = $session->get('quix.hasFree', false);
$hasLicense = $hasLicensepro;

// we have to check for free license only for now, as this is the only version
// if($hasLicensepro && $hasFree){
// 	$hasLicense = true;
// }
?>
<script type="text/javascript">
$(document).ready(function() {

	$('[data-installation-submit]' ).bind('click', function(){
		$('[data-installation-form]').submit();
	});

	$('[data-try-again]' ).bind('click', function(){
		$('[data-source-errors]').addClass('d-none');
		$('[data-licenses]').removeClass('d-none');
	});

	
	<?php if (QX_INSTALLER == 'full') { //  or $hasLicense?>
		$('[data-installation-form]').submit();
	<?php } ?>

	<?php if (QX_INSTALLER == 'launcher') { ?>
		var loading = $('[data-checking]');
		var licenses = $('[data-licenses]');

		var form = $('[data-installation-form]');
		var submit = $('[data-installation-submit]');		
		submit.addClass('d-none');


		var validation = $('[data-validation-submit]');
		// Change the behavior of form submission
		validation.on('click', function() {
			loading.removeClass('d-none');
			licenses.addClass('d-none');


			// Validate api key
			$.ajax({
				type: 'POST',
				url: '<?php echo JURI::root();?>administrator/index.php?option=com_iquix&ajax=1&controller=license&task=verify',
				data: {'username': $('#usernameInput').val(), 'key': $('#keyInput').val()}
			}).done(function(result) {

				// d-none the loading
				loading.addClass('d-none');

				// User is not allowed to install
				if (result.state == 400 || result.state == 403) {

					// Set the error message
					$('[data-api-errors]').removeClass('d-none');
					$('[data-error-message]').html(result.message);
					return false;
				}


				// Valid licenses
				if (result.state == 200) {

					submit.removeClass('d-none');
					licenses.append(result.html);
					
					$('[data-success-message]').html(result.message);
					$('[data-success-container]').removeClass('d-none');

					// form.submit();
				}
			})
			.fail(function( jqXHR, textStatus ) {
				// Set the error message
				$('[data-api-errors]').removeClass('d-none');
				$('[data-error-message]').html("Request failed: " + textStatus);	
				// console.error( "Request failed: " + textStatus );
			})
			.always(function() {
				// console.warn( "complete" );
				// d-none the loading
				loading.addClass('d-none');
			});
		});

	<?php } ?>
});
</script>
<form action="index.php?option=com_iquix" method="post" name="installation" data-installation-form>
	
	<!-- //No license found warning -->
	<div class="alert alert-info d-none" data-source-errors data-api-errors>
		<h3 class="alert-heading">Unable to validate 🙇🏻</h3>
		<p data-error-message>
			Sorry, but the API key that you have provided does not have a valid subscription. Please try again, or if you still have technical difficulties, please contact our support team.
		</p>
		<hr>
		<a href="#" class="btn btn-primary" data-try-again>Try Again</a>
		<a href="https://www.themexpert.com" class="btn btn-link" target="_blank">Contact Support</a>
	</div>

	<!-- // License found -->
	<div class="text-center<?php echo ($hasLicense ? '' : ' d-none');?>" data-success-container>
		<p class="mt-4" style="font-size: 120px; line-height: 1">🕺</p>
		<h2 class="alert-heading">Let's get started </h2>
		<p class="text-muted ml-5 mr-5" data-success-message>
			<?php echo JText::_('A valid license has been found, awesome! Please click next to continute the installation process.'); ?>
		</p>
	</div>

	<!-- // validation form -->
	<div class="<?php echo ($hasLicense ? ' d-none' : '');?>" data-licenses>
		<div class="text-center mb-5">
			<h2>Input your credentials</h2>
		</div>
		<div class="form-group">
			<label class="control-label" for="inputName"><b>Username</b></label>
			<input class="form-control" type="text" id="usernameInput" placeholder="Input here" name="username" value="" />
			<small class="form-text text-muted">Input your <b>ThemeXpert</b> username here.</small>
		</div>
		<div class="form-group">
			<label class="control-label" for="key">Auth Key</label>
			<input class="form-control" type="text" id="keyInput" placeholder="Input here" name="key" value="" />
			<small class="form-text text-muted">Don't have any <b>Auth Key</b> ? Generate it from <a href="https://www.themexpert.com/dashboard" target="_blank">ThemeXpert Dashboard</a>.</small>
		</div>
		<a href="#" class="btn btn-primary btn-block" data-validation-submit>Validate</a>
		
	</div>

	<!-- //License checking loader -->
	<div class="installation-methods d-none" data-checking>
		<?php if (QX_INSTALLER == 'launcher') { ?>
		<div class="text-center">
			<div class="progress">
				<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" style="width: 50%"></div>
			</div>
			<h6 class="mt-1">Checking for license...</h6>
		</div>
		<input type="hidden" name="method" value="network" />
		<?php } ?>

		<?php if (QX_INSTALLER == 'full' || QX_BETA) { ?>
		<input type="hidden" name="method" value="directory" />
		<?php } ?>
	</div>

	<input type="hidden" name="option" value="com_iquix" />
	<input type="hidden" name="active" value="<?php echo $active; ?>" />
</form>
<script type="text/javascript">
jQuery(document).ready( function(){
	qx.ajaxUrl = "<?php echo JURI::root();?>administrator/index.php?option=com_iquix&ajax=1";
	// Immediately proceed with installation
	<?php if(!$hasLicense):?>
	qx.verification.getInfo();
	<?php else: ?>
	jQuery('[data-installation-submit]').removeClass('d-none');
	<?php endif; ?>
});
</script>
