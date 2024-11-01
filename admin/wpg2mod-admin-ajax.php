<?php
/** ------------------------------------------------------------------------ *\
 *	AJAX server side to make Modula Image Galleries from WP Galleries.
 *
 *		+ generates Modula galleries and associated meta data from existing 
 *		  WP image galleries
 *		+ creates/updates Modula gallery CPT from WP Gallery images 
 *		+ returns Modula shortcode and source post ID of WP Gallery
 *		+ provides the server side for the settings
 *
 * 	@package    	WPG2Mod
 * 	@subpackage 	WPG2Mod/admin
 * 	Text Domain:    wpg2mod
 * 	Domain Path:    /languages 
 * 	@license 		GPL-3
 * 	@version		1.0.0
 * 	@author			edobees
 *
 *	Date: 18. Jan. 2019
\** ------------------------------------------------------------------------ */	

/** -------------------------------------------------------------------- *\
 * 	Main entry for all AJAX admin calls.
 * 	Errors and results are reported via JSON data array.
 *
 *	@param	obj		$adm	Plugin Admin object
 *
 *	Does not return, but dies. 
\** -------------------------------------------------------------------- */
function handle_AJAX_Admin( $adm ) {

	$what = sanitize_text_field( $_POST['what'] );
	$ret = '';

	// verify nonce
	$die = false;
	if( !check_ajax_referer( 'wpg2mod_nonce', 'verif', $die ) ){
		error_log( "###NONCE check failed." );
		$err = array( -1, __('Sorry. Call verification failed.', $adm->get_TD() ) );
		wp_send_json( $err );
	}

	// switch depending on call type
	switch( $what ) {
		
		// import existing WP galleries
		case 'impWPG': $ret = do_impWP( $adm ); break;
		
		// save the new settings
		case 'settings': $ret = do_Settings( $adm ); break;

		// restore default settings
		case 'restDEF': $ret = restoreDefSettings( $adm ); break;
		
		// a test case for development
		case 'MGMeta': $ret = doMGalMeta(); break;
		
		default: {
			error_log( "###Unknown AJAX call: $what " );
			$err = array( -1, __('Unknown AJAX call.', $adm->get_TD() ) );
			wp_send_json( $err );
		}
	
	}		

	wp_send_json( $ret );	// sends JSON data and dies

} // handle_AJAX-Admin

/** -------------------------------------------------------------------- *\
 * 	Imports WP Galleries using several function calls.
 *	
 *	@param	obj		$adm	Plugin Admin object
 *	@return array	[0]	error code, < 0 for error
 *					[1] data ... or error message
\** -------------------------------------------------------------------- */
function do_impWP( $adm ) {
	
	// get existing WP gallery strings from posts
	$gals = getWPGStrings( $adm );
	if( $gals[0]  < 0 ) {
		return $gals;
	}
	
	// We have WP Gallery image ids with associated post IDs -
	// create Modula Galleries
	$msc = create_ModulaGals( $gals[1] );
	if( isset( $msc[0] ) && $msc[0] < 0 )  {
		return $msc;
	}
		
	// create migration support post
	create_MigSupportPost( $msc, $adm );
	
	$txt = sprintf( __("%d WP Galleries found. %d Modula Galleries created / updated.", 'wpg2mod' ), $gals[0], count($msc) );
	
	return array( 0, $txt );
	
} // do_impWP

