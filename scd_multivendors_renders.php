<?php

include 'scd_pro_currencies.php';

add_filter('scd_enable_js_conversion', 'scd_enable_js_conversion_func', 10, 1);
function scd_enable_js_conversion_func($enableJsConv)
{
    $options =  get_option('scd_currency_options', true);
    if (isset($options['pricesInVendorCurrency']) && !empty($options['pricesInVendorCurrency']))
        return false;
    return $enableJsConv;
}

add_filter('scd_multivendors_activate', 'scd_multivendors_activate_func', 10, 1);
function scd_multivendors_activate_func($scd_multi_activate)
{
    return true;
}
function scd_check_license_active()
{
    $opt_license_key = get_option('scd_license_key');
    $opt_license_start_date = get_option('scd_license_start_date');
    $opt_license_expiry_date = get_option('scd_license_expiry_date');

    if (empty($opt_license_key) && empty($opt_license_start_date) && !file_exists($GLOBALS['scd_license_file'])) {
        return FALSE;
    } else {
        if (!empty($opt_license_start_date)) {
            $startdate = new DateTime(base64_decode(get_option('scd_license_start_date')));
        } else if (file_exists($GLOBALS['scd_license_file'])) {
            $startdate = new DateTime(base64_decode(file_get_contents($GLOBALS['scd_license_file'])));
        } else { //only the license key varable remains
            return FALSE;
        }

        if (empty($opt_license_expiry_date) && is_admin()) {
            scd_set_expiry($opt_license_key, $startdate);
            $opt_license_expiry_date = get_option('scd_license_expiry_date');
        }

        $todaydate = new DateTime(date('Y-m-d'));
        $duration = $startdate->diff($todaydate);

        if (!empty($opt_license_expiry_date)) {
            $expirydate = new DateTime(base64_decode($opt_license_expiry_date));
            if ($todaydate < $expirydate) {
                return TRUE;
            } else {
                return FALSE;
            }
        } else {
            // For backward compatibility with older activations prior to 4.5.2 
            if ($duration->days > $GLOBALS['scd_license_duration']) {
                return FALSE;
            } else {
                return TRUE;
            }
        }
    }
}
add_action('wp_ajax_scd_wcmp_get_user_currency', 'scd_wcmp_get_user_currency');
function scd_wcmp_get_user_currency()
{
    $user_curr = get_user_meta(get_current_user_id(), 'scd-user-currency', true);
    if ($user_curr) {
        echo $user_curr;
        return $user_curr;
    } else {
        echo 'FALSE';
        return FALSE;
    }
}

function scd_get_user_currency()
{
    $user_curr = get_user_meta(get_current_user_id(), 'scd-user-currency', true);
    if ($user_curr) {
        return $user_curr;
    } else {
        $default_curr = get_option( 'woocommerce_currency');
        return $default_curr;
    }
}

function scd_get_user_currency_option()
{
    $curr_opt = get_user_meta(get_current_user_id(), 'user-currency-option');
    if (count($curr_opt) > 0) {
        return $curr_opt[0];
    } else {
        return 'only-default-currency';
    }
}

add_action('wp_ajax_scd_show_user_currency', 'scd_show_user_currency');
function scd_show_user_currency()
{
    $options = array(
        'base-currency' => 'Base currency only',
        'only-default-currency' => 'Your default currency only'/*,
            'base-and-default-currency' => 'Base and default currency',
            'selected-currencies' => 'Selected currencies'*/
    );
    echo '<div class="scd-choose-curr" style="margin-left:15%;margin-top:70px; backgound-color:red;">';
?>
    <p id="scd-action-status" style="margin-left:15%;"></p>
    <p style="color: black;">Select your default currency</p>
    <select id="scd-currency-list" class="scd-user-curr" style="width: 58%;">
        <?php
        $user_curr = scd_get_user_currency();
        //if($user_curr!==FALSE) $user_curr=$user_curr[0];
        foreach (scd_get_list_currencies() as $key => $val) {
            if ($user_curr == $key) {
                echo '<option selected value="' . $key . '" >' . $key . '(' .  get_woocommerce_currency_symbol($key) . ')</option>';
            } else {
                echo '<option value="' . $key . '" >' . $key . '(' . get_woocommerce_currency_symbol($key) . ')</option>';
            }
        }
        ?>
    </select>
    <?php

    //echo '<a  style="color:black;" class="button" href="#" id="scd-save-curr">Save change<a>';
    echo '<br><br>';
    echo '<p style="color: black;">Set products price in</p>';
    ?>

    <select id="scd-currency-option" class="scd-user-curr" style="width: 58%;">
        <?php
        $currency_opt = scd_get_user_currency_option();
        foreach ($options as $key => $val) {
            if ($currency_opt == $key) {
                echo '<option selected value="' . $key . '" >' . $val . '</option>';
            } else {
                echo '<option value="' . $key . '" >' . $val . '</option>';
            }
        }
        ?>
    </select>
<?php
    echo '<br><br>';
    echo '<a  style="color:black;" class="button" href="#" id="scd-save-currency-option">Save change<a>';
    echo '</p></div>';
    die();
}

