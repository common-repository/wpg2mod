<?php
/** -------------------------------------------------------------------- *\
 * 	The frontend specific functionality of WPG2Mod.
 *
 *		+ enqueues scripts & styles
 *		+ (optionally) replaces WP Gallery shortcodes by Modula shortcodes
 *
 *	@since 1.0.1
 *		+ on-the-fly creation of Modula Gallery from WPG shortcode
 *
 * 	@package    	WPG2Mod
 * 	@subpackage 	WPG2Mod/public
 * 	Text Domain:    wpg2mod
 * 	Domain Path:    /languages 
 * 	@license 		GPL-3
 * 	@version		1.0.1
 * 	@author			edobees
 *
 * 	Date: 9. Mar. 2019
\** -------------------------------------------------------------------- */

// include 
$hd = dirname( plugin_dir_path( __FILE__  ) );
require_once $hd . '/admin/wpg2mod-admin-ajax.php';

class WPG2Mod_Front {

	private $plugin_name;
	private $version;
	private $conf;			// configuration data
		
	public function __construct( $conf ) {

		$this->conf = $conf;
		$this->plugin_name = $conf['name'];
		$this->version = $conf['name'];

	} // constructor

	// enqueue frontend (public) stylesheets
	public function enqueue_styles() {

		$handle = $this->plugin_name .'front';	// unique name for style sheet
		$src = plugin_dir_url( __FILE__ ) . 'css/wpg2mod-front.css'; // URL
		$deps = array();				// dependency array
		$ver = $this->version;			// version, needed for cache invalidation
		$media = 'all';					// media query
		
		wp_enqueue_style( $handle, $src, $deps, $ver, $media );		

	} // enqueue_styles

	
	/* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ *\
	 *	Shortcode Addition
	\* +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
	/** -------------------------------------------------------------------- *\
	 * 	Filter WP Gallery shortcodes and (optionally) replace them by 
	 *	Modula Gallery shortcodes.
	 *
	 *	Called as an INIT action hook.
	\** -------------------------------------------------------------------- */
	public function modify_shortcode(){

		$cb = array( $this, 'wpg2mod_filter' );
		add_filter( 'do_shortcode_tag', $cb, 10, 3 );
						
	} // modify_shortcode	
	
	/** -------------------------------------------------------------------- *\
	 * 	Filter WP gallery shortcode, optionally replace by Modula.
	 *	
	 *	The replacemnent of WP Galleries is controlled by the global 
	 *	settings and can be finetuned with the 'mod' attribute.
	 *	Setting 'mod' to 'yes', 'ja', '1', 'true', 'y', 'j' invokes the 
	 *	gallery conversion independent from the global settings.
	 *
	 *	@param		as described by WP codex ...
	\** -------------------------------------------------------------------- */
	function wpg2mod_filter( $output, $tag, $atts ) {

		// only act on 'gallery' shortcodes
		if( $tag != 'gallery' ) {
			return $output;
		}
		
		// get global conversion option
		$opt = get_option( $this->conf['opt_conv_wpg'], $this->conf['def_conv_wpg'] );
		
		// get 'mod' attribute value
		$val = isset( $atts['mod'] ) ? strtolower($atts['mod']) : '';
		$val = trim( $val );
		
		// define the yes values
		$yeshay = array( 'yes', 'ja', '1', 'true', 'y', 'j' );
		$pos = in_array( $val, $yeshay );	
		
		$nohay = array( 'no', 'nein', '0', 'false', 'n' );	
		$no = in_array( $val, $nohay );
		
		if( $pos || ( $opt == true &&  !$no ) ) {

			$html = $this->wpg2modula( $atts );
			if( empty( $html ) ) $html = $output;
			return $html;
					
		}
	
		// just in case:
		return $output;
	
	} // wpg2mod_filter
			
	
	/** -------------------------------------------------------------------- *\
	 * 	Shortcode function replacing WP gallery shortcode by Modula.
	 *
	 * 	@param array $atts		shortcode parameter as provided by post 
	 *							content.
	 *	@return string			HTML formatted output.
	\** -------------------------------------------------------------------- */
	function wpg2modula( $atts ) {		
		
		// get the global post context
		global $post;		

		$err_out = '<p style="color:red;">' . 
					__("Sorry, this WP Gallery could not be converted.", 'wpg2mod' ) . "</p>";
		
		// current post ID
		$pid = $post->ID;
		
		// get image IDs - without white space
		$ids = preg_replace( '/\s+/', '', $atts['ids'] );
		if( empty($ids) ) { // no gallery
			return '';
		}
		
		// do we have associated Modula galleries?
		$res = $this->getMG_ID( $pid );
		
		// get position of this WP gallery
		$pos = $this->getSC_Pos( $ids );
						
		$mid = (-1);
		// get ID of the Modula gallery (if any)
		foreach( $res as $item ) {
			if( $item->pos == $pos ) {
				$mid = $item->id;
			}
		}
		
		// if a suitable Modula gallery is missing, create one
		if( $mid < 0 ) {
			$imgIDs[] = array( 'PID' => $pid, 'ids' => $ids, 'pos' => $pos );
			$msc = create_ModulaGals( $imgIDs );
			// take the first shortocde
			preg_match( '/id="([^"]+)"/' , $msc[0]['sc'], $match );			
			$mid = $match[1];			
		}	
		
		// do Modula shortcode
		$tpl = '<div class="wpg2mod-wrap"><p class="clear"></p>[modula id="%d"]</div>';
		$sc = sprintf( $tpl, $mid );
		return do_shortcode( $sc );		

	} // wpg2modula

