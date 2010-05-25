<?php
/*
 * This is the main code for the SFC Base system. It's included by the main "Simple Facebook Connect" plugin.
 */

// load plugin locales
load_plugin_textdomain( 'sfc', false, basename(dirname(__FILE__)) . '/languages/');

// load the FB script into the head 
add_action('wp_enqueue_scripts','sfc_featureloader');
function sfc_featureloader() {
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
		wp_enqueue_script( 'fb-featureloader', 'https://ssl.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php/'.get_locale(), array(), '0.4', false);
	else
		wp_enqueue_script( 'fb-featureloader', 'http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php/'.get_locale(), array(), '0.4', false);
		
	//wp_enqueue_script( 'fb-all', 'http://connect.facebook.net/en_US/all.js', array(), '1', false);	
}

// fix up the html tag to have the FBML extensions
add_filter('language_attributes','sfc_lang_atts');
function sfc_lang_atts($lang) {
    return ' xmlns:fb="http://www.facebook.com/2008/fbml" xmlns:og="http://opengraphprotocol.org/schema/" '.$lang;
}

// basic XFBML load into footer
add_action('wp_footer','sfc_add_base_js',20); // 20, to put it at the end of the footer insertions. sub-plugins should use 30 for their code
function sfc_add_base_js() {
	$options = get_option('sfc_options');
	sfc_load_api($options['api_key']);
};

function sfc_load_api($key) {
$reload = apply_filters('sfc_reload_state_change',false);

$sets['permsToRequestOnConnect']='email';
if ($reload) $sets['reloadIfSessionStateChanged'] = true;
?>
<script type="text/javascript">
FB_RequireFeatures(["XFBML"], function() {
  	FB.init("<?php echo $key; ?>", "<?php bloginfo('url'); ?>/?xd_receiver=1", <?php echo json_encode($sets); ?>);
});
</script>
<?php
}

// plugin row links
add_filter('plugin_row_meta', 'sfc_donate_link', 10, 2);
function sfc_donate_link($links, $file) {
	if ($file == plugin_basename(__FILE__)) {
		$links[] = '<a href="'.admin_url('options-general.php?page=sfc').'">' . __('Settings', 'sfc') . '</a>';
		$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=otto%40ottodestruct%2ecom">' . __('Donate', 'sfc') . '</a>';
	}
	return $links;
}

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'sfc_settings_link', 10, 1);
function sfc_settings_link($links) {
	$links[] = '<a href="'.admin_url('options-general.php?page=sfc').'">' . __('Settings', 'sfc') . '</a>';
	return $links;
}

// add the admin settings and such
add_action('admin_init', 'sfc_admin_init',9); // 9 to force it first, subplugins should use default
function sfc_admin_init(){
	$options = get_option('sfc_options');
	if (empty($options['api_key']) || empty($options['app_secret']) || empty($options['appid'])) {
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".sprintf(__('Simple Facebook Connect needs configuration information on its <a href="%s">settings</a> page.', 'sfc'), admin_url('options-general.php?page=sfc'))."</p></div>';" ) );
	} else {
		sfc_featureloader();
		add_action('admin_footer','sfc_add_base_js',20);
	}
	wp_enqueue_script('jquery');
	register_setting( 'sfc_options', 'sfc_options', 'sfc_options_validate' );
	add_settings_section('sfc_main', __('Main Settings', 'sfc'), 'sfc_section_text', 'sfc');
	if (!defined('SFC_API_KEY')) add_settings_field('sfc_api_key', __('Facebook API Key', 'sfc'), 'sfc_setting_api_key', 'sfc', 'sfc_main');
	if (!defined('SFC_APP_SECRET')) add_settings_field('sfc_app_secret', __('Facebook Application Secret', 'sfc'), 'sfc_setting_app_secret', 'sfc', 'sfc_main');
	if (!defined('SFC_APP_ID')) add_settings_field('sfc_appid', __('Facebook Application ID', 'sfc'), 'sfc_setting_appid', 'sfc', 'sfc_main');
	if (!defined('SFC_FANPAGE')) add_settings_field('sfc_fanpage', __('Facebook Fan Page', 'sfc'), 'sfc_setting_fanpage', 'sfc', 'sfc_main');
}

