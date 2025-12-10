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
            
            // Hide default WooCommerce addresses if setting enabled
            if (Settings::should_hide_wc_addresses()) {
                add_action('wp_head', [self::class, 'output_hide_wc_addresses_css']);
            }
        }
    }
    
    /**
     * Output CSS to hide default WooCommerce addresses on My Account.
     */
    public static function output_hide_wc_addresses_css(): void
    {
        if (!is_account_page()) {
            return;
        }
        
        ?>
        <style id="hp-ma-hide-wc-addresses">
        /* Hide default WooCommerce My Account addresses - HP Multi-Address */
        .woocommerce-Addresses.col2-set.addresses,
        .u-columns.woocommerce-Addresses.col2-set.addresses {
            display: none !important;
        }
        /* Hide "The following addresses will be used on the checkout page by default." text */
        /* Target the specific p element that WooCommerce adds */
        .woocommerce-MyAccount-content > p:first-of-type,
        .woocommerce-MyAccount-content-wrapper p:first-of-type,
        .woocommerce-edit-address > p:first-of-type,
        .e-my-account-tab__address > p:first-of-type,
        .woocommerce-account .woocommerce > p:first-of-type,
        .woocommerce-MyAccount-content-wrapper [data-elementor-type="container"] > p:first-of-type {
            display: none !important;
        }
        /* Ensure our address card content is never hidden */
        .address-card p,
        [id^="hp-address-card-picker"] p {
            display: block !important;
        }
        </style>
        <?php
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

