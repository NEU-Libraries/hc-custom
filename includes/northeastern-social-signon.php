<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs actions on init
 */
function northeastern_social_login_init() {
	if ( function_exists( 'wsl_register_components' ) ) {
		remove_action( 'login_form', 'shibboleth_login_form' );
		remove_action( 'login_form', 'wsl_render_auth_widget_in_wp_login_form' );
		add_action( 'login_form', 'northeastern_render_social_login' );
		add_action( 'wsl_component_tools_sections', 'northeastern_social_login_whitelist_settings' );
		add_action( 'wsl_component_tools_do_repair', 'northeastern_wsl_do_repair', 5 );
		add_action( 'wsl_component_tools_start', 'northeastern_wsl_do_whitelist_job' );
	}
}
add_action( 'init', 'northeastern_social_login_init' );

/**
 * Renders the social buttons on the login page
 */
function northeastern_render_social_login() {
	$args = array(

	);

	$auth_mode = isset( $args['mode'] ) && $args['mode'] ? $args['mode'] : 'login';

	// validate auth-mode
	if( ! in_array( $auth_mode, array( 'login', 'link', 'test' ) ) )
	{
		return;
	}

	// auth-mode eq 'login' => display wsl widget only for NON logged in users
	// > this is the default mode of wsl widget.
	if( $auth_mode == 'login' && is_user_logged_in() )
	{
		return;
	}

	// auth-mode eq 'link' => display wsl widget only for LOGGED IN users
	// > this will allows users to manually link other social network accounts to their WordPress account
	if( $auth_mode == 'link' && ! is_user_logged_in() )
	{
		return;
	}

	// auth-mode eq 'test' => display wsl widget only for LOGGED IN users only on dashboard
	// > used in Authentication Playground on WSL admin dashboard
	if( $auth_mode == 'test' && ! is_user_logged_in() && ! is_admin() )
	{
		return;
	}

	// Bouncer :: Allow authentication?
	if( get_option( 'wsl_settings_bouncer_authentication_enabled' ) == 2 )
	{
		return;
	}

	// HOOKABLE: This action runs just before generating the WSL Widget.
	do_action( 'wsl_render_auth_widget_start' );

	GLOBAL $WORDPRESS_SOCIAL_LOGIN_PROVIDERS_CONFIG;

	ob_start();

	// Icon set. If eq 'none', we show text instead
	$social_icon_set = get_option( 'wsl_settings_social_icon_set' );

	// wpzoom icons set, is shown by default
	if( empty( $social_icon_set ) )
	{
		$social_icon_set = "wpzoom/";
	}

	$assets_base_url  = WORDPRESS_SOCIAL_LOGIN_PLUGIN_URL . 'assets/img/32x32/' . $social_icon_set . '/';

	$assets_base_url  = isset( $args['assets_base_url'] ) && $args['assets_base_url'] ? $args['assets_base_url'] : $assets_base_url;

	// HOOKABLE:
	$assets_base_url = apply_filters( 'wsl_render_auth_widget_alter_assets_base_url', $assets_base_url );

	// get the current page url, which we will use to redirect the user to,
	// unless Widget::Force redirection is set to 'yes', then this will be ignored and Widget::Redirect URL will be used instead
	$redirect_to = wsl_get_current_url();

	// Use the provided redirect_to if it is given and this is the login page.
	if ( in_array( $GLOBALS["pagenow"], array( "wp-login.php", "wp-register.php" ) ) && !empty( $_REQUEST["redirect_to"] ) )
	{
		$redirect_to = $_REQUEST["redirect_to"];
	}

	// build the authentication url which will call for wsl_process_login() : action=wordpress_social_authenticate
	$authenticate_base_url = site_url( 'wp-login.php', 'login_post' )
	                         . ( strpos( site_url( 'wp-login.php', 'login_post' ), '?' ) ? '&' : '?' )
	                         . "action=wordpress_social_authenticate&mode=login&";

	// if not in mode login, we overwrite the auth base url
	// > admin auth playground
	if( $auth_mode == 'test' )
	{
		$authenticate_base_url = home_url() . "/?action=wordpress_social_authenticate&mode=test&";
	}

	// > account linking
	elseif( $auth_mode == 'link' )
	{
		$authenticate_base_url = home_url() . "/?action=wordpress_social_authenticate&mode=link&";
	}

	// Connect with caption
	$connect_with_label = _wsl__( get_option( 'wsl_settings_connect_with_label' ), 'wordpress-social-login' );

	$connect_with_label = isset( $args['caption'] ) ? $args['caption'] : $connect_with_label;

	// HOOKABLE:
	$connect_with_label = apply_filters( 'wsl_render_auth_widget_alter_connect_with_label', $connect_with_label );
	?>

	<!--
wsl_render_auth_widget
WordPress Social Login <?php echo wsl_get_version(); ?>.
http://wordpress.org/plugins/wordpress-social-login/
-->
	<?php
	// Widget::Custom CSS
	$widget_css = get_option( 'wsl_settings_authentication_widget_css' );

	// HOOKABLE:
	$widget_css = apply_filters( 'wsl_render_auth_widget_alter_widget_css', $widget_css, $redirect_to );

	// show the custom widget css if not empty
	if( ! empty( $widget_css ) )
	{
		?>

		<style type="text/css">
			<?php
				echo
					preg_replace(
						array( '%/\*(?:(?!\*/).)*\*/%s', '/\s{2,}/', "/\s*([;{}])[\r\n\t\s]/", '/\\s*;\\s*/', '/\\s*{\\s*/', '/;?\\s*}\\s*/' ),
							array( '', ' ', '$1', ';', '{', '}' ),
								$widget_css );
			?>
		</style>
		<?php
	}
	?>

	<div class="wp-social-login-widget">

		<div class="wp-social-login-connect-with">Login with:</div>

		<div class="wp-social-login-provider-list">
			<a rel="nofollow" href="<?php echo add_query_arg( 'action', 'shibboleth', wp_login_url() ); ?>" title="Login with Northeastern University Universal Login" class="wp-social-login-provider wp-social-login-provider-shibboleth" data-provider="shibboleth">
				<img alt="shibboleth" title="Login with Northeastern University Universal Login" src="" />
			</a>
			<?php
			// Widget::Authentication display
			$wsl_settings_use_popup = get_option( 'wsl_settings_use_popup' );

			// if a user is visiting using a mobile device, WSL will fall back to more in page
			$wsl_settings_use_popup = function_exists( 'wp_is_mobile' ) ? wp_is_mobile() ? 2 : $wsl_settings_use_popup : $wsl_settings_use_popup;

			$no_idp_used = true;

			// display provider icons
			foreach( $WORDPRESS_SOCIAL_LOGIN_PROVIDERS_CONFIG AS $item )
			{
				$provider_id    = isset( $item["provider_id"]    ) ? $item["provider_id"]   : '' ;
				$provider_name  = isset( $item["provider_name"]  ) ? $item["provider_name"] : '' ;

				// provider enabled?
				if( get_option( 'wsl_settings_' . $provider_id . '_enabled' ) )
				{
					// restrict the enabled providers list
					if( isset( $args['enable_providers'] ) )
					{
						$enable_providers = explode( '|', $args['enable_providers'] ); // might add a couple of pico seconds

						if( ! in_array( strtolower( $provider_id ), $enable_providers ) )
						{
							continue;
						}
					}

					// build authentication url
					$authenticate_url = $authenticate_base_url . "provider=" . $provider_id . "&redirect_to=" . urlencode( $redirect_to );

					// http://codex.wordpress.org/Function_Reference/esc_url
					$authenticate_url = esc_url( $authenticate_url );

					// in case, Widget::Authentication display is set to 'popup', then we overwrite 'authenticate_url'
					// > /assets/js/connect.js will take care of the rest
					if( $wsl_settings_use_popup == 1 &&  $auth_mode != 'test' )
					{
						$authenticate_url= "javascript:void(0);";
					}

					// HOOKABLE: allow user to rebuilt the auth url
					$authenticate_url = apply_filters( 'wsl_render_auth_widget_alter_authenticate_url', $authenticate_url, $provider_id, $auth_mode, $redirect_to, $wsl_settings_use_popup );

					// HOOKABLE: allow use of other icon sets
					$provider_icon_markup = apply_filters( 'wsl_render_auth_widget_alter_provider_icon_markup', $provider_id, $provider_name, $authenticate_url );

					if( $provider_icon_markup != $provider_id )
					{
						echo $provider_icon_markup;
					}
					else
					{
						?>

						<a rel="nofollow" href="<?php echo $authenticate_url; ?>" title="<?php echo sprintf( _wsl__("Login with %s", 'wordpress-social-login'), $provider_name ) ?>" class="wp-social-login-provider wp-social-login-provider-<?php echo strtolower( $provider_id ); ?>" data-provider="<?php echo $provider_id ?>">
							<?php if( $social_icon_set == 'none' ){ echo apply_filters( 'wsl_render_auth_widget_alter_provider_name', $provider_name ); } else { ?><img alt="<?php echo $provider_name ?>" title="<?php echo sprintf( _wsl__("Login with %s", 'wordpress-social-login'), $provider_name ) ?>" src="<?php echo $assets_base_url . strtolower( $provider_id ) . '.png' ?>" /><?php } ?>

						</a>
						<?php
					}

					$no_idp_used = false;
				}
			}

			// no provider enabled?
			if( $no_idp_used )
			{
				?>
				<p style="background-color: #FFFFE0;border:1px solid #E6DB55;padding:5px;">
					<?php _wsl_e( '<strong>WordPress Social Login is not configured yet</strong>.<br />Please navigate to <strong>Settings &gt; WP Social Login</strong> to configure this plugin.<br />For more information, refer to the <a rel="nofollow" href="http://miled.github.io/wordpress-social-login">online user guide</a>.', 'wordpress-social-login') ?>.
				</p>
				<style>#wp-social-login-connect-with{display:none;}</style>
				<?php
			}
			?>

		</div>

		<div class="wp-social-login-widget-clearing"></div>

	</div>

	<?php
	// provide popup url for hybridauth callback
	if( $wsl_settings_use_popup == 1 )
	{
		?>
		<input type="hidden" id="wsl_popup_base_url" value="<?php echo esc_url( $authenticate_base_url ) ?>" />
		<input type="hidden" id="wsl_login_form_uri" value="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" />

		<?php
	}

	// HOOKABLE: This action runs just after generating the WSL Widget.
	do_action( 'wsl_render_auth_widget_end' );
	?>
	<!-- wsl_render_auth_widget -->

	<?php
	// Display WSL debugging area bellow the widget.
	// wsl_display_dev_mode_debugging_area(); // ! keep this line commented unless you know what you are doing :)

	echo ob_get_clean();
}

