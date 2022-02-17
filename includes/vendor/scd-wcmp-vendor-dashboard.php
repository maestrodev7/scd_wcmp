<?php

add_action('after_wcmp_vendor_stats_reports','scd_after_wcmp_vendor_stats_reports');

function scd_after_wcmp_vendor_stats_reports(){
    global $WCMp, $wpdb;

    $chosen_product_ids = $vendor_id = $vendor = false;
    $gross_sales = $my_earning = $vendor_earning = 0;
    $vendor_term_id = get_user_meta( get_current_user_id(), '_vendor_term_id', true );
    $vendor = get_wcmp_vendor_by_term($vendor_term_id);
    $vendor_id = $vendor->id;
    $start_date = strtotime('this week');
    $end_date = strtotime('now');
    if ($vendor_id) {
        if ($vendor)
            $products = $vendor->get_products_ids();
        if (!empty($products)) {
            foreach ($products as $product) {
                $chosen_product_ids[] = $product->ID;
            }
        }
    }

    $args = apply_filters('wcmp_report_admin_vendor_tab_query_args', array(
        'post_type' => 'shop_order',
        'posts_per_page' => -1,
        'author' => $vendor_id,
        'post_status' => array('wc-processing', 'wc-completed'),
        'meta_query' => array(
            array(
                'key' => '_commissions_processed',
                'value' => 'yes',
                'compare' => '='
            ),
            array(
                'key' => '_vendor_id',
                'value' => $vendor_id,
                'compare' => '='
            )
        ),
        'date_query' => array(
            'inclusive' => true,
            'after' => array(
                'year' => date('Y', $start_date),
                'month' => date('n', $start_date),
                'day' => date('j', $start_date),
            ),
            'before' => array(
                'year' => date('Y', $end_date),
                'month' => date('n', $end_date),
                'day' => date('j', $end_date),
            ),
        )
    ) );

    $qry = new WP_Query($args);

    $orders = apply_filters('wcmp_filter_orders_report_vendor', $qry->get_posts());

    if (!empty($orders)) {

        $admin_earning = array();
        $max_total_sales = 0;

        foreach ($orders as $order_obj) {
            try {
                $order = wc_get_order($order_obj->ID);
                $rate = scd_wcmp_get_order_rate($order_obj->post_parent);
                if ($order) :
                    $vendor_order = wcmp_get_order($order->get_id());
                    $gross_sales += $order->get_total( 'edit' )*$rate;
                    $vendor_earning += $vendor_order->get_commission_total('edit')*$rate;    
                endif;
            } catch (Exception $ex) {

            }
        }   
    }
   
    $textSymbl = file_get_contents(WP_PLUGIN_DIR.'/scd_wcmp_marketplace/includes/Common-Currency.json');
    $user_curr= get_user_meta(get_current_user_id(), 'scd-user-currency',true);
    $decimals = scd_options_get_decimal_precision();
    $convert_rate = scd_get_conversion_rate(get_option('woocommerce_currency'), $user_curr);
    $gross_sales *= $convert_rate;
    $vendor_earning *= $convert_rate;
	?>
        <script type="text/javascript">
            var textSymbl = <?php echo $textSymbl;?>
            symbl = textSymbl['<?php echo $user_curr;?>'].symbol;
            gross_sales = '<?php echo round($gross_sales,$decimals);?>';
            vendor_earning = '<?php echo round($vendor_earning,$decimals);?>';
            setTimeout(function(){
	            document.getElementsByClassName('woocommerce-Price-amount amount')[0].textContent = symbl+gross_sales;//s
	            document.getElementsByClassName('woocommerce-Price-amount amount')[2].textContent = symbl+vendor_earning;
	        },1000);
        </script>
<?php
}