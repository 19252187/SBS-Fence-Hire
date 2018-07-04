<style>
    .lds-dual-ring {
      display: inline-block;
      width: 64px;
      height: 64px;
      animation: lds-dual-ring 1.2s linear infinite;
    }
    .lds-dual-ring:after {
      content: " ";
      display: block;
      width: 46px;
      height: 46px;
      margin: 1px;
      border-radius: 50%;
      border: 5px solid #e96f24;
      border-color: #e96f24 transparent #e96f24 transparent;
      animation: lds-dual-ring 1.2s linear infinite;
    }
@keyframes lds-dual-ring {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

</style>
<?php
/**
 * Simple product add to cart
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/add-to-cart/simple.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.4.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product->is_purchasable() ) {
	return;
}

echo wc_get_stock_html( $product ); // WPCS: XSS ok.

if ( $product->is_in_stock() ) : ?>

	<?php do_action( 'woocommerce_before_add_to_cart_form' ); ?>

	<form class="cart" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype='multipart/form-data'>
		<?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>

		<?php
		do_action( 'woocommerce_before_add_to_cart_quantity' );

		woocommerce_quantity_input( array(
			'min_value'   => apply_filters( 'woocommerce_quantity_input_min', $product->get_min_purchase_quantity(), $product ),
			'max_value'   => apply_filters( 'woocommerce_quantity_input_max', $product->get_max_purchase_quantity(), $product ),
			'input_value' => isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : $product->get_min_purchase_quantity(), // WPCS: CSRF ok, input var ok.
		) );

		do_action( 'woocommerce_after_add_to_cart_quantity' );
		?>

		<button type="submit" name="add-to-cart" value="<?php echo esc_attr( $product->get_id() ); ?>" class="single_add_to_cart_button button alt"><?php echo esc_html( $product->single_add_to_cart_text() ); ?></button>

		<?php do_action( 'woocommerce_after_add_to_cart_button' ); ?>
	</form>

	<?php do_action( 'woocommerce_after_add_to_cart_form' ); ?>



<?php endif; ?>

<script>
    jQuery( function($) {
        function getCheckoutPage(){
            var wp_ajax_url="/wp-admin/admin-ajax.php";
            var data = {
                action: 'getCheckoutPageContent'
            };
             $.ajax({
                url: wp_ajax_url,
                type: 'post',
                data:  data,
                beforeSend :function(){
                  $("#checkout").html('<div class="lds-dual-ring"></div> Loading ...');
                },
                success: function( content){
                   $("#checkout").html(content);  
                },
            });
        }
      
        function getCartPage(){
            var wp_ajax_url="/wp-admin/admin-ajax.php";
            var data = {
                action: 'getCartPageContent'
            };
            $.ajax({
                url: wp_ajax_url,
                type: 'post',
                data:  data,
                beforeSend :function(){
                  $("#cart").html('<div class="lds-dual-ring"></div> Loading ...');
                },
                success: function(content ){
                  $("#cart").html(content);
                },
                error: function( jqXhr, textStatus, errorThrown ){
                    console.log( errorThrown );
                }
            });
            
        }
        
        
        function removeItemFromCart(url,item){
            $.ajax({
                url: url,
                type: 'get',
                beforeSend :function(){
                 $(item).parent().parent().css("opacity","0.3");
                },
                success: function(e ){
                 $(item).parent().parent().remove();
                }
            });
        }
        
        $( document ).on( 'click', '.checkout-button', function(e) {
            e.preventDefault();
            getCheckoutPage();
        });
        
        $( document ).on( 'click', '.remove', function(e) {
             e.preventDefault();
             var item = $(this);
             removeItemFromCart($(this).attr('href'),item);
        });
        
        $( document ).on( 'click', '.single_add_to_cart_button', function(e) {
            e.preventDefault();
            var nyp = $('#nyp').val();
            var quantity = 1;
            var product_id = "<?php echo esc_attr( $product->get_id() ); ?>";
            var this_button = $(this);
            $.ajax({
                url: '<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>',
                dataType: "text",
                type: 'post',
                data:  'add-to-cart=' + product_id + '&quantity=' + quantity+ '&nyp='+nyp,
                processData: false,
                beforeSend :function(){
                  $(this_button).html("Adding....");
                },
                success: function( ){
                   getCartPage();
                   $(this_button).html("<?php echo esc_html( $product->single_add_to_cart_text() ); ?>");  
                },
                error: function( jqXhr, textStatus, errorThrown ){
                    console.log( errorThrown );
                }
            });
        })
    });
</script>