// add the admin options page
add_action('admin_menu', 'sfc_admin_add_page');
function sfc_admin_add_page() {
	$mypage = add_options_page(__('Simple Facebook Connect', 'sfc'), __('Simple Facebook Connect', 'sfc'), 'manage_options', 'sfc', 'sfc_options_page');
}

// display the admin options page
function sfc_options_page() {
    include dirname(__FILE__) . '/views/options_page.php';
}

function sfc_section_text() {
	$options = get_option('sfc_options');
	if (empty($options['api_key']) || empty($options['app_secret']) || empty($options['appid'])) {
    
        include dirname(__FILE__) . '/views/section_text.php';
		
		// look for an FBFoundations key if we dont have one of our own, 
		// to better facilitate switching from that plugin to this one.
		$fbfoundations_settings = get_option('fbfoundations_settings');
		if (isset($fbfoundations_settings['api_key']) && !empty($fbfoundations_settings['api_key'])) {
			$options['api_key'] = $fbfoundations_settings['api_key'];
		}
	} else {

		// load facebook platform
		include_once 'facebook-platform/facebook.php';
		$fb=new Facebook($options['api_key'], $options['app_secret']);

		$error = false;
		$a = null;
		$connecturl = null;
		
		try {
    		$a = $fb->api_client->admin_getAppProperties(array('connect_url'));
		} catch (Exception $e) {
		    // bad API key or secret or something
		    $error=true;
		    echo '<p class="error">' . __('Facebook doesn\'t like your settings, it says', 'sfc') . ': ';
		    echo $e->getMessage();
		    echo '.</p>';
		}
		
		if (is_array($a)) {
			$connecturl = $a['connect_url'];
		} else if (is_object($a)) { // seems to happen on some setups.. dunno why.
			$connecturl = $a->connect_url;
		}
		
		if (!defined('SFC_IGNORE_ERRORS') && !empty($connecturl)) {
			$siteurl = trailingslashit(get_option('siteurl'));
			if (@strpos($siteurl, $connecturl) === false) {
				$error = true;
				echo '<p class="error">' . __('Your Facebook Application\'s "Connect URL" is configured incorrectly. It is currently set to','sfc') . ' "'. 
				$connecturl . "\" " . __('when it should be set to') . " \"{$siteurl}\" .</p>";
			}

			if ($error) {
?>
<p class="error"><?php echo sprintf( __('To correct these errors, you may need to <a href="http://www.facebook.com/developers/editapp.php?app_id=%s" target="_blank">edit your applications settings</a> and correct the values therein. The site will not work properly until the errors are corrected.','sfc'), $options['appid']) ?></p>
<?php
			}
		}
	}
}

// this will override all the main options if they are pre-defined
function sfc_override_options($options) {
	if (defined('SFC_API_KEY')) $options['api_key'] = SFC_API_KEY;
	if (defined('SFC_APP_SECRET')) $options['app_secret'] = SFC_APP_SECRET;
	if (defined('SFC_APP_ID')) $options['appid'] = SFC_APP_ID;
	if (defined('SFC_FANPAGE')) $options['fanpage'] = SFC_FANPAGE;
	return $options;
}
add_filter('option_sfc_options', 'sfc_override_options');

