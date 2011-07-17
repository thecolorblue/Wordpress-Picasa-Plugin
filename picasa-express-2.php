<?php
/*
Plugin Name: Picasa Plugin
Description: Browse, search and select photos from any Picasa Web Album and add them to your post/pages.
Version: 1.0
Author: Brad Davis
Author URI: http://braddavis.cc/

Great thanks to Wott (email: wotttt@gmail.com) for the core code used for this plugin. 

Thank you to Scrawl ( scrawl@psytoy.net ) for plugin Picasa Image Express 2.0 RC2
for main idea and Picasa icons

Copyright 2010 Wott ( email : wotttt@gmail.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

define( 'PE2_VERSION', '1.5.2' );

if (!class_exists("PicasaExpressX2")) {
	class PicasaExpressX2 {

		/**
		 * Define options and default values
		 * @var array
		 */
		var $options = array(
			'pe2_configured'        => false,
			'pe2_icon'              => 2,
			'pe2_roles'        		=> array('administrator'=>1),
			'pe2_level'         	=> 'blog',
			'pe2_user_name'         => 'undefined',

			'pe2_caption'           => 0,
			'pe2_title'             => 1,
			'pe2_link'              => 'thickbox',

			'pe2_img_align'         => 'left',
			'pe2_img_css'           => '',
			'pe2_img_style'         => '',

			'pe2_gal_align'         => 'left',
			'pe2_gal_css'           => '',
			'pe2_gal_style'         => '',

			'pe2_img_sort'          => 0,
			'pe2_img_asc'           => 1,

			'pe2_gal_order'         => 0,

			'pe2_token'				=> '',

			'pe2_save_state'		=> 1,
			'pe2_saved_state'		=> '',
			'pe2_last_album'		=> '',
			'pe2_saved_user_name'	=> '',

			'pe2_large_limit'		=> '',
		);

		/**
		 * plugin URL
		 * @var string
		 */
		var $plugin_URL;

		function PicasaExpressX2() {
			// Hook for plugin de/activation
			if (
				(function_exists('is_multisite') && is_multisite()) || // new version check
				(function_exists('activate_sitewide_plugin'))		   // pre 3.0 version check
			){
				register_activation_hook( __FILE__, array (&$this, 'init_site_options' ) );
				register_deactivation_hook( __FILE__, array (&$this, 'delete_site_options' ) );
			} else {
				register_activation_hook( __FILE__, array (&$this, 'init_options' ) );
				register_deactivation_hook( __FILE__, array (&$this, 'delete_options' ) );
			}

			// Retrieve plugin options from database if plugin configured
			if (!$this->options['pe2_configured']) {
				// get plugin URL
				$this->plugin_URL = WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__));

				foreach ($this->options as $key => $option) {
					$this->options[$key] = get_option($key,$option);
					if (!preg_match('/^[whs]\d+$/',$this->options['pe2_large_limit']))
						$this->options['pe2_large_limit'] = '';
				}

				if ($this->options['pe2_configured']) {
				if (is_admin()) {

					// loading localization if exist
					add_action('init', array(&$this, 'load_textdomain'));

					// Add settings to the plugins management page under
					add_filter('plugin_action_links_'.plugin_basename(__FILE__), array(&$this, 'add_settings_link'));
					// Add a page which will hold the  Options form
					add_action('admin_menu', array(&$this, 'add_settings_page'));
					add_filter('contextual_help', array(&$this, 'contextual_help'), 10 , 2);

					// Add media button to editor
					add_action('media_buttons', array(&$this, 'add_media_button'), 20);
					// Add iframe page creator
					add_action('media_upload_picasa', array(&$this, 'media_upload_picasa'));

					// AJAX request from media_upload_picasa iframe script ( pe2-scripts.js )
					add_action('wp_ajax_pe2_get_gallery', array(&$this, 'get_gallery'));
					add_action('wp_ajax_pe2_get_images', array(&$this, 'get_images'));
					add_action('wp_ajax_pe2_save_state', array(&$this, 'save_state'));

					// Add setting for user profile if capable
					add_action('show_user_profile', array(&$this, 'user_profile'));
					add_action('personal_options_update', array(&$this, 'user_update'));

				} else {

					/* Attach stylesheet in the user page
					 * you can enable attach styles if define some special
					 */
					// add_action('wp_head', array(&$this, 'add_style'));

					add_shortcode('pe2-gallery', array(&$this, 'gallery_shortcode'));
					add_shortcode('clear', array(&$this, 'clear_shortcode'));

					if ($this->options['pe2_footer_link']) {
						add_action('wp_footer', array(&$this, 'add_footer_link'));
					}
				}}
			}

		}

		/**
		 * Walk all blogs and apply $func to every founded
		 *
		 * @global integer $blog_id
		 * @param function $func Function to apply changes to blog
		 */
		function walk_blogs($func) {

			$walk = isset($_GET['networkwide'])||isset($_GET['sitewide']); // (de)activate by command from site admin

			if (function_exists('get_site_option')) {
				$active_sitewide_plugins = (array) maybe_unserialize( get_site_option('active_sitewide_plugins') );
				$walk = $walk || isset($active_sitewide_plugins[plugin_basename(__FILE__)]);
			}

			if ( $walk && function_exists('switch_to_blog')) {

				global $blog_id, $switched_stack, $switched;
				$saved_blog_id = $blog_id;

				global $wpdb;
				$blogs = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs WHERE public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0'");
				if( is_array( $blogs ) ) {
					reset( $blogs );
					foreach ( (array) $blogs as $new_blog_id ) {
						switch_to_blog($new_blog_id);
						$this->$func();
						array_pop( $switched_stack ); // clean
					}
					switch_to_blog($saved_blog_id);
					array_pop( $switched_stack ); // clean
					$switched = ( is_array( $switched_stack ) && count( $switched_stack ) > 0 );
				}
			} else {
				$this->$func();
			}
		}

		function init_site_options() {
			$this->walk_blogs('init_options');
		}
		function delete_site_options() {
			$this->walk_blogs('delete_options');
		}

		/**
		 * Enable plugin configuration and set roles by config
		 */
		function init_options() {
			add_option('pe2_configured',true);

			foreach (get_option('pe2_roles',$this->options['pe2_roles']) as $role=>$data) {
				if ($data) {
					$role = get_role($role);
					$role->add_cap('picasa_dialog');
				}
			}
		}

		/**
		 * Delete plugin configuration flag
		 */
		function delete_options() {
			delete_option('pe2_configured');
		}

		function load_textdomain() {
			if ( function_exists('load_plugin_textdomain') ) {
				load_plugin_textdomain('pe2', false, dirname( plugin_basename( __FILE__ ) ) );
			}
		}

		/**
		 * Echo the link with icon to run plugin dialog
		 *
		 * @param string $id optinal id for link to plugin dialog
		 * @return void
		 */
		function add_media_button($id = '') {

			if (!current_user_can('picasa_dialog')) return;

			$plugin_URL = $this->plugin_URL;
			$icon = $this->options['pe2_icon'];
			// 'type=picasa' => 'media_upload_picasa' action above
			$media_picasa_iframe_src = "media-upload.php?type=picasa&tab=type&TB_iframe=true&width=640&height=566";
			$media_picasa_title = __("Add Picasa image or gallery", 'pe2');
			$put_id = ($id)?"id=\"$id\"":'';

			echo "<a href=\"$media_picasa_iframe_src\" $put_id class=\"thickbox\" title=\"$media_picasa_title\"><img src=\"$plugin_URL/icon_picasa$icon.gif\" alt=\"$media_picasa_title\" /></a>";

		}

		/**
		 * Config scrips and styles and print iframe content for dialog
		 *
		 */
		function media_upload_picasa() {

			if (!current_user_can('picasa_dialog')) return;

			// add script and style for dialog
			add_action('admin_print_styles', array(&$this, 'add_style'));
			add_action('admin_print_scripts', array(&$this, 'add_script'));

			// we do not need default script for media_upload
			wp_deregister_script('swfupload-all');
			wp_deregister_script('swfupload-handlers');
			wp_deregister_script('image-edit');
			wp_deregister_script('set-post-thumbnail' );
			wp_deregister_script('imgareaselect');

			// but still reuse code for make media_upload iframe
			return wp_iframe(array(&$this, 'type_dialog'));
		}

		/**
		 * Attach script and localisation text in dialog
		 * run from action 'admin_print_scripts' from {@link media_upload_picasa()}
		 *
		 * @global object $wp_scripts
		 */
		function add_script() {
			global $wp_scripts;
			$wp_scripts->add('pe2-script', $this->plugin_URL.'/pe2-scripts.js', array('jquery'),PE2_VERSION);
			$options = array(
				'waiting'   => str_replace('%pluginpath%', $this->plugin_URL, __("<img src='%pluginpath%/loading.gif' height='16' width='16' /> Please wait", 'pe2')),
				'env_error' => __("Error: Can not insert image(s) due wrong envirionment\nCheck script media-upload.js in the parent/editor window", 'pe2'),
				'image'     => __('Image', 'pe2'),
				'gallery'   => __('Gallery', 'pe2'),
				'reload'    => __('Reload', 'pe2'),
				'options'   => __('Options', 'pe2'),
				'album'	    => __('Album', 'pe2'),
				'shortcode'	=> __('Shortcode', 'pe2'),
				'thumb_w'   => get_option('thumbnail_size_w'),
				'thumb_h'   => get_option('thumbnail_size_h'),
				'thumb_crop'=> get_option('thumbnail_crop'),
				'uniqid'    => uniqid(''),
				'state'		=> 'albums',
			);
			foreach ( $this->options as $key => $val ) {
                if (!is_array($val)) // skip arrays: pe2_roles
                    $options[$key]=$val;
			}
			if ($this->options['pe2_level'] == 'user') {
				global $current_user;
				$options['pe2_save_state']  = get_user_meta($current_user->data->ID,'pe2_save_state',true);
				$options['pe2_saved_state'] = get_user_meta($current_user->data->ID,'pe2_saved_state',true);
				$options['pe2_last_album']  = get_user_meta($current_user->data->ID,'pe2_last_album',true);
				$options['pe2_saved_user_name']  = get_user_meta($current_user->data->ID,'pe2_saved_user_name',true);
				$options['pe2_user_name']   = get_user_meta($current_user->data->ID,'pe2_user_name',true);
			}

			if ($options['pe2_save_state']) {
				if ($options['pe2_saved_state']) $options['state'] = $options['pe2_saved_state'];
				if ($options['pe2_saved_user_name']) $options['pe2_user_name'] = $options['pe2_saved_user_name'];
			}
			
			$options['pe2_user_name'] = trim($options['pe2_user_name']);
			if (''==$options['pe2_user_name']) $options['pe2_user_name']='undefined';
			if ('undefined'==$options['pe2_user_name']) $options['state']= 'nouser';

			foreach ( $options as $key => $val ) {
					$options[$key] = rawurlencode($val);
			}
			$wp_scripts->localize( 'pe2-script', 'pe2_options', $options );

			$wp_scripts->enqueue('pe2-script');
		}

		/**
		 * Request styles
		 * run by action 'admin_print_styles' from {@link media_upload_picasa()}
		 *
		 * @global boolean $is_IE
		 */
		function add_style() {
			global $is_IE;
			wp_enqueue_style('media');
			wp_enqueue_style('pe2-style', $this->plugin_URL.'/picasa-express-2.css',array(),PE2_VERSION,'all');
			if ($is_IE)
				wp_enqueue_style('pe2-style-ie', $this->plugin_URL.'/picasa-express-2-IE.css',array(),PE2_VERSION,'all');
		}

		/**
		 * Print dialog html
		 * run by parameter in (@link wp_iframe()}
		 *
		 * @global object $current_user
		 */
		function type_dialog() {

			/*
				<a href="#" class="button alignright">Search</a>
				<form><input type="text" class="alignright" value="Search ..."/></form>
			 */
			?>
			<div id="pe2-nouser" class="pe2-header" style="display:none;">
				<input type="text" class="alignleft" value="user name"/>
				<a id="pe2-change-user" href="#" class="button alignleft pe2-space"><?php _e('Change user', 'pe2')?></a>
				<a id="pe2-cu-cancel" href="#" class="button alignleft pe2-space"><?php _e('Cancel', 'pe2')?></a>
				<div id="pe2-message1" class="alignleft"></div>
				<br style="clear:both;"/>
			</div>
			<div id="pe2-albums" class="pe2-header" style="display:none;">
				<a id="pe2-user" href="#" class="button alignleft"></a>
				<div id="pe2-message2" class="alignleft"><?php _e('Select an Album', 'pe2')?></div>
				<a id="pe2-switch2" href="#" class="button alignleft"><?php _e('Album', 'pe2')?></a>
				<a href="#" class="pe2-options button alignright pe2-space" ><?php _e('Options','pe2'); ?></a>
				<a href="#" class="pe2-reload button alignright" ></a>
				<br style="clear:both;"/>
			</div>
			<div id="pe2-images" class="pe2-header" style="display:none;">
				<a id="pe2-album-name" href="#" class="button alignleft"><?php _e('Select an Album', 'pe2')?></a>
				<div id="pe2-message3" class="alignleft"><?php _e('Select images', 'pe2')?></div>

				<a id="pe2-switch" href="#" class="button alignleft"><?php _e('Image', 'pe2')?></a>
				<a id="pe2-insert" href="#" class="button alignleft pe2-space" style="display:none;"><?php _e('Insert', 'pe2')?></a>
				<a href="#" class="pe2-options button alignright pe2-space" ></a>
				<a href="#" class="pe2-reload button alignright" ></a>
				<br style="clear:both;"/>
			</div>
			<div id="pe2-options" style="display:none;">
				<h3><?php _e('Image properties', 'pe2') ?></h3>
				<table class="form-table">
					<?php
					$option = $this->options['pe2_caption'];
					$this->make_settings_row(
						__('Display caption', 'pe2'),
						'<label><input type="checkbox" name="pe2_caption" value="1" '.checked($option,'1',false).' /> '.__('Show the caption under thumbnail image', 'pe2').'</label> '
					);

					$option = $this->options['pe2_title'];
					$this->make_settings_row(
						__('Add caption as title', 'pe2'),
						'<label><input type="checkbox" name="pe2_title" value="1" '.checked($option,'1',false).' /> '.__('Show the caption by mouse hover tip', 'pe2').'</label> '
					);

					$opts = array (
						'none'     => __('No link', 'pe2'),
						'direct'   => __('Direct link', 'pe2'),
						'picasa'   => __('Link to Picasa Web Album', 'pe2'),
						'lightbox' => __('Lightbox', 'pe2'),
						'thickbox' => __('Thickbox', 'pe2'),
						'highslide'=> __('Highslide', 'pe2'),
					);
					$is_gallery = array (
						'none'     => 'false',
						'direct'   => 'false',
						'picasa'   => 'false',
						'lightbox' => 'true',
						'thickbox' => 'true',
						'highslide'=> 'true',
					);
					$is_gallery_js = 'var is_gallery = { ';
					foreach ($is_gallery as $key=>$val) {
						$is_gallery_js .= "$key:$val,";
					}
					$is_gallery_js = trim($is_gallery_js, ',').' };';
					?>
					<script type="text/javascript">
					function handle_gallery_properties(t) {
						<?php echo $is_gallery_js; ?>

						if (is_gallery[t]) {
							jQuery('#gallery_properties').show();
							jQuery('#gallery-message').show();
							jQuery('#nogallery_properties').hide();
						} else {
							jQuery('#gallery_properties').hide();
							jQuery('#gallery-message').hide();
							jQuery('#nogallery_properties').show();
						}
					}
					</script>
					<?php

					$out = '<select name="pe2_link" onchange="handle_gallery_properties(this.value);">';
					$option = $this->options['pe2_link'];
					foreach ($opts as $key => $val ) {
						$out .= "<option value=\"$key\" ".selected($option, $key, false ).">$val</option>";
					}
					$out .= '</select>';
					$this->make_settings_row(
						__('Link to larger image', 'pe2'),
						$out,
						__('To use external libraries like Thickbox, Lightbox or Highslide you need to install and integrate the library independently','pe2'),
						'',
						'id="gallery-message" style="display:'.(($is_gallery[$option]=='true') ? 'block' : 'none').';"'
					);

					$opts = array (
						'none'   => __('None'),
						'left'   => __('Left'),
						'center' => __('Center'),
						'right'  => __('Right'),
					);
					$option = $this->options['pe2_img_align'];
					$out = '';
					foreach ($opts as $key => $val ) {
						$out .= "<input type=\"radio\" name=\"pe2_img_align\" id=\"img-align$key\" value=\"$key\" ".checked($option, $key, false)." /> ";
						$out .= "<label for=\"img-align$key\" style=\"padding-left:22px;margin-right:13px;\" class=\"image-align-$key-label\">$val</label>";
					}
					$this->make_settings_row(
						__('Image alignment', 'pe2'),
						$out
					);

					$this->make_settings_row(
						__('CSS Class', 'pe2'),
						'<input type="text" name="pe2_img_css" class="regular-text" value="'.esc_attr($this->options['pe2_img_css']).'"/>',
						__("You can define default class for images from theme's style.css", 'pe2')
					);
					$this->make_settings_row(
						__('Style', 'pe2'),
						'<input type="text" name="pe2_img_style" class="regular-text" value="'.esc_attr($this->options['pe2_img_style']).'"/>',
						__('You can hardcode some css attributes', 'pe2')
					);

					$this->make_settings_row(
						__('Thumbnail size'),
						'<label for="thumbnail_size_w">'.__('Width').'</label> '.
						'<input name="thumb_w" type="text" id="thumbnail_size_w" value="'.esc_attr( get_option('thumbnail_size_w')).'" class="small-text" />&nbsp;&nbsp;&nbsp;'.
						'<label for="thumbnail_size_h">'.__('Height').'</label> '.
						'<input name="thumb_h" type="text" id="thumbnail_size_h" value="'.esc_attr( get_option('thumbnail_size_h')).'" class="small-text" /><br />'.
						'<input name="thumb_crop" type="checkbox" id="thumbnail_crop" value="1" '.checked('1', get_option('thumbnail_crop'),false).'/> '.
						'<label for="thumbnail_crop">'.__('Crop thumbnail to exact dimensions (normally thumbnails are proportional)').'</label>'
					);
					?>
				</table>

				<h3><?php _e('Gallery properties', 'pe2') ?></h3>

			<div id="nogallery_properties" style="<?php echo ($is_gallery[$this->options['pe2_link']]=='true') ? 'display:none;' : 'display:block;'?>">
				<p><?php _e('To view and change properties you have to select Thickbox, Lightbox or Highslide support for the images above', 'pe2') ?></p>
			</div>
			<div id="gallery_properties" style="<?php echo ($is_gallery[$this->options['pe2_link']]=='false') ? 'display:none;' : 'display:block;'?>">

				<table class="form-table">
					<?php
					$option = $this->options['pe2_gal_align'];
					$out = '';
					foreach ($opts as $key => $val ) {
						$out .= "<input type=\"radio\" name=\"pe2_gal_align\" id=\"gal-align$key\" value=\"$key\" ".checked($option, $key, false)." /> ";
						$out .= "<label for=\"gal-align$key\" style=\"padding-left:22px;margin-right:13px;\" class=\"image-align-$key-label\">$val</label>";
					}
					$this->make_settings_row(
						__('Gallery alignment', 'pe2'),
						$out
					);

					$this->make_settings_row(
						__('CSS Class', 'pe2'),
						'<input type="text" name="pe2_gal_css" class="regular-text" value="'.esc_attr($this->options['pe2_gal_css']).'"/>',
						__("You can define default class for images from theme's style.css", 'pe2')
					);
					$this->make_settings_row(
						__('Style', 'pe2'),
						'<input type="text" name="pe2_gal_style" class="regular-text" value="'.esc_attr($this->options['pe2_gal_style']).'"/>',
						__('You can hardcode some css attributes', 'pe2')
					);
					?>

				</table>
			</div>

			</div>
			<div id="pe2-main">
			</div>
		<?php
		}

		/**
		 * Request server with token if defined
		 *
		 * @param string $url URL for request data
		 * @param boolean $token use token from settings
		 * @return string received data
		 */
		function get_feed($url,$token=false) {
			global $wp_version;
			// add Auth later
			$options = array(
				'timeout' => 30 ,
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ),
				'sslverify' => false // prevent some problems with Google in token request
			);

			if (!$token) {
				if ($this->options['pe2_level'] == 'user') {
					global $current_user;
					$token = get_user_meta($current_user->data->ID,'pe2_token',true);
				} else {
					$token  = $this->options['pe2_token'];
				}
			}
			if ($token) $options['headers'] = array ( 'Authorization' =>"AuthSub token=\"$token\"" );

			$response = wp_remote_get($url, $options);

			if ( is_wp_error( $response ) )
				return $response;

			if ( 200 != $response['response']['code'] )
				return new WP_Error('http_request_failed', __('Response code is ').$response['response']['code']);

			// preg sensitive for \n\n, but we not need any formating inside
			return (str_replace("\n",'',trim( $response['body'] )));
		}

		/**
		 * Find tag in content by attribute
		 *
		 * @param string $content
		 * @param string $tag
		 * @param string $attr
		 * @return string attribute value or all parameters if not found. false if no tag found
		 */
		function get_item_attr($content,$tag,$attr) {
			if (!preg_match("|<$tag\s+([^>]+)/?>|u",$content,$m))
				return false;
			$a = preg_split("/[\s=]/",$m[1]);
			for ($i=0; $i<count($a); $i+=2) {
				if ($a[$i]==$attr) return trim($a[$i+1],"'\" ");
			}
			return join(',',$a);
		}
		/**
		 * Find tag in content
		 *
		 * @param string $content
		 * @param string $tag
		 * @param boolean $first Search only first. False by default
		 * @return bool|string|array content of the found node. false if not found
		 */
		function get_item($content,$tag,$first=false) {
			if (!preg_match_all("|<$tag(?:\s[^>]+)?>(.+?)</$tag>|u",$content,$m,PREG_PATTERN_ORDER))
				return false;
//			echo "$tag: ".count($m[1])."<br/>";
			if (count($m[1])>1 && !$first) return ($m[1]);
			else return ($m[1][0]);
		}

		/**
		 * wp_ajax_pe2_get_gallery
		 * print html for gallery
		 *
		 */
		function get_gallery() {

			if (!current_user_can('picasa_dialog')) {
				echo json_encode((object) array('error'=>__('Insufficient privelegies','pe2')));
				die();
			}

			$out = (object)array();

			if (isset($_POST['user'])) {
				$user = $_POST['user'];
			} else die();

			$rss = $this->get_feed("http://picasaweb.google.com/data/feed/base/user/$user?alt=rss&kind=album&hl=en_US");
			if (is_wp_error($rss)) {
				$out->error = $rss->get_error_message();
			} else if (!$this->get_item($rss,'atom:id')) {
				$out->error = __('Invalid picasa username: ', 'pe2').$user;
		    } else {
			    $items = $this->get_item($rss,'item');
			    $output = '';
			    $insert_h = (get_option('thumbnail_crop')) ? "height='144' " : '';
			    if ($items) {
			    	if (!is_array($items)) $items = array($items);
			    	$output .= "\n<table><tr>\n";
			    	$i = 0;
					foreach($items as $item) {
						// http://picasaweb.google.com/data/entry/base/user/wotttt/albumid/5408701349107410241?alt=rss&amp;hl=en_US
						$guid  = base64_encode(str_replace("entry","feed",$this->get_item($item,'guid'))."&kind=photo");
						$title = $this->escape($this->get_item($item,'title'));
						$desc  = $this->escape($this->get_item($item,'media:description'));
						$url   = $this->get_item_attr($item,'media:thumbnail','url');
						$output .= "<td><a href='#$guid'><img src='$url' alt='$desc' width='144' $insert_h/><span>$title</span></a></td>\n";
						if ($i++%4==3) $output .= "</tr><tr>\n";
					}
					$output .= "</tr></table>\n";
			    }

			    $out->items = $this->get_item($rss,'openSearch:totalResults');
			    $out->title = $this->get_item($rss,'title',true);
			    $out->data  = $output;
			    $out->cache  = $_POST['cache'];
		    }

			echo json_encode($out);
			die();
		}

		/**
		 * wp_ajax_pe2_get_images
		 * print html for images
		 *
		 */
		function get_images() {

			if (!current_user_can('picasa_dialog')) {
				echo json_encode((object) array('error'=>__('Insufficient privelegies','pe2')));
				die();
			}

			$out = (object)array();

			if (isset($_POST['guid'])) {
				$guid = base64_decode($_POST['guid']);
			} else die();

			$rss = $this->get_feed($guid);
			if (is_wp_error($rss)) {
				$out->error = $rss->get_error_message();
			} else if (!$this->get_item($rss,'atom:id')) {
				$out->error = __('Invalid album ', 'pe2');
		    } else {
			    $items = $this->get_item($rss,'item');
		    	$output = '';
		    	$insert_h = (get_option('thumbnail_crop')) ? "height='144' " : '';
		    	$key = 1; $images = array();
		    	$sort = $this->options['pe2_img_sort'];
			    if ($items) {
			    	if (!is_array($items)) $items = array($items);
			    	foreach($items as $item) {
			    		switch ($sort) {
			    			case 0: $key++; break;
			    			case 1: $key = strtotime($this->get_item($item,'pubDate',true)); break;
			    			case 2: $key = $this->get_item($item,'title',true); break;
			    			case 3: $key = $this->get_item($item,'media:title',true); break;
			    		}
			    		$images[$key] = array (
						'guid'  => $this->get_item($item,'link'), // picasa album image
						'title' => $this->escape($this->get_item($item,'title')),
						'file'  => $this->escape($this->get_item($item,'media:title')),
						'desc'  => $this->escape($this->get_item($item,'media:description')),
						'url'   => str_replace('s72','w144',$this->get_item_attr($item,'media:thumbnail','url')),
			    		);
			    	}
			    	if ($this->options['pe2_img_asc']) ksort($images);
			    	else krsort($images);
			    	$output .= "\n<table><tr>\n";
			    	$i = 0;
			    	foreach($images as $item) {
						$output .= "<td><a href='{$item['guid']}'><img src='{$item['url']}' alt='{$item['file']}' title='{$item['desc']}' width='144' $insert_h/><span>{$item['title']}</span></a></td>\n";
						if ($i++%4==3) $output .= "</tr><tr>\n";
					}
					$output .= "</tr></table>\n";
			    }
			    $out->items = $this->get_item($rss,'openSearch:totalResults');
			    $out->title = $this->get_item($rss,'title',true);
				$out->data  = $output;
			    $out->cache  = $_POST['cache'];
		    }

			echo json_encode($out);
			die();

		}

		/**
		 * Escape quotes to html entinty
		 *
		 * @param <type> $str
		 * @return <type>
		 */
		function escape($str) {
			$str = preg_replace('/"/', '&quot;', $str);
			$str = preg_replace("/'/", '&#039;', $str);
			return $str;
		}

		/**
		 * wp_ajax_pe2_save_state
		 * save state of dialog
		 */
		function save_state() {
			if (!current_user_can('picasa_dialog')) {
				echo json_encode((object) array('error'=>__('Insufficient privelegies','pe2')));
				die();
			}

			if (!isset($_POST['state'])) die();
			global $current_user;

			switch ( $saved_state = sanitize_text_field($_POST['state']) ) {
				case 'nouser' :
				case 'albums' :
					if ($this->options['pe2_level'] == 'user')
						update_user_meta($current_user->data->ID, 'pe2_saved_user_name', sanitize_text_field($_POST['last_request']) );
					else
						update_option( 'pe2_saved_user_name', sanitize_text_field($_POST['last_request']) );
					break;
				case 'images' :
					if ($this->options['pe2_level'] == 'user')
						update_user_meta($current_user->data->ID, 'pe2_last_album', sanitize_text_field($_POST['last_request']) );
					else
						update_option( 'pe2_last_album', sanitize_text_field($_POST['last_request']) );
					break;
				default:
					die();
			}
			if ($this->options['pe2_level'] == 'user')
				update_user_meta($current_user->data->ID, 'pe2_saved_state', $saved_state );
			else
				update_option( 'pe2_saved_state', $saved_state );
			die();
		}

		/**
		 * Envelope content with tag
		 * used by shortcode 'pe2_gallery'
		 *
		 * @param array $atts tag, class and style defined. album also
		 * @param string $content
		 * @return string
		 */
		function gallery_shortcode($atts, $content) {
			extract(shortcode_atts(array(
				'class' => '',
				'style' => '',
				'tag'   => 'div',
				'album' => '',
				'thumb_w' => get_option('thumbnail_size_w'),
				'thumb_h' => get_option('thumbnail_size_h'),
				'thumb_crop' => get_option('thumbnail_crop'),
				'large_size' => get_option('pe2_large_limit'),
			), $atts ));

			if ($album) {
				// request images for album
				$rss = $this->get_feed(base64_decode($album));
				if (is_wp_error($rss)) {
					$content = $rss->get_error_message();
				} else if ($this->get_item($rss,'atom:id')) {
					$items = $this->get_item($rss,'item');
					$output = '';
					$uniqid = uniqid('');

					// prepare common image attributes
					$iclass = array($this->options['pe2_img_css']);
					$istyle = array($this->options['pe2_img_style']);

					// create align vars
					// for caption - align="alignclass" including alignnone also
					// else add alignclass to iclass
					$calign = '';
					if ($this->options['pe2_caption']) {
						$calign = 'align="align'.$this->options['pe2_img_align'].'" ';
					} else {
						array_push($iclass,'align'.$this->options['pe2_img_align']);
					}

					// check thumb setting and define width or height or both
					$idimen   = array(
						($thumb_w)?('width="'.$thumb_w.'"'):'',
						($thumb_h)?('height="'.$thumb_h.'"'):''
					);
					if (!$thumb_crop && $idimen[0]) unset($idimen[1]);
					// new size for thumbnail
					$new_thumb_size = '';
					if ($thumb_w && $thumb_h) {
						// both sizes and crop
						if ($thumb_w == $thumb_h) {
							if ($thumb_crop) $new_thumb_size = '/s'.$thumb_w.'-c';
							else $new_thumb_size = '/s'.$thumb_w;
						}
						else if ($thumb_w > $thumb_h) $new_thumb_size = '/w'.$thumb_w;
						else $new_thumb_size = '/h'.$thumb_h;
					}
					else if ($thumb_w) $new_thumb_size = '/w'.$thumb_w;
					else if ($thumb_h) $new_thumb_size = '/h'.$thumb_h;
					// new size for large image
					$new_large_size='';
					if ($large_size) $new_large_size = '/'.$large_size;

					$cdim  = ($thumb_w)?('width="'.$thumb_w.'" '):'';

					// link and gallery additions
					$amore='';
					switch ($this->options['pe2_link']) {
						case 'thickbox':
							$amore = 'class="thickbox" ';
							if (true) $amore .= 'rel="'.$uniqid.'" ';
							break;
						case 'lightbox':
							$amore = (true)?('rel="lightbox-'.$uniqid.'" '):'rel="lightbox" ';
							break;
						case 'highslide':
							$amore = (true)?('class="highslide" onclick="return hs.expand(this,{ slideshowGroup: \''.$uniqid.'\' })"'):
								'class="highslide" onclick="return hs.expand(this)"';
							break;
					}

					$iclass = implode(' ',array_diff($iclass,array(''))); $iclass = ($iclass)?('class="'.$iclass.'" '):'';
					$istyle = implode(' ',array_diff($istyle,array(''))); $istyle = ($istyle)?('style="'.$istyle.'" '):'';
					$idimen = implode(' ',array_diff($idimen,array(''))); $idimen = ($idimen)?($idimen.' '):'';
					
					$key = 1; $images = array();
					$sort = $this->options['pe2_img_sort'];
					if ($items) {
						if (!is_array($items)) $items = array($items);
						foreach($items as $item) {
							switch ($sort) {
								case 0: $key++; break;
								case 1: $key = strtotime($this->get_item($item,'pubDate',true)); break;
								case 2: $key = $this->get_item($item,'title',true); break;
								case 3: $key = $this->get_item($item,'media:title',true); break;
							}
							$url = $this->get_item_attr($item,'media:thumbnail','url');
							$title = $this->escape($this->get_item($item,'title'));
							$images[$key] = array (
							'ialbum'   => $this->get_item($item,'link'), // picasa album image
							'icaption' => $title,
							'ialt'     => $this->escape($this->get_item($item,'media:title')),
							'isrc'     => str_replace('/s72',$new_thumb_size,$url),
							'iorig'    => str_replace('/s72',$new_large_size,$url),
							'ititle'   => ($this->options['pe2_title'])?'title="'.$title.'" ':'',
							);
						}
						if ($this->options['pe2_img_asc']) ksort($images);
						else krsort($images);
						
						foreach($images as $item) {
							$img = "<img src=\"{$item['isrc']}\" alt=\"{$item['ialt']}\" {$item['ititle']}{$iclass}{$istyle}{$idimen} />";

							if ($this->options['pe2_link'] != 'none') {
								if ($this->options['pe2_link'] == 'picasa') $item['iorig'] = $item['ialbum'];
								$img = "<a href=\"{$item['iorig']}\" {$item['ititle']}{$amore}>$img</a>";
							}
							if ($this->options['pe2_caption']) {
								// add caption
								$img = "[caption id=\"\" {$calign}{$cdim}caption=\"{$item['icaption']}\"]{$img}[/caption]";
							}

							$output .= $img;
						}

						$class = implode(' ',
							array_diff(array(
								$class,
								$this->options['pe2_gal_css'],
								($this->options['pe2_gal_align']!='none')?'align'.$this->options['pe2_gal_align']:''
							),array('')));

						$style = ($style)?$style:$this->options['pe2_gal_style'];

					}
				}
				$content .= $output;
			}

			$properties = '';
			if ($class) $properties .= "class='".esc_attr($class)."' ";
			if ($style) $properties .= "style='".esc_attr($style)."' ";

			$code = "<$tag $properties>".do_shortcode($content)."</$tag><div class='clear'></div>";

			return $code;
		}

		/**
		 * Envelope content with tag with additinoal class 'clear'
		 * used by shortcode 'clear'
		 *
		 * @param array $atts tag and class
		 * @param string $content
		 * @return string
		 */
		function clear_shortcode($atts, $content) {
			extract(shortcode_atts(array(
				'class' => '',
				'tag'   => 'div',
			), $atts ));

			$class .= (($class)?' ':'').'clear';

			$code = "<$tag class='$class'>".do_shortcode($content)."</$tag>";

			return $code;
		}

		/**
		 * Print and request user for Picasa in profile. Token link present
		 * uses if settings in user level
		 * run by action 'show_user_profile' from user-edit.php
		 *
		 * @param object $user
		 */
		function user_profile($user) {

			if (!current_user_can('picasa_dialog')) return;
			if ($this->options['pe2_level'] != 'user') return;

			$user_id = $user->ID;

			if ( isset($_GET['revoke']) ) {
				$response = $this->get_feed("https://www.google.com/accounts/AuthSubRevokeToken");
				if ( is_wp_error( $response ) ) {
					$message = __('Google return error: ','pe2').$response->get_error_message();
				} else {
					$message = __('Private access revoked','pe2');
				}
				delete_user_meta($user_id,'pe2_token');
				$this->options['pe2_token'] = '';
			}

			if ( isset($_GET['message']) && $_GET['message']) {
				$message = esc_html(stripcslashes($_GET['message']));
			}


			if (!get_user_meta($user_id,'pe2_user_name',true) && current_user_can('manage_options') ) {
				update_user_meta($user_id,'pe2_user_name',$this->options['pe2_user_name']);
				if ($this->options['pe2_token'])
					update_user_meta($user_id,'pe2_token',$this->options['pe2_token']);
			} 

			?>
				<h3><?php _e('Picasa access', 'pe2') ?></h3>

				<?php
					if ($message) {
						echo '<div id="picasa-express-x2-message" class="updated"><p><strong>'.$message.'</strong></p></div>';
					}
				?>

				<table class="form-table">
					<?php
					$user = get_user_meta($user_id,'pe2_user_name',true);
					$result = 'ok';
					$response = $this->get_feed("http://picasaweb.google.com/data/feed/base/user/$user?alt=rss&kind=album&hl=en_US");
					if ( is_wp_error( $response ) )
						$result = 'nok: '.$response->get_error_message();
				    else if (!$this->get_item($response,'atom:id')) {
						$result = 'nok: wrong answer';
					}

					$ta = array(); $transports = WP_Http::_getTransport(array());
					foreach ($transports as $t) $ta[] = strtolower(str_replace('WP_Http_','',get_class($t)));
					if ($ta) $result = sprintf(__("checking user: %s transport: %s",'pe2'),$result,implode(',',$ta));

					$this->make_settings_row(
						__('Picasa user name', 'pe2'),
						'<input type="text" class="regular-text" name="pe2_user_name" value="'.esc_attr($user).'" />'.$result.
						((!get_user_meta($user_id,'pe2_token',true))?'<br /><a href="https://www.google.com/accounts/AuthSubRequest?next='.urlencode(WP_PLUGIN_URL.'/'.plugin_basename(__FILE__).'?authorize&user='.$user_id).'&scope=http%3A%2F%2Fpicasaweb.google.com%2Fdata%2F&session=1&secure=0">'.__('Requesting access to private albums', 'pe2').'</a>':'<br/><a href="?revoke">'.__('Revoke access to private albums', 'pe2').'</a>'),
						((get_user_meta($user_id,'pe2_token',true))?__('You already received the access to private albums', 'pe2'):__('By this link you will be redirected to the Google authorization page. Please, use same name as above to login before accept.', 'pe2'))
					);
					$option = get_user_meta($user_id,'pe2_save_state',true);
					$this->make_settings_row(
						__('Save last state', 'pe2'),
						'<label><input type="checkbox" name="pe2_save_state" value="1" '.checked($option,'1',false).' /> '.__('Save last state in dialog', 'pe2').'</label> ',
						__('Save user when changes, album if you insert images or albums list if you shorcode for album', 'pe2')
					);
					?>
				</table>
			<?php
		}

		/**
		 * Save parameters and save profile
		 * by action 'personal_options_update' in user-edit.php
		 */
		function user_update() {

			if (!current_user_can('picasa_dialog')) return;

			$user_id = sanitize_text_field($_POST['user_id']);
			if ($user_id && sanitize_text_field($_POST['pe2_user_name']) != get_user_meta($user_id,'pe2_user_name',true)) {
				$picasa_user = sanitize_text_field($_POST['pe2_user_name']);
				if (!$picasa_user) $picasa_user='undefined';
				update_user_meta($user_id,'pe2_user_name', $picasa_user);
				delete_user_meta($user_id,'pe2_token');
			}
			update_user_meta($user_id,'pe2_save_state', ($_POST['pe2_save_state']?'1':'0'));
		}

		/**
		 * Add setting link to plugin action
		 * run by action 'plugin_action_links_*'
		 *
		 */
		function add_settings_link($links) {
			if (!current_user_can('manage_options')) return $links;
			$settings_link = '<a href="options-general.php?page=picasa-express-2">'.__('Settings', 'pe2').'</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		/**
		 * Config settings, add actions for registry setting and add styles
		 * run by action 'admin_menu'
		 *
		 */
		function add_settings_page() {
			if (!current_user_can('manage_options')) return;
			add_options_page(__('Picasa Express x2', 'pe2'), __('Picasa Express x2', 'pe2'), 'manage_options', 'picasa-express-2', array(&$this, 'settings_form'));
			add_action('admin_init', array(&$this, 'settings_reg'));
			add_action('admin_print_styles-settings_page_picasa-express-2', array(&$this, 'settings_style'));
		}

		/**
		 * Register all option for save
		 *
		 */
		function settings_reg() {
			foreach ($this->options as $key => $option) {
				if ($key != 'pe2_token') // skip token in non secure requests
					register_setting( 'picasa-express-2', $key );
			}
		}

		/**
		 * Define misseed style for setting page
		 */
		function settings_style() {
			$images = admin_url('images');
			echo<<<STYLE
			<style type="text/css" id="pe2-media" name="pe2-media">
				.image-align-none-label {
					background: url($images/align-none.png) no-repeat center left;
				}
				.image-align-left-label {
					background: url($images/align-left.png) no-repeat center left;
				}
				.image-align-center-label {
					background: url($images/align-center.png) no-repeat center left;
				}
				.image-align-right-label {
					background: url($images/align-right.png) no-repeat center left;
				}
			</style>
STYLE;
		}

		/**
		 * Add help to the top of the setting page
		 */
		function contextual_help($help, $screen) {
			if ( 'settings_page_picasa-express-2' == $screen ) {
				$homepage = __('Plugin homepage','pe2');
				$messages = array(
					__('To receive access for private album press link under username. You will be redirected to Google for grant access. If you press "Grant access" button you will be returned to settings page, but access will be granted.','pe2'),
					__("In the album's images you have to press button with 'Image' button. The 'Gallery' will appear on the button and you can select several images. This can be happen if you use Thickbox, Lightbox or Highslide support.",'pe2'),
					__("By default images inserted in the displayed order. If you need control the order in gallery - enable 'Selection order'.", 'pe2'),
					__('To use external libraries like Thickbox, Lightbox or Highslide you need to install and integrate the library independently','pe2'),
					);
				$message = '<p>'.implode('</p><p>',$messages).'</p>';
				$help .= <<<HELP_TEXT
				<h5>Small help</h5>
				$message
				<div class="metabox-prefs">
					<a href="http://wott.info/picasa-express">$homepage</a>
				</div>
HELP_TEXT;
			}
			return $help;
		}

		/**
		 * Make the row from parameters for setting tables
		 */
		function make_settings_row($title, $content, $description='', $title_pars='', $description_pars='') {
			?>
					<tr valign="top" <?php echo $title_pars; ?>>
			        <th scope="row"><?php echo $title; ?></th>
			        <td>
						<?php echo $content; ?>
			        	<br />
			        	<span class="description" <?php echo $description_pars; ?>><?php echo $description; ?></span>
			        </td>
			        </tr>
			<?php
		}

		/**
		 * Show the main settings form
		 */
		function settings_form(){

			if ( isset($_GET['updated']) && 'true' == $_GET['updated'] ) {
				// change 'picasa_dialog' capability to new role
				global $wp_roles;
				if ( ! isset( $wp_roles ) )	$wp_roles = new WP_Roles();

				foreach ( $wp_roles->roles as $role => $data) {
					if (isset($data['capabilities']['picasa_dialog']))
						unset( $wp_roles->roles[$role]['capabilities']['picasa_dialog'] );
				}

				foreach ( $this->options['pe2_roles'] as $role => $data) {
					if ($data) $wp_roles->add_cap($role,'picasa_dialog');
				}

			}

			if ( isset($_GET['revoke']) ) {
				$response = $this->get_feed("https://www.google.com/accounts/AuthSubRevokeToken");
				if ( is_wp_error( $response ) ) {
					$message = __('Google return error: ','pe2').$response->get_error_message();
				} else {
					$message = __('Private access revoked','pe2');
				}
				delete_option('pe2_token');
				$this->options['pe2_token'] = '';
			}

			if ( isset($_GET['message']) && $_GET['message']) {
				$message = esc_html(stripcslashes($_GET['message']));
			}

			?>

			<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div>
			<h2><?php _e('Picasa Express x2 settings', 'pe2')?></h2>

			<?php
				if (isset($message) && $message) {
					echo '<div id="picasa-express-x2-message" class="updated"><p><strong>'.$message.'</strong></p></div>';
				}
			?>

			<form method="post" action="options.php">
    			<?php settings_fields( 'picasa-express-2' ); ?>

				<input type="hidden" name="pe2_configured" value="1" />

				<h3><?php _e('Picasa access', 'pe2') ?></h3>
				<table class="form-table">

					<?php 
					$option = $this->options['pe2_roles'];
					$editable_roles = get_editable_roles();

					$out = '';
					foreach( $editable_roles as $role => $details ) {
						$name = translate_user_role($details['name'] );
						$out .= "<label><input name=\"pe2_roles[$role]\" type=\"checkbox\" value=\"1\" ".checked(isset($option[$role]),true,false)."/>$name</label><br/>";
					}
					
					$this->make_settings_row(
						__('Assign capability to Roles', 'pe2'),
						$out,
						__('Roles for users who can use Picasa albums access via plugin', 'pe2')
					);

					$option = $this->options['pe2_level'];
					
					$this->make_settings_row(
						__('Picasa access level', 'pe2'),
						'<label><input type="radio" name="pe2_level" value="blog" '.checked($option,'blog',false).' onclick="jQuery(\'.picasa-site-user\').show();" />'.__('Blog').'</label> '.
			        	'<label><input type="radio" name="pe2_level" value="user" '.checked($option,'user',false).' onclick="jQuery(\'.picasa-site-user\').hide();" />'.__('User').'</label> ',
						__('Picasa user name ( including private album access ) defined for whole blog or for every user individual', 'pe2')
					);

					?>

				</table>

				<h3><?php _e('Display properties', 'pe2') ?></h3>
				<table class="form-table">

					<?php
					$user = $this->options['pe2_user_name'];

					if ('blog'==$this->options['pe2_level'] && $user) {
						$result = 'ok';
						$response = $this->get_feed("http://picasaweb.google.com/data/feed/base/user/$user?alt=rss&kind=album&hl=en_US");
						if ( is_wp_error( $response ) )
							$result = 'nok: '.$response->get_error_message();
						else if (!$this->get_item($response,'atom:id')) {
							$result = 'nok: wrong answer';
						}

						if (method_exists('WP_Http', '_getTransport')) {
							$ta = array(); $transports = WP_Http::_getTransport(array());
							foreach ($transports as $t) $ta[] = strtolower(str_replace('WP_Http_','',get_class($t)));
							if ($ta) $result = sprintf(__("checking user: %s transport: %s",'pe2'),$result,implode(',',$ta));
						} else if (method_exists('WP_Http', '_get_first_available_transport')) {
							$transport = WP_Http::_get_first_available_transport(array());
							if ($transport) {
								$transport_name = strtolower(str_replace('WP_HTTP_','',$transport));
								$result = sprintf(__("checking user: %s transport: %s",'pe2'),$result,$transport_name);
							}
							
						}
					} else $result='';

					$this->make_settings_row(
						__('Picasa user name for site', 'pe2'),
						'<input type="text" class="regular-text" name="pe2_user_name" value="'.esc_attr($user).'" />'.$result.
						((!$this->options['pe2_token'])?'<br /><a href="https://www.google.com/accounts/AuthSubRequest?next='.urlencode(WP_PLUGIN_URL.'/'.plugin_basename(__FILE__).'?authorize').'&scope=http%3A%2F%2Fpicasaweb.google.com%2Fdata%2F&session=1&secure=0">'.__('Requesting access to private albums', 'pe2').'</a>':'<br/><a href="?page=picasa-express-2&revoke">'.__('Revoke access to private albums', 'pe2').'</a>'),
						(($this->options['pe2_token'])?__('You receive the access to private albums.', 'pe2'):__('By this link you will be redirected to the Google authorization page. Please, use same name as above to login before accept.', 'pe2')),
						'class="picasa-site-user" style="display:'.(('blog'==$this->options['pe2_level'])?'table-row':'none').'"'
					);

					$option = $this->options['pe2_save_state'];
					$this->make_settings_row(
						__('Save last state', 'pe2'),
						'<label><input type="checkbox" name="pe2_save_state" value="1" '.checked($option,'1',false).' /> '.__('Save last state in dialog', 'pe2').'</label> ',
						__('Save user when changes, album if you insert images or albums list if you shorcode for album', 'pe2'),
						'class="picasa-site-user" style="display:'.(('blog'==$this->options['pe2_level'])?'table-row':'none').'"'
					);

/*					$opts = array(
						1 => __('Picasa squire icon', 'pe2'),
						2 => __('Picasa squire grayscale icon', 'pe2'),
						3 => __('Picasa round icon', 'pe2'),
						4 => __('Picasa round grayscale icon', 'pe2'),
					);
					$option = $this->options['pe2_icon'];
					$out = '';
					foreach ($opts as $i=>$text) {
						$out .= '<label>';
						$out .= "<input type=\"radio\" name=\"pe2_icon\" value=\"$i\" ".checked($option,$i,false)." />";
						$out .= "<img src=\"{$this->plugin_URL}/icon_picasa$i.gif\" alt=\"$text\" title=\"$text\"/> ";
						$out .= '</label>';
			        	}  

					$this->make_settings_row(
						__('Picasa icon', 'pe2'),
						$out,
						__('This icon marks the dialog activation link in the edit post page', 'pe2')
					); */

					$opts = array(
						0 => __('None', 'pe2'),
						1 => __('Date', 'pe2'),
						2 => __('Title', 'pe2'),
						3 => __('File name', 'pe2'),
					);
					$option = $this->options['pe2_img_sort'];
					$out = '';
					foreach ($opts as $i=>$text) {
						$out .= '<label>';
						$out .= "<input type=\"radio\" name=\"pe2_img_sort\" value=\"$i\" ".checked($option,$i,false)." /> $text ";
						$out .= '</label>';
					}
					$this->make_settings_row(
						__('Sorting images in album', 'pe2'),
						$out,
						__('This option drive image sorting in the dialog', 'pe2')
					);

					$option = $this->options['pe2_img_asc'];
					$this->make_settings_row(
						__('Sorting order', 'pe2'),
						'<label><input type="radio" name="pe2_img_asc" value="1" '.checked($option,'1',false).' />'.__('Ascending',  'pe2').'</label> '.
						'<label><input type="radio" name="pe2_img_asc" value="0" '.checked($option,'0',false).' />'.__('Descending', 'pe2').'</label> '
					);

					?>

				</table>

				<h3><?php _e('Image properties', 'pe2') ?></h3>
				<p><?php _e('Images from Picasa are inserted to a post as small as default thumbnail size ( you can change it <a href="options-media.php">here</a> ).')?><br/>
			       <?php _e('Image can be resized proportional with defined width or hardcoded with both width and height if "crop" checkbox is on.', 'pe2') ?></p>
				<p><?php _e('Thumbnail image can have a caption, title, link to original sized image, can be aligned and styled. Image alt property is defined by original file name.', 'pe2') ?></p>

				<table class="form-table">

					<?php

					$option = $this->options['pe2_caption'];
					$this->make_settings_row(
						__('Display caption', 'pe2'),
						'<label><input type="checkbox" name="pe2_caption" value="1" '.checked($option,'1',false).' /> '.__('Show the caption under thumbnail image', 'pe2').'</label> '
					);

					$option = $this->options['pe2_title'];
					$this->make_settings_row(
						__('Add caption as title', 'pe2'),
						'<label><input type="checkbox" name="pe2_title" value="1" '.checked($option,'1',false).' /> '.__('Show the caption by mouse hover tip', 'pe2').'</label> '
					);

					$opts = array (
						'none'     => __('No link', 'pe2'),
						'direct'   => __('Direct link', 'pe2'),
						'picasa'   => __('Link to Picasa Web Album', 'pe2'),
						'lightbox' => __('Lightbox', 'pe2'),
						'thickbox' => __('Thickbox', 'pe2'),
						'highslide'=> __('Highslide', 'pe2'),
					);
					$is_gallery = array (
						'none'     => 'false',
						'direct'   => 'false',
						'picasa'   => 'false',
						'lightbox' => 'true',
						'thickbox' => 'true',
						'highslide'=> 'true',
					);
					$is_gallery_js = 'var is_gallery = { ';
					foreach ($is_gallery as $key=>$val) {
						$is_gallery_js .= "$key:$val,";
					}
					$is_gallery_js = trim($is_gallery_js, ',').' };';
					?>
					<script type="text/javascript">
					function handle_gallery_properties(t) {
						<?php echo $is_gallery_js; ?>

						if (is_gallery[t]) {
							jQuery('#gallery_properties').show();
							jQuery('#gallery-message').show();
							jQuery('#nogallery_properties').hide();
						} else {
							jQuery('#gallery_properties').hide();
							jQuery('#gallery-message').hide();
							jQuery('#nogallery_properties').show();
						}
					}
					</script>
					<?php

					$out = '<select name="pe2_link" onchange="handle_gallery_properties(this.value);">';
					$option = $this->options['pe2_link'];
					foreach ($opts as $key => $val ) {
						$out .= "<option value=\"$key\" ".selected($option, $key, false ).">$val</option>";
					}
					$out .= '</select>';
					$this->make_settings_row(
						__('Link to larger image', 'pe2'),
						$out,
						__('To use external libraries like Thickbox, Lightbox or Highslide you need to install and integrate the library independently','pe2'),
						'',
						'id="gallery-message" style="display:'.(($is_gallery[$option]=='true') ? 'block' : 'none').';"'
					);
					$option = $this->options['pe2_large_limit'];
					preg_match('/(\w)(\d+)/',$option,$mode);
					if (!$mode) $mode=array('','','');
					$this->make_settings_row(
						__('Large image size', 'pe2'),
						'<label><input type="checkbox" name="pe2_large_limit" value="'.$option.'" '.checked(($option)?1:0,1,false).' /> '.__('Limit ','pe2').'</label> '.
						'<label><input type="radio" name="pe2_large_mode" class="pe2_large_limit" value="w" '.checked($mode[1], 'w', false).' '.disabled(($option)?1:0,0,false).' /> '.__('width','pe2').'</label> '.
						'<label><input type="radio" name="pe2_large_mode" class="pe2_large_limit" value="h" '.checked($mode[1], 'h', false).' '.disabled(($option)?1:0,0,false).' /> '.__('height','pe2').'</label> '.
						'<label><input type="radio" name="pe2_large_mode" class="pe2_large_limit" value="s" '.checked($mode[1], 's', false).' '.disabled(($option)?1:0,0,false).' /> '.__('any','pe2').'</label> '.
						__(' to ','pe2').
						'<input type="text" name="pe2_large_size" class="pe2_large_limit" style="width:60px;" id="pe2_large_size" value="'.$mode[2].'" '.disabled(($option)?1:0,0,false).' />'.
						__(' pixels','pe2'),
						sprintf(__('Value \'/%s\' will be used to limit large image'),"<span id=\"pe2_show-large-size\">$option</span>"),
						'',
						'id="large-limit-message" style="display:'.(($option) ? 'block' : 'none').';"'
					);
					?>
					<style type="text/css">
						input:disabled {
							background-color: #eee;
						}
					</style>
					<script type="text/javascript">
						jQuery('input[name=pe2_large_limit]').change(function(){
							if (jQuery(this).attr('checked')) {
								jQuery('input.pe2_large_limit').removeAttr('disabled');
								jQuery('#large-limit-message').show();
							} else {
								jQuery('input.pe2_large_limit').removeAttr('checked').attr('disabled','disabled');
								jQuery('input[name=pe2_large_size]').val('');
								jQuery('#pe2_show-large-size').text('');
								jQuery('input[name=pe2_large_limit]').val('');
								jQuery('#large-limit-message').hide();
							}
						});
						function pe2_large_limit(mode,value) {
							var master = jQuery('input[name=pe2_large_limit]');
							var val = master.val();
							var parts = {
								mode : val.replace(/([a-z]?).*/,'$1'),
								size : val.replace(/[a-z]*(\d*)/,'$1')};

							parts[mode] = value;
							master.val(parts.mode+parts.size);
							jQuery('#pe2_show-large-size').text(parts.mode+parts.size);
						}
						jQuery('input[name=pe2_large_mode]').change(function(){ if (jQuery(this).attr('checked')) pe2_large_limit('mode',jQuery(this).val()); });
						jQuery('input[name=pe2_large_size]').keypress(function(e){
//							console.log(e.type+' which:'+e.which+' alt:'+e.altKey+' ctrl:'+e.ctrlKey+' shift:'+e.shiftKey+' meta:'+e.metaKey);
							setTimeout(function(){
								pe2_large_limit('size',jQuery('input[name=pe2_large_size]').val());
							}, 10);
							if (e.altKey || e.ctrlKey || e.metaKey || e.which==37 || e.which==39 || e.which==8 || e.which==46) return true;
							if (e.which<48 || e.which>57 || e.shiftKey) return false;

						});
					</script>
					<?php


					$opts = array (
						'none'   => __('None'),
						'left'   => __('Left'),
						'center' => __('Center'),
						'right'  => __('Right'),
					);
					$option = $this->options['pe2_img_align'];
					$out = '';
					foreach ($opts as $key => $val ) {
						$out .= "<input type=\"radio\" name=\"pe2_img_align\" id=\"img-align$key\" value=\"$key\" ".checked($option, $key, false)." /> ";
						$out .= "<label for=\"img-align$key\" style=\"padding-left:22px;margin-right:13px;\" class=\"image-align-$key-label\">$val</label>";
					}
					$this->make_settings_row(
						__('Image alignment', 'pe2'),
						$out
					);

					$this->make_settings_row(
						__('CSS Class', 'pe2'),
						'<input type="text" name="pe2_img_css" class="regular-text" value="'.esc_attr($this->options['pe2_img_css']).'"/>',
						__("You can define default class for images from theme's style.css", 'pe2')
					);
					$this->make_settings_row(
						__('Style', 'pe2'),
						'<input type="text" name="pe2_img_style" class="regular-text" value="'.esc_attr($this->options['pe2_img_style']).'"/>',
						__('You can hardcode some css attributes', 'pe2')
					);
					?>

				</table>

				<h3><?php _e('Gallery properties', 'pe2') ?></h3>
				<p>
				<?php _e('Images can be grouped into gallery by Lightbox,Thickbox or Highslide and viewed sequentially with embedded navigation', 'pe2') ?>
				<br />
				<?php _e('Plugin can orginize the group by aligning or apply CSS style or class', 'pe2') ?>
				</p>

			<div id="nogallery_properties" style="<?php echo ($is_gallery[$this->options['pe2_link']]=='true') ? 'display:none;' : 'display:block;'?>">
				<p><?php _e('To view and change properties you have to select Thickbox, Lightbox or Highslide support for the images above', 'pe2') ?></p>
			</div>
			<div id="gallery_properties" style="<?php echo ($is_gallery[$this->options['pe2_link']]=='false') ? 'display:none;' : 'display:block;'?>">

				<table class="form-table">

					<?php
					$this->make_settings_row(
						__('Selection order', 'pe2'),
						'<label><input type="checkbox" name="pe2_gal_order" value="1" '.checked($this->options['pe2_gal_order'],'1',false).' /> '.__("Click images in your preferred order", 'pe2').'</label>'
					);

					$option = $this->options['pe2_gal_align'];
					$out = '';
					foreach ($opts as $key => $val ) {
						$out .= "<input type=\"radio\" name=\"pe2_gal_align\" id=\"gal-align$key\" value=\"$key\" ".checked($option, $key, false)." /> ";
						$out .= "<label for=\"gal-align$key\" style=\"padding-left:22px;margin-right:13px;\" class=\"image-align-$key-label\">$val</label>";
					}
					$this->make_settings_row(
						__('Gallery alignment', 'pe2'),
						$out
					);

					$this->make_settings_row(
						__('CSS Class', 'pe2'),
						'<input type="text" name="pe2_gal_css" class="regular-text" value="'.esc_attr($this->options['pe2_gal_css']).'"/>',
						__("You can define default class for images from theme's style.css", 'pe2')
					);
					$this->make_settings_row(
						__('Style', 'pe2'),
						'<input type="text" name="pe2_gal_style" class="regular-text" value="'.esc_attr($this->options['pe2_gal_style']).'"/>',
						__('You can hardcode some css attributes', 'pe2')
					);
					?>

				</table>
			</div>

				<p class="submit">
			    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			    </p>

			</form>
			</div>
			<?php
		}
	}
}

