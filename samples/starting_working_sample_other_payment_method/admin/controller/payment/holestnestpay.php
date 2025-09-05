<?php
namespace Opencart\Admin\Controller\Extension\Holestnestpay\Payment;
class Holestnestpay extends \Opencart\System\Engine\Controller {
	private $error     = array();
	private $_postparms = null;
	
	private function getDefaults(){
		return
			array (
			  'enabled' => '',
			  'description' => 'Pay securely by Credit or Debit Card via Banca Intesa',
			  'bank_logo' => '',
			  'cc_logo' => '',
			  'merchant_currency' => '',
			  'conversion_rate_adjust' => '0.00',
			  'user_language_code' => '',
			  'merchant_id' => '',
			  'merchant_username' => '',
			  'merchant_password' => '',
			  'gateway_url' => 'https://testsecurepay.eway2pay.com/fim/est3Dgate',
			  'api_url' => 'https://testsecurepay.eway2pay.com/fim/api',
			  'store_key' => '',
			  'title' => 'Pay by credit card',
			  'tran_type' => 'Auth',
			  'refreshtime' => '',
			  'add_bill_to' => '',
			  'store_type' => '3d_pay_hosting',
			  'instalment_plans' => '',
			  'refresh_exchange_rate' => '',
			  'override_back_url' => '',
			  'cancel_url' => '',
			  'order_completed' => '5',
			  'order_failed' => '7',
			  'serial_key' => '',
			  'no_transaction_email' => ''
			);
	}
	
	private function getParameterValue($name, $default = null){
		global $__hlst_np_defaults;
		if(!isset($__hlst_np_defaults)){
			$__hlst_np_defaults = $this->getDefaults();
		}
		
		if(isset($this->_postparms)){
			if(isset($this->_postparms["payment_holestnestpay_" . $name])){
				return $this->_postparms["payment_holestnestpay_" . $name];
			}	
		}
		$val = $this->config->get("payment_holestnestpay_" . $name);
		if($val === null){
			if(isset($__hlst_np_defaults[$name]))
				return $__hlst_np_defaults[$name];
		}
		return $val;
	}
	
	function isSecure() {
	  return
		(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
		|| $_SERVER['SERVER_PORT'] == 443;
	}

	public function index() : void {
		
		$this->load->language('extension/holestnestpay/payment/holestnestpay');
		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('setting/setting');
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['_holestnestpay_save'])) {
			if($this->request->post['_holestnestpay_save'] == "save"){
				$postparms  = array();
				
				$postparms["payment_holestnestpay_bank_logo"] = $this->getParameterValue('bank_logo',null);
				$postparms["payment_holestnestpay_cc_logo"] = $this->getParameterValue('cc_logo',null);
				
				foreach($this->request->post as $key => $val){
					if(strpos($key,"_") === 0)
						continue;
					
					if(strpos($key,"bank_logo_remove") !== false){
						$postparms["payment_holestnestpay_bank_logo"] = "";
					}elseif(strpos($key,"cc_logo_remove") !== false){
						$postparms["payment_holestnestpay_cc_logo"]   = "";
					}elseif(strpos($key,"_uploadfile") !== false){
						
					}else
						$postparms["payment_holestnestpay_" . $key] = $val;	
				}
				
				foreach($this->request->files as $key => $file){
					if($file["tmp_name"]){
						copy($file["tmp_name"], DIR_IMAGE . "holestnestpay_" . $file['name'] );
						$url = str_ireplace(array("http:","https:"),"", HTTP_CATALOG) . "image/holestnestpay_" . $file['name'];
						$postparms["payment_holestnestpay_" . str_replace("_uploadfile","",$key)] = $url;
					}
				}
				
				if(!isset($postparms["payment_holestnestpay_enabled"]))
					$postparms["payment_holestnestpay_enabled"] = "";
				
				if(!isset($postparms["payment_holestnestpay_add_bill_to"]))
					$postparms["payment_holestnestpay_add_bill_to"] = "";
				
				if(!isset($postparms["payment_holestnestpay_refresh_exchange_rate"]))
					$postparms["payment_holestnestpay_refresh_exchange_rate"] = "";
				
				if(!isset($postparms["payment_holestnestpay_no_transaction_email"]))
					$postparms["payment_holestnestpay_no_transaction_email"] = "";
				
				$postparms['payment_holestnestpay_status'] = (($postparms["payment_holestnestpay_enabled"] == "yes") ? 1 : 0);
				$postparms['holestnestpay_status'] = (($postparms["payment_holestnestpay_enabled"] == "yes") ? 1 : 0);
				$postparms['status'] = (($postparms["payment_holestnestpay_enabled"] == "yes") ? 1 : 0);
				
				$this->model_setting_setting->editSetting('payment_holestnestpay', $postparms);
				
				if(defined("VERSION")){			
					if(strpos(VERSION,"2.") === 0){
						$this->model_setting_setting->editSetting('holestnestpay', $postparms);
					}
				}
				
				$this->session->data['success'] = $this->language->get('text_success');
				
				$this->_postparms = $postparms;
			}
			
		}
		