/** -------------------------------------------------------------------- *\
 * 	Deal with the settings given by the input form of the options page.
 *	Write new setting to wp_option. Make sure to validate first!
 *	
 *	$_POST['input'] contains the settings data as an assoc. array.
 *	
 *	@parameter object $adm	admin object.
 *	@return array	[0]	error code, < 0 for error
 *					[1] data ... or error message
\** -------------------------------------------------------------------- */
function do_Settings( $adm ) {

	// clear the options cache
	wp_cache_delete ( 'alloptions', 'options' );
			
	// deal with user input
	foreach( $_POST['input'] as $pair ) {
	
		$key = $pair['name']; $val = $pair['value'];
		
		// handle excluded post types
		if( $key == $adm->get_exPT() ) {
	
			$val = preg_replace( '/\s+/', '', $val );
			$pts = explode( ',', $val );
			
			// make sure, they exist!
			foreach( $pts as $pt ) {
				if( !empty($pt) && !post_type_exists( $pt ) ) {
					error_log( '###Unknown post type to exclude. $pt');
					$msg = sprintf( __( 'Error. Unknown post type "%s" to exclude.',
							'wpg2mod' ), $pt );
					return array( -1, $msg );
				}					
			}								
		}

		// write option to database
		update_option( $key, $val );
	
	}		
	
	return array( 0, __( 'Settings have been saved.', 'wpg2mod' ) );
	
} // do_Settings


/** -------------------------------------------------------------------- *\
 * 	Retrieve WP Gallery image ID strings from posts.
 *	To avoid potential PHP memory overflows the retrieval is done row 
 *	by row instead of a bulk action.
 *
 *	@param	obj		$adm	Plugin Admin object 
 *	@return array	WP Gallery strings or error array
\** -------------------------------------------------------------------- */
function getWPGStrings( $adm ) {

	global $wpdb;
	
	// excluded post_types
	$excl_pt = get_option( $adm->get_exPT() );
	
	// clean-up input, strp whitespace 
	$excl_pt = preg_replace( '/\s+/', '', $excl_pt );
	if( !empty( $excl_pt ) ) {
		$tmp = explode( ',', $excl_pt );
		$excl_pt = "'" . implode( "','" , $tmp ) . "'";
	}
	
	// token to be used as identifier for WP gallery shortcode
	$galToken = $adm->get_GalTok();
	
	// 1. Get all posts containing at least one WP gallery
	$sql = "SELECT p.ID, p.post_title, CHAR_LENGTH( p.post_content ) AS len
			FROM la1_posts p
			WHERE p.post_content LIKE '%" . $galToken  . "%' ";
			
	if( !empty( $excl_pt ) ) {
		$sql .= "AND p.post_type NOT IN ($excl_pt) ";
	}					
	
	// do the query
	$recs = $wpdb->get_results( $sql );
	if( $wpdb->num_rows == 0 ) {
		if( $recs === false ) {
			$err = 	$wpdb->last_error;
			error_log( "###DB Error in " . __FILE__ .  " - line " . __LINE__ . ": $err" );
		}
		
		// exit with error coding
		return array( -1, __('No WP Galleries found.', $adm->get_TD() ) );
	}

	// 2. Now, iterate over all posts with a WP Gallery shortcode.
	$wp_gals = array();		// array for found WP Galleries image IDs

	foreach( $recs as $row ) {
	
		$ids = getWPGalImgIDs( $row->ID, $galToken );
		if( false == $ids ) {		
			
			error_log( "###No shortcode for post[$row->title]." );
		
		} else {
			
			// put all entries in a resulting WP gallery array
			foreach( $ids as $entry ) { 
				$wp_gals[] = $entry;
			}			
		}	
	}
	
	return array( count($wp_gals), $wp_gals );
	
} // getWPGStrings