/**
 * Adds a section to the social login plugin settings for whitelist
 */
 function northeastern_social_login_whitelist_settings() {
	 ?>
	 <div class="stuffbox">
		 <h3>
			 <label><?php _wsl_e("User Account Whitelist", 'northeastern') ?></label>
		 </h3>
		 <div class="inside">
			 <p>
				 <?php _wsl_e('This will allow you to whitelist social accounts that are able to create accounts on the website.', 'wordpress-social-login') ?>.
			 </p>
			 <form method="post" id="wsl_whitelist_form" action="options-general.php?page=wordpress-social-login&wslp=tools" enctype="multipart/form-data">
				 <h4>Upload CSV</h4>
				 <p>Upload a CSV of Google Account Email Addresses or Twitter Usernames to bulk add to the whitelist. Select if you are whitelisting Twitter usernames or Google Accounts. Format the CSV with the username/email in the first column with no header. All other columns will be ignored.</p>
				 <select name="csvprovider">
					 <option value="twitter">Twitter</option>
					 <option value="google">Google</option>
				 </select>
				 <br>
				 <input type="file"
				        id="whitelistcsv" name="whitelistcsv"
				        accept="text/csv">
				 <br><br>
				 <h4>Add Twitter Whitelist Entries</h4>
				 <p>Add one username per line. Do not use @ signs on the twitter username.</p>
				 <textarea id="whitelist-twitter" name="whitelist-twitter" rows="10" cols="50"></textarea>
				 <br>

				 <br><br>
				 <h4>Add Google Whitelist Entries</h4>
				 <p>Add one email per line.</p>
				 <textarea id="whitelist-entries" name="whitelist-google" rows="10" cols="50"></textarea>
				 <br>
				 <input type="hidden" name="do" value="whitelist" />
				 <?php wp_nonce_field(); ?>

				 <input type="submit" class="button-primary" value="Add To Whitelist" />
			 </form>
		 </div>
	 </div>
	 <?php
 }

