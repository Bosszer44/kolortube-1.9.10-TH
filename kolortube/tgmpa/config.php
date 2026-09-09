<?php
/**
 * Plugin TGM config file.
 *
 * @package dvic\tgmpa
 */

add_action( 'tgmpa_register', 'wpst_register_required_plugins' );

/**
 * Register TGM options.
 */
function wpst_register_required_plugins() {

	$plugins = array(
		array(
			'name'               => 'WP-Script Core', // The plugin name.
			'slug'               => 'wp-script-core', // The plugin slug (typically the folder name).
			'source'             => 'https://wp-script-products.s3.us-east-2.amazonaws.com/wp-script-core.zip', // The plugin source.
			'required'           => true, // If false, the plugin is only 'recommended' instead of required.
			'version'            => '5.2.1', // E.g. 1.0.0. If set, the active plugin must be this version or higher. If the plugin version is higher than the plugin version installed, the user will be notified to update the plugin.
			'force_activation'   => false, // If true, plugin is activated upon theme activation and cannot be deactivated until theme switch.
			'force_deactivation' => false, // If true, plugin is deactivated upon theme switch, useful for theme-specific plugins.
			'external_url'       => '', // If set, overrides default API URL and points to an external URL.
			'is_callable'        => '', // If set, this callable will be be checked for availability to determine if a plugin is active.
		),
	);

	$config = array(
		'id'           => 'wpst',                   // Unique ID for hashing notices for multiple instances of TGMPA.
		'default_path' => '',                       // Default absolute path to bundled plugins.
		'menu'         => 'tgmpa-install-plugins',  // Menu slug.
		'parent_slug'  => 'themes.php',             // Parent menu slug.
		'capability'   => 'edit_theme_options',     // Capability needed to view plugin install page, should be a capability associated with the parent menu used.
		'has_notices'  => true,                     // Show admin notices or not.
		'dismissable'  => false,                    // If false, a user cannot dismiss the nag message.
		'dismiss_msg'  => '',                       // If 'dismissable' is false, this message will be output at top of nag.
		'is_automatic' => true,                     // Automatically activate plugins after installation or not.
		'message'      => '',                       // Message to output right before the plugins table.
		'strings'      => array(
			'page_title'                     => __( 'Install Required Plugins', 'wpst' ),
			'menu_title'                     => __( 'Install Plugins', 'wpst' ),
			/* translators: %s: plugin name. */
			'installing'                     => __( 'Installing Plugin: %s', 'wpst' ),
			/* translators: %s: plugin name. */
			'updating'                       => __( 'Updating Plugin: %s', 'wpst' ),
			'oops'                           => __( 'Something went wrong with the plugin API.', 'wpst' ),
			// translators: 1: plugin name(s).
			'notice_can_install_required'    => _n_noop(
				'WP-Script products requires the following plugin: %1$s.',
				'WP-Script products requires the following plugins: %1$s.',
				'wpst'
			),
			/* translators: 1: plugin name(s). */
			'notice_can_install_recommended' => _n_noop(
				'WP-Script products recommends the following plugin: %1$s.',
				'TWP-Script products recommends the following plugins: %1$s.',
				'wpst'
			),
			/* translators: 1: plugin name(s). */
			'notice_ask_to_update'           => _n_noop(
				'The following WP-Script product needs to be updated to its latest version to ensure maximum compatibility with this plugin: %1$s.',
				'The following WP-Script products need to be updated to their latest version to ensure maximum compatibility with this plugin: %1$s.',
				'wpst'
			),
		),
	);

	tgmpa( $plugins, $config );
}