/** -------------------------------------------------------------------- *\
 * 	Retrieve WP Gallery image IDs from a post.
 *	NOTE: A post can have more than one gallery shortcode.	
 *
 *	@param int $pid			WP post id
 *	@param str $galToken	WP Gallery shortcode token
 *	@return array			WP Gallery image IDs or false 
\** -------------------------------------------------------------------- */
function getWPGalImgIDs( $pid, $galToken ) {

	// resulting image ID array
	$imgIDs = array();		

	global $wpdb;
	$sql = "SELECT 
			SUBSTR( p.post_content, LOCATE( '" . $galToken . "', p.post_content ) ) as pc
			FROM la1_posts p
			WHERE p.ID = $pid ";

	$row = $wpdb->get_row( $sql );
	
	if( empty( $row ) ) {
		$err = 	$wpdb->last_error;
		error_log( "###DB Error in " . __FILE__ .  " - line " . __LINE__ . ": $err" );

		// just, exit with error coding
		return false;	
	}

	// find all gallery short codes in post content...
	$pat = '/\\' . $galToken . '[\s\S]*?\]/';	// find: '[gall ... ]' not greedy, use *?
	$res = preg_match_all( $pat, $row->pc, $matches );

	// iterate over matches to get shortcodes, might be more than one gallery
	$pos = 0;		// index of WP Gallery found - o-based
	foreach( $matches[0] as $shortcode ) {		

		// find image IDs in gallery short code
		// find: 'ids="12,34,56"' being not greedy(*?)
		// brackets () group everything inside the "", () can be accessed as $img_ids[1]
		$pat = '/ids\s*=\s*\"([\s\S]*?)\"/';	
		$res = preg_match( $pat, $shortcode, $img_ids );
		if( !empty( $res ) ) {
			
			// return 1st group () of RegEx patttern as list of image IDs
			$imgIDs[] = array( 'PID' => $pid, 'ids' => $img_ids[1], 'pos' => $pos );
		
		$pos++;	
		}
				
	}		

	return $imgIDs;

} // getWPGalImgIDs

/** -------------------------------------------------------------------- *\
 * 	Create or update Modula Gallery post from WP Gallery image IDs.
 *
 *	@param array $wpg_ids	WP Gallery image ids and assoc. post_id
 *	@return array			Modula Gallery shortcodes 
 *							or error array. 
\** -------------------------------------------------------------------- */
function create_ModulaGals( $wpg_ids ) {

	$msc = array();
		
	foreach( $wpg_ids as $wpg_id ) {
	
		// build image ID array from string
		$ids = $wpg_id['ids'];	// comma separated list of image IDs

		// strip all white space - just in case
		$ids = preg_replace( '/\s+/', '', $ids );
	
		// convert WP Gallery images to Modula image arrays
		$a_ids = explode( ',', $ids );
		$mia = id2ModulaImg( $a_ids );
	
		// create/update Modula Gallery post from image array
		$sc = insert_ModulaGal( $mia, $wpg_id['PID'], $wpg_id['pos'] );
		if( isset( $sc[0] ) && $sc[0] < 0 ) {
			return $sc;
		}
		

		$msc[] = array( 'sc' => $sc, 'src_pid' => $wpg_id['PID'], 
						'pos' => $wpg_id['pos'] );
	}
	
	return $msc;
		
} // create_ModulaGals

/** -------------------------------------------------------------------- *\
 * 	Convert WP image IDs to Modula image array.
 *	
 *	@param array $a_ids		WP Gallery image ids
 *	@return array			Modula image array
\** -------------------------------------------------------------------- */
function id2ModulaImg( $a_ids ) {

	// read configuration
	$conf = include( plugin_dir_path( __DIR__ ) . WPG2MOD_CONFIG );

	$mia = array();
	$def_mia = $conf['def_mia'];  // default image attributes
		
	// iterate over every image ID and generate a Modula Gallery image entry.
	foreach( $a_ids as $a_id ) {
		
		$inf = getIMGInfo( $a_id );
		
		$act = array(		
			'id' 			=> $a_id,
			'title' 		=> $inf['title'],
			'description' 	=> $inf['caption'],
			'alt' 			=> $inf['alt'],
			// NOTE: Modula allows to assign combinations of 'title', 'description' 
			// and 'caption' (=post_excerpt) to their image captions.
			// Which values are actually used, is decided in Modula's settings pages.
			// We use 'caption' that matches the default settings, we have used.
		);
		
		$mia[] = array_replace( $def_mia, $act );
		
	}
		
	return $mia;

} // id2ModulaImg

