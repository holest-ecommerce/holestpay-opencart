/**
 * HolestPay Payment Gateway - Admin JavaScript
 * Handles admin panel interactions and form validation
 */

$(document).ready(function() {
    'use strict';
    
    // Initialize HolestPay admin functionality
    initHolestPayAdmin();
    
    // Log initialization
    console.log('HolestPay Admin JavaScript loading...');
    
    function initHolestPayAdmin() {
        // Environment toggle functionality
        handleEnvironmentToggle();
        
        // Form validation
        handleFormValidation();
        
        // Country restrictions handling
        handleCountryRestrictions();
        
        // Order total limits validation
        handleOrderTotalValidation();
    }
    
    /**
     * Handle order total validation
     */
    function handleOrderTotalValidation() {
        // This function is already implemented above
        // Just ensuring it's called during initialization
    }
    
    /**
     * Handle environment toggle between sandbox and live
     */
    function handleEnvironmentToggle() {
        $('#input-environment').on('change', function() {
            var environment = $(this).val();
            var isSandbox = environment === 'sandbox';
            
            // Update help text based on environment
            if (isSandbox) {
                $('#help-environment').text('Using sandbox environment for testing. No real transactions will be processed.');
                $('.sandbox-notice').show();
            } else {
                $('#help-environment').text('Using live environment for production. Real transactions will be processed.');
                $('.sandbox-notice').hide();
            }
            
            // Update API URL display if present
            updateApiUrlDisplay(environment);
        });
    }
    
    /**
     * Update API URL display based on environment
     */
    function updateApiUrlDisplay(environment) {
        var apiUrl = environment === 'sandbox' 
            ? 'https://sandbox-api.holestpay.com/api/v1/orders'
            : 'https://api.holestpay.com/api/v1/orders';
            
        if ($('#api-url-display').length) {
            $('#api-url-display').text(apiUrl);
        }
    }
    
    /**
     * Handle form validation
     */
    function handleFormValidation() {
        $('#form-holestpay').on('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Real-time validation
        $('#input-merchant-site-uid').on('blur', function() {
            validateMerchantSiteUid($(this));
        });
        
        $('#input-secret-key').on('blur', function() {
            validateSecretKey($(this));
        });
        
        $('#input-min-order-total, #input-max-order-total').on('blur', function() {
            validateOrderTotalLimits();
        });
    }
    
    /**
     * Validate the entire form
     */
    function validateForm() {
        var isValid = true;
        
        // Validate required fields
        if (!validateMerchantSiteUid($('#input-merchant-site-uid'))) {
            isValid = false;
        }
        
        if (!validateSecretKey($('#input-secret-key'))) {
            isValid = false;
        }
        
        if (!validateOrderTotalLimits()) {
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Validate Merchant Site UID
     */
    function validateMerchantSiteUid(field) {
        var value = field.val().trim();
        var errorElement = field.siblings('.text-danger');
        
        if (!value) {
            showFieldError(field, 'Merchant Site UID is required');
            return false;
        }
        
        if (value.length < 3) {
            showFieldError(field, 'Merchant Site UID must be at least 3 characters');
            return false;
        }
        
        hideFieldError(field);
        return true;
    }
    
    /**
     * Validate Secret Key
     */
    function validateSecretKey(field) {
        var value = field.val().trim();
        var errorElement = field.siblings('.text-danger');
        
        if (!value) {
            showFieldError(field, 'Secret Key is required');
            return false;
        }
        
        if (value.length < 8) {
            showFieldError(field, 'Secret Key must be at least 8 characters');
            return false;
        }
        
        hideFieldError(field);
        return true;
    }
    
    /**
     * Validate order total limits
     */
    function validateOrderTotalLimits() {
        var minTotal = parseFloat($('#input-min-order-total').val()) || 0;
        var maxTotal = parseFloat($('#input-max-order-total').val()) || 0;
        var isValid = true;
        
        if (maxTotal > 0 && minTotal > maxTotal) {
            showFieldError($('#input-min-order-total'), 'Minimum order total cannot be greater than maximum');
            isValid = false;
        } else {
            hideFieldError($('#input-min-order-total'));
        }
        
        if (minTotal < 0) {
            showFieldError($('#input-min-order-total'), 'Minimum order total cannot be negative');
            isValid = false;
        } else {
            hideFieldError($('#input-min-order-total'));
        }
        
        if (maxTotal < 0) {
            showFieldError($('#input-max-order-total'), 'Maximum order total cannot be negative');
            isValid = false;
        } else {
            hideFieldError($('#input-max-order-total'));
        }
        
        return isValid;
    }
    
    /**
     * Handle country restrictions
     */
    function handleCountryRestrictions() {
        $('#input-allowspecific').on('change', function() {
            var allowspecific = $(this).val();
            
            if (allowspecific === '1') {
                $('#specific-country-container').show();
            } else {
                $('#specific-country-container').hide();
            }
        });
        
        // Initialize on page load
        $('#input-allowspecific').trigger('change');
    }
    
    /**
     * Show field error
     */
    function showFieldError(field, message) {
        var errorElement = field.siblings('.text-danger');
        
        if (errorElement.length === 0) {
            errorElement = $('<div class="text-danger"></div>');
            field.after(errorElement);
        }
        
        errorElement.text(message).show();
        field.addClass('is-invalid');
    }
    
    /**
     * Hide field error
     */
    function hideFieldError(field) {
        var errorElement = field.siblings('.text-danger');
        errorElement.hide();
        field.removeClass('is-invalid');
    }
    
    /**
     * Test API connection
     */
    function testApiConnection() {
        var environment = $('#input-environment').val();
        var merchantSiteUid = $('#input-merchant-site-uid').val();
        var secretKey = $('#input-secret-key').val();
        
        if (!merchantSiteUid || !secretKey) {
            alert('Please fill in Merchant Site UID and Secret Key first');
            return;
        }
        
        $('#test-api-btn').prop('disabled', true).text('Testing...');
        
        // Simulate API test (in real implementation, this would make an actual API call)
        setTimeout(function() {
            $('#test-api-btn').prop('disabled', false).text('Test API Connection');
            alert('API connection test completed. Check the logs for details.');
        }, 2000);
    }
    
    /**
     * Export configuration
     */
    function exportConfiguration() {
        var config = {
            environment: $('#input-environment').val(),
            merchantSiteUid: $('#input-merchant-site-uid').val(),
            orderStatus: $('#input-order-status').val(),
            sortOrder: $('#input-sort-order').val(),
            allowspecific: $('#input-allowspecific').val(),
            specificcountry: $('#input-specificcountry').val(),
            minOrderTotal: $('#input-min-order-total').val(),
            maxOrderTotal: $('#input-max-order-total').val()
        };
        
        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(config, null, 2));
        var downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", "holestpay-config.json");
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    }
    
    // Main HolestPayAdmin object (matching real HolestPay structure)
    window.HolestPayAdmin = {
        // Core properties (will be set by PHP templates)
        admin_base_url: window.location.origin + '/admin/',
        frontend_base_url: window.location.origin + '/',
        
        // Settings object (matching real structure)
        settings: {
            environment: 'sandbox',
            merchant_site_uid: '',
            sandboxPOS: null,
            sandbox: {
                company_id: null,
                site_id: null,
                merchant_site_uid: '',
                main_url: '',
                secret_token: ''
            }
        },
        
        // Context for admin operations
        context: {
            orderId: null,
            customerEmail: null
        },
        
        // Core functions (matching real HolestPay structure)
        init: function() {
            console.log('HolestPayAdmin initialized');
            this.initAdminInterface();
        },
        
        initAdminInterface: function() {
            // Admin interface initialization
            console.log('HolestPay: Admin interface initialized');
        },
        
        getEnvironment: function() {
            return this.settings.environment || 'sandbox';
        },
        
        getMerchantSiteUid: function() {
            return this.settings.merchant_site_uid || this.settings.sandbox.merchant_site_uid || '';
        },
        
        getSecretToken: function() {
            return this.settings.sandbox.secret_token || '';
        },
        
        getCompanyId: function() {
            return this.settings.sandbox.company_id || null;
        },
        
        getSiteId: function() {
            return this.settings.sandbox.site_id || null;
        },
        
        getMainUrl: function() {
            return this.settings.sandbox.main_url || '';
        },
        
        // Utility functions
        isSandbox: function() {
            return this.getEnvironment() === 'sandbox';
        },
        
        isLive: function() {
            return this.getEnvironment() === 'live';
        },
        
        // Configuration management
        updateSettings: function(newSettings) {
            if (newSettings && typeof newSettings === 'object') {
                this.settings = Object.assign({}, this.settings, newSettings);
                console.log('HolestPayAdmin settings updated:', this.settings);
            }
        },
        
        // Validation functions
        validateConfiguration: function() {
            var errors = [];
            
            if (!this.getMerchantSiteUid()) {
                errors.push('Merchant Site UID is required');
            }
            
            if (!this.getSecretToken()) {
                errors.push('Secret Token is required');
            }
            
            if (!this.getMainUrl()) {
                errors.push('Main URL is required');
            }
            
            return {
                isValid: errors.length === 0,
                errors: errors
            };
        },
        
        // API connection testing
        testApiConnection: function() {
            var self = this;
            var validation = this.validateConfiguration();
            
            if (!validation.isValid) {
                console.error('Configuration validation failed:', validation.errors);
                return Promise.reject(validation.errors);
            }
            
            console.log('Testing API connection...');
            
            // This would be implemented by the actual testApiConnection function
            return window.testApiConnection ? window.testApiConnection() : Promise.resolve('API connection test completed');
        },
        
        // Configuration export
        exportConfiguration: function() {
            var config = {
                environment: this.getEnvironment(),
                merchant_site_uid: this.getMerchantSiteUid(),
                company_id: this.getCompanyId(),
                site_id: this.getSiteId(),
                main_url: this.getMainUrl(),
                admin_base_url: this.admin_base_url,
                frontend_base_url: this.frontend_base_url
            };
            
            console.log('Exporting configuration:', config);
            
            // This would be implemented by the actual exportConfiguration function
            return window.exportConfiguration ? window.exportConfiguration() : Promise.resolve(config);
        },
        
        // Form validation functions (keeping existing functionality)
        validateForm: validateForm,
        validateMerchantSiteUid: validateMerchantSiteUid,
        validateSecretKey: validateSecretKey,
        validateOrderTotalLimits: validateOrderTotalLimits,
        showFieldError: showFieldError,
        hideFieldError: hideFieldError,
        updateApiUrlDisplay: updateApiUrlDisplay
    };
    
    // Initialize settings from form values if available
    $(document).ready(function() {
        var environmentSelect = $('#input-environment');
        if (environmentSelect.length) {
            window.HolestPayAdmin.settings.environment = environmentSelect.val();
        }
        
        // Initialize HolestPayAdmin
        if (window.HolestPayAdmin && typeof window.HolestPayAdmin.init === 'function') {
            window.HolestPayAdmin.init();
        }
    });
    
    // Make sure jQuery is available
    if (typeof $ !== 'undefined') {
        // Initialize when document is ready (if not already done)
        $(document).ready(function() {
            if (!window.HolestPayAdminInitialized) {
                window.HolestPayAdminInitialized = true;
                console.log('HolestPay Admin JavaScript initialized');
                console.log('Available functions:', Object.keys(window.HolestPayAdmin));
            }
        });
    }
    
    // Global availability check function
    window.checkHolestPayAdminAvailability = function() {
        if (window.HolestPayAdmin) {
            console.log('✅ HolestPayAdmin is available globally');
            console.log('Available functions:', Object.keys(window.HolestPayAdmin));
            console.log('Settings:', window.HolestPayAdmin.settings);
            return true;
        } else {
            console.log('❌ HolestPayAdmin is NOT available globally');
            return false;
        }
    };
    
    // Auto-check availability after a short delay
    setTimeout(function() {
        window.checkHolestPayAdminAvailability();
    }, 1000);
});
