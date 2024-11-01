<?php
/** ------------------------------------------------------------------------ *\
 * 	This is where it all begins...
 *	
 *	WPG2Mod - plugin to convert existing WordPress galleries into Modula galleries.
 *
 *		+ satisfies the WP plugin interface & starts it up
 *		+ defines & instantiates the WPG2Mod Main object
 *
 *	@since 1.0.1 -- auto-creation of Modula Galleries from WPG shortcode
 *
 * 	@wordpress-plugin
 * 	Plugin Name:       WPG2Mod
 * 	Plugin URI:        https://plugins.svn.wordpress.org/wpg2mod
 * 	Description:       Import and convert WordPress Galleries into Modula Galleries.
 * 	Version:           1.0.1
 * 	Author:            edobees
 * 	Author URI:        edobees.de
 * 	Text Domain:       wpg2mod
 * 	Domain Path:       /languages
 *
 *	Date: 16. Feb. 2019
 *
 * 	@package    	WPG2Mod
 * 	@license 		GPL-3
 * 	@version		1.0.1
 * 	@author			edobees
 *	@copyright		2019 edobees 
 *
 * 	This program (the WPG2Mod plugin) is free software; you can redistribute 
 * 	it and/or modify it under the terms of the GNU General Public License 
 *	version 3, as published by the Free Software Foundation. 
 *	You may NOT assume that you can use any other version of the GPL.
 *
 * 	This program is distributed in the hope that it will be useful, but
 *	WITHOUT ANY WARRANTY; without even the implied warranty of 
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
/** ------------------------------------------------------------------------ */
// If this file is called directly, abort.
if ( !defined( 'WPINC' ) ) { die; }

/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	Global Configuration File
\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
define( 'WPG2MOD_CONFIG', 'wpg2mod_conf.php' ); 	// configuration file

/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	Hook WPG2Mod into the WP universe
\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

// WPG2Mod's activation hook
register_activation_hook( __FILE__, 'activate_wpg2mod' );

// WPG2Mod's deactivation hook
register_deactivation_hook( __FILE__, 'deactivate_wpg2mod' );

/** -------------------------------------------------------------------- *\
 * 	Instantiate the WPG2Mod core object and run it.
\** -------------------------------------------------------------------- */
$plugin = new WPG2Mod();
$plugin->run();


/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	Conventional Function Area
\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/** -------------------------------------------------------------------- *\
 * 	Main entry call for WPG2Mod activation via WP hook mechanism.
 *	Checks:
 *		+ Modula installed?
 *		+ WPG2Mod installed before?
 *
 *	Uses a transient to signal issues.
\** -------------------------------------------------------------------- */
function activate_wpg2mod() {
	
	$conf = include( plugin_dir_path( __FILE__ ) . WPG2MOD_CONFIG );
		
	// Modula installed?	
	$pf = WPG2Mod_Admin::get_modula_file();	
	
	if( empty( $pf ) ) {

		error_log( "###Modula has not been installed." );
		
		$msg = __( "It seems as if Modula has not been installed / activated.",
					'wpg2mod' );
	
		// setup transient for notification
		$opt = $conf['opt_issue'];
		set_transient( $opt, $msg, MINUTE_IN_SECONDS );
		
		// no further checks
		return;		
	}	

	// WPG2Mod installed before?
	$on = $conf['opt_prev_ver'];	// previous version of WPG2Mod
	$pre_ver = get_option( $on  );
	
	if( false != $pre_ver && !empty( $pf ) ) {			
		error_log( "###WPG2Mod has been installed before. Version: $pre_ver." );
	}
	
	// write actual WPG2Mod version as previous version option  ;-) 
	update_option( $on, $conf['version'] );	
	
	// write default options to database
	update_option( $conf['opt_exclude'], $conf['def_exclude'], false );
	update_option( $conf['opt_use_templ'], $conf['def_use_templ'], false );
	update_option( $conf['opt_conv_wpg'], $conf['def_conv_wpg'], false );

} // activate_wpg2mod


/** -------------------------------------------------------------------- *\
 * 	Main entry call for WPG2Mod de-activation via WP hook mechanism.
\** -------------------------------------------------------------------- */
function deactivate_wpg2mod() {
	
	// no action, yet?
	// error_log( 'WPG2Mod has been deactivated.' );
	
} // deactivate_wpg2mod
	

/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	WPG2Mod Class definition - the core implementation object.
\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
class WPG2Mod {

	protected $conf;	// configuration array read from wpg2mod_conf.php

	// constructor
	public function __construct() {
		
		// load configuration data	
		$this->conf = include( plugin_dir_path( __FILE__ ) . WPG2MOD_CONFIG );
		
		$this->load_dependencies();
		$this->set_locale();
		$this->add_hooks();

	} // constructor

	/** -------------------------------------------------------------------- *\
	 * 	Load alls depencies and add hooks for WPG2Mod.
	 *
	 *	Includes following files:
	 * 	- wpg2mod-admin.php - Defines all hooks for the admin area.
	 * 	- wpg2mod-public.php - Defines all hooks for the fornt end.
	 *
	\** -------------------------------------------------------------------- */
	private function load_dependencies() {

		$path = plugin_dir_path( __FILE__  );
	
		// admin object
		require_once $path . 'admin/wpg2mod-admin.php';
		
		// public (front) object
		require_once $path . 'public/wpg2mod-public.php';

	} // load dependencies

	/** -------------------------------------------------------------------- *\
	 * 	Internationalization. 
	\** -------------------------------------------------------------------- */
	 private function set_locale() {

		$dir = dirname( plugin_basename( __FILE__ ) )  . '/languages/';

		load_plugin_textdomain(
			'wpg2mod',			// text domain
			false,				// = abs_rel_path --> deprecated!
			$dir				// languages directory
		); 
	 
	} // set_locale()

	/** -------------------------------------------------------------------- *\
	 * 	Setup the admin and public interface.
	 *	The admin and the front objects are taking care of implementation.
	 *	Includes scripts and various hooks.
	\** -------------------------------------------------------------------- */	 
	private function add_hooks() {
		
		// create admin object --------------------------------------------
		$pa = new WPG2Mod_Admin( $this->conf );

		// load scripts and styles for admin area
		add_action( 'admin_enqueue_scripts', array( $pa, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $pa, 'enqueue_scripts' ) );

		// add options/settings page
		add_action( 'admin_menu', array( $pa, 'add_options_page' ) );
						
		// add AJAX handler for authorized calls (in admin area!)
		add_action( 'wp_ajax_ajx_wpg2mod', array( $pa, 'ajx_wpg2mod' ) );

		// add handler for admin_notices (looking for transients?)
		add_action( 'admin_notices', array( $pa, 'admin_notices' ) );	

		// create public (frontend) object ---------------------------------		
		$front = new WPG2Mod_Front( $this->conf );
		
		// load script(s) for front end
		add_action( 'wp_enqueue_scripts', array( $front, 'enqueue_styles' ) );
		
		// add handler for init action (WP is loaded)
		add_action( 'init', array( $front, 'modify_shortcode' ) );	
		
	} // add_hooks 

	
	/** -------------------------------------------------------------------- *\
	 * 	The run function ... nothing (special) here.
	\** -------------------------------------------------------------------- */	 
	public function run() {

		// nothing, yet

	} // run


	/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
		Get/Set Member Variables
	\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
	// get the name
	public function get_plugin_name() { return $this->conf['name']; } 

	// get the version 
	public function get_version() { return $this->conf['version']; } 

} //WPG2Mod
?>