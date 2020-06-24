<?php

class WC_universalpay_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		
		$this->id				= 'universalpay';
		$this->icon 			= apply_filters('woocommerce_universalpay_icon', plugin_dir_url( __FILE__ ) . '/tarjetas.png');
		$this->has_fields 		= false;
		$this->method_title     = __( 'universalpay', "universalpay_gw_woo" );

		$this->init_form_fields();
		$this->init_settings();

		$this->title 			= apply_filters( 'woouniversalpay_title', $this->get_option( 'title' ) );
		$this->description      = apply_filters( 'woouniversalpay_description', $this->get_option( 'description' ) );
		
		$this->commerce 		= $this->get_option( 'commerce' );
		$this->key 				= $this->get_option( 'key' );
		$this->url				= $this->get_option( 'url' );
		$this->test 			= $this->get_option( 'test' );
		$this->merchantName 	= $this->get_option( 'merchantName' );
		$this->owner 			= $this->get_option( 'owner' );
		$this->limporte			= $this->get_option( 'limporte' );
		$this->lconcepto		= $this->get_option( 'lconcepto' );
		$this->fondoboton		= $this->get_option( 'fondoboton' );
		$this->textoboton		= $this->get_option( 'textoboton' );
		$this->fondoframe		= $this->get_option( 'fondoframe' );
		$this->mensaje			= $this->get_option( 'mensaje' );
		$this->nombre			= $this->get_option( 'nombre' );
		$this->p1c              = $this->get_option( 'p1c' );
		$this->p1ctext          = $this->get_option( 'p1ctext' ) ;
		$this->p1curl           = $this->get_option( 'p1curl' );
		$this->debug			= $this->settings['debug'];
		
		if ( 'yes' == $this->debug ) {
					if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
						$this->log = $woocommerce->logger();
					} else {
						$this->log =  new WC_Logger();
					}
		}
		
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
			add_action( 'init', array( $this, 'universalpay_ipn_response' ) );
			add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		}else{
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'universalpay_ipn_response' ) );
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
		
		add_action( 'woocommerce_receipt_universalpay', array( $this, 'receipt_page' ) );
		
	}

	function universalpay_ipn_response(){
		global $woocommerce;
		header("Content-type: text/plain");
		if ( 'yes' == $this->debug ) {
			$this->log->add( 'universalpay', 'Checking notification is valid...' );
		}
		if ( !empty( $_REQUEST ) ) {
						$json = file_get_contents('php://input') ;
						$array = json_decode($json);
					if ( !empty($array))
					{
						if ( $array->PAYMENT_CODE == '000' or $array->PAYMENT_CODE == '010') {
							
							@ob_clean();
				
							if ( 'yes' == $this->debug ) {
								$this->log->add( 'universalpay', 'Received data: ' . print_r($array, true) );
							}
							
							$received_signature	= $array->SIGNATURE;
							
							$data = array (
								"MERCHANT_IDENTIFIER" => $array->MERCHANT_IDENTIFIER,
								"AMOUNT" => $array->PAYMENT_AMOUNT,
								"MERCHANT_OPERATION" => $array->MERCHANT_OPERATION,
								"PAYMENT_OPERATION" => $array->PAYMENT_OPERATION,
								"PAYMENT_CHANNEL" => $array->PAYMENT_CHANNEL,
								"PAYMENT_DATE" => $array->PAYMENT_DATE,
								"PAYMENT_CODE" => $array->PAYMENT_CODE,
								"CARDHOLDER" => $ARRAY->CARDHOLDER,
								"PARAMS" => $array->PARAMS,
							);
							$clave = $this->key;
							$firma = hash ( "sha256", $data ["MERCHANT_IDENTIFIER"] . $data ["AMOUNT"] . $data ["MERCHANT_OPERATION"] . $data ["PAYMENT_OPERATION"] . $data ["PAYMENT_CHANNEL"] . $data["PAYMENT_DATE"] . $data["PAYMENT_CODE"] . $clave, FALSE );
							if ($firma == $received_signature)
							{
								$order_id = substr( $array->MERCHANT_OPERATION, 0, 8 );
								$order = new WC_Order( $order_id );
								if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '<' ) ) {
									if ( $order->status == 'processing' )
										exit;
								}
								else
								{
									
								}
								$virtual_order = null;
					 
								if ( count( $order->get_items() ) > 0 ) {
									foreach( $order->get_items() as $item ) {
										if ( 'line_item' == $item['type'] ) {
											$_product = $order->get_product_from_item( $item );

											if ( ! $_product->is_virtual() ) {
												$virtual_order = false;
												break;
											} else {
												$virtual_order = true;
											}	
										}
									}
								}
								$order->update_status( 'processing' );
								if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '<' ) ) {
									$order->reduce_order_stock();
								}
								else
								{
									wc_reduce_stock_levels($order_id);
								}
								$woocommerce->cart->empty_cart();
								$order->add_order_note( sprintf( __( 'Pedido completo, codigo %s', "universalpay_gw_woo" ), $array->MERCHANT_OPERATION ) );
								$order->add_order_note( sprintf( __( 'Método de pago %s', "universalpay_gw_woo" ), $array->PAYMENT_CHANNEL ) );
								if ($data ["CARDHOLDER"] <> '')
								{
									$order->add_order_note( sprintf( __( 'titular tarjeta %s', "universalpay_gw_woo" ), $array->CARDHOLDER ) );
								}
								die('OK');
							}
						}
						else{
							$order_id = substr( $array->MERCHANT_OPERATION, 0, 8 );
							$order = new WC_Order( $order_id );
							$order->update_status('cancelled');
							$order->add_order_note( sprintf( __( 'Payment error, code %s', "universalpay_gw_woo" ), $array->PAYMENT_CODE ) );
							if ( 'yes' == $this->debug ) {
								$this->log->add( 'universalpay', 'Received data: ' . print_r($array, true) );
							}
							die('OK');
						};
					}
					else
					{
						if ( 'yes' == $this->debug ) {
							$this->log->add( 'universalpay', 'datos recibido array vacio: ' . print_r($array, true) );
						}
						die('OK');
					}
		}
		else
		{
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'universalpay', 'datos recibido vacio: ' . print_r($array, true) );
			}
			die('KO');
		}
	}
	
	function init_form_fields() {

		$this->form_fields = array(
				'enabled' => array(
						'title' => __( 'Enable/Disable', "universalpay_gw_woo" ),
						'type' => 'checkbox',
						'label' => __( 'Enable universalpay', "universalpay_gw_woo" ),
						'default' => 'yes'
				),
				'title' => array(
						'title' => __( 'Title', "universalpay_gw_woo" ),
						'type' => 'text',
						'description' => __( 'This title is showed in checkout process.', "universalpay_gw_woo" ),
						'default' => __( 'universalpay', "universalpay_gw_woo" ),
						'desc_tip'      => true,
				),
				'description' => array(
						'title' => __( 'Description', "universalpay_gw_woo" ),
						'type' => 'textarea',
						'description' => __( 'Descripción del método de pago. Información para los usuarios sobre las opciones de pago.', "universalpay_gw_woo" ),
						'default' => __( 'Pago con tarjeta', "universalpay_gw_woo" )
				),
				'owner' => array(
						'title' => __( 'Owner', "universalpay_gw_woo" ),
						'type' => 'text',
						'default' => ''
				),
				'merchantName' => array(
						'title' => __( 'Trade name', "universalpay_gw_woo" ),
						'type' => 'text',
						'default' => ''
				),
				'commerce' => array(
						'title' => __( 'Trade number', "universalpay_gw_woo" ),
						'type' => 'text',
						'default' => ''
				),
				'key' => array(
						'title' => __( 'Secret key', "universalpay_gw_woo" ),
						'type' => 'text',
						'description' => __('Clave de cifrado.', "universalpay_gw_woo" ),
						'default' => ''
				),
				'test' => array(
						'title' => __( 'Test Mode', "universalpay_gw_woo" ),
						'type' => 'checkbox',
						'label' => __( 'Habilitar universalpay en modo test.', "universalpay_gw_woo" ),
						'default' => 'yes'
				),
				'fondoboton' => array(
						'title' => __( 'Color Fondo Boton', "universalpay_gw_woo"),
						'type' => 'text',
						'label' => __( 'Color para el fondo del boton de pago', "universalpay_gw_woo"),
						'default' => '1E315A'
				),
				'textoboton' => array(
						'title' => __( 'Color Texto Boton', "universalpay_gw_woo"),
						'type' => 'text',
						'label' => __( 'Color para el texto del boton de pago', "universalpay_gw_woo"),
						'default' => 'ffffff'
				),
				'fondoframe' => array(
						'title' => __( 'Color fondo iframe', "universalpay_gw_woo"),
						'type' => 'text',
						'label' => __( 'Color para el fondo del iframe', "universalpay_gw_woo"),
						'default' => ''
				),
				'mensaje' => array(
						'title' => __( 'Mensaje métodos de pago', "universalpay_gw_woo"),
						'type' => 'text',
						'label' => __( 'Mensaje que aparecerá encima de los métodos de pago', "universalpay_gw_woo"),
						'default' => 'Seleccione su método de pago'
				),
				'nombre' => array(
						'title' => __( 'Nombre del titular de la tarjeta', "universalpay_gw_woo"),
						'type' => 'checkbox',
						'label' => __( 'Indica si queremos pedir el nombre del titular de la tarjeta', "universalpay_gw_woo"),
						'default' => 'no'
				),
				'p1c' => array(
						'title' => __( 'Habilitar pago en 1 click', "universalpay_gw_woo"),
						'type' => 'checkbox',
						'label' => __( 'Indica si queremos activar la opción de pago en 1 click, solo usuarios registrados', "universalpay_gw_woo"),
						'default' => 'no'
				),
				'p1ctext' => array(
						'title' => __( 'url aceptación pago en 1 click', "universalpay_gw_woo"),
						'type' => 'text',
						'label' => __( 'url donde el cliente podrá ver las condiciones legales para guardar la tarjeta', "universalpay_gw_woo"),
						'default' => 'Deseo guardar mi tarjeta para futuros pagos'
				),
				'p1curl' => array(
						'title' => __( 'url aceptación pago en 1 click', "universalpay_gw_woo"),
						'type' => 'text',
						'label' => __( 'Texto que aparecerá para aceptar guardar la tarjeta', "universalpay_gw_woo"),
						'default' => 'https://'
				),
				'debug' => array(
						'title' => __( 'Debug Log', 'universalpay_gw_woo' ),
						'type' => 'checkbox',
						'label' => __( 'Enable logging', 'universalpay_gw_woo' ),
						'default' => 'no',
						'description' => sprintf( __( 'Log UniversalPay events, inside %s', 'universalpay_gw_woo' ), wc_get_log_file_path( 'universalpay' ) ),
				)
		);
	}

	public function admin_options() {
		?>
		<h3><?php _e( 'universalpay Payment', "universalpay_gw_woo" ); ?></h3>
		<p><?php _e('Allows universalpay card payments.', "universalpay_gw_woo" ); ?></p>
		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table>

		<script>
		jQuery( document ).ready( function( $ ){
			$.validator.addMethod("requiredIfChecked", function (val, ele, arg) {
			    if ($("#woocommerce_universalpay_enabled").is(":checked") && ($.trim(val) == '')) { return false; }
			    return true;
			}, "This field is required if gateway is enabled");


			$("#mainform").validate({
				rules: {
					woocommerce_universalpay_title: "requiredIfChecked",
					woocommerce_universalpay_description: "requiredIfChecked",
					woocommerce_universalpay_owner: "requiredIfChecked",
					woocommerce_universalpay_merchantName: "requiredIfChecked",
					woocommerce_universalpay_commerce: "requiredIfChecked",
					woocommerce_universalpay_key: "requiredIfChecked"
				},

				messages: {
					woocommerce_universalpay_title: "<?php _e( 'You must fill out a title for this gateway', 'universalpay_gw_woo' ); ?>",
					woocommerce_universalpay_description: "<?php _e( 'You must fill out a description for this gateway', 'universalpay_gw_woo' ); ?>",
					woocommerce_universalpay_owner: "<?php _e( 'You must fill out who is the owner', 'universalpay_gw_woo' ); ?>",
					woocommerce_universalpay_merchantName: "<?php _e( 'You must fill out the merchant name', 'universalpay_gw_woo' ); ?>",
					woocommerce_universalpay_commerce: "<?php _e( 'You must fill out the merchant number', 'universalpay_gw_woo' ); ?>",
					woocommerce_universalpay_key: "<?php _e( 'You must fill out the key', 'universalpay_gw_woo' ); ?>"
				}
			});			
		} )
		</script>
		<?php
	}

	function receipt_page( $order ) {
		echo '<p>'.__( $this->mensaje , "universalpay_gw_woo" ).'</p>';
		echo $this->generate_universalpay_form( $order );
	}
	

	function generate_universalpay_form( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );
        
		$servired_args = $this->prepare_args( $order );

		$Merchant_identifier = $servired_args['Ds_Merchant_MerchantCode']; 
		$importe=$servired_args['Ds_Merchant_Amount'];
		$operation=$servired_args['Ds_Merchant_Order'];
		$url_response=add_query_arg( 'wc-api', 'WC_universalpay_Gateway', home_url( '/' ) );
		$url_ok=$servired_args['Ds_Merchant_UrlOK'];
		$url_ko=$servired_args['Ds_Merchant_UrlKO'];
		$clave=$this->key;
		$description=$servired_args['Ds_Merchant_ProductDescription'];
		$this_currency=$servired_args['Ds_Merchant_Currency'];
		$fondoboton=$servired_args['fondoboton'];
		$textoboton=$servired_args['textoboton'];
		$fondoframe=$servired_args['fondoframe'];
		$limporte=$servired_args['limporte'];
		$lconcepto=$servired_args['lconcepto'];
		if ($servired_args['nombre'] == 'no'){
			$cardholder= 'false';
		} else {
			$cardholder= 'true';
		}
		$locale= strtoupper(substr (get_locale(), 0, 2));
		$p1c_text = "";
		$p1c_url = "";
		$activa1click ="";
		if ($servired_args['p1c'] = true)
		{
			if ($order->get_customer_id() <> 0)
			{
				$codcliente = $order->get_customer_id();
				$activa1click = "true";
				$p1c_text = $servired_args['p1ctext'];
				$p1c_url = $servired_args['p1curl'];
			}
			else
			{
				$codcliente = "";
				$activa1click = "false";
			}
		}
		else
		{
			$codcliente = "";
			$activa1click = "false";			
		}
		$params = array (
			 "STYLE_BACK_BOTON" => $fondoboton,
			 "STYLE_COLOR_BOTON" => $textoboton,
			 "STYLE_BACK_FRAME" => $fondoframe,
			 "LABEL_AMOUNT" => $limporte,
			 "LABEL_CONCEPT" => $lconcepto,
			 "AMOUNT_MAX" => $importe,
			 "AMOUNT_MIN" => $importe,
			 "PAYMENT_TYPE" => "PN",
			 "REQUIRE_CARDHOLDER" => $cardholder,
			 "TARGET" => "_parent",
			 "PERSONAL_IDENTITY_NUMBER" => $codcliente,
			 "P1C" => $activa1click,
			 "P1C_TEXT" => $p1c_text,
			 "P1C_LINK" => $p1c_url		 
	   );

	   $data = array (
			 "MERCHANT_IDENTIFIER" => $Merchant_identifier,
			 "AMOUNT" => $importe,
			 "OPERATION" => $operation,
			 "URL_RESPONSE" => $url_response,
			 "URL_OK" => $url_ok,
			 "URL_KO" => $url_ko,
			 "DESCRIPTION" => $description,
			 "LOCALE" => $locale,
			 "CURRENCY" => $servired_args['Ds_Merchant_Currency'],
			 "PARAMS" => $params 
	   );
   
		   $firma = hash ( "sha256", $data ["MERCHANT_IDENTIFIER"] . $data ["AMOUNT"] . $operation . $data ["URL_RESPONSE"] . $data ["URL_OK"] . $data ["URL_KO"] . $clave, FALSE );

		   $data ["SIGNATURE"] = $firma;
		   
		   $data_string = json_encode ( $data );
		if ( $this->test == 'yes' ) 
		{
		   $ch = curl_init ( 'https://test.imspagofacil.es/client2/token-pro' );
		}
		else
		{
			$ch = curl_init ( 'https://imspagofacil.es/client2/token-pro' );
		}
		   curl_setopt ( $ch, CURLOPT_CUSTOMREQUEST, "POST" );
		   curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data_string );
		   curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		   curl_setopt ( $ch, CURLOPT_HTTPHEADER, array (
				 'Content-Type: application/json',
				 'Content-Length: ' . strlen ( $data_string ) 
		   ) );
		   
		   $response = curl_exec ( $ch );
		   curl_close ( $ch );
		   
		   $result_string = json_decode ( $response, TRUE );
 if ( 'yes' == $this->debug ) {
				$this->log->add( 'universalpay', 'Datos resultado token: ' . print_r($result_string, true) );
				$this->log->add( 'universalpay', 'Datos enviados: ' . print_r($data, true) );
			}
		   $token = $result_string ["TOKEN"];
		   if ($token <> "")
		   {
?>
   
      <iframe id="framePago"
         style="width: 80%;margin-left:10%;height: 800px; border: none; text-align: center; padding: 0px;display:none"
         name="ventana" scrolling="no" sandbox="allow-same-origin allow-forms allow-top-navigation allow-scripts allow-popups"></iframe>
   
<?php
if ( $this->test == 'yes' ) {
?>
   <form action="https://test.imspagofacil.es/client2/load" name="pagoForm" target="ventana" method="post">
      <input type="hidden" name="TOKEN" value="<?php echo $token ?>" />
   </form>
<?php
}else{
?>
   <form action="https://imspagofacil.es/client2/load" name="pagoForm" target="ventana" method="post">
      <input type="hidden" name="TOKEN" value="<?php echo $token ?>" />
   </form>
<?php
}
?>
<input type='button' value="Pagar" name="Pagar" id="Pagar" onclick="return pagarPasarela()"/>
<script type="text/javascript">
pagarPasarela();
function pagarPasarela() {
   document.forms['pagoForm'].submit();
   document.getElementById("framePago").style.display = 'block';
   document.getElementById("Pagar").style.display = 'none';
   return false;
}
</script>

<?php
		   }
		   else{
			   echo "Existen problemas técnicos en este momento contacte con su proveedor<br>we have technical problems in this moment please contact with us";
		   }
	}

	function prepare_args( $order ) {
		global $woocommerce;

		$order_id = $order->get_order_number();
		$ds_order = str_pad($order->get_order_number(), 8, "0", STR_PAD_LEFT) . date('is');
		$curr = get_woocommerce_currency();
		$moneda = $this->isos_code_currency($curr);
		$message =  $order->get_total()*100 .
		$ds_order .
		$this->commerce .
		$moneda .
		$this->key;
			
		$signature = strtoupper(sha1($message));
		
		$products = '';
		if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>' ) ) {
			if ( is_array( $cart_contents = WC()->cart->cart_contents ) ) {
				foreach ( $cart_contents as $cart_content ) {
					if ( !empty( $products ) ) {
						$separator = '/';
					} else {
						$separator = '';
					}
					$products .= $separator . $cart_content['quantity'] . 'x' . $cart_content['data']->get_title();
				}
			}
		} else {
			$products = __('Online order', 'WC_universalpay_Gateway');
		}
		$vowels = array("+", "/", "\\","'","?","¿","!","¡");
		$products = str_replace($vowels, " " , $products);
		if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '>=' ) )
		{
			$args = array (
					'Ds_Merchant_MerchantCode'			=> $this->commerce,
					'Ds_Merchant_Terminal'				=> 1,
					'Ds_Merchant_Currency'				=> $moneda,
					'Ds_Merchant_MerchantURL'			=> add_query_arg( 'wc-api', 'WC_universalpay_Gateway', home_url( '/' ) ),
					'Ds_Merchant_TransactionType'		=> 0,
					'Ds_Merchant_MerchantSignature'		=> $signature,
					'Ds_Merchant_UrlKO'					=> apply_filters( 'woouniversalpay_param_urlKO', get_permalink( wc_get_page_id( 'checkout' ) ) ),
					'Ds_Merchant_UrlOK'					=> apply_filters( 'woouniversalpay_param_urlOK', $this->get_return_url( $order ) ),
					'Ds_Merchant_Titular'				=> $this->owner,
					'Ds_Merchant_MerchantName'			=> $this->merchantName,
					'Ds_Merchant_Amount'				=> round($order->get_total()*100),
					'Ds_Merchant_ProductDescription'	=> $products,
					'Ds_Merchant_Order'					=> $ds_order,
					'fondoboton'						=> $this->fondoboton,
					'textoboton'						=> $this->textoboton,
					'fondoframe'						=> $this->fondoframe,
					'nombre'							=> $this->nombre,
					'limporte'							=> $this->limporte,
					'lconcepto'							=> $this->lconcepto,
					'p1ctext'							=> $this->p1ctext,
					'p1curl'							=> $this->p1curl,
					'p1c'								=> $this->p1c,
				
			);
		}
		else
		{
			$args = array (
					'Ds_Merchant_MerchantCode'			=> $this->commerce,
					'Ds_Merchant_Terminal'				=> 1,
					'Ds_Merchant_Currency'				=> $moneda,
					'Ds_Merchant_MerchantURL'			=> add_query_arg( 'wc-api', 'WC_universalpay_Gateway', home_url( '/' ) ),
					'Ds_Merchant_TransactionType'		=> 0,
					'Ds_Merchant_MerchantSignature'		=> $signature,
					'Ds_Merchant_UrlKO'					=> apply_filters( 'woouniversalpay_param_urlKO', get_permalink( woocommerce_get_page_id( 'checkout' ) ) ),
					'Ds_Merchant_UrlOK'					=> apply_filters( 'woouniversalpay_param_urlOK', $this->get_return_url( $order ) ),
					'Ds_Merchant_Titular'				=> $this->owner,
					'Ds_Merchant_MerchantName'			=> $this->merchantName,
					'Ds_Merchant_Amount'				=> round($order->get_total()*100),
					'Ds_Merchant_ProductDescription'	=> $products,
					'Ds_Merchant_Order'					=> $ds_order,
					'fondoboton'						=> $this->fondoboton,
					'textoboton'						=> $this->textoboton,
					'fondoframe'						=> $this->fondoframe,
					'nombre'							=> $this->nombre,
					'limporte'							=> $this->limporte,
					'lconcepto'							=> $this->lconcepto,
					'p1ctext'							=> $this->p1ctext,
					'p1curl'							=> $this->p1curl,
					'p1c'								=> $this->p1c,
			);
		}
		if ( 'yes' == $this->debug ) {
				$this->log->add( 'universalpay', 'Datos resultado para token: ' . print_r($args, true) );
			}
		return $args;		
	}
	function generateResponseSignature( $key, $b64_data ) {
				$key = base64_decode( $key );
				$data_string = base64_decode( strtr( $b64_data, '-_', '+/' ) );
				$data = json_decode( $data_string, true);
				$key = $this->encrypt_3DES( $this->getOrderNotified( $data ), $key);
				$mac256 = $this->mac256( $b64_data, $key );
				return strtr( base64_encode( $mac256 ), '+/', '-_' );
	}
	function process_payment( $order_id ) {

    	$order = new WC_Order( $order_id );

    	if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
			$redirect_url = add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))));
		} else {
			$redirect_url = $order->get_checkout_payment_url( true );
		}
		return array(
					'result' 	=> 'success',
					'redirect'	=> $redirect_url
		);
    }
	
	function isos_code_currency( $pais)	{
		
	$codigo = array('AFN'=>'971',
	'EUR'=>'978',
	'ALL'=>'008',
	'DZD'=>'012',
	'USD'=>'840',
	'EUR'=>'978',
	'AOA'=>'973',
	'XCD'=>'951',
	'XCD'=>'951',
	'ARS'=>'032',
	'AMD'=>'051',
	'AWG'=>'533',
	'AUD'=>'036',
	'EUR'=>'978',
	'AZN'=>'944',
	'BSD'=>'044',
	'BHD'=>'048',
	'BDT'=>'050',
	'BBD'=>'052',
	'BYN'=>'933',
	'EUR'=>'978',
	'BZD'=>'084',
	'XOF'=>'952',
	'BMD'=>'060',
	'INR'=>'356',
	'BTN'=>'064',
	'BOB'=>'068',
	'BOV'=>'984',
	'USD'=>'840',
	'BAM'=>'977',
	'BWP'=>'072',
	'NOK'=>'578',
	'BRL'=>'986',
	'USD'=>'840',
	'BND'=>'096',
	'BGN'=>'975',
	'XOF'=>'952',
	'BIF'=>'108',
	'CVE'=>'132',
	'KHR'=>'116',
	'XAF'=>'950',
	'CAD'=>'124',
	'KYD'=>'136',
	'XAF'=>'950',
	'XAF'=>'950',
	'CLP'=>'152',
	'CLF'=>'990',
	'CNY'=>'156',
	'AUD'=>'036',
	'AUD'=>'036',
	'COP'=>'170',
	'COU'=>'970',
	'KMF'=>'174',
	'CDF'=>'976',
	'XAF'=>'950',
	'NZD'=>'554',
	'CRC'=>'188',
	'XOF'=>'952',
	'HRK'=>'191',
	'CUP'=>'192',
	'CUC'=>'931',
	'ANG'=>'532',
	'EUR'=>'978',
	'CZK'=>'203',
	'DKK'=>'208',
	'DJF'=>'262',
	'XCD'=>'951',
	'DOP'=>'214',
	'USD'=>'840',
	'EGP'=>'818',
	'SVC'=>'222',
	'USD'=>'840',
	'XAF'=>'950',
	'ERN'=>'232',
	'EUR'=>'978',
	'SZL'=>'748',
	'ETB'=>'230',
	'EUR'=>'978',
	'FKP'=>'238',
	'DKK'=>'208',
	'FJD'=>'242',
	'EUR'=>'978',
	'EUR'=>'978',
	'EUR'=>'978',
	'XPF'=>'953',
	'EUR'=>'978',
	'XAF'=>'950',
	'GMD'=>'270',
	'GEL'=>'981',
	'EUR'=>'978',
	'GHS'=>'936',
	'GIP'=>'292',
	'EUR'=>'978',
	'DKK'=>'208',
	'XCD'=>'951',
	'EUR'=>'978',
	'USD'=>'840',
	'GTQ'=>'320',
	'GBP'=>'826',
	'GNF'=>'324',
	'XOF'=>'952',
	'GYD'=>'328',
	'HTG'=>'332',
	'USD'=>'840',
	'AUD'=>'036',
	'EUR'=>'978',
	'HNL'=>'340',
	'HKD'=>'344',
	'HUF'=>'348',
	'ISK'=>'352',
	'INR'=>'356',
	'IDR'=>'360',
	'XDR'=>'960',
	'IRR'=>'364',
	'IQD'=>'368',
	'EUR'=>'978',
	'GBP'=>'826',
	'ILS'=>'376',
	'EUR'=>'978',
	'JMD'=>'388',
	'JPY'=>'392',
	'GBP'=>'826',
	'JOD'=>'400',
	'KZT'=>'398',
	'KES'=>'404',
	'AUD'=>'036',
	'KPW'=>'408',
	'KRW'=>'410',
	'KWD'=>'414',
	'KGS'=>'417',
	'LAK'=>'418',
	'EUR'=>'978',
	'LBP'=>'422',
	'LSL'=>'426',
	'ZAR'=>'710',
	'LRD'=>'430',
	'LYD'=>'434',
	'CHF'=>'756',
	'EUR'=>'978',
	'EUR'=>'978',
	'MOP'=>'446',
	'MKD'=>'807',
	'MGA'=>'969',
	'MWK'=>'454',
	'MYR'=>'458',
	'MVR'=>'462',
	'XOF'=>'952',
	'EUR'=>'978',
	'USD'=>'840',
	'EUR'=>'978',
	'MRU'=>'929',
	'MUR'=>'480',
	'EUR'=>'978',
	'XUA'=>'965',
	'MXN'=>'484',
	'MXV'=>'979',
	'USD'=>'840',
	'MDL'=>'498',
	'EUR'=>'978',
	'MNT'=>'496',
	'EUR'=>'978',
	'XCD'=>'951',
	'MAD'=>'504',
	'MZN'=>'943',
	'MMK'=>'104',
	'NAD'=>'516',
	'ZAR'=>'710',
	'AUD'=>'036',
	'NPR'=>'524',
	'EUR'=>'978',
	'XPF'=>'953',
	'NZD'=>'554',
	'NIO'=>'558',
	'XOF'=>'952',
	'NGN'=>'566',
	'NZD'=>'554',
	'AUD'=>'036',
	'USD'=>'840',
	'NOK'=>'578',
	'OMR'=>'512',
	'PKR'=>'586',
	'USD'=>'840',
	'PAB'=>'590',
	'USD'=>'840',
	'PGK'=>'598',
	'PYG'=>'600',
	'PEN'=>'604',
	'PHP'=>'608',
	'NZD'=>'554',
	'PLN'=>'985',
	'EUR'=>'978',
	'USD'=>'840',
	'QAR'=>'634',
	'EUR'=>'978',
	'RON'=>'946',
	'RUB'=>'643',
	'RWF'=>'646',
	'EUR'=>'978',
	'SHP'=>'654',
	'XCD'=>'951',
	'XCD'=>'951',
	'EUR'=>'978',
	'EUR'=>'978',
	'XCD'=>'951',
	'WST'=>'882',
	'EUR'=>'978',
	'STN'=>'930',
	'SAR'=>'682',
	'XOF'=>'952',
	'RSD'=>'941',
	'SCR'=>'690',
	'SLL'=>'694',
	'SGD'=>'702',
	'ANG'=>'532',
	'XSU'=>'994',
	'EUR'=>'978',
	'EUR'=>'978',
	'SBD'=>'090',
	'SOS'=>'706',
	'ZAR'=>'710',
	'SSP'=>'728',
	'EUR'=>'978',
	'LKR'=>'144',
	'SDG'=>'938',
	'SRD'=>'968',
	'NOK'=>'578',
	'SEK'=>'752',
	'CHF'=>'756',
	'CHE'=>'947',
	'CHW'=>'948',
	'SYP'=>'760',
	'TWD'=>'901',
	'TJS'=>'972',
	'TZS'=>'834',
	'THB'=>'764',
	'USD'=>'840',
	'XOF'=>'952',
	'NZD'=>'554',
	'TOP'=>'776',
	'TTD'=>'780',
	'TND'=>'788',
	'TRY'=>'949',
	'TMT'=>'934',
	'USD'=>'840',
	'AUD'=>'036',
	'UGX'=>'800',
	'UAH'=>'980',
	'AED'=>'784',
	'GBP'=>'826',
	'USD'=>'840',
	'USD'=>'840',
	'USN'=>'997',
	'UYU'=>'858',
	'UYI'=>'940',
	'UYW'=>'927',
	'UZS'=>'860',
	'VUV'=>'548',
	'VES'=>'928',
	'VND'=>'704',
	'USD'=>'840',
	'USD'=>'840',
	'XPF'=>'953',
	'MAD'=>'504',
	'YER'=>'886',
	'ZMW'=>'967',
	'ZWL'=>'932',
	'XBA'=>'955',
	'XBB'=>'956',
	'XBC'=>'957',
	'XBD'=>'958',
	'XTS'=>'963',
	'XXX'=>'999',
	'XAU'=>'959',
	'XPD'=>'964',
	'XPT'=>'962',
	'XAG'=>'961');

	foreach ($codigo as $k => $v) {
      if ($k == $pais) 
		  return $v;
		}
	} 
	


}
