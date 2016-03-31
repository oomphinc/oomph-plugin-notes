<?php
/**
Plugin Name: Oomph Plugin Notes
Plugin URI: http://www.oomphinc.com/plugins-modules/oomph-plugin-notes
Description: Add usage notes to your plugins
Author: Ben Doherty @ Oomph, Inc.
Version: 0.1.0
Author URI: http://www.oomphinc.com/thinking/author/bdoherty/
License: GPLv2 or later

		Copyright © 2016 Oomph, Inc. <http://oomphinc.com>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @package Oomph Plugin Notes
 */
class Oomph_Plugin_Notes {
	// Store plugin notes in this option
	const OPTION_NAME = 'plugin_notes';

	// Capability to see hidden tags
	const CAPABILITY = 'activate_plugins';

	// Nonce action
	const NONCE_ACTION = 'oomph-plugin-notes';

	// Filters and actions used by this class
	var $filters = array(
		'plugin_row_meta' => array( 20, 'add_plugin_notes' ),
		'wp_ajax_oomph-plugin-notes-save' => 'save_plugin_notes',
		'admin_enqueue_scripts' => 'enqueue_scripts',
		'admin_head' => 'add_styles',
	);

	/**
	 * Register filters and assets
	 */
	function __construct() {
		$this->filter_map( 'add_filter' );
	}

	/**
	 * Unregister all filters on object destruction
	 */
	function __destruct() {
		$this->filter_map( 'remove_filter' );
	}

	/**
	 * Enqueue scripts used in this module on the plugin screen and add
	 * script data.
	 *
	 * @action admin_enqueue_scripts
	 */
	function enqueue_scripts() {
		global $current_screen;

		if( $current_screen->base !== 'plugins' ) {
			return;
		}

		wp_enqueue_script( 'oomph-plugin-notes', plugins_url( 'oomph-plugin-notes.js', __FILE__ ), array(), 1, true );
		wp_localize_script( 'oomph-plugin-notes', 'OPN', array(
			'nonce' => wp_create_nonce( self::NONCE_ACTION ),
			'text' => array(
				'save' => __( 'Save' ),
				'cancel' => __( 'Cancel' )
			)
		) );
	}

	/**
	 * Add styles used in this module to the header
	 *
	 * @action admin_head
	 */
	function add_styles() {
		global $current_screen;

		if( $current_screen->base !== 'plugins' ) {
			return;
		}

		echo <<<STYLES
<style type="text/css">
.plugin-notes-edit { padding-left: 1em; white-space: nowrap; color: #999; cursor: pointer; }
</style>
STYLES;

	}

	/**
	 * Return a link that can be clicked to edit the current notes
	 */
	function edit_link() {
		if( current_user_can( self::CAPABILITY ) ) {
			return '<span class="plugin-notes-edit">' . __( 'edit notes' ) . '</span>';
		}

		return '';
	}

	/**
	 * Save the notes for the plugin and return a marked-up version of those notes for
	 * display.
	 *
	 * @action wp_ajax_oomph-plugin-notes-save
	 */
	function save_plugin_notes() {
		if( !current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error();
		}

		$all_notes = get_option( self::OPTION_NAME );

		if( empty( $current ) ) {
			$current = array();
		}

		$input = filter_input_array( INPUT_POST, array(
			'plugin' => FILTER_REQUIRE_SCALAR,
			'notes' => FILTER_REQUIRE_SCALAR,
			'nonce' => FILTER_REQUIRE_SCALAR
		) );

		extract( $input );

		if( !wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error();
		}

		$all_notes[$plugin] = $notes;

		update_option( self::OPTION_NAME, $all_notes );

		wp_send_json_success( array( 'markup' => wp_kses_post( wpautop( $notes . ' ' . $this->edit_link() ) ) ) );
	}

	/**
	 * Add or remove filters by calling the specified API function (add_ or
	 * remove_filter) for all filters defined in $filters.
	 */
	function filter_map( $api_call ) {
		foreach( $this->filters as $filter => $method ) {
			$priority = 10;

			if( is_array( $method ) ) {
				$priority = $method[0];
				$method = $method[1];
			}

			$api_call( $filter, array( $this, $method ), $priority, 50 );
		}
	}

	/**
	 * Add notes field to plugin description by prepending the container to the first plugin
	 * meta item.
	 *
	 * @filter plugin_row_meta
	 */
	function add_plugin_notes( $plugin_meta, $plugin_file, $plugin_data, $status ) {
		$id = sanitize_title( $plugin_data['Name'] );

		$all_notes = get_option( self::OPTION_NAME, array() );

		if( !is_array( $all_notes ) ) {
			$all_notes = array();
		}

		$notes = isset( $all_notes[$id] ) ? $all_notes[$id] : '';

		$classes = "plugin-notes-container editable";

		$plugin_meta[0] = '<div data-plugin-notes="' . esc_attr( $notes ) . '" class="' . esc_attr( $classes ) . '">' . wp_kses_post( wpautop( $notes . ' ' . $this->edit_link() ) ) . '</div>' . $plugin_meta[0];

		return $plugin_meta;
	}
}

new Oomph_Plugin_Notes;
