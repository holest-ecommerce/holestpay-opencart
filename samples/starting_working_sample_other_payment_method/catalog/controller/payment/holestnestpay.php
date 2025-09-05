<?php
namespace Opencart\Catalog\Controller\Extension\Holestnestpay\Payment;
class Holestnestpay extends \Opencart\System\Engine\Controller {

	private $error = array();

	private $CURRENCY_ISO_CODES = array("AFN" => 971,"EUR" => 978,"ALL" => 8,"DZD" => 12,"USD" => 840,"EUR" => 978,"AOA" => 973,"XCD" => 951,"XCD" => 951,"ARS" => 32,"AMD" => 51,"AWG" => 533,"AUD" => 36,"EUR" => 978,"AZN" => 944,"BSD" => 44,"BHD" => 48,"BDT" => 50,"BBD" => 52,"BYN" => 933,"EUR" => 978,"BZD" => 84,"XOF" => 952,"BMD" => 60,"INR" => 356,"BTN" => 64,"BOB" => 68,"BOV" => 984,"USD" => 840,"BAM" => 977,"BWP" => 72,"NOK" => 578,"BRL" => 986,"USD" => 840,"BND" => 96,"BGN" => 975,"XOF" => 952,"BIF" => 108,"CVE" => 132,"KHR" => 116,"XAF" => 950,"CAD" => 124,"KYD" => 136,"XAF" => 950,"XAF" => 950,"CLP" => 152,"CLF" => 990,"CNY" => 156,"AUD" => 36,"AUD" => 36,"COP" => 170,"COU" => 970,"KMF" => 174,"CDF" => 976,"XAF" => 950,"NZD" => 554,"CRC" => 188,"XOF" => 952,"HRK" => 191,"CUP" => 192,"CUC" => 931,"ANG" => 532,"EUR" => 978,"CZK" => 203,"DKK" => 208,"DJF" => 262,"XCD" => 951,"DOP" => 214,"USD" => 840,"EGP" => 818,"SVC" => 222,"USD" => 840,"XAF" => 950,"ERN" => 232,"EUR" => 978,"ETB" => 230,"EUR" => 978,"FKP" => 238,"DKK" => 208,"FJD" => 242,"EUR" => 978,"EUR" => 978,"EUR" => 978,"XPF" => 953,"EUR" => 978,"XAF" => 950,"GMD" => 270,"GEL" => 981,"EUR" => 978,"GHS" => 936,"GIP" => 292,"EUR" => 978,"DKK" => 208,"XCD" => 951,"EUR" => 978,"USD" => 840,"GTQ" => 320,"GBP" => 826,"GNF" => 324,"XOF" => 952,"GYD" => 328,"HTG" => 332,"USD" => 840,"AUD" => 36,"EUR" => 978,"HNL" => 340,"HKD" => 344,"HUF" => 348,"ISK" => 352,"INR" => 356,"IDR" => 360,"XDR" => 960,"IRR" => 364,"IQD" => 368,"EUR" => 978,"GBP" => 826,"ILS" => 376,"EUR" => 978,"JMD" => 388,"JPY" => 392,"GBP" => 826,"JOD" => 400,"KZT" => 398,"KES" => 404,"AUD" => 36,"KPW" => 408,"KRW" => 410,"KWD" => 414,"KGS" => 417,"LAK" => 418,"EUR" => 978,"LBP" => 422,"LSL" => 426,"ZAR" => 710,"LRD" => 430,"LYD" => 434,"CHF" => 756,"EUR" => 978,"EUR" => 978,"MOP" => 446,"MKD" => 807,"MGA" => 969,"MWK" => 454,"MYR" => 458,"MVR" => 462,"XOF" => 952,"EUR" => 978,"USD" => 840,"EUR" => 978,"MRO" => 478,"MUR" => 480,"EUR" => 978,"XUA" => 965,"MXN" => 484,"MXV" => 979,"USD" => 840,"MDL" => 498,"EUR" => 978,"MNT" => 496,"EUR" => 978,"XCD" => 951,"MAD" => 504,"MZN" => 943,"MMK" => 104,"NAD" => 516,"ZAR" => 710,"AUD" => 36,"NPR" => 524,"EUR" => 978,"XPF" => 953,"NZD" => 554,"NIO" => 558,"XOF" => 952,"NGN" => 566,"NZD" => 554,"AUD" => 36,"USD" => 840,"NOK" => 578,"OMR" => 512,"PKR" => 586,"USD" => 840,"PAB" => 590,"USD" => 840,"PGK" => 598,"PYG" => 600,"PEN" => 604,"PHP" => 608,"NZD" => 554,"PLN" => 985,"EUR" => 978,"USD" => 840,"QAR" => 634,"EUR" => 978,"RON" => 946,"RUB" => 643,"RWF" => 646,"EUR" => 978,"SHP" => 654,"XCD" => 951,"XCD" => 951,"EUR" => 978,"EUR" => 978,"XCD" => 951,"WST" => 882,"EUR" => 978,"STD" => 678,"SAR" => 682,"XOF" => 952,"RSD" => 941,"SCR" => 690,"SLL" => 694,"SGD" => 702,"ANG" => 532,"XSU" => 994,"EUR" => 978,"EUR" => 978,"SBD" => 90,"SOS" => 706,"ZAR" => 710,"SSP" => 728,"EUR" => 978,"LKR" => 144,"SDG" => 938,"SRD" => 968,"NOK" => 578,"SZL" => 748,"SEK" => 752,"CHF" => 756,"CHE" => 947,"CHW" => 948,"SYP" => 760,"TWD" => 901,"TJS" => 972,"TZS" => 834,"THB" => 764,"USD" => 840,"XOF" => 952,"NZD" => 554,"TOP" => 776,"TTD" => 780,"TND" => 788,"TRY" => 949,"TMT" => 934,"USD" => 840,"AUD" => 36,"UGX" => 800,"UAH" => 980,"AED" => 784,"GBP" => 826,"USD" => 840,"USD" => 840,"USN" => 997,"UYU" => 858,"UYI" => 940,"UZS" => 860,"VUV" => 548,"VEF" => 937,"VND" => 704,"USD" => 840,"USD" => 840,"XPF" => 953,"MAD" => 504,"YER" => 886,"ZMW" => 967,"ZWL" => 932,"XBA" => 955,"XBB" => 956,"XBC" => 957,"XBD" => 958,"XTS" => 963,"XXX" => 999,"XAU" => 959,"XPD" => 964,"XPT" => 962,"XAG" => 961,"AFA" => 4,"FIM" => 246,"ADP" => 20,"ESP" => 724,"FRF" => 250,"AON" => 24,"AOR" => 982,"ARA" => 32,"ARP" => 32,"RUR" => 810,"ATS" => 40,"AYM" => 945,"AZM" => 31,"RUR" => 810,"BYR" => 974,"BYB" => 112,"RUR" => 810,"BEC" => 993,"BEF" => 56,"BEL" => 992,"BAD" => 70,"BRC" => 76,"BRE" => 76,"BRN" => 76,"BRR" => 987,"BGL" => 100,"HRD" => 191,"HRK" => 191,"CYP" => 196,"CSK" => 200,"ECS" => 218,"ECV" => 983,"GQE" => 226,"EEK" => 233,"XEU" => 954,"FIM" => 246,"FRF" => 250,"FRF" => 250,"FRF" => 250,"GEK" => 268,"RUR" => 810,"DDM" => 278,"DEM" => 276,"GHC" => 288,"GHP" => 939,"GRD" => 300,"FRF" => 250,"GWP" => 624,"ITL" => 380,"IEP" => 372,"ITL" => 380,"RUR" => 810,"RUR" => 810,"LVL" => 428,"LVR" => 428,"ZAL" => 991,"LTL" => 440,"LTT" => 440,"LUC" => 989,"LUF" => 442,"LUL" => 988,"MGF" => 450,"MWK" => 454,"MLF" => 466,"MTL" => 470,"FRF" => 250,"FRF" => 250,"RUR" => 810,"FRF" => 250,"MZM" => 508,"NLG" => 528,"ANG" => 532,"PEN" => 604,"PEI" => 604,"PES" => 604,"PLZ" => 616,"PTE" => 620,"FRF" => 250,"RON" => 946,"ROL" => 642,"RUR" => 810,"FRF" => 250,"FRF" => 250,"FRF" => 250,"ITL" => 380,"CSD" => 891,"EUR" => 978,"SKK" => 703,"SIT" => 705,"ZAL" => 991,"SDG" => 938,"ESA" => 996,"ESB" => 995,"ESP" => 724,"SDD" => 736,"SRG" => 740,"CHC" => 948,"RUR" => 810,"TJR" => 762,"IDR" => 360,"TPE" => 626,"TRL" => 792,"TRY" => 949,"RUR" => 810,"TMM" => 795,"UAK" => 804,"USS" => 998,"RUR" => 810,"VEB" => 862,"VEF" => 937,"VEF" => 937,"YDD" => 720,"YUM" => 891,"YUN" => 890,"ZRN" => 180,"ZRZ" => 180,"ZMK" => 894,"ZWD" => 716,"ZWD" => 716,"ZWN" => 942,"ZWR" => 935);

	

