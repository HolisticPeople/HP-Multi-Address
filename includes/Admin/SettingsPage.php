<?php
namespace HP_MA\Admin;

use HP_MA\Settings;

/**
 * Admin settings page for HP Multi-Address.
 */
class SettingsPage
{
    /**
     * Menu slug.
     */
    private const MENU_SLUG = 'hp-multi-address';
    
    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'handle_save']);
    }
    
    /**
     * Add admin menu item.
     */
    public function add_menu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('HP Multi-Address', 'hp-multi-address'),
            __('Multi-Address', 'hp-multi-address'),
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render_page']
        );
    }
    
    /**
     * Handle settings save.
     */
    public function handle_save(): void
    {
        if (!isset($_POST['hp_ma_save_settings'])) {
            return;
        }
        
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['hp_ma_nonce'] ?? '', 'hp_ma_settings')) {
            add_settings_error('hp_ma', 'nonce_failed', __('Security check failed.', 'hp-multi-address'));
            return;
        }
        
        $settings = [
            'enable_billing'            => isset($_POST['enable_billing']) ? 'yes' : 'no',
            'enable_shipping'           => isset($_POST['enable_shipping']) ? 'yes' : 'no',
            'enable_my_account'         => isset($_POST['enable_my_account']) ? 'yes' : 'no',
            'collapsible_picker'        => isset($_POST['collapsible_picker']) ? 'yes' : 'no',
            'billing_display_position'  => sanitize_key($_POST['billing_display_position'] ?? 'above'),
            'shipping_display_position' => sanitize_key($_POST['shipping_display_position'] ?? 'above'),
            'billing_shortcode'         => sanitize_text_field($_POST['billing_shortcode'] ?? ''),
            'shipping_shortcode'        => sanitize_text_field($_POST['shipping_shortcode'] ?? ''),
            'billing_toggle_text'       => sanitize_text_field($_POST['billing_toggle_text'] ?? ''),
            'shipping_toggle_text'      => sanitize_text_field($_POST['shipping_toggle_text'] ?? ''),
            'address_limit'             => absint($_POST['address_limit'] ?? 20),
        ];
        
        Settings::update($settings);
        
        add_settings_error('hp_ma', 'settings_saved', __('Settings saved successfully.', 'hp-multi-address'), 'success');
    }
    
    /**
     * Render the settings page.
     */
    public function render_page(): void
    {
        $settings = Settings::get_all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('HP Multi-Address Settings', 'hp-multi-address'); ?></h1>
            
            <?php settings_errors('hp_ma'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('hp_ma_settings', 'hp_ma_nonce'); ?>
                
                <h2 class="title"><?php esc_html_e('General Settings', 'hp-multi-address'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Address Limit', 'hp-multi-address'); ?></th>
                        <td>
                            <input type="number" 
                                   name="address_limit" 
                                   value="<?php echo esc_attr($settings['address_limit']); ?>" 
                                   min="1" 
                                   max="100" 
                                   class="small-text">
                            <p class="description">
                                <?php esc_html_e('Maximum number of addresses per type (billing/shipping) a user can save.', 'hp-multi-address'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('My Account Integration', 'hp-multi-address'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_my_account" 
                                       value="yes" 
                                       <?php checked($settings['enable_my_account'], 'yes'); ?>>
                                <?php esc_html_e('Show additional addresses on My Account page', 'hp-multi-address'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Collapsible Picker', 'hp-multi-address'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="collapsible_picker" 
                                       value="yes" 
                                       <?php checked($settings['collapsible_picker'], 'yes'); ?>>
                                <?php esc_html_e('Show address picker as collapsible toggle button on checkout', 'hp-multi-address'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php esc_html_e('Billing Address Settings', 'hp-multi-address'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'hp-multi-address'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_billing" 
                                       value="yes" 
                                       <?php checked($settings['enable_billing'], 'yes'); ?>>
                                <?php esc_html_e('Enable multiple billing addresses', 'hp-multi-address'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Display Position', 'hp-multi-address'); ?></th>
                        <td>
                            <select name="billing_display_position">
                                <option value="above" <?php selected($settings['billing_display_position'], 'above'); ?>>
                                    <?php esc_html_e('Above billing form', 'hp-multi-address'); ?>
                                </option>
                                <option value="below" <?php selected($settings['billing_display_position'], 'below'); ?>>
                                    <?php esc_html_e('Below billing form', 'hp-multi-address'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Toggle Button Text', 'hp-multi-address'); ?></th>
                        <td>
                            <input type="text" 
                                   name="billing_toggle_text" 
                                   value="<?php echo esc_attr($settings['billing_toggle_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Shortcode', 'hp-multi-address'); ?></th>
                        <td>
                            <input type="text" 
                                   name="billing_shortcode" 
                                   value="<?php echo esc_attr($settings['billing_shortcode']); ?>" 
                                   class="large-text code">
                            <p class="description">
                                <?php esc_html_e('The HP React Widgets shortcode to render for billing addresses.', 'hp-multi-address'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php esc_html_e('Shipping Address Settings', 'hp-multi-address'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable', 'hp-multi-address'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_shipping" 
                                       value="yes" 
                                       <?php checked($settings['enable_shipping'], 'yes'); ?>>
                                <?php esc_html_e('Enable multiple shipping addresses', 'hp-multi-address'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Display Position', 'hp-multi-address'); ?></th>
                        <td>
                            <select name="shipping_display_position">
                                <option value="above" <?php selected($settings['shipping_display_position'], 'above'); ?>>
                                    <?php esc_html_e('Above shipping form', 'hp-multi-address'); ?>
                                </option>
                                <option value="below" <?php selected($settings['shipping_display_position'], 'below'); ?>>
                                    <?php esc_html_e('Below shipping form', 'hp-multi-address'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Toggle Button Text', 'hp-multi-address'); ?></th>
                        <td>
                            <input type="text" 
                                   name="shipping_toggle_text" 
                                   value="<?php echo esc_attr($settings['shipping_toggle_text']); ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Shortcode', 'hp-multi-address'); ?></th>
                        <td>
                            <input type="text" 
                                   name="shipping_shortcode" 
                                   value="<?php echo esc_attr($settings['shipping_shortcode']); ?>" 
                                   class="large-text code">
                            <p class="description">
                                <?php esc_html_e('The HP React Widgets shortcode to render for shipping addresses.', 'hp-multi-address'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php esc_html_e('Dependency Status', 'hp-multi-address'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('HP React Widgets', 'hp-multi-address'); ?></th>
                        <td>
                            <?php if (hp_ma_is_react_widgets_active()): ?>
                                <span style="color: green;">✓ <?php esc_html_e('Active', 'hp-multi-address'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php esc_html_e('Not Active - Required for address picker UI', 'hp-multi-address'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('WooCommerce', 'hp-multi-address'); ?></th>
                        <td>
                            <?php if (hp_ma_is_woocommerce_active()): ?>
                                <span style="color: green;">✓ <?php esc_html_e('Active', 'hp-multi-address'); ?></span>
                            <?php else: ?>
                                <span style="color: red;">✗ <?php esc_html_e('Not Active', 'hp-multi-address'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php esc_html_e('Migration Information', 'hp-multi-address'); ?></h2>
                <div class="notice notice-info inline" style="margin: 0;">
                    <p>
                        <strong><?php esc_html_e('Replacing ThemeHigh Multiple Addresses?', 'hp-multi-address'); ?></strong><br>
                        <?php esc_html_e('This plugin uses the same address data structure as ThemeHigh. After activating this plugin:', 'hp-multi-address'); ?>
                    </p>
                    <ol>
                        <li><?php esc_html_e('Deactivate the ThemeHigh Multiple Addresses plugin', 'hp-multi-address'); ?></li>
                        <li><?php esc_html_e('Remove the WPCode snippet for "ThemeHigh Shortcode Display Type" if you added one', 'hp-multi-address'); ?></li>
                        <li><?php esc_html_e('Your existing addresses will continue to work with no data migration needed', 'hp-multi-address'); ?></li>
                    </ol>
                </div>
                
                <p class="submit">
                    <input type="submit" 
                           name="hp_ma_save_settings" 
                           class="button button-primary" 
                           value="<?php esc_attr_e('Save Settings', 'hp-multi-address'); ?>">
                </p>
            </form>
        </div>
        <?php
    }
}

