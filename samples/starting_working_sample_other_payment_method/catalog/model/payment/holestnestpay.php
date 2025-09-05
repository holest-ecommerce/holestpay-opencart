<?php
namespace Opencart\Catalog\Model\Extension\Holestnestpay\Payment;
class Holestnestpay extends \Opencart\System\Engine\Model {
	public function getMethod($address, $total = null) {
		$this->load->language('extension/payment/holestnestpay');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_holestnestpay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
		$status = false;
		
		if (!$this->config->get('payment_holestnestpay_geo_zone_id')) {
			$status = true;
		}elseif ($query->num_rows) {
			$status = true;
		}

		$method_data = array();

		if ($status) {
			$logo = "";
			if($this->config->get('payment_holestnestpay_cc_logo')){
				$logo = "<img src='".$this->config->get('payment_holestnestpay_cc_logo')."' alt='".$this->config->get('payment_holestnestpay_title')."' />";
			}
			
			$method_data = array(
				'code'       => 'holestnestpay',
				'title'      => $this->config->get('payment_holestnestpay_title'),
				'terms'      => $logo,
				'sort_order' => $this->config->get('payment_holestnestpay_sort_order')
			);
			
			
		}
		
		return $method_data;
	}
	
}