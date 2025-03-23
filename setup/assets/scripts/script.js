
var qx = {
	ajaxUrl: "index.php?option=com_iquix&ajax=1",
	installation: {
		path: null,
		ajaxCall: function(task, properties, callback) {

			var prop = $.extend({
				"path": qx.installation.path
			}, properties);

			$.ajax({
				type: "POST",
				url: qx.ajaxUrl + "&controller=installation&task=" + task ,
				data: prop
			}).done(function(result) {
				callback.apply(this, [result]);
			});
		},

		showRetry: function(step) {
			$('[data-installation-retry]').data('retry-step', step).show();
			$('[data-installation-loading]').hide();
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

				qx.installation.backupDatabase();
			});
		},

		backupDatabase: function() {
			qx.installation.setActive('data-progress-backup');

			qx.installation.ajaxCall('backupDatabase' , {} , function(result) {
				// Set the progress
				qx.installation.update( 'data-progress-backup' , result , '40%');

				if (!result.state) {
					qx.installation.showRetry('backupDatabase');
					return false;
				}

				qx.installation.installComponent();
			});
		},

		installComponent : function() {
			// Install the admin stuffs
			qx.installation.setActive( 'data-progress-component' );

			qx.installation.ajaxCall( 'installComponent' , {} , function( result )
			{
				// Set the progress
				qx.installation.update( 'data-progress-component' , result , '50%');

				if( !result.state )
				{
					qx.installation.showRetry( 'installComponent' );
					return false;
				}

				qx.installation.installLibrary();
			});
		},

		installLibrary : function() {
			// Install the admin stuffs
			qx.installation.setActive( 'data-progress-library' );

			qx.installation.ajaxCall( 'installLibrary' , {} , function( result )
			{
				// Set the progress
				qx.installation.update( 'data-progress-library' , result , '60%');

				if( !result.state )
				{
					qx.installation.showRetry( 'installLibrary' );
					return false;
				}

				qx.installation.installModules();
			});
		},


		installModules: function() {
			// Install the admin stuffs
			qx.installation.setActive('data-progress-modules');

			qx.installation.ajaxCall('installModules', {}, function(result) {
				// Set the progress
				qx.installation.update('data-progress-modules', result , '70%');

				if (!result.state) {
					qx.installation.showRetry('installModules');
					return false;
				}

				qx.installation.installTemplates();
			});
		},

		installTemplates: function()
		{
			// Install the admin stuffs
			qx.installation.setActive( 'data-progress-plugins' );

			qx.installation.ajaxCall( 'installTemplates' , {} , function( result )
			{
				// Set the progress
				qx.installation.update( 'data-progress-templates' , result , '80%');

				if( !result.state )
				{
					qx.installation.showRetry( 'installTemplates' );
					return false;
				}
				
				qx.installation.installPlugins();
			});
		},
		installPlugins: function()
		{
			// Install the admin stuffs
			qx.installation.setActive( 'data-progress-templates' );

			qx.installation.ajaxCall( 'installPlugins' , {} , function( result )
			{
				// Set the progress
				qx.installation.update( 'data-progress-plugins' , result , '90%');

				if( !result.state )
				{
					qx.installation.showRetry( 'installPlugins' );
					return false;
				}

				qx.installation.syncdb();
			});
		},
		syncdb: function()
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

				$( '[data-installation-completed]' ).show();

				$( '[data-installation-loading]' ).hide();
				$( '[data-installation-submit]' ).show();

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
		
		finalizeMaintenance: function()
		{
			var frame = $('[data-progress-finalizing]');
			frame.addClass('active').removeClass('pending');

			$.ajax({
				type: 'POST',
				url: qx.ajaxUrl + '&controller=maintenance&task=cleanInstallation&active=1'
			})
			.done(function(result){
				var stateMessage	= result.state ? 'Success' : 'Failed';

				var item = $('<li>');
				item.addClass('text-success').html(result.message);
				$('[data-progress-finalizing] .notes ul').append(item);
				$('[data-progress-finalizing] .progress-state').html( stateMessage );


				qx.maintenance.remoeUpdateRecord();
			});
		},

		remoeUpdateRecord: function()
		{
			var frame = $('[data-progress-updaterecord]');

			frame.addClass('active').removeClass('pending');

			$.ajax({
				type: 'POST',
				url: qx.ajaxUrl + '&controller=maintenance&task=removeUpdateRecord&active=1'
			})
			.done(function(result){
				var stateMessage	= result.state ? 'Success' : 'Failed';
				var item = $('<li>');
				item.addClass('text-success').html(result.message);

				$('[data-progress-updaterecord] .notes ul').append(item);
				$('[data-progress-updaterecord] .progress-state').html( stateMessage );


				qx.maintenance.updateAssets();
			});
		},
		
		updateAssets: function()
		{
			var frame = $('[data-progress-updateassets]');

			frame.addClass('active').removeClass('pending');

			$.ajax({
				type: 'POST',
				url: qx.ajaxUrl + '&controller=maintenance&task=updateAssets&active=1'
			})
			.done(function(result){
				var stateMessage	= result.state ? 'Success' : 'Failed';
				var item = $('<li>');
				item.addClass('text-success').html(result.message);

				$('[data-progress-updateassets] .notes ul').append(item);
				$('[data-progress-updateassets] .progress-state').html( stateMessage );


				qx.maintenance.complete();
			});
		},

		complete: function() {
			$('[data-installation-loading]').hide();
			$('[data-installation-submit]').show();

			$('[data-installation-submit]').on('click', function() {
				$('[data-installation-form]').submit();
			});
		}
	},
	verification: {
		getInfo: function() {
			$('[data-licenses]').addClass('hide');
			$('[data-checking]').removeClass('hide');
			qx.installation.ajaxCall('getAuthInfo', {}, function(result){
				// console.log(result.length);
				for (var i = 0; i < result.length; i++) {
					// console.log(result[i].name);
					if(result[i].name == 'username')
					{
						$('#usernameInput').val(result[i].params);
					}
					else if(result[i].name == 'key')
					{
						$('#keyInput').val(result[i].params);
					}
				}

				$('[data-checking]').addClass('hide');
				$('[data-licenses]').removeClass('hide');

			});
		},
	},
	core: {
		checkUpdate: function() {
			jQuery('[data-update-checking]').removeClass('hide');
			jQuery('[data-installation-form]').addClass('hide');
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
					console.warn(result.state);
					// already uptodate for this session
					$('[data-update-checking]').html('<div class="alert alert-danger">' + result.message + '<.div>');
					return;					
				}

				$('[data-update-checking]').addClass('hide');
				$('[data-installation-form]').removeClass('hide');

			});
		},
		ajaxCall: function(task, properties, callback) {

			var prop = $.extend({
				"path": qx.installation.path
			}, properties);

			$.ajax({
				type: "POST",
				url: qx.ajaxUrl + "&controller=update&task=" + task ,
				data: prop
			}).done(function(result) {
				callback.apply(this, [result]);
			});
		},
	}
}
