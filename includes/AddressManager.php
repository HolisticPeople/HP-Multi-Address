<?php
namespace HP_MA;

/**
 * Core address management utilities.
 * 
 * Handles CRUD operations for multiple addresses stored in user meta.
 * Uses the same data structure as ThemeHigh for backward compatibility.
 */
class AddressManager
{
    /**
     * Get all custom addresses for a user.
     *
     * @param int         $user_id     User ID.
     * @param string|null $type        Address type ('billing' or 'shipping'). Null returns all.
     * @param string|null $address_key Specific address key (e.g., 'address_0').
     * @param string|null $field       Specific field within the address.
     * @return mixed
     */
    public static function get_addresses(int $user_id, ?string $type = null, ?string $address_key = null, ?string $field = null)
    {
        $addresses = get_user_meta($user_id, HP_MA_ADDRESS_KEY, true);
        
        if (!is_array($addresses)) {
            return false;
        }
        
        // Return all addresses
        if ($type === null) {
            return $addresses;
        }
        
        // Return addresses for specific type
        if (!isset($addresses[$type])) {
            return false;
        }
        
        // Return all addresses of this type
        if ($address_key === null) {
            return $addresses[$type];
        }
        
        // Return specific address
        if (!isset($addresses[$type][$address_key])) {
            return false;
        }
        
        // Return specific field
        if ($field !== null) {
            return $addresses[$type][$address_key][$field] ?? false;
        }
        
        return $addresses[$type][$address_key];
    }
    
    /**
     * Get the WooCommerce default address for a user.
     *
     * @param int    $user_id User ID.
     * @param string $type    Address type ('billing' or 'shipping').
     * @return array
     */
    public static function get_default_address(int $user_id, string $type): array
    {
        $fields = self::get_address_field_keys($type);
        $address = [];
        
        foreach ($fields as $field) {
            $address[$field] = get_user_meta($user_id, $field, true) ?: '';
        }
        
        return $address;
    }
    
    /**
     * Get address field keys for a type.
     *
     * @param string $type Address type ('billing' or 'shipping').
     * @return array
     */
    public static function get_address_field_keys(string $type): array
    {
        $prefix = $type . '_';
        
        $base_fields = [
            'first_name',
            'last_name',
            'company',
            'address_1',
            'address_2',
            'city',
            'state',
            'postcode',
            'country',
            'phone',
        ];
        
        // Email only for billing
        if ($type === 'billing') {
            $base_fields[] = 'email';
        }
        
        return array_map(fn($field) => $prefix . $field, $base_fields);
    }
    
    /**
     * Save a new address for a user.
     *
     * @param int    $user_id User ID.
     * @param array  $address Address data (with prefixed keys like 'billing_first_name').
     * @param string $type    Address type ('billing' or 'shipping').
     * @return string|false The new address key or false on failure.
     */
    public static function save_address(int $user_id, array $address, string $type)
    {
        $all_addresses = get_user_meta($user_id, HP_MA_ADDRESS_KEY, true);
        $all_addresses = is_array($all_addresses) ? $all_addresses : [];
        
        // Check address limit
        $limit = Settings::get_address_limit();
        $type_addresses = $all_addresses[$type] ?? [];
        
        if (count($type_addresses) >= $limit) {
            return false;
        }
        
        // Initialize type array if needed
        if (!isset($all_addresses[$type])) {
            $all_addresses[$type] = [];
            
            // If no custom addresses exist, save the current default as address_0
            $default = self::get_default_address($user_id, $type);
            if (self::is_address_valid($default)) {
                $all_addresses[$type]['address_0'] = $default;
            }
        }
        
        // Generate new address key
        $new_key = self::generate_address_key($user_id, $type);
        
        // Save the address
        $all_addresses[$type][$new_key] = $address;
        
        update_user_meta($user_id, HP_MA_ADDRESS_KEY, $all_addresses);
        
        return $new_key;
    }
    
    /**
     * Update an existing address.
     *
     * @param int    $user_id     User ID.
     * @param array  $address     Address data.
     * @param string $type        Address type.
     * @param string $address_key Address key to update.
     * @return bool
     */
    public static function update_address(int $user_id, array $address, string $type, string $address_key): bool
    {
        $all_addresses = get_user_meta($user_id, HP_MA_ADDRESS_KEY, true);
        
        if (!is_array($all_addresses) || !isset($all_addresses[$type][$address_key])) {
            return false;
        }
        
        $all_addresses[$type][$address_key] = $address;
        
        return (bool) update_user_meta($user_id, HP_MA_ADDRESS_KEY, $all_addresses);
    }
    
    /**
     * Delete an address.
     *
     * @param int    $user_id     User ID.
     * @param string $type        Address type.
     * @param string $address_key Address key to delete.
     * @return bool
     */
    public static function delete_address(int $user_id, string $type, string $address_key): bool
    {
        $all_addresses = get_user_meta($user_id, HP_MA_ADDRESS_KEY, true);
        
        if (!is_array($all_addresses) || !isset($all_addresses[$type][$address_key])) {
            return false;
        }
        
        unset($all_addresses[$type][$address_key]);
        
        return (bool) update_user_meta($user_id, HP_MA_ADDRESS_KEY, $all_addresses);
    }
    
