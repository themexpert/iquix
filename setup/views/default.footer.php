<?php
/**
* @package		Quix
* @copyright	Copyright (C) 2010 - 2023 ThemeXpert.com. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Quix is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
defined('_JEXEC') or die('Unauthorized Access');
?>
<?php if ($active != 'complete') { ?>
<script type="text/javascript">
$(document).ready( function(){

	var previous = $('[data-installation-nav-prev]'),
		active = $('[data-installation-form-nav-active]'),
		nav = $('[data-installation-form-nav]'),
		retry = $('[data-installation-retry]'),
		cancel = $('[data-installation-nav-cancel]'),
		loading = $('[data-installation-loading]');

	previous.on('click', function() {
		active.val(<?php echo $active;?> - 2);

		nav.submit();
	});

	cancel.on('click', function() {
		window.location = '<?php echo \Joomla\CMS\Uri\Uri::base();?>index.php';
	});

	retry.on('click', function() {
		var step = $(this).data('retry-step');

		// d-none is the only hide class the loaded stylesheets define, and it
		// carries !important -- so it is also the only one a jQuery .show()
		// could not undo. Every show/hide in this wizard toggles d-none.
		$(this).addClass('d-none');

		loading.removeClass('d-none');

		if (typeof qx.installation[step] !== 'function') {
			console.error('Unknown retry step: ' + step);
			return;
		}

		qx.installation[step]();
	});
});
</script>

<form action="index.php?option=com_iquix" method="post" data-installation-form-nav class="d-none">
	<input type="hidden" name="active" value="<?php echo $active ?>" data-installation-form-nav-active />
	<input type="hidden" name="option" value="com_iquix" />
	<?php //if ($reinstall) { ?>
	<!-- <input type="hidden" name="reinstall" value="1" /> -->
	<?php //} ?>

	<?php //if ($update) { ?>
	<!-- <input type="hidden" name="update" value="1" /> -->
	<?php //} ?>
</form>

	<div class="d-flex justify-content-between">
		<a href="javascript:void(0);" class="btn btn-link" <?php echo $active > 1 ? ' data-installation-nav-prev' : ' data-installation-nav-cancel';?>>
			<b>
				<span>
					<i class="qx-icon icon-arrow-left-2 mr-2"></i>
				</span>
				<span>
					<?php if ($active > 1) { ?>
						<?php echo \Joomla\CMS\Language\Text::_('Previous'); ?>
					<?php } else { ?>
						<?php echo \Joomla\CMS\Language\Text::_('Exit Installation'); ?>
					<?php } ?>
				</span>
			</b>
		</a>

		<div>
			<!-- Debug log download button -->
			<a href="javascript:void(0);" id="debug" data-installation-debug class="btn btn-sm">
				<b>
					<span>
						<i class="qx-icon icon-download mr-1"></i>
					</span>
					<span><?php echo \Joomla\CMS\Language\Text::_('Download Debug Log'); ?></span>
				</b>
			</a>
			<a href="javascript:void(0);" class="btn btn-primary" data-installation-submit>
			<b>
				<span><?php echo \Joomla\CMS\Language\Text::_('Next'); ?></span>
				<span>
					<i class="qx-icon icon-arrow-right-2 ml-2"></i>
				</span>
			</b>
		</a>

		</div>

		<a href="javascript:void(0);" class="col-cell loading d-none disabled" data-installation-loading>
			<b>
				<span><?php echo \Joomla\CMS\Language\Text::_('Loading'); ?></span>
				<span>
					<b class="ui loader"></b>
				</span>
			</b>
		</a>

		<a href="javascript:void(0);" class="col-cell primary d-none" data-installation-refresh>
			<b>
				<span><?php echo \Joomla\CMS\Language\Text::_('Retry'); ?></span>
				<span>
					<i class="qx-icon icon-arrow-right-2 ml-2"></i>
				</span>
			</b>
		</a>

		<a href="javascript:void(0);" class="col-cell primary d-none" data-installation-retry>
			<b>
				<span><?php echo \Joomla\CMS\Language\Text::_('Retry'); ?></span>
				<span>
					<i class="qx-icon icon-arrow-right-2 ml-2"></i>
				</span>
			</b>
		</a>
	</div>
<?php } ?>

<?php if ($active == 'complete') { ?>
	<div class="d-flex justify-content-between align-items-center">
		<!-- Debug log download button shown on completion page too -->
		<a href="javascript:void(0);" id="debug" data-installation-debug class="btn btn-sm">
			<b>
				<span>
					<i class="qx-icon icon-download mr-1"></i>
				</span>
				<span><?php echo \Joomla\CMS\Language\Text::_('Download Debug Log'); ?></span>
			</b>
		</a>

		<a class="btn btn-primary" href="<?php echo \Joomla\CMS\Uri\Uri::root();?>administrator/index.php?option=com_quix">
			<b><span><?php echo \Joomla\CMS\Language\Text::_('Start Building 👋🏻');?></span></b>
		</a>
	</div>
<?php } ?>
