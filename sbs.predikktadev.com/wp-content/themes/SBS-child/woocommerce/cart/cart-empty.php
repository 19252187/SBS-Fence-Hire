

<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

wc_print_notices();

/**
 * @hooked wc_empty_cart_message - 10
 */
do_action( 'woocommerce_cart_is_empty' );

if ( wc_get_page_id( 'shop' ) > 0 ) :
 ?>
	<p class="return-to-shop">
		<a class="button wc-backward" href="https://www.sbs.predikktadev.com/product/invoice/">
			<?php _e( 'Please Enter the Amout to Pay', 'woocommerce' ) ?>
		</a>
	</p>
<?php endif; ?>
