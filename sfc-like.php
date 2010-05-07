<?php
/*
Plugin Name: SFC - Like Button
Plugin URI: http://ottopress.com/wordpress-plugins/simple-facebook-connect/
Description: Simple like button for use with SFC.
Author: Otto
Version: 0.20
Author URI: http://ottodestruct.com
License: GPL2

    Copyright 2010  Samuel Wood  (email : otto@ottodestruct.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License version 2, 
    as published by the Free Software Foundation. 
    
    You may NOT assume that you can use any other version of the GPL.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    
    The license for this software can likely be found here: 
    http://www.gnu.org/licenses/gpl-2.0.html
    
*/

// checks for sfc on activation
function sfc_like_activation_check(){
	if (function_exists('sfc_version')) {
		if (version_compare(sfc_version(), '0.1', '>=')) {
			return;
		}
	}
	deactivate_plugins(basename(__FILE__)); // Deactivate ourself
	wp_die("The base SFC plugin must be activated before this plugin will run.");
}
register_activation_hook(__FILE__, 'sfc_like_activation_check');

global $sfc_like_defaults;
$sfc_like_defaults = array(
		'id'=>0,
		'layout'=>'standard', 		// standard or button_count
		'showfaces'=>'true', 		// true or false
		'width'=>'260',
		'height'=>'26', 
		'action'=>'like',			// like or recommend
		'colorscheme'=>'light',		// light or dark
		'font' => 'lucida+grande',	// arial, lucida+grande, seqoe+ui, tahoma, trebuchet+ms, or verdana
		);

function get_sfc_like_button($args='') {
	global $sfc_like_defaults;
	$args = wp_parse_args($args, $sfc_like_defaults);
	extract($args);
	
	$url = get_permalink($id);

	// This wont work until I switch to the new libraries
	//return "<fb:like href='{$url}' layout='{$layout}' show_faces='{$showfaces}' width='{$width}' action='{$action}' colorscheme='{$colorscheme}' />";
	
	return "<p class='fb-like'><iframe src='http://www.facebook.com/plugins/like.php?href={$url}&amp;layout={$layout}&amp;show_faces={$showfaces}&amp;width={$width}&amp;action={$action}&amp;colorscheme={$colorscheme}' scrolling='no' frameborder='0' allowTransparency='true' style='border:none; overflow:hidden; width:{$width}px; height:{$height}px'></iframe></p>";
}

function sfc_like_button($args='') {
	echo get_sfc_like_button($args);
}

function sfc_like_shortcode($atts) {
	global $sfc_like_defaults;
	$args = shortcode_atts($sfc_like_defaults, $atts);

	return get_sfc_like_button($args);
}
add_shortcode('fb-like', 'sfc_like_shortcode');

function sfc_like_button_automatic($content) {
	$options = get_option('sfc_options');
	$button = get_sfc_like_button();
	switch ($options['like_position']) {
		case "before":
			$content = $button . $content;
			break;
		case "after":
			$content = $content . $button;
			break;
		case "both":
			$content = $button . $content . $button;
			break;
		case "manual":
		default:
			break;
	}
	return $content;
}
add_filter('the_content', 'sfc_like_button_automatic', 30);

// add the admin sections to the sfc page
add_action('admin_init', 'sfc_like_admin_init');
function sfc_like_admin_init() {
	add_settings_section('sfc_like', 'Like Button Settings', 'sfc_like_section_callback', 'sfc');
	add_settings_field('sfc_like_position', 'Like Button Position', 'sfc_like_position', 'sfc', 'sfc_like');
}

function sfc_like_section_callback() {
	echo '<p>Choose where you want the like button added to your content.</p>';
}

function sfc_like_position() {
	$options = get_option('sfc_options');
	if (!$options['like_position']) $options['like_position'] = 'manual';
	?>
	<p><label><input type="radio" name="sfc_options[like_position]" value="before" <?php checked('before', $options['like_position']); ?> /> Before the content of your post</label></p>
	<p><label><input type="radio" name="sfc_options[like_position]" value="after" <?php checked('after', $options['like_position']); ?> /> After the content of your post</label></p>
	<p><label><input type="radio" name="sfc_options[like_position]" value="both" <?php checked('both', $options['like_position']); ?> /> Before AND After the content of your post </label></p>
	<p><label><input type="radio" name="sfc_options[like_position]" value="manual" <?php checked('manual', $options['like_position']); ?> /> Manually add the button to your theme or posts (use the sfc_like_button function in your theme)</label></p>
<?php 
}

add_filter('sfc_validate_options','sfc_like_validate_options');
function sfc_like_validate_options($input) {
	if (!in_array($input['like_position'], array('before', 'after', 'both', 'manual'))) {
			$input['like_position'] = 'manual';
	}
	return $input;
}

add_action('wp_head','sfc_like_meta');
function sfc_like_meta() {
	$excerpt = '';
	if (is_singular()) {
		the_post();
		rewind_posts(); 
		$excerpt = strip_tags(get_the_excerpt());
		$content = get_the_content();
		$content = apply_filters('the_content', $content);
?>
<meta property="og:type" content="article" />
<meta property="og:title" content="<?php echo esc_attr(get_the_title()); ?>" />
<?php
		// look for image to add with image_src (simple, just add first image)
		
		// get the post thumbnail, put it first in the image list
		if ( current_theme_supports('post-thumbnails') && has_post_thumbnail(get_the_ID()) ) {
			$thumbid = get_post_thumbnail_id(get_the_ID());
			$att = wp_get_attachment_image_src($thumbid, 'full');
			if (!empty($att[0])) {
				?><link rel="image_src" href="<?php echo $att[0]; ?>" /><?php
			}
		} else if ( preg_match('/<img (.+?)>/', $content, $matches) ) {
			foreach ( wp_kses_hair($matches[1], array('http')) as $attr) 
				$img[$attr['name']] = $attr['value'];
			if ( isset($img['src']) ) {
				if (!isset($img['class']) || 
					(isset($img['class']) && false === strpos($img['class'], 'wp-smiley'))
					) { // ignore smilies
?>
<meta property="og:image" content="<?php echo $img['src'] ?>" />
<?php
				}
			}
		}		
	} else if (is_home()) {
	?>
<meta property="og:type" content="blog" />
<meta property="og:title" content="<?php bloginfo('name'); ?>" />
<?php
	}
}