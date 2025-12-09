<?php
namespace HP_MA;

use HP_MA\Admin\SettingsPage;
use HP_MA\Checkout\CheckoutIntegration;
use HP_MA\MyAccount\AccountIntegration;

/**
 * Main plugin bootstrap class.
 */
class Plugin
{
    /**
     * Initialize all plugin components.
     */
    public static function init(): void
    {
        // Load settings
        Settings::init();
        
        // Register admin settings page
        if (is_admin()) {
            $settingsPage = new SettingsPage();
            $settingsPage->register();
        }
        
        // Frontend integrations
        if (!is_admin() || wp_doing_ajax()) {
            // Checkout page address picker
            $checkoutIntegration = new CheckoutIntegration();
            $checkoutIntegration->register();
            
            // My Account page address management
            $accountIntegration = new AccountIntegration();
            $accountIntegration->register();
        }
    }
    
    /**
     * Plugin activation callback.
     */
    public static function activate(): void
    {
        // Set default settings if not present
        if (get_option(HP_MA_SETTINGS_KEY) === false) {
            update_option(HP_MA_SETTINGS_KEY, Settings::get_defaults());
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation callback.
     */
    public static function deactivate(): void
    {
        // Optionally clean up transients, scheduled events, etc.
        flush_rewrite_rules();
    }
}