	/** -------------------------------------------------------------------- *\
	 * 	Get Modula Gallery ID/POS from source post ID. (Metadata query).
	 *
	 *	NOTE: Looks only at posts having status DRAFT or PUBLISH
	 *
	 * 	@param int $pid		source post ID of original WP Gallery. 
	 *	@return array 		MG IDs and POSs (a source post can have more 
	 *						than one gallery!)
	\** -------------------------------------------------------------------- */
	function getMG_ID( $pid ) {
		
		global $wpdb;
		
		$cpt = $this->conf['mgal_cpt'];		

		$sql = $wpdb->prepare( 			
				"SELECT p.id, pm2.meta_value as pos
				FROM la1_postmeta pm 
				JOIN la1_posts p ON p.ID = pm.post_id
				JOIN la1_postmeta pm2 ON pm2.post_id = p.ID 
				WHERE pm.meta_key = '_misrc' AND pm.meta_value = %d 
				AND p.post_type = '" . $cpt . "' 
				AND (p.post_status = 'draft' OR p.post_status = 'publish')
				AND pm2.meta_key = '_mipos';", $pid );
		
		return $wpdb->get_results( $sql );

	} // getMG_ID

	/** -------------------------------------------------------------------- *\
	 * 	Try to determine WP gallery shortcode (sc) position in post content.
	 *
	 *	Galleries are assumed identical, if they have the same image IDs in 
	 *	the same order. 
	 *
	 * 	@param string $ids		image IDs, comma separated
	 *	@return int 			(potential) position, o-based or false
	\** -------------------------------------------------------------------- */
	function getSC_Pos( $ids_in ) {

		// get rid of potential white space
		$ids = preg_replace( '/\s+/', '', $ids_in );
		if( empty( $ids ) ) {
			return false;
		}
		
		// we are in a post context ...
		global $post;
		$cont = $post->post_content;
		
		$tok = $this->conf['gal_token'];
		
		// look for WP gallery shortcodes in the actual post 
		$pat = '/\\' . $tok . '[\s\S]*?\]/';	// find: '[gall ... ]' not greedy, use *?
		$res = preg_match_all( $pat, $cont, $matches );
		
		// find shortcodes
		$pos = 0;
		foreach( $matches[0] as $sc ) {

			// find image IDs in gallery short code
			// find: 'ids="12,34,56"' being not greedy(*?)
			// brackets () group everything inside the "", 
			// () can be accessed as $img_ids[1]
			$pat = '/ids\s*=\s*\"([\s\S]*?)\"/';	
			$res = preg_match( $pat, $sc, $cand_ids );
			
			// check, if we match the given IDs
			if( !empty( $res ) ) {
				
				// remove white space 
				$cand = preg_replace( '/\s+/', '', $cand_ids[1] );
				
				// check, if we match
				if( $ids == $cand )	{
					return $pos;		// we have the position
				}
					
				$pos++;		
			}		
		}

		// we do not have a match
		if( !empty( 'WP_DEBUG' ) ) error_log( '###No matching shortcode found.');
		return false;

	} // getSC_Pos
	
} // WPG2Mod_Front 
