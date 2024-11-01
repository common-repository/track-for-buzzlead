<?php
/**
 * Plugin Name: Tracker Buzzlead para Woocommerce by Webgoias
 * Plugin URI: https://www.webgoias.com.br/produto/tracker-buzzlead-para-woocommerce/
 * Description: Marketing de Indicações Buzzlead - Adicione o tracking de conversão Buzzlead (www.buzzlead.com.br) para sua loja virtual Woocommerce e converta mais ! 
 * Version: 2.2.4
 * Text Domain: wbg-buzzlead-tracker
 * Domain Path: /languages
 * License: GPLv3 or later
 * Author: Rodrigo Fleury Bastos - Webgoias
 * Author URI: http://www.webgoias.com.br
 */
 
 
 if (! defined('ABSPATH'))
{
    exit;
}


if ( !function_exists( 'deactivate_plugins' ) ) { 
    require_once ABSPATH . '/wp-admin/includes/plugin.php'; 
} 

define('WBG_BUZZLEAD_TRACK_PATH', plugin_dir_path(__FILE__));
define('WBG_BUZZLEAD_TRACK_URL', plugin_dir_url(__FILE__));
define('WBG_BUZZLEAD_TRACK_URL_IMAGES', plugin_dir_url(__FILE__)."images");
define('WBG_BUZZLEAD_TRACK_PATH_IMAGES', plugin_dir_url(__FILE__)."images");
define('WBG_BUZZLEAD_TRACK_VERSION', '1.0.0');
define('WBG_BUZZLEAD_TRACK_SLUG', 'wbg_buzzlead_conversion_tracking');



//validando PHP 7.2
if ( version_compare( PHP_VERSION, '7.2.0', '>=' ) ) { 
	//Validando mbstring
	$user_id = get_current_user_id();
	if(extension_loaded('mbstring')!=1) {
		add_action( 'admin_notices', 'wbg_mbstring_validate_false' );
		deactivate_plugins( '/track-for-buzzlead/track-for-buzzlead.php' );
	}
} else {
	add_action( 'admin_notices', 'wbg_php_version_validate' );
	deactivate_plugins( '/track-for-buzzlead/track-for-buzzlead.php' );
	
}
function wbg_notice_dismiss() {
    $user_id = get_current_user_id();
    if ( isset( $_GET['wbg_buzzlead_tracker_dismissed'] ) ) {
        add_user_meta( $user_id, 'wbg_notice_dismiss', 'true', true );
	}
}
add_action( 'admin_init', 'wbg_notice_dismiss' );

function wbg_mbstring_validate_false (){
    ?>
    <div class="error notice is-dismissible">
        <p><?php _e( 'A extensão MBSTRING é necessária para o funcionamento do plugin!', 'wbg-buzzlead-tracker' ); ?></p>
    </div>
    <?php
}

function wbg_php_version_validate (){
    ?>
    <div class="error notice is-dismissible">
        <p><?php _e( 'Por motivos de segurança a versão do PHP mínima requerida é 7.2!', 'wbg-buzzlead-tracker' ); ?></p>
    </div>
    <?php
}


use Carbon_Fields\Container;
use Carbon_Fields\Field;

add_action( 'carbon_fields_register_fields', 'wbg_buzzlead_theme_options' );
function wbg_buzzlead_theme_options() {
	
	$html = 'Preencha abaixo com o ID da sua campanha (Não sabe? Envie um email para <a href="mailto:contato@buzzlead.com.br">contato@buzzlead.com.br)</a><br> Ao preencher o código abaixo o tracker já irá começar a funcionar.</a></p>';
    Container::make( 'theme_options', __( 'Buzzlead', 'crb' ) )
		->set_page_menu_position( 2		 )
		->set_icon( WBG_BUZZLEAD_TRACK_URL_IMAGES.'/icone.png' )
        ->add_fields( array(
			Field::make( 'html', 'crb_information_text' )->set_html( '<center><img src="'.WBG_BUZZLEAD_TRACK_URL_IMAGES.'/logo.svg'.'"></center><br/><h1>Buzzlead Tracker</h2><p>'.$html.'</p>' ),
            Field::make( 'text', 'wbg_id_campanha', 'ID da Campanha' )->set_required( true ),
        ) );
}

add_action( 'after_setup_theme', 'wbg_buzzlead_load' );
function wbg_buzzlead_load() {
    require_once( 'vendor/autoload.php' );
    \Carbon_Fields\Carbon_Fields::boot();
}

########## WOOCOMMERCE #############
add_action( 'carbon_fields_fields_registered', 'wbg_buzzlead_value_avail' );
function wbg_buzzlead_value_avail() {
	$idCampanha = carbon_get_theme_option( 'wbg_id_campanha' );
	//Se a campanha for diferente de vazio
	if(carbon_get_theme_option( 'wbg_id_campanha' ) != "") {
		add_action( 'woocommerce_thankyou', 'wbg_buzzlead_conversion_tracking' );
		function wbg_buzzlead_conversion_tracking($order_id) {
			$order = wc_get_order( $order_id );
			$nomeCliente = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
			if ( ! function_exists( 'is_plugin_active' ) ) 
			{
		 
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
					if( is_plugin_active( 'woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php' ) ) 
					{
						$cpfCliente = get_post_meta($order_id, '_billing_cpf', true );
						$cnpjCliente = get_post_meta($order_id, '_billing_cnpj', true );
					}
			}
			//Adicionando os Scripts na pagina de sucesso
			
			echo '<div id="buzzlead-root"></div>';
			wp_enqueue_script( 'wbg-buzzlead-widget', 'https://app.buzzlead.com.br/widget-v2.js', array(), '1.0' );
			wp_add_inline_script( 'wbg-buzzlead-widget', 'window.campaignId = "'.carbon_get_theme_option( 'wbg_id_campanha' ).'";' );
			
			wp_register_script( 'wbg_buzzlead_tracker', '', [], '', true );
			wp_enqueue_script( 'wbg_buzzlead_tracker'  );
			wp_add_inline_script( 'wbg_buzzlead_tracker', 'console.log("Carregando o Tracker na pagina de sucesso!");' );
			
			wp_add_inline_script( 'wbg_buzzlead_tracker',
				'window.Tracker({
					campaignId:"'.carbon_get_theme_option( 'wbg_id_campanha' ).'",
					total:"'.$order->get_total().'",
					numeroPedido:"'.$order_id.'",
					cliente:{
						nome:"'.$nomeCliente.'",
						email:"'.$order->get_billing_email().'",
						documento:"'.$cpfCliente.$cnpjCliente.'"
					}
				});'
			);
			wp_add_inline_script( 'wbg_buzzlead_tracker', 'console.log("Carregado!");' );
			
		}
		
		add_action('wp_head', 'wbg_buzzlead_tracker_head');
		function wbg_buzzlead_tracker_head() {
			wp_enqueue_script( 'wbg-buzzlead-head-tracker', 'https://app.buzzlead.com.br/tracker.js', array(), '1.2' );
			wp_add_inline_script( 'wbg-buzzlead-head-tracker', 'console.log("Campanha Cadastrada: '. carbon_get_theme_option( 'wbg_id_campanha' ).'");' );
			wp_add_inline_script( 'wbg-buzzlead-head-tracker', 'console.log("Buzzlead Tracker Carregado!");' );
		}
		
	}
}

?>