/**
 * Create the database table for the whitelist
 */
function northeastern_create_whitelist_db_table() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE `{$wpdb->prefix}wsl_login_whitelist` (
	  id int(11) NOT NULL AUTO_INCREMENT,
	  provider varchar(50) NOT NULL,
	  identifier varchar(255) NOT NULL,
	  UNIQUE KEY id (id),
	  KEY provider (provider)
	) $charset_collate;";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
}

/**
 * Hook into the wsl repair action to create db table
 */
function northeastern_wsl_do_repair() {

	northeastern_create_whitelist_db_table();

}

/**
 * Add a whiltelist entry to the database
 */
function northeastern_add_whitelist_entry( $provider, $identifier ) {
	global $wpdb;

	$wpdb->insert(
		$wpdb->prefix . 'wsl_login_whitelist',
		array(
			'provider'   => $provider,
			'identifier' => $identifier,
		),
		array(
			'%s',
			'%s',
		)

	);
}

/**
 * Process the whitelist database job
 */
function northeastern_wsl_do_whitelist_job() {

	if( isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'] ) ) {

		if( isset( $_REQUEST['do'] ) && 'whitelist' === $_REQUEST['do'] ) {

			// Process Twitter Whitelist Textarea
			$twitter_text = ( isset( $_REQUEST['whitelist-twitter'] ) && $_REQUEST['whitelist-twitter'] ) ? $_REQUEST['whitelist-twitter'] : null;
			if ( ! is_null( $twitter_text ) ) {
				$usernames = preg_split( '/\r\n|\r|\n/', $twitter_text );
				$usernames = array_unique( $usernames );
				foreach ( $usernames as $username ) {
					$username = str_replace( '@', '', $username );
					northeastern_add_whitelist_entry( 'twitter', sanitize_text_field( $username ) );
				}
			}

			// Process Google Whitelist Textarea
			$google_text = ( isset( $_REQUEST['whitelist-google'] ) && $_REQUEST['whitelist-google'] ) ? $_REQUEST['whitelist-google'] : null;
			if ( ! is_null( $google_text ) ) {
				$emails = preg_split( '/\r\n|\r|\n/', $google_text );
				$emails = array_unique( $emails );
				foreach ( $emails as $email ) {
					northeastern_add_whitelist_entry( 'google', sanitize_text_field( $email ) );
				}
			}


			// Process CSV Upload
			if ( isset( $_FILES['whitelistcsv'] ) && $_FILES['whitelistcsv'] ) {
				$files = $_FILES['whitelistcsv'];
				if ( $files['name'] ) {
					if ( isset( $files['error'] ) && $files['error'] ) {
						?>
						<div class="notice notice-error is-dismissible">
							<p>Error uploading CSV File</p>
						</div>
						<?php
						return;
					}

					$open = fopen( $files['tmp_name'], 'r' );

					if ( $open !== false ) {

						while ( ( $data = fgetcsv( $open, 0, ',' ) ) !== false ) {
							if ( isset( $data[0] ) && $data[0] ) {
								$identifier = $data[0];
								if ( 'twitter' === $_REQUEST['csvprovider'] ) {
									$identifier = str_replace( '@', '', $data );
								}
								northeastern_add_whitelist_entry( sanitize_text_field( $_REQUEST['csvprovider'] ), sanitize_text_field( $identifier ) );
							}
						}

					} else {
						?>
						<div class="notice notice-error is-dismissible">
							<p>Error reading CSV File</p>
						</div>
						<?php
						return;
					}
				}
			}

			?>
			<div class="notice notice-success is-dismissible">
				<p>Finished added entries to whitelist!</p>
			</div>
			<?php
			
		}
		
	}
	
}