add_action('wp_ajax_scd_update_user_currency', 'scd_update_user_currency');
function scd_update_user_currency()
{
    if (isset($_POST['user_currency'])) {

        update_user_meta(get_current_user_id(), 'scd-user-currency', $_POST['user_currency']);
        echo 'Information saved. Your new custom currency is ' . get_user_meta(get_current_user_id(), 'scd-user-currency')[0];
    } else {
        echo 'Currency not saved please try again';
    }
    die();
}
add_action('wp_ajax_scd_update_user_currency_option', 'scd_update_user_currency_option');
function scd_update_user_currency_option()
{
    if (isset($_POST['user_currency_option'])) {

        update_user_meta(get_current_user_id(), 'user-currency-option', $_POST['user_currency_option']);
        echo 'Information saved';
    } else {
        echo 'Option not saved please try again';
    }
    die();
}

//when vendor is connected set the target currency to his default currency
function scd_multivendor_currency($scd_target_currency)
{

    $user_currency = scd_get_user_currency();
    if ($user_currency !== false) {
        $scd_target_currency = $user_currency;
    }
    return $scd_target_currency;
}
add_filter('scd_target_currency', 'scd_multivendor_currency', 10, 1);

//export import products with woocommerce

add_filter('woocommerce_product_export_column_names', 'scd_add_export_column');
add_filter('woocommerce_product_export_product_default_columns', 'scd_add_export_column');
function scd_add_export_column($columns)
{

    // column slug => column name
    $columns['scd_other_options'] = 'Meta: scd_other_options';

    return $columns;
}

function scd_add_export_data($value, $product)
{
    $value = get_post_meta($product->get_id(), 'scd_other_options', true);

    return serialize($value);
}
// Filter you want to hook into will be: 'woocommerce_product_export_product_column_{$column_slug}'.
add_filter('woocommerce_product_export_product_column_scd_other_options', 'scd_add_export_data', 10, 2);

// Hook into the filter
add_filter("woocommerce_product_importer_parsed_data", "scd_csv_import_serialized", 10, 2);
function scd_csv_import_serialized($data, $importer)
{
    if (isset($data["meta_data"]) && is_array($data["meta_data"])) {
        foreach (array_keys($data["meta_data"]) as $k) {
            $data["meta_data"][$k]["value"] = maybe_unserialize($data["meta_data"][$k]["value"]);
        }
    }
    return $data;
}

//filter in the free version
add_filter('is_scd_multivendor', 'is_scd_multivendor', 10, 1);
function is_scd_multivendor($multi)
{
    return true;
}

add_filter('scd_disable_sidebar_currencies', 'fct_scd_disable_sidebar_currencies', 10, 1);
function fct_scd_disable_sidebar_currencies()
{
    return false;
}

//Vendor order WCMP convertion
add_filter('wcmp_datatable_order_list_row_data', 'scd_wcmp_orders_convertion', 10, 2);
function scd_wcmp_orders_convertion($tab, $order)
{
    $actions['view'] = array(
        'url' => esc_url(wcmp_get_vendor_dashboard_endpoint_url(get_wcmp_vendor_settings('wcmp_vendor_orders_endpoint', 'vendor', 'general', 'vendor-orders'), $order->get_id())),
        'icon' => 'ico-eye-icon action-icon',
        'title' => __('View', 'dc-woocommerce-multi-vendor'),
    );
    $user = wp_get_current_user();
    $vendor = get_wcmp_vendor($user->ID);
    if ($vendor->is_shipping_enable()) {
        $vendor_shipping_method = get_wcmp_vendor_order_shipping_method($order->get_id(), $vendor->id);
        // hide shipping for local pickup
        if ($vendor_shipping_method && !in_array($vendor_shipping_method->get_method_id(), apply_filters('hide_shipping_icon_for_vendor_order_on_methods', array('local_pickup')))) {
            $actions['mark_ship'] = array(
                'url' => '#',
                'title' => $mark_ship_title,
                'icon' => 'ico-shippingnew-icon action-icon'
            );
        }
    }
    if (apply_filters('can_wcmp_vendor_export_orders_csv', true, get_current_vendor_id())) :
        $actions['wcmp_vendor_csv_download_per_order'] = array(
            'url' => admin_url('admin-ajax.php?action=wcmp_vendor_csv_download_per_order&order_id=' . $order->get_id() . '&nonce=' . wp_create_nonce('wcmp_vendor_csv_download_per_order')),
            'icon' => 'ico-download-icon action-icon',
            'title' => __('Download', 'dc-woocommerce-multi-vendor'),
        );
    endif;
    $actions = apply_filters('wcmp_my_account_my_orders_actions', $actions, $order->get_id());
    $action_html = '';
    $is_shipped = (array) get_post_meta($order->get_id(), 'dc_pv_shipped', true);
    foreach ($actions as $key => $action) {
        if ($key == 'mark_ship' && !in_array($vendor->id, $is_shipped)) {
            $action_html .= '<a href="javascript:void(0)" title="' . __('Mark as shipped', 'dc-woocommerce-multi-vendor') . '" onclick="wcmpMarkeAsShip(this,' . $order->get_id() . ')"><i class="wcmp-font ' . $action['icon'] . '"></i></a> ';
        } else if ($key == 'mark_ship') {
            $action_html .= '<i title="' . __('Shipped', 'dc-woocommerce-multi-vendor') . '" class="wcmp-font ' . $action['icon'] . '"></i> ';
        } else {
            $action_html .= '<a href="' . $action['url'] . '" title="' . $action['title'] . '"><i class="wcmp-font ' . $action['icon'] . '"></i></a> ';
        }
    }
    return array(
        'select_order' => '<input type="checkbox" class="select_' . $order->get_status() . '" name="selected_orders[' . $order->get_id() . ']" value="' . $order->get_id() . '" />',
        'order_id' => $order->get_id(),
        'order_date' => wcmp_date($order->get_date_created()),
        'vendor_earning' => (wcmp_get_order($order->get_id())->get_commission_total()) ? get_woocommerce_currency_symbol(scd_get_user_currency()) . apply_filters('scd_convert_subtotal', wcmp_get_order($order->get_id())->get_commission_total('edit'), $order->get_currency(), scd_get_user_currency(), 2) : '-',
        'order_status' => esc_html(wc_get_order_status_name($order->get_status())), //ucfirst($order->get_status()),
        'action' => $action_html,
    );
}

