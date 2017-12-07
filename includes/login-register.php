<?php
/**
 * Login / Register Functions
 *
 * @package     Give
 * @subpackage  Functions/Login
 * @copyright   Copyright (c) 2016, WordImpress
 * @license     https://opensource.org/licenses/gpl-license GNU Public License
 * @since       1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Login Form
 *
 * @since 1.0
 * @global       $give_login_redirect
 * @global       $give_logout_redirect
 *
 * @param string $login_redirect  Login redirect page URL
 * @param string $logout_redirect Logout redirect page URL
 *
 * @return string Login form
 */
function give_login_form( $login_redirect = '', $logout_redirect = '' ) {
	if ( empty( $login_redirect ) ) {
		$login_redirect = add_query_arg( 'give-login-success', 'true', give_get_current_page_url() );
	}

	if ( empty( $logout_redirect ) ) {
		$logout_redirect = add_query_arg( 'give-logout-success', 'true', give_get_current_page_url() );
	}

	// Add user_logout action to logout url.
	$logout_redirect = add_query_arg(
		array(
			'give_action'          => 'user_logout',
			'give_logout_nonce'    => wp_create_nonce( 'give-logout-nonce' ),
			'give_logout_redirect' => urlencode( $logout_redirect ),
		),
		home_url( '/' )
	);

	ob_start();

	give_get_template(
		'shortcode-login',
		array(
			'give_login_redirect'  => $login_redirect,
			'give_logout_redirect' => $logout_redirect,
		)
	);

	return apply_filters( 'give_login_form', ob_get_clean() );
}

/**
 * Registration Form
 *
 * @since 2.0
 * @global       $give_register_redirect
 *
 * @param string $redirect Redirect page URL
 *
 * @return string Register form
 */
function give_register_form( $redirect = '' ) {
	if ( empty( $redirect ) ) {
		$redirect = give_get_current_page_url();
	}

	ob_start();

	if ( ! is_user_logged_in() ) {
		give_get_template(
			'shortcode-register',
			array(
				'give_register_redirect' => $redirect,
			)
		);
	}

	return apply_filters( 'give_register_form', ob_get_clean() );
}

/**
 * Process Login Form
 *
 * @since 1.0
 *
 * @param array $data Data sent from the login form
 *
 * @return void
 */
function give_process_login_form( $data ) {
	if ( wp_verify_nonce( $data['give_login_nonce'], 'give-login-nonce' ) ) {
		$user_data = get_user_by( 'login', $data['give_user_login'] );
		if ( ! $user_data ) {
			$user_data = get_user_by( 'email', $data['give_user_login'] );
		}
		if ( $user_data ) {
			$user_ID    = $user_data->ID;
			$user_email = $user_data->user_email;
			if ( wp_check_password( $data['give_user_pass'], $user_data->user_pass, $user_ID ) ) {
				give_log_user_in( $user_data->ID, $data['give_user_login'], $data['give_user_pass'] );
			} else {
				give_set_error( 'password_incorrect', __( 'The password you entered is incorrect.', 'give' ) );
			}
		} else {
			give_set_error( 'username_incorrect', __( 'The username you entered does not exist.', 'give' ) );
		}
		// Check for errors and redirect if none present
		$errors = give_get_errors();
		if ( ! $errors ) {
			$redirect = apply_filters( 'give_login_redirect', $data['give_login_redirect'], $user_ID );
			wp_redirect( $redirect );
			give_die();
		}
	}
}

add_action( 'give_user_login', 'give_process_login_form' );


/**
 * Process User Logout
 *
 * @since 1.0
 *
 * @param array $data Data sent from the give login form page
 *
 * @return void
 */
