<?php
/**
 * Customizations to BuddyPress Members.
 *
 * @package Hc_Custom
 */

/**
 * Disable follow button for non-society-members.
 */
function hcommons_add_non_society_member_follow_button() {
	if ( ! is_super_admin() && hcommons_check_non_member_active_session() ) {
		echo '<div class="disabled-button">Follow</div>';
	}
}
add_action( 'bp_directory_members_actions', 'hcommons_add_non_society_member_follow_button' );

/**
 * Add follow disclaimer for non-society-members.
 */
function hcommons_add_non_society_member_disclaimer_member() {
	if ( ! is_super_admin() && hcommons_check_non_member_active_session() ) {
		printf(
			'<div class="non-member-disclaimer">Only %s members can follow others from here.<br>To follow these members, go to <a href="%s">Humanities Commons</a>.</div>',
			strtoupper( Humanities_Commons::$society_id ),
			get_site_url( getenv( 'HC_ROOT_BLOG_ID' ) )
		);
	}
}
add_action( 'bp_before_directory_members_content', 'hcommons_add_non_society_member_disclaimer_member' );

/**
 * Add custom member type meta box, included ahead of the one from core
 * @author Tanner Moushey
 */
function hcommons_admin_member_type_metabox() {
	global $wp_meta_boxes;

	$member_types = bp_get_member_types();
	if ( ! empty( $member_types ) ) {
		add_meta_box(
			'bp_members_admin_member_type',
			_x( 'Member Type', 'members user-admin edit screen', 'buddypress' ),
			'hcommons_user_admin_member_type_metabox_cb',
			get_current_screen()->id,
			'side',
			'core'
		);
	}
}
add_action( 'bp_members_admin_xprofile_metabox', 'hcommons_admin_member_type_metabox', 10 );

/**
 * Meta box callback
 *
 * @param null $user
 *
 * @author Tanner Moushey
 */
function hcommons_user_admin_member_type_metabox_cb( $user = null ) {

	// Bail if no user ID.
	if ( empty( $user->ID ) ) {
		return;
	}

	$types        = bp_get_member_types( array(), 'objects' );
	$current_type = bp_get_member_type( $user->ID, false );
	?>

	<label for="bp-members-profile-member-type" class="screen-reader-text"><?php
		/* translators: accessibility text */
		esc_html_e( 'Select member type', 'buddypress' );
		?></label>
	<ul>
		<?php foreach ( $types as $type ) : ?>
			<li><label><input type="checkbox" name="bp-members-profile-member-type[]" value="<?php echo esc_attr( $type->name ) ?>" <?php checked( in_array( $type->name, $current_type ) ) ?> /> <?php echo esc_html( $type->labels['singular_name'] ) ?></label></li>
		<?php endforeach; ?>
	</ul>

	<?php

	wp_nonce_field( 'bp-member-type-change-' . $user->ID, 'hc-bp-member-type-nonce' );

}

/**
 * Process member types
 *
 * @author Tanner Moushey
 */
function hcommons_process_member_type_update() {
	if ( ! isset( $_POST['hc-bp-member-type-nonce'] ) || ! isset( $_POST['bp-members-profile-member-type'] ) ) {
		return;
	}

	$user_id = (int) get_current_user_id();

	// We'll need a user ID when not on self profile.
	if ( ! empty( $_GET['user_id'] ) ) {
		$user_id = (int) $_GET['user_id'];
	}

	check_admin_referer( 'bp-member-type-change-' . $user_id, 'hc-bp-member-type-nonce' );

	// Permission check.
	if ( ! current_user_can( 'bp_moderate' ) && $user_id != bp_loggedin_user_id() ) {
		return;
	}

	// Member type string must either reference a valid member type, or be empty.
	$member_types = array_map( 'stripslashes', $_POST['bp-members-profile-member-type'] );

	foreach ( $member_types as $key => $member_type ) {
		if ( ! empty( $member_type ) && ! bp_get_member_type_object( $member_type ) ) {
			continue;
		}

		/*
		 * If an invalid member type is passed, someone's doing something
		 * fishy with the POST request, so we can fail silently.
		 */
		bp_set_member_type( $user_id, $member_type, $key != 0 );
	}
}
add_action( 'bp_members_admin_load', 'hcommons_process_member_type_update' );