//Rigth price in WCMP vendor email
add_action('woocommerce_email_before_order_table', 'scd_show_cust_order_calulations_field_hearder', 10, 1);
function scd_show_cust_order_calulations_field_hearder($order)
{
    $text_align = is_rtl() ? 'right' : 'left';
    $user = wp_get_current_user();
    $vendor = get_wcmp_vendor($user->ID);
    echo '<style> tbody{ display:none; } thead{ display:none;} table:nth-child(4){display: none;}</style>';
?>
    <table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
        <tr>
            <th scope="col" style="text-align:<?php echo $text_align; ?>; border: 1px solid #eee;"><?php _e('Product', 'dc-woocommerce-multi-vendor'); ?></th>
            <th scope="col" style="text-align:<?php echo $text_align; ?>; border: 1px solid #eee;"><?php _e('Quantity', 'dc-woocommerce-multi-vendor'); ?></th>
            <th scope="col" style="text-align:<?php echo $text_align; ?>; border: 1px solid #eee;"><?php _e('Commission', 'dc-woocommerce-multi-vendor'); ?></th>
        </tr>
        <tfoot>
            <?php
            $vendor_items = $order->get_items('line_item');
            $total_comission = 0;
            foreach ($vendor_items as $item_id => $item) {
                $product = $item->get_product();
                $_product = apply_filters('wcmp_woocommerce_order_item_product', $product, $item);
            ?>
                <tr class="">
                    <?php do_action('wcmp_before_vendor_order_item_table', $item, $order, $vendor_id, $is_ship); ?>
                    <td scope="col" style="text-align:left; border: 1px solid #eee;" class="product-name">
                        <?php
                        if ($_product && !$_product->is_visible()) {
                            echo apply_filters('wcmp_woocommerce_order_item_name', $item->get_name(), $item);
                        } else {
                            echo apply_filters('woocommerce_order_item_name', sprintf('<a href="%s">%s</a>', get_permalink($item->get_product_id()), $item->get_name()), $item);
                        }
                        wc_display_item_meta($item);
                        ?>
                    </td>
                    <td scope="col" style="text-align:left; border: 1px solid #eee;">
                        <?php
                        echo $item->get_quantity();
                        ?>
                    </td>
                    <td scope="col" style="text-align:left; border: 1px solid #eee;">
                        <?php
                        if ($is_ship) {
                            echo round($order->get_formatted_line_subtotal($item), 2);
                        } else {
                            echo get_woocommerce_currency_symbol($order->get_currency()) . round($item['_vendor_item_commission'], 2);
                            $total_comission = $total_comission + $item['_vendor_item_commission'];
                        }
                        //echo get_woocommerce_currency_symbol(get_user_meta($vendor->id, 'scd-user-currency',true)).apply_filters('scd_convert_subtotal', $order->get_formatted_line_subtotal($item), $order->get_currency(), get_user_meta(get_post_field('post_author', $product->get_id()), 'scd-user-currency',true), 2);
                        ?>
                    </td>
                    <?php do_action('wcmp_after_vendor_order_item_table', $item, $order, $vendor_id, $is_ship); ?>
                </tr>
            <?php
            }
            ?>
            <?php
            $order_item_totals = array();
            $vendor_order = wcmp_get_order($order->get_id());
            $order_item_totals['commission_subtotal'] = array(
                'label' => __('Commission Subtotal:', 'dc-woocommerce-multi-vendor'),
                'value' => wc_price($total_comission, array('currency' => $order->get_currency()))
            );
            foreach ($order->get_shipping_methods() as $shipping_method) {
                $meta_data = $shipping_method->get_formatted_meta_data('');
                foreach ($meta_data as $meta_id => $meta) :
                    if (!in_array($meta->key, array('vendor_id'), true)) {
                        continue;
                    }

                    if ($meta->value || $meta->value == get_current_user_id())
                        $order_item_totals['shipping_method'] = array(
                            'label' => __('Shipping Method:', 'dc-woocommerce-multi-vendor'),
                            'value' => $shipping_method->get_name()
                        );

                endforeach;
            }
            $vendor_order_scd = new WCMp_Vendor_Order($order->get_id());
            $order_item_totals['tax_subtotal'] = array(
                'label' => __('Tax Subtotal:', 'dc-woocommerce-multi-vendor'),
                'value' => $vendor_order_scd->get_tax()
            );
            $order_item_totals['shipping_subtotal'] = array(
                'label' => __('Shipping Subtotal:', 'dc-woocommerce-multi-vendor'),
                'value' => wc_price(apply_filters('scd_convert_subtotal', $order->get_shipping_total(), get_option('woocommerce_currency'), $order->get_currency(), 2), array('currency' => $order->get_currency()))
            );
            $order_item_totals['total'] = array(
                'label' => __('Total:', 'dc-woocommerce-multi-vendor'),
                'value' => wc_price(apply_filters('scd_convert_subtotal', $order->get_shipping_total(), get_option('woocommerce_currency'), $order->get_currency(), 2) + $vendor_order_scd->get_tax() + $total_comission, array('currency' => $order->get_currency()))
            );
            $totals = apply_filters('wcmp_vendor_get_order_item_totals', $order_item_totals, $order, $vendor);
            if ($totals) {
                foreach ($totals as $total_key => $total) {
            ?><tr>
                        <th scope="row" colspan="2" style="text-align:left; border: 1px solid #eee;"><?php echo $total['label']; ?></th>
                        <td style="text-align:<?php echo $text_align; ?>; border: 1px solid #eee;"><?php echo $total['value']; ?></td>
                    </tr><?php
                        }
                    }
                    if ($order->get_customer_note()) {
                            ?>
                <tr>
                    <th class="td" scope="row" colspan="2" style="text-align:<?php echo esc_attr($text_align); ?>;"><?php esc_html_e('Note:', 'woocommerce'); ?></th>
                    <td class="td" style="text-align:<?php echo esc_attr($text_align); ?>;"><?php echo wp_kses_post(nl2br(wptexturize($order->get_customer_note()))); ?></td>
                </tr>
            <?php
                    }
            ?>
        </tfoot>

    </table>
<?php
}




