/** -------------------------------------------------------------------- *\
 * 	Insert (or update) Modula Gallery post from image array.
 *	
 *	@param array $mia		Modula image array making up the gallery
 *	@param int $src_pid		'Source' ID of post,where the WP gallery has 
 *							been used.
 *	@param int $pos			zero-based index of the WP Gallery shortcode
 *							inside $src_pid
 *	@return string  		Modula shortcode or error object
 *							[0] error code (<0)
 *							[1] error message
\** -------------------------------------------------------------------- */
function insert_ModulaGal( $mia, $src_pid, $pos ) {

	// read configuration data
	$conf = include( plugin_dir_path( __DIR__ ) . WPG2MOD_CONFIG );
	
	// check the input
	if( empty($mia) || !isset( $mia[0]['alt'] ) ) {
		error_log( '###Bad Modula image array.' );
		return array( -1, 
			__( 'Fatal error. Bad Modula image array.', 'wpg2mod' )  ); 
	}
	
	// set title - prefix - source ID - - position (starting from 1!)
	$title = $conf['mgal_tit_pref'] . $src_pid . '-' . ($pos + 1);
	
	// Modula gallery post base data
	$mgal = array(
		'post_type'		=> $conf['mgal_cpt'],		// Modula CPT
		'post_author'	=> get_current_user_id(),
		'post_title'	=> $title,
		'post_content'	=> '',						// does not have content
		'post_status'	=> 'draft',
	);
	
	
	// look for matching Modula gallery ID
	$mgal_id = findMG_ID( $conf, $src_pid, $pos );		

	// Future option? @todo ???
	// We are always in update mode, but we might consider to offer
	// an additional append mode for future versions of WPG2Mod.
	$upd_mode = true;	// As of now: We are always in update mode
	
	// create new gallery, if we have no match or if we are not in update mode
	if( false == $mgal_id || !$upd_mode ) { 

		// insert Modula gallery CPT post
		$wp_err = true;		// create a WPError object 
		$mgal_id = wp_insert_post( $mgal, $wp_err );
	
		if ( is_wp_error( $mgal_id ) ) {
			$err = $result->get_error_message();
			error_log( "###MGal insert failed. $err ");
			return array( -1, 
			__( 'Fatal error. Could not create Modula gallery.', 'wpg2mod' ) );
		}		
	}	

	// We should have a valid Modula Gallery ID now
	
	// insert/update image array in post_meta 'modula-images' 
	$val = $mia;		// modula image array
	$key = $conf['mgal_img_key'];
	$pm_id = update_post_meta( $mgal_id, $key, $val ); 
	
	if( false === $pm_id && !$upd_mode ) {
		error_log( "###MGal insert error. Image meta data not added.");
		return array( -1, 
		__( 'Fatal error. Could not insert images in Modula gallery.', 'wpg2mod' ) );
	}

	// insert default settings array in post_meta 'modula-settings' 
	$val = $conf['def_set'];	
	
	// check, if setting-template data should be used
	$opt = get_option( $conf['opt_use_templ'] );	
	if( $opt ) {
		
		// get post id of setting template
		$cpt = $conf['mgal_cpt'];
		$tmp = get_page_by_title( $conf['modula_template'], OBJECT, $cpt );
		if( !empty( $tmp ) ) {
		
			// use gal settings of template
			$key = $conf['mgal_set_key'];
			$tval = get_post_meta( $tmp->ID, $key, true );
			if( !empty($tval) ) {
				$val = $tval;
			}
		
		} else { // gallery post template not found.
			$tn = $conf['modula_template'];
			error_log( '###Missing template: ' . $tn );
			$msg = sprintf( __( 'Fatal error. Missing setting template. Looking for Modula Gallery Post: "%s".', 'wpg2mod' ), $tn );
			
			return array( -1, $msg );
		}
	}
	
	// add/insert gallery settings as post meta
	$pm_id = update_post_meta( $mgal_id, $conf['mgal_set_key'], $val );
	
	if( false === $pm_id && !$upd_mode ) {
		error_log( "###MGal insert error. Setting meta data not inserted.");
		return false;	
	}
	
	// add additional WPG2Mod private post_meta to identify source post
	// to be used by shortcode replacement
	$mi_src = $conf['key_src_id'];
	$mi_pos = $conf['key_src_pos'];
	
	$pm_id = update_post_meta( $mgal_id, $mi_src, $src_pid );
	$pm_id = update_post_meta( $mgal_id, $mi_pos, $pos );
	
	// return modula short_code for generated gallery
	return '[modula id="' . $mgal_id . '"]';

} // insert_ModulaGal

