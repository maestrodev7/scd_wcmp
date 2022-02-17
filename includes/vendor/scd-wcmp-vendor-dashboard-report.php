<?php

add_filter('wcmp_vendor_dashboard_report_data','scd_wcmp_vendor_dashboard_report_data',10,2); 

function scd_wcmp_vendor_dashboard_report_data($result,$data){
	global $wpdb;

	if ( isset( $_POST['wcmp_stat_start_dt'] ) ) {
        $start_date = wc_clean( wp_unslash( $_POST['wcmp_stat_start_dt'] ) );
    } else {
        // hard-coded '01' for first day     
        $start_date = date( 'Y-m-01' );
    }

    if ( isset( $_POST['wcmp_stat_end_dt'] ) ) {
        $end_date = wc_clean( wp_unslash( $_POST['wcmp_stat_end_dt'] ) );
    } else {
        // hard-coded '01' for first day
        $end_date = date( 'Y-m-d' );
    }
    $total_sales = 0;
    $total_vendor_earnings = 0;
    $total_order_count = 0;
    $total_purchased_products = 0;
    $total_coupon_used = 0;
    $total_coupon_discount_value = 0;
    $total_earnings = 0;
    $total_customers = array();
    $vendor = get_wcmp_vendor(get_current_vendor_id());
    $vendor = apply_filters('wcmp_dashboard_sale_stats_vendor', $vendor);
    for ($date = strtotime($start_date); $date <= strtotime('+1 day', strtotime($end_date)); $date = strtotime('+1 day', $date)) {

        $year = date('Y', $date);
        $month = date('n', $date);
        $day = date('j', $date);

        $line_total = $sales = $comm_amount = $vendor_earnings = $earnings = 0;

        $args = apply_filters( 'vendor_sales_stat_overview_args', array(
            'post_type' => 'shop_order',
            'posts_per_page' => -1,
            'post_status' => array('wc-processing', 'wc-completed'),
            'meta_query' => array(
                array(
                    'key' => '_commissions_processed',
                    'value' => 'yes',
                    'compare' => '='
                ),
                array(
                    'key' => '_vendor_id',
                    'value' => get_current_user_id(),
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                ),
            )
        ), $vendor);

        $qry = new WP_Query($args);

        $orders = apply_filters('wcmp_filter_orders_report_overview', $qry->get_posts(), $vendor->id);
        if (!empty($orders)) {
            foreach ($orders as $order_obj) {
            	$rate = scd_wcmp_get_order_rate($order_obj->post_parent);//ok
                $order = new WC_Order($order_obj->ID);
                if ($order) :
                    $vendor_order = wcmp_get_order($order->get_id());
                    $total_sales += $order->get_total()*$rate;
                    $total_coupon_discount_value += $order->get_total_discount()*$rate;
                    $total_earnings += $vendor_order->get_commission_total('edit')*$rate;
                    $total_vendor_earnings += $vendor_order->get_commission('edit')*$rate;
                    $total_purchased_products += count($order->get_items('line_item'));
                endif;
            }
        }
    }

	$result['total_vendor_sales'] = $total_sales;
	$result['total_vendor_earning'] = $total_earnings;
	$result['total_coupon_discount_value'] = $total_coupon_discount_value;

	return $result;
}
