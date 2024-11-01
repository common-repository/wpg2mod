<?php
/** -------------------------------------------------------------------- *\
 * 	The backend specific functionality of WPG2Mod.
 *
 *		+ enqueues scripts & styles
 *		+ display the settings/options page 
 *		+ deals with settings (not using the WP Settings API, though!)
 *		+ accepts and relays AJAX calls
 *		@since 1.0.1
 *		+ capabilities: from manage_options -> manage_categories
 *		+ display: version, settings explanation
 *
 * 	@package    	WPG2Mod
 * 	@subpackage 	WPG2Mod/admin
 * 	Text Domain:    wpg2mod
 * 	Domain Path:    /languages 
 * 	@license 		GPL-3
 * 	@version		1.0.1
 * 	@author			edobees
 *
 * 	Date: 9. Mar. 2019
\** -------------------------------------------------------------------- */

class WPG2Mod_Admin {

	private $plugin_name;
	private $version;
	private $conf;				// configuration data 
	
	// prefix for option_names (settings, etc.)
	public function __construct( $conf ) {
		$this->conf = $conf;
		$this->plugin_name = $conf['name'];
		$this->version = $conf['version'];

	} // constructor

	// enqueue admin stylesheets (called by loader!)
	public function enqueue_styles() {

		$handle = $this->plugin_name;	// unique name for style sheet
		$src = plugin_dir_url( __FILE__ ) . 'css/wpg2mod-admin.css'; // URL
		$deps = array();				// dependency array
		$ver = $this->version;			// version, needed for cache invalidation
		$media = 'all';					// media query
		
		wp_enqueue_style( $handle, $src, $deps, $ver, $media );

	} // enqueue_styles

	// enqueue admin JS (called by loader!)
	public function enqueue_scripts() {

		$handle = $this->plugin_name;	// unique name for script
		$src = plugin_dir_url( __FILE__ ) . 'js/wpg2mod-admin.js'; // URL
		$deps = array('jquery');		// dependency array, jquery is needed
		$ver = $this->version;			// version, needed for cache invalidation
		$media = 'all';					// media query
		$in_footer = true;				// load in footer? yes

		wp_enqueue_script( $handle, $src, $deps, $ver, $in_footer );

	} // enqueue_scripts
	
	/* ------------------------------------------------------------------------ *\
	 *	Add an options page under the Settings submenu
	\* ------------------------------------------------------------------------ */	
	public function add_options_page() {
	
		$page_title = __( 'Import WP Galleries', 'wpg2mod' );
		$menu_title = __( 'WPG2Mod', 'wpg2mod' );
		//@since 1.0.1
		$capabilities = 'manage_categories';
		$menu_slug = $this->plugin_name;
		$call_back = array( $this, 'display_options_page' );

		$pf = $this->get_modula_file();
		
		// if Modula is activated, insert options menu into Modula menu
		if( $pf && is_plugin_active( $pf ) ) {

			$this->plugin_screen_hook_suffix = add_submenu_page(
				'edit.php?post_type=modula-gallery',		// under MODULA submenu
				$page_title,
				$menu_title,
				$capabilities,
				$menu_slug,
				$call_back				
			);
		
		} else { // insert into general plugin settings
		
			$this->plugin_screen_hook_suffix = add_options_page( 
				$page_title,
				$menu_title,
				$capabilities,
				$menu_slug,
				$call_back				
			);		
		}
		
	} // add_options_page


