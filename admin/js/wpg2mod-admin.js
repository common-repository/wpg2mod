/** ------------------------------------------------------------------------ *\
 *	Main JS file for the WPG2Mod admin backend.
 *
 *		+ initiates AJAX various calls (WPG import, settings, ...)
 *		+ deals with WP settings via AJAX 
 *		+ show/hide WP admin notifications
 *		
 * 	@package    	WPG2Mod
 * 	@subpackage 	WPG2Mod/admin/js
 * 	@license 		GPL-3
 * 	@version		1.0.0
 * 	@author			edobees
 *
 *	Date: 16. Feb. 2019 
\** ------------------------------------------------------------------------ */	
'use strict';

// jQuery Wrapper
(function( $ ) {
	
	// on document ready ...
	$(function() {
		
		// attach click event handler to import, save and restore
		$( '#import' ).click( ImportWPGallery );
		
		$( '#s_save' ).click( SaveSettings );

		$( '#s_restore' ).click( RestoreSettings );	
		
	});

	/** -------------------------------------------------------------- *\
	 *	'Import' button clicked. 
	/** -------------------------------------------------------------- */
	function ImportWPGallery( evt ){
	
		// get current settings?		
		doAJAX( 'impWPG' );
	
		// do not propagate event.
		evt.preventDefault();
		return false;
		
	} //ImportWPGallery 
	

	/** -------------------------------------------------------------- *\
	 *	'Save Settings' button clicked. 
	/** -------------------------------------------------------------- */
	function SaveSettings( evt ){
	
		// get all form input
		var input = $( 'div.wpg2mod_settings form' ).serializeArray();		
	
		// NOTE: unchecked input is not covered by serializeArray!
		// Therefore we need to handle this case separately.
		$( 'input:checkbox:not(:checked)' ).each( function() {			
			input.push( { name: this.name, value: '' } );			
		});		
		
		doAJAX( 'settings', input );
	
		// do not propagate event.
		evt.preventDefault();
		return false;
		
	} //SaveSettings 
	
	/** -------------------------------------------------------------- *\
	 *	'Restore Settings' button clicked. 
	/** -------------------------------------------------------------- */
	function RestoreSettings( evt ){
	
		doAJAX( 'restDEF' );	// restore defaults
	
		// do not propagate event.
		evt.preventDefault();
		return false;
		
	} //RestoreSettings 	
	
	/** -------------------------------------------------------------- *\
	 *	Do an AJAX call identified by the 'what' parameter.
	 *
	 *	@param	string	what	AJAX call identifier
	 *	@param	mixed	input	additional (form) input data (optional)
	/** -------------------------------------------------------------- */
	function doAJAX( what, input ){

		// get nonce
		var $nonce = $( 'div.wpg2mod_wrap' ).data( 'sec' );		
	
		// data to be sent
		var data = {
			'action':	'ajx_wpg2mod',	// must match wpg2mod-admin-ajax handler!
			'verif':	$nonce,
			'what':		what,
			'input':	input			// user input via form (optional)
		};		
		
		// assign success function depending on 'what'
		var success;
		switch( what ) {			
			
			case 'impWPG':
				// disable inputs, show spinner, ...
				$( '.wpg2mod_wrap input' ).prop( 'disabled', true );
				show_spinner();
				success = ajaxImpWPG; 
				break;
			
			case 'restDEF':	
				success = ajaxDEF; 
				break;
				
			default:		
				success = ajaxSuccess; 
		}
					
		// setup the AJAX call structure
		$.post(			
			ajaxurl,			// NOTE: for admin area defined as global in WP core!
			data,
			success				// AJAX return callback
		);			
	
	} // doAJAX
	
	/** -------------------------------------------------------------- *\
	 *	Success Callback: Restore default settings given by AJAX data.
	 *
	 *	@param	json data	returned data from AJAX call.
	/** -------------------------------------------------------------- */
	function ajaxDEF( data ) {
	
		//deal with error
		if( data[0] < 0 ) {
			show_admin_message( 'error', data[1] );
			return;
		}
		
		// get default setting data from JSON array
		var def = data[2];
	
		// assign data to input fields
		for( var key in def ) {
			
			var $inp = $( '#' + key );
			var i_type = $inp.attr( 'type' );
			
			// NOTE: we only have text and checkbox input fields
			if( i_type == 'text' ) {
				$inp.attr( 'value', def[key] );
				continue;
			}

			if( i_type == 'checkbox' ) {
				var val = parseInt( def[key] ) > 0;
				$inp.prop( 'checked', val );
				continue;
			}
		
		}
		
		// inform user
		show_admin_message( 'info', data[1] );
		setTimeout( function() {
				rem_admin_message();		// remove notification					
				}, 4000 );
		
		
	} // ajaxDEF

	/** -------------------------------------------------------------- *\
	 *	Success Callback: Show a simple AJAX success message.
	 *
	 *	@param	json data	returned data from AJAX call.
	/** -------------------------------------------------------------- */
	function ajaxSuccess( data ) {
	
		var type = 'info';
		if( data[0] < 0 ) type = 'error';
		show_admin_message( type, data[1] );
		
		if( data[0] >= 0 ) {	// no error, auto-remove message
			setTimeout( function() {
				rem_admin_message();		// remove notification					
				}, 4000 );
		}
		
	} // ajaxSuccess

	/** -------------------------------------------------------------- *\
	 *	ImpWPG success callback: Remove spinner and do Successs.
	 *
	 *	@param	json data	returned data from AJAX call.
	/** -------------------------------------------------------------- */
	function ajaxImpWPG( data ) {
			
		ajaxSuccess( data );
		
		// enable input again
		$( '.wpg2mod_wrap input' ).prop( 'disabled', false );
		
		$( 'div.wpg2_spinner p' ).hide();		
		
	} // ajaxImpWPG


	/** -------------------------------------------------------------- *\
	 *	Display a 'spinner' to indicate ongoing operation.
	 *	(see CSS file).
	/** -------------------------------------------------------------- */
	function show_spinner() {
		
		$( 'div.wpg2_spinner p' ).show();		
		
	} // show_spinner
	
	
	/** -------------------------------------------------------------- *\
	 *	Show an admin message (notice) beneath the <h1> tag.
	 *
	 *	Uses original WP markup.
	 *
	 *	@param	string	type	notice class - 'error, warning, info'
	 *	@param	string	msg		message string
	/** -------------------------------------------------------------- */
	function show_admin_message( type, msg ) {

		window.scrollTo(0, 0);

		var but = '<button class="notice-dismiss" type="button" ></button>';
		var dismiss = ' is-dismissible ';
		if( type == 'info' ) {
			but = ''; dismiss = '';
		}	
		
		// Assemble WP HTML message has to be inserted into DOM.
		// NOTE: Using WP markup.
		var html = '<div id="wpg2mod-notif" class="notice notice-' + type +
					dismiss + '">' + '<p>' + msg + '</p>' + but + '</div>';

		// check, if notification is present
		var $notif = $( '#wpg2mod-notif' );
		if( $notif.length > 0 ) {
			
			$notif.replaceWith( html );		// replace
			
		} else {
			
			$( '.wrap > h1:first-child' ).after( html );	// insert
			
		}
				
		// attach dismiss handler
		$( '#wpg2mod-notif button' ).click( rem_admin_message );		
		
	
	} // show_admin_message

	/** -------------------------------------------------------------- *\
	 *	Remove admin message (notice) beneath the <h1> tag.
	/** -------------------------------------------------------------- */
	function rem_admin_message( type, msg ) {
		
		$( '#wpg2mod-notif' ).remove();
		
	} // rem_admin_message
	
})( jQuery );
