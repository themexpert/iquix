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

// Function to print the icons
function printIcon($iconType)
{
// Define SVG icons as variables
	$checkmarkIcon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M9 16.2l-3.5-3.5L4 14l5 5 12-12-1.5-1.5z"/></svg>';
	$crossIcon     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path fill="none" d="M0 0h24v24H0z"/><path d="M18.3 5.71L12 12l6.3 6.29-1.42 1.42L12 14.41l-6.29 6.3-1.42-1.42L10.59 12 4.29 5.71 5.71 4.29 12 10.59l6.29-6.3z"/></svg>';

	if ($iconType === 'checkmark')
	{
		echo $checkmarkIcon;
	}
    elseif ($iconType === 'cross')
	{
		echo $crossIcon;
	}
}

$gd = function_exists('gd_info');
$curl = is_callable('curl_init');
$ctype = extension_loaded('ctype');
$fileinfo = extension_loaded('fileinfo');
//###########################################
//# MySQL info
//###########################################
$db = \Joomla\CMS\Factory::getDbo();
$mysqlVersion = $db->getVersion();
// first 3 characters of the version
$mysqlVersion = substr($mysqlVersion, 0, 5);

//###########################################
//# PHP info
//###########################################
$phpVersion = phpversion();

$memoryLimit = ini_get('memory_limit');
// convert the value to MB without unit
if (stripos($memoryLimit, 'M') !== false) {
    list($memoryLimit) = explode('M', $memoryLimit);
} elseif (stripos($memoryLimit, 'K') !== false) {
    list($memoryLimit) = explode('K', $memoryLimit);
    $memoryLimit = $memoryLimit / 1024;
}

$postSize = (int) ini_get('post_max_size');
$max_execution = ini_get('max_execution_time');
$allow_url_fopen = ini_get('allow_url_fopen');

$hasErrors = false;

if (stripos($memoryLimit, 'G') !== false) {
    list($memoryLimit) = explode('G', $memoryLimit);
    $memoryLimit = $memoryLimit * 1024;
}

if (!$gd || !$curl || !$ctype || !$fileinfo || !$allow_url_fopen || $postSize < 5 || $max_execution < 30) {
    $hasErrors = true;
}

//#########################################
//# Paths
//#########################################
$files = [];

$files['admin'] = new stdClass();
$files['admin']->path = JPATH_ROOT . '/administrator/components';

$files['admin_modules'] = new stdClass();
$files['admin_modules']->path = JPATH_ROOT . '/administrator/modules';

$files['site'] = new stdClass();
$files['site']->path = JPATH_ROOT . '/components';

$files['tmp'] = new stdClass();
$files['tmp']->path = JPATH_ROOT . '/tmp';

$files['media'] = new stdClass();
$files['media']->path = JPATH_ROOT . '/media';

$files['libraries'] = new stdClass();
$files['libraries']->path = JPATH_ROOT . '/libraries';

$files['system'] = new stdClass();
$files['system']->path = JPATH_ROOT . '/plugins/system';

$files['content'] = new stdClass();
$files['content']->path = JPATH_ROOT . '/plugins/content';

$files['finder'] = new stdClass();
$files['finder']->path = JPATH_ROOT . '/plugins/finder';

$files['quickicon'] = new stdClass();
$files['quickicon']->path = JPATH_ROOT . '/plugins/quickicon';

$files['editors_xtd'] = new stdClass();
$files['editors_xtd']->path = JPATH_ROOT . '/plugins/editors_xtd';

$files['auth'] = new stdClass();
$files['auth']->path = JPATH_ROOT . '/modules';

$files['cache'] = new stdClass();
$files['cache']->path = JPATH_ROOT . '/cache';

//#########################################
//# Determine states
//#########################################
// $hasErrors	= false;

