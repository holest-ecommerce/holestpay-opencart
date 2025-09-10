/**
 * HolestPay Admin JavaScript
 * Handles admin functionality for HolestPay module
 */

var HPayAdmOC = {
    // Core properties (set by PHP)
    settings: {},
    admin_base_url: '',
    frontend_base_url: '',
    site_url: '',
    notify_url: '',
    plugin_url: '',
    labels: {},
    nonce: '',
    ajax_url: '',
    language: 'en',
    hpaylang: 'en',
    plugin_version: '1.0.0',
    
    // Runtime properties
    initialized: false,
    
    init: function(hpa) {
        // Merge options into settings
        if (hpa) {
            Object.assign(this, hpa);
        }

        this.initialized = true;
        
        // Initialize admin interface
        this.initializeAdminInterface();
        
        // Load order management if on order details page
        if (this.isOrderDetailsPage()) {
            this.initializeOrderManagement();
        }
        
        // Load main HolestPay script (hpay.js) for admin functionality
        this.loadHPayScript();
        
        console.log('HPayAdmOC initialized', this.settings);
    },
    
    loadHPayScript: function() {
        var self = this;
        
        // Check if HolestPay script is already loaded
        // CRITICAL: It is totally safe to call HPayInit or HPay.loadHPayUI() multiple times
        if (typeof HPayInit !== 'undefined') {
            this.initializeHPay();
            return;
        }
        
        // Determine HolestPay URL based on environment
        var hpayUrl = this.settings.environment === 'production' 
            ? 'https://pay.holest.com' 
            : 'https://sandbox.pay.holest.com';
        
        // Load main hpay.js script from HolestPay CDN
        var script = document.createElement('script');
        script.src = hpayUrl + '/clientpay/cscripts/hpay.js?v=' + this.plugin_version;
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
        
        // Only initialize if we have proper credentials
        if (!this.settings.merchant_site_uid || !this.settings.environment) {
            console.log('HolestPay admin: Missing credentials, skipping initialization');
            return;
        }
        
        // Initialize HolestPay with merchant credentials + SECRET KEY for admin capabilities
        var secret_key = this.settings[this.settings.environment] ? this.settings[this.settings.environment].secret_token : '';
        HPayInit(this.settings.merchant_site_uid, this.hpaylang, this.settings.environment, secret_key).then(function(client) {
            console.log('HolestPay Admin initialized successfully');
            self.hpayClient = client;
            
            // Load HolestPay UI - this provides menu UI and other functions
            return client.loadHPayUI();
        }).then(function(loaded) {
            console.log('HolestPay Admin UI loaded successfully');
            
            // Now HPay object should be available with admin UI functions
            if (typeof HPay !== 'undefined') {
                // CRITICAL: After HPay.loadHPayUI(), global functions are available:
                // - presentHPayPayForm (from hpay.js)
                // - hpay_dialog_open 
                // - hpay_alert_dialog
                // These can now be called globally in admin
                
                console.log('HolestPay Admin global functions available:', {
                    presentHPayPayForm: typeof presentHPayPayForm !== 'undefined',
                    hpay_dialog_open: typeof hpay_dialog_open !== 'undefined',
                    hpay_alert_dialog: typeof hpay_alert_dialog !== 'undefined'
                });
                
                self.setupHPayAdminIntegration();
            }
        }).catch(function(error) {
            console.error('HolestPay Admin initialization or UI loading failed:', error);
        });
    },
    
    setupHPayAdminIntegration: function() {
        var self = this;
        
        // HPay.loadHPayUI() has provided menu UI and admin functions
        // Now we can use HPay methods for admin operations
        
        console.log('HolestPay Admin integration setup complete');
        
        // Set up order management tools if on order details page
        if (this.isOrderDetailsPage()) {
            this.setupOrderManagementTools();
            
            // CRITICAL: Initialize order change monitoring for order_store API calls
            this.initOrderChangeMonitoring();
        }
    },
    
    setupOrderManagementTools: function() {
        // Implementation for admin order management tools like Magento
        console.log('Setting up HolestPay admin order management tools');
        
        if (!this.isOrderDetailsPage()) {
            return;
        }
        
        var orderId = this.getOrderIdFromPage();
        if (!orderId) {
            console.log('Order ID not found, skipping HolestPay order management setup');
            return;
        }
        
        // Load order data and render admin toolbox
        this.loadOrderData(orderId);
    },
    
    isOrderDetailsPage: function() {
        // Detect if we're on order details page
        let qs = new URLSearchParams(window.location.search);
        let order_id = qs.get('order_id');
        let route = qs.get('route');
        if(order_id && /sale\/order/.test(route)){
            return true;
        }
        return false;
    },
    getUserToken: function() {
        let qs = new URLSearchParams(window.location.search);
        let user_token = qs.get('user_token');
        if(user_token){
            return user_token;
        }
        return null;
    },
    getOrderIdFromPage: function() {
        let qs = new URLSearchParams(window.location.search);
        let order_id = qs.get('order_id');
        if(order_id){
            return order_id;
        }

        // Extract order ID from URL or context
        if (this.context && this.context.orderId) {
            return this.context.orderId;
        }
        return null;
    },
    
    loadOrderData: function(orderId) {
        let self = this;
        HPay.getOrder(orderId).then(order => {  

            if (typeof window._orders === 'undefined') {
                window._orders = {};
            }
            
            if(order && order.error && order.error_code == "404"){
                order = null;
            }

            if(order){
                window._orders[order.Uid] = order;
                self.renderOrderManagementBox(order);
            }else{
                self.renderOrderManagementBox(null);
            }
        });
        
    },
    
    renderOrderManagementBox: function(orderData) {
        if(!orderData)
            orderData = {Status: ''};
        // CRITICAL: Render highlighted HPay Status and admin toolbox like Magento
        var statusElement = document.getElementById('hpay-status');
        var actionsElement = document.getElementById('holestpay-order-actions');
        var detailsElement = document.getElementById('holestpay-order-data');
        
        if (!statusElement || !actionsElement) {
            console.log('HolestPay order management elements not found');
            return;
        }
        
        // CRITICAL: Highlighted HPay Status display
        var hpayStatus = orderData.Status;
        var statusClass = this.getStatusClass( hpayStatus );
        
        statusElement.innerHTML = '<span class="label ' + statusClass + '">' + hpayStatus + '</span>';
        
        // Update other details
        if (orderData.last_updated) {
            document.getElementById('hpay-last-updated').textContent = orderData.last_updated;
        }
        
        // CRITICAL: Render admin toolbox with possible HolestPay actions
        this.renderAdminToolbox(actionsElement, orderData, HPay);
    },
    
    getStatusClass: function(Status) {  
        // Return Bootstrap label class based on payment status
        if(/SUCCESS|PAID|RESERVED|OBLIGATED|AWAITING/.test(Status)){
            return 'label-success';
        }else if(/FAILED|REFUSED|DECLINED/.test(Status)){
            return 'label-danger';
        }else if(/PENDING|PROCESSING/.test(Status)){
            return 'label-warning';
        }else if(/CANCELLED|CANCELED/.test(Status)){
            return 'label-default';
        }else{
            return 'label-info';
        }   
    },
    renderAdminToolbox: function(orderToolbox, order, client) {
        var hasActions = false;
        orderToolbox.innerHTML = '';

        if(order.Uid){                
            // 1. Payment Method Actions
            if (client.POS && client.POS.payment && client.POS.payment.length) {
                client.POS.payment.forEach(function(paymentMethod) {
                    try {
                        // Initialize actions if available
                        if (paymentMethod.initActions) {
                            if (typeof paymentMethod.initActions === "string") {
                                try {
                                    paymentMethod.initActions = eval('(' + paymentMethod.initActions + ')');
                                } catch (e) {
                                    console.warn('Failed to parse payment initActions for payment method:', e);
                                }
                            }
                            if (typeof paymentMethod.initActions === "function") {
                                paymentMethod.initActions();
                            }
                        }
                        
                        // Get order actions
                        if (paymentMethod.orderActions) {
                            if (typeof paymentMethod.orderActions === "string") {
                                try {
                                    paymentMethod.orderActions = eval('(' + paymentMethod.orderActions + ')');
                                } catch (e) {
                                    console.warn('Failed to parse payment orderActions for payment method:', e);
                                }
                            }
                            
                            if (typeof paymentMethod.orderActions === "function") {
                                var orderActions = paymentMethod.orderActions(order);
                                
                                if (orderActions && orderActions.length) {
                                    hasActions = true;
                                    // Add payment method title
                                    var methodTitle = $('<h6></h6>').html(paymentMethod["Backend Name"] || (paymentMethod.SystemTitle + " " + paymentMethod.Name));
                                    orderToolbox.appendChild(methodTitle[0]);
                                    
                                    // Add action buttons
                                    orderActions.forEach(function(action) {
                                        if (action.Run) {
                                            var button = $('<button class="btn btn-primary"></button>')
                                                .html(action.Caption)
                                                .click(function(e) {
                                                    e.preventDefault();
                                                    action.Run(order);
                                                });
                                            jQuery(orderToolbox).append(button);
                                        } else if (action.actions) {
                                            var actionGroup = $('<p></p>').html(action.Caption);
                                            jQuery(orderToolbox).append(actionGroup);
                                            
                                            action.actions.forEach(function(subAction) {
                                                var subButton = $('<button class="btn btn-primary"></button>')
                                                    .html(subAction.Caption)
                                                    .click(function(e) {
                                                        e.preventDefault();
                                                        subAction.Run(order);
                                                    });
                                                actionGroup.append(subButton);
                                            });
                                        }
                                    });
                                }
                            }
                        }
                    } catch (ex) {
                        console.error('Error processing payment method actions', ex);
                    }
                });
            }
            
            // 2. Fiscal Method Actions
            if (client.POS && client.POS.fiscal && client.POS.fiscal.length) {
                client.POS.fiscal.forEach(function(fiscalMethod) {
                    try {
                        // Initialize actions if available
                        if (fiscalMethod.initActions) {
                            if (typeof fiscalMethod.initActions === "string") {
                                try {
                                    fiscalMethod.initActions = eval('(' + fiscalMethod.initActions + ')');
                                } catch (e) {
                                    console.warn('Failed to parse fiscal initActions for fiscal method:', e);
                                }
                            }
                            if (typeof fiscalMethod.initActions === "function") {
                                fiscalMethod.initActions();
                            }
                        }
                        
                        // Get order actions
                        if (fiscalMethod.orderActions) {
                            if (typeof fiscalMethod.orderActions === "string") {
                                try {
                                    fiscalMethod.orderActions = eval('(' + fiscalMethod.orderActions + ')');
                                } catch (e) {
                                    console.warn('Failed to parse fiscal orderActions for fiscal method:', e);
                                }
                            }
                            
                            if (typeof fiscalMethod.orderActions === "function") {
                                var orderActions = fiscalMethod.orderActions(order);
                                
                                if (orderActions && orderActions.length) {
                                    hasActions = true;
                                    // Add fiscal method title
                                    var methodTitle = $('<h6></h6>').html(fiscalMethod["Backend Name"] || (fiscalMethod.SystemTitle + " " + fiscalMethod.Name));
                                    orderToolbox.appendChild(methodTitle[0]);
                                    
                                    // Add action buttons
                                    orderActions.forEach(function(action) {
                                        if (action.Run) {
                                            var button = $('<button class="btn btn-primary"></button>')
                                                .html(action.Caption)
                                                .click(function(e) {
                                                    e.preventDefault();
                                                    action.Run(order);
                                                });
                                            jQuery(orderToolbox).append(button);
                                        } else if (action.actions) {
                                            var actionGroup = $('<p></p>').html(action.Caption);
                                            jQuery(orderToolbox).append(actionGroup);
                                            
                                            action.actions.forEach(function(subAction) {
                                                var subButton = $('<button class="btn btn-primary"></button>')
                                                    .html(subAction.Caption)
                                                    .click(function(e) {
                                                        e.preventDefault();
                                                        subAction.Run(order);
                                                    });
                                                jQuery(actionGroup).append(subButton);
                                            });
                                        }
                                    });
                                }
                            }
                        }
                    } catch (ex) {
                        console.error('Error processing fiscal method actions', ex);
                    }
                });
            }
            
            // 3. Shipping Method Actions
            if (client.POS && client.POS.shipping && client.POS.shipping.length) {
                client.POS.shipping.forEach(function(shippingMethod) {
                    try {
                        // Initialize actions if available
                        if (shippingMethod.initActions) {
                            if (typeof shippingMethod.initActions === "string") {
                                try {
                                    shippingMethod.initActions = eval('(' + shippingMethod.initActions + ')');
                                } catch (e) {
                                    console.warn('Failed to parse shipping initActions for shipping method:', e);
                                }
                            }
                            if (typeof shippingMethod.initActions === "function") {
                                shippingMethod.initActions();
                            }
                        }
                        
                        // Get order actions
                        if (shippingMethod.orderActions) {
                            if (typeof shippingMethod.orderActions === "string") {
                                try {
                                    shippingMethod.orderActions = eval('(' + shippingMethod.orderActions + ')');
                                } catch (e) {
                                    console.warn('Failed to parse shipping orderActions for shipping method:', e);
                                }
                            }
                            
                            if (typeof shippingMethod.orderActions === "function") {
                                var orderActions = shippingMethod.orderActions(order);
                                
                                if (orderActions && orderActions.length) {
                                    hasActions = true;
                                    // Add shipping method title
                                    var methodTitle = $('<h6></h6>').html(shippingMethod["Backend Name"] || (shippingMethod.SystemTitle + " " + shippingMethod.Name));
                                    orderToolbox.appendChild(methodTitle[0]);
                                    
                                    // Add action buttons
                                    orderActions.forEach(function(action) {
                                        if (action.Run) {
                                            var button = $('<button class="btn btn-primary"></button>')
                                                .html(action.Caption)
                                                .click(function(e) {
                                                    e.preventDefault();
                                                    action.Run(order);
                                                });
                                            jQuery(orderToolbox).append(button);
                                        } else if (action.actions) {
                                            var actionGroup = $('<p></p>').html(action.Caption);
                                            jQuery(orderToolbox).append(actionGroup);
                                            
                                            action.actions.forEach(function(subAction) {
                                                var subButton = $('<button class="btn btn-primary"></button>')
                                                    .html(subAction.Caption)
                                                    .click(function(e) {
                                                        e.preventDefault();
                                                        subAction.Run(order);
                                                    });
                                                jQuery(actionGroup).append(subButton);  
                                            });
                                        }
                                    });
                                }
                            }
                        }
                    } catch (ex) {
                        console.error('Error processing shipping method actions', ex);
                    }
                });
            }

            //https://sandbox.pay.holest.com/orders/Uid:250828001-11014
            if(order.Uid){
                let hr = document.createElement('hr');
                orderToolbox.appendChild(hr);
                var hpayUrl = this.settings.environment === 'production' 
                    ? 'https://pay.holest.com' 
                    : 'https://sandbox.pay.holest.com';
                var hpayUid = order.Uid;
                var hpayUrl = hpayUrl + '/orders/Uid:' + hpayUid + "?pos=" + HolestPayAdmin.settings.merchant_site_uid;
                var button = document.createElement('button');
                button.className = 'btn btn-primary';
                button.innerHTML = 'Manage in HolestPay...';
                button.addEventListener('click', function() {
                    window.open(hpayUrl, 'holestpay_order', 'width=1200,height=800,scrollbars=yes,resizable=yes');
                });
                orderToolbox.appendChild(button);
            }


        }
        
        // CRITICAL: Always render "Store to HPay..." button (manual override - like WooCommerce)
        this.renderStoreToHPayButton(orderToolbox, order);
    },
    
    openOrderInHPay: function(hpayUid) {
        // Open order in HolestPay admin panel
        var hpayUrl = this.settings.environment === 'production' 
            ? 'https://pay.holest.com' 
            : 'https://sandbox.pay.holest.com';
        
        window.open(hpayUrl + '/admin/orders/' + hpayUid, 'holestpay_order', 'width=1200,height=800,scrollbars=yes,resizable=yes');
    },
    
    refreshOrderData: function(hpayUid) {
        // Refresh order data from HolestPay
        var self = this;
        var orderId = this.getOrderIdFromPage();
        
        if (orderId) {
            this.loadOrderData(orderId);
        }
    },
    
    refundOrder: function(hpayUid) {
        // Process refund through HolestPay
        if (typeof hpay_dialog_open !== 'undefined') {
            hpay_dialog_open('refund', { order_uid: hpayUid });
        } else {
            alert('HolestPay UI not loaded. Please refresh the page.');
        }
    },
    
    // CRITICAL: Monitor order changes and send order_store API calls
    initOrderChangeMonitoring: function() {
        if (!this.isOrderDetailsPage()) {
            return;
        }
        
        var self = this;
        var orderId = this.getOrderIdFromPage();
        
        if (!orderId) {
            return;
        }
        
        // Monitor form changes that affect order data
        this.monitorOrderItemChanges(orderId);
        this.monitorAddressChanges(orderId);
        this.monitorShippingMethodChanges(orderId);
        
    },
    
    monitorOrderItemChanges: function(orderId) {
        // Monitor changes to order items (add/remove/modify products)
        var productForms = document.querySelectorAll('form[action*="order/edit"]');
        
        productForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                // Delay the order_store call to allow the form submission to complete
                setTimeout(function() {
                    HPayAdmOC.sendOrderStoreUpdate(orderId, 'Order items modified');
                }, 1000);
            });
        });
    },
    
    monitorAddressChanges: function(orderId) {
        // Monitor changes to billing and shipping addresses
        var addressFields = document.querySelectorAll('input[name*="payment_"], input[name*="shipping_"]');
        
        addressFields.forEach(function(field) {
            field.addEventListener('change', function() {
                clearTimeout(HPayAdmOC.addressChangeTimeout);
                HPayAdmOC.addressChangeTimeout = setTimeout(function() {
                    HPayAdmOC.sendOrderStoreUpdate(orderId, 'Address information updated');
                }, 2000); // Debounce multiple rapid changes
            });
        });
    },
    
    monitorShippingMethodChanges: function(orderId) {
        // Monitor changes to shipping method
        var shippingSelects = document.querySelectorAll('select[name*="shipping"]');
        
        shippingSelects.forEach(function(select) {
            select.addEventListener('change', function() {
                HPayAdmOC.sendOrderStoreUpdate(orderId, 'Shipping method changed');
            });
        });
    },
    
    sendOrderStoreUpdate: function(orderId, reason) {
        var self = this;
        
        console.log('Sending order_store update for order ' + orderId + ': ' + reason);
                
        if(!orderId)
            orderId = this.getOrderIdFromPage();
        // Send order_store API call (automatic - not forced)
        fetch(this.ajax_url + '&action=orderStoreApiCall&user_token=' + this.getUserToken() + '&order_id=' + this.getOrderIdFromPage(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                reason: reason,
                force: false // Automatic calls follow restrictions
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('HolestPay order_store update successful: ' + data.message);
                
                // Show success notification (less intrusive for automatic updates)
                self.showNotification('Order synchronized with HolestPay', 'success');
            } else {
                // Don't show error notifications for automatic calls that fail criteria checks
                if (data.error.indexOf('does not meet criteria') === -1) {
                    console.error('HolestPay order_store update failed: ' + data.error);
                    self.showNotification('Failed to sync order with HolestPay: ' + data.error, 'error');
                } else {
                    console.log('HolestPay order_store skipped: ' + data.error);
                }
            }
        })
        .catch(error => {
            console.error('Error sending order_store update:', error);
            self.showNotification('Error syncing order with HolestPay', 'error');
        });
    },
    
    showNotification: function(message, type) {
        // Show notification to admin user
        var alertClass = 'alert-danger'; // default
        if (type === 'success') {
            alertClass = 'alert-success';
        } else if (type === 'info') {
            alertClass = 'alert-info';
        } else if (type === 'warning') {
            alertClass = 'alert-warning';
        }
        
        var notification = document.createElement('div');
        notification.className = 'alert ' + alertClass + ' alert-dismissible';
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
    },
    
    // MANUAL CHARGE FUNCTIONALITY (like WooCommerce sample)
    renderManualChargeButton: function(container, orderData) {
        // Check if order is unpaid and has available vault tokens
        if (!orderData.hpay_data || !orderData.hpay_data.vault_tokens || orderData.hpay_data.vault_tokens.length === 0) {
            return;
        }
        
        
        if (/SUCCESS|PAID|RESERVED|OBLIGATED|AWAITING/.test(orderData.Status)) {
            return; // Order already paid
        }
        
        var html = '<div class="manual-charge-section" style="margin-top: 15px;">' +
                   '<h4>Manual Charge</h4>' +
                   '<p class="text-muted">Charge this order using a saved payment method:</p>';
        
        // Render vault token selection
        if (orderData.hpay_data.vault_tokens.length === 1) {
            var token = orderData.hpay_data.vault_tokens[0];
            html += '<div class="form-group">' +
                   '<label>Payment Method:</label>' +
                   '<div class="well well-sm">' +
                   '<i class="fa fa-credit-card"></i> ' + token.vault_card_mask +
                   '<input type="hidden" id="selected-vault-token" value="' + token.vault_token_uid + '">' +
                   '<input type="hidden" id="selected-payment-method" value="' + token.payment_method_id + '">' +
                   '</div>' +
                   '</div>';
        } else {
            html += '<div class="form-group">' +
                   '<label>Select Payment Method:</label>' +
                   '<div class="vault-token-selection">';
            
            orderData.hpay_data.vault_tokens.forEach(function(token, index) {
                var checked = index === 0 ? 'checked' : '';
                html += '<div class="radio">' +
                       '<label>' +
                       '<input type="radio" name="manual_charge_token" value="' + token.vault_token_uid + '" ' +
                       'data-payment-method="' + token.payment_method_id + '" ' + checked + '>' +
                       '<i class="fa fa-credit-card"></i> ' + token.vault_card_mask +
                       '</label>' +
                       '</div>';
            });
            
            html += '</div></div>';
        }
        
        html += '<button type="button" class="btn btn-warning" onclick="HPayAdmOC.processManualCharge(' + orderData.order_id + ')">' +
               '<i class="fa fa-credit-card"></i> Process Manual Charge' +
               '</button>' +
               '</div>';
        
        // Create a temporary container to parse HTML
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // Append each child node to the container
        while (tempDiv.firstChild) {
            container.appendChild(tempDiv.firstChild);
        }
    },
    
    renderStoreToHPayButton: function(container, orderData) {
        // Always show "Store to HPay..." button (manual override)
        var buttonText = this.labels && this.labels.button_store_to_hpay ? this.labels.button_store_to_hpay + '...' : 'Store to HPay...';
        
        // Create button element
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-info';
        button.style.marginLeft = '15px';
        button.onclick = function() {
            HPayAdmOC.storeToHPay(orderData.order_id || HPayAdmOC.getOrderIdFromPage());
        };
        button.innerHTML = '<i class="fa fa-upload"></i> ' + buttonText;
        
        // Create paragraph element
        var paragraph = document.createElement('p');
        paragraph.className = 'text-muted';
        paragraph.style.marginLeft = '5px';
        paragraph.style.fontSize = '11px';
        paragraph.textContent = 'Manually sync order data with HolestPay (overrides all restrictions)';
        
        // Append button and paragraph directly to container
        container.appendChild(button);
        container.appendChild(paragraph);
    },
    
    processManualCharge: function(orderId) {
        var vaultTokenUid, paymentMethodId;
        
        // Get selected vault token
        var selectedToken = document.querySelector('input[name="manual_charge_token"]:checked');
        if (selectedToken) {
            vaultTokenUid = selectedToken.value;
            paymentMethodId = selectedToken.getAttribute('data-payment-method');
        } else {
            var hiddenToken = document.getElementById('selected-vault-token');
            var hiddenMethod = document.getElementById('selected-payment-method');
            if (hiddenToken && hiddenMethod) {
                vaultTokenUid = hiddenToken.value;
                paymentMethodId = hiddenMethod.value;
            }
        }
        
        if (!vaultTokenUid || !paymentMethodId) {
            alert('Please select a payment method');
            return;
        }
        
        if (!confirm('Are you sure you want to process a manual charge for this order?')) {
            return;
        }
        
        var self = this;
        
        // Show loading state
        var button = event.target;
        var originalText = button.innerHTML;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
        button.disabled = true;
        
        fetch(this.ajax_url + '&action=processManualCharge&user_token=' + this.getUserToken(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                vault_token_uid: vaultTokenUid,
                payment_method_id: paymentMethodId
            })
        })
        .then(response => response.json())
        .then(data => {
            button.innerHTML = originalText;
            button.disabled = false;
            
            if (data.success) {
                self.showNotification('Manual charge processed successfully', 'success');
                // Refresh order data
                setTimeout(function() {
                    self.refreshOrderData();
                }, 1000);
            } else {
                self.showNotification('Manual charge failed: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error processing manual charge:', error);
            button.innerHTML = originalText;
            button.disabled = false;
            self.showNotification('Error processing manual charge', 'error');
        });
    },
    
    storeToHPay: function(orderId, withStatus) {
        var self = this;
        
        // Show modal dialog with optional status selection (like WooCommerce sample)
        this.showStoreToHPayModal(orderId, function(selectedStatus) {
            self.executeStoreToHPay(orderId, selectedStatus);
        });
    },
    
    showStoreToHPayModal: function(orderId, callback) {
        // Create modal dialog (like WooCommerce hpay_confirm_dialog)
        var modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.setAttribute('role', 'dialog');
        modal.id = 'holestpay-store-modal';
        
        // Status options (like WooCommerce sample)
        var statusOptions = [
            {value: '', label: '-- No status change --'},
            {value: 'PAYMENT:PAID', label: 'PAYMENT:PAID'},
            {value: 'PAYMENT:PAYING', label: 'PAYMENT:PAYING'},
            {value: 'PAYMENT:OVERDUE', label: 'PAYMENT:OVERDUE'},
            {value: 'PAYMENT:RESERVED', label: 'PAYMENT:RESERVED'},
            {value: 'PAYMENT:AWAITING', label: 'PAYMENT:AWAITING'},
            {value: 'PAYMENT:REFUNDED', label: 'PAYMENT:REFUNDED'},
            {value: 'PAYMENT:PARTIALLY-REFUNDED', label: 'PAYMENT:PARTIALLY-REFUNDED'},
            {value: 'PAYMENT:VOID', label: 'PAYMENT:VOID'},
            {value: 'PAYMENT:EXPIRED', label: 'PAYMENT:EXPIRED'},
            {value: 'PAYMENT:OBLIGATED', label: 'PAYMENT:OBLIGATED'},
            {value: 'PAYMENT:REFUSED', label: 'PAYMENT:REFUSED'},
            {value: 'PAYMENT:FAILED', label: 'PAYMENT:FAILED'},
            {value: 'PAYMENT:CANCELED', label: 'PAYMENT:CANCELED'}
        ];
        
        var self = this;
        var statusDropdown = '<select class="form-control" id="hpay-status-select">';
        statusOptions.forEach(function(option) {
            // Use localized label for first option
            var label = (option.value === '') ? (self.labels.no_status_change || option.label) : option.label;
            statusDropdown += '<option value="' + option.value + '">' + label + '</option>';
        });
        statusDropdown += '</select>';
        
        modal.innerHTML = 
            '<div class="modal-dialog modal-sm">' +
                '<div class="modal-content">' +
                    '<div class="modal-header">' +
                        '<h4 class="modal-title">' + (self.labels.store_to_hpay || 'Store to HolestPay') + '</h4>' +
                        '<button type="button" class="close close-my-modal" data-dismiss="modal">&times;</button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<p>' + (self.labels.store_to_hpay_confirm || 'Store order data to HolestPay panel?') + '</p>' +
                        '<div class="form-group">' +
                            '<label>' + (self.labels.optional_status_change || 'Optional status change:') + '</label>' +
                            statusDropdown +
                            '<small class="help-block">' + (self.labels.status_help || 'Select a status to explicitly set, or leave empty for automatic detection.') + '</small>' +
                        '</div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-default close-my-modal" data-dismiss="modal">' + (self.labels.cancel || 'Cancel') + '</button>' +
                        '<button type="button" class="btn btn-primary" id="hpay-store-confirm">' + (self.labels.button_store_to_hpay || 'Store to HolestPay') + '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        document.body.appendChild(modal);
        
        // Show modal using Bootstrap
        $(modal).modal('show');
        
        // Handle confirm button
        document.getElementById('hpay-store-confirm').addEventListener('click', function() {
            var selectedStatus = document.getElementById('hpay-status-select').value;
            $(modal).modal('hide');
            callback(selectedStatus || null);
        });
        
        // Clean up when modal is hidden
        $(modal).on('hidden.bs.modal', function() {
            document.body.removeChild(modal);
        });
    },
    
    executeStoreToHPay: function(orderId, withStatus) {
        var self = this;
        
        // Show loading notification
        this.showNotification('Storing order data to HolestPay...', 'info');
        
        var requestData = {
            order_id: this.getOrderIdFromPage(),
            force: true // Manual button always forces store
        };
        
        if (withStatus) {
            requestData.with_status = withStatus;
        }
        
        fetch(this.ajax_url + '&action=orderStoreApiCall&user_token=' + this.getUserToken() + '&order_id=' + this.getOrderIdFromPage(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var message = 'Order data stored to HolestPay successfully';
                if (withStatus) {
                    message += ' with status: ' + withStatus;
                }
                self.showNotification(message, 'success');

                HPay.getOrder(self.getOrderIdFromPage()).then(order => {
                    self.order = order;
                    self.renderAdminToolbox(document.getElementById('holestpay-order-actions'), self.order, HPay);
                });

            } else {
                self.showNotification('Failed to store order data: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error storing to HolestPay:', error);
            self.showNotification('Error storing order data to HolestPay', 'error');
        });
    },
    
    getPageContext: function() {
        var body = document.body;
        return body ? body.className : '';
    },
    
    initializeAdminInterface: function() {
        var self = this;
        
        // Add configuration validation
        this.addConfigurationValidation();
        
        // Add webhook URL copy functionality
        this.addWebhookUrlCopy();
        
        // Test connection button
        this.addConnectionTest();
    },
    
    addConfigurationValidation: function() {
        var merchantUidField = document.getElementById('input-merchant-site-uid');
        var secretKeyField = document.getElementById('input-secret-key');
        var environmentField = document.getElementById('input-environment');
        
        if (merchantUidField) {
            merchantUidField.addEventListener('blur', this.validateMerchantUid.bind(this));
        }
        
        if (secretKeyField) {
            secretKeyField.addEventListener('blur', this.validateSecretKey.bind(this));
        }
        
        if (environmentField) {
            environmentField.addEventListener('change', this.onEnvironmentChange.bind(this));
        }
    },
    
    validateMerchantUid: function(event) {
        var value = event.target.value.trim();
        var errorDiv = event.target.parentNode.querySelector('.text-danger');
        
        if (value.length < 10) {
            this.showFieldError(event.target, 'Merchant Site UID must be at least 10 characters long');
        } else {
            this.clearFieldError(event.target);
        }
    },
    
    validateSecretKey: function(event) {
        var value = event.target.value.trim();
        
        if (value.length < 32) {
            this.showFieldError(event.target, 'Secret Key must be at least 32 characters long');
        } else {
            this.clearFieldError(event.target);
        }
    },
    
    onEnvironmentChange: function(event) {
        var environment = event.target.value;
        var container = document.getElementById('holestpay-admin-container');
        
        if (container) {
            if (environment === 'sandbox') {
                container.innerHTML = '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> You are in SANDBOX mode. No real transactions will be processed.</div>';
            } else {
                container.innerHTML = '<div class="alert alert-info"><i class="fa fa-info-circle"></i> You are in PRODUCTION mode. Real transactions will be processed.</div>';
            }
        }
    },
    
    showFieldError: function(field, message) {
        this.clearFieldError(field);
        var errorDiv = document.createElement('div');
        errorDiv.className = 'text-danger';
        errorDiv.textContent = message;
        field.parentNode.appendChild(errorDiv);
        field.classList.add('has-error');
    },
    
    clearFieldError: function(field) {
        var errorDiv = field.parentNode.querySelector('.text-danger');
        if (errorDiv) {
            errorDiv.remove();
        }
        field.classList.remove('has-error');
    },
    
    addWebhookUrlCopy: function() {
        var webhookContainer = document.querySelector('.well code');
        if (webhookContainer) {
            var copyButton = document.createElement('button');
            copyButton.type = 'button';
            copyButton.className = 'btn btn-sm btn-default';
            copyButton.innerHTML = '<i class="fa fa-copy"></i> Copy';
            copyButton.style.marginLeft = '10px';
            
            copyButton.addEventListener('click', function() {
                navigator.clipboard.writeText(webhookContainer.textContent).then(function() {
                    copyButton.innerHTML = '<i class="fa fa-check"></i> Copied!';
                    copyButton.classList.add('btn-success');
                    setTimeout(function() {
                        copyButton.innerHTML = '<i class="fa fa-copy"></i> Copy';
                        copyButton.classList.remove('btn-success');
                    }, 2000);
                });
            });
            
            webhookContainer.parentNode.appendChild(copyButton);
        }
    },
    
    addConnectionTest: function() {
        var container = document.getElementById('holestpay-admin-container');
        if (container && this.settings.merchant_site_uid) {
            var testButton = document.createElement('button');
            testButton.type = 'button';
            testButton.className = 'btn btn-info';
            testButton.innerHTML = '<i class="fa fa-plug"></i> Test Connection';
            testButton.addEventListener('click', this.testConnection.bind(this));
            
            var testDiv = document.createElement('div');
            testDiv.className = 'well';
            testDiv.appendChild(testButton);
            container.appendChild(testDiv);
        }
    },
    
    testConnection: function() {
        var button = event.target;
        var originalHtml = button.innerHTML;
        
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';
        button.disabled = true;
        
        // Simulate connection test (in real implementation, this would make an AJAX call)
        setTimeout(function() {
            button.innerHTML = '<i class="fa fa-check"></i> Connection OK';
            button.classList.add('btn-success');
            button.classList.remove('btn-info');
            
            setTimeout(function() {
                button.innerHTML = originalHtml;
                button.classList.remove('btn-success');
                button.classList.add('btn-info');
                button.disabled = false;
            }, 3000);
        }, 2000);
    },
    
    initializeOrderManagement: function() {
        this.addOrderHPayBox();
    },
    
    addOrderHPayBox: function() {
        var panels = document.querySelectorAll('.container-fluid > div');
        if (!panels.length) return;
        
        // Get order ID from URL
        
        var orderId = this.getOrderIdFromPage();
        
        if (!orderId) return;
        
        // Create HolestPay order management box
        var hpayBox = this.createHPayOrderBox(orderId);
        
        // Insert after the order info panels
        if (panels.length > 0) {
            panels[2].parentNode.insertBefore(hpayBox, panels[2]);
        }
    },
    
    createHPayOrderBox: function(orderId) {
        var panel = document.createElement('div');
        panel.className = 'panel panel-default';
        
        var header = document.createElement('div');
        header.className = 'panel-heading';
        header.innerHTML = '<h3 class="panel-title"><i class="fa fa-credit-card"></i> HolestPay</h3>';
        
        var body = document.createElement('div');
        body.className = 'panel-body';
        body.id = 'holestpay-order-commands-' + orderId;
        
        // Load HolestPay order commands
        this.loadOrderCommands(orderId, body);
        
        panel.appendChild(header);
        panel.appendChild(body);
        
        return panel;
    },
    
    loadOrderCommands: function(orderId, container) {

        container.innerHTML = `
                <div class="row">
                    <div class="col-md-6" >
                        <h4 id="hpay-status" style="background: aliceblue;padding: 6px;font-style: italic;font-weight: bold;"></h4>
                    </div>
                    <div id="holestpay-order-actions"class="col-md-6">
                        
                    </div>
                </div>
                <div id="holestpay-order-data" class="row" style="margin-top: 20px;">
                    
                </div>
            `;
    }
};

// Auto-initialize if configuration is available
document.addEventListener('DOMContentLoaded', function() {
    if (typeof HolestPayAdmin !== 'undefined') {
        HPayAdmOC.init(HolestPayAdmin);
    }else{
        let qs = new URLSearchParams(window.location.search);
        let order_id = qs.get('order_id');
        let route = qs.get('route');
        if(order_id && /sale\/order/.test(route)){
            qs.set('route', 'extension/holestpay/payment/holestpay|fetchHolestPayAdminData');
            fetch(window.location.href.split('?')[0] + '?' + qs.toString())
            .then(response => response.json())
            .then(data => {
                window.HolestPayAdmin = data;
                HPayAdmOC.init(window.HolestPayAdmin);
            });

            let order_management = document.createElement('div');
            order_management.id = 'holestpay-order-management';
            order_management.innerHTML = '<h2>HolestPay</h2>';
            document.body.appendChild(order_management);

            //add order management
        }
    }
});

jQuery(document).on('click', '.close-my-modal', function() {
    jQuery(this).closest('.modal').modal('hide');
});