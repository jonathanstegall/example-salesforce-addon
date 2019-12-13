<?php
/*
Plugin Name: Example Salesforce Addon
Plugin URI:
Description:
Version: 0.0.1
Author: Jonathan Stegall
Author URI: https://jonathanstegall.com
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: example-salesforce-addon
*/

// Start up the plugin
class Example_Salesforce_Addon {

	/**
	* @var string
	*/
	private $version;

	/**
	* @var string
	*/
	private $option_prefix;

	/**
	 * This is our constructor
	 */
	public function __construct() {

		$this->version       = '0.0.1';
		$this->option_prefix = 'salesforce_api_';

		$this->add_actions();
	}

	/**
	* Add actions
	*/
	private function add_actions() {
		add_filter( 'object_sync_for_salesforce_pull_query_modify', array( $this, 'pull_query' ), 10, 4 );
		add_filter( 'object_sync_for_salesforce_settings_tabs', array( $this, 'example_sf_addon_tabs' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'example_sf_addon_salesforce_settings_forms' ) );
	}

	/**
	* Add the fields to the SOQL query to specify which records get pulled
	*
	* @param object $soql
	*   The SOQL query object
	* @param string $object_type
	*   Salesforce object type
	* @param array $salesforce_mapping
	*   The map between the WordPress and Salesforce object types
	* @param array $mapped_fields
	*   The fields that are mapped between these objects
	* @param object $soql
	*   The SOQL query object
	*
	*/
	public function pull_query( $soql, $object_type, $salesforce_mapping, $mapped_fields ) {
		if ( 'Contact' === $object_type ) {
			$contact_pull_field = get_option( $this->option_prefix . 'contact_required_field', '' );
			if ( '' !== $contact_pull_field ) {
				$soql->add_condition( $contact_pull_field, 'true', '=' );
			}
		}
		return $soql;
	}

	/**
	* Add a tab to the plugin settings
	*
	* @param array $tabs
	* @return array $tabs
	*
	*/
	public function example_sf_addon_tabs( $tabs ) {
		$tabs['exampleaddon'] = __( 'Example Addon Settings', 'example-salesforce-addon' );
		return $tabs;
	}

	/**
	* Create default WordPress admin settings form for addon-specific salesforce things
	* This is for the Settings page/tab
	*
	*/
	public function example_sf_addon_salesforce_settings_forms() {
		$get_data = filter_input_array( INPUT_GET, FILTER_SANITIZE_STRING );
		$page     = isset( $get_data['tab'] ) ? sanitize_key( $get_data['tab'] ) : 'settings';
		$section  = isset( $get_data['tab'] ) ? sanitize_key( $get_data['tab'] ) : 'settings';

		$input_callback_default   = array( $this, 'display_input_field' );
		$input_checkboxes_default = array( $this, 'display_checkboxes' );
		$this->fields_example_sf_addon_settings(
			'exampleaddon',
			'exampleaddon',
			array(
				'text'       => $input_callback_default,
				'checkboxes' => $input_checkboxes_default,
			)
		);
	}

	/**
	* Fields for the Log Settings tab
	* This runs add_settings_section once, as well as add_settings_field and register_setting methods for each option
	*
	* @param string $page
	* @param string $section
	* @param array $callbacks
	*/
	private function fields_example_sf_addon_settings( $page, $section, $callbacks ) {
		add_settings_section( $page, ucwords( str_replace( '_', ' ', $page ) ), null, $page );
		$example_sf_addon_salesforce_settings = array(
			'contact_required_field' => array(
				'title'    => 'Name of field required to sync a Contact (must be a true/false field)',
				'callback' => $callbacks['text'],
				'page'     => $page,
				'section'  => $section,
				'args'     => array(
					'type'     => 'text',
					'desc'     => __( 'If blank, all contacts will be synced', 'example-salesforce-addon' ),
					'constant' => '',
				),
			),
		);
		foreach ( $example_sf_addon_salesforce_settings as $key => $attributes ) {
			$id       = $this->option_prefix . $key;
			$name     = $this->option_prefix . $key;
			$title    = $attributes['title'];
			$callback = $attributes['callback'];
			$page     = $attributes['page'];
			$section  = $attributes['section'];
			$args     = array_merge(
				$attributes['args'],
				array(
					'title'     => $title,
					'id'        => $id,
					'label_for' => $id,
					'name'      => $name,
				)
			);
			add_settings_field( $id, $title, $callback, $page, $section, $args );
			register_setting( $section, $id );
		}
	}

	/**
	* Default display for <input> fields
	*
	* @param array $args
	*/
	public function display_input_field( $args ) {
		$type    = $args['type'];
		$id      = $args['label_for'];
		$name    = $args['name'];
		$desc    = $args['desc'];
		$checked = '';

		$class = 'regular-text';

		if ( 'checkbox' === $type ) {
			$class = 'checkbox';
		}

		if ( ! isset( $args['constant'] ) || ! defined( $args['constant'] ) ) {
			$value = esc_attr( get_option( $id, '' ) );
			if ( 'checkbox' === $type ) {
				if ( '1' === $value ) {
					$checked = 'checked ';
				}
				$value = 1;
			}
			if ( '' === $value && isset( $args['default'] ) && '' !== $args['default'] ) {
				$value = $args['default'];
			}

			echo sprintf(
				'<input type="%1$s" value="%2$s" name="%3$s" id="%4$s" class="%5$s"%6$s>',
				esc_attr( $type ),
				esc_attr( $value ),
				esc_attr( $name ),
				esc_attr( $id ),
				sanitize_html_class( $class . esc_html( ' code' ) ),
				esc_html( $checked )
			);
			if ( '' !== $desc ) {
				echo sprintf(
					'<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
		} else {
			echo sprintf(
				'<p><code>%1$s</code></p>',
				esc_html__( 'Defined in wp-config.php', 'example-salesforce-addon' )
			);
		}
	}

	/**
	* Display for multiple checkboxes
	* Above method can handle a single checkbox as it is
	*
	* @param array $args
	*/
	public function display_checkboxes( $args ) {
		$type    = 'checkbox';
		$name    = $args['name'];
		$options = get_option( $name, array() );
		foreach ( $args['items'] as $key => $value ) {
			$text    = $value['text'];
			$id      = $value['id'];
			$desc    = $value['desc'];
			$checked = '';
			if ( is_array( $options ) && in_array( $key, $options, true ) ) {
				$checked = 'checked';
			} elseif ( is_array( $options ) && empty( $options ) ) {
				if ( isset( $value['default'] ) && true === $value['default'] ) {
					$checked = 'checked';
				}
			}
			echo sprintf(
				'<div class="checkbox"><label><input type="%1$s" value="%2$s" name="%3$s[]" id="%4$s"%5$s>%6$s</label></div>',
				esc_attr( $type ),
				esc_attr( $key ),
				esc_attr( $name ),
				esc_attr( $id ),
				esc_html( $checked ),
				esc_html( $text )
			);
			if ( '' !== $desc ) {
				echo sprintf(
					'<p class="description">%1$s</p>',
					esc_html( $desc )
				);
			}
		}
	}
}
// Instantiate our class
$example_salesforce_addon = new Example_Salesforce_Addon();
