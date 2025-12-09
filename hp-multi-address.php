<?php
/**
 * Plugin Name:       HP Multi-Address
 * Description:       Multiple shipping/billing addresses for WooCommerce. Integrates with HP React Widgets for modern address picker UI.
 * Version:           1.0.0
 * Author:            Holistic People
 * Text Domain:       hp-multi-address
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 7.6
 * WC tested up to:   9.7
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('HP_MA_VERSION', '1.0.1');
define('HP_MA_FILE', __FILE__);
define('HP_MA_PATH', plugin_dir_path(__FILE__));
define('HP_MA_URL', plugin_dir_url(__FILE__));
define('HP_MA_BASENAME', plugin_basename(__FILE__));

// User meta key for addresses (kept for backward compatibility with ThemeHigh data)
define('HP_MA_ADDRESS_KEY', 'thwma_custom_address');

// Settings option key (new HP naming)
define('HP_MA_SETTINGS_KEY', 'hp_ma_settings');

/**
 * Check if WooCommerce is active
 */
function hp_ma_is_woocommerce_active(): bool
{
    $active_plugins = (array) get_option('active_plugins', []);
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));
    }
    return in_array('woocommerce/woocommerce.php', $active_plugins) 
        || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

/**
 * Check if HP React Widgets is active (required dependency)
 */
function hp_ma_is_react_widgets_active(): bool
{
    $active_plugins = (array) get_option('active_plugins', []);
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));
    }
    
    // Check both possible folder names (local dev vs deployed)
    $possible_paths = [
        'HP-React-Widgets/hp-react-widgets.php',
        'hp-react-widgets/hp-react-widgets.php',
    ];
    
    foreach ($possible_paths as $path) {
        if (in_array($path, $active_plugins) || array_key_exists($path, $active_plugins)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Display admin notice if dependencies are missing
 */
function hp_ma_dependency_notice(): void
{
    $missing = [];
    
    if (!hp_ma_is_woocommerce_active()) {
        $missing[] = 'WooCommerce';
    }
    
    if (!hp_ma_is_react_widgets_active()) {
        $missing[] = 'HP React Widgets';
    }
    
    if (!empty($missing)) {
        $message = sprintf(
            '<strong>HP Multi-Address</strong> requires the following plugins to be active: %s',
            implode(', ', $missing)
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', $message);
    }
}

// PSR-4 style autoloader
spl_autoload_register(function ($class) {
    $prefix = 'HP_MA\\';
    $base_dir = HP_MA_PATH . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Initialize the plugin after all plugins are loaded
 */
add_action('plugins_loaded', function () {
    // Check dependencies
    if (!hp_ma_is_woocommerce_active() || !hp_ma_is_react_widgets_active()) {
        add_action('admin_notices', 'hp_ma_dependency_notice');
        return;
    }
    
    // Initialize plugin
    if (class_exists('HP_MA\\Plugin')) {
        \HP_MA\Plugin::init();
    }
}, 20); // Priority 20 to load after WooCommerce and HP React Widgets

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function () {
    if (class_exists('HP_MA\\Plugin')) {
        \HP_MA\Plugin::activate();
    }
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function () {
    if (class_exists('HP_MA\\Plugin')) {
        \HP_MA\Plugin::deactivate();
    }
});

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Add settings link on plugins page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $settings_url = admin_url('admin.php?page=hp-multi-address');
    array_unshift($links, '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'hp-multi-address') . '</a>');
    return $links;
});