foreach ($files as $file) {
    // The only proper way to test this is to not use is_writable
    $contents = '<body></body>';
    $state = \Joomla\Filesystem\File::write($file->path . '/tmp.html', $contents);

    // Initialize this to false by default
    $file->writable = false;

    if ($state) {
        \Joomla\Filesystem\File::delete($file->path . '/tmp.html');

        $file->writable = true;
    }

    if (!$file->writable) {
        $hasErrors = true;
    }
}
?>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		<?php if (!empty($hasErrors)): ?>
		console.log('<?php echo $hasErrors; ?>');
		<?php endif; ?>
		$('[data-installation-submit]').bind('click', function() {
			$('[data-installation-form]').submit();
		});

		<?php if ($hasErrors) { ?>
		$('[data-installation-submit]').hide();
		$('[data-installation-refresh]').removeClass('hide');

		// now we rebind the click.
		$('[data-installation-refresh]').on('click', function() {
			window.location.reload();
		});
		<?php } ?>
	});
</script>
<!-- //loader -->
<div class="installation-methods d-none" data-update-checking>
	<div class="text-center">
		<b class="ui loader" style="width: 48px; height: 48px;"></b>&nbsp;
		<b>Checking update script...</b>
	</div>
</div>

<div class="text-center mb-3">
	<h2>Quix Auto Installer</h2>
    <p class="text-muted mt-3 text-center">
        Quix Installer - v#<strong><?php echo IQX_VERSION; ?></strong>
    </p>
</div>