if (isset($_GET['authorize'])&&!defined('ABSPATH')) {

	if (!isset($_GET['token'])||!$_GET['token']||strlen($_GET['token'])>256) {
		header('Location: '.preg_replace('/wp-content.*/','',$_SERVER["REQUEST_URI"]).'wp-admin/options-general.php?page=picasa-express-2');
		die();
	}

	require_once(preg_replace('/wp-content.*/','',__FILE__).'wp-load.php');

	if (!isset($pe2_instance)) $pe2_instance = new PicasaExpressX2();

	if ('user' == $pe2_instance ->options['pe2_level'] && isset($_GET['user']) ) {
		$user_id = sanitize_text_field($_GET['user']);
		$user = new WP_User( $user_id );

		global $wp_roles;
		if ( ! isset( $wp_roles ) )	$wp_roles = new WP_Roles();

		$allow = false;
		foreach ( $user->roles as $role) {
			if (isset($wp_roles->roles[$role]['capabilities']['picasa_dialog'])) {
				$allow=true; break;
			}
		}

		if (!$allow) {
			header('Location: '.preg_replace('/wp-content.*/','',$_SERVER["REQUEST_URI"]).'wp-admin/profile.php');
			die();
		}
	}

	$response = $pe2_instance->get_feed("https://www.google.com/accounts/AuthSubSessionToken",sanitize_text_field($_GET['token']));

	$message='';
	if (is_wp_error($response)) {
		$message = 'Can\'t request token: ' .$response->get_error_message();
	} else if ($response) {
		$lines  = explode("\n", $response);
		foreach ($lines as $line) {
			$pair = explode("=", $line, 2);
			if (0==strcasecmp($pair[0],'token')) {
				if (isset($user_id))
					update_user_meta($user_id,'pe2_token',sanitize_text_field($pair[1]));
				else
					update_option('pe2_token',sanitize_text_field($pair[1]));
				$message = 'Private access received';
			}
		}
	}

	if (isset($user_id))
		header('Location: '.preg_replace('/wp-content.*/','',$_SERVER["REQUEST_URI"]).'wp-admin/profile.php?message='.rawurlencode($message));
	else
		header('Location: '.preg_replace('/wp-content.*/','',$_SERVER["REQUEST_URI"]).'wp-admin/options-general.php?page=picasa-express-2&message='.rawurlencode($message));
	die();

} else {
	if (!isset($pe2_instance)) $pe2_instance = new PicasaExpressX2();
}

?>