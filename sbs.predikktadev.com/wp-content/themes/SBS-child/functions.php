<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'avada-stylesheet' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 20 );

// END ENQUEUE PARENT ACTION

// Our custom post type function
function create_posttype() {
 
    register_post_type( 'fence-hire',
    // CPT Options
        array(
            'labels' => array(
                'name' => __( 'fence-hire' ),
                'singular_name' => __( 'fence-hire' )
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'fence-hire'),
        )
    );
}
// Hooking up our function to theme setup
add_action( 'init', 'create_posttype' );
// Our custom post type function

 
    register_post_type( 'toilet-hire',
    // CPT Options
        array(
            'labels' => array(
                'name' => __( 'Toilet Hire' ),
                'singular_name' => __( 'Toilet Hire' )
            ),
            'public' => true,
            'has_archive' => true,
            'rewrite' => array('slug' => 'toilet-hire'),
        )
    );

function wpb_widgets_init() {
    register_sidebar( array(
        'name' => __( 'Toilets Sidebar', 'wpb' ),
        'id' => 'sidebar-1',
        'description' => __( 'The main sidebar appears on the right on Toilert Hire Post type except the front page template', 'wpb' ),
        'before_widget' => '<aside id="%1$s" class="widget %2$s">',
        'after_widget' => '</aside>',
        'before_title' => '<h3 class="widget-title">',
        'after_title' => '</h3>',
    ) );
    }
add_action( 'widgets_init', 'wpb_widgets_init' );


/* PHP Code on functions.php */
add_action( 'wp_ajax_getCheckoutPageContent', 'getCheckoutPageContentCallBack' );
add_action( 'wp_ajax_nopriv_getCheckoutPageContent', 'getCheckoutPageContentCallBack' );

function getCheckoutPageContentCallBack() {
    echo do_shortcode('[woocommerce_checkout]');
    die();
}

add_action( 'wp_ajax_getCartPageContent', 'getCartPageContentCallBack' );
add_action( 'wp_ajax_nopriv_getCartPageContent', 'getCartPageContentCallBack' );

function getCartPageContentCallBack() {
    echo do_shortcode('[woocommerce_cart]');
    die();
}


 function remove_item_from_cart() {
    $cart = WC()->instance()->cart;
    $id = $_POST['product_id'];
    $cart_id = $cart->generate_cart_id($id);
    $cart_item_id =  $_SESSION["cart_item_key_sbs"];

    if($cart_item_id){
       $cart->set_quantity($cart_item_id, 0);
       return true;
    } 
    return false;
    }

add_action('wp_ajax_remove_item_from_cart', 'remove_item_from_cart');
add_action('wp_ajax_nopriv_remove_item_from_cart', 'remove_item_from_cart');


// check for empty-cart get param to clear the cart
add_action( 'init', 'woocommerce_clear_cart_url' );
function woocommerce_clear_cart_url() {
  global $woocommerce;
	
	if ( isset( $_GET['empty-cart'] ) ) {
		$woocommerce->cart->empty_cart(); 
	}
}

/*Proceed to Checkout*/

remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 ); 
add_action('woocommerce_proceed_to_checkout', 'sm_woo_custom_checkout_button_text',20);

function sm_woo_custom_checkout_button_text() {
    $checkout_url = WC()->cart->get_checkout_url();
  ?>
       <a href="<?php echo $checkout_url; ?>" class="checkout-button button alt wc-forward"><?php  _e( 'Confirm Payment', 'woocommerce' ); ?></a> 
  <?php
} 


/* Add to the functions.php file of your theme */

add_filter( 'woocommerce_order_button_text', 'woo_custom_order_button_text' ); 

function woo_custom_order_button_text() {
    return __( 'Make Payment', 'woocommerce' ); 
}


