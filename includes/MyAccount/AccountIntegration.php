<?php
namespace HP_MA\MyAccount;

use HP_MA\Settings;
use HP_MA\AddressManager;

/**
 * My Account page integration.
 * 
 * Handles displaying multiple addresses and the edit-address functionality
 * on WooCommerce's My Account page.
 */
class AccountIntegration
{
    /**
     * Register hooks.
     */
    public function register(): void
    {
        if (!Settings::is_my_account_enabled()) {
            return;
        }
        
        // Display additional addresses on My Account addresses page
        add_action('woocommerce_after_edit_account_address_form', [$this, 'display_additional_addresses']);
        
        // Modify the address edit form when editing a custom address
        add_filter('woocommerce_address_to_edit', [$this, 'modify_edit_form'], 10, 2);
        
        // Save custom address after validation
        add_action('woocommerce_after_save_address_validation', [$this, 'save_address'], 10, 3);
        
        // Handle delete and set-default actions
        add_action('woocommerce_before_edit_account_address_form', [$this, 'handle_actions']);
        
        // Add scripts to My Account page
        add_action('wp_footer', [$this, 'output_scripts'], 100);
    }
    
    /**
     * Display additional addresses section on My Account.
     */
    public function display_additional_addresses(): void
    {
        if (!is_user_logged_in()) {
            return;
        }
        
        $customer_id = get_current_user_id();
        
        // Display billing addresses if enabled
        if (Settings::is_billing_enabled()) {
            $this->display_address_section($customer_id, 'billing');
        }
        
        // Display shipping addresses if enabled
        if (Settings::is_shipping_enabled() && wc_shipping_enabled() && !wc_ship_to_billing_address_only()) {
            $this->display_address_section($customer_id, 'shipping');
        }
    }
    