	public function orderIdToOid($order_id){

		$res = $this->db->query("SELECT concat(TIMESTAMPDIFF(SECOND,'1970-01-01 00:00:00',`date_added`) % 99998,'0000',`order_id`) as oid FROM " . DB_PREFIX . "order WHERE order_id = {$order_id}");

		if(!$res->num_rows)

			return "";

		return $res->row["oid"];

	}

	

	public function oidToOrderId($oid){

		if(!$oid)

			return "";

		$r = explode("0000",$oid);

		$r[0] = "";

		return implode("",$r);

	}

	

	public function generateRandomString($length = 10) {

		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		$charactersLength = strlen($characters);

		$randomString = '';

		for ($i = 0; $i < $length; $i++) {

			$randomString .= $characters[rand(0, $charactersLength - 1)];

		}

		return $randomString;

	}

	

	public function getRelevantConfig(){

		global $ocnp_cfg;

		

		if(isset($ocnp_cfg))

			return $ocnp_cfg;

		

		$ocnp_cfg = array();

		$res = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE `code` = 'config' AND (

																`key` LIKE 'config_mail_%' 

																OR 

																`key` LIKE '%holestnestpay%'

																OR 

																`key` IN (

																			'config_email',

																			'config_url',

																			'config_theme',

																			'config_currency'

																		 )

																)");

		if($res->num_rows){

			foreach($res->rows as $rec){

				$ocnp_cfg[$rec["key"]] = $rec["value"];

			}

		}

		return $ocnp_cfg;

	}

	

	private function module_param($name){

		global $ocnp_cfg;

		if(!isset($ocnp_cfg)){

			$this->getRelevantConfig();

		}

		if(isset($ocnp_cfg[$name]))

			return $ocnp_cfg[$name];

		else

			return $this->config->get($name);

	}

	

	public function checkRequirements(){
		$ext = "twig";
		
		
		$footer_files = array(DIR_TEMPLATE . "common". DIRECTORY_SEPARATOR . "footer.{$ext}");
		foreach(glob(DIR_EXTENSION . '*', GLOB_ONLYDIR) as $dir) {
			if(file_exists($dir . "/catalog/view/template/common/footer.{$ext}")){
				$footer_files[] = $dir . "/catalog/view/template/common/footer.{$ext}";
			}
		}
		
		$planted     = false;
		$cannotwrite = false;
		
		ob_start();
		
		foreach($footer_files as $footer_file){
			if(file_exists($footer_file)){
				$cnt = file_get_contents($footer_file);
				
				if(stripos($cnt,"</body>") !== false){
					if(stripos($cnt,"payment_holestnestpay_script") === false){
						$cnt = str_ireplace(
								  "</body>",
								  "\r\n<script id='payment_holestnestpay_script' src='/extension/holestnestpay/catalog/view/javascript/holestnestpay.js?ver=1'></script>\r\n</body>",

								  $cnt);
						if(@file_put_contents($footer_file,$cnt)){
							$planted     = true;
						}else{
							$cannotwrite = true;
						}		  
					}else
						$planted = true;
				}
			}
		}
		
		$dump = ob_get_clean();
		
		if($planted)
			return false;	
		else if($cannotwrite){
			return $this->language->get('payment_holestnestpay_script_missing'); 
		}else{
			return $this->language->get('payment_holestnestpay_setup_incomplete'); 
		}
	}

