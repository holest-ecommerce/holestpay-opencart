<?php
/**
 * OpenCart 3 Compatible HolestPay Payment Controller (Catalog)
 * This file provides OpenCart 3 compatibility for the HolestPay extension
 */

class ControllerPaymentHolestpay extends Controller {
    
    public function index() {
        $this->load->language('payment/holestpay');
        
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_loading'] = $this->language->get('text_loading');
        
        // Get order information
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        
        if ($order_info) {
            // Generate HolestPay request
            $hpay_request = $this->generateHPayRequest($order_info);
            
            // Add signature
            $secret_key = $this->config->get('payment_holestpay_secret_key');
            $hpay_request['verificationhash'] = $this->generateSignature($hpay_request, $secret_key);
            
            $data['hpay_request'] = $hpay_request;
            $data['hpay_url'] = $this->getHolestPayUrl();
            $data['merchant_site_uid'] = $this->config->get('payment_holestpay_merchant_site_uid');
            $data['environment'] = $this->config->get('payment_holestpay_environment');
        }
        
        return $this->load->view('payment/holestpay', $data);
    }
    
    public function confirm() {
        $this->load->language('payment/holestpay');
        
        $json = array();
        
        if ($this->session->data['payment_method']['code'] == 'holestpay') {
            $this->load->model('checkout/order');
            
            // Update order status to pending
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_holestpay_order_status_id'), $this->language->get('text_comment'), false);
            
            $json['redirect'] = $this->url->link('checkout/success');
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
    
    public function callback() {
        $this->load->language('payment/holestpay');
        $this->load->model('checkout/order');
        
        // Process HolestPay callback
        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
        
        if ($order_id) {
            $order_info = $this->model_checkout_order->getOrder($order_id);
            
            if ($order_info) {
                // Process the payment result
                $this->processPaymentResult($order_id, $order_info);
            }
        }
        
        $this->response->redirect($this->url->link('checkout/success'));
    }
    
    private function generateHPayRequest($order_info) {
        // Generate order items
        $order_items = $this->generateOrderItems($order_info);
        
        $request = array(
            'merchant_site_uid' => $this->config->get('payment_holestpay_merchant_site_uid'),
            'order_uid' => $order_info['order_id'],
            'order_name' => '#' . $order_info['order_id'],
            'order_amount' => $order_info['total'],
            'order_currency' => $order_info['currency_code'],
            'order_items' => $order_items,
            'order_billing' => array(
                'email' => $order_info['email'],
                'first_name' => $order_info['payment_firstname'],
                'last_name' => $order_info['payment_lastname'],
                'phone' => $order_info['telephone'],
                'company' => $order_info['payment_company'],
                'address' => $order_info['payment_address_1'],
                'address2' => $order_info['payment_address_2'],
                'city' => $order_info['payment_city'],
                'country' => $order_info['payment_country'],
                'postcode' => $order_info['payment_postcode'],
                'lang' => $this->config->get('config_language')
            ),
            'order_shipping' => array(
                'first_name' => $order_info['shipping_firstname'],
                'last_name' => $order_info['shipping_lastname'],
                'company' => $order_info['shipping_company'],
                'address' => $order_info['shipping_address_1'],
                'address2' => $order_info['shipping_address_2'],
                'city' => $order_info['shipping_city'],
                'country' => $order_info['shipping_country'],
                'postcode' => $order_info['shipping_postcode']
            )
        );
        
        return $request;
    }
    
    private function generateOrderItems($order_info) {
        $this->load->model('checkout/order');
        
        $order_items = array();
        
        // Get order products
        $order_products = $this->model_checkout_order->getOrderProducts($order_info['order_id']);
        foreach ($order_products as $product) {
            $order_items[] = array(
                'name' => $product['name'],
                'quantity' => (int)$product['quantity'],
                'price' => (float)$product['price'],
                'total' => (float)$product['total']
            );
        }
        
        // Get order totals
        $order_totals = $this->model_checkout_order->getOrderTotals($order_info['order_id']);
        foreach ($order_totals as $total) {
            if (in_array($total['code'], ['subtotal', 'total'])) {
                continue;
            }
            
            $order_items[] = array(
                'name' => $total['title'],
                'quantity' => 1,
                'price' => (float)$total['value'],
                'total' => (float)$total['value']
            );
        }
        
        return $order_items;
    }
    
    private function generateSignature($data, $secret_key) {
        $merchant_site_uid = $this->config->get('payment_holestpay_merchant_site_uid');
        
        $amt_for_signature = number_format((float)(isset($data['order_amount']) ? $data['order_amount'] : 0), 8, '.', '');
        
        $cstr = trim(isset($data['transaction_uid']) ? (string)$data['transaction_uid'] : '') . '|';
        $cstr .= trim(isset($data['status']) ? (string)$data['status'] : '') . '|';
        $cstr .= trim(isset($data['order_uid']) ? (string)$data['order_uid'] : '') . '|';
        $cstr .= trim($amt_for_signature) . '|';
        $cstr .= trim(isset($data['order_currency']) ? (string)$data['order_currency'] : '') . '|';
        $cstr .= trim(isset($data['vault_token_uid']) ? (string)$data['vault_token_uid'] : '') . '|';
        $cstr .= trim(isset($data['subscription_uid']) ? (string)$data['subscription_uid'] : '');
        $cstr .= trim(isset($data['rand']) ? (string)$data['rand'] : '');
        
        $cstrmd5 = md5($cstr . $merchant_site_uid);
        $sha512calc = hash('sha512', $cstrmd5 . $secret_key);
        
        return strtolower($sha512calc);
    }
    
    private function getHolestPayUrl() {
        $environment = $this->config->get('payment_holestpay_environment');
        return ($environment === 'production') ? 
            'https://pay.holest.com' : 
            'https://sandbox.pay.holest.com';
    }
    
    private function processPaymentResult($order_id, $order_info) {
        // Process payment result from HolestPay
        // This would include updating order status, storing transaction data, etc.
        
        $this->load->model('payment/holestpay');
        
        // Update order with HolestPay data
        $this->model_payment_holestpay->addHolestPayOrderFields(
            $order_id, 
            $order_id, 
            'PAYMENT:PENDING', 
            json_encode(array('callback_received' => true))
        );
    }
}