add_filter('woocommerce_cart_totals_order_total_html', 'scd_wcmp_woocommerce_cart_totals_order_total_html', 10, 1);
function scd_wcmp_woocommerce_cart_totals_order_total_html($total)
{
    if (get_option('woocommerce_tax_display_cart') == "excl") {
        //return $total;
    }
    $currency_cart = scd_get_target_currency();

    $items = WC()->cart->get_cart();

    $mysubtot = 0;
    $basecurrency = get_option('woocommerce_currency');
    foreach ($items as $cart_item) {
        // Get the product price from the id
        $product = wc_get_product($cart_item['product_id']);
        $vendor_currency = (get_user_meta(get_wcmp_product_vendors($product->get_id())->id, 'scd-user-currency', true));
        if ((get_the_terms($product->get_id(), 'product_type')[0]->slug == "variable") || (get_the_terms($product->get_id(), 'product_type')[0]->slug == "simple")) {
            if (!empty($product)) {
                if ($cart_item['variation_id']) {
                    $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    $price_html = $regprice;
                    if ($regprice != "") {
                        if ($regprice > 0) {
                            if ($saleprice != "") {
                                $price_html = $saleprice;
                            }
                        } else {
                            $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $price_html = $regprice;
                            if ($regprice > 0) {
                                if ($saleprice != "") {
                                    $price_html = $saleprice;
                                }
                            }
                        }
                    } else {
                        $regprice = scd_function_convert_subtotal($cart_item['data']->get_price(), scd_get_target_currency(), get_woocommerce_currency(), 1, TRUE);
                        $price_html = $regprice;
                    }
                } else {
                    $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    $price_html = $regprice;
                    if ($regprice != "") {
                        if ($regprice > 0) {
                            if ($saleprice != "") {
                                $price_html = $saleprice;
                            }
                        } else {
                            $price_html = (check_simple_product_custom_prices($product, true));
                            if ($price_html <= 0) {
                                $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                                $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                                $price_html = $regprice;
                                $regprice = 0;
                                if ($regprice > 0) {
                                    if ($saleprice != "") {
                                        $price_html = $saleprice;
                                    }
                                }
                            }
                        }
                    } else {
                        $regprice = scd_function_convert_subtotal($cart_item['data']->get_price(), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                        $price_html = $regprice;
                    }
                }
                $qty = $cart_item['quantity'];

                // Add the item price to our computed subtotal
                $unit_price = $price_html * $qty;
                $mysubtot += $unit_price;
            }
        } else {
            $qty = $cart_item['quantity'];

            // Add the item price to our computed subtotal
            $unit_price = scd_function_convert_subtotal($cart_item['data']->get_price(), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE) * $qty;
            $mysubtot += $unit_price;
        }
    }

    // If prices are tax inclusive, show taxes here.
    if (wc_tax_enabled() && WC()->cart->display_prices_including_tax()) {
        $tax_string_array = array();
        $cart_tax_totals = WC()->cart->get_tax_totals();

        if (get_option('woocommerce_tax_total_display') === 'itemized') {
            foreach ($cart_tax_totals as $code => $tax) {
                $tax_amount = $tax->amount;
                if ($currency_cart != $basecurrency) {
                    $tax_amount = scd_function_convert_subtotal($tax_amount, $basecurrency, $currency_cart);
                }
                $tax_html = scd_format_converted_price_to_html($tax_amount, $args);
                $tax_string_array[] = sprintf('%s %s', $tax_html, $tax->label);
            }
        } elseif (!empty($cart_tax_totals)) {
            $tax_amount = WC()->cart->get_taxes_total(true, true);
            if ($currency_cart != $basecurrency) {
                $tax_amount = scd_function_convert_subtotal($tax_amount, $basecurrency, $currency_cart);
            }
            $tax_html = scd_format_converted_price_to_html($tax_amount, $args);
            $tax_string_array[] = sprintf('%s %s', $tax_html, WC()->countries->tax_or_vat());
        }

        if (!empty($tax_string_array)) {
            $taxable_address = WC()->customer->get_taxable_address();
            /* translators: %s: country name */
            $estimated_text = WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping() ? sprintf(' ' . __('estimated for %s', 'woocommerce'), WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]) : '';
            /* translators: %s: tax information */
            //$value .= '<small class="includes_tax">' . sprintf( __( '(includes %s)', 'woocommerce' ), implode( ', ', $tax_string_array ) . $estimated_text ) . '</small>';
        }
    } else {
        $total_tax = "";
        foreach (WC()->cart->get_tax_totals() as $tax) {
            $total_tax += scd_function_convert_subtotal($tax->amount, scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
        }
    }

    //get shipping fees
    $ship_total = WC()->cart->get_shipping_total();
    if ($currency_cart != $basecurrency) {
        $ship_total = scd_function_convert_subtotal($ship_total, $basecurrency, $currency_cart);
    }

    $total_amount = $mysubtot + $ship_total + $total_tax;
    //$total = scd_format_converted_price_to_html($total_amount, $args);

    if ($mysubtot != 0) {
        $store_currency = get_option('woocommerce_currency');
        $target_currency = scd_get_target_currency();
        $decimals = scd_options_get_decimal_precision();
        $args['currency'] = $target_currency; //function to define
        $args['decimals'] = $decimals;
        $args['price_format'] = scd_change_currency_display_format(get_woocommerce_price_format(), $target_currency);
        $max_sale = scd_vendor_format_converted_price_to_html(floatval($total_amount), $args);
        $total = $max_sale;;
    }
   
//code pour le coupon de réduction by LJ
if (WC()->cart->has_discount() ) {
    $values = array (
        'data'		=> $product,
        'quantity'	=> 1

    );
    $coupons = WC()->cart->get_coupons();
    $items = count(WC()->cart->get_cart());
   $items1 =WC()->cart->get_cart_contents_count();
    $_price = $total_amount;
    $undiscounted_price = $_price;
    if ( ! empty( $coupons ) ) {
        foreach ( $coupons as $code => $coupon ) {
            if ( $coupon->is_valid() && ( $coupon->is_valid_for_product( $product, $values ) || $coupon->is_valid_for_cart() ) ) {
               // $discount_amount = $coupon->get_discount_amount( 'yes' === get_option( 'woocommerce_calc_discounts_sequentially', 'no' ) ? $_price : $undiscounted_price, $values, true );
               if ( $coupon->is_type( array( 'percent' ) ) ) {
                    $discount_amount = $total_amount * ( $coupon->get_amount() / 100 );
               }elseif( $coupon->is_type( 'fixed_cart' ) && ! is_null( $cart_item ) && WC()->cart->subtotal_ex_tax ) {
                    $discount_amount = $coupon->get_amount();
               }elseif ( $coupon->is_type( 'fixed_product' ) ) {
                    $discount = min( $total_amount, $coupon->get_amount() );
                    $discount_amount = $single ? $discount : $discount * $items1;
               }    
            }
               // var_dump($coupon);
            $discount_amount = min( $_price, $discount_amount );
            $_price          = max( $_price - $discount_amount, 0 );
            //}
            if ( 0 >= $_price ) {
                break;
            }
        }
        if ( ( $product->get_price() > 0 ) && ( $undiscounted_price !== $_price ) )
            $price = wc_format_sale_price( wc_get_price_to_display( $product, array( 'price' => $undiscounted_price ) ), $_price ) . $product->get_price_suffix();
    }
$total= $price;
}

    return $total;
}


//Global cart subtotal
add_filter('woocommerce_cart_subtotal', 'scd_wcmp_woocommerce_cart_subtotal', 10, 3);
function scd_wcmp_woocommerce_cart_subtotal($cart_subtotal, $compound, $cart)
{
    $items = $cart->get_cart();
    $mysubtot = 0;
    foreach ($items as $cart_item) {
        // Get the product price from the id
        $product = wc_get_product($cart_item['product_id']);
        $vendor_currency = (get_user_meta(get_wcmp_product_vendors($product->get_id())->id, 'scd-user-currency', true));
        if ((get_the_terms($product->get_id(), 'product_type')[0]->slug == "variable") || (get_the_terms($product->get_id(), 'product_type')[0]->slug == "simple")) {
            if (!empty($product)) {
                if ($cart_item['variation_id']) {
                    $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    if ($regprice != "") {
                        $price_html = $regprice;
                        if ($regprice > 0) {
                            if ($saleprice != "") {
                                $price_html = $saleprice;
                            }
                        } else {
                            $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $price_html = $regprice;
                            if ($regprice > 0) {
                                if ($saleprice != "") {
                                    $price_html = $saleprice;
                                }
                            }
                        }
                    } else {
                        $regprice = scd_function_convert_subtotal($cart_item['data']->get_price(), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                        $price_html = $regprice;
                    }
                } else {
                    $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                    if ($regprice != "") {
                        $price_html = $regprice;
                        if ($regprice > 0) {
                            if ($saleprice != "") {
                                $price_html = $saleprice;
                            }
                        } else {
                            $price_html = (check_simple_product_custom_prices($product, true));
                            if ($price_html <= 0) {
                                $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                                $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                                $price_html = $regprice;
                                $regprice = 0;
                                if ($regprice > 0) {
                                    if ($saleprice != "") {
                                        $price_html = $saleprice;
                                    }
                                }
                            }
                        }
                    } else {
                        $regprice = scd_function_convert_subtotal($cart_item['data']->get_price(), scd_get_target_currency(), get_woocommerce_currency(), 1, TRUE);
                        $price_html = $regprice;
                    }
                }
                $qty = $cart_item['quantity'];

                // Add the item price to our computed subtotal
                $unit_price = $price_html * $qty;
                $mysubtot += $unit_price;
            }
        } else {
            $qty = $cart_item['quantity'];
            // Add the item price to our computed subtotal
            $unit_price = scd_function_convert_subtotal($cart_item['data']->get_price(), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE) * $qty;
            $mysubtot += $unit_price;
        }
    }


    if ($mysubtot != 0) {
        $store_currency = get_option('woocommerce_currency');
        $target_currency = scd_get_target_currency();
        $decimals = scd_options_get_decimal_precision();
        $args['currency'] = $target_currency; //function to define
        $args['decimals'] = $decimals;
        $args['price_format'] = scd_change_currency_display_format(get_woocommerce_price_format(), $target_currency);
        $max_sale = scd_vendor_format_converted_price_to_html(floatval($mysubtot), $args);
        $mysubtot = $max_sale;
        return $mysubtot;
    } else {
        return $total;
    }
}



















//Cart item subtotal
add_filter('woocommerce_cart_item_subtotal', 'scd_wcmp_change_cart_item_subtotal_html', 10, 3);
function scd_wcmp_change_cart_item_subtotal_html($subtotal, $cart_item, $cart_item_key)
{
    // Get the product price from the id
    $product = wc_get_product($cart_item['product_id']);
    $vendor_currency = (get_user_meta(get_wcmp_product_vendors($product->get_id())->id, 'scd-user-currency', true));
    if ((get_the_terms($product->get_id(), 'product_type')[0]->slug == "variable") || (get_the_terms($product->get_id(), 'product_type')[0]->slug == "simple")) {
        if (!empty($product)) {
            if ($cart_item['variation_id']) {
                $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $price_html = $regprice;
                if ($regprice != "") {
                    if ($regprice > 0) {
                        if ($saleprice != "") {
                            $price_html = $saleprice;
                        }
                    } else {
                        $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                        $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                        $price_html = $regprice;
                        if ($regprice > 0) {
                            if ($saleprice != "") {
                                $price_html = $saleprice;
                            }
                        }
                    }
                }
            } else {
                $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $price_html = $regprice;
                if ($regprice != "") {
                    if ($regprice > 0) {
                        if ($saleprice != "") {
                            $price_html = $saleprice;
                        }
                    } else {
                        $price_html = (check_simple_product_custom_prices($product, true));
                        if ($price_html <= 0) {
                            $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $price_html = $regprice;
                            $regprice = 0;
                            if ($regprice > 0) {
                                if ($saleprice != "") {
                                    $price_html = $saleprice;
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($price_html != 0) {
            $store_currency = get_option('woocommerce_currency');
            $target_currency = scd_get_target_currency();
            $decimals = scd_options_get_decimal_precision();
            $args['currency'] = $target_currency; //function to define
            $args['decimals'] = $decimals;
            $args['price_format'] = scd_change_currency_display_format(get_woocommerce_price_format(), $target_currency);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($price_html * $cart_item['quantity']), $args);
            $subtotal = $max_sale;
        } else {
            return $subtotal;
        }
    } else {
        return $subtotal;
    }

    return $subtotal;
}





























//Cart Html
add_filter('woocommerce_cart_item_price', 'scd_wcmp_change_product_cart_html', 10, 3);
function scd_wcmp_change_product_cart_html($base_price_html, $cart_item, $cart_item_key)
{
    $product = wc_get_product($cart_item['product_id']);
    $vendor_currency = (get_user_meta(get_wcmp_product_vendors($product->get_id())->id, 'scd-user-currency', true));
    if ((get_the_terms($product->get_id(), 'product_type')[0]->slug == "variable") || (get_the_terms($product->get_id(), 'product_type')[0]->slug == "simple")) {
        // Get the product price from the id
        if (!empty($product)) {
            if ($cart_item['variation_id']) {
                $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $price_html = $regprice;
                if ($regprice != "") {
                    if ($regprice > 0) {
                        if ($saleprice != "") {
                            $price_html = $saleprice;
                        }
                    } else {
                        $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                        $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['variation_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                        $price_html = $regprice;
                        if ($regprice > 0) {
                            if ($saleprice != "") {
                                $price_html = $saleprice;
                            }
                        }
                    }
                }
            } else {
                $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
                $price_html = $regprice;
                if ($regprice != "") {
                    if ($regprice > 0) {
                        if ($saleprice != "") {
                            $price_html = $saleprice;
                        }
                    } else {
                        $price_html = (check_simple_product_custom_prices($product, true));
                        if ($price_html <= 0) {
                            $regprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $saleprice = scd_function_convert_subtotal(get_post_meta($cart_item['product_id'], '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                            $price_html = $regprice;
                            $regprice = 0;
                            if ($regprice > 0) {
                                if ($saleprice != "") {
                                    $price_html = $saleprice;
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($price_html > 0 && $price_html != "") {
            $store_currency = get_option('woocommerce_currency');
            $target_currency = scd_get_target_currency();
            $decimals = scd_options_get_decimal_precision();
            $args['currency'] = $target_currency; //function to define
            $args['decimals'] = $decimals;
            $args['price_format'] = scd_change_currency_display_format(get_woocommerce_price_format(), $target_currency);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($price_html), $args);
            $price_html = sprintf(__('%1$s', 'woocommerce'), $max_sale);
        } else {
            return $base_price_html;
        }
    } else {
        return $price_html;
    }

    return $price_html;
}






















function check_variable_prices($product)
{
    $vendor_currency = (get_user_meta(get_wcmp_product_vendors($product->get_id())->id, 'scd-user-currency', true));
    $store_currency = get_option('woocommerce_currency');
    $target_currency = scd_get_target_currency() ?? $store_currency;
    $decimals = scd_options_get_decimal_precision();
    $args['currency'] = $target_currency; //function to define
    $args['decimals'] = $decimals;
    $args['price_format'] = scd_change_currency_display_format(get_woocommerce_price_format(), $target_currency);
    if ($product->get_children()) {
        $array_price = array();
        $price_html = "";
        foreach ($product->get_children() as $variation_id) {
            $variable_product = wc_get_product($variation_id);

            $regprice = scd_function_convert_subtotal(get_post_meta($variation_id, '_meta_regular_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
            $saleprice = scd_function_convert_subtotal(get_post_meta($variation_id, '_meta_sale_price', TRUE), scd_get_target_currency(), $vendor_currency, 2, TRUE);
            if (($regprice != '') && ($regprice > 0)) {
                if ($saleprice == "") {
                    array_push($array_price, $regprice);
                } else {
                    array_push($array_price, $saleprice);
                }
            } else {
                $regprice = scd_function_convert_subtotal(get_post_meta($variation_id, '_regular_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                $saleprice = scd_function_convert_subtotal(get_post_meta($variation_id, '_sale_price', TRUE), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                if (($regprice != '') && ($regprice > 0)) {
                    if ($saleprice == "") {
                        array_push($array_price, $regprice);
                    } else {
                        array_push($array_price, $saleprice);
                    }
                } else {
                    $regprice = scd_function_convert_subtotal($variable_product->get_regular_price(), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                    $saleprice = scd_function_convert_subtotal($variable_product->get_sale_price(), scd_get_target_currency(), get_woocommerce_currency(), 2, TRUE);
                    if (($regprice != '') && ($regprice > 0)) {
                        if ($saleprice == "") {
                            array_push($array_price, $regprice);
                        } else {
                            array_push($array_price, $saleprice);
                        }
                    }
                }
            }
        }
        if (sizeof($array_price) > 0) {
            (sort($array_price,SORT_NUMERIC));
            $price1 = $array_price[0];
            $price2 = $array_price[sizeof($array_price) - 1];
            if ($price1 == $price2) {
                $max_sale = scd_vendor_format_converted_price_to_html(floatval($price1), $args);
                $price_html = sprintf(__('%1$s', 'woocommerce'), $max_sale);
            } else {
                $max_sale = scd_vendor_format_converted_price_to_html(floatval($price1), $args);
                $min_sale = scd_vendor_format_converted_price_to_html(floatval($price2), $args);
                $price_html = sprintf(__('%1$s - %2$s', 'woocommerce'), $max_sale, $min_sale);
            }
        }
        //}
    }
    return $price_html;
}




















add_filter('woocommerce_get_price_html', 'scd_change_product_html', 10, 2);
function scd_change_product_html($price_html, $product)
{
    $price_html = check_variable_prices($product);
    if ($price_html != "" && $price_html > 0) {
        return $price_html;
    }
    if (!$product->get_children()) {
        $price_html = scd_wcmp_simple_product_display($price_html, $product);
    }
    return $price_html;
}


function scd_wcmp_simple_product_display($price_html, $product)
{
    $vendor_currency = (get_user_meta(get_wcmp_product_vendors($product->get_id())->id, 'scd-user-currency', true));
    $store_currency = get_option('woocommerce_currency');
    $target_currency = scd_get_target_currency() ?? $store_currency;
    $decimals = scd_options_get_decimal_precision();
    $args['currency'] = $target_currency; //function to define
    $args['decimals'] = $decimals;
    $args['price_format'] = scd_change_currency_display_format(get_woocommerce_price_format(), $target_currency);

    if (floatval(get_post_meta($product->get_id(), '_meta_regular_price')[0])) {
        if (floatval(get_post_meta($product->get_id(), '_meta_sale_price')[0])) {
            $converted_min_sale = scd_function_convert_subtotal((get_post_meta($product->get_id(), '_meta_sale_price')[0]), $target_currency, $vendor_currency, 1, TRUE);
            $min_sale = scd_vendor_format_converted_price_to_html(floatval($converted_min_sale), $args);
            $converted_max_sale = scd_function_convert_subtotal((get_post_meta($product->get_id(), '_meta_regular_price')[0]), $target_currency, $vendor_currency, 1, TRUE);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($converted_max_sale), $args);
            $price = sprintf(__('<del>%1$s</del> %2$s', 'woocommerce'), $max_sale, $min_sale);
        } else {
            $converted_max_sale = scd_function_convert_subtotal((get_post_meta($product->get_id(), '_meta_regular_price')[0]), $target_currency, $vendor_currency, 1, TRUE);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($converted_max_sale), $args);
            $price = sprintf(__('%1$s', 'woocommerce'), $max_sale);
        }
        return $price;
    } elseif (floatval(get_post_meta($product->get_id(), '_regular_price')[0])) {
        if (floatval(get_post_meta($product->get_id(), '_sale_price')[0])) {
            $converted_min_sale = scd_function_convert_subtotal((get_post_meta($product->get_id(), '_sale_price')[0]), $target_currency,  $store_currency, 1, TRUE);
            $min_sale = scd_vendor_format_converted_price_to_html(floatval($converted_min_sale), $args);
            $converted_max_sale = scd_function_convert_subtotal((get_post_meta($product->get_id(), '_regular_price')[0]), $target_currency, $store_currency, 1, TRUE);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($converted_max_sale), $args);
            $price = sprintf(__('<del>%1$s</del> %2$s', 'woocommerce'), $max_sale, $min_sale);
        } else {
            $converted_max_sale = scd_function_convert_subtotal((get_post_meta($product->get_id(), '_regular_price')[0]), $target_currency, $store_currency, 1, TRUE);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($converted_max_sale), $args);
            $price = sprintf(__('%1$s', 'woocommerce'), $max_sale);
        }
        return $price;
    } else {
        $converted_max_sale = scd_function_convert_subtotal($product->get_regular_price(), $target_currency, $store_currency, 1, TRUE);
        if ($product->get_sale_price()) {
            $converted_min_sale = scd_function_convert_subtotal($product->get_sale_price(), $target_currency, $store_currency, 1, TRUE);
            $min_sale = scd_vendor_format_converted_price_to_html(floatval($converted_min_sale), $args);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($converted_max_sale), $args);
            $price = sprintf(__('<del>%1$s</del> %2$s', 'woocommerce'), $max_sale, $min_sale);
        } else {
            $converted_max_sale = scd_function_convert_subtotal($product->get_regular_price(), $target_currency, $store_currency, 1, TRUE);
            $max_sale = scd_vendor_format_converted_price_to_html(floatval($converted_max_sale), $args);
            $price = sprintf(__('%1$s', 'woocommerce'), $max_sale);
        }
        return $price;
    }
}


function scd_vendor_format_converted_price_to_html($price, $args)
{

    // Note: This function adds the class 'scd-converted' to the HTML markup element. This class is 
    //       an indication to the javascript that the price has already been converted.

    $unformatted_price = $price;
    $negative          = $price < 0;

    if (apply_filters('woocommerce_price_trim_zeros', false) && $args['decimals'] > 0) {
        $price = wc_trim_zeros($price);
    }

    $dec = get_option('scd_currency_options');
    $dec = $dec['decimalPrecision'];
    //var_dump($args);
    $price = number_format($price, $dec, wc_get_price_decimal_separator(), wc_get_price_thousand_separator());
    $formatted_price = ($negative ? '-' : '') . sprintf($args['price_format'], '<span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol($args['currency']) . '</span>', $price);
    $return          = '<span class="woocommerce-Price-amount amount scd-converted" basecurrency="' . $args['currency'] . '">' . $formatted_price . '</span>';
    if ($args['ex_tax_label'] && wc_tax_enabled()) {
        $return .= ' <small class="woocommerce-Price-taxLabel tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
    }

    return $return;
}
