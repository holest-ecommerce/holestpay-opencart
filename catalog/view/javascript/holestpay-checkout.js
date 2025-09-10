/**
 * HPayFrontOC JavaScript Object
 * Handles frontend checkout functionality for HolestPay in OpenCart
 */

if(!window.HPayFrontOC){
	window.HPayFrontOC = {
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
			if(!options && typeof HolestPayCheckout !== 'undefined'){
				options = HolestPayCheckout;
			}
			// Merge options into this object
			if (options) {
				Object.assign(this, options);
			}
			
			if(this.cart && this.cart.needs_reload){
				let self = this;					
				setTimeout(()=>{
					try{
						if(self.cart.needs_reload != localStorage.shp_refresh){
							localStorage.shp_refresh = this.cart.needs_reload;
							window.location.reload();
							return;
						}
					}catch(ex){

					}
				},250);
			}

			if(this.POS)
			   this.initialized = true;
			
			// Initialize tracking variables (like Magento sample)
			this.adapted_checkout_destroy = null;
			this.prev_hpay_shipping_method = null;
			
			// Initialize checkout interface
			this.initializeCheckout();
			
			console.log('HPayFrontOC initialized', this);
		},
		
		initializeCheckout: function() {
			var self = this;

			if(!(document.querySelector('input[value="holestpay"], select option[value="holestpay"],input[name="payment_method"],select[name="payment_method"],input[name="shipping_method"],select[name="shipping_method"]'))){
				return;
			}

			self.startCartMonitoring();
			
			if(typeof HPay !== 'undefined' && HPay){
				setTimeout(()=>{
					self.initializeHPay();
				},50); 
			}else{
				setTimeout(()=>{
					self.loadHPayScript();
				},50); 
			}
		},
		
		loadHPayScript: function() {
			var self = this;
			
			// Check if HolestPay script is already loaded
			// CRITICAL: It is totally safe to call HPayInit or HPay.loadHPayUI() multiple times
			if (typeof HPayInit !== 'undefined') {
				if(this.__initializeHPay)
					return;
				this.__initializeHPay = true;
				this.initializeHPay();
				return;
			}
			if(this.__loadHPayScript){
				return;
			}
			this.__loadHPayScript = true;
			
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
			
			
			document.addEventListener("onHPayPanelClose", function(e){
				setTimeout(function(){
					//e.hpay_response.reason == "user" - click to close
					
					self.showError(HolestPayCheckout.labels['Payment failed']);
					self.disableForm(false);
					self.hideLoading();
				
					if(e && e.hpay_response && e.hpay_response.reason == "user"){
						//user clicked to close
					}else{
						//something else
					}	
				},150);
			})

			document.addEventListener("onHPayResult", function(e){
				  last_resp = e.hpay_response || null;
				  if(last_resp){

					  if(last_resp.error && last_resp.error.code){
						  
						  self.showError(HolestPayCheckout.labels['Payment failed']);
						  self.disableForm(false);
						  self.hideLoading();

					  }else{
						  
						  if(/PAID|RESERVED|SUCCESS|PAYING|OBLIGATED/i.test(last_resp.status)){
							//TO REDIRECT TO THANKYOU
							//I need to user post to the e.hpay_response.order_user_url , with the hpay_forwarded_payment_response parameter set to json serialized e.hpay_response
							//costrict form and 

							
							let form = document.createElement('form');
							form.method = 'POST';
							form.action = e.hpay_response.order_user_url;
							let inp = document.createElement('input');
							inp.name = 'hpay_forwarded_payment_response';
							inp.value = JSON.stringify(e.hpay_response);
							form.appendChild(inp);

							inp = document.createElement('input');
							inp.name = 'hpay_clear_cart';
							inp.value = 1;
							form.appendChild(inp);

							document.body.appendChild(form);
							form.submit();

						  }else{
							self.showError(HolestPayCheckout.labels['Payment failed']);
							self.disableForm(false);
							self.hideLoading();
							if(e.hpay_response.transaction_user_info){

								let content = '';
								for(var key in e.hpay_response.transaction_user_info){
									if(e.hpay_response.transaction_user_info.hasOwnProperty(key)){
										let translated_key = HolestPayCheckout.labels[key] || key;
										content += (translated_key + ': ' + e.hpay_response.transaction_user_info[key] + '<br/>');
									}
								}

								hpay_dialog_open("payreq-error", HolestPayCheckout.labels['Payment failed'], content);
							}
						  }
						}	  

				  }else{
						self.showError(HolestPayCheckout.labels['Payment failed']);
						self.disableForm(false);
						self.hideLoading();
				  }
			});
			
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
				self.POS = HPay.POS;
				self.initializePaymentMethods();
				// Initialize vault token management
				self.initializeVaultTokens();
				// Initialize shipping method integration
				self.initializeShippingMethods();
				// Set up form submission
				self.setupFormSubmission();
				// Update cart data periodically
				
				return client.loadHPayUI();
			}).then(function(loaded) {
				console.log('HolestPay UI loaded successfully');
				
				// Now HPay object should be available with UI functions
				if (typeof HPay !== 'undefined') {
					// Set POS configuration reference for easy access
					
					
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
		
		setSelectedHPayPaymentMethod: function(hpay_id, form_select){
			let self = this;
			
			document.querySelectorAll(".hpay-method-selection[hpay_id]").forEach(m_cnt => {
				if(!hpay_id){
					m_cnt.style.display = '';
				}else{
					if(m_cnt.getAttribute("hpay_id") == hpay_id && hpay_id){
						m_cnt.style.display = '';
						self.onPaymentMethodChange();
					}else{
						m_cnt.style.display = 'none';
					}
				}
			});
			
			document.querySelectorAll('input[name="holestpay_payment_method"]').forEach(m_inp => {
				if(m_inp.getAttribute("value") == hpay_id){
					if(!m_inp.checked){
						m_inp.checked = true;
					}
				}else{
					if(m_inp.checked){
						m_inp.checked = false;
					}
				}
			});
			
			if(form_select){
				document.querySelectorAll(".hpay-method-selection[hpay_id] .hpay-method-label").forEach(el => {
					el.style.display = 'none';
				});
			}
		},
		initializePaymentMethods: function() {
			var self = this;
			var container = document.getElementById('holestpay-payment-methods');
			
			if (!this.POS || !this.POS.payment) {
				return;
			}
			
			try{
				if(sessionStorage.selected_hpay_pm && !window.selected_hpay_pm){
					window.selected_hpay_pm = sessionStorage.selected_hpay_pm;
				}
			}catch(ex){
				//
			}
			
			var methodsHtml = '';
			var methods_shown = {};
			this.POS.payment.forEach(function(method, index) {
				method.supports_mit = /mit/i.test(method.SubsciptionsType);
				method.supports_cof = /cof/i.test(method.SubsciptionsType);
					
				if(method.Enabled && !method.Hidden){
					methods_shown[method.HPaySiteMethodId] = method.Name;
					
					let dock = '';
					
					if(typeof HPay !== 'undefined' && HPay && HPay.POS && HPay.POS.pos_parameters && HPay.POS.pos_parameters["Docked Input"] && method.PayInputUrl){
						//suports dock
						dock = "<div class='hpay_method_dock' style='padding: 10px;' hpay_method_dock='" + method.HPaySiteMethodId + "'></div>";
					}
					
					var checked = index === 0 ? 'checked' : '';
					methodsHtml += `
						<div class="radio hpay-method-selection" hpay_id="${method.HPaySiteMethodId}">
							<label class='hpay-method-label' >
								<input type="radio" name="holestpay_payment_method" value="${method.HPaySiteMethodId}" ${checked} 
									   data-supports-mit="${method.supports_mit}" data-supports-cof="${method.supports_cof}">
								${method.Name}
							</label>
							<div class='method-description hpay-method-description'>
								${method.Description}
							</div>
							${dock}
						</div>
					`;
				}
			});
			
			if(container)
				container.style.display = '';
			
			//IF POSSIBLE DISPLAY EACH METHOD AS SEPARATE METHOD. WE DONT NEED THIS IF THERE IS ONLY ONE PAYMENT METHOD
			let oc_pm_sel = document.querySelector("select[name='payment_method']:has(option[value='holestpay'])");
			if(oc_pm_sel && !oc_pm_sel.getAttribute("hpay_norm")){
				oc_pm_sel.setAttribute("hpay_norm",1);
				if(Object.keys(methods_shown).length > 1){
					let last_opt = null;
					for(var hpay_id in methods_shown){
						if(methods_shown.hasOwnProperty(hpay_id)){
							if(!last_opt){
								last_opt = oc_pm_sel.querySelector("option[value='holestpay']");
								if(!last_opt)
									break;
								last_opt.innerHTML = methods_shown[hpay_id];
								last_opt.setAttribute("hpay_id",hpay_id); 
								
								if(oc_pm_sel.value == "holestpay" && !window.selected_hpay_pm){
									window.selected_hpay_pm = hpay_id;
									try{ sessionStorage.selected_hpay_pm = hpay_id;} catch(ex){}
								}
								
							}else{
								let newopt = document.createElement('option');
								newopt.setAttribute('value', 'holestpay');
								newopt.setAttribute("hpay_id",hpay_id); 
								newopt.innerHTML = methods_shown[hpay_id];
								oc_pm_sel.insertBefore(newopt, last_opt.nextSibling);
								last_opt = newopt;
							}
						}
					}
					
					if(window.selected_hpay_pm && oc_pm_sel.value == 'holestpay'){
						let opt = oc_pm_sel.querySelector("option[hpay_id='" + window.selected_hpay_pm + "']");
						if(opt && !opt.selected){
							opt.selected = true;
						}
					}
					
					oc_pm_sel.setAttribute("prev_value",oc_pm_sel.value);
					oc_pm_sel.addEventListener("change", function(e){
						
						if(oc_pm_sel.value == oc_pm_sel.getAttribute("prev_value") && oc_pm_sel.value == 'holestpay' && oc_pm_sel.getAttribute("prev_value") == 'holestpay'){
							try{
								e.preventDefault();
								e.stopImmediatePropagation()
							}catch(cex){
								//
							}
						}
						oc_pm_sel.setAttribute("prev_value",oc_pm_sel.value);
						if(oc_pm_sel.value != 'holestpay'){
							return true;
						}
						
						setTimeout(()=>{
							let hpay_id = oc_pm_sel.selectedOptions[0].getAttribute("hpay_id");
							if(hpay_id){
								window.selected_hpay_pm = hpay_id;
								try{ sessionStorage.selected_hpay_pm = hpay_id;} catch(ex){}
								self.setSelectedHPayPaymentMethod(window.selected_hpay_pm || null, true);
							}
						},30);
					}, true);
				}else{
					let h_opt = oc_pm_sel.querySelector("option[name='holestpay']");
					if(h_opt){
						h_opt.setAttribute("hpay_id", Object.keys(methods_shown)[0]);
					}
				}
			}
			
			if(container){
				container.innerHTML = methodsHtml;
				self.setSelectedHPayPaymentMethod(window.selected_hpay_pm || null, !!oc_pm_sel);
				
				// Add event listeners for method selection
				var methodRadios = container.querySelectorAll('input[name="holestpay_payment_method"]');
				methodRadios.forEach(function(radio) {
					radio.addEventListener('change', self.onPaymentMethodChange.bind(self));
				});
			}
		},
		
		onPaymentMethodChange: function(event) {
			if(event && event.target && event.target.value){
				window.selected_hpay_pm = event.target.value;
			}
			
			var selectedMethod = document.querySelector("input[name='holestpay_payment_method'][value='" + window.selected_hpay_pm + "']");
			var supportsMit = selectedMethod.getAttribute('data-supports-mit') === '1';
			var supportsCof = selectedMethod.getAttribute('data-supports-cof') === '1';
			
			// Show/hide vault token options (only for logged-in users)
			this.toggleVaultTokenOptions(supportsCof && this.customer_id);
			
			// Show/hide subscription options
			this.toggleSubscriptionOptions(supportsMit || supportsCof);
			
			// Update available vault tokens for this method
			this.updateVaultTokensForMethod(selectedMethod.value);
			
			// Update UI based on selected method
			this.updatePaymentMethodUI(window.selected_hpay_pm);
			
			//hpay_method_dock
			if(selected_hpay_pm && typeof HPay !== 'undefined' && HPay && HPay.POS && HPay.POS.pos_parameters && HPay.POS.pos_parameters["Docked Input"]){
				let dock_cnt = document.querySelector('div[hpay_method_dock="' + selected_hpay_pm + '"]');
				if(dock_cnt){
					HPay.setPaymentMethodDock(selected_hpay_pm, {
							order_amount: HolestPayCheckout.cart.order_amount,//may be element, selector or actual value. Selector may contain {$pmid} replace makro 
							order_currency: HolestPayCheckout.cart.order_currency,//may be element, selector or actual value. Selector may contain {$pmid} replace makro 
							monthly_installments: null,//may be element, selector or actual value. Selector may contain {$pmid} replace makro 
							vault_token_uid: null,//may be element, selector or actual value. Selector may contain {$pmid} replace makro,
							hpaylang: HolestPayCheckout.hpaylang,
							cof: 'optional' 	
						},dock_cnt 
					);
				}
			}
		},
		
		initializeVaultTokens: function() {
			var self = this;
			var container = document.getElementById('holestpay-vault-tokens');
			
			if (!container || !this.cart || !this.cart.vault_tokens || this.cart.vault_tokens.length === 0) {
				return;
			}
			
			var tokensHtml = '<h4>Saved Payment Methods</h4>';
			this.cart.vault_tokens.forEach(function(token) {
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
				// Clear selection if no HolestPay shipping method selected
				window.hpay_selected_shipping_method = HolestPayCheckout.cart.shipping_method || "";
				try {
					sessionStorage.hpay_selected_shipping_method = HolestPayCheckout.cart.shipping_method || "";
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
				setTimeout(function(){
					self.setupCheckoutAddressInput(false);	
				},1000);
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
				shipping_method_id: (this.cart || {}).shipping_method || "", 
				vault_token_uid: selectedToken ? selectedToken.value : '',
				cof: 'optional',
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
			
			fetch(this.get_request_url, {
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
					self.showError(data.error || HolestPayCheckout.labels['Payment failed']);
					self.disableForm(false);
					self.hideLoading();
				}
			})
			.catch(error => {
				console.error('Payment error:', error);
				self.showError(HolestPayCheckout.labels['Payment failed']);
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
					
					if(hpayRequest.order_user_url){
						let site_url = hpayRequest.order_user_url.split("?")[0];
						let qstring = new URLSearchParams(hpayRequest.order_user_url.split("?")[1] || "").toString();
						hpayRequest.order_user_url = site_url + "?" + qstring;
					}
					
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
			return this.environment === 'sandbox' 
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
			let self = this;
			
			
			document.querySelectorAll("input[name='payment_method'],select[name='payment_method'],input[name='shipping_method'],select[name='shipping_method']").forEach(el => {
				if(el.getAttribute('data-hpay-cart-monitoring-hooked') == '1')
					return;
				el.setAttribute('data-hpay-cart-monitoring-hooked', '1');
				el.addEventListener('change', function() {
					setTimeout(() => self.updateCheckoutData(),500);
				});
			});
			document.querySelectorAll("#button-register").forEach(el => {
				if(el.getAttribute('data-hpay-cart-monitoring-hooked') == '1')
					return;
				el.setAttribute('data-hpay-cart-monitoring-hooked', '1');
				el.addEventListener('click', function() {
					setTimeout(() => self.updateCheckoutData(),500);
				});
			});
			
		},
		updateCheckoutData: function() {
			let self = this;
			fetch(this.ajax_url + '&action=refresh_checkout', {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json'
				}
			}).then(response => response.json()).then(data => {
				HolestPayCheckout = data;
				Object.assign(this, HolestPayCheckout);
				if(this.cart && this.cart.needs_reload){
					if(this.cart.needs_reload != localStorage.shp_refresh){
						localStorage.shp_refresh = this.cart.needs_reload;
						window.location.reload();
						return;
					}
				}

				let pmsel = document.querySelector("select[name='payment_method']:has(option[value='holestpay'])");
				if(!pmsel.getAttribute("hpay_norm")){
					self.initializePaymentMethods();
				}

			});
		},
		updatePaymentMethodUI: function(methodId) {
			// Update UI elements based on selected payment method
			var methodConfig = this.POS.payment.find(function(method) {
				return method.HPaySiteMethodId === methodId;
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
			
			if(tokens.length > 0){
				tokens = [{vault_token_id: null, vault_token_uid: '', vault_card_mask: HolestPayCheckout.labels.UseOther},...tokens];
			}
			
			tokens.forEach(function(token, index) {
				var isDefault = token.is_default == '1';
				var checked = isDefault ? 'checked' : '';
				var defaultBadge = isDefault ? ('<span class="label label-primary">' + HolestPayCheckout.labels.Default + '</span>') : '';
				
				html += '<div class="radio vault-token-item" data-token-id="' + token.vault_token_id + '">' +
						'<label>' +
						'<input type="radio" name="holestpay_vault_token" value="' + token.vault_token_uid + '" ' + checked + '>' +
						'<i class="fa fa-credit-card"></i> ' + token.vault_card_mask + ' ' + defaultBadge +
						'<div class="token-actions" style="margin-left: 20px; display: inline-block;">' +
						'<button type="button" class="btn btn-xs btn-default" onclick="HPayFrontOC.setTokenDefault(\'' + token.vault_token_id + '\')" title="' + HolestPayCheckout.labels.SetDefault + '">' +
						'<i class="fa fa-star' + (isDefault ? '' : '-o') + '"></i>' +
						'</button>' +
						'<button type="button" class="btn btn-xs btn-danger" onclick="HPayFrontOC.removeToken(\'' + token.vault_token_id + '\')" title="' + HolestPayCheckout.labels.Remove + '">' +
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
	setTimeout(function() {
		if (typeof HolestPayCheckout !== 'undefined') {
			HPayFrontOC.init(HolestPayCheckout);
		}
	},100);
}else{
	if(document.querySelector('input[value="holestpay"], select option[value="holestpay"]'))
		window.HPayFrontOC.init();
}

if(typeof HolestPayCheckout !== 'undefined' && HolestPayCheckout && HolestPayCheckout.logotypes_setup){
	let card_images_html = '';
	let banks_html = '';
	let threes_html = '';

	if(HolestPayCheckout.logotypes_setup['Logotypes Card Images']){
		let card_images = HolestPayCheckout.logotypes_setup['Logotypes Card Images'].split("\n");
		for(let i = 0; i < card_images.length; i++){
			card_images_html += '<img style="height:22px;" src="' + card_images[i] + '" alt="Card" />';
		}
	}
	
	if(HolestPayCheckout.logotypes_setup['Logotypes Banks']){
		let banks = HolestPayCheckout.logotypes_setup['Logotypes Banks'].split("\n");
		for(let i = 0; i < banks.length; i++){
			let t = banks[i].replace(/https:/gi,'-PS-').replace(/http:/gi,'-P-').split(":").map(r=>r.replace("-P-","http:").replace("-PS-","https:"));
			if(t.length > 1){
				banks_html += '<a href="' + t[1] + '" target="_blank"><img style="height:22px;" src="' + t[0] + '" alt="Bank" /></a>';
			}else{
				banks_html += '<img style="height:22px;" src="' + t[0] + '" alt="Bank" />';
			}
		}
	}

	if(HolestPayCheckout.logotypes_setup['Logotypes 3DS']){
		let threes = HolestPayCheckout.logotypes_setup['Logotypes 3DS'].split("\n");
		for(let i = 0; i < threes.length; i++){
			let t = threes[i].replace(/https:/gi,'-PS-').replace(/http:/gi,'-P-').split(":").map(r=>r.replace("-P-","http:").replace("-PS-","https:"));
			if(t.length > 1){
				threes_html += '<a href="' + t[1] + '" target="_blank"><img style="height:22px;" src="' + t[0] + '" alt="3DS" /></a>';
			}else{
				threes_html += '<img style="height:22px;" src="' + t[0] + '" alt="3DS" />';
			}
		}
	}		
	
	let logotypes_footer_html = '<div class="hpay-footer-branding-cards">' + card_images_html + '</div><div style="padding: 0 30px;" class="hpay-footer-branding-bank">' + banks_html + '</div><div class="hpay-footer-branding-3ds">' + threes_html + '</div>';
	let logotypes_div = document.createElement("div");
	logotypes_div.style.display = 'flex';
	logotypes_div.style.justifyContent = 'center';
	logotypes_div.style.padding = '4px 0';
	logotypes_div.style.background = '#ededed';
	logotypes_div.className = 'hpay_footer_branding';
	logotypes_div.innerHTML = logotypes_footer_html;
	
	setTimeout(function(){
		
		(document.querySelector('footer') || document.querySelector('body')).appendChild(logotypes_div); 
		
	},150);
	
}