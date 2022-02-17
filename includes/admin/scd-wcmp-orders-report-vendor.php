<?php
/*
add_filter('wcmp_filter_orders_report_vendor','scd_wcmp_filter_orders_report_vendor',10,1);
function scd_wcmp_filter_orders_report_vendor($orders){
	return $orders;
}*/

add_action('wcmp_frontend_report_vendor_filter','scd_wcmp_frontend_report_vendor_filter',10,2);
function scd_wcmp_frontend_report_vendor_filter($start_date,$end_date){
	$textSymbl = file_get_contents(WP_PLUGIN_DIR.'/scd_wcmp_marketplace/includes/Common-Currency.json');
	?>
	<script type="text/javascript">
		interval = setInterval(()=>{
			var prices = document.getElementsByClassName('woocommerce-Price-amount amount');
			vends = document.getElementsByTagName('th');
			var textSymbl = <?php echo $textSymbl;?>
            symbl = textSymbl['<?php echo get_option('woocommerce_currency');?>'].symbol;

			if (prices.length>0) {
				var regex = /\d+/g;
				clearInterval(interval);
				var vend_ids = [];
				for (var i = 2; i < vends.length; i++) {
					vend_id_str = vends[i].firstChild.href;
					index_deb= vend_id_str.indexOf('user_id');
					index_fin = vend_id_str.length;
					vend_ids.push(vend_id_str.substr(index_deb,index_fin).match(regex)[0]);
				}
				jQuery.post(ajaxurl,
                    {
                        action: 'scd_wcmp_get_vendor_report_data',
                        vendor_ids: vend_ids,
                        start_date: <?php echo json_encode($start_date);?>,
                        end_date: <?php echo json_encode($end_date);?>

                    },
	                function (response) {
	                    if (response.success) {
	                        for (var i = 0; i < vend_ids.length; i++) {
	                        	vend_data = response.data[vend_ids[i]];
	                        	prices[3*i].innerHTML =symbl+'</br>'+vend_data['total_sales'].toFixed(2);
	                        	prices[3*i+1].innerHTML=symbl+'</br>'+vend_data['admin_earning'].toFixed(2);
	                        	prices[3*i+2].innerHTML=symbl+'</br>'+vend_data['vendor_earning'].toFixed(2);
	                        }
	                    }
	                }
	            );
			}
			
		},500);
	</script>
	<?php
}


add_action('wp_ajax_scd_wcmp_get_vendor_report_data', 'scd_wcmp_get_vendor_report_data');

function scd_wcmp_get_vendor_report_data() {
	$prices = array();

    if (isset($_POST['vendor_ids']) && isset($_POST['start_date']) && isset($_POST['end_date'])) {
    	global $wpdb, $woocommerce, $WCMp;
  
		$vendor = $vendor_id = $order_items = false;

        $all_vendors = get_wcmp_vendors();

        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
    
        $total_sales = $admin_earning = $vendor_report = $report_bk = array();

        if (!empty($all_vendors) && is_array($all_vendors)) {
            foreach ($all_vendors as $vendor) {
                $gross_sales = $my_earning = $vendor_earning = 0;
                $chosen_product_ids = array();
                $vendor_id = $vendor->id;

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

                if ( !empty( $orders ) ) {
                    foreach ( $orders as $order_obj ) {
                        try {
                            $order = wc_get_order($order_obj->ID);
                            $rate = scd_wcmp_get_order_rate($order_obj->post_parent);//ok
                            if ($order) :
                                $vendor_order = wcmp_get_order($order->get_id());
                                $gross_sales += $order->get_total( 'edit' )*$rate;
                                $vendor_earning += $vendor_order->get_commission_total('edit')*$rate;
                            endif;
                        } catch (Exception $ex) {

                        }
                        
                    }
                }
                
                $total_sales[$vendor_id]['total_sales'] = $gross_sales;
                $total_sales[$vendor_id]['vendor_earning'] = $vendor_earning;
                $total_sales[$vendor_id]['admin_earning'] = $gross_sales - $vendor_earning;
                $total_sales[$vendor_id]['vendor_id'] = $vendor_id; // for report filter
            }
    	}
        wp_send_json_success($total_sales);
    }
    wp_send_json_error();
}



add_filter('wp_ajax_vendor_search', 'scd_wcmp_search_vendor_data');

    /**
     * WCMp Vendor Data Searching
     */