function give_process_user_logout( $data ) {
	if ( wp_verify_nonce( $data['give_logout_nonce'], 'give-logout-nonce' ) && is_user_logged_in() ) {

		// Prevent occurring of any custom action on wp_logout.
		remove_all_actions( 'wp_logout' );

		/**
		 * Fires before processing user logout.
		 *
		 * @since 1.0
		 */
		do_action( 'give_before_user_logout' );

		// Logout user.
		wp_logout();

		/**
		 * Fires after processing user logout.
		 *
		 * @since 1.0
		 */
		do_action( 'give_after_user_logout' );

		wp_redirect( $data['give_logout_redirect'] );
		give_die();
	}
}

add_action( 'give_user_logout', 'give_process_user_logout' );

/**
 * Log User In
 *
 * @since 1.0
 *
 * @param int    $user_id    User ID
 * @param string $user_login Username
 * @param string $user_pass  Password
 *
 * @return bool
 */
function give_log_user_in( $user_id, $user_login, $user_pass ) {

	if ( $user_id < 1 ) {
		return false;
	}

	wp_set_auth_cookie( $user_id );
	wp_set_current_user( $user_id, $user_login );

	/**
	 * Fires after the user has successfully logged in.
	 *
	 * @since 1.0
	 *
	 * @param string $user_login Username.
	 * @param WP_User $$user      WP_User object of the logged-in user.
	 */
	do_action( 'wp_login', $user_login, get_userdata( $user_id ) );

	/**
	 * Fires after give user has successfully logged in.
	 *
	 * @since 1.0
	 *
	 * @param int    $$user_id   User id.
	 * @param string $user_login Username.
	 * @param string $user_pass  User password.
	 */
	do_action( 'give_log_user_in', $user_id, $user_login, $user_pass );
}


/**
 * Process Register Form
 *
 * @since 2.0
 *
 * @param array $data Data sent from the register form
 *
 * @return bool
 */
function give_process_register_form( $data ) {

	if ( is_user_logged_in() ) {
		return false;
	}

	if ( empty( $_POST['give_register_submit'] ) ) {
		return false;
	}

	/**
	 * Fires before processing user registration.
	 *
	 * @since 1.0
	 */
	do_action( 'give_pre_process_register_form' );

	if ( empty( $data['give_user_login'] ) ) {
		give_set_error( 'empty_username', esc_html__( 'Invalid username.', 'give' ) );
	}

	if ( username_exists( $data['give_user_login'] ) ) {
		give_set_error( 'username_unavailable', esc_html__( 'Username already taken.', 'give' ) );
	}

	if ( ! validate_username( $data['give_user_login'] ) ) {
		give_set_error( 'username_invalid', esc_html__( 'Invalid username.', 'give' ) );
	}

	if ( email_exists( $data['give_user_email'] ) ) {
		give_set_error( 'email_unavailable', esc_html__( 'Email address already taken.', 'give' ) );
	}

	if ( empty( $data['give_user_email'] ) || ! is_email( $data['give_user_email'] ) ) {
		give_set_error( 'email_invalid', esc_html__( 'Invalid email.', 'give' ) );
	}

	if ( ! empty( $data['give_payment_email'] ) && $data['give_payment_email'] != $data['give_user_email'] && ! is_email( $data['give_payment_email'] ) ) {
		give_set_error( 'payment_email_invalid', esc_html__( 'Invalid payment email.', 'give' ) );
	}

	if ( empty( $_POST['give_user_pass'] ) ) {
		give_set_error( 'empty_password', esc_html__( 'Please enter a password.', 'give' ) );
	}

	if ( ( ! empty( $_POST['give_user_pass'] ) && empty( $_POST['give_user_pass2'] ) ) || ( $_POST['give_user_pass'] !== $_POST['give_user_pass2'] ) ) {
		give_set_error( 'password_mismatch', esc_html__( 'Passwords don\'t match.', 'give' ) );
	}

	/**
	 * Fires while processing user registration.
	 *
	 * @since 1.0
	 */
	do_action( 'give_process_register_form' );

	// Check for errors and redirect if none present
	$errors = give_get_errors();

	if ( empty( $errors ) ) {

		$redirect = apply_filters( 'give_register_redirect', $data['give_redirect'] );

		give_register_and_login_new_user( array(
			'user_login'      => $data['give_user_login'],
			'user_pass'       => $data['give_user_pass'],
			'user_email'      => $data['give_user_email'],
			'user_registered' => date( 'Y-m-d H:i:s' ),
			'role'            => get_option( 'default_role' ),
		) );

		wp_redirect( $redirect );
		give_die();
	}
}

