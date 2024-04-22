<?php

defined( 'ABSPATH' ) or die();

/**
 * Gravity Forms Postmark API Library.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2017, Rocketgenius
 */
class GF_Postmark_API {

	/**
	 * Postmark account token.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $account_token Postmark account token.
	 */
	protected $account_token;

	/**
	 * Postmark API URL.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $api_url Postmark API URL.
	 */
	protected $api_url = 'https://api.postmarkapp.com/';

	/**
	 * Postmark server token.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $server_token Postmark server token.
	 */
	protected $server_token;

	/**
	 * Assign account token to API instance.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  string $account_token Postmark account token.
	 */
	public function set_account_token( $account_token ) {

		$this->account_token = $account_token;

	}

	/**
	 * Assign server token to API instance.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  string $server_token Postmark server token.
	 */
	public function set_server_token( $server_token ) {

		$this->server_token = $server_token;

	}

	/**
	 * Get a list of domains associated with the account.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  int $count  Number of records to return per request. Defaults to 500.
	 * @param  int $offset Number of records to skip.
	 *
	 * @uses   GF_Postmark_API::make_request()
	 *
	 * @return array|WP_Error
	 */
	public function get_domains( $count = 500, $offset = 0 ) {

		return $this->make_request( 'domains', array(
			'count'  => $count,
			'offset' => $offset,
		), 'account', 'GET', 'Domains' );

	}


	/**
	 * Get a list of sender signatures associated with the account.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  int $count  Number of records to return per request. Defaults to 500.
	 * @param  int $offset Number of records to skip.
	 *
	 * @uses   GF_Postmark_API::make_request()
	 *
	 * @return array|WP_Error
	 */
	public function get_sender_signatures( $count = 500, $offset = 0 ) {

		return $this->make_request( 'senders', array(
			'count'  => $count,
			'offset' => $offset,
		), 'account', 'GET', 'SenderSignatures' );

	}

	/**
	 * Get details about the current Postmark server.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GF_Postmark_API::make_request()
	 *
	 * @return array|WP_Error
	 */
	public function get_current_server() {

		return $this->make_request( 'server' );

	}

	/**
	 * Get statistics of outbound emails.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $from_date Filter stats starting from the date specified (inclusive).
	 * @param string $to_date   Filter stats up to the date specified (inclusive).
	 *
	 * @uses   GF_Postmark_API::make_request()
	 *
	 * @return array|WP_Error
	 */
	public function get_outbound_stats( $from_date, $to_date ) {

		return $this->make_request( 'stats/outbound', array( 'fromdate' => $from_date, 'todate' => $to_date ) );

	}

	/**
	 * Send a single email.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $email Email contents.
	 *
	 * @uses   GF_Postmark_API::make_request()
	 *
	 * @return array|WP_Error
	 */
	public function send_email( $email ) {

		return $this->make_request( 'email', $email, 'server', 'POST' );

	}





	// # REQUEST METHODS -----------------------------------------------------------------------------------------------

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param string $action     Request action.
	 * @param array  $options    Request options.
	 * @param string $auth_type  Authentication token to use. Defaults to server.
	 * @param string $method     HTTP method. Defaults to GET.
	 * @param string $return_key Array key from response to return. Defaults to null (return full response).
	 *
	 * @return array|string|WP_Error
	 */
	private function make_request( $action, $options = array(), $auth_type = 'server', $method = 'GET', $return_key = null ) {

		// Build request options string.
		$request_options = 'GET' === $method ? '?' . http_build_query( $options ) : null;

		// Build request URL.
		$request_url = $this->api_url . $action . $request_options;

		// Build request arguments.
		$request_args = array(
			'body'      => 'GET' !== $method ? json_encode( $options ) : '',
			'method'    => $method,
			'timeout'   => 30,
			'sslverify' => false,
			'headers'   => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
		);

		// Add auth token based on auth type.
		if ( 'server' === $auth_type ) {
			$request_args['headers']['X-Postmark-Server-Token'] = $this->server_token;
		}
		if ( 'account' === $auth_type ) {
			$request_args['headers']['X-Postmark-Account-Token'] = $this->account_token;
		}

		// Execute API request.
		$response = wp_remote_request( $request_url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Convert JSON response to array.
		$response = json_decode( $response['body'], true );

		if ( isset( $response['ErrorCode'] ) && $response['ErrorCode'] !== 0 ) {
			return new WP_Error( $response['ErrorCode'], $response['Message'] );
		}

		// If a return key is defined and array item exists, return it.
		if ( ! empty( $return_key ) && isset( $response[ $return_key ] ) ) {
			return $response[ $return_key ];
		}

		return $response;

	}

}
