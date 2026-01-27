<?php
namespace HP_MA\Checkout;

use HP_MA\Settings;

/**
 * Checkout page integration.
 * 
 * Renders the address picker shortcodes on checkout and handles
 * synchronization between selected addresses and WooCommerce fields.
 */
class CheckoutIntegration
{
    /**
     * Register hooks.
     */
    public function register(): void
    {
        // Render address pickers on checkout
        add_action('woocommerce_before_checkout_billing_form', [$this, 'maybe_render_billing_above'], 5);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'maybe_render_billing_below'], 5);
        add_action('woocommerce_before_checkout_shipping_form', [$this, 'maybe_render_shipping_above'], 5);
        add_action('woocommerce_after_checkout_shipping_form', [$this, 'maybe_render_shipping_below'], 5);
        
        // Enqueue checkout scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts'], 25);
        
        // Add checkout field sync JavaScript
        add_action('wp_footer', [$this, 'output_checkout_sync_script'], 100);
        
        // Add checkout CSS
        add_action('wp_footer', [$this, 'output_checkout_styles'], 100);
    }
    
    /**
     * Check if we should render the picker (user logged in, on checkout, feature enabled).
     */
    private function should_render(string $type): bool
    {
        if (!is_checkout()) {
            return false;
        }
        
        if (!is_user_logged_in()) {
            return false;
        }
        
        if ($type === 'billing' && !Settings::is_billing_enabled()) {
            return false;
        }
        
        if ($type === 'shipping' && !Settings::is_shipping_enabled()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Render billing picker above the form.
     */
    public function maybe_render_billing_above(): void
    {
        if (!$this->should_render('billing')) {
            return;
        }
        
        if (Settings::get('billing_display_position') !== 'above') {
            return;
        }
        
        $this->render_picker('billing');
    }
    
    /**
     * Render billing picker below the form.
     */
    public function maybe_render_billing_below(): void
    {
        if (!$this->should_render('billing')) {
            return;
        }
        
        if (Settings::get('billing_display_position') !== 'below') {
            return;
        }
        
        $this->render_picker('billing');
    }
    
    /**
     * Render shipping picker above the form.
     */
    public function maybe_render_shipping_above(): void
    {
        if (!$this->should_render('shipping')) {
            return;
        }
        
        if (Settings::get('shipping_display_position') !== 'above') {
            return;
        }
        
        $this->render_picker('shipping');
    }
    
    /**
     * Render shipping picker below the form.
     */
    public function maybe_render_shipping_below(): void
    {
        if (!$this->should_render('shipping')) {
            return;
        }
        
        if (Settings::get('shipping_display_position') !== 'below') {
            return;
        }
        
        $this->render_picker('shipping');
    }
    
    /**
     * Render the address picker for a type.
     */
    private function render_picker(string $type): void
    {
        $shortcode = Settings::get($type . '_shortcode');
        $toggle_text = Settings::get($type . '_toggle_text');
        $collapsible = Settings::get('collapsible_picker') === 'yes';
        
        $container_id = 'hp-ma-' . esc_attr($type) . '-' . uniqid();
        
        ?>
        <div class="hp-ma-wrapper hp-ma-wrapper-<?php echo esc_attr($type); ?>" style="margin: 15px 0;">
            <?php if ($collapsible): ?>
                <!-- Toggle Button -->
                <button type="button" 
                        class="hp-ma-toggle-btn" 
                        data-target="<?php echo esc_attr($container_id); ?>"
                        style="border:1px solid #555;border-radius:4px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#333;color:#fff;">
                    <span class="hp-ma-toggle-icon">▼</span>
                    <?php echo esc_html($toggle_text); ?>
                </button>
                
                <!-- Collapsible Address Picker Container -->
                <div id="<?php echo esc_attr($container_id); ?>" 
                     class="hp-ma-shortcode-container hp-ma-shortcode-<?php echo esc_attr($type); ?>" 
                     style="display:none; margin-top:15px; padding:15px; border:1px solid #444; border-radius:8px; background:rgba(0,0,0,0.2);">
                    <?php $this->render_shortcode($shortcode); ?>
                </div>
            <?php else: ?>
                <!-- Always visible picker -->
                <div class="hp-ma-shortcode-container hp-ma-shortcode-<?php echo esc_attr($type); ?>" 
                     style="margin-top:10px; padding:15px; border:1px solid #ddd; border-radius:8px;">
                    <?php $this->render_shortcode($shortcode); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render a shortcode with fallback message.
     */
    private function render_shortcode(string $shortcode): void
    {
        $output = do_shortcode($shortcode);
        
        if (empty(trim($output)) || $output === $shortcode) {
            echo '<div style="padding: 15px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 8px; color: #666;">';
            echo '<strong>Address Picker:</strong> The shortcode <code>' . esc_html($shortcode) . '</code> is not available. ';
            echo 'Make sure the HP React Widgets plugin is active.';
            echo '</div>';
        } else {
            echo $output;
        }
    }
    
    /**
     * Enqueue scripts on checkout page.
     */
    public function enqueue_scripts(): void
    {
        if (!is_checkout()) {
            return;
        }
        
        // The HP React Widgets plugin handles its own asset loading
        // We just need to ensure our sync script runs
    }
    
    /**
     * Output checkout field synchronization JavaScript.
     */
    public function output_checkout_sync_script(): void
    {
        if (!is_checkout()) {
            return;
        }
        
        ?>
        <script>
        (function() {
            'use strict';

            const fieldMappings = {
                billing: {
                    firstName: 'billing_first_name',
                    lastName: 'billing_last_name',
                    company: 'billing_company',
                    address1: 'billing_address_1',
                    address2: 'billing_address_2',
                    city: 'billing_city',
                    state: 'billing_state',
                    postcode: 'billing_postcode',
                    country: 'billing_country',
                    phone: 'billing_phone',
                    email: 'billing_email'
                },
                shipping: {
                    firstName: 'shipping_first_name',
                    lastName: 'shipping_last_name',
                    company: 'shipping_company',
                    address1: 'shipping_address_1',
                    address2: 'shipping_address_2',
                    city: 'shipping_city',
                    state: 'shipping_state',
                    postcode: 'shipping_postcode',
                    country: 'shipping_country',
                    phone: 'shipping_phone'
                }
            };

            /**
             * Extract country code from various formats.
             */
            function getCountryCode(countryValue) {
                if (!countryValue) return '';
                
                // Already a 2-letter code
                if (countryValue.length === 2 && countryValue === countryValue.toUpperCase()) {
                    return countryValue;
                }
                
                // Format "Country Name (XX)"
                const parenMatch = countryValue.match(/\(([A-Z]{2})\)$/);
                if (parenMatch) {
                    return parenMatch[1];
                }
                
                // Common country name to code mapping
                const countryNameToCode = {
                    'United States': 'US',
                    'United Kingdom': 'GB',
                    'Canada': 'CA',
                    'Australia': 'AU',
                    'Germany': 'DE',
                    'France': 'FR',
                    'Italy': 'IT',
                    'Spain': 'ES',
                    'Netherlands': 'NL',
                    'Belgium': 'BE',
                    'Ireland': 'IE',
                    'New Zealand': 'NZ',
                    'Israel': 'IL',
                    'Mexico': 'MX',
                    'Brazil': 'BR',
                    'Japan': 'JP',
                    'China': 'CN',
                    'India': 'IN',
                    'Singapore': 'SG',
                    'Switzerland': 'CH',
                    'Austria': 'AT',
                    'Sweden': 'SE',
                    'Norway': 'NO',
                    'Denmark': 'DK',
                    'Finland': 'FI',
                    'Poland': 'PL',
                    'Portugal': 'PT',
                    'Puerto Rico': 'PR',
                };
                
                return countryNameToCode[countryValue] || countryValue;
            }

            /**
             * Fill checkout fields with address data.
             */
            function fillCheckoutFields(address, type) {
                const mapping = fieldMappings[type];
                if (!mapping || !address) return;

                // Set country first (triggers state dropdown update)
                if (address.country) {
                    const countryCode = getCountryCode(address.country);
                    const countryField = document.getElementById(mapping.country);
                    if (countryField) {
                        countryField.value = countryCode;
                        jQuery(countryField).trigger('change').trigger('input');
                        
                        // Wait for WooCommerce to update state dropdown
                        setTimeout(function() {
                            fillRemainingFields(address, mapping);
                        }, 500);
                        return;
                    }
                }

                fillRemainingFields(address, mapping);
            }

            /**
             * Fill remaining fields after country is set.
             */
            function fillRemainingFields(address, mapping) {
                Object.keys(mapping).forEach(function(hpField) {
                    if (hpField === 'country') return;
                    
                    const wcFieldId = mapping[hpField];
                    const value = address[hpField] || '';
                    const field = document.getElementById(wcFieldId);
                    
                    if (field) {
                        field.value = value;
                        jQuery(field).trigger('change').trigger('input');
                    }
                });

                // Trigger WooCommerce checkout update
                jQuery(document.body).trigger('update_checkout');
            }

            /**
             * Store selected address ID in hidden field for order processing.
             */
            function storeSelectedAddressId(addressId, type) {
                const hiddenFieldName = 'hp_ma_selected_' + type;
                let hiddenField = document.querySelector('input[name="' + hiddenFieldName + '"]');
                
                if (!hiddenField) {
                    hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = hiddenFieldName;
                    const form = document.querySelector('form.checkout');
                    if (form) {
                        form.appendChild(hiddenField);
                    }
                }

                hiddenField.value = addressId;
                console.log('[HP-MA] Stored address ID:', addressId, 'for', type);
            }

            // Listen for address selection events from HP React Widgets
            window.addEventListener('hpAddressSelected', function(e) {
                if (e.detail && e.detail.address && e.detail.type) {
                    fillCheckoutFields(e.detail.address, e.detail.type);
                    storeSelectedAddressId(e.detail.addressId || e.detail.address.id, e.detail.type);
                }
            });

            // Listen for address copy events
            window.addEventListener('hpRWAddressCopied', function(e) {
                if (e.detail && e.detail.addresses && e.detail.toType) {
                    const selectedId = e.detail.selectedId;
                    const address = e.detail.addresses.find(function(addr) {
                        return addr.id === selectedId;
                    });
                    if (address) {
                        fillCheckoutFields(address, e.detail.toType);
                        storeSelectedAddressId(selectedId, e.detail.toType);
                    }
                }
            });

            // Toggle functionality for collapsible pickers
            document.addEventListener('click', function(e) {
                const toggleBtn = e.target.closest('.hp-ma-toggle-btn');
                if (!toggleBtn) return;
                
                e.preventDefault();
                
                const targetId = toggleBtn.getAttribute('data-target');
                const container = document.getElementById(targetId);
                
                if (!container) return;
                
                const isHidden = container.style.display === 'none';
                
                if (isHidden) {
                    container.style.display = 'block';
                    toggleBtn.classList.add('expanded');
                } else {
                    container.style.display = 'none';
                    toggleBtn.classList.remove('expanded');
                }
            });

        })();
        </script>
        <?php
    }
    
    /**
     * Output checkout-specific CSS.
     */
    public function output_checkout_styles(): void
    {
        if (!is_checkout()) {
            return;
        }
        
        ?>
        <style>
        /* HP Multi-Address Checkout Styles */
        
        /* Modal z-index fixes for HP React Widgets */
        [data-radix-portal],
        .hp-edit-address-modal,
        [role="dialog"],
        .fixed.inset-0 {
            z-index: 999999 !important;
        }
        
        /* Modal backdrop */
        [data-radix-portal] > div:first-child,
        .fixed.inset-0.bg-black\/80,
        [data-radix-portal] [data-state="open"] + div {
            z-index: 999998 !important;
            background-color: rgba(0, 0, 0, 0.92) !important;
            backdrop-filter: blur(4px) !important;
        }
        
        /* Modal content */
        [data-radix-portal] [role="dialog"] {
            z-index: 999999 !important;
        }
        
        /* Toggle button styles */
        .hp-ma-toggle-btn:hover {
            opacity: 0.9;
        }
        
        .hp-ma-toggle-btn.expanded .hp-ma-toggle-icon {
            transform: rotate(180deg);
        }
        
        .hp-ma-toggle-icon {
            transition: transform 0.2s ease;
            font-size: 10px;
        }
        
        /* Container animation */
        .hp-ma-shortcode-container {
            transition: all 0.3s ease;
        }
        
        /* Wrapper spacing */
        .hp-ma-wrapper {
            clear: both;
        }
        
        /* Fix for cropped address picker on checkout */
        .hp-ma-shortcode-container {
            overflow: visible !important;
            min-height: auto !important;
            height: auto !important;
        }
        
        /* Ensure the address slider can scroll horizontally */
        .hp-ma-shortcode-container .address-slider {
            overflow-x: auto !important;
            overflow-y: visible !important;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            padding-bottom: 10px;
        }
        
        /* Ensure cards are fully visible */
        .hp-ma-shortcode-container .address-card {
            overflow: visible !important;
        }
        
        /* Fix any parent containers that might be causing cropping */
        .hp-ma-wrapper,
        .hp-ma-wrapper > div,
        [id^="hp-address-card-picker"] {
            overflow: visible !important;
        }
        </style>
        <?php
    }
}

