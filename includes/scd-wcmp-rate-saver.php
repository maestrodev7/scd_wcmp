<?php
//for each order's we save the rate between the coustumers currency and woocommerce admin currency.
//this is use to assure good conversion on dashboard
add_action('woocommerce_thankyou','scd_wcmp_save_rate');

function scd_wcmp_save_rate($order_id){
	global $wpdb;
	?>
	<script type="text/javascript">//alert(1);</script>
	<?php
	$mpOrders =  $wpdb->get_results('SELECT order_item_id as ID FROM '.$wpdb->prefix.'woocommerce_order_items WHERE `order_id` = '.$order_id);
	if (count($mpOrders) > 0) {
		# l'ordre est bien enregistre
		$user_curr = scd_get_target_currency();
		$wc_curr = get_option('woocommerce_currency'); 

		if($user_curr == $wc_curr)  {
			$rate = 1;
		}else{
			$rate =  scd_get_conversion_rate($user_curr,$wc_curr);
		}
		foreach ($mpOrders as $key => $value) {
			$wpdb->insert($wpdb->prefix.'woocommerce_order_itemmeta', array(
			    'order_item_id' => $value->ID,
			    'meta_key' => "rate",
			    'meta_value' => $rate
			));
		}
		
	}
}