function scd_wcmp_search_vendor_data() {
    global $WCMp, $wpdb;

    $chosen_product_ids = $vendor_id = $vendor = false;
    $gross_sales = $my_earning = $vendor_earning = 0;
    $vendor_term_id = isset($_POST['vendor_id']) ? absint($_POST['vendor_id']) : 0;
    $vendor = get_wcmp_vendor_by_term($vendor_term_id);
    $vendor_id = $vendor->id;
    $start_date = isset($_POST['start_date']) ? wc_clean($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? wc_clean($_POST['end_date']) : '';

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
    
    if ( ($vendor_id && empty($products) ) || empty($orders)) {
        $no_vendor = '<h4>' . __("Sales and Earnings", 'dc-woocommerce-multi-vendor') . '</h4>
        <table class="bar_chart">
            <thead>
                <tr>
                    <th>' . __("Month", 'dc-woocommerce-multi-vendor') . '</th>
                    <th colspan="2">' . __("Sales", 'dc-woocommerce-multi-vendor') . '</th>
                </tr>
            </thead>
            <tbody> 
                <tr><td colspan="3">' . __("No Sales :(", 'dc-woocommerce-multi-vendor') . '</td></tr>
            </tbody>
        </table>';

        echo $no_vendor;
        die;
    }

    if (!empty($orders)) {

        $total_sales = $admin_earning = array();
        $max_total_sales = 0;

        foreach ($orders as $order_obj) {
            try {
                $order = wc_get_order($order_obj->ID);
                $rate = scd_wcmp_get_order_rate($order_obj->post_parent);
                if ($order) :
                    $vendor_order = wcmp_get_order($order->get_id());
                    $gross_sales += $order->get_total( 'edit' )*$rate;
                    $vendor_earning += $vendor_order->get_commission_total('edit')*$rate;
                    // Get date
                    $date = date('Ym', strtotime($order->get_date_created()));
                    // Set values
                    $total_sales[$date]['total_sales'] = $gross_sales;
                    $total_sales[$date]['vendor_earning'] = $vendor_earning;
                    $total_sales[$date]['admin_earning'] = $gross_sales - $vendor_earning;
                    
                endif;
            } catch (Exception $ex) {

            }
        }
        

        $report_chart = $report_html = '';
        if (count($total_sales) > 0) {
            foreach ($total_sales as $date => $sales) {
                $total_sales_width = ( $sales > 0 ) ? ( round($sales['total_sales']) / round( $sales['total_sales'] ) ) * 100 : 0;
                $admin_earning_width = ( $sales['admin_earning'] > 0 ) ? ( $sales['admin_earning'] / round($sales['total_sales']) ) * 100 : 0;
                $vendor_earning_width = ( $sales['vendor_earning'] > 0 ) ? ( $sales['vendor_earning'] / round($sales['total_sales']) ) * 100 : 0;

                $orders_link = admin_url('edit.php?s&post_status=all&post_type=shop_order&action=-1&m=' . date('Ym', strtotime($date . '01')) . '&shop_order_status=' . implode(",", apply_filters('woocommerce_reports_order_statuses', array('completed', 'processing', 'on-hold'))));
                $orders_link = apply_filters('woocommerce_reports_order_link', $orders_link, $chosen_product_ids );

                $report_chart .= '<tr><th><a href="' . esc_url($orders_link) . '">' . date_i18n('F', strtotime($date . '01')) . '</a></th>
                    <td class="sales_prices" width="1%"><span>' . wc_price($sales['total_sales']) . '</span>'
                        . '<span class="alt">' . wc_price($sales['admin_earning']) . '</span>'
                        . '<span class="alt">' . wc_price($sales['vendor_earning']) . '</span></td>
                    <td class="bars">
                        <span class="gross_bar main" style="width:' . esc_attr($total_sales_width) . '%">&nbsp;</span>
                        <span class="admin_bar alt" style="width:' . esc_attr($admin_earning_width) . '%">&nbsp;</span>
                        <span class="vendor_bar alt" style="width:' . esc_attr($vendor_earning_width) . '%">&nbsp;</span>
                    </td></tr>';
            }

            $report_html = '
                <h4>' . $vendor->page_title . '</h4>
                <div class="bar_indecator">
                    <div class="bar1">&nbsp;</div>
                    <span class="">' . __('Gross Sales', 'dc-woocommerce-multi-vendor') . '</span>
                    <div class="bar2">&nbsp;</div>
                    <span class="">' . __('Admin Earnings', 'dc-woocommerce-multi-vendor') . '</span>
                    <div class="bar3">&nbsp;</div>
                    <span class="">' . __('Vendor Earnings', 'dc-woocommerce-multi-vendor') . '</span>
                </div>
                <table class="bar_chart">
                    <thead>
                        <tr>
                            <th>' . __("Month", 'dc-woocommerce-multi-vendor') . '</th>
                            <th colspan="2">' . __("Vendor Earnings", 'dc-woocommerce-multi-vendor') . '</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $report_chart . '
                    </tbody>
                </table>
            ';
        } else {
            $report_html = '<tr><td colspan="3">' . __('This vendor did not generate any sales in the given period.', 'dc-woocommerce-multi-vendor') . '</td></tr>';
        }
    }

    echo $report_html;

    die;
}


