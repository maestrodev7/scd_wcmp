<?php
/**
 * WCMp Report Admin Overview
 *
 * @author      WC Marketplace
 * @category    Vendor
 * @package     WCMp/Reports
 * @version     3.5.0
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
include_once(WP_PLUGIN_DIR.'/woocommerce/includes/admin/reports/class-wc-admin-report.php');
class SCD_WCMp_Report_Admin_overview extends WC_Admin_Report {

    function __construct() {}

    /**
     * Output the report
     */
    public function output_report() {
        global $wpdb, $woocommerce, $WCMp;

        $current_range = ( isset($_GET['range']) && !empty($_GET['range']) ) ? sanitize_text_field($_GET['range']) : '7day';

        if (!in_array($current_range, array('custom', 'year', 'last_month', 'month', '7day'))) {
            $current_range = '7day';
        }

        $this->calculate_current_range($current_range);

        $start_date = $this->start_date;
        $end_date = $this->end_date;
        $end_date = strtotime('+1 day', $end_date);

        $sales = $gross_sales = $vendor_earning = $admin_earning = $pending_vendors = $vendors = $products = $transactions = 0;
        
        $args = apply_filters('wcmp_report_admin_overview_query_args', array(
                'post_type' => 'shop_order',
                'posts_per_page' => -1,
                'post_parent' => 0,
                'post_status' => array('wc-processing', 'wc-completed'),
                'date_query' => array(
                    'inclusive' => true,
                    'after' => array(
                        'year' => date('Y', $start_date),
                        'month' => date('n', $start_date),
                        'day' => date('1'),
                    ),
                    'before' => array(
                        'year' => date('Y', $end_date),
                        'month' => date('n', $end_date),
                        'day' => date('j', $end_date),
                    ),
                )
            ));

        $qry = new WP_Query($args);
        $orders = apply_filters('wcmp_report_admin_overview_orders', $qry->get_posts());
         if ( !empty( $orders ) ) {
            foreach ( $orders as $order_obj ) {
                $order = wc_get_order($order_obj->ID);
                $rate = scd_wcmp_get_order_rate($order_obj->ID);//ok
                $sales += $order->get_subtotal()*$rate;
                $wcmp_suborders = get_wcmp_suborders($order_obj->ID);
                if(!empty($wcmp_suborders)) {
                    foreach ($wcmp_suborders as $suborder) {
                        $vendor_order = wcmp_get_order($suborder->get_id());
                        if( $vendor_order ){
                            $gross_sales += $suborder->get_total( 'edit' )*$rate;
                            $vendor_earning += $vendor_order->get_commission_total('edit')*$rate;
                        }
                    }
                }
            }
            $admin_earning = $gross_sales - $vendor_earning;
        }
        $textSymbl = file_get_contents(WP_PLUGIN_DIR.'/scd_wcmp_marketplace/includes/Common-Currency.json');
        ?>
        <script type="text/javascript">
            var textSymbl = <?php echo $textSymbl;?>
            symbl = textSymbl['<?php echo get_option('woocommerce_currency');?>'].symbol;
            sales = '<?php echo round($sales,2);?>';
            admin_earning = '<?php echo round($admin_earning,2);?>'
            document.getElementsByClassName('woocommerce-Price-amount amount')[0].textContent = symbl+sales;//s
            document.getElementsByClassName('woocommerce-Price-amount amount')[1].textContent = symbl+admin_earning;
        </script>
    <?php
    }
}
