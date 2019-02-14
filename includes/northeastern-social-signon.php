<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NEU_Social_Signon {
	/**
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Start up
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'login_footer', array( $this, 'social_login_footer' ) );
		add_filter( 'nsl_is_register_allowed', [ $this, 'check_social_whitelist' ], 10, 2 );
		add_action( 'nsl_login', [ $this, 'add_to_networks' ] );

		$this->options = get_option( 'neu_social' );
	}

	/**
	 * Add options page
	 */
	public function add_plugin_page() {
		// This page will be under "Settings"
		add_options_page(
			'NEU Social Whitelist',
			'NEU Whitelist',
			'manage_options',
			'neu-social-whitelist',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Options page callback
	 */
	public function create_admin_page() {
		?>
		<div class="wrap">
			<h1>NEU Social Settings</h1>
			<form method="post" action="options.php">
				<?php
				// This prints out all hidden setting fields
				settings_fields( 'neu_social_whitelist' );
				do_settings_sections( 'neu-social-whitelist' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			'neu_social_whitelist', // Option group
			'neu_social', // Option name
			array( $this, 'sanitize' ) // Sanitize
		);

		add_settings_section(
			'setting_section_id', // ID
			'Whitelist', // Title
			array( $this, 'print_section_info' ), // Callback
			'neu-social-whitelist' // Page
		);

		add_settings_field(
			'whitelist', // ID
			'Whitelist', // Title
			array( $this, 'whitelist_callback' ), // Callback
			'neu-social-whitelist', // Page
			'setting_section_id' // Section
		);

	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 *
	 * @return array
	 */
	public function sanitize( $input ) {
		$new_input = array();
		if ( isset( $input['whitelist'] ) ) {
			$new_input['whitelist'] = esc_textarea( $input['whitelist'] );
		}

		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		print '';
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function whitelist_callback() {
		?>
		<p>
			<label for="whitelist">Enter the email addresses to allow to sign up through the social login. One email per line.</label>
		</p>
		<p>
			<textarea name="neu_social[whitelist]" rows="10" cols="50" id="whitelist" class="large-text code"><?php echo isset( $this->options['whitelist'] ) ? esc_attr( $this->options['whitelist'] ) : ''; ?></textarea>
		</p>
		<?php
	}

	/**
	 * @param                       $retval
	 * @param NextendSocialProvider $provider
	 *
	 * @return mixed
	 * @author Tanner Moushey
	 */
	public function check_social_whitelist( $retval, $provider ) {
		if ( ! $email = $provider->getAuthUserData( 'email' ) ) {
			return false;
		}

		if ( ! $whitelist = array_map( 'trim', explode( PHP_EOL, $this->options['whitelist'] ) ) ) {
			return false;
		}

		if ( ! in_array( $email, $whitelist ) ) {
			return false;
		}

		return $retval;
	}

	public function social_login_footer() {
		?>
		<style>
			#nsl-custom-login-form-main #shibboleth_login a {
				display: block;
				text-align: center;
				color: white;
				border-radius: 3px;
				text-decoration: none;
				font-family: Helvetica, Arial, sans-serif;
			}
			#nsl-custom-login-form-main div.nsl-container-block,
			div.nsl-container-block .nsl-container-buttons a {
				max-width: none !important;
			}
		</style>

		<script>
          jQuery('#shibboleth_login').find('a').html(
            '<span class="nsl-button nsl-button-default nsl-button-google" data-skin="uniform" style="background-color:#333;"><span class="nsl-button-label-container">Continue with <b>NEU</b></span></span>');

          if (jQuery('#nsl-custom-login-form-main .nsl-container').length) {
            jQuery('#nsl-custom-login-form-main .nsl-container').prepend(jQuery('#shibboleth_login'));
          }
		</script>
		<?php
	}

	/**
	 * Add social members to network
	 *
	 * @param $user_id
	 *
	 * @author Tanner Moushey
	 */
	public function add_to_network( $user_id ) {
		$types = [ 'next' ];

		foreach( $types as $type ) {
			bp_set_member_type( $user_id, $type, true );
		}
	}
}

$neu_social_signon = new NEU_Social_Signon();