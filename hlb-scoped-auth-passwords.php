<?php
/**
 * Plugin Name:       Scoped Agent Credentials
 * Plugin URI:        https://github.com/jdbg/hlb-scoped-auth-passwords
 * Description:       Scope WordPress Application Passwords to named abilities and post types, with expiry and revoke, so an agent only touches what it was asked to.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            jd
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hlb-scoped-auth-passwords
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HLB_SAP_VERSION', '0.1.0' );
define( 'HLB_SAP_FILE', __FILE__ );
define( 'HLB_SAP_DIR', plugin_dir_path( __FILE__ ) );

require_once HLB_SAP_DIR . 'includes/class-plugin.php';

add_action( 'plugins_loaded', array( 'HLB_SAP\\Plugin', 'init' ) );
