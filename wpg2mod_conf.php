<?php
/** -------------------------------------------------------------------- *\
 * 	Constants and settings for (default) configuration of WPG2Mod.
 *
 *	Including this file will make the configuration data available to
 *	consumers as an array.
 *
 * 	@package    	WPG2Mod
 * 	@license 		GPL-3
 * 	@version		1.0.1
 * 	@author			edobees
 *
 * 	Date: 9. Mar. 2019
\** -------------------------------------------------------------------- */
// The global (configuration) data for WPG2Mod ...
return array(

    'name' 			=> 'WPG2Mod',			// Plugin Name 
    'version' 		=> '1.0.1',				// WPG2Mod version
    
	'gal_token'		=> '[gallery',			// token to identify beginning of a
											// WP Gallery shortcode

	'mgal_cpt'		=> 'modula-gallery',	// Modula custom post type - as defined by
											// the Modula plugin.
	'mgal_img_key'	=> 'modula-images',		// post meta key for image data
	'mgal_set_key'	=> 'modula-settings',	// post meta key for gallery settings
	'mgal_tit_pref'	=> 'GEN-MG-',			// Title Prefix for created Modula galleries
	
	'excl_posttypes'	=> 'attachment, nav_menu_item, revision', 
	'modula_template'	=> 'setting-template',	// title of template modula post
	
	// WP options - to be stored in DB
	'opt_prev_ver'	=> 'wpg2mod_opt_prev',		// (previous) version of WPG2Mod
	'opt_issue'		=> 'wpg2mod_opt_act_issue',	// actual transient issue
	
	// option keys
	'opt_exclude'	=> 'wpg2mod_opt_exl_pt',	// excluded post types string list
	'opt_use_templ'	=> 'wpg2mod_opt_use_templ',	// use template?
	'opt_conv_wpg'	=> 'wpg2mod_opt_conv_wpg',	// convert WPG shortcodes on-the-fly?

	// 'hidden' post meta of modula-gallery for source identification
	'key_src_id'	=> '_misrc',	// source post ID
	'key_src_pos'	=> '_mipos',	// WP Gallery index
	'key_migsup_id'	=> '_misid',	// MigSupport post ID
	
	// default values for options
	'def_exclude'	=> 'attachment, nav_menu_item, revision',	// excluded post types
	'def_use_templ'	=> '0',		// use template
	'def_conv_wpg'	=> '1',		// convert WPG shortcodes on-the-fly
	
	// NOTE: This array contains Modula image default values.
	// as of Jan. 2019 - MODULA 2.x.x
	'def_mia'	=> array(	
				'id' 			=> null,	// will be updated during import
				'alt' 			=> '',		// will be updated during import	
				'title' 		=> '',		// will be updated during import
				'description' 	=> '',		// will be updated during import
				'halign' 		=> 'center',
				'valign' 		=> 'middle',
				'link' 	 		=> '',			// ???
				'target' 		=> '_blank',	// HTML target
			),

	// Default Modula Gallery (2.x.x) settings (arbitrarily selected)
	// Compatible with Modula Lite/Free 
	'def_set' 	=> array(
				'type' 				=> 'creative-gallery',	
				'gutter' 			=> '10',		// ???
				'columns'			=> 6,			// ??? 
				'width' 			=> '100%',		// gallery width
				'height' 			=> '800',		// gallery height in pixel
				'img_size' 			=> '300',		// min. width/height of image
				'margin' 			=> '10',		// margin between img in pixel
				'randomFactor' 		=> '50',		// parameter for randomization
				'lightbox' 			=> 'lightbox2',	// lightbox in free version
				'shuffle' 			=> '0',			// don't shuffle images
				'captionColor' 		=> '#ffffff',
				'wp_field_title' 	=> 'title',		// use image title as title
				'wp_field_caption' 	=> 'caption',	// use image caption (=post_excerpt)
				'hide_title' 		=> '1',			// we hide titles...
				'hide_description' 	=> '1',			// ...and descriptions
				'captionFontSize' 	=> '14',		// font size (pt) for Modula
				'titleFontSize' 	=> '16',		// in pixel, 0: use defaults
				'enableTwitter' 	=> '0',			// no social media links 
				'enableFacebook' 	=> '0',
				'enableGplus' 		=> '0',
				'enablePinterest' 	=> '0',
				'socialIconColor' 	=> '#ffffff',
				'loadedScale' 		=> '100',		// zoom-in/-out on loading
				'effect' 			=> 'pufrobo',	// hover effect in free version
				'borderSize' 		=> '1',			// in pixel
				'borderRadius' 		=> '4',			// in pixel
				'borderColor' 		=> '#ffffff',			
				'shadowSize' 		=> '0',			// in pixel
				'shadowColor' 		=> '#ffffff',			
				'style' 			=> '',			// no custom CSS
				'helpergrid' 		=> '0',			// no helper grid		
			),			
);
?>