add_action( 'give_user_register', 'give_process_register_form' );


function give_email_access_login() {

	// Form submission.
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'give' ) ) {
		return false;
	}

	if ( empty( $email ) ) {
		give_set_error( 'give_empty_email', __( 'Please enter the email address you used for your donation.', 'give' ) );
	}

	$recaptcha_key    = give_get_option( 'recaptcha_key' );
	$recaptcha_secret = give_get_option( 'recaptcha_secret' );
	$enable_recaptcha = ! empty( $recaptcha_key ) && ! empty( $recaptcha_secret ) ? true : false;
	$access_token     = ! empty( $_GET['payment_key'] ) ? $_GET['payment_key'] : '';
	$email            = isset( $_POST['give_email'] ) ? give_clean( $_POST['give_email'] ) : '';

	// Use reCAPTCHA.
	if ( $enable_recaptcha ) {

		$args = array(
			'secret'   => $recaptcha_secret,
			'response' => $_POST['g-recaptcha-response'],
			'remoteip' => $_POST['give_ip'],
		);

		if ( ! empty( $args['response'] ) ) {
			$request = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
				'body' => $args,
			) );
			if ( ! is_wp_error( $request ) || 200 == wp_remote_retrieve_response_code( $request ) ) {

				$response = json_decode( $request['body'], true );

				// reCAPTCHA fail.
				if ( ! $response['success'] ) {
					give_set_error( 'give_recaptcha_test_failed', apply_filters( 'give_recaptcha_test_failed_message', __( 'reCAPTCHA test failed.', 'give' ) ) );
				}
			} else {

				// Connection issue.
				give_set_error( 'give_recaptcha_connection_issue', apply_filters( 'give_recaptcha_connection_issue_message', __( 'Unable to connect to reCAPTCHA server.', 'give' ) ) );

			}  // End if().
		} else {

			give_set_error( 'give_recaptcha_failed', apply_filters( 'give_recaptcha_failed_message', __( 'It looks like the reCAPTCHA test has failed.', 'give' ) ) );

		}  // End if().
	}  // End if().

	// If no errors or only expired token key error - then send email.
	if ( ! give_get_errors() ) {

		$donation_ids   = array();
		$donation_match = false;
		$donor          = Give()->donors->get_donor_by( 'email', $email );

		// Verify that donor object is present and donor is connected with its user profile or not.
		if ( ! $access_token && is_object( $donor ) ) {

			Give()->session->set( 'receipt_access', $donation->key );

		} else if ( $access_token && is_object( $donor ) ) {

			// Scenario: Donation - Receipt Access.
			if ( ! empty( $donor->payment_ids ) ) {
				$donation_ids = explode( ',', $donor->payment_ids );
			}

			foreach ( $donation_ids as $donation_id ) {
				$donation = new Give_Payment( $donation_id );

				// Make sure Donation Access Token matches with donation details of donor whose email is provided.
				if ( $access_token === $donation->key ) {
					$donation_match = true;
				}

			}

			// Do required based on Payment Key and Access Token Match.
			if ( ! $donation_match ) {
				give_set_error( 'give_email_access_token_not_match', __( 'It looks like that email address provided and access token of the link does not match.', 'give' ) );
			} else {
				Give()->session->set( 'receipt_access', $access_token );
				wp_safe_redirect( esc_url( get_permalink( give_get_option( 'history_page' ) ) . '?payment_key=' . $access_token ) );
			}

		} else {
			give_set_error( 'give-no-donations', __( 'We are unable to fetch donations from the email you entered. Please try again.', 'give' ) );
		}  // End if().
	}


}

add_action( 'give_email_access_form_login', 'give_email_access_login' );