function sfc_setting_api_key() {
	if (defined('SFC_API_KEY')) return;
	$options = get_option('sfc_options');
	echo "<input type='text' id='sfcapikey' name='sfc_options[api_key]' value='{$options['api_key']}' size='40' /> (".__('required', 'sfc').")";	
}
function sfc_setting_app_secret() {
	if (defined('SFC_APP_SECRET')) return;
	$options = get_option('sfc_options');
	echo "<input type='text' id='sfcappsecret' name='sfc_options[app_secret]' value='{$options['app_secret']}' size='40' /> (".__('required', 'sfc').")";	
}
function sfc_setting_appid() {
	if (defined('SFC_APP_ID')) return;
	$options = get_option('sfc_options');
	echo "<input type='text' id='sfcappid' name='sfc_options[appid]' value='{$options['appid']}' size='40' /> (".__('required', 'sfc').")";
	$_url = "http://www.facebook.com/apps/application.php?id={$options['appid']}&amp;v=wall";
	if (!empty($options['appid'])) echo sprintf( __("<p>Here is a <a href='%s'>link to your applications wall</a>. There you can give it a name, upload a profile picture, things like that. Look for the \"Edit Application\" link to modify the application.</p>", 'sfc') , $_url);	
}
function sfc_setting_fanpage() {
	if (defined('SFC_FANPAGE')) return;
	$options = get_option('sfc_options');

_e('<p>Some sites use Fan Pages on Facebook to connect with their users. The Application wall acts as a 
Fan Page in all respects, however some sites have been using Fan Pages previously, and already have 
communities and content built around them. Facebook offers no way to migrate these, so the option to 
use an existing Fan Page is offered for people with this situation. Note that this doesn\'t <em>replace</em> 
the application, as that is not optional. However, you can use a Fan Page for specific parts of the 
SFC plugin, such as the Fan Box, the Publisher, and the Chicklet.</p>', 'sfc');

_e('<p>If you have a <a href="http://www.facebook.com/pages/manage/">Fan Page</a> that you want to use for 
your site, enter the ID of the page here. Most users should leave this blank.</p>', 'sfc');

	echo "<input type='text' id='sfcfanpage' name='sfc_options[fanpage]' value='{$options['fanpage']}' size='40' />";
}

// validate our options
function sfc_options_validate($input) {
	if (!defined('SFC_API_KEY')) {
		// api keys are 32 bytes long and made of hex values
		$input['api_key'] = trim($input['api_key']);
		if(! preg_match('/^[a-f0-9]{32}$/i', $input['api_key'])) {
		  $input['api_key'] = '';
		}
	}

	if (!defined('SFC_APP_SECRET')) {
		// api keys are 32 bytes long and made of hex values
		$input['app_secret'] = trim($input['app_secret']);
		if(! preg_match('/^[a-f0-9]{32}$/i', $input['app_secret'])) {
		  $input['app_secret'] = '';
		}
	}

	if (!defined('SFC_APP_ID')) {
		// app ids are big integers
		$input['appid'] = trim($input['appid']);
		if(! preg_match('/^[0-9]+$/i', $input['appid'])) {
		  $input['appid'] = '';
		}
	}
	
	if (!defined('SFC_FANPAGE')) {
		// fanpage ids are big integers
		$input['fanpage'] = trim($input['fanpage']);
		if(! preg_match('/^[0-9]+$/i', $input['fanpage'])) {
		  $input['fanpage'] = '';
		}
	}
	
	$input = apply_filters('sfc_validate_options',$input); // filter to let sub-plugins validate their options too
	return $input;
}


// this adds the app id to allow you to use Facebook Insights on your domain, linked to your application.
add_action('wp_head','sfc_meta_head');
function sfc_meta_head() {
	$options = get_option('sfc_options');
	
	if ($options['appid']) {
	?>
<meta property='fb:app_id' content='<?php echo $options['appid']; ?>' />
<?php
	}
	?>
<meta property="og:site_name" content="<?php bloginfo('name'); ?>" />
<?php
	if ( is_singular() ) {
		global $wp_the_query;
		if ( $id = $wp_the_query->get_queried_object_id() ) {
			$link = get_permalink( $id );
			echo "<meta property='og:url' content='{$link}' />\n";
		}
	} else if (is_home()) {
		$link = get_bloginfo('url');
		echo "<meta property='og:url' content='{$link}' />\n";
	}
}


// this function checks if the current FB user is a fan of your page. 
// Returns true if they are, false otherwise.
function sfc_is_fan($pageid='0') {
	$options = get_option('sfc_options');

	if ($pageid == '0') {
		if ($options['fanpage']) $pageid = $options['fanpage'];
		else $pageid = $options['appid'];
	}

	include_once 'facebook-platform/facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	
	$fbuid=$fb->get_loggedin_user();
	
	if (!$fbuid) return false;

	if ($fb->api_client->pages_isFan($pageid) ) {
		return true;
	} else {
		return false;
	}
}

// get the current facebook user number (0 if the user is not connected to this site)
function sfc_get_user() {
	$options = get_option('sfc_options');
	include_once 'facebook-platform/facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	$fbuid=$fb->get_loggedin_user();
	return $fbuid;
}
