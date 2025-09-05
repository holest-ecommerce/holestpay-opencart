/**
 * HolestPayCheckout JavaScript Object
 * Handles frontend checkout functionality for HolestPay
 */

var HolestPayCheckout = {
    // Core properties (set by PHP)
    merchant_site_uid: '',
    hpay_url: '',
    site_url: '',
    labels: {},
    ajax_url: '',
    language: 'en',
    hpaylang: 'en',
    plugin_version: '1.0.0',
    environment: 'sandbox',
    dock_payment_methods: false,
    hpay_autoinit: 0,
    
    // Cart data (updated dynamically)
    cart: {},
    
    // POS configuration (set after HPay.loadHPayUI())
    POS: null,
    
    // Runtime properties
    initialized: false,
    paymentForm: null,
    
    init: function(options) {
        // Merge options into this object
        if (options) {
            Object.assign(this, options);
        }
        
        this.initialized = true;
        
        // Initialize tracking variables (like Magento sample)
        this.adapted_checkout_destroy = null;
        this.prev_hpay_shipping_method = null;
        
        // Initialize checkout interface
        this.initializeCheckout();
        
        console.log('HolestPayCheckout initialized', this);
    },
    
    initializeCheckout: function() {
        var self = this;
        
        // Initialize payment method selection
        this.initializePaymentMethods();
        
        // Initialize vault token management
        this.initializeVaultTokens();
        
        // Initialize shipping method integration
        this.initializeShippingMethods();
        
        // Set up form submission
        this.setupFormSubmission();
        
        // Update cart data periodically
        this.startCartMonitoring();
        
        // Load main HolestPay script (hpay.js) and initialize
        this.loadHPayScript();
    },
    
    loadHPayScript: function() {
        var self = this;
        
        // Check if HolestPay script is already loaded
        // CRITICAL: It is totally safe to call HPayInit or HPay.loadHPayUI() multiple times
        if (typeof HPayInit !== 'undefined') {
            this.initializeHPay();
            return;
        }
        
        // Load main hpay.js script from HolestPay CDN
        var script = document.createElement('script');
        script.src = this.hpay_url + '/clientpay/cscripts/hpay.js?v=' + this.plugin_version;
        script.onload = function() {
            self.initializeHPay();
        };
        script.onerror = function() {
            console.error('Failed to load HolestPay hpay.js script from:', script.src);
        };
        document.head.appendChild(script);
    },
    
    initializeHPay: function() {
        var self = this;
        
        if (typeof HPayInit === 'undefined') {
            console.error('HPayInit not available after loading hpay.js');
            return;
        }
        
        // Initialize HolestPay with merchant credentials (NO SECRET KEY for frontend security)
        HPayInit(this.merchant_site_uid, this.hpaylang, this.environment).then(function(client) {
            console.log('HolestPay initialized successfully');
            self.hpayClient = client;
            
            // Load HolestPay UI - this provides menu UI and other functions
            return client.loadHPayUI();
        }).then(function(loaded) {
            console.log('HolestPay UI loaded successfully');
            
            // Now HPay object should be available with UI functions
            if (typeof HPay !== 'undefined') {
                // Set POS configuration reference for easy access
                self.POS = HPay.POS;
                
                // CRITICAL: After HPay.loadHPayUI(), global functions are available:
                // - presentHPayPayForm (from hpay.js)
                // - hpay_dialog_open 
                // - hpay_alert_dialog
                // These can now be called globally
                
                console.log('HolestPay global functions available:', {
                    presentHPayPayForm: typeof presentHPayPayForm !== 'undefined',
                    hpay_dialog_open: typeof hpay_dialog_open !== 'undefined',
                    hpay_alert_dialog: typeof hpay_alert_dialog !== 'undefined'
                });
                
                // Setup payment form integration after UI is loaded
                self.setupHPayIntegration();
            }
        }).catch(function(error) {
            console.error('HolestPay initialization or UI loading failed:', error);
        });
    },
    
    setupHPayIntegration: function() {
        var self = this;
        
        // HPay.loadHPayUI() has provided menu UI and other functions
        // Now we can use HPay methods for payment processing
        
        // Set up payment method dock if enabled
        if (this.dock_payment_methods && typeof HPay !== 'undefined' && HPay.setPaymentMethodDock) {
            this.setupPaymentMethodDock();
        }
        
        // Set up shipping method integration
        if (this.POS && this.POS.shipping) {
            this.setupShippingIntegration();
        }
        
        console.log('HolestPay integration setup complete');
    },
    
    setupPaymentMethodDock: function() {
        // Implementation for payment method dock
        console.log('Setting up payment method dock');
    },
    
    setupShippingIntegration: function() {
        // Implementation for shipping integration
        console.log('Setting up shipping integration');
    },
    
    initializePaymentMethods: function() {
        var self = this;
        var container = document.getElementById('holestpay-payment-methods');
        
        if (!container || !this.config.payment_methods) {
            return;
        }
        
        var methodsHtml = '';
        this.config.payment_methods.forEach(function(method, index) {
            var checked = index === 0 ? 'checked' : '';
            methodsHtml += `
                <div class="radio">
                    <label>
                        <input type="radio" name="holestpay_payment_method" value="${method.hpay_id}" ${checked} 
                               data-supports-mit="${method.supports_mit}" data-supports-cof="${method.supports_cof}">
                        <img src="${method.icon || ''}" alt="${method.method_name}" style="height: 20px; margin-right: 10px;">
                        ${method.method_name}
                    </label>
                </div>
            `;
        });
        
        container.innerHTML = methodsHtml;
        
        // Add event listeners for method selection
        var methodRadios = container.querySelectorAll('input[name="holestpay_payment_method"]');
        methodRadios.forEach(function(radio) {
            radio.addEventListener('change', self.onPaymentMethodChange.bind(self));
        });
        
        // Trigger initial method change
        if (methodRadios.length > 0) {
            this.onPaymentMethodChange({ target: methodRadios[0] });
        }
    },
    
    onPaymentMethodChange: function(event) {
        var selectedMethod = event.target;
        var supportsMit = selectedMethod.getAttribute('data-supports-mit') === '1';
        var supportsCof = selectedMethod.getAttribute('data-supports-cof') === '1';
        
        // Show/hide vault token options (only for logged-in users)
        this.toggleVaultTokenOptions(supportsCof && this.customer_id);
        
        // Show/hide subscription options
        this.toggleSubscriptionOptions(supportsMit || supportsCof);
        
        // Update available vault tokens for this method
        this.updateVaultTokensForMethod(selectedMethod.value);
        
        // Update UI based on selected method
        this.updatePaymentMethodUI(selectedMethod.value);
    },
    
    initializeVaultTokens: function() {
        var self = this;
        var container = document.getElementById('holestpay-vault-tokens');
        
        if (!container || !this.config.vault_tokens || this.config.vault_tokens.length === 0) {
            return;
        }
        
        var tokensHtml = '<h4>Saved Payment Methods</h4>';
        this.config.vault_tokens.forEach(function(token) {
            tokensHtml += `
                <div class="radio">
                    <label>
                        <input type="radio" name="holestpay_vault_token" value="${token.vault_token_uid}">
                        <i class="fa fa-credit-card"></i> ${token.vault_card_mask}
                    </label>
                </div>
            `;
        });
        
        tokensHtml += `
            <div class="radio">
                <label>
                    <input type="radio" name="holestpay_vault_token" value="" checked>
                    <i class="fa fa-plus"></i> Use new payment method
                </label>
            </div>
        `;
        
        container.innerHTML = tokensHtml;
        
        // Add event listeners
        var tokenRadios = container.querySelectorAll('input[name="holestpay_vault_token"]');
        tokenRadios.forEach(function(radio) {
            radio.addEventListener('change', self.onVaultTokenChange.bind(self));
        });
    },
    
    onVaultTokenChange: function(event) {
        var selectedToken = event.target.value;
        var newPaymentSection = document.getElementById('holestpay-new-payment');
        
        if (selectedToken) {
            // Using saved payment method
            if (newPaymentSection) {
                newPaymentSection.style.display = 'none';
            }
            this.showInstallmentOptions(selectedToken);
        } else {
            // Using new payment method
            if (newPaymentSection) {
                newPaymentSection.style.display = 'block';
            }
            this.hideInstallmentOptions();
        }
    },
    
    toggleVaultTokenOptions: function(show) {
        var container = document.getElementById('holestpay-vault-tokens');
        if (container) {
            container.style.display = show ? 'block' : 'none';
        }
    },
    
    toggleSubscriptionOptions: function(show) {
        var container = document.getElementById('holestpay-subscription-options');
        if (container) {
            container.style.display = show ? 'block' : 'none';
        }
    },
    
    initializeShippingMethods: function() {
        // Initialize shipping method integration (like Magento sample)
        var self = this;
        
        // Set up shipping method selection monitoring
        this.setupShippingMethodMonitoring();
        
        // Set up checkout address input adaptation
        this.setupCheckoutAddressInput();
    },
    
    // Like Magento window._hpay_selected_shipping_method
    setupShippingMethodMonitoring: function() {
        var self = this;
        
        // Define the shipping method selection handler
        window._hpay_selected_shipping_method = function() {
            var candidate = document.querySelector("input[name^='shipping_method'][value^='holestpay_']:checked");
            
            if (candidate && self.POS && self.POS.shipping) {
                var methodId = String(candidate.value).replace(/^holestpay_/, '');
                var m = self.POS.shipping.find(function(s) {
                    return String(s.HPaySiteMethodId) === methodId;
                });
                
                if (m) {
                    // Add shipping method options span if not already added (like Magento)
                    if (!candidate.getAttribute("sm_options_added")) {
                        candidate.setAttribute("sm_options_added", "1");
                        
                        var sm_opt = document.createElement("span");
                        sm_opt.setAttribute("class", "hpay_sm_options");
                        sm_opt.setAttribute("hpay_site_shipping_method", candidate.value);
                        sm_opt.setAttribute("hpay_shipping_method_id", m.HPaySiteMethodId);
                        
                        // Find the shipping method description area
                        var label = candidate.closest('label') || candidate.parentNode;
                        var methodContainer = label.closest('div') || label.parentNode;
                        methodContainer.appendChild(sm_opt);
                    }
                    
                    // Set selected shipping method (like Magento)
                    window.hpay_selected_shipping_method = parseInt(methodId);
                    self.cart.shipping_method = window.hpay_selected_shipping_method;
                    
                    // Store in session storage
                    try {
                        sessionStorage.hpay_selected_shipping_method = window.hpay_selected_shipping_method;
                    } catch (e) {
                        // SessionStorage not available
                    }
                    
                    // Trigger address input adaptation
                    if (window.doAdaptCheckout) {
                        window.doAdaptCheckout();
                    } else {
                        self.setupCheckoutAddressInput();
                    }
                    return;
                }
            }
            
            // Clear selection if no HolestPay shipping method selected
            window.hpay_selected_shipping_method = '';
            if (self.cart && self.cart.shipping_method) {
                delete self.cart.shipping_method;
            }
            try {
                sessionStorage.hpay_selected_shipping_method = '';
            } catch (e) {
                // SessionStorage not available
            }
        };
        
        // Set up shipping method change monitoring (like Magento)
        var doshook = function() {
            window._hpay_selected_shipping_method();
            
            // Hook shipping method changes
            document.querySelectorAll("div[id*='shipping'], .shipping-method, [class*='shipping']").forEach(function(spanel) {
                if (spanel.getAttribute('data-hpay-shipping-method-hooked') == '1') {
                    return;
                }
                spanel.setAttribute('data-hpay-shipping-method-hooked', '1');
                spanel.addEventListener('change', function(e) {
                    setTimeout(function() {
                        window._hpay_selected_shipping_method();
                    }, 100);
                });
            });
        };
        
        // Initialize and set up periodic monitoring (like Magento)
        window._hpay_selected_shipping_method();
        
        // Run hooks with delays to catch dynamic content
        [500, 1000, 1500, 2500, 5500, 10500].forEach(function(delay) {
            setTimeout(doshook, delay);
        });
    },
    
    // Like Magento window.setup_checkout_address_input
    setupCheckoutAddressInput: function(is_script_loaded) {
        var self = this;
        
        if (!is_script_loaded && window.setup_checkout_address_input_done) return;
        window.setup_checkout_address_input_done = true;
        
        if (typeof HPayInit !== 'undefined') {
            HPayInit().then(function(client) {
                client.loadHPayUI().then(function(ui_loaded) {
                    // Like Magento window.doAdaptCheckout
                    window.doAdaptCheckout = function() {
                        if (self.cart.shipping_method) {
                            // Prevent duplicate adaptation
                            if (self.prev_hpay_shipping_method && 
                                self.prev_hpay_shipping_method.HPaySiteMethodId == window.hpay_selected_shipping_method) {
                                return;
                            }
                            
                            // Find the shipping method in POS data
                            var smethod = self.POS.shipping.find(function(s) {
                                return s.HPaySiteMethodId == self.cart.shipping_method;
                            });
                            
                            if (smethod && smethod.AdaptCheckout) {
                                try {
                                    // Clean up previous adaptation
                                    if (self.adapted_checkout_destroy) {
                                        self.adapted_checkout_destroy();
                                        self.adapted_checkout_destroy = null;
                                    }
                                    
                                    // Apply new adaptation (like Magento sample)
                                    self.adapted_checkout_destroy = smethod.AdaptCheckout({
                                        billing: {
                                            postcode: "input[name='postcode'], input[name='billing_postcode']",
                                            phone: "input[name='telephone'], input[name='billing_telephone']", 
                                            country: "select[name='country_id'], select[name='billing_country_id']",
                                            city: "input[name='city'], input[name='billing_city']",
                                            address: "input[name='address_1'], input[name='billing_address_1']",
                                            address_num: "input[name='address_2'], input[name='billing_address_2']"
                                        },
                                        shipping: {
                                            postcode: "input[name='shipping_postcode']",
                                            phone: "input[name='shipping_telephone']",
                                            country: "select[name='shipping_country_id']", 
                                            city: "input[name='shipping_city']",
                                            address: "input[name='shipping_address_1']",
                                            address_num: "input[name='shipping_address_2']"
                                        }
                                    }) || null;
                                    
                                    console.log('HolestPay: AdaptCheckout applied for shipping method', smethod.HPaySiteMethodId);
                                } catch (ex) {
                                    console.log('HolestPay: AdaptCheckout error:', ex);
                                }
                            }
                            
                            self.prev_hpay_shipping_method = smethod;
                        } else {
                            // Clean up if no HolestPay shipping method selected
                            if (self.adapted_checkout_destroy) {
                                self.adapted_checkout_destroy();
                                self.adapted_checkout_destroy = null;
                            }
                            self.prev_hpay_shipping_method = null;
                        }
                    };
                    
                    // Initial adaptation
                    window.doAdaptCheckout();
                });
            });
        } else {
            // Load hpay.js script and retry (like Magento sample)
            var scriptUrl = 'https://' + (this.config.environment == 'sandbox' ? 'sandbox.' : '') + 'pay.holest.com/clientpay/cscripts/hpay.js';
            var script = document.createElement('script');
            script.src = scriptUrl;
            script.async = true;
            script.onload = function() {
                self.setupCheckoutAddressInput(true);
            };
            document.head.appendChild(script);
        }
    },
    
    setupFormSubmission: function() {
        var self = this;
        var form = document.getElementById('holestpay-payment-form');
        
        if (form) {
            form.addEventListener('submit', this.handleFormSubmission.bind(this));
        }
        
        // Also handle OpenCart's checkout button
        var checkoutButton = document.getElementById('button-confirm');
        if (checkoutButton) {
            checkoutButton.addEventListener('click', this.handleCheckoutConfirm.bind(this));
        }
    },
    
    handleFormSubmission: function(event) {
        event.preventDefault();
        this.processPayment();
    },
    
    handleCheckoutConfirm: function(event) {
        // Check if HolestPay is the selected payment method
        var selectedPayment = document.querySelector('input[name="payment_method"]:checked');
        
        if (selectedPayment && selectedPayment.value === 'holestpay') {
            event.preventDefault();
            this.processPayment();
        }
    },
    
    processPayment: function() {
        var self = this;
        
        // Disable form
        this.disableForm(true);
        
        // Show loading
        this.showLoading('Processing payment...');
        
        // Collect form data
        var formData = this.collectFormData();
        
        // Validate form data
        if (!this.validateFormData(formData)) {
            this.disableForm(false);
            this.hideLoading();
            return;
        }
        
        // Send confirmation request
        this.sendConfirmationRequest(formData);
    },
    
    collectFormData: function() {
        var selectedMethod = document.querySelector('input[name="holestpay_payment_method"]:checked');
        var selectedToken = document.querySelector('input[name="holestpay_vault_token"]:checked');
        var saveCard = document.querySelector('input[name="holestpay_save_card"]');
        var installments = document.querySelector('select[name="holestpay_installments"]');
        
        return {
            payment_method_id: selectedMethod ? selectedMethod.value : '',
            vault_token_uid: selectedToken ? selectedToken.value : '',
            cof: saveCard && saveCard.checked ? 'required' : 'none',
            installments: installments ? installments.value : '',
            cart: this.cart
        };
    },
    
    validateFormData: function(data) {
        if (!data.payment_method_id) {
            this.showError('Please select a payment method');
            return false;
        }
        
        return true;
    },
    
    sendConfirmationRequest: function(formData) {
        var self = this;
        
        fetch(this.config.urls.confirm, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                self.presentHPayPayForm(data.hpay_request);
            } else {
                self.showError(data.error || 'Payment processing failed');
                self.disableForm(false);
                self.hideLoading();
            }
        })
        .catch(error => {
            console.error('Payment error:', error);
            self.showError('Payment processing failed');
            self.disableForm(false);
            self.hideLoading();
        });
    },
    
    presentHPayPayForm: function(hpayRequest) {
        // This function presents the HolestPay payment form
        // Implementation depends on HolestPay's JavaScript SDK
        
        if (typeof presentHPayPayForm !== 'undefined') {
            try {
                // Call the main HolestPay payment form function (from hpay.js)
                // This is available after HPay.loadHPayUI() has been called
                presentHPayPayForm(hpayRequest);
                
                // Payment callbacks are handled through HolestPay's system
                // The payment result will come through webhooks and redirects
                console.log('HolestPay payment form presented successfully');
                
            } catch (error) {
                console.error('Error calling presentHPayPayForm:', error);
                this.onPaymentError(error);
            }
        } else if (!this.hpayClient) {
            console.error('HolestPay not initialized - hpay.js may not be loaded');
            this.showError('Payment system not initialized. Please refresh the page.');
        } else {
            console.error('presentHPayPayForm function not available - HPay.loadHPayUI() may not have completed');
            // Fallback: redirect to HolestPay
            this.redirectToHolestPay(hpayRequest);
        }
    },
    
    redirectToHolestPay: function(hpayRequest) {
        // Create a form and submit to HolestPay
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = this.getHolestPayUrl();
        
        for (var key in hpayRequest) {
            if (hpayRequest.hasOwnProperty(key)) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = typeof hpayRequest[key] === 'object' ? JSON.stringify(hpayRequest[key]) : hpayRequest[key];
                form.appendChild(input);
            }
        }
        
        document.body.appendChild(form);
        form.submit();
    },
    
    getHolestPayUrl: function() {
        return this.config.environment === 'sandbox' 
            ? 'https://sandbox.pay.holest.com/pay' 
            : 'https://pay.holest.com/pay';
    },
    
    onPaymentSuccess: function(result) {
        this.showSuccess('Payment completed successfully!');
        
        // Redirect to success page
        setTimeout(function() {
            window.location.href = '/index.php?route=checkout/success';
        }, 2000);
    },
    
    onPaymentError: function(error) {
        this.showError('Payment failed: ' + (error.message || 'Unknown error'));
        this.disableForm(false);
        this.hideLoading();
    },
    
    onPaymentCancel: function() {
        this.showError('Payment was cancelled');
        this.disableForm(false);
        this.hideLoading();
    },
    
    startCartMonitoring: function() {
        var self = this;
        
        // Update cart data every 30 seconds
        setInterval(function() {
            self.updateCartData();
        }, 30000);
    },
    
    updateCartData: function() {
        var self = this;
        
        // This would typically make an AJAX call to get updated cart data
        // For now, we'll just update the timestamp
        this.cart.updated = new Date().toISOString();
        
        console.log('Cart data updated', this.cart);
    },
    
    updatePaymentMethodUI: function(methodId) {
        // Update UI elements based on selected payment method
        var methodConfig = this.config.payment_methods.find(function(method) {
            return method.hpay_id === methodId;
        });
        
        if (methodConfig) {
            // Update description
            var descElement = document.getElementById('holestpay-method-description');
            if (descElement) {
                descElement.textContent = methodConfig.description || '';
            }
            
            // Update icon
            var iconElement = document.getElementById('holestpay-method-icon');
            if (iconElement && methodConfig.icon) {
                iconElement.src = methodConfig.icon;
                iconElement.style.display = 'inline';
            }
        }
    },
    
    showInstallmentOptions: function(tokenUid) {
        var container = document.getElementById('holestpay-installment-options');
        if (container) {
            container.style.display = 'block';
            // Load installment options for this token
            this.loadInstallmentOptions(tokenUid, container);
        }
    },
    
    hideInstallmentOptions: function() {
        var container = document.getElementById('holestpay-installment-options');
        if (container) {
            container.style.display = 'none';
        }
    },
    
    loadInstallmentOptions: function(tokenUid, container) {
        // Generate installment options (2-12 months)
        var optionsHtml = '<label>Installments:</label><select name="holestpay_installments" class="form-control">';
        optionsHtml += '<option value="">Pay in full</option>';
        
        for (var i = 2; i <= 12; i++) {
            optionsHtml += `<option value="${i}">${i} monthly payments</option>`;
        }
        
        optionsHtml += '</select>';
        container.innerHTML = optionsHtml;
    },
    
    disableForm: function(disabled) {
        var form = document.getElementById('holestpay-payment-form');
        if (form) {
            var inputs = form.querySelectorAll('input, select, button');
            inputs.forEach(function(input) {
                input.disabled = disabled;
            });
        }
    },
    
    showLoading: function(message) {
        var loadingDiv = document.getElementById('holestpay-loading');
        if (!loadingDiv) {
            loadingDiv = document.createElement('div');
            loadingDiv.id = 'holestpay-loading';
            loadingDiv.className = 'alert alert-info text-center';
            document.getElementById('holestpay-payment-form').appendChild(loadingDiv);
        }
        
        loadingDiv.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + message;
        loadingDiv.style.display = 'block';
    },
    
    hideLoading: function() {
        var loadingDiv = document.getElementById('holestpay-loading');
        if (loadingDiv) {
            loadingDiv.style.display = 'none';
        }
    },
    
    showError: function(message) {
        this.showMessage(message, 'danger');
    },
    
    showSuccess: function(message) {
        this.showMessage(message, 'success');
    },
    
    showMessage: function(message, type) {
        var messageDiv = document.getElementById('holestpay-messages');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'holestpay-messages';
            var form = document.getElementById('holestpay-payment-form');
            form.insertBefore(messageDiv, form.firstChild);
        }
        
        messageDiv.className = 'alert alert-' + type;
        messageDiv.innerHTML = '<i class="fa fa-' + (type === 'success' ? 'check' : 'exclamation-triangle') + '"></i> ' + message;
        messageDiv.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            messageDiv.style.display = 'none';
        }, 5000);
    },
    
    // VAULT TOKEN MANAGEMENT (like WooCommerce sample)
    updateVaultTokensForMethod: function(methodId) {
        // Filter vault tokens for the selected payment method
        if (!this.vault_tokens || !methodId) {
            return;
        }
        
        var filteredTokens = this.vault_tokens.filter(function(token) {
            return token.payment_method_id === methodId;
        });
        
        var container = document.getElementById('holestpay-vault-tokens');
        if (container) {
            this.renderFilteredVaultTokens(container, filteredTokens);
        }
    },
    
    renderFilteredVaultTokens: function(container, tokens) {
        if (!tokens || tokens.length === 0) {
            container.innerHTML = '<p class="text-muted">No saved payment methods for this option.</p>';
            return;
        }
        
        var html = '<div class="vault-tokens-list">';
        var self = this;
        
        tokens.forEach(function(token, index) {
            var isDefault = token.is_default == '1';
            var checked = isDefault ? 'checked' : '';
            var defaultBadge = isDefault ? '<span class="label label-primary">Default</span>' : '';
            
            html += '<div class="radio vault-token-item" data-token-id="' + token.vault_token_id + '">' +
                    '<label>' +
                    '<input type="radio" name="holestpay_vault_token" value="' + token.vault_token_uid + '" ' + checked + '>' +
                    '<i class="fa fa-credit-card"></i> ' + token.vault_card_mask + ' ' + defaultBadge +
                    '<div class="token-actions" style="margin-left: 20px; display: inline-block;">' +
                    '<button type="button" class="btn btn-xs btn-default" onclick="HolestPayCheckout.setTokenDefault(\'' + token.vault_token_id + '\')" title="Set as default">' +
                    '<i class="fa fa-star' + (isDefault ? '' : '-o') + '"></i>' +
                    '</button>' +
                    '<button type="button" class="btn btn-xs btn-danger" onclick="HolestPayCheckout.removeToken(\'' + token.vault_token_id + '\')" title="Remove">' +
                    '<i class="fa fa-trash"></i>' +
                    '</button>' +
                    '</div>' +
                    '</label>' +
                    '</div>';
        });
        
        html += '</div>';
        container.innerHTML = html;
    },
    
    setTokenDefault: function(tokenId) {
        var self = this;
        
        fetch(this.ajax_url + '&action=setVaultTokenDefault', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                vault_token_id: tokenId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                self.showNotification('Default payment method updated', 'success');
                // Refresh vault tokens display
                location.reload();
            } else {
                self.showNotification('Failed to update default payment method: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error setting default token:', error);
            self.showNotification('Error updating payment method', 'error');
        });
    },
    
    removeToken: function(tokenId) {
        if (!confirm('Are you sure you want to remove this payment method?')) {
            return;
        }
        
        var self = this;
        
        fetch(this.ajax_url + '&action=removeVaultToken', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                vault_token_id: tokenId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                self.showNotification('Payment method removed', 'success');
                // Remove from DOM
                var tokenElement = document.querySelector('[data-token-id="' + tokenId + '"]');
                if (tokenElement) {
                    tokenElement.remove();
                }
            } else {
                self.showNotification('Failed to remove payment method: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error removing token:', error);
            self.showNotification('Error removing payment method', 'error');
        });
    },
    
    showNotification: function(message, type) {
        // Simple notification system
        var notification = document.createElement('div');
        notification.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger') + ' alert-dismissible';
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.zIndex = '9999';
        notification.style.maxWidth = '400px';
        
        notification.innerHTML = '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                                '<strong>HolestPay:</strong> ' + message;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);
    }
};

// Auto-initialize if configuration is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof holestpayCheckoutConfig !== 'undefined') {
        HolestPayCheckout.init(holestpayCheckoutConfig);
    }
});