		$data['site_base_url'] = HTTP_CATALOG;
		$data['admin_base_url'] = HTTP_SERVER;
		
		$data['enabled'] = $this->getParameterValue('enabled',null);
		$data['title'] = $this->getParameterValue('title',"Card");
		$data['description'] = $this->getParameterValue('description','Pay securely with Maestro/Master/VISA/AMEX/DINA cards');
		$data['bank_logo'] = $this->getParameterValue('bank_logo',null);
		$data['cc_logo'] = $this->getParameterValue('cc_logo',null);
		$data['merchant_currency'] = $this->getParameterValue('merchant_currency',null);
		$data['conversion_rate_adjust'] = $this->getParameterValue('conversion_rate_adjust',null);
		$data['user_language_code'] = $this->getParameterValue('user_language_code','sr');
		$data['merchant_id'] = $this->getParameterValue('merchant_id',null);
		$data['merchant_username'] = $this->getParameterValue('merchant_username',null);
		$data['merchant_password'] = $this->getParameterValue('merchant_password',null);
		$data['gateway_url'] = $this->getParameterValue('gateway_url','https://testsecurepay.eway2pay.com/fim/est3Dgate');
		$data['api_url'] = $this->getParameterValue('api_url','https://testsecurepay.eway2pay.com/fim/api');
		$data['store_key'] = $this->getParameterValue('store_key',null);
		$data['tran_type'] = $this->getParameterValue('tran_type','Auth');
		$data['refreshtime'] = $this->getParameterValue('refreshtime',null);
		$data['add_bill_to'] = $this->getParameterValue('add_bill_to',null);
		$data['no_transaction_email'] = $this->getParameterValue('no_transaction_email',null);
		$data['store_type'] = $this->getParameterValue('store_type','3d_pay_hosting');
		//$data['instalment_plans'] = $this->getParameterValue('instalment_plans',null);
		$data['refresh_exchange_rate'] = $this->getParameterValue('refresh_exchange_rate',null);
		//$data['override_back_url'] = $this->getParameterValue('override_back_url',null);
		$data['cancel_url'] = $this->getParameterValue('cancel_url',null);
		$data['order_completed'] = $this->getParameterValue('order_completed',5);
		$data['order_failed'] = $this->getParameterValue('order_failed',null);
		$data['serial_key'] = $this->getParameterValue('serial_key',null);
		$data['sort_order'] = $this->getParameterValue('sort_order',null);
		$data['geo_zone_id'] = $this->getParameterValue('geo_zone_id',null);
		//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		
		$this->load->model('localisation/currency');
		$data['currencies'] = $this->model_localisation_currency->getCurrencies();
		
		//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		$data["payment_holestnestpay_js"] =  HTTP_CATALOG . 'extension/holestnestpay/catalog/view/javascript/holestnestpay.js';
		//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		
		$data['header']      = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']      = $this->load->controller('common/footer');
		if(defined("VERSION")){			
			if(strpos(VERSION,"2.") === 0){			
			
				$this->load->model('localisation/language');	

				$ldata = (array)$this->language;
				foreach($ldata as $k => $v){
					if(is_array($v)){
						if(isset($v["code"])){
							$ldata = $v;
							break;
						}
					}
				}
				
				$all_phrases = array_keys($ldata);
				foreach($all_phrases as $phrase){					
					$data[$phrase] = $this->language->get($phrase);				
				}			
			}		
		}
		
		
		$this->response->setOutput($this->load->view('extension/holestnestpay/payment/holestnestpay', $data));	
		
	}
	
	protected function validate() {
		return true;
	}
}	