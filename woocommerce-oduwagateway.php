<?php
/*
Plugin Name: Cryptocurrency Oduwa Gateway for WooCommerce
Plugin URI: https://www.yourpluginuri.com
Description: Cryptocurrency Gateway for WooCommerce
Version: 1.0
Author: ODUWAX
Text Domain: oduwagateway
Author URI: https://profiles.wordpress.org/yourauthoruri
*/
define("ROWPG_FILE_URL", plugin_dir_url(__FILE__));
include_once('PaymentGateway.php');
add_action('plugins_loaded', 'woocommerce_rain_init', 0);
function woocommerce_rain_init() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return; 
    load_plugin_textdomain('wc-oduwagateway', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
					
    // Gateway class
    class WC_OduwaGateway extends WC_Payment_Gateway {
    	protected $spmsg = array();
        public function __construct(){
            $this -> id = 'oduwagateway';
            $this -> method_title = __('OduwaGateway', 'oduwagateway');
            $this -> icon = '';
            $this -> has_fields = false;
            $this -> init_form_fields();
            $this -> init_settings();
            $this -> title = $this -> settings['title'];
            $this -> description = $this -> settings['description'];
			$this -> method_description = 'OduwaGateway Gateway for Woocommerce (using  <a href="https://www.oduwagateway.com/">Oduwa Gateway</a>) allows you to accept cryptocurrency payments on your WooCommerce store';
            $this -> trade_id = $this -> settings['trade_id'];
            $this -> box_name = $this -> settings['box_name'];
			$this -> public_key = $this -> settings['public_key'];
			$this -> private_key = $this -> settings['private_key'];			
			$this -> environment = $this -> settings['environment'];
			$this -> iframe_width = empty($this -> settings['iframe_width']) ? '500' : $this -> settings['iframe_width'];
			$this -> currency_code = get_woocommerce_currency();
            $this -> spmsg['message'] = "";
            $this -> spmsg['class'] = "";

            add_action('valid-oduwagateway-request', array(&$this, 'successful_request'));				
          	if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
             } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            } 		
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_oduwagateway_response' ) );	
			
        }
        function init_form_fields(){	
			$env_list =  array('test'=>'Test', 'live' => 'Live');	
            $this -> form_fields = array(

                'enabled' => array(
                    'title' => __('Enable/Disable', 'oduwagateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable OduwaGateway Payment Module.', 'oduwagateway'),
                    'default' => 'no'),

                'title' => array(
                    'title' => __('Title:', 'oduwagateway'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'oduwagateway'),
                    'default' => __('OduwaGateway', 'oduwagateway')),

                'description' => array(
                    'title' => __('Description:', 'oduwagateway'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'oduwagateway'),
                    'default' => __('Pay securely by OduwaGateway.', 'oduwagateway')),				

				'trade_id' => array(
                    'title' => __('Box ID', 'oduwagateway'),
                    'type' => 'text',
                    'description' => __('Enter Box ID Given by OduwaGateway')),
					
				'box_name' => array(
					'title' => __('Box Name', 'oduwagateway'),
					'type' => 'text',
					'description' => __('Enter Box Name Given by OduwaGateway')),
					
				'public_key' => array(
					'title' => __('Public Key', 'oduwagateway'),
					'type' => 'text',
					'description' => __('Enter Public Key Given by OduwaGateway')),
				
				'private_key' => array(
					'title' => __('Private Key', 'oduwagateway'),
					'type' => 'text',
					'description' => __('Enter Private Key Given by OduwaGateway')),
				
				'environment' => array(
					'title'       => __( 'Environment', 'oduwagateway' ),
					'type'        => 'select',
					'default'     => 'test',										
					'description' => '',
					'options'     => $env_list,
				),

				'new_status' => array(
					'title'       => __( 'New status', 'oduwagateway' ),
					'type'        => 'select',
					'default'     => 'wc-pending',										
					'description' => '',
					'options'     => wc_get_order_statuses(),
				),
				
				'payment_confirm' => array(
					'title'       => __( 'Confirmed status', 'oduwagateway' ),
					'type'        => 'select',
					'default'     => 'wc-processing',										
					'description' => '',
					'options'     => wc_get_order_statuses(),
				),			
								
				'payment_unrecognized' => array(
					'title'       => __( 'Unrecognized status', 'oduwagateway' ),
					'type'        => 'select',
					'default'     => 'wc-failed',										
					'description' => '',
					'options'     => wc_get_order_statuses(),
				),
				
				'iframe_width' => array(
					'title' => __('Iframe Width', 'oduwagateway'),
					'type' => 'text',
					'description' => __('Enter iframe width without PX')
				),
           );
        }
        // Admin Panel Options
        // 	- Options for bits like 'title' and availability on a country-by-country basis
        public function admin_options(){
            echo '<h3>'.__('Oduwa Payment Gateway', 'oduwagateway').'</h3>';
            echo '<p>'.__('Oduwa is most popular payment gateway').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';
        }
        function payment_fields(){
			if($this -> description) echo wpautop(wptexturize($this -> description));
        }
		
        // Process the payment and return the result
        function process_payment($order_id){

			global $woocommerce;

            $order 		= new WC_Order($order_id);			
			$amount 	= $order->calculate_totals();						
			$userID 	=   $order->get_user_id();

            $options = array(
                'public_key'=> $this->public_key,    // your payment gateway public key
                'private_key'=> $this->private_key,   // your payment gateway private key
                'boxID'=> $this->trade_id,         // your payment gateway box id
                'coinLabel'=> $this->box_name,  // coin name(OWC)
                'userID'=> $userID,   // user Id of your site
                'orderID'=> $order_id, // order id of your site
                'coinAmount'=> 0,   // in double/dacimal value
                'usdAmount'=> $amount,   // in float/integer value
                'boxwidth'=> $this->iframe_width,   // in px
                'iframeID'=> '',      //optional(autogenerate if not given)
                'cookieName'=> '',    // your cookie name for store basic data
            );
			$data = base64_encode(serialize($options));
			return array('result' => 'success', 'redirect' => ''.get_bloginfo('wpurl').'/?payment='.$data.'');		
        }
		//call back	
        function check_oduwagateway_response(){
			global $woocommerce, $wpdb;
			$resStatus = false;  //status for check your response is valid or not
			$key = '';
			if($_GET['wc-api'] == get_class( $this )){
				if($_SERVER['REQUEST_METHOD'] === 'POST'){
					if($_POST['status']){  //it's identify that status and other data set or not
						$tempFile = dirname(__FILE__).'/logs/check-payment.log';
    					file_put_contents($tempFile, 'Check Payment Status '.$_POST['status'].' '.date('Y-m-d H:i:s').PHP_EOL, FILE_APPEND);
						$order_id 	= $_POST['orderid'];				   
						$order 		= new WC_Order($order_id);
						$notifyURL  = add_query_arg( 'wc-api', get_class( $this ), home_url('/') );
						$notifyURL  = add_query_arg(array('orderid' => $order_id), $notifyURL);
						$returnURL  = $this->get_return_url( $order );
						$isExist 	= "FIRE_YOUR_DATABASE_QUERY_HERE"; // get record from database where DB_OrderId==$_POST['orderid'] and DB_UserId==$_POST['userid']
						$check_id  	= $order->get_id();
						$userID 	= $order->get_user_id();

						if ($isExist && $check_id == $_POST['orderid'] && $userID == $_POST['userid']) {
							$public_key		= $this->public_key; 				// Public Key
							$private_key	= $this->private_key; 				// Private Key
							
							$key 		= md5(($public_key."".$private_key."".$_POST['orderid'])); // generate key using md5 mehod with combination of public key, private key and order id
							$resStatus 	= true; //set status as true for pass in response
				
							if($_POST['status']=="payment_confirm" && $_POST['confirmed']==0){
								// payment detected but unconfirm transaction			   
				   				$status 	= $this->settings['new_status'];
								$order->update_status($status, __('Payment Status - '.$_POST['status'], 'oduwagateway'));
							}else if($_POST['status']=="payment_confirm" && $_POST['confirmed'] == 1){
								// payment verified successfully
								$status 	= $this->settings[$_POST['status']];
								$order->update_status($status, __('Payment Status - '.$_POST['status'], 'oduwagateway'));
							}else if($_POST['status']=="payment_unrecognized"){
								// payment detected but amount not matched
								$status 	= $this->settings[$_POST['status']];
								$order->update_status($status, __('Payment Status - '.$_POST['status'], 'oduwagateway'));
							}
						}
					}
					$tempFile = dirname(__FILE__).'/logs/check-payment.log';
    				file_put_contents($tempFile, 'Check Response '.json_encode(array("status"=>$resStatus,"hash"=>$key)).date('Y-m-d H:i:s').PHP_EOL, FILE_APPEND);

				}
			}
			// send key and status in response to payment gateway for verification
			echo json_encode(array("status"=>$resStatus,"hash"=>$key));
			exit;
        }
	}
    // Add the Gateway to WooCommerce
    function woocommerce_add_oduwagateway_gateway($methods) {
        $methods[] = 'WC_OduwaGateway';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_oduwagateway_gateway' );
	add_filter('plugin_action_links', 'oduwagateway_plugin_action_links', 10, 2);
    function oduwagateway_plugin_action_links($links, $file)
    {
        static $this_plugin;

        if (false === isset($this_plugin) || true === empty($this_plugin)) {
            $this_plugin = plugin_basename(__FILE__);
        }

        if ($file == $this_plugin) {            
            $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=oduwagateway">Settings</a>';   array_unshift($links, $settings_link);
        }
        return $links;
    }
}
add_action('init','wpyog_add_rewrite_rules');
function wpyog_add_rewrite_rules(){
    add_rewrite_rule('^content/([^/]*)/([^/]*)/?','index.php?payment=data','top');
}
add_action('query_vars','wpyog_add_query_vars');
function wpyog_add_query_vars( $qvars ) {
    $qvars[] = 'payment';
    return $qvars;
}
add_action('template_redirect','wpyog_template_redirect');
function wpyog_template_redirect(){
   if( get_query_var('payment') ) 
  	{
		get_header();
		// Initialise Payment Class
		$payment 				= unserialize(base64_decode(get_query_var('payment')));
		$PaymentGateway 		= new PaymentGateway($payment);
		echo $payment_status 	= $PaymentGateway->loadPaymentBox();
		get_footer();
		exit;
    }
}