	public function index() {

		$this->load->language('extension/holestnestpay/payment/holestnestpay');

		$this->load->model('checkout/order');

		

		$err_req = $this->checkRequirements();

		

		if($err_req){

			$data['err_req'] = $err_req;

		}

		

		$data["not_configured"] = "not_configured";

		$data["general_error"] = "general_error";
		
		if(

			!$this->module_param('payment_holestnestpay_store_key')

			||	

			!$this->module_param('payment_holestnestpay_merchant_id')

			||

			!$this->module_param('payment_holestnestpay_gateway_url')

		){

			$data["error"] = "not_configured";

			

		}else{

		

			$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

			

			$use_currency          = $this->module_param('payment_holestnestpay_merchant_currency'); 

			if(!$use_currency)

				$use_currency = $order_info['currency_code'];

			

			$refresh_exchange_rate = $this->module_param('payment_holestnestpay_refresh_exchange_rate') == "yes"; 

			$exchange_rate = 1;

			

			$default_currency = $this->module_param('config_currency');

			if($refresh_exchange_rate && $use_currency != $order_info['currency_code']){

				

				$adjust_perc = floatval($this->module_param('payment_holestnestpay_conversion_rate_adjust'));

				if(!$adjust_perc){

					$adjust_perc = 0;

				}

				$adjust_perc = $adjust_perc / 100;

				

				if($default_currency != $order_info['currency_code']){

					$res = $this->db->query("SELECT value as value, TIMESTAMPDIFF(SECOND,date_modified,NOW()) as pastfromupdate FROM " . DB_PREFIX . "currency WHERE code LIKE '". $order_info['currency_code']."'");

					if($res->num_rows){

						if($res->row["pastfromupdate"] > 60 * 60 * 2){

							$CLIENT_SCHEMA = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';

							$to_cur   = $order_info['currency_code'];

							$NESTPAY_EXCANGE_RATE_LINK = "{$CLIENT_SCHEMA}cdn.payments.holest.com/exchangerate.php?from={$default_currency}&to={$to_cur}"; 

							$excresp = json_decode(file_get_contents($NESTPAY_EXCANGE_RATE_LINK));

							if($excresp){

								$exchange_rate_update  = floatval($excresp->rate);

								if($exchange_rate_update){

									$exchange_rate_update *= (1 + $adjust_perc);

									$res = $this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '{$exchange_rate_update}', date_modified = NOW() WHERE code LIKE '{$to_cur}'");	

								}

							}

						}

					}	

				}

				

				if($default_currency != $use_currency){

					$res = $this->db->query("SELECT value as value, TIMESTAMPDIFF(SECOND,date_modified,NOW()) as pastfromupdate FROM " . DB_PREFIX . "currency WHERE code LIKE '". $use_currency."'");

					if($res->num_rows){

						if($res->row["pastfromupdate"] > 60 * 60 * 2){

							$CLIENT_SCHEMA = stripos($_SERVER['SERVER_PROTOCOL'],'https') === true ? 'https://' : 'http://';

							$to_cur   = $use_currency;

							$NESTPAY_EXCANGE_RATE_LINK = "{$CLIENT_SCHEMA}cdn.payments.holest.com/exchangerate.php?from={$default_currency}&to={$to_cur}"; 

							$excresp = json_decode(file_get_contents($NESTPAY_EXCANGE_RATE_LINK));

							if($excresp){

								$exchange_rate_update  = floatval($excresp->rate);

								if($exchange_rate_update){

									$exchange_rate_update *= (1 + $adjust_perc);

									$res = $this->db->query("UPDATE " . DB_PREFIX . "currency SET value = '{$exchange_rate_update}', date_modified = NOW() WHERE code LIKE '{$to_cur}'");	

								}

							}

						}

					}

				}

			}

			$amount = $order_info['total'];
			
			
			if(isset($order_info['currency_value'])){
				if(floatval($order_info['currency_value'])){
					$amount = floatval($amount) * floatval($order_info['currency_value']);
				}
			}
			
			if($use_currency != $order_info['currency_code']){

				if($default_currency != $order_info['currency_code']){

					$res = $this->db->query("SELECT value as value FROM " . DB_PREFIX . "currency WHERE code LIKE '". $order_info['currency_code']."'");

					if($res->num_rows){

						if($res->row["value"]){

							$exchange_rate *= $res->row["value"];

						}

					}	

				}

				

				if($default_currency != $use_currency){

					$res = $this->db->query("SELECT value as value FROM " . DB_PREFIX . "currency WHERE code LIKE '". $use_currency."'");

					if($res->num_rows){

						if($res->row["value"]){

							$exchange_rate *= $res->row["value"];

						}

					}

				}

			}

			

			$data["order_id"] = $this->session->data['order_id'];

			

			$npintesa_args = array();

			$npintesa_args["order_id"]  = $this->session->data['order_id'];

			$npintesa_args["clientid"]  = $this->module_param('payment_holestnestpay_merchant_id');

			

			$npintesa_args["amount"]    = number_format(round(floatval($amount) * $exchange_rate ,2),2, '.', '');

			

			$npintesa_args["okUrl"]     = htmlspecialchars_decode($this->url->link('extension/holestnestpay/payment/holestnestpay|success',"order_id=" . $npintesa_args["order_id"],true));

			$npintesa_args["failUrl"]   = htmlspecialchars_decode($this->url->link('extension/holestnestpay/payment/holestnestpay|failed',"order_id=" . $npintesa_args["order_id"],true));

			$npintesa_args["shopurl"]  = htmlspecialchars_decode($this->module_param('payment_holestnestpay_cancel_url')); 

			if(!$npintesa_args["shopurl"])

				$npintesa_args["shopurl"] = $npintesa_args["failUrl"];

			$npintesa_args["trantype"]  = $this->module_param('payment_holestnestpay_tran_type'); 

			$npintesa_args["currency"]  = $this->CURRENCY_ISO_CODES[$use_currency]; 

			$npintesa_args["rnd"]       = $this->generateRandomString(20); 

			$npintesa_args["storetype"] = $this->module_param('payment_holestnestpay_store_type'); 

			$npintesa_args["hashAlgorithm"] = "ver2";

			$npintesa_args["lang"] = "en";

			if($this->module_param('payment_holestnestpay_user_language_code'))

				$npintesa_args["lang"] = $this->module_param('payment_holestnestpay_user_language_code');

			$npintesa_args["oid"]      = $this->orderIdToOid($npintesa_args["order_id"]);

			$npintesa_args["storeKey"] = $this->module_param('payment_holestnestpay_store_key');

			$npintesa_args["instalment"] = "";

			

			if($this->module_param('payment_holestnestpay_add_bill_to')){

				 $npintesa_args["email"] 		   = $order_info['email'];

				 $npintesa_args["tel"]   		   = (string)substr($order_info['telephone'], 0, 32);

				 $npintesa_args["BillToCompany"]   = (string)substr($order_info['payment_company'], 0, 50); 

				 $npintesa_args["BillToName"]      = (string)substr($order_info['payment_firstname'], 0, 24) . " " . (string)substr($order_info['payment_lastname'], 0, 50);

				 $npintesa_args["BillToStreet1"]   = (string)substr($order_info['payment_address_1'], 0, 50);

				 $npintesa_args["BillToStreet2"]   = (string)substr($order_info['payment_address_2'], 0, 50);

				 $npintesa_args["BillToStreet3"]    = "--";

				 $npintesa_args["BillToCity"]       = (string)substr($order_info['payment_city'], 0, 50);

				 $npintesa_args["BillToStateProv"]  = "";

				 $npintesa_args["BillToPostalCode"] = (string)substr($order_info['payment_postcode'], 0, 30);

				 $npintesa_args["BillToCountry"]    = strtolower($order_info['payment_iso_code_2']);

			}

			

			$npintesa_args["encoding"] = "UTF-8";

			$npintesa_args["refreshtime"] = $this->module_param('payment_holestnestpay_refreshtime');

			

			$orgClientId  = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['clientid']));