    /**
     * Display a section of additional addresses.
     */
    private function display_address_section(int $customer_id, string $type): void
    {
        $addresses = AddressManager::get_addresses($customer_id, $type);
        $default_address_key = AddressManager::find_matching_address($customer_id, $type);
        
        $title = $type === 'billing' 
            ? __('Additional Billing Addresses', 'hp-multi-address')
            : __('Additional Shipping Addresses', 'hp-multi-address');
        
        ?>
        <div class="hp-ma-addresses hp-ma-addresses-<?php echo esc_attr($type); ?>" style="margin-top: 30px;">
            <h3><?php echo esc_html($title); ?></h3>
            
            <?php if (is_array($addresses) && !empty($addresses)): ?>
                <div class="hp-ma-address-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 15px;">
                    <?php foreach ($addresses as $key => $address): ?>
                        <?php 
                        // Skip if this is the current default
                        if ($key === $default_address_key) {
                            continue;
                        }
                        
                        $this->display_address_card($customer_id, $type, $key, $address);
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($this->can_add_address($customer_id, $type)): ?>
                <div class="hp-ma-add-address" style="margin-top: 20px;">
                    <a href="<?php echo esc_url($this->get_add_address_url($type)); ?>" class="button">
                        + <?php esc_html_e('Add New Address', 'hp-multi-address'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Display a single address card.
     */
    private function display_address_card(int $customer_id, string $type, string $key, array $address): void
    {
        $formatted_address = AddressManager::get_formatted_address($address, $type);
        $edit_url = $this->get_edit_address_url($type, $key);
        
        ?>
        <div class="hp-ma-address-card" style="border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #fafafa;">
            <div class="hp-ma-address-content" style="margin-bottom: 15px;">
                <?php echo $formatted_address; ?>
            </div>
            
            <div class="hp-ma-address-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">
                    <?php esc_html_e('Edit', 'hp-multi-address'); ?>
                </a>
                
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('hp_ma_address_action', 'hp_ma_nonce'); ?>
                    <input type="hidden" name="hp_ma_action" value="set_default">
                    <input type="hidden" name="hp_ma_type" value="<?php echo esc_attr($type); ?>">
                    <input type="hidden" name="hp_ma_key" value="<?php echo esc_attr($key); ?>">
                    <button type="submit" class="button button-small">
                        <?php esc_html_e('Set as Default', 'hp-multi-address'); ?>
                    </button>
                </form>
                
                <form method="post" style="display: inline;" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to delete this address?', 'hp-multi-address'); ?>');">
                    <?php wp_nonce_field('hp_ma_address_action', 'hp_ma_nonce'); ?>
                    <input type="hidden" name="hp_ma_action" value="delete">
                    <input type="hidden" name="hp_ma_type" value="<?php echo esc_attr($type); ?>">
                    <input type="hidden" name="hp_ma_key" value="<?php echo esc_attr($key); ?>">
                    <button type="submit" class="button button-small" style="color: #a00;">
                        <?php esc_html_e('Delete', 'hp-multi-address'); ?>
                    </button>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle delete and set-default actions.
     */
    public function handle_actions(): void
    {
        if (!isset($_POST['hp_ma_action']) || !isset($_POST['hp_ma_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['hp_ma_nonce'], 'hp_ma_address_action')) {
            wc_add_notice(__('Security check failed.', 'hp-multi-address'), 'error');
            return;
        }
        
        $action = sanitize_key($_POST['hp_ma_action']);
        $type = sanitize_key($_POST['hp_ma_type'] ?? '');
        $key = sanitize_key($_POST['hp_ma_key'] ?? '');
        $user_id = get_current_user_id();
        
        if (!in_array($type, ['billing', 'shipping']) || empty($key)) {
            return;
        }
        
        switch ($action) {
            case 'delete':
                if (AddressManager::delete_address($user_id, $type, $key)) {
                    wc_add_notice(__('Address deleted successfully.', 'hp-multi-address'), 'success');
                } else {
                    wc_add_notice(__('Failed to delete address.', 'hp-multi-address'), 'error');
                }
                break;
                
            case 'set_default':
                if (AddressManager::set_default_address($user_id, $type, $key)) {
                    wc_add_notice(__('Default address updated.', 'hp-multi-address'), 'success');
                } else {
                    wc_add_notice(__('Failed to update default address.', 'hp-multi-address'), 'error');
                }
                break;
        }
        
        // Redirect to avoid form resubmission
        wp_safe_redirect(wc_get_endpoint_url('edit-address', '', wc_get_page_permalink('myaccount')));
        exit;
    }
    
    /**
     * Modify the edit form when editing a custom address.
     */
    public function modify_edit_form(array $address, string $load_address): array
    {
        if (empty($load_address) || !isset($_GET['atype'])) {
            return $address;
        }
        
        $atype = sanitize_key($_GET['atype']);
        
        // Adding a new address - clear all fields
        if ($atype === 'add-address') {
            $allowed_countries = WC()->countries->get_allowed_countries();
            
            foreach ($address as $key => $field) {
                // Keep country if only one allowed
                if (count($allowed_countries) === 1 && $key === $load_address . '_country') {
                    continue;
                }
                $address[$key]['value'] = '';
            }
            
            return $address;
        }
        
        // Editing an existing custom address
        $user_id = get_current_user_id();
        $custom_address = AddressManager::get_addresses($user_id, $load_address, $atype);
        
        if (!is_array($custom_address)) {
            return $address;
        }
        
        // Populate form with custom address values
        foreach ($address as $key => $field) {
            if (isset($custom_address[$key])) {
                $address[$key]['value'] = $custom_address[$key];
            }
        }
        
        return $address;
    }
    
    /**
     * Save address after validation.
     */
    public function save_address(int $user_id, string $load_address, array $address): void
    {
        if (empty($load_address) || !isset($_GET['atype'])) {
            // Saving the default address - check if we need to sync to custom storage
            $this->maybe_sync_default_to_custom($user_id, $load_address);
            return;
        }
        
        // Check for validation errors
        if (wc_notice_count('error') > 0) {
            return;
        }
        
        $atype = sanitize_key($_GET['atype']);
        $prefix = $load_address . '_';
        
        // Build the address array with prefixed keys
        $address_data = [];
        $fields = AddressManager::get_address_field_keys($load_address);
        
        foreach ($fields as $field) {
            $post_key = $field;
            $address_data[$field] = isset($_POST[$post_key]) ? wc_clean($_POST[$post_key]) : '';
        }
        
        if ($atype === 'add-address') {
            // Save new address
            $new_key = AddressManager::save_address($user_id, $address_data, $load_address);
            
            if ($new_key) {
                wc_add_notice(__('Address added successfully.', 'hp-multi-address'), 'success');
            } else {
                wc_add_notice(__('Failed to add address. You may have reached the address limit.', 'hp-multi-address'), 'error');
                return;
            }
        } else {
            // Update existing address
            if (AddressManager::update_address($user_id, $address_data, $load_address, $atype)) {
                wc_add_notice(__('Address updated successfully.', 'hp-multi-address'), 'success');
            } else {
                wc_add_notice(__('Failed to update address.', 'hp-multi-address'), 'error');
                return;
            }
        }
        
        // Redirect to addresses page
        wp_safe_redirect(wc_get_endpoint_url('edit-address', '', wc_get_page_permalink('myaccount')));
        exit;
    }
    
    /**
     * Sync default WooCommerce address to custom storage if needed.
     */
    private function maybe_sync_default_to_custom(int $user_id, string $type): void
    {
        $existing = AddressManager::get_addresses($user_id, $type);
        
        // If user already has custom addresses, check if default matches any
        if (is_array($existing) && !empty($existing)) {
            $matching_key = AddressManager::find_matching_address($user_id, $type);
            
            if ($matching_key) {
                // Update the matching custom address with new default values
                $default = AddressManager::get_default_address($user_id, $type);
                AddressManager::update_address($user_id, $default, $type, $matching_key);
            }
        }
    }
    
    /**
     * Check if user can add more addresses.
     */
    private function can_add_address(int $customer_id, string $type): bool
    {
        $count = AddressManager::get_address_count($customer_id, $type);
        $limit = Settings::get_address_limit();
        
        return $count < $limit;
    }
    
    /**
     * Get URL to add a new address.
     */
    private function get_add_address_url(string $type): string
    {
        $base_url = wc_get_endpoint_url('edit-address', $type, wc_get_page_permalink('myaccount'));
        return add_query_arg('atype', 'add-address', $base_url);
    }
    
    /**
     * Get URL to edit an address.
     */
    private function get_edit_address_url(string $type, string $key): string
    {
        $base_url = wc_get_endpoint_url('edit-address', $type, wc_get_page_permalink('myaccount'));
        return add_query_arg('atype', $key, $base_url);
    }
    
    /**
     * Output page-specific scripts.
     */
    public function output_scripts(): void
    {
        if (!is_wc_endpoint_url('edit-address')) {
            return;
        }
        
        ?>
        <style>
        .hp-ma-addresses {
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        
        .hp-ma-address-card {
            transition: box-shadow 0.2s ease;
        }
        
        .hp-ma-address-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .hp-ma-address-actions .button {
            margin: 0 !important;
        }
        </style>
        <?php
    }
}

