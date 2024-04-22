<?php

defined( 'ABSPATH' ) or die();

GFForms::include_addon_framework();

/**
 * Gravity Forms Postmark Add-On.
 *
 * @since     1.0
 * @package   GravityForms
 * @author    Rocketgenius
 * @copyright Copyright (c) 2018, Rocketgenius
 */
class GF_Postmark extends GFAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @since  1.0
	 * @access private
	 * @var    object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Postmark Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_version Contains the version, defined from postmark.php
	 */
	protected $_version = GF_POSTMARK_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_min_gravityforms_version The minimum version required.
	 */
	protected $_min_gravityforms_version = '2.2.3.8';

	/**
	 * Defines the plugin slug.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_slug The slug used for this plugin.
	 */
	protected $_slug = 'gravityformspostmark';

	/**
	 * Defines the main plugin file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_path The path to the main plugin file, relative to the plugins folder.
	 */
	protected $_path = 'gravityformspostmark/postmark.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_full_path The full path.
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string The URL of the Add-On.
	 */
	protected $_url = 'https://www.gravityforms.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_title The title of the Add-On.
	 */
	protected $_title = 'Gravity Forms Postmark Add-On';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_short_title The short title.
	 */
	protected $_short_title = 'Postmark';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    bool
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Defines the capability needed to access the Add-On settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_settings_page The capability needed to access the Add-On settings page.
	 */
	protected $_capabilities_settings_page = 'gravityforms_postmark';

	/**
	 * Defines the capability needed to access the Add-On form settings page.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_form_settings The capability needed to access the Add-On form settings page.
	 */
	protected $_capabilities_form_settings = 'gravityforms_postmark';

	/**
	 * Defines the capability needed to uninstall the Add-On.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    string $_capabilities_uninstall The capability needed to uninstall the Add-On.
	 */
	protected $_capabilities_uninstall = 'gravityforms_postmark_uninstall';

	/**
	 * Defines the capabilities needed for the Postmark Add-On
	 *
	 * @since  1.0
	 * @access protected
	 * @var    array $_capabilities The capabilities needed for the Add-On
	 */
	protected $_capabilities = array( 'gravityforms_postmark', 'gravityforms_postmark_uninstall' );

	/**
	 * Contains an instance of the Postmark API library, if available.
	 *
	 * @since  1.0
	 * @access protected
	 * @var    GF_Postmark_API $api If available, contains an instance of the Postmark API library.
	 */
	protected $api = null;

	/**
	 * Whether Add-on framework has settings renderer support or not, settings renderer was introduced in Gravity Forms 2.5
	 *
	 * @since 1.3
	 *
	 * @var bool
	 */
	protected $_has_settings_renderer;

	/**
	 * Cached API credentials.
	 *
	 * @since 1.4
	 *
	 * @var array
	 */
	private $sender_signatures = array();

	/**
	 * Get instance of this class.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return GF_Postmark
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Autoload the required libraries.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFAddOn::is_gravityforms_supported()
	 */
	public function pre_init() {

		parent::pre_init();

		if ( $this->is_gravityforms_supported() ) {

			// Load the Postmark API library.
			if ( ! class_exists( 'GF_Postmark_API' ) ) {
				require_once( 'includes/class-gf-postmark-api.php' );
			}

		}

		// Add Postmark as a notification send via type.
		add_filter( 'gform_notification_services', array( $this, 'add_notification_service' ) );

		// Add Postmark notification fields.
		if ( version_compare( GFForms::$version, '2.5-dev-1', '>=' ) ) {

			add_filter( 'gform_notification_settings_fields', array( $this, 'filter_gform_notification_settings_fields' ), 10, 3 );

		} else {

			add_filter( 'gform_notification_ui_settings', array( $this, 'add_notification_fields' ), 10, 4 );

		}

		add_filter( 'gform_pre_notification_save', array( $this, 'save_notification_fields' ), 10, 2 );


	}

	/**
	 * Register needed hooks.
	 *
	 * @since 1.0
	 * @access public
	 */
	public function init() {

		parent::init();

		// Handle Postmark notifications.
		add_filter( 'gform_pre_send_email', array( $this, 'maybe_send_email' ), 19, 4 );

		// Check if settings renderer is supported.
		$this->_has_settings_renderer = $this->is_gravityforms_supported( '2.5-beta' );

	}

	/**
	 * Return the plugin's icon for the plugin/form settings menu.
	 *
	 * @since 1.1
	 *
	 * @return string
	 */
	public function get_menu_icon() {

		return file_get_contents( $this->get_base_path() . '/images/menu-icon.svg' );

	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Define plugin settings fields.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFAddOn::add_field_after()
	 * @uses   GF_Postmark::initialize_api()
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'description' => sprintf(
					'<p>%s</p>',
					sprintf(
						esc_html__( 'Postmark makes it easy to reliably send and track email notifications. If you don\'t have a Postmark account, you can %1$ssign up for one here%2$s. Once you have signed up, you can %3$sfind your Account and Server tokens here%4$s.', 'gravityformspostmark' ),
						'<a href="https://postmarkapp.com" target="_blank">', '</a>',
						'<a href="https://account.postmarkapp.com/api_tokens" target="_blank">', '</a>'
					)
				),
				'fields' => array(
					array(
						'name'              => 'accountToken',
						'label'             => esc_html__( 'Account API Token', 'gravityformspostmark' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'validate_account_token' ),
					),
					array(
						'name'              => 'serverToken',
						'label'             => esc_html__( 'Server API Token', 'gravityformspostmark' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'validate_server_token' ),
					),
					array(
						'name'  => 'serverStats',
						'label' => esc_html__( 'Email Statistics', 'gravityformspostmark' ),
						'type'  => 'server_stats',
					),
					array(
						'type'     => 'save',
						'messages' => array(
							'success' => esc_html__( 'Postmark settings have been updated.', 'gravityformspostmark' ),
						),
					),
				),
			),
		);

	}

	/**
	 * Renders and initializes a field that displays the statistics for the Postmark Server API token for the past 30 days.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $field Field settings.
	 * @param bool  $echo  Display field. Defaults to true.
	 *
	 * @uses   GF_Postmark_API::get_outbound_stats()
	 *
	 * @return string
	 */
	public function settings_server_stats( $field, $echo = true ) {
		$html  = '';
		$stats = array();

		if ( ! $this->initialize_api() ) {
			$html = sprintf(
				'<p class="gform-settings-validation__error">%s</p>',
				esc_html__( 'Error Initializing Postmark API.', 'gravityformspostmark' )
			);
		} else {
			// Get outbound stats.
			$stats = $this->api->get_outbound_stats( date( 'Y-m-d', strtotime( '30 days ago' ) ), date( 'Y-m-d' ) );

			if ( is_wp_error( $stats ) ) {
				$this->log_error( __METHOD__ . '(): Unable to retrieve stats; ' . $stats->get_error_message() );
				$html = sprintf(
					'<p class="gform-settings-validation__error">%s</p>',
					esc_html__( 'Error retrieving email statistics.', 'gravityformspostmark' )
				);
			}
		}

		// Prepare server stats table if stats exist.
		if ( ! empty( $stats ) ) {
			$html  = '<table id="gform_postmark_server_stats">';
			$html .= '<tr><th>' . esc_html__( 'Sent', 'gravityformspostmark' ) . '</th><td>' . rgar( $stats, 'Sent' ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'Bounced', 'gravityformspostmark' ) . '</th><td>' . rgar( $stats, 'Bounced' ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'Spam Complaints', 'gravityformspostmark' ) . '</th><td>' . rgar( $stats, 'SpamComplaints' ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'Opens', 'gravityformspostmark' ) . '</th><td>' . rgar( $stats, 'Opens' ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'Unique Opens', 'gravityformspostmark' ) . '</th><td>' . rgar( $stats, 'UniqueOpens' ) . '</td></tr>';
			$html .= '<tr><th>' . esc_html__( 'Tracked', 'gravityformspostmark' ) . '</th><td>' . rgar( $stats, 'Tracked' ) . '</td></tr>';
			$html .= '</table>';
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}


	// # NOTIFICATIONS -------------------------------------------------------------------------------------------------

	/**
	 * Add Postmark as a notification service.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array $services Existing notification services.
	 *
	 * @uses   GFAddOn::get_base_url()
	 * @uses   GF_Postmark::initialize_api()
	 *
	 * @return array
	 */
	public function add_notification_service( $services = array() ) {

		// If running GF prior to 2.4, check that API is initialized.
		if ( version_compare( GFFormsModel::get_database_version(), '2.4-beta-2', '<' ) && ! $this->initialize_api() ) {
			return $services;
		}

		// Add service.
		$services['postmark'] = array(
			'label'            => esc_html__( 'Postmark', 'gravityformspostmark' ),
			'image'            => $this->get_base_url() . '/images/icon.svg',
			'disabled'         => ! $this->initialize_api(),
			'disabled_message' => sprintf(
				esc_html__( 'You must %sauthenticate with Postmark%s before sending emails using their service.', 'gravityformspostmark' ),
				"<a href='" . esc_url( admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) ) . "'>",
				'</a>'
			),
		);

		return $services;

	}

	/**
	 * Checks if postmark is the selected notification service.
	 *
	 * @since 1.3
	 *
	 * @param array $notification Current notification being processed.
	 *
	 * @return bool
	 */
	private function is_postmark_selected( $notification = array() ) {
		if ( $this->_has_settings_renderer ) {
			return empty( $_POST ) ? GFNotification::get_settings_renderer()->get_value( 'service' ) === 'postmark' : rgpost( '_gform_setting_service' ) === 'postmark';
		}

		return rgpost( 'gform_notification_service' ) ? 'postmark' === rgpost( 'gform_notification_service' ) : 'postmark' === rgar( $notification, 'service' );

	}

	/**
	 * Add Postmark notification fields.
	 *
	 * @since 1.1
	 *
	 * @param array $fields       An array of settings for the notification UI.
	 * @param array $notification The current notification object being edited.
	 * @param array $form         The current form object to which the notification being edited belongs.
	 *
	 * @return array
	 */
	public function filter_gform_notification_settings_fields( $fields, $notification, $form ) {

		// If Postmark is not the selected service, return.
		if ( ! $this->is_postmark_selected() ) {
			return $fields;
		}

		// Prepare signatures array.
		$signature_choices = array();

		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Could not initialize API.' );
			$signature_choices[] = array(
				'label' => esc_html__( 'Error Initializing Postmark API.', 'gravityformspostmark' ),
				'value' => '',
			);
		} else {
			// Get sender signatures.
			$sender_signatures = $this->api->get_sender_signatures();

			if ( is_wp_error( $sender_signatures ) ) {
				// Log that sender signatured could not be retrieved.
				$this->log_error( __METHOD__ . '(): Unable to retrieve sender signatures; ' . $sender_signatures->get_error_message() );

				$signature_choices[] = array(
					'label' => esc_html__( 'Unable to retrieve sender signatures.', 'gravityformspostmark' ),
					'value' => '',
				);
			} else {
				// Prepare signatures as choices.
				foreach ( $sender_signatures as $sender_signature ) {
					$signature_choices[] = array(
						'label' => esc_html( $sender_signature['EmailAddress'] ),
						'value' => esc_attr( $sender_signature['EmailAddress'] ),
					);
				}
			}
		}

		// Remove From Name field.
		$fields = GFNotification::get_settings_renderer()->remove_field( 'fromName', $fields );

		// Get existing From Address field.
		$from_field = GFNotification::get_settings_renderer()->get_field( 'from', $fields );

		// Prepare updated From Address field.
		$from_field = array(
			'name'       => $from_field['name'],
			'label'      => $from_field['label'],
			'tooltip'    => rgar( $from_field, 'tooltip' ),
			'type'       => 'select',
			'choices'    => $signature_choices,
			'no_choices' => sprintf(
				// translators: Placeholders represent opening and closing link tags.
				esc_html__( 'To setup a notification using Postmark, you must define at least one %1$sSender Signature%3$s. You can learn about Sender Signatures in the %2$sPostmark Help Center%3$s.', 'gravityformspostmark' ),
				'<a href="https://postmarkapp.com/signatures" target="_blank">',
				'<a href="http://support.postmarkapp.com/category/45-category" target="_blank">',
				'</a>'
			),
		);

		// Replace field.
		$fields = GFNotification::get_settings_renderer()->replace_field( 'from', $from_field, $fields );

		// Prepare Email Tracking setting.
		$tracking_field = array(
			'name'    => 'postmarkTrack',
			'type'    => 'checkbox',
			'label'   => esc_html__( 'Email Tracking', 'gravityformsmpostmark' ),
			'choices' => array(
				array(
					'name'  => 'postmarkTrackOpens',
					'label' => esc_html__( 'Enable open tracking for this notification', 'gravityformsmpostmark' ),
				),
			),
		);

		// Add Email Tracking setting.
		$fields = GFNotification::get_settings_renderer()->add_field( 'conditionalLogic', $tracking_field, 'before', $fields );

		return $fields;
	}

	/**
	 * Add Postmark notification fields.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $ui_settings  An array of settings for the notification UI.
	 * @param array $notification The current notification object being edited.
	 * @param array $form         The current form object to which the notification being edited belongs.
	 *
	 * @uses GF_Postmark::initialize_api()
	 * @uses GF_Postmark_API::get_sender_signatures()
	 *
	 * @return array
	 */
	public function add_notification_fields( $ui_settings, $notification, $form ) {

		// If Postmark is not the selected notification service or the API is not initialized, return default UI settings.
		if ( ! $this->is_postmark_selected( $notification ) || ! $this->initialize_api() ) {
			return $ui_settings;
		}

		// Get sender signatures.
		$sender_signatures = $this->api->get_sender_signatures();

		if ( is_wp_error( $sender_signatures ) ) {
			$this->log_error( __METHOD__ . '(): Unable to retrieve sender signatures; ' . $sender_signatures->get_error_message() );

			return $ui_settings;
		}

		// Remove the "From Name" field.
		unset( $ui_settings['notification_from_name'] );

		// Build from address row.
		$from_address  = '<tr valign="top">';
		$from_address .= '<th scope="row">';
		$from_address .= sprintf( '<label for="gform_notification_from">%s %s</label>', esc_html__( 'From Email', 'gravityforms' ), gform_tooltip( 'notification_from_email', '', true ) );
		$from_address .= '</th>';
		$from_address .= '<td>';

		// If no sender signatures are provided, display a message to configure.
		if ( empty( $sender_signatures ) ) {

			$from_address .= sprintf(
				esc_html__( 'To setup a notification using Postmark, you must define at least one %1$sSender Signature%3$s. You can learn about Sender Signatures in the %2$sPostmark Help Center%3$s.', 'gravityformspostmark' ),
				'<a href="https://postmarkapp.com/signatures" target="_blank">',
				'<a href="http://support.postmarkapp.com/category/45-category" target="_blank">',
				'</a>'
			);

		} else {

			$from_address .= '<select name="gform_notification_from" id="gform_notification_from">';
			foreach ( $sender_signatures as $sender_signature ) {
				$from_address .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $sender_signature['EmailAddress'] ), selected( $sender_signature['EmailAddress'], rgar( $notification, 'from' ), false ), esc_attr( $sender_signature['EmailAddress'] ) );
			}
			$from_address .= '</select>';

		}

		$from_address .= '</td>';
		$from_address .= '</tr>';

		// Insert from address row.
		$ui_settings['notification_from'] = $from_address;

		// Build tracking row.
		$tracking  = '<tr valign="top">';
		$tracking .= '<th scope="row">';
		$tracking .= sprintf( '<label for="gform_notification_postmark_track_opens">%s</label>', esc_html__( 'Email Tracking', 'gravityformspostmark' ) );
		$tracking .= '</th>';
		$tracking .= '<td>';
		$tracking .= sprintf( '<input type="checkbox" name="gform_notification_postmark_track_opens" id="gform_notification_postmark_track_opens" value="1" %s />', checked( '1', rgar( $notification, 'postmarkTrackOpens' ), false ) );
		$tracking .= sprintf( '<label for="gform_notification_postmark_track_opens" class="inline"> &nbsp;%s</label>', esc_html__( 'Enable open tracking for this notification', 'gravityformspostmark' ) );
		$tracking .= '</td>';
		$tracking .= '</tr>';

		// Get UI settings array keys.
		$ui_settings_keys = array_keys( $ui_settings );

		// Loop through UI settings.
		foreach ( $ui_settings as $key => $ui_setting ) {

			// If this is not the conditional logic setting, skip.
			if ( 'notification_conditional_logic' !== $key && in_array( 'notification_conditional_logic', $ui_settings_keys ) ) {
				continue;
			}

			// Get position.
			$position = array_search( $key, $ui_settings_keys );

			// Add tracking row.
			array_splice( $ui_settings, $position, 0, array( 'postmark_tracking' => $tracking ) );

		}

		return $ui_settings;

	}

	/**
	 * Save Postmark notification fields.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $notification The notification object about to be saved.
	 * @param array $form         The current form object to which the notification being saved belongs.
	 *
	 * @return array
	 */
	public function save_notification_fields( $notification, $form ) {

		$input_prefix = $this->_has_settings_renderer ? '_gform_setting_' : 'gform_notification_';

		if ( 'postmark' === rgpost( $input_prefix . 'service' ) ) {

			$notification['postmarkTrackOpens'] = sanitize_text_field( rgpost( $input_prefix . ( $this->_has_settings_renderer ? 'postmarkTrackOpens' : 'postmark_track_opens' ) ) );
		}

		return $notification;

	}

	/**
	 * Send email through Postmark.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param  array  $email          The email details.
	 * @param  string $message_format The message format, html or text.
	 * @param  array  $notification   The Notification object.
	 * @param  array  $entry          The current Entry object.
	 *
	 * @return array
	 */
	public function maybe_send_email( $email, $message_format, $notification, $entry ) {

		// If email has been aborted, return the email.
		if ( $email['abort_email'] ) {
			$this->log_debug( __METHOD__ . "(): Not sending notification (#{$notification['id']} - {$notification['name']}) for {$entry['id']} via Postmark because the notification has already been aborted by another Add-On." );
			return $email;
		}

		// If this is not a Postmark notification or Postmark API is not initialized, return the email.
		if ( 'postmark' !== rgar( $notification, 'service' ) || ! $this->initialize_api() ) {
			return $email;
		}

		// Get form object.
		$form = GFAPI::get_form( $entry['form_id'] );

		// Prepare email for Postmark.
		$postmark_email = array(
			'From'       => rgar( $notification, 'from' ),
			'To'         => rgar( $email, 'to' ),
			'Subject'    => rgar( $email, 'subject' ),
			'TrackOpens' => rgar( $notification, 'postmarkTrackOpens' ) == '1' ? true : false,
		);

		// Add BCC.
		if ( rgar( $notification, 'bcc' ) ) {
			$postmark_email['Bcc'] = GFCommon::replace_variables( rgar( $notification, 'bcc' ), $form, $entry, false, false, false, 'text' );
		}

		// Add Reply To.
		if ( rgar( $notification, 'replyTo' ) ) {
			$postmark_email['ReplyTo'] = GFCommon::replace_variables( rgar( $notification, 'replyTo' ), $form, $entry, false, false, false, 'text' );
		}

		// Add body based on message format.
		if ( $message_format == 'html' ) {
			$postmark_email['HtmlBody'] = $email['message'];
		} else {
			$postmark_email['TextBody'] = $email['message'];
		}

		// Add attachments.
		if ( ! empty( $email['attachments'] ) ) {

			// Loop through attachments, add to email.
			foreach ( $email['attachments'] as $attachment ) {

				// Get mime type of attachment.
				$finfo     = finfo_open( FILEINFO_MIME_TYPE );
				$mime_type = finfo_file( $finfo, $attachment );
				finfo_close( $finfo );

				// Add attachment.
				$postmark_email['Attachments'][] = array(
					'Name'        => basename( $attachment ),
					'Content'     => base64_encode( file_get_contents( $attachment ) ),
					'ContentType' => $mime_type
				);

			}

		}

		// Add any extra email headers.
		$postmark_headers = $email['headers'];
		unset( $postmark_headers['Bcc'], $postmark_headers['Reply-To'], $postmark_headers['From'], $postmark_headers['Content-type'] );
		$postmark_email['Headers'] = $postmark_headers;

		/**
		 * Modify the email being sent by Postmark.
		 *
		 * @since 1.0
		 *
		 * @param array $postmark_email The Postmark email arguments.
		 * @param array $email          The original email details.
		 * @param array $message_format The message format, html or text.
		 * @param array $notification   The Notification object.
		 * @param array $entry          The current Entry object.
		 */
		$postmark_email = apply_filters( 'gform_postmark_email', $postmark_email, $email, $message_format, $notification, $entry );

		// Log the email to be sent.
		$this->log_debug( __METHOD__ . "(): Sending notification (#{$notification['id']} - {$notification['name']}) for entry {$entry['id']} via Postmark; " . print_r( $postmark_email, true ) );

		// Send email.
		$sent_email = $this->api->send_email( $postmark_email );

		if ( is_wp_error( $sent_email ) ) {
			$error_message = $sent_email->get_error_message();

			// Log that email failed to send.
			$this->log_error( __METHOD__ . "(): Unable to send notification (#{$notification['id']} - {$notification['name']}) for entry {$entry['id']} with Postmark; " . $error_message );

			// Add sending error result note.
			// translators: Notification name followed by its ID. e.g. Admin Notification (ID: 5d4c0a2a37204).
			// translators: Add-on name followed by the error message. e.g. Gravity Forms Postmark Add-On was unable to send the notification. Error: The 'From' address you supplied (testing@example.com) is not a Sender.
			GFFormsModel::add_note( $entry['id'], 0, sprintf( esc_html__( '%1$s (ID: %2$s)', 'gravityforms' ), $notification['name'], $notification['id'] ), sprintf( esc_html__( '%1$s was unable to send the notification. Error: %2$s', 'gravityformspostmark' ), $this->_title, $error_message ), 'gravityformspostmark', 'error' );

			/**
			 * Allow developers to take additional actions when email sending fail.
			 *
			 * @since 1.2
			 *
			 * @param string $error_message  The error message.
			 * @param array  $postmark_email The Postmark email arguments.
			 * @param array  $email          The original email details.
			 * @param array  $message_format The message format, html or text.
			 * @param array  $notification   The Notification object.
			 * @param array  $entry          The current Entry object.
			 */
			do_action( 'gform_postmark_send_email_failed', $error_message, $postmark_email, $email, $message_format, $notification, $entry );

			return $email;
		}

		// Log that email was sent.
		$this->log_debug( __METHOD__ . "(): Notification (#{$notification['id']} - {$notification['name']}) for entry {$entry['id']} successfully passed to Postmark; " . print_r( $sent_email, true ) );

		// Add sending successful result note.
		// translators: Notification name followed by its ID. e.g. Admin Notification (ID: 5d4c0a2a37204).
		// translators: Add-on name followed by the successful result message. e.g. Gravity Forms Postmark Add-On successfully passed the notification to Postmark.
		GFFormsModel::add_note( $entry['id'], 0, sprintf( esc_html__( '%1$s (ID: %2$s)', 'gravityforms' ), $notification['name'], $notification['id'] ), sprintf( esc_html__( '%1$s successfully passed the notification to Postmark.', 'gravityformspostmark' ), $this->_title ), 'gravityformspostmark', 'success' );

		// Prevent Gravity Forms from sending email.
		$email['abort_email'] = true;

		return $email;

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Initializes Postmark API if credentials are valid.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFAddOn::get_plugin_settings()
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::log_error()
	 * @uses   GF_Postmark::validate_account_token()
	 * @uses   GF_Postmark::validate_server_token()
	 * @uses   GF_Postmark_API::set_account_token()
	 * @uses   GF_Postmark_API::set_server_token()
	 *
	 * @return bool|null
	 */
	public function initialize_api() {

		// If API object is already setup, return true.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// If server or account token are empty, do not initialize API.
		if ( empty( $settings['serverToken'] ) || empty( $settings['accountToken'] ) ) {
			return null;
		}

		// Log that we are testing the API credentials.
		$this->log_debug( __METHOD__ . '(): Validating API credentials.' );

		// Get account and server token validity.
		$account_token_valid = $this->validate_account_token( $settings['accountToken'] );
		$server_token_valid  = $this->validate_server_token( $settings['serverToken'] );

		// If account and server token are valid, assign API object to this instance.
		if ( $account_token_valid && $server_token_valid ) {

			// Assign a new Postmark API object to this instance.
			$this->api = new GF_Postmark_API();

			// Set account token.
			$this->api->set_account_token( $settings['accountToken'] );

			// Set server token.
			$this->api->set_server_token( $settings['serverToken'] );

			// Log that test passed.
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );

			return true;

		} else {

			// Log that test failed.
			$this->log_error( __METHOD__ . '(): API credentials are invalid.' );

			return false;

		}

	}

	/**
	 * Validate Postmark account token.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $account_token Postmark account token.
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::log_error()
	 * @uses   GF_Postmark_API::get_sender_signatures()
	 * @uses   GF_Postmark_API::set_account_token()
	 *
	 * @return bool|null
	 */
	public function validate_account_token( $account_token = null ) {

		// If the account token is empty, do not run a validation check.
		if ( rgblank( $account_token ) ) {
			return null;
		}

		if ( ! empty( $this->sender_signatures[ $account_token ] ) ) {
			return true;
		}

		// Log that we are testing the account token.
		$this->log_debug( __METHOD__ . '(): Validating account token.' );

		// Setup a new Postmark API object.
		$postmark = new GF_Postmark_API();

		// Set account token.
		$postmark->set_account_token( $account_token );

		// Attempt to get sender signatures.
		$result = $postmark->get_sender_signatures();

		if ( is_wp_error( $result ) ) {
			$this->log_error( __METHOD__ . '(): Account token is invalid; ' . $result->get_error_message() );

			return false;
		}

		$this->log_debug( __METHOD__ . '(): Account token is valid.' );

		// Cache the account token so we don't have to fetch it again.
		$this->sender_signatures[ $account_token ] = $result;

		return true;

	}

	/**
	 * Validate Postmark server token.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param string $server_token Postmark server token.
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::log_error()
	 * @uses   GF_Postmark_API::get_sender_signatures()
	 * @uses   GF_Postmark_API::set_account_token()
	 *
	 * @return bool|null
	 */
	public function validate_server_token( $server_token = null ) {

		// If the server token is empty, do not run a validation check.
		if ( rgblank( $server_token ) ) {
			return null;
		}

		if ( ! empty( $this->sender_signatures[ $server_token ] ) ) {
			return true;
		}

		// Log that we are testing the server token.
		$this->log_debug( __METHOD__ . '(): Validating server token.' );

		// Setup a new Postmark API object.
		$postmark = new GF_Postmark_API();

		// Set server token.
		$postmark->set_server_token( $server_token );

		// Attempt to get current server.
		$result = $postmark->get_current_server();

		if ( is_wp_error( $result ) ) {
			$this->log_error( __METHOD__ . '(): Server token is invalid; ' . $result->get_error_message() );

			return false;
		}

		$this->log_debug( __METHOD__ . '(): Server token is valid.' );

		// Cache the account token so we don't have to fetch it again.
		$this->sender_signatures[ $server_token ] = $result;

		return true;

	}

	/**
	 * Register styles.
	 *
	 * @since 1.3
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'gform_postmark_pluginsettings',
				'src'     => $this->get_base_url() . '/css/plugin_settings.css',
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'admin_page' => array( 'plugin_settings' ),
						'tab'        => $this->_slug,
					),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

}