<h5>Requirements</h5>
<form name="installation" method="post" data-installation-form>
	<?php if (!$hasErrors) { ?>
	<p class="alert alert-success">👍 Awesome! The minimum requirements are met. You may proceed with the installation
		process now.</p>
	<?php } ?>

	<p class="alert alert-danger <?php echo $hasErrors ? '' : 'd-none';?>"
		data-requirements-error>
		Some of the requirements below are not met. Please ensure that all of the requirements below are met.
	</p>

	<div class="card requirements-table" data-system-requirements>
		<div class="card-header">
			<ul class="nav nav-tabs card-header-tabs">
				<li class="nav-item">
					<a class="nav-link active" data-toggle="tab" href="#settings">Settings</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" data-toggle="tab" href="#directories">Directories</a>
				</li>
			</ul>
		</div>
		<div class="card-body">
			<div class="tab-content" id="nav-tabContent">
				<div class="tab-pane fade show active" id="settings" role="tabpanel" aria-labelledby="nav-home-tab">
					<table class="table table-striped mt-20 stats">
						<thead>
							<tr>
								<td width="40%">
									<?php echo \Joomla\CMS\Language\Text::_('Settings');?>
								</td>
								<td class="text-center" width="30%">
									<?php echo \Joomla\CMS\Language\Text::_('Recommended');?>
								</td>
								<td class="text-center" width="30%">
									<?php echo \Joomla\CMS\Language\Text::_('Current');?>
								</td>
							</tr>
						</thead>

						<tbody>
							<tr
								class="<?php echo version_compare($phpVersion, '5.6.0') == -1 ? 'text-error' : '';?>">
								<td>
									<div class="clearfix">
										<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
										Version
										<i class="icon-help hasTooltip"
											title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_PHP_VERSION_TIPS');?>"></i>

										<?php if (false == true) { //(version_compare($phpVersion, '5.6.0') == -1) {?>
										<a href="https://themexpert.com/docs/quix/welcome/getting-started"
											class="pull-right btn btn-es-danger btn-mini"><?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_FIX_THIS');?></a>
										<?php } ?>
									</div>
								</td>
								<td class="text-center text-success">
									7.2.0
								</td>
								<td
									class="text-center text-<?php echo version_compare($phpVersion, '5.6.0') == -1 ? 'error' : 'success';?>">
									<?php echo $phpVersion;?>
								</td>
							</tr>
							<tr
								class="<?php echo !$gd ? 'text-error' : '';?>">
								<td>
									<div class="clearfix">
										<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
										GD Library
										<i class="icon-help hasTooltip"
											title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_PHP_GD_TIPS');?>"></i>

										<?php if (false == true) { //( !$gd ){?>
										<a href="https://themexpert.com/docs/quix/setup/gd-library" target="_blank"
											class="pull-right btn btn-es-danger btn-mini"><?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_FIX_THIS');?></a>
										<?php } ?>
									</div>
								</td>
								<td class="text-center text-success">
                                    <?php printIcon('checkmark'); ?>
								</td>
								<?php if ($gd) { ?>
								<td class="text-center text-success">
									<?php printIcon('checkmark'); ?>
                                </td>
								<?php } else { ?>
								<td class="text-center text-error">
									<?php echo printIcon('cross'); ?>
                                </td>
								<?php } ?>
							</tr>

							<tr
								class="<?php echo !$curl ? 'text-error' : '';?>">
								<td>
									<div class="clearfix">
										<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
										CURL Library
										<i class="icon-help hasTooltip"
											title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_PHP_CURL_TIPS');?>"></i>
										<?php if (false == true) { //( !$curl ){?>
										<a href="https://themexpert.com/docs/quix/setup/curl-library" target="_blank"
											class="pull-right btn btn-es-danger btn-mini"><?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_FIX_THIS');?></a>
										<?php } ?>
									</div>
								</td>
								<td class="text-center text-success">
									<?php printIcon('checkmark'); ?>
								</td>
								<?php if ($curl) { ?>
								<td class="text-center text-success">
									<?php printIcon('checkmark'); ?>
								</td>
								<?php } else { ?>
								<td class="text-center text-error">
									<?php printIcon('cross'); ?>
								</td>
								<?php } ?>
							</tr>
							<tr
								class="<?php echo $ctype ? '' : 'text-error';?>">
								<td>
									<div class="clearfix">
										<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
										CType Function
										<i class="icon-help hasTooltip"
											title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_PHP_CTYPE_TIPS');?>"></i>

										<?php if (false == true) { //( !$ctype ){?>
										<a href="https://themexpert.com/docs/quix/setup/magic-quotes" target="_blank"
											class="pull-right btn btn-es-danger btn-mini"><?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_FIX_THIS');?></a>
										<?php } ?>
									</div>
								</td>
								<td class="text-center text-success">
									<?php printIcon('checkmark'); ?>
								</td>
								<td
									class="text-center text-<?php echo $ctype ? 'success' : 'error';?>">
									<?php if (!$ctype) { ?>
									<?php printIcon('cross'); ?>
									<?php } else { ?>
									<?php printIcon('checkmark'); ?>
									<?php } ?>
								</td>
							</tr>

							<tr
								class="<?php echo !$fileinfo ? 'text-error' : '';?>">
								<td>
									<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
									Fileinfo Support
									<i class="icon-help hasTooltip"
										title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_FILEINFO_TIPS');?>"></i>
								</td>
								<td class="text-center text-success">
									<?php printIcon('checkmark'); ?>
								</td>
								<td
									class="text-center text-<?php echo !$fileinfo ? 'error' : 'success'; ?>">
									<?php if (!$fileinfo) { ?>
									<?php printIcon('cross'); ?>
									<?php } else { ?>
									<?php printIcon('checkmark'); ?>
									<?php } ?>
								</td>
							</tr>

							<tr
								class="<?php echo !$allow_url_fopen ? 'text-error' : '';?>">
								<td>
									<div class="clearfix">
										<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
										allow_url_fopen
										<i class="icon-help hasTooltip"
											title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_ALLOW_URL_FOPEN_TIPS');?>"></i>
									</div>
								</td>
								<td class="text-center text-success">
									<?php printIcon('checkmark'); ?>
								</td>
								<td
									class="text-center text-<?php echo !$allow_url_fopen ? 'error' : 'success';?>">
									<?php if (!$allow_url_fopen) { ?>
									<?php printIcon('cross'); ?>
									<?php } else { ?>
									<?php printIcon('checkmark'); ?>
									<?php } ?>
								</td>
							</tr>
							<tr
								class="<?php echo $memoryLimit < 64 ? 'text-error' : '';?>">
								<td>
									<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
									memory_limit
									<i class="icon-help hasTooltip"
										title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_PHP_MEMORYLIMIT_TIPS');?>"></i>
								</td>
								<td class="text-center text-success">
									128 <?php echo \Joomla\CMS\Language\Text::_('M');?>
								</td>
								<td
									class="text-center text-<?php echo $memoryLimit < 64 ? 'error' : 'success';?>">
									<?php echo $memoryLimit; ?>
								</td>
							</tr>
							<tr
								class="<?php echo $postSize < 16 ? 'text-error' : '';?>">
								<td>
									<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
									post_max_size
									<i class="icon-help hasTooltip"
										title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_PHP_POST_MAX_SIZE_TIPS');?>"></i>
								</td>
								<td class="text-center text-success">
									24 <?php echo \Joomla\CMS\Language\Text::_('M');?>
								</td>
								<td
									class="text-center text-<?php echo $postSize < 5 ? 'error' : 'success';?>">
									<?php echo $postSize; ?> M
								</td>
							</tr>
							<tr
								class="<?php echo $max_execution < 60 ? 'text-error' : '';?>">
								<td>
									<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('PHP');?></span>
									max_execution_time
									<i class="icon-help hasTooltip"
										title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_PHP_MAX_EXECUTION_TIPS');?>"></i>
								</td>
								<td class="text-center text-success">
									120
								</td>
								<td
									class="text-center text-<?php echo $max_execution < 60 ? 'error' : 'success';?>">
									<?php echo $max_execution; ?>
								</td>
							</tr>
							<tr
								class="<?php echo !$mysqlVersion || version_compare($mysqlVersion, '5.0.4') == -1 ? 'text-error' : '';?>">
								<td>
									<span class="label label-info"><?php echo \Joomla\CMS\Language\Text::_('MySQL');?></span>
									Version
									<i class="icon-help hasTooltip"
										title="<?php echo \Joomla\CMS\Language\Text::_('COM_QUIX_INSTALLATION_MYSQL_VERSION_TIPS');?>"></i>
								</td>
								<td class="text-center text-success">
									5.0.4
								</td>
								<td
									class="text-center text-<?php echo !$mysqlVersion || version_compare($mysqlVersion, '5.0.4') == -1 ? 'error' : 'success'; ?>">
									<?php echo !$mysqlVersion ? 'N/A' : $mysqlVersion;?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="tab-pane fade" id="directories" role="tabpanel" aria-labelledby="nav-profile-tab">
					<table class="table table-striped mt-20 stats">
						<thead>
							<tr>
								<td width="75%">
									<?php echo \Joomla\CMS\Language\Text::_('Directory'); ?>
								</td>
								<td class="text-center" width="25%">
									<?php echo \Joomla\CMS\Language\Text::_('State'); ?>
								</td>
							</tr>
						</thead>

						<tbody>
							<?php foreach ($files as $file) { ?>
							<tr
								class="<?php echo !$file->writable ? 'text-error' : '';?>">
								<td>
									<?php echo $file->path;?>
								</td>

								<?php if ($file->writable) { ?>
								<td class="text-center text-success">
									<?php printIcon('checkmark'); ?>
								</td>
								<?php } else { ?>
								<td class="text-center text-error">
									<?php printIcon('cross'); ?>&nbsp; <?php echo \Joomla\CMS\Language\Text::_('Unwritable');?>
								</td>
								<?php } ?>
							</tr>
							<?php } ?>

						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>

	<p class="alert alert-info small text-muted mt-3">
		Need help about requirements? You might want check <a
			href="https://www.themexpert.com/docs/quix-builder/basics/requirements" target="_blank">this link</a> or our
		<a href="https://www.themexpert.com/docs" target="_blank">Docs Home</a>.
	</p>


	<input type="hidden" name="option" value="com_iquix" />
	<input type="hidden" name="active"
		value="<?php echo $active; ?>" />
</form>

<?php
$session = \Joomla\CMS\Factory::getSession();
$checkUpdate = $session->get('quix.scriptupdate', false);
if (!$checkUpdate): ?>
<script type="text/javascript">
	jQuery(document).ready(function() {
		// jQuery('[data-installation-form]').addClass('hide');
		// Immediately proceed with installation
		qx.core.checkUpdate();
	});
</script>
<?php endif;