	/* ------------------------------------------------------------------------ *\
	 *	Callback function for options page. 
	\* ------------------------------------------------------------------------ */	
	public function display_options_page() {
		
		// add nonce as data attribute
		$nonce = wp_create_nonce( 'wpg2mod_nonce' );
		
		$ver = $this->conf['version']; // @since 1.0.1
		echo '<div data-sec="' . $nonce . '" class="wpg2mod_wrap wrap">';
		echo '<h1>WPG2Mod<span>'.$ver.'</span></h1>';
				
		echo '<div class="head">';
				
		$txt =  __( "Import and convert existing WordPress Galleries into Modula Galleries.", 'wpg2mod' );
		echo "<div class=\"hl\"><p>$txt ";
		$txt =  __( "WPG2Mod looks for WordPress Galleries inside your posts and generates Modula Galleries.", 'wpg2mod' );
		echo "$txt</p>";
		$txt = __("Make sure to backup your data before using WPG2Mod!", 'wpg2mod' );
		echo "<p class=\"wpg2mod_bu_warn\"><span>$txt</span></p>";
		
		// place holder for 'spinner'
		echo '<div class="wpg2_spinner"><p></p></div>';
		
		// the import button
		submit_button( __( 'Import WPG', 'wpg2mod' ), 'primary', 'import', true, 
						array('id' => 'import') );
						
		echo "</div>";				
						
		$src = plugin_dir_url( __FILE__ ) . 'img/wpg2mod-icon.jpg'; // icon
		echo "<div class=\"hr\"><img src=\"$src\" alt=\"\" width=\"128\" height=\"128\" /></div>";		
					
		echo "<p class=\"clear\"></p></div>";
		
		$this->display_settings_section();

		echo '</div>';
		
	} // display_options_page

	
	/** -------------------------------------------------------------------- *\
	 * 	Display the settings section.
	 *	NOTE: It is a good idea to use the wp_option keys to name the 
	 *	input fields.
	\** -------------------------------------------------------------------- */
	function display_settings_section() {
	
		echo '<div class="wpg2mod_settings">';
		$t = __( "WPG2Mod's Settings", 'wpg2mod' );
		echo "<h2>$t</h2>";
		echo '<form>';		
		
		// --- excluded posts 
		$opt = $this->conf['opt_exclude']; 
		$exl_pt = get_option( $opt );

		echo '<label for="' . $opt . '">' . '<span>' . 
			 __( 'Excluded Post Types', 'wpg2mod' ) . '</span></label>';

		echo '<p><input type="text" name="' . $opt . '" id="' . $opt 
				 . '" value="' . $exl_pt . '" title="' . $exl_pt . '"></p>';
				 
		$t = __( "Comma-separated list of post types to be excluded from the WP Gallery
			  search.<br>Typical exclusions: attachment, nav_menu_item, revision.", 'wpg2mod' );
		echo "<p>$t</p>";

		// --- use import template?
		$opt = $this->conf['opt_use_templ'];
		$ticked = get_option( $opt ); 
		$check = $ticked ? 'checked' : '';		
		
		$tmpl = $this->conf['modula_template'];
		
		echo '<h3><span>' . 
			 __( 'Use Modula Setting Template?', 'wpg2mod' ) . '</span></h3>';

		echo '<input type="checkbox" name="' . $opt . '" id="' . $opt 
			 . '" value="Yes" ' . $check . '>';

		echo '<label class="checkker" for="' . $opt . '">' . 
			 __( 'Tick to use the template.', 'wpg2mod' ) . '</label>';		 
			 
		$t = sprintf( __("If ticked, WPG2Mod will use the settings of the Modula Gallery <strong>&lt;%s&gt;</strong> for the generated galleries.", 'wpg2mod'), $tmpl );
		echo "<p>$t</p>";
			  
		// --- replace WP Galleries on-the-fly? (replace WPG shortcode)
		$opt = $this->conf['opt_conv_wpg']; 
		$ticked = get_option( $opt ); 
		$check = $ticked ? 'checked' : '';		
		
		echo '<h3><span>' . 
			 __( 'On-the-fly WP Gallery Conversion', 'wpg2mod' ) . '</span></h3>';

		echo '<input type="checkbox" name="' . $opt . '" id="' . $opt 
			 . '" value="Yes" ' . $check . '>';

		echo '<label class="checkker" for="' . $opt . '">' . 
			 __( 'Tick to display a Modula Gallery.', 'wpg2mod' ) . '</label>';	
			 
		$t = sprintf( __("If ticked, WPG2Mod will expand a WP Gallery shortcode to a matching Modula Gallery.<br>A Modula gallery will be created if necessary.", 'wpg2mod') );
		
		echo "<p>$t</p>";
			  
		echo '</form>';
		
		// save & default buttons
		echo '<p class="submit">';
		echo '<input name="s_restore" class="button" id="s_restore" type="submit" ';
		echo 'value="' . __( "Restore Defaults", 'wpg2mod' ) . '">';
		echo '<input name="s_save" class="button" id="s_save" type="submit" ';
		echo 'value="' . __( "Save Settings", 'wpg2mod' ) . '">';
		echo '</p>';

		echo '</div>';

	} // display_settings_section	
	
	/** ------------------------------------------------------------------------ *\
	 *	Deal with admin notices specifically transient messages.
	\** ------------------------------------------------------------------------ */	
	public function admin_notices(){
		
		// look for transient messages from activation
		$opt = $this->conf['opt_issue'];
		$msg = get_transient( $opt );
		if( !empty( $msg ) ){ 	
			$msg = '<div class="notice notice-warning 
					is-dismissible"><p>' . $msg . '</p></div>';
			echo $msg;
			delete_transient( $opt );
		}
	
	} // admin_notices
	
	/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	 *	Admin AJAX handling...
	\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
	
	/** -------------------------------------------------------------------- *\
	 * 	Main entry for AJAX calls in admin area. 
	 *	Relays to AJAX admin handlers.
	\** -------------------------------------------------------------------- */
	public function ajx_wpg2mod() {

		include_once 'wpg2mod-admin-ajax.php';
		handle_AJAX_Admin( $this );

	} // ajx_wpg2mod
	
	/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	 *	Member Getter
	\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
	
	// get exclude post type option
	public function get_exPT() { return $this->conf['opt_exclude']; } 
	
	// get MigSupp post ID key
	public function get_key_MigSupID() { return $this->conf['key_migsup_id']; } 

	// get WP gallery token
	public function get_GalTok() { return $this->conf['gal_token']; } 

	/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	 *	Static Functions
	\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

	/** ------------------------------------------------------------------------ *\
	 *	Check if Modula is installed.
	 *	looking for the substring 'modula' in plugin name/title.
	 *	return string		plugin file, if Modula is installed
	 *						otherwise false
	\** ------------------------------------------------------------------------ */	
	public static function get_modula_file(){

		// get all plagins
		$pis = get_plugins( );
		
		// look for modula in name & title
		$modRoot = 'modula';
		
		foreach( $pis as $key => $val ) {
		
			if( false === stripos( $val['Name'], $modRoot ) &&
				false === stripos( $val['Title'], $modRoot ) ) {
					
				continue; // keep on searching
			
			} else {
				
				// looks like a match
				return $key;
			}				
		}

		return false;

	} // get_modula_file		
	
} // WPG2Mod_Admin 
?>