			$oid          = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['oid']));

			$storetype    = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['storetype']));

			$orgAmount    = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['amount']));

			$orgOkUrl     = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['okUrl']));

			$orgFailUrl   = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['failUrl']));

			$orgTransactionType = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['trantype']));

			$instalment   = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['instalment']));

			$orgRnd       = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['rnd']));

			$orgCurrency   = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['currency']));

			$storeKey      = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['storeKey']));

			$hashAlgorithm = str_replace("|", "\\|", str_replace("\\", "\\\\", $npintesa_args['hashAlgorithm']));

			

			$hash = "";

			

			//THIS IS HASH VER2

			$plaintext = $orgClientId ."|". $oid."|".$orgAmount."|".$orgOkUrl."|".$orgFailUrl."|".$orgTransactionType."|".$instalment."|".$orgRnd."||||".$orgCurrency."|".$storeKey;

			$calculatedHashValue = hash('sha512', $plaintext);

			$hash = base64_encode(pack('H*',$calculatedHashValue));

			

			

			$npintesa_args_array = array();

			foreach($npintesa_args as $key => $value){

				if($key != "path" && $key != "storeKey"){

					if($key == "instalment" && !intval($npintesa_args['instalment']))

						continue;

					$npintesa_args_array[] = "<input type='hidden' name='$key' id='nestpayfield_$key' class='nestpayfield_$key' value='$value'/>";

				}

			}

			$npintesa_args_array[] = "<input type='hidden' name='hash' value='$hash'/>";

			

			$before = "";

			if($use_currency != $order_info['currency_code']){

				$before .= "<p>";

				$before .= $this->language->get("payment_holestnestpay_payment_currency") . ": " . $use_currency;

				$before .= "</p>";

				$before .= "<p>";

				$before .= $this->language->get("payment_holestnestpay_payment_currency_total") . ": " . $npintesa_args["amount"];

				$before .= "</p>";

			}

			

			$data["form_fields"] = $before . implode("\n",$npintesa_args_array); 

			$data["description"] = $this->module_param('payment_holestnestpay_description');

			$data["action"]      = $this->module_param('payment_holestnestpay_gateway_url');
			
			$data['site_base_url'] = $this->siteUrl();

		}
		
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
		
		return $this->load->view('extension/holestnestpay/payment/holestnestpay', $data);

	}

	

	public function rightValueFromXML($val){

		if(is_object($val) || is_array($val))

			return "";

		return $val;

	}

	

	public function readStatusWithApi($order_id){

		global $last_curl_resp;

		

		$merchant_username = $this->module_param('payment_holestnestpay_merchant_username');

		$merchant_password = $this->module_param('payment_holestnestpay_merchant_password');

		$merchant_id       = $this->module_param('payment_holestnestpay_merchant_id');

		$api_url           = $this->module_param('payment_holestnestpay_api_url');

		

		$oid = $this->orderIdToOid($order_id);

		

		$npv_data = array();

		$npv_data['oid']            = $oid;

		$npv_data['TransId']        = null;

		$npv_data['Response']       = "--";

		$npv_data['ProcReturnCode'] = "--";

		$npv_data['mdStatus']       = "--";

		$npv_data['AuthCode']       = "--";

		$npv_data['EXTRA_TRXDATE']  = "--";

		$npv_data['instalment']     = "--";

		$npv_data['ProcReturnCode'] = "--";

		$npv_data["clientid"]       = $merchant_id;

		

		if(!$merchant_username || !$merchant_password || !$merchant_id || !$order_id){

			$last_curl_resp = "no_config";

			return false;

		}

		

		//encoding=\"ISO-8859-9\"

		$post_variables = array(

							"DATA" => 

							  "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>

								 <CC5Request>

								 <Name>{$merchant_username}</Name>

								 <Password>{$merchant_password}</Password>

								 <ClientId>{$merchant_id}</ClientId>

								 <OrderId>{$oid}</OrderId>

								 <Extra>

								 <ORDERSTATUS>QUERY</ORDERSTATUS>

								 </Extra>

								</CC5Request>"

							   );

					

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, $api_url );

		curl_setopt($curl, CURLOPT_POST, true);

		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post_variables));

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

		

		$result = curl_exec($curl);    

		$last_curl_resp = $result;

		

		curl_close($curl);	

		if($result){

			$xresp = simplexml_load_string($result);

			if($xresp){

				$xresp = json_decode(json_encode($xresp));

				

				if(isset($xresp->Response))

					$npv_data["Response"]      = $this->rightValueFromXML($xresp->Response);

				if(isset($xresp->TransId))

					$npv_data["TransId"]       = $this->rightValueFromXML($xresp->TransId);

				

				if(!isset($xresp->Extra))

					$xresp->Extra = new stdClass;

				

				if(isset($xresp->Extra->ORD_ID)){

					

					if(isset($xresp->Extra->ORD_ID))

						$npv_data["ReturnOid"]     = $this->rightValueFromXML($xresp->Extra->ORD_ID);

					if(isset($xresp->Extra->MDSTATUS))

						$npv_data["mdStatus"]      = $this->rightValueFromXML($xresp->Extra->MDSTATUS);

					if(isset($xresp->Extra->ORD_ID))

						$npv_data["AuthCode"]      = $this->rightValueFromXML($xresp->Extra->AUTH_CODE);

					if(isset($xresp->Extra->AUTH_CODE))

						$npv_data["EXTRA_TRXDATE"] = $this->rightValueFromXML($xresp->Extra->AUTH_DTTM);

					if(isset($xresp->ProcReturnCode))

						$npv_data["ProcReturnCode"]= $this->rightValueFromXML($xresp->ProcReturnCode);

				}else{

					if(isset($xresp->OrderId))

						$npv_data["ReturnOid"]     = $this->rightValueFromXML($xresp->OrderId);

					if(isset($xresp->Extra->MDSTATUS))

						$npv_data["mdStatus"]      = $this->rightValueFromXML($xresp->Extra->MDSTATUS);

					if(isset($xresp->AuthCode))

						$npv_data["AuthCode"]      = $this->rightValueFromXML($xresp->AuthCode);

					if(isset($xresp->Extra->TRXDATE))

						$npv_data["EXTRA_TRXDATE"] = $this->rightValueFromXML($xresp->Extra->TRXDATE);

					if(isset($xresp->ProcReturnCode))

						$npv_data["ProcReturnCode"]= $this->rightValueFromXML($xresp->ProcReturnCode);

				}

			}

		}

		return $npv_data;

	}

	

	public function checkorder(){

		$this->load->language('extension/holestnestpay/payment/holestnestpay');

		$this->load->model('checkout/order');

		$resp = array();

		

		if(isset($this->request->post["order_id"])){

			$order_id = $this->session->data['order_id'];

			$np_response = $this->cleanResponse($this->readStatusWithApi($this->request->post["order_id"]));

			$resp["np_response"] = $np_response;

			

			$failed_status_id          = $this->module_param('payment_holestnestpay_order_failed');

			$order_completed_status_id = $this->module_param('payment_holestnestpay_order_completed');

			

			$order_info = $this->model_checkout_order->getOrder($order_id);

			if($order_info){

				$sqlresult = $this->db->query("SELECT * FROM " . DB_PREFIX . "order_history WHERE order_id = {$order_id}");

				$process   = !$sqlresult->num_rows;

				$success   = false;

				$failed    = false;

				if($np_response["mdStatus"]){

					if(stripos($np_response["Response"],"Approved") !== false){

						$success = true;

					}else if(stripos($np_response["Response"],"Declined") !== false || stripos($np_response["Response"],"Error") !== false){

						$failed  = true;

					}

				}

				

				$html_resp = "";

				if($success)

					$html_resp = $this->responseToHtml($np_response,

										   $this->language->get('payment_holestnestpay_transaction_success'),

										   "green",	

										   $this->language->get('payment_holestnestpay_transaction_details'));

				else						   

					$html_resp = $this->responseToHtml($np_response,

												   $this->language->get('payment_holestnestpay_transaction_failed'),

												   "red",	

												   $this->language->get('payment_holestnestpay_transaction_details'));	

					

				if($process){

					if($success || $failed){

						$status_id = $success ? $order_completed_status_id : $failed_status_id;

						

						$query = $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = {$status_id} WHERE order_id = {$order_id}");

						try{
							$querypm = $this->db->query("UPDATE " . DB_PREFIX . "order SET 
								payment_method = '" . $this->module_param('payment_holestnestpay_title') . "',
								payment_code   = 'holestnestpay'
							WHERE order_id = {$order_id}");
						}catch(Throwable $ex){
							
						}

						$notified = false;

						if($this->module_param('payment_holestnestpay_no_transaction_email') != "yes"){											   

							$mail = new Mail();

							

							

							

							$mail->protocol = $this->module_param('config_mail_protocol');

							$mail->parameter = $this->module_param('config_mail_parameter');

							$mail->smtp_hostname = $this->module_param('config_mail_smtp_hostname');

							$mail->smtp_username = $this->module_param('config_mail_smtp_username');

							$mail->smtp_password = html_entity_decode($this->module_param('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');

							$mail->smtp_port = $this->module_param('config_mail_smtp_port');

							$mail->smtp_timeout = $this->module_param('config_mail_smtp_timeout');



							$mail->setTo($order_info['email']);

							$mail->setFrom($this->module_param('config_email'));

							$mail->setSender(html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'));

							

							if($success){

								$mail->setSubject(html_entity_decode($this->language->get('payment_holestnestpay_transaction_details') . " " . $this->language->get('payment_holestnestpay_transaction_success') , ENT_QUOTES, 'UTF-8'));

						    }else{

								$mail->setSubject(html_entity_decode($this->language->get('payment_holestnestpay_transaction_details') . " " . $this->language->get('payment_holestnestpay_transaction_failed') , ENT_QUOTES, 'UTF-8'));

							}

							

							$mhtml = "<p>&nbsp;</p>" .$html_resp. "<p>&nbsp;</p>" . "<p>" . $order_info['store_name'] . " " . date("d.m.Y")."</p>";

																	   

							$mail->setHtml($mhtml);

							$mail->setText(strip_tags(str_ireplace(array("</p>","</ul>"),"\n",$mhtml)));

							if($mail->send()){

								$notified = true;

							}

						}

						$this->addOrderHistory($order_id, $status_id,$this->language->get('payment_holestnestpay_transaction_details') . "\n" . json_encode($np_response),$notified);

						if($success){

							$this->cart->clear();

						}

					}

					

					if($success){

						$resp["redirect"] = $this->url->link('checkout/success', '', true);

					}else if($failed){

						$resp["redirect"] = $this->url->link('checkout/failure', '', true);

					}

				}

				

				if($success || $failed){

					$resp["success"]               = $success;

					$resp["complete_with_message"] = $html_resp;

				}

				

			}else{

				$resp["complete"]      = true;

				$resp["console_error"] = "Order {$order_id} not found";

			}

		}else{

			$resp["no_order_id"] = 1;

		}

		

		$this->response->addHeader('Content-Type: application/json');

		$this->response->setOutput(json_encode($resp));

	}

	

	public function cleanResponse($resp){

		$clean = array();

		

		if(!isset($resp["oid"])){

			if(isset($resp["ReturnOid"]))

				$resp["oid"] = $resp["ReturnOid"];

			else if(isset($resp["order_id"]))

				$resp["oid"] = $resp["order_id"];

		}

		

		foreach($resp as $key=> $val){

			if(in_array(strtolower($key),array("oid","authcode","response","procreturncode","transid","extra_trxdate","mdstatus")))

				$clean[$key] = $val;

		}

		return $clean;

	}

	

	public function addOrderHistory($order_id, $status_id, $comment, $notify = true){

		$this->load->model('checkout/order');
		
		if(defined("VERSION")){			
			if(strpos(VERSION,"2.") === 0){	
				$order_info = $this->model_checkout_order->getOrder($order_id);
				$this->model_checkout_order->addHistory($order_id, $status_id);
				return;
			}
		}

		$this->model_checkout_order->addHistory($order_id, $status_id,$comment,true);
	}

	public function responseToHtml($arr, $title ,$title_color = "red", $subtitle = ""){

		$out = "<h3  style='color:{$title_color}' class='holestnestpay_response_title'>{$title}</h3>";

		$out .= "<h4 class='holestnestpay_response_subtitle'>{$subtitle}</h3>";

		$out .= "<ul class='holestnestpay_response'>";

		foreach($arr as $key => $val){

			if(in_array(strtolower($key),array("oid","authcode","response","procreturncode","transid","extra_trxdate","mdstatus")))

				$out .=  "<li><span>" . $this->language->get("payment_holestnestpay_payment_field_" . strtolower($key)) . "</span>: <b>{$val}</b></li>";

		}

		$out .= "</ul>";
		return $out;
	}
	
	private function siteUrl(){
		return HTTP_SERVER;
	}
	
	private function makeInfo($order_id, $order_info){
		if($order_info){
			$this->load->language('account/order');
			$this->load->model('account/order');
			
			$url = '';

			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}

			$data['header'] = array();
			$data['footer'] = array();
			
			
			$data['site_base_url'] = $this->siteUrl();
			
			$data['breadcrumbs'] = array();

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/home')
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_account'),
				'href' => $this->url->link('account/account', '', true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('account/order', $url, true)
			);

			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_order'),
				'href' => $this->url->link('account/order/info', 'order_id=' . $this->request->get['order_id'] . $url, true)
			);

			if (isset($this->session->data['error'])) {
				$data['error_warning'] = $this->session->data['error'];

				unset($this->session->data['error']);
			} else {
				$data['error_warning'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];

				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}

			if ($order_info['invoice_no']) {
				$data['invoice_no'] = $order_info['invoice_prefix'] . $order_info['invoice_no'];
			} else {
				$data['invoice_no'] = '';
			}

			$data['order_id'] = $this->request->get['order_id'];
			$data['date_added'] = date($this->language->get('date_format_short'), strtotime($order_info['date_added']));

			if ($order_info['payment_address_format']) {
				$format = $order_info['payment_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['payment_firstname'],
				'lastname'  => $order_info['payment_lastname'],
				'company'   => $order_info['payment_company'],
				'address_1' => $order_info['payment_address_1'],
				'address_2' => $order_info['payment_address_2'],
				'city'      => $order_info['payment_city'],
				'postcode'  => $order_info['payment_postcode'],
				'zone'      => $order_info['payment_zone'],
				'zone_code' => $order_info['payment_zone_code'],
				'country'   => $order_info['payment_country']
			);

			$data['payment_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['payment_method'] = $order_info['payment_method'];

			if ($order_info['shipping_address_format']) {
				$format = $order_info['shipping_address_format'];
			} else {
				$format = '{firstname} {lastname}' . "\n" . '{company}' . "\n" . '{address_1}' . "\n" . '{address_2}' . "\n" . '{city} {postcode}' . "\n" . '{zone}' . "\n" . '{country}';
			}

			$find = array(
				'{firstname}',
				'{lastname}',
				'{company}',
				'{address_1}',
				'{address_2}',
				'{city}',
				'{postcode}',
				'{zone}',
				'{zone_code}',
				'{country}'
			);

			$replace = array(
				'firstname' => $order_info['shipping_firstname'],
				'lastname'  => $order_info['shipping_lastname'],
				'company'   => $order_info['shipping_company'],
				'address_1' => $order_info['shipping_address_1'],
				'address_2' => $order_info['shipping_address_2'],
				'city'      => $order_info['shipping_city'],
				'postcode'  => $order_info['shipping_postcode'],
				'zone'      => $order_info['shipping_zone'],
				'zone_code' => $order_info['shipping_zone_code'],
				'country'   => $order_info['shipping_country']
			);

			$data['shipping_address'] = str_replace(array("\r\n", "\r", "\n"), '<br />', preg_replace(array("/\s\s+/", "/\r\r+/", "/\n\n+/"), '<br />', trim(str_replace($find, $replace, $format))));

			$data['shipping_method'] = $order_info['shipping_method'];

			$this->load->model('catalog/product');
			$this->load->model('tool/upload');

			// Products
			$data['products'] = array();
			
			$products = $this->model_account_order->getProducts($this->request->get['order_id']);

			foreach ($products as $product) {
				$option_data = array();

				$options = $this->model_account_order->getOptions($this->request->get['order_id'], $product['order_product_id']);

				foreach ($options as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				}

				$product_info = $this->model_catalog_product->getProduct($product['product_id']);

				if ($product_info) {
					$reorder = $this->url->link('account/order/reorder', 'order_id=' . $order_id . '&order_product_id=' . $product['order_product_id'], true);
				} else {
					$reorder = '';
				}

				$data['products'][] = array(
					'name'     => $product['name'],
					'model'    => $product['model'],
					'option'   => $option_data,
					'quantity' => $product['quantity'],
					'price'    => $this->currency->format($product['price'] + ($this->config->get('config_tax') ? $product['tax'] : 0), $order_info['currency_code'], $order_info['currency_value']),
					'total'    => $this->currency->format($product['total'] + ($this->config->get('config_tax') ? ($product['tax'] * $product['quantity']) : 0), $order_info['currency_code'], $order_info['currency_value']),
					'reorder'  => $reorder,
					'return'   => $this->url->link('account/return/add', 'order_id=' . $order_info['order_id'] . '&product_id=' . $product['product_id'], true)
				);
			}

			// Voucher
			$data['vouchers'] = array();

			$vouchers = $this->model_account_order->getVouchers($this->request->get['order_id']);

			foreach ($vouchers as $voucher) {
				$data['vouchers'][] = array(
					'description' => $voucher['description'],
					'amount'      => $this->currency->format($voucher['amount'], $order_info['currency_code'], $order_info['currency_value'])
				);
			}

			// Totals
			$data['totals'] = array();

			$totals = $this->model_account_order->getTotals($this->request->get['order_id']);

			foreach ($totals as $total) {
				$data['totals'][] = array(
					'title' => $total['title'],
					'text'  => $this->currency->format($total['value'], $order_info['currency_code'], $order_info['currency_value']),
				);
			}

			$data['comment'] = nl2br($order_info['comment']);

			// History
			$data['histories'] = array();

			$results = $this->model_account_order->getHistories($this->request->get['order_id']);

			foreach ($results as $result) {
				$data['histories'][] = array(
					'date_added' => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
					'status'     => $result['status'],
					'comment'    => $result['notify'] ? nl2br($result['comment']) : ''
				);
			}

			$data['continue'] = $this->url->link('account/order', '', true);

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			return $this->load->view('extension/holestnestpay/payment/holestnestpay_order_info', $data);
		}else 
			return "";
	}
	

	public function success() {

		

		$this->load->language('extension/holestnestpay/payment/holestnestpay');

		$this->load->model('checkout/order');

		

		$np_response = null;

		$order_id    = null;

		

		if(isset($this->request->post["ReturnOid"])){

			$np_response = $this->request->post;

			$order_id    = $this->oidToOrderId($this->request->post["ReturnOid"]);

		}else if(isset($this->request->post["oid"])){

			$np_response = $this->request->post;

			$order_id    = $this->oidToOrderId($this->request->post["oid"]);

		}else{

			$np_response = $this->readStatusWithApi($this->request->get["order_id"]);

			$order_id    = $this->request->get["order_id"];

		}

		

		if(!$order_id){

			$this->session->data['error'] = $this->language->get('payment_holestnestpay_transaction_success') . " " . $this->language->get('payment_holestnestpay_transaction_success_problem');

			$this->response->redirect($this->url->link('checkout/checkout', '', true));

			return;

		}
		
		$this->session->data['orderid']  = $order_id;
        $this->session->data['order_id'] = $order_id;
		

		$order_completed_status_id = $this->module_param('payment_holestnestpay_order_completed');

		$order_info = $this->model_checkout_order->getOrder($order_id);



		$this->cart->clear();
		
		$already_proccessed = false;
		
		if(isset($np_response["TransId"])){
			if($np_response["TransId"]){
				$transId = $np_response["TransId"];
				$res = $this->db->query("SELECT order_id FROM " . DB_PREFIX . "order_history WHERE order_id = {$order_id} AND comment LIKE '%{$transId}%'");
				if($res){
					if(isset($res->rows)){
						if(!empty($res->rows)){
							$already_proccessed = true;
						}
					}
				}
			}
		}
		

		$np_response = $this->cleanResponse($np_response);

		if(isset($np_response["Response"])){

			

			if(

				stripos($np_response["Response"],"approved") === false 

				&& 

				stripos($np_response["Response"],"odobreno") === false){

					

					return $this->failed();

			}

		}

		

		$html_resp = $this->responseToHtml($np_response,

											   $this->language->get('payment_holestnestpay_transaction_success'),

											   "green",	

											   $this->language->get('payment_holestnestpay_transaction_details'));

		
			
		
										   

		$notified = false;

		if(!$already_proccessed && $order_info["email"] && $this->module_param('payment_holestnestpay_no_transaction_email') != "yes"){											   

			

			$mail = new \Opencart\System\Library\Mail();

			$mail->protocol = $this->module_param('config_mail_protocol');

			$mail->parameter = $this->module_param('config_mail_parameter');

			$mail->smtp_hostname = $this->module_param('config_mail_smtp_hostname');

			$mail->smtp_username = $this->module_param('config_mail_smtp_username');

			$mail->smtp_password = html_entity_decode($this->module_param('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');

			$mail->smtp_port = $this->module_param('config_mail_smtp_port');

			$mail->smtp_timeout = $this->module_param('config_mail_smtp_timeout');



			$mail->setTo($order_info['email']);

			$mail->setFrom($this->module_param('config_email'));

			$mail->setSender(html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'));

			$mail->setSubject(html_entity_decode($this->language->get('payment_holestnestpay_transaction_details') . " " . $this->language->get('payment_holestnestpay_transaction_success') , ENT_QUOTES, 'UTF-8'));

			$html_resp .= $this->makeInfo($order_id, $order_info);
			
			$mhtml = "<p>&nbsp;</p>" .$html_resp. "<p>&nbsp;</p>" . "<p>" . $order_info['store_name'] . " " . date("d.m.Y")."</p>";

			$mail->setHtml($mhtml);

			$mail->setText(strip_tags(str_ireplace(array("</p>","</ul>"),"\n",$mhtml)));

			try{

				if($mail->send()){

					$notified = true;

				}

			}catch(Exception $ex){

				echo "<p  class='error' style='color:red'>Error sending email<!-- " . $ex->getMessage() . " --></p>";		

			}catch(Throwable $tx){

				echo "<p  class='error' style='color:red'>Error sending email<!-- " . $ex->getMessage() . " --></p>";	

			}	

		}

			
		if(!$already_proccessed)
			$this->addOrderHistory($order_id, $order_completed_status_id, $this->language->get('payment_holestnestpay_transaction_details') . "\n" . json_encode($np_response),$notified);

		

		if($order_id){
			$query = $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = {$order_completed_status_id} WHERE order_id = {$order_id}");
			try{
				$querypm = $this->db->query("UPDATE " . DB_PREFIX . "order SET 
					payment_method = '" . $this->module_param('payment_holestnestpay_title') . "',
					payment_code   = 'holestnestpay'
				WHERE order_id = {$order_id}");
			}catch(Throwable $ex){
				
			}
		}

		

		?><!DOCTYPE html>

		<html>

			<head>

				<title></title>

				<script src="<?php echo $this->module_param('config_url'); ?>extension/holestnestpay/catalog/view/javascript/holestnestpay.js" type="text/javascript"></script>

				<link rel="stylesheet" href="<?php echo $this->module_param('config_url'); ?>extension/holestnestpay/catalog/view/template/payment/stylesheet/holestnestpay.css"> 

			</head>

			<body class="success">

				<h1><?php echo $this->module_param('config_name'); ?></h1>

				<h3><?php echo $this->language->get('payment_holestnestpay_transaction_details'); ?></h3>

				<div>

					<?php

					echo $html_resp;

					?>
					<div class="order_details">
						<?php
						$o_html = $this->makeInfo($order_id, $order_info);
						echo $o_html;
						?>
					</div>	
				</div>
				<p style="margin-bottom:150px;">
					<a href="<?php echo $this->module_param('config_url'); ?>index.php?route=checkout/success"><?php echo  $this->language->get('payment_holestnestpay_proceed'); ?></a>
				</p>
			</body>

		</html>

		<?php

		die;

		//$this->response->redirect($this->url->link('checkout/success', '', true));

	}

	

	public function failed() {

		$this->load->language('extension/holestnestpay/payment/holestnestpay');

		$this->load->model('checkout/order');

		

		$np_response = null;

		$order_id    = null;

		

		if(isset($this->request->post["ReturnOid"])){

			$np_response = $this->request->post;

			$order_id    = $this->oidToOrderId($this->request->post["ReturnOid"]);

		}else if(isset($this->request->post["oid"])){

			$np_response = $this->request->post;

			$order_id    = $this->oidToOrderId($this->request->post["oid"]);

		}else{
			$np_response = $this->readStatusWithApi($this->request->get["order_id"]);
			$order_id    = $this->request->get["order_id"];
		}

		if(!$order_id){
			$this->session->data['error'] = $this->language->get('payment_holestnestpay_transaction_failed') . " " . $this->language->get('payment_holestnestpay_transaction_failed_no_reposonse');
			$this->response->redirect($this->url->link('checkout/checkout', '', true));
			return;
		}
		
		$this->session->data['orderid']  = $order_id;
        $this->session->data['order_id'] = $order_id;
		

		$failed_status_id = $this->module_param('payment_holestnestpay_order_failed');

		$order_info = $this->model_checkout_order->getOrder($order_id);
		
		$already_proccessed = false;
		
		if(isset($np_response["TransId"])){
			if($np_response["TransId"]){
				$transId = $np_response["TransId"];
				$res = $this->db->query("SELECT order_id FROM " . DB_PREFIX . "order_history WHERE order_id = {$order_id} AND comment LIKE '%{$transId}%'");
				if($res){
					if(isset($res->rows)){
						if(!empty($res->rows)){
							$already_proccessed = true;
						}
					}
				}
			}
		}
		
		$np_response = $this->cleanResponse($np_response);

		$html_resp = $this->responseToHtml($np_response,

													   $this->language->get('payment_holestnestpay_transaction_failed'),

													   "red",	

													   $this->language->get('payment_holestnestpay_transaction_details'));

		$notified = false;
		
		
		
		if(!$already_proccessed && $order_info["email"] && $this->module_param('payment_holestnestpay_no_transaction_email') != "yes"){											   

			$mail = new Mail();

			$mail->protocol = $this->module_param('config_mail_protocol');

			$mail->parameter = $this->module_param('config_mail_parameter');

			$mail->smtp_hostname = $this->module_param('config_mail_smtp_hostname');

			$mail->smtp_username = $this->module_param('config_mail_smtp_username');

			$mail->smtp_password = html_entity_decode($this->module_param('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');

			$mail->smtp_port = $this->module_param('config_mail_smtp_port');

			$mail->smtp_timeout = $this->module_param('config_mail_smtp_timeout');



			$mail->setTo($order_info['email']);

			$mail->setFrom($this->module_param('config_email'));

			$mail->setSender(html_entity_decode($order_info['store_name'], ENT_QUOTES, 'UTF-8'));

			$mail->setSubject(html_entity_decode($this->language->get('payment_holestnestpay_transaction_details') . " " . $this->language->get('payment_holestnestpay_transaction_failed') , ENT_QUOTES, 'UTF-8'));

			$html_resp .= $this->makeInfo($order_id, $order_info);

			$mhtml = "<p>&nbsp;</p>" .$html_resp. "<p>&nbsp;</p>" . "<p>" . $order_info['store_name'] . " " . date("d.m.Y")."</p>";

			$mail->setHtml($mhtml);

			$mail->setText(strip_tags(str_ireplace(array("</p>","</ul>"),"\n",$mhtml)));

			try{

				if($mail->send()){

					$notified = true;

				}

			}catch(Exception $ex){

				echo "<p class='error' style='color:red'>Error sending email<!-- " . $ex->getMessage() . " --></p>";		

			}catch(Throwable $tx){

				echo "<p class='error' style='color:red'>Error sending email<!-- " . $ex->getMessage() . " --></p>";	

			}	

		}

		if(!$already_proccessed)
			$this->addOrderHistory($order_id, $failed_status_id, $this->language->get('payment_holestnestpay_transaction_details') . "\n" . json_encode($np_response), $notified);

		if($order_id){
			$query = $this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = {$failed_status_id} WHERE order_id = {$order_id}");
			try{
				$querypm = $this->db->query("UPDATE " . DB_PREFIX . "order SET 
					payment_method = '" . $this->module_param('payment_holestnestpay_title') . "',
					payment_code   = 'holestnestpay'
				WHERE order_id = {$order_id}");
			}catch(Throwable $ex){
				
			}
		}

		?><!DOCTYPE html>

		<html>

			<head>

				<title><?php echo  $this->language->get('payment_holestnestpay_transaction_details'); ?></title>

				<script src="<?php echo $this->module_param('config_url'); ?>extension/holestnestpay/catalog/view/javascript/holestnestpay.js" type="text/javascript"></script>

				<link rel="stylesheet" href="<?php echo $this->module_param('config_url'); ?>extension/holestnestpay/catalog/view/template/payment/stylesheet/holestnestpay.css"> 

			</head>

			<body class="failure">

			    <h1><?php echo $this->module_param('config_name'); ?></h1>

				<h3><?php echo  $this->language->get('payment_holestnestpay_transaction_details'); ?></h3>

				<div>

					<?php
					echo $html_resp;

					?>
					<div class="order_details">
						<?php
						echo $this->makeInfo($order_id, $order_info);
						?>
					</div>
				</div>

				<p style="margin-bottom:150px;">

					<a href="<?php echo $this->module_param('config_url'); ?>index.php?route=checkout/failure"><?php echo  $this->language->get('payment_holestnestpay_proceed'); ?></a>

				</p>
			</body>

		</html>

		<?php

		die;

		//$this->response->redirect($this->url->link('checkout/failure', '', true));

		

	}

}	