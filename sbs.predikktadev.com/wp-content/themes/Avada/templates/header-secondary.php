<?php
/**
 * Template for the secondary header.
 *
 * @author     ThemeFusion
 * @copyright  (c) Copyright by ThemeFusion
 * @link       http://theme-fusion.com
 * @package    Avada
 * @subpackage Core
 */

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'Direct script access denied.' );
}
?>
<?php

$content_1 = avada_secondary_header_content( 'header_left_content' );
$content_2 = avada_secondary_header_content( 'header_right_content' );
?>

<div class="fusion-secondary-header">
	<div class="fusion-row">
	    <div id="mobile-call">
            <a class="active" href="tel:1300556396"><img src="https://www.sbs.predikktadev.com/wp-content/uploads/2018/06/phone-receiver.png"/></a>
            <a href="mailto:sbs@sbsfence.com.au"><img src="https://www.sbs.predikktadev.com/wp-content/uploads/2018/06/envelope.png"/></a>
        </div>
		<?php if ( $content_1 ) : ?>
			<div class="fusion-alignleft">
				<?php echo $content_1; // WPCS: XSS ok. ?>
			</div>
		<?php endif; ?>
		<?php if ( $content_2 ) : ?>
			<div class="fusion-alignright">
				<?php echo $content_2; // WPCS: XSS ok. ?>
			</div>
		<?php endif; ?>
	</div>
</div>