/** -------------------------------------------------------------------- *\
 * 	Utility: Get WP image information.
 *
 *	@param int $pid			post id of image attachment
 *	@return array			image information or null
\** -------------------------------------------------------------------- */
function getIMGInfo( $pid ) {

	$p = get_post( $pid );
	if( empty( $p || $p->post_type != 'attachment' ) ) {
		return null;
	}

	$single = true;
	return array( 
		'title'			=> $p->post_title,
		'caption'		=> $p->post_excerpt,
		'description'	=> $p->post_content,
		'alt'			=> get_post_meta( $pid, '_wp_attachment_image_alt', $single ),
		'href'			=> get_permalink( $pid ),	
	);
	
} // getIMGInfo


/** -------------------------------------------------------------------- *\
 * 	Utility: Find Modula Gallery for WP Gallery.
 *
 *	@param mixed $conf		configuration data
 *	@param int $src_pid		post ID of WP Gallery
 *	@param int $pos			index of WP Gallery inside src post
*	@return int 			post ID of Modula Gallery or false
\** -------------------------------------------------------------------- */
function findMG_ID( $conf, $src_pid, $pos ) {

	global $wpdb;
	
	$cpt = $conf['mgal_cpt'];
	$mi_src = $conf['key_src_id'];
	$mi_pos = $conf['key_src_pos'];

	// get post ID of 
	$sql = $wpdb->prepare( 			
			"SELECT p.id, pm2.meta_value as pos
			FROM la1_postmeta pm 
			JOIN la1_posts p ON p.ID = pm.post_id
			JOIN la1_postmeta pm2 ON pm2.post_id = p.ID 
			WHERE pm.meta_key = '%s' AND pm.meta_value = %d 
			AND p.post_type = '" . $cpt . "' 
			AND (p.post_status = 'draft' OR p.post_status = 'publish')
			AND pm2.meta_key = '%s';", $mi_src, $src_pid, $mi_pos );
	
	$res = $wpdb->get_results( $sql );
	
	// iterate over results and find matching IDs
	foreach( $res as $pair ) {		
		if( $pair->pos == $pos ) { 
			return $pair->id;
		}	
	}
	
	return false;	// i.e. no match
	
} // findMG_ID


/** -------------------------------------------------------------------- *\
 * 	Create edit support post with links to original WP Gallery posts.
 *
 *	@param array $msc		array with Modula shortcodes and source post IDs.
 *	@param	obj		$adm	Plugin Admin object 
 *	@return bool			false on error
\** -------------------------------------------------------------------- */
function create_MigSupportPost( $msc, $adm ) {

	// some basic checking
	if( empty($msc) || !isset( $msc[0]['src_pid'] ) ) {
		return false;
	}


	// assemble post content, wrapped in div 
	$html = '<div class="mig-supp-wrap">';
	
	foreach( $msc as $entry ) {	

		$html .= '<p>';

		$lnk = get_edit_post_link( $entry['src_pid'] );
		
		$tit = get_the_title( $entry['src_pid'] );
		$html .= '<a href="' . $lnk . '">' . $tit .  '</a>';
		
		// escape brackets to prevent shortcode interpretation
		$html .= ' [' . $entry['sc'] . ']';		
		
		// add position to shortcode
		$html .= ' / ' . ($entry['pos'] + 1);
		
		$html .= '</p>';	
	
	}
	
	$html .= '</div>';	
	
	// create migration support post	
	$mp = array(
		'post_type'		=> 'post',				
		'post_author'	=> get_current_user_id(),
		'post_title'	=> __( 'Migration Support Post', 'wpg2mod' ),
		'post_content'	=> $html,
		'post_status'	=> 'draft',
	);

	// do we already have a MigSupport post?
	$mp_id = get_option( $adm->get_key_MigSupID() );
	$wp_err = true;		// create a WPError object on update/insert post
	
	// make sure, that the post exists
	$stat = get_post_status( $mp_id );	
	
	if( empty( $stat ) || $stat == 'trash'  ) {
	
		// insert post
		$mp_id = wp_insert_post( $mp, $wp_err );	

	} else {	
	
		// update the migration support post
		$mp['ID'] = $mp_id;
		$mp_id = wp_update_post( $mp, $wp_err ); 
		
	}

	// error handling
	if ( is_wp_error( $mp_id ) ) {
		$err = $mp_id->get_error_message();
		error_log( "###MigSupp post insert failed. $err ");		
		return false;
	}

	// update mig support post ID in option table
	update_option( $adm->get_key_MigSupID(), $mp_id ); 
		
	return true;
	
} // create_MigSupportPost

/** -------------------------------------------------------------------- *\
 * 	Restore the default settings.
 *
 *	@param	obj		$adm	Plugin Admin object
 *	@return array			[0]: error code
\** -------------------------------------------------------------------- */
function restoreDefSettings( $adm ) {

	// clear the options cache
	wp_cache_delete ( 'alloptions', 'options' );
	
	// read configuration data from plugin base dir
	$conf = include( plugin_dir_path( __DIR__ ) . WPG2MOD_CONFIG );
	
	update_option( $conf['opt_exclude'], $conf['def_exclude'], false );
	update_option( $conf['opt_use_templ'], $conf['def_use_templ'], false );
	update_option( $conf['opt_conv_wpg'], $conf['def_conv_wpg'], false );
	
	// setting array
	$defs = array(
		$conf['opt_exclude']	=> $conf['def_exclude'], 
		$conf['opt_use_templ']	=> $conf['def_use_templ'],
		$conf['opt_conv_wpg']	=> $conf['def_conv_wpg'],
	);

	return array( 0, __('Default settings restored.', 'wpg2mod' ), $defs );

} // restoreDefSettings


/** -------------------------------------------------------------------- *\
 * 	Retrieve MODULA Gallery specific post_meta data.
 *	NOTE: Intended for testing and development only.
 *
 *	@param int $pid			MG post id
 *	@return array			???
\** -------------------------------------------------------------------- */
function doMGalMeta( $pid = 7994 ) {
		
	global $wpdb;
	$sql = "SELECT p.ID AS pid, p.post_title, pm.meta_key, pm.meta_value
			FROM la1_posts p
			JOIN la1_postmeta pm ON pm.post_id = p.ID
			WHERE p.ID = $pid
			AND (pm.meta_key = 'modula-images' OR pm.meta_key = 'modula-settings');";
	
	// do the query
	$recs = $wpdb->get_results( $sql );
	if( $wpdb->num_rows == 0 ) {
		if( $recs === false ) {
			$err = 	$wpdb->last_error;
			error_log( "###DB Error in doMGalMeta - " . __FILE__ . ": $err" );
		}
		
		// exit with error coding
		return array( -1, 'No records found.' );
	}
	
	foreach( $recs as $meta ) {

		$uns = unserialize( $meta->meta_value );
		//dlog( $uns );
	
	}

	return array( 0, count( $recs ) . ' Meta data found.' );

} // doMGalMeta
?>