    /**
     * Set the default address for a type.
     *
     * This copies the address data to WooCommerce's native user meta fields.
     *
     * @param int    $user_id     User ID.
     * @param string $type        Address type.
     * @param string $address_key Address key to set as default.
     * @return bool
     */
    public static function set_default_address(int $user_id, string $type, string $address_key): bool
    {
        $address = self::get_addresses($user_id, $type, $address_key);
        
        if (!is_array($address)) {
            return false;
        }
        
        // Update WooCommerce user meta
        foreach ($address as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
        
        return true;
    }
    
    /**
     * Generate a unique address key.
     *
     * @param int    $user_id User ID.
     * @param string $type    Address type.
     * @return string
     */
    public static function generate_address_key(int $user_id, string $type): string
    {
        $addresses = self::get_addresses($user_id, $type);
        
        if (!is_array($addresses) || empty($addresses)) {
            return 'address_1';
        }
        
        // Find the highest numeric suffix
        $max_id = 0;
        foreach (array_keys($addresses) as $key) {
            if (preg_match('/^address_(\d+)$/', $key, $matches)) {
                $max_id = max($max_id, (int) $matches[1]);
            } elseif (preg_match('/^addr_\d+_\d+$/', $key)) {
                // Handle HP React Widgets format: addr_{timestamp}_{random}
                // Generate a new unique key
                return 'addr_' . time() . '_' . wp_rand(1000, 9999);
            }
        }
        
        return 'address_' . ($max_id + 1);
    }
    
    /**
     * Check if an address has valid data.
     *
     * @param array $address Address data.
     * @return bool
     */
    public static function is_address_valid(array $address): bool
    {
        // Check for minimum required fields
        $has_name = false;
        $has_address = false;
        
        foreach ($address as $key => $value) {
            if (empty($value)) {
                continue;
            }
            
            if (strpos($key, 'first_name') !== false) {
                $has_name = true;
            }
            if (strpos($key, 'address_1') !== false) {
                $has_address = true;
            }
        }
        
        return $has_name || $has_address;
    }
    
    /**
     * Check if a similar address already exists for the user.
     *
     * @param int    $user_id User ID.
     * @param string $type    Address type.
     * @return string|false The matching address key or false.
     */
    public static function find_matching_address(int $user_id, string $type): string|false
    {
        $default = self::get_default_address($user_id, $type);
        $addresses = self::get_addresses($user_id, $type);
        
        if (!is_array($addresses)) {
            return false;
        }
        
        foreach ($addresses as $key => $address) {
            if (self::addresses_match($default, $address)) {
                return $key;
            }
        }
        
        return false;
    }
    
    /**
     * Compare two addresses for equality.
     *
     * @param array $address1 First address.
     * @param array $address2 Second address.
     * @return bool
     */
    public static function addresses_match(array $address1, array $address2): bool
    {
        // Compare key fields
        $compare_fields = ['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country'];
        
        foreach ($compare_fields as $field) {
            foreach ($address1 as $key => $value) {
                if (strpos($key, $field) !== false) {
                    $matched = false;
                    foreach ($address2 as $key2 => $value2) {
                        if (strpos($key2, $field) !== false && $value === $value2) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched && !empty($value)) {
                        return false;
                    }
                    break;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Get the count of addresses for a user and type.
     *
     * @param int    $user_id User ID.
     * @param string $type    Address type.
     * @return int
     */
    public static function get_address_count(int $user_id, string $type): int
    {
        $addresses = self::get_addresses($user_id, $type);
        
        if (!is_array($addresses)) {
            // Check if user has a default address
            $default = self::get_default_address($user_id, $type);
            return self::is_address_valid($default) ? 1 : 0;
        }
        
        return count($addresses);
    }
    
    /**
     * Format an address for display.
     *
     * @param array  $address Address data.
     * @param string $type    Address type.
     * @return array Formatted address for WC()->countries->get_formatted_address()
     */
    public static function format_for_display(array $address, string $type): array
    {
        $prefix = $type . '_';
        
        return [
            'first_name' => $address[$prefix . 'first_name'] ?? '',
            'last_name'  => $address[$prefix . 'last_name'] ?? '',
            'company'    => $address[$prefix . 'company'] ?? '',
            'address_1'  => $address[$prefix . 'address_1'] ?? '',
            'address_2'  => $address[$prefix . 'address_2'] ?? '',
            'city'       => $address[$prefix . 'city'] ?? '',
            'state'      => $address[$prefix . 'state'] ?? '',
            'postcode'   => $address[$prefix . 'postcode'] ?? '',
            'country'    => $address[$prefix . 'country'] ?? '',
        ];
    }
    
    /**
     * Get a formatted address string.
     *
     * @param array  $address   Address data.
     * @param string $type      Address type.
     * @param string $separator Line separator.
     * @return string
     */
    public static function get_formatted_address(array $address, string $type, string $separator = '<br/>'): string
    {
        $formatted = self::format_for_display($address, $type);
        return WC()->countries->get_formatted_address($formatted, $separator);
    }
}


