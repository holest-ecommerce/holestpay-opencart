/**
 * HolestPay Payment Gateway - Frontend JavaScript
 * Simple frontend initialization matching Magento implementation
 */

(function () {
    // Global utility functions for HolestPay (matching Magento)
    window.hpay_isTruthy = function(value) {
        if (value === null || value === undefined) return false;
        if (typeof value === 'boolean') return value === true;
        if (typeof value === 'string') {
            var str = value.toLowerCase().trim();
            return str === 'true' || str === '1' || str === 'yes' || str === 'on';
        }
        if (typeof value === 'number') return value === 1;
        return false;
    };
    
    window.hpay_isFalsy = function(value) {
        if (value === null || value === undefined) return false;
        if (typeof value === 'boolean') return value === false;
        if (typeof value === 'string') {
            var str = value.toLowerCase().trim();
            return str === 'false' || str === '0' || str === 'no' || str === 'off';
        }
        if (typeof value === 'number') return value === 0;
        return false;
    };

    var obj = window.HolestPayCheckout || {
        // Place your global frontend JavaScript here. This object is available on all pages.
        version: '1.0.0',
        POS: null,
        hpaylang: 'en',
        init: function () {
            // Initialize POS data from window.HolestPayCheckout.POS if available
            if (window.HolestPayCheckout && window.HolestPayCheckout.POS) {
                this.POS = window.HolestPayCheckout.POS;
                console.log('HolestPayCheckout POS data loaded:', this.POS);
            }
            
            // Set language
            if (window.HolestPayCheckout && window.HolestPayCheckout.hpaylang) {
                this.hpaylang = window.HolestPayCheckout.hpaylang;
            }
            
            // Initialize footer logotypes if enabled
            this.initFooterLogotypes();
        },
        
        /**
         * Initialize footer logotypes if enabled
         */
        initFooterLogotypes: function() {
            // Check if footer logotypes are enabled in checkout config
            if (window.checkoutConfig && 
                window.checkoutConfig.payment && 
                window.checkoutConfig.payment.holestpay && 
                window.checkoutConfig.payment.holestpay.insertFooterLogotypes) {
                
                console.log('HolestPay: Footer logotypes enabled');
                this.setupFooterLogotypes();
            } else {
                console.log('HolestPay: Footer logotypes disabled or not configured');
            }
        },
        
        /**
         * Setup footer logotypes display
         */
        setupFooterLogotypes: function() {
            // This will be handled by the FooterLogotypes block in PHP
            // The block automatically renders the logotypes when enabled
            console.log('HolestPay: Footer logotypes setup complete');
        },
        
        /**
         * Get available payment methods from POS data
         */
        getPaymentMethods: function() {
            if (!this.POS || !this.POS.payment) {
                return [];
            }
            
            return this.POS.payment.filter(function(method) {
                // Only show enabled methods that are not hidden
                return window.hpay_isTruthy(method.Enabled) && !window.hpay_isFalsy(method.Hidden);
            });
        },
        
        /**
         * Get localized text for payment method
         */
        getLocalizedText: function(method, field) {
            if (method.localized && method.localized[this.hpaylang]) {
                return method.localized[this.hpaylang][field] || method[field] || '';
            }
            return method[field] || '';
        },
        
        /**
         * Check if method supports Card-on-File
         */
        supportsCOF: function(method) {
            return method.SubsciptionsType && 
                   /mit|cof/.test(method.SubsciptionsType);
        },
        
        /**
         * Get customer tokens for logged-in users
         */
        getCustomerTokens: function() {
            if (!this.context.customerEmail) {
                return [];
            }
            
            // This would be populated by the payment method renderer
            return this.context.customerTokens || [];
        },
        
        /**
         * Remove customer token
         */
        removeToken: function(tokenValue, callback) {
            if (!this.context.customerEmail) {
                if (callback) callback(false, 'Customer not logged in');
                return;
            }
            
            // Token removal would be handled by the payment gateway
            if (callback) callback(false, 'Not implemented in OpenCart version');
        },
        
        /**
         * Set token as default
         */
        setDefaultToken: function(tokenValue, callback) {
            if (!this.context.customerEmail) {
                if (callback) callback(false, 'Customer not logged in');
                return;
            }
            
            // Set default token would be handled by the payment gateway
            if (callback) callback(false, 'Not implemented in OpenCart version');
        }
    };
    
    // Ensure a mutable context to store order/customer info
    obj.context = obj.context || { 
        orderId: null, 
        customerEmail: null,
        customerTokens: []
    };
    
    // Expose only the correctly spelled global
    window.HolestPayCheckout = obj;

    if (typeof window.HolestPayCheckout.init === 'function') {
        try { window.HolestPayCheckout.init(); } catch (e) { /* no-op */ }
    }

    // Initialize when document is ready
    $(document).ready(function() {
        if (!window.HolestPayFrontendInitialized) {
            window.HolestPayFrontendInitialized = true;
            console.log('HolestPay Frontend JavaScript initialized');
            console.log('HolestPayCheckout properties:', Object.keys(window.HolestPayCheckout));
        }
    });
    
    // Global availability check function
    window.checkHolestPayAvailability = function() {
        if (window.HolestPayCheckout) {
            console.log('✅ HolestPayCheckout is available globally');
            console.log('Available properties:', Object.keys(window.HolestPayCheckout));
            console.log('HolestPayCheckout.POS:', window.HolestPayCheckout.POS);
            console.log('HolestPayCheckout.environment:', window.HolestPayCheckout.environment);
            return true;
        } else {
            console.log('❌ HolestPayCheckout is NOT available globally');
            return false;
        }
    };
    
    // Auto-check availability after a short delay
    setTimeout(function() {
        window.checkHolestPayAvailability();
    }, 1000);
})();
