<?php
namespace HP_MA;

/**
 * Plugin settings management.
 */
class Settings
{
    /**
     * Cached settings array.
     */
    private static ?array $settings = null;
    
    /**
     * Initialize settings.
     */
    public static function init(): void
    {
        // Settings are loaded on demand
    }
    
    /**
     * Get default settings values.
     */
    public static function get_defaults(): array
    {
        return [
            // Billing settings
            'enable_billing'            => 'yes',
            'billing_display_position'  => 'above', // above|below
            'billing_shortcode'         => '[hp_address_card_picker type="billing" show_actions="true"]',
            
            // Shipping settings
            'enable_shipping'           => 'yes',
            'shipping_display_position' => 'above', // above|below
            'shipping_shortcode'        => '[hp_address_card_picker type="shipping" show_actions="true"]',
            
            // General settings
            'address_limit'             => 20,
            'enable_my_account'         => 'no', // Disabled by default - use shortcodes via page builder instead
            
            // Display settings
            'collapsible_picker'        => 'yes', // Show as collapsible toggle button
            'billing_toggle_text'       => 'Select a different billing address',
            'shipping_toggle_text'      => 'Select a different shipping address',
        ];
    }
    
    /**
     * Get all settings.
     */
    public static function get_all(): array
    {
        if (self::$settings === null) {
            $stored = get_option(HP_MA_SETTINGS_KEY, []);
            self::$settings = wp_parse_args($stored, self::get_defaults());
        }
        
        return self::$settings;
    }
    
    /**
     * Get a single setting value.
     *
     * @param string $key     Setting key.
     * @param mixed  $default Default value if not set.
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $settings = self::get_all();
        
        if (isset($settings[$key])) {
            return $settings[$key];
        }
        
        $defaults = self::get_defaults();
        return $defaults[$key] ?? $default;
    }
    
    /**
     * Update settings.
     *
     * @param array $new_settings Settings to merge/update.
     */
    public static function update(array $new_settings): bool
    {
        $current = self::get_all();
        $updated = wp_parse_args($new_settings, $current);
        
        // Sanitize values
        $updated = self::sanitize($updated);
        
        $result = update_option(HP_MA_SETTINGS_KEY, $updated);
        
        // Clear cache
        self::$settings = null;
        
        return $result;
    }
    
    /**
     * Sanitize settings values.
     */
    public static function sanitize(array $settings): array
    {
        $sanitized = [];
        $defaults = self::get_defaults();
        
        foreach ($defaults as $key => $default) {
            if (!isset($settings[$key])) {
                $sanitized[$key] = $default;
                continue;
            }
            
            $value = $settings[$key];
            
            // Sanitize based on key type
            if (in_array($key, ['enable_billing', 'enable_shipping', 'enable_my_account', 'collapsible_picker'])) {
                $sanitized[$key] = $value === 'yes' ? 'yes' : 'no';
            } elseif (in_array($key, ['billing_display_position', 'shipping_display_position'])) {
                $sanitized[$key] = in_array($value, ['above', 'below']) ? $value : 'above';
            } elseif ($key === 'address_limit') {
                $sanitized[$key] = absint($value) ?: 20;
            } elseif (in_array($key, ['billing_shortcode', 'shipping_shortcode'])) {
                // Remove any escaped slashes and sanitize
                $sanitized[$key] = sanitize_text_field(wp_unslash($value));
            } else {
                $sanitized[$key] = sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Check if billing addresses are enabled.
     */
    public static function is_billing_enabled(): bool
    {
        return self::get('enable_billing') === 'yes';
    }
    
    /**
     * Check if shipping addresses are enabled.
     */
    public static function is_shipping_enabled(): bool
    {
        return self::get('enable_shipping') === 'yes';
    }
    
    /**
     * Check if My Account integration is enabled.
     */
    public static function is_my_account_enabled(): bool
    {
        return self::get('enable_my_account') === 'yes';
    }
    
    /**
     * Get the address limit per type.
     */
    public static function get_address_limit(): int
    {
        return (int) self::get('address_limit', 20);
    }
}

