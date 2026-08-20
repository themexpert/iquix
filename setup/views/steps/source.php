<?php
/**
 * @package     Quix
 * @copyright   Copyright (C) 2010 - 2026 ThemeXpert.com. All rights reserved.
 * @license     GNU/GPL, see LICENSE.php
 */
defined('_JEXEC') or die('Unauthorized Access');
?>
<form action="index.php?option=com_iquix" method="post" name="installation" data-installation-form>

    <div class="text-center" data-checking>
        <div class="progress">
            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 50%"></div>
        </div>
        <h6 class="mt-3">Checking your license...</h6>
    </div>

    <!-- Already licensed: no credentials are asked for. -->
    <div class="text-center d-none" data-licensed>
        <p class="mt-4" style="font-size: 96px; line-height: 1">🕺</p>
        <h2>You're all set</h2>
        <p class="text-muted">
            A valid Quix Pro license is already active on this site
            (<code data-licensed-key></code>). Click next to install.
        </p>
        <a href="#" class="btn btn-link btn-sm" data-use-different-key>Use a different license key</a>
    </div>

    <!-- Not licensed yet. -->
    <div class="d-none" data-licenses>
        <div class="text-center mb-4">
            <h2>Activate Quix Pro</h2>
            <p class="text-muted">
                Find your key in <a href="https://my.converslabs.com" target="_blank" rel="noopener">your account</a>.
            </p>
        </div>

        <div class="alert alert-warning d-none" data-license-error></div>

        <div class="form-group">
            <label class="control-label" for="licenseKeyInput"><b>License key</b></label>
            <input class="form-control" type="text" id="licenseKeyInput" name="license_key"
                   placeholder="Paste your license key" autocomplete="off" />
        </div>

        <button type="button" class="btn btn-primary btn-block" data-validation-submit>Activate</button>

        <p class="text-center text-muted mt-3 mb-0">
            No license? <a href="#" data-use-free>Install Quix Free instead</a>.
        </p>
    </div>

    <input type="hidden" name="option" value="com_iquix" />
    <input type="hidden" name="active" value="<?php echo (int) $active; ?>" />
</form>

<script type="text/javascript">
jQuery(function ($) {
    var checking = $('[data-checking]');
    var licensed = $('[data-licensed]');
    var licenses = $('[data-licenses]');
    var error    = $('[data-license-error]');
    var next     = $('[data-installation-submit]');

    next.addClass('d-none');

    function showLicensed(result) {
        checking.addClass('d-none');
        licenses.addClass('d-none');
        $('[data-licensed-key]').text(result.maskedKey || '');
        licensed.removeClass('d-none');
        next.removeClass('d-none');
    }

    function showPrompt(message) {
        checking.addClass('d-none');
        licensed.addClass('d-none');
        licenses.removeClass('d-none');
        next.addClass('d-none');

        if (message) {
            error.html(message).removeClass('d-none');
        } else {
            error.addClass('d-none');
        }
    }

    qx.request('license', 'status', {})
        .done(function (result) {
            if (result.licensed) {
                showLicensed(result);
            } else {
                showPrompt(result.message);
            }
        })
        .fail(function () {
            showPrompt('We could not check your license. Enter your key to continue.');
        });

    $('[data-validation-submit]').on('click', function () {
        error.addClass('d-none');
        checking.removeClass('d-none');
        licenses.addClass('d-none');

        qx.request('license', 'verify', { license_key: $('#licenseKeyInput').val() })
            .done(function (result) {
                if (result.state) {
                    showLicensed(result);
                } else {
                    showPrompt(result.message);
                }
            })
            .fail(function (jqXHR) {
                showPrompt((jqXHR.responseJSON && jqXHR.responseJSON.message) || 'The request failed.');
            });
    });

    $('[data-use-free]').on('click', function (e) {
        e.preventDefault();

        qx.request('license', 'useFree', {}).done(function () {
            $('[data-installation-form]').submit();
        });
    });

    $('[data-use-different-key]').on('click', function (e) {
        e.preventDefault();
        showPrompt('');
    });

    $('[data-installation-submit]').on('click', function () {
        $('[data-installation-form]').submit();
    });
});
</script>
