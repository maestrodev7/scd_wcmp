<?php
function scd_wcmp_get_order_rate($order_id=''){
	global $wpdb;
	if ($order_id) {
		$order_item =  $wpdb->get_results('SELECT order_item_id FROM '.$wpdb->prefix.'woocommerce_order_items WHERE `order_id` = '.$order_id);
		foreach ($order_item as $ids){
			$order_item_id = $ids->order_item_id;
			$rate_temp = $wpdb->get_results('SELECT meta_value as value FROM '.$wpdb->prefix.'woocommerce_order_itemmeta WHERE `meta_key` = "rate" AND `order_item_id` = '.$order_item_id);
			if (count($rate_temp)>0) {
				return $rate_temp[0]->value;
			}
		}
		//get the order currency
		$order_curr =  $wpdb->get_results('SELECT meta_value FROM '.$wpdb->prefix.'postmeta WHERE `post_id` = '.$order_id.' AND `meta_key` = "_order_currency"');
		if (count($order_curr)>0) {
			return scd_get_conversion_rate($order_curr[0]->meta_value,get_option('woocommerce_currency'));
		}
	}
	return 1;
}