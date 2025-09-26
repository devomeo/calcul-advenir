<?php
/**
 * Plugin Name:       Advenir Eligibility Wizard
 * Plugin URI:        https://example.com/
 * Description:       Fournit un assistant Advenir pour estimer l'éligibilité et les montants d'aide.
 * Version:           1.0.0
 * Author:            Calcul Advenir
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       advenir-eligibility-wizard
 * Domain Path:       /languages
 *
 * @package AdvenirEligibilityWizard
 */

defined( 'ABSPATH' ) || exit;

define( 'AEW_VERSION', '1.0.0' );
define( 'AEW_PLUGIN_FILE', __FILE__ );
define( 'AEW_PLUGIN_DIR', plugin_dir_path( AEW_PLUGIN_FILE ) );
define( 'AEW_PLUGIN_URL', plugin_dir_url( AEW_PLUGIN_FILE ) );

require_once AEW_PLUGIN_DIR . 'includes/class-aew-admin.php';
require_once AEW_PLUGIN_DIR . 'includes/class-aew-rest.php';
require_once AEW_PLUGIN_DIR . 'includes/class-aew-frontend.php';

/**
 * Load the plugin text domain for translation.
 *
 * @return void
 */
function aew_load_textdomain() {
	load_plugin_textdomain( 'advenir-eligibility-wizard', false, dirname( plugin_basename( AEW_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'aew_load_textdomain' );

/**
 * Bootstrap plugin services.
 *
 * @return void
 */
function aew_bootstrap() {
	$admin    = AEW_Admin::get_instance();
	$rest_api = AEW_REST::get_instance();
	$frontend = AEW_Frontend::get_instance();

	// The variables are not used further but instantiation is required.
	if ( ! $admin || ! $rest_api || ! $frontend ) {
		return;
	}
}
add_action( 'plugins_loaded', 'aew_bootstrap' );
