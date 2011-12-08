<?php

/*
Plugin Name: Social Connect - Facebook Provider
Plugin URI: http://wordpress.org/extend/plugins/social-connect/
Description: Allows you to login / register with Facebook - REQUIRES Social Connect plugin
Version: 0.10
Author: Brent Shepherd, Nathan Rijksen
Author URI: http://wordpress.org/extend/plugins/social-connect/
License: GPL2
 */

require_once dirname(__FILE__) . '/sdk.php';

class SC_Provider_Facebook 
{
	
	protected static $calls = array('connect','callback');
	
	static function init()
	{
		add_action('admin_init',                        array('SC_Provider_Facebook', 'register_settings') );
		add_action('social_connect_button_list',        array('SC_Provider_Facebook', 'render_button'));
		
		add_filter('social_connect_enable_options_page', create_function('$bool','return true;'));
		add_action('social_connect_options',            array('SC_Provider_Facebook', 'render_options') );
	}
	
	static function call()
	{
		if ( !isset($_GET['call']) OR !in_array($_GET['call'], array('connect','callback')))
		{
			return;
		}
		
		call_user_func(array('SC_Provider_Facebook', $_GET['call']));
	}
	
	static function register_settings()
	{
		register_setting( 'social-connect-settings-group', 'social_connect_facebook_api_key' );
		register_setting( 'social-connect-settings-group', 'social_connect_facebook_secret_key' );
	}
	
	static function render_options()
	{
		?>
		<h3><?php _e('Facebook Settings', 'social_connect'); ?></h3>
		<p><?php _e('To connect your site to Facebook, you need a Facebook Application. If you have already created one, please insert your API & Secret key below.', 'social_connect'); ?></p>
		<p><?php printf(__('Already registered? Find your keys in your <a target="_blank" href="%2$s">%1$s Application List</a>', 'social_connect'), 'Facebook', 'http://www.facebook.com/developers/apps.php'); ?></p>
		<p><?php _e('Need to register?', 'social_connect'); ?></p>
		<ol>
			<li><?php printf(__('Visit the <a target="_blank" href="%1$s">Facebook Application Setup</a> page', 'social_connect'), 'http://www.facebook.com/developers/createapp.php'); ?></li>
			<li><?php printf(__('Get the API information from the <a target="_blank" href="%1$s">Facebook Application List</a>', 'social_connect'), 'http://www.facebook.com/developers/apps.php'); ?></li>
			<li><?php _e('Select the application you created, then copy and paste the API key & Application Secret from there.', 'social_connect'); ?></li>
		</ol>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('API Key', 'social_connect'); ?></th>
				<td><input type="text" name="social_connect_facebook_api_key" value="<?php echo get_option('social_connect_facebook_api_key' ); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row"><?php _e('Secret Key', 'social_connect'); ?></th>
				<td><input type="text" name="social_connect_facebook_secret_key" value="<?php echo get_option('social_connect_facebook_secret_key' ); ?>" /></td>
			</tr>
		</table>
		<?php
	}
	
	static function render_button()
	{
		$image_url = plugins_url() . '/' . basename( dirname( __FILE__ )) . '/button.png';
		?>
		<a href="javascript:void(0);" title="Facebook" class="social_connect_login_facebook"><img alt="Facebook" src="<?php echo $image_url ?>" /></a>
		<div id="social_connect_facebook_auth" style="display: none;">
			<input type="hidden" name="client_id" value="<?php echo get_option( 'social_connect_facebook_api_key' ); ?>" />
			<input type="hidden" name="redirect_uri" value="<?php echo urlencode( SOCIAL_CONNECT_PLUGIN_URL . '/call.php?call=connect&provider=facebook' ); ?>" />
		</div>
		
		<script type="text/javascript">
		(jQuery(function($) {
			var _do_facebook_connect = function() {
				var facebook_auth = $('#social_connect_facebook_auth');
				var client_id = facebook_auth.find('input[type=hidden][name=client_id]').val();
				var redirect_uri = facebook_auth.find('input[type=hidden][name=redirect_uri]').val();
	
				if(client_id == "") {
					alert("Social Connect plugin has not been configured for this provider")
				} else {
					window.open('https://graph.facebook.com/oauth/authorize?client_id=' + client_id + '&redirect_uri=' + redirect_uri + '&scope=email',
					'','scrollbars=no,menubar=no,height=400,width=800,resizable=yes,toolbar=no,status=no');
				}
			};
			
			$(".social_connect_login_continue_facebook, .social_connect_login_facebook").click(function() {
				_do_facebook_connect();
			});
		}));
		</script>
		<?php
	}
	
	static function connect()
	{
		
		$client_id      = get_option('social_connect_facebook_api_key');
		$secret_key     = get_option('social_connect_facebook_secret_key');
		$redirect_uri   = urlencode(SOCIAL_CONNECT_PLUGIN_URL . '/call.php?provider=facebook&call=callback');
		
		wp_redirect('https://graph.facebook.com/oauth/authorize?client_id=' . $client_id . '&redirect_uri=' . $redirect_uri . '&scope=email');
		
	}
	
	static function callback()
	{
		$client_id      = get_option('social_connect_facebook_api_key');
		$secret_key     = get_option('social_connect_facebook_secret_key');
		$code           = $_GET['code'];
		
		parse_str(SC_Utils::curl_get_contents(
			"https://graph.facebook.com/oauth/access_token?" .
			'client_id=' . $client_id . '&redirect_uri=' . urlencode(SOCIAL_CONNECT_PLUGIN_URL . '/call.php?provider=facebook&call=callback') .
			'&client_secret=' .  $secret_key .
			'&code=' . urlencode($code)
		));
			
		$signature = SC_Utils::generate_signature($access_token);
		
		?>
		<html>
		<head>
		<script>
		function init() {
			window.opener.wp_social_connect({
				'action' : 'social_connect', 'social_connect_provider' : 'facebook',
				'social_connect_signature' : '<?php echo $signature ?>',
				'social_connect_access_token' : '<?php echo $access_token ?>'});
			window.close();
		}
		</script>
		</head>
		<body onload="init();">
		</body>
		</html>
		<?php
	}
	
	static function process_login()
	{
		SC_Utils::verify_signature( $_REQUEST[ 'social_connect_access_token' ], $_REQUEST[ 'social_connect_signature' ], $redirect_to );
		
		$fb_json = json_decode( SC_Utils::curl_get_contents("https://graph.facebook.com/me?access_token=" . $_REQUEST[ 'social_connect_access_token' ]) );
		
		return (object) array(
			'provider_identity' => $fb_json->id,
			'email'             => $fb_json->email,
			'first_name'        => $fb_json->first_name,
			'last_name'         => $fb_json->last_name,
			'profile_url'       => $fb_json->link,
			'name'              => $fb_json->first_name . ' ' . $fb_json->last_name,
			'user_login'        => strtolower( $fb_json->first_name.$fb_json->last_name )
		);
	}
	
}

SC_Provider_Facebook::init();