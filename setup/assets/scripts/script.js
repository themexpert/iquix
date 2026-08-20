var qx = {
	get ajaxUrl() {
		return window.iquix.ajaxUrl + '&ajax=1';
	},

	request: function (controller, task, properties) {
		var data = Object.assign({ path: qx.installation.path }, properties || {});
		data[window.iquix.token] = 1;

		return $.ajax({
			type: 'POST',
			url: qx.ajaxUrl + '&controller=' + controller + '&task=' + task,
			data: data
		});
	},

	// Shared shape for a failed jqXHR, so every .fail() handler across the
	// app reports the same way: server message when present, else a generic
	// HTTP-status fallback.
	requestError: function (jqXHR) {
		return {
			state: false,
			message: (jqXHR.responseJSON && jqXHR.responseJSON.message) ||
				'The request failed (HTTP ' + jqXHR.status + ').'
		};
	},

	installation: {
		path: null,

		ajaxCall: function (task, properties, callback) {
			qx.request('installation', task, properties)
				.done(function (result) { callback(result); })
				.fail(function (jqXHR) { callback(qx.requestError(jqXHR)); });
		},

		// Show/hide throughout the wizard is a single class contract:
		// bootstrap.min.css defines .d-none{display:none!important} and
		// nothing else. Neither stylesheet defines .hide or .hidden, and the
		// inline display jQuery .show() writes loses to that !important --
		// which is why the Retry button could never actually appear.
		showRetry: function(step) {
			$('[data-installation-retry]').data('retry-step', step).removeClass('d-none');
			$('[data-installation-loading]').addClass('d-none');
		},

		download: function() {
			qx.installation.ajaxCall('download', {}, function(result){
				// Set the progress
				qx.installation.update( 'data-progress-download' , result , '30%');

				if (!result.state) {
					qx.installation.showRetry('download');
					return false;
				}

				// Set the installation path
				qx.installation.path = result.path;
				qx.installation.cleanCache();
			});
		},

		cleanCache: function() {
			// Install the SQL stuffs
			qx.installation.setActive( 'data-progress-cleancache' );
			qx.installation.ajaxCall('cleanCache', {}, function(result) {

				qx.installation.update('data-progress-cleancache', result, '35%');
				if (!result.state) {
					qx.installation.showRetry('cleanCache');
					return false;
				}

				// 	Move to the next step
				qx.installation.installExtensions();
			});
		},

		installExtensions: function () {
			qx.installation.setActive('data-progress-extensions');

			qx.installation.ajaxCall('installExtensions', {}, function (result) {
				qx.installation.update('data-progress-extensions', result, '80%');

				if (!result.state) {
					qx.installation.showRetry('installExtensions');
					return false;
				}

				qx.installation.syncDb();
			});
		},

		syncDb: function()
		{
			// Install the admin stuffs
			qx.installation.setActive( 'data-progress-syncdb' );

			qx.installation.ajaxCall( 'syncDb' , {} , function( result )
			{
				// Set the progress
				qx.installation.update( 'data-progress-syncdb' , result , '95%');

				if( !result.state )
				{
					qx.installation.showRetry( 'syncDb' );
					return false;
				}

				qx.installation.postInstall();
			});
		},
		postInstall : function() {
			// Install the admin stuffs
			qx.installation.setActive( 'data-progress-postinstall' );

			qx.installation.ajaxCall( 'installPost' , {} , function( result )
			{
				// Set the progress
				qx.installation.update( 'data-progress-postinstall' , result , '100%');

				if( !result.state )
				{
					qx.installation.showRetry( 'postInstall' );
					return false;
				}

				$( '[data-installation-completed]' ).removeClass( 'd-none' );

				$( '[data-installation-loading]' ).addClass( 'd-none' );
				$( '[data-installation-submit]' ).removeClass( 'd-none' );

				$( '[data-installation-submit]' ).bind( 'click' , function(){
					$( '[data-installation-form]' ).submit();
				});

			});
		},

		update : function( element , obj , progress )
		{
			var className 		= obj.state ? ' text-success' : ' text-error',
				stateMessage	= obj.state ? 'Success' : 'Failed';

			// Update the state
			$( '[' + element + ']' )
			.find( '.progress-state' )
			.html( stateMessage )
			.removeClass( 'text-info' )
			.addClass( className );

			// Update the message
			$( '[' + element + ']' )
			.find( '.notes' )
			.html( obj.message )
			.removeClass( 'text-info' )
			.addClass( className );

			// Update the progress
			qx.installation.updateProgress( progress );
		},

		updateProgress	: function( percentage )
		{
			$( '[data-progress-bar]' ).css( 'width' , percentage );
			$( '[data-progress-bar-result]' ).html( percentage );
		},

		setActive 	: function( item )
		{
			$( '[data-progress-active-message]' ).html( $( '[' + item + ']' ).find( '.split__title' ).html() + ' ...' );
			$( '[' + item + ']' ).removeClass( 'pending' ).addClass( 'active' );
		}
	},
	maintenance :
	{
		init: function()
		{
			// Initializes the installation process.
			qx.maintenance.finalizeMaintenance();
		},

		// Renders a maintenance step's outcome — success or failure alike —
		// into its own `.notes` list and progress-state badge. Returns
		// whether the step succeeded, so callers know whether it is safe to
		// chain on to the next step.
		report: function (frame, result)
		{
			var className		= result.state ? 'text-success' : 'text-error',
				stateMessage	= result.state ? 'Success' : 'Failed';

			var item = $('<li>');
			item.addClass(className).html(result.message);

			frame.find('.notes ul').append(item);
			frame.find('.progress-state').html(stateMessage);

			return result.state;
		},

		finalizeMaintenance: function()
		{
			var frame = $('[data-progress-finalizing]');
			frame.addClass('active').removeClass('pending');

			qx.request('maintenance', 'cleanInstallation', {})
			.done(function(result){
				if (qx.maintenance.report(frame, result)) {
					qx.maintenance.remoeUpdateRecord();
				}
			})
			.fail(function(jqXHR){
				qx.maintenance.report(frame, qx.requestError(jqXHR));
			});
		},

		remoeUpdateRecord: function()
		{
			var frame = $('[data-progress-updaterecord]');

			frame.addClass('active').removeClass('pending');

			qx.request('maintenance', 'removeUpdateRecord', {})
			.done(function(result){
				if (qx.maintenance.report(frame, result)) {
					qx.maintenance.updateAssets();
				}
			})
			.fail(function(jqXHR){
				qx.maintenance.report(frame, qx.requestError(jqXHR));
			});
		},

		updateAssets: function()
		{
			var frame = $('[data-progress-updateassets]');

			frame.addClass('active').removeClass('pending');

			qx.request('maintenance', 'updateAssets', {})
			.done(function(result){
				if (qx.maintenance.report(frame, result)) {
					qx.maintenance.complete();
				}
			})
			.fail(function(jqXHR){
				qx.maintenance.report(frame, qx.requestError(jqXHR));
			});
		},

		complete: function() {
			$('[data-installation-loading]').addClass('d-none');
			$('[data-installation-submit]').removeClass('d-none');

			$('[data-installation-submit]').on('click', function() {
				$('[data-installation-form]').submit();
			});
		}
	},
	core: {
		checkUpdate: function() {
			jQuery('[data-update-checking]').removeClass('d-none');
			jQuery('[data-installation-form]').addClass('d-none');
			qx.core.ajaxCall('updateScript', {}, function(result){

				if(result.state && result.stateMessage == 302)
				{
					// already uptodate for this session
					$('[data-update-checking]').html(result.message);
					
					location.reload();
					// reload this page
					return;
				}
				else if(result.state)
				{
					// already uptodate for this session
					$('[data-update-checking]').html(result.message);
				}
				else if(!result.state)
				{
					console.warn(result.message);

					// Leave the notice on screen, but put the form back:
					// failing to check for an iQuix update is not a reason to
					// strand the user on a page with nothing to click.
					$('[data-update-checking]').html('<div class="alert alert-danger">' + result.message + '</div>');
					$('[data-installation-form]').removeClass('d-none');
					return;
				}

				$('[data-update-checking]').addClass('d-none');
				$('[data-installation-form]').removeClass('d-none');

			});
		},
		ajaxCall: function (task, properties, callback) {
			qx.request('update', task, properties)
				.done(function (result) { callback(result); })
				.fail(function (jqXHR) { callback(qx.requestError(jqXHR)); });
		},
	}
}

/**
 * Download debug log functionality
 */
qx.debug = {
	/**
	 * Initialize debug log download functionality
	 * @returns {void}
	 */
	init: function() {
		$(document).on('click', '#debug[data-installation-debug]', function(e) {
			e.preventDefault();
			qx.debug.downloadLog();
		});
	},

	/**
	 * Download the debug log file directly
	 * @returns {void}
	 */
	downloadLog: function () {
		var form = $('<form>', {
			method: 'POST',
			action: qx.ajaxUrl + '&controller=license&task=downloadDebugLog'
		});

		form.append($('<input>', { type: 'hidden', name: window.iquix.token, value: 1 }));
		form.appendTo('body').submit().remove();
	}
}

// Initialize debug functionality when document is ready
$(document).ready(function() {
	// Initialize debug log download
	qx.debug.init();
});
