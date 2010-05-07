<?php
/*
Plugin Name: SFC - Activity Feed Widget
Plugin URI: http://ottopress.com/wordpress-plugins/simple-facebook-connect/
Description: Create an Activity Feed for your sites sidebar.
Author: Otto
Version: 0.20
Author URI: http://ottodestruct.com
License: GPL2

    Copyright 2009-2010  Samuel Wood  (email : otto@ottodestruct.com)

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
function sfc_activity_feed_activation_check(){
	if (function_exists('sfc_version')) {
		if (version_compare(sfc_version(), '0.18', '>=')) {
			return;
		}
	}
	deactivate_plugins(basename(__FILE__)); // Deactivate ourself
	wp_die("The base SFC plugin must be activated before this plugin will run.");
}
register_activation_hook(__FILE__, 'sfc_activity_feed_activation_check');

function get_sfc_activity_feed($args) {
	$args = wp_parse_args($args, array(
		'header'=>'true',
		'site'=>'',
		'bordercolor'=>'000000',
		'width'=>'260',
		'height'=>'400',
		'font'=>'lucida+grande',
		'colorscheme'=>'light'));
	extract($args);
	
	if (empty($site)) $site = get_bloginfo('url');
	
	return "<p class='fb-activity-feed'><iframe src='http://www.facebook.com/plugins/activity.php?site={$site}&amp;width={$width}&amp;height={$height}&amp;header={$header}&amp;colorscheme={$colorscheme}&amp;font={$font}&amp;border_color={$bordercolor}' scrolling='no' frameborder='0' allowTransparency='true' style='border:none; overflow:hidden; width:{$width}px; height:{$height}px'></iframe></p>";
}
	
function sfc_activity_feed($args='') {
	echo get_sfc_activity_feed($args);
}

function sfc_activity_feed_shortcode($atts) {
	$args = shortcode_atts(array(
		'header'=>'true',
		'site'=>'',
		'bordercolor'=>'000000',
		'width'=>'260',
		'height'=>'400',
		'font'=>'lucida+grande',
		'colorscheme'=>'light'), $atts);

	return get_sfc_activity_feed($args);
}
add_shortcode('fb-activity', 'sfc_activity_feed_shortcode');

class SFC_Activity_Feed_Widget extends WP_Widget {
	function SFC_Activity_Feed_Widget() {
		$widget_ops = array('classname' => 'widget_sfc-activity-feed', 'description' => 'Facebook Activity Feed');
		$this->WP_Widget('sfc-activity', 'Facebook Activity Feed (SFC)', $widget_ops);
	}

	function widget($args, $instance) {
		extract( $args );
		$title = apply_filters('widget_title', $instance['title']);
		$instance['width'] = intval($instance['width']);
		$instance['height'] = intval($instance['height']);
		?>
		<?php echo $before_widget; ?>
		<?php if ( $title ) echo $before_title . $title . $after_title; ?>
		<?php sfc_activity_feed($instance); ?>
		<?php echo $after_widget; ?>
		<?php
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$new_instance = wp_parse_args( (array) $new_instance, array( 'title' => '', 'width'=>260, 'height'=>400) );
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['width'] = intval($new_instance['width']);
		$instance['height'] = intval($new_instance['height']);
		return $instance;
	}

	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'width'=>260, 'height'=>400) );
		$title = strip_tags($instance['title']);
		$width = intval($instance['width']);
		$height = intval($instance['height']);
		?>
<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> 
<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
</label></p>
<p><label for="<?php echo $this->get_field_id('width'); ?>">Width of the widget in pixels:
<input class="widefat" id="<?php echo $this->get_field_id('width'); ?>" name="<?php echo $this->get_field_name('width'); ?>" type="text" value="<?php echo $width; ?>" />
</label></p>
<p><label for="<?php echo $this->get_field_id('height'); ?>">Height of the widget in pixels:
<input class="widefat" id="<?php echo $this->get_field_id('height'); ?>" name="<?php echo $this->get_field_name('height'); ?>" type="text" value="<?php echo $height; ?>" />
</label></p>
		<?php
	}
}
add_action('widgets_init', create_function('', 'return register_widget("SFC_Activity_Feed_Widget");'));

