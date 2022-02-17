<?php

/* -------------------------------------------------------
   This module contains functions used only for the SCD multivendor functionality.
   It is included by the index.php file.
   ------------------------------------------------------- */
//    add_action('dokan_get_all_cap','scd_dokan_capability');
//    function scd_dokan_capability($capabilities) {
//        $capabilities['menu']['dokan_view_scd_currency_menu']=__( 'View scd currency menu', 'dokan-lite' );
//        return $capabilities;
//    }

function scd_save_product_prices($post_id,$data) {
    //var_dump($data);
    
        $scd_userRole = scd_get_user_role();
        $scd_userID = get_current_user_id();
        $scd_currencyVal = '';
        if (isset($data['scd_currencyVal'])) {
            //if ($_POST['scd_currencyVal'] !== '') {
            $scd_currencyVal = $data['scd_currencyVal'];
            //}
        }

        $priceField = '';
        if (isset($data['priceField'])) {
            if ($data['priceField'] !== '') {
                $priceField = $data['priceField'];
            }
        }
        // save data
        $user_curr= scd_get_user_currency();
            if ($user_curr!==FALSE && isset($data['scd_sale_price'])) {
                $scd_currencyVal=$user_curr;
                $priceField = 'regular_'.$scd_currencyVal.'_'.$data['scd_regular_price'].'-sale_'.$scd_currencyVal.'_'.$data['scd_sale_price'];
        }
        
        $curr_opt= scd_get_user_currency_option();
        if($user_curr!==FALSE && $user_curr!==get_option('woocommerce_currency') && $curr_opt=='only-default-currency'){
             $scd_currencyVal=$user_curr;
                $priceField = 'regular_'.$scd_currencyVal.'_'.$data['regular_price'].'-sale_'.$scd_currencyVal.'_'.$data['sale_price'];
        //save the equivalent price entered by user in base currency
                 $converted=scd_function_convert_subtotal($data['regular_price'], get_option('wocommerce_currency'), $scd_currencyVal , 2,TRUE );
                 
                update_post_meta($post_id,'_regular_price',$converted);
                update_post_meta($post_id,'_meta_regular_price',$data['regular_price']);
             if($data['sale_price']!==''){
                 $converted=scd_function_convert_subtotal($data['sale_price'],get_option('wocommerce_currency'), $scd_currencyVal , 2,TRUE );
              update_post_meta($post_id,'_sale_price',$converted);
              update_post_meta($post_id,'_meta_sale_price',$data['sale_price']);

              update_post_meta($post_id,'_price',$converted);
             } else {
               update_post_meta($post_id,'_price',$converted);   
             }
        }elseif ($user_curr!==FALSE) {
         
        }
        if($priceField!=='')
        update_post_meta($post_id, 'scd_other_options', array(
            "currencyUserID" => $scd_userID,
            "currencyUserRole" => $scd_userRole,
            "currencyVal" => $scd_currencyVal,
            "currencyPrice" => $priceField
        ));
    }
    
    //scd menu in wcfm dashboard
    add_action('wcfm_formeted_menus','scd_wcfm_menus_dashboard',15);
    function scd_wcfm_menus_dashboard($menu){
              $menu['scd-menu-dash'] =  array(
               'label' =>'SCD Currency',
                'url' =>'#',
                'icon' => 'cogs',
	'id' => 'scd-wcfm-menu',
                'priority' => 5
            );
            
    return $menu;
}
    
//wcmp
    add_filter('wcmp_vendor_dashboard_nav', 'scd_wcmp_dashboard_menu',10,1);
    function scd_wcmp_dashboard_menu($vendor_nav) {
     
       $vendor_nav['scd_setting']  = array(
                'label'       => __( 'SCD Currencies', 'dc-woocommerce-multi-vendor' )
                , 'url'         => '#'
                , 'capability'  =>  true 
                , 'position'    => 22
                , 'submenu'     => array()
                , 'link_target' => '_self'
                , 'nav_icon'    => 'wcmp-font ico-store-settings-icon'
            );
        return  $vendor_nav;
    }
      
    add_action('wcmp_afm_product_options_pricing', 'scd_wcmp_pricing_fields', 10, 3);
    function scd_wcmp_pricing_fields($post_id, $post_obect, $post) {

        $regprice = '';
        $saleprice = '';
        $scd_curr = get_post_meta($post_id, 'scd_other_options', true);
        $currencyVal = '';
        if (isset($scd_curr['currencyVal'])) {
            $currencyVal = $scd_curr['currencyVal'];
        }
        $default_curr=false;
        $user_curr = scd_get_user_currency();
        $user_curr_opt = scd_get_user_currency_option();
        if ($user_curr == FALSE || $user_curr_opt == 'selected-currencies') {
            
        } else {
            $curr_symbol = get_woocommerce_currency_symbol($user_curr);
            list($regprice, $saleprice) = scd_get_product_custom_price_for_currency($post->ID, $user_curr);
            if(empty($regprice)){
                $regprice= get_post_meta($post->ID,'_regular_price',true);
                $regprice= $regprice ? scd_function_convert_subtotal($regprice, get_option('wocommerce_currency'), $user_curr , 2) : '';
                $saleprice= get_post_meta($post->ID,'_sale_price',true);
                $saleprice= $saleprice ? scd_function_convert_subtotal($saleprice, get_option('wocommerce_currency'), $user_curr , 2) : '';
            }
            if ($user_curr_opt == 'base-and-default-currency') {
                echo '<div class="form-group">
                    <label class="control-label col-sm-3 col-md-3" for="_scd_regular_price">Regular price (' . $curr_symbol . ')</label>
                    <div class="col-md-6 col-sm-9">
                        <input type="text" id="_scd_regular_price" name="scd_regular_price" value="'.$regprice.'" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="control-label col-sm-3 col-md-3" for="_scd_sale_price">Sale price (' . $curr_symbol . ')</label>
                    <div class="col-md-6 col-sm-9">
                        <input type="text" id="_scd_regular_price" name="scd_sale_price" value="'.$saleprice.'" class="form-control">
                    </div>
                </div>';
            } elseif ($user_curr_opt == 'only-default-currency') {
                $default_curr=true;
                
            } else { //base vurrency
            }
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function () {
                var default_curr="<?php echo $default_curr; ?>";
              
                if(default_curr==1){
                    var curr_sym="<?php echo $curr_symbol; ?>";
                 jQuery('.control-label[for="_regular_price"]').html('Regular price('+curr_sym+')');
                 jQuery('.control-label[for="_sale_price"]').html('Sale price('+curr_sym+')');
                 var regprice="<?php echo $regprice; ?>";
                 var saleprice="<?php echo $saleprice; ?>";
				 
				 if(regprice == saleprice ){
                 jQuery('#_regular_price').val(regprice);
                 //jQuery('#_sale_price').val(saleprice);
				 }
				 else{
			     jQuery('#_regular_price').val(regprice);
                 jQuery('#_sale_price').val(saleprice);
				 }
                 }
                jQuery(".scd_select").data("placeholder", "Set currency per product...").chosen();

            });
        </script>
        <?php
        return $fields;
    }
    //wcmp_process_product_meta_ wcmp_process_product_object
    add_action('wcmp_process_product_meta_simple','scd_wcmp_save_prices',10,2);
    function scd_wcmp_save_prices($post_id,$data) {
       $data['regular_price']=$data['_regular_price'];
       $data['sale_price']=$data['_sale_price'];
       scd_save_product_prices($post_id,$data);
    }

    // //variable product
    add_action('wcmp_afm_after_variation_sku','scd_wcmp_show_prices',10,4);
    /**
     * woocommerce_variation_options_pricing action.
     *
     * @since 2.5.0
     *
     * @param int     $loop
     * @param array   $variation_data
     * @param WP_Post $variation
     */
    function scd_wcmp_show_prices($loop, $variation_data, $variation) {
        $regprice = '';
        $saleprice = '';
        $currencyVal = '';
        $default_curr=false;
        $user_curr = scd_get_user_currency();
        $user_curr_opt = scd_get_user_currency_option();
        if ($user_curr == FALSE || $user_curr_opt == 'selected-currencies') {
            
        } else {
            $curr_symbol = get_woocommerce_currency_symbol($user_curr);
            list($regprice, $saleprice) = scd_get_product_custom_price_for_currency($variation->ID, $user_curr);
            if(empty($regprice)){
            $regprice=get_post_meta($variation->ID,'_regular_price',true);
             $regprice=scd_function_convert_subtotal($regprice, get_option('wocommerce_currency'), $user_curr , 2);
            $saleprice=get_post_meta($variation->ID,'_sale_price',true);
            $saleprice=scd_function_convert_subtotal($saleprice, get_option('wocommerce_currency'), $user_curr , 2);
            }
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function () {
                jQuery('.tab-content .variable_pricing').remove();
            });
            </script>
            <div class="row form-group-row variable_pricings"> 
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label col-md-6"><?php printf( __( 'Regular price (%s)', 'woocommerce' ), get_woocommerce_currency_symbol(scd_get_user_currency()) ); ?></label>
                        <div class="col-md-6">
                        <?php if($regprice){?>
                            <input type="text" class="<?php echo wc_format_localized_price('regular_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_regular_price_<?php echo esc_attr( $loop ); ?>" name="scd_variable_regular_price[<?php echo esc_attr( $loop ); ?>]" value="<?php echo wc_format_localized_price($regprice) ?>" placeholder="<?php echo __( 'Variation price (required)', 'woocommerce' ); ?>">
                        <?php } else{ ?>
                            <input type="text" class="<?php echo wc_format_localized_price('regular_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_regular_price_<?php echo esc_attr( $loop ); ?>" name="scd_variable_regular_price[<?php echo esc_attr( $loop ); ?>]" value="" placeholder="<?php echo __( 'Variation price (required)', 'woocommerce' ); ?>">
                        <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="control-label col-md-6">
                            <?php printf( __( 'Sale price (%s)', 'woocommerce' ), get_woocommerce_currency_symbol(scd_get_user_currency()) ); ?>
                            <a href="#" class="sale_schedule"><?php echo esc_html__( 'Schedule', 'woocommerce' ); ?></a><a href="#" class="cancel_sale_schedule" style="display:none;"><?php echo esc_html__( 'Cancel schedule', 'woocommerce' ); ?></a>
                        </label>
                        <div class="col-md-6">
                            <?php if($saleprice){?>
                                <?php if($saleprice === $regprice){?>
                                    <input type="text" class="<?php echo wc_format_localized_price('sale_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_sale_price<?php echo esc_attr( $loop ); ?>" name="scd_variable_sale_price[<?php echo esc_attr( $loop ); ?>]" value="" placeholder="">
                                <?php } else{ ?>
                                    <input type="text" class="<?php echo wc_format_localized_price('sale_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_sale_price<?php echo esc_attr( $loop ); ?>" name="scd_variable_sale_price[<?php echo esc_attr( $loop ); ?>]" value="<?php echo wc_format_localized_price($saleprice) ?>" placeholder="">
                                <?php } ?>
                            <?php } else{ ?>
                                <input type="text" class="<?php echo wc_format_localized_price('sale_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_sale_price<?php echo esc_attr( $loop ); ?>" name="scd_variable_sale_price[<?php echo esc_attr( $loop ); ?>]" value=" " placeholder="">
                            <?php } ?>
                        </div>
                    </div>
                </div>
                    <input type="hidden" class="<?php echo wc_format_localized_price('regular_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_regular_price_<?php echo esc_attr( $loop ); ?>" name="variable_regular_price[<?php echo esc_attr( $loop ); ?>]" value="<?php echo wc_format_localized_price(scd_function_convert_subtotal($regprice, $user_curr ,  get_option('woocommerce_currency'), 18)) ?>" placeholder="<?php echo __( 'Variation price (required)', 'woocommerce' ); ?>">
                    <?php if($saleprice){?>
                        <input type="hidden" class="<?php echo wc_format_localized_price('sale_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_sale_price<?php echo esc_attr( $loop ); ?>" name="variable_sale_price[<?php echo esc_attr( $loop ); ?>]" value="<?php echo wc_format_localized_price( scd_function_convert_subtotal($saleprice, $user_curr ,  get_option('woocommerce_currency'), 18)) ?>" placeholder="">
                    <?php } else{ ?>
                        <input type="hidden" class="<?php echo wc_format_localized_price('sale_'.$variation->ID.'_scd'); ?> form-control short wc_input_price" id="variable_sale_price<?php echo esc_attr( $loop ); ?>" name="variable_sale_price[<?php echo esc_attr( $loop ); ?>]" value="<?php echo wc_format_localized_price( $saleprice) ?>" placeholder="">
                    <?php } ?>
            </div>
         <?php
     }

    
    //save variations with wcmp 
add_action('woocommerce_save_product_variation', 'scd_wcmp_save_variable_product', 999, 2);

function scd_wcmp_save_variable_product($variation_id, $i) {
    if(!isset($_POST['submit-data'])){
    //if (!isset($_POST['action']) || (isset($_POST['action']) && $_POST['action'] !== 'woocommerce_save_variations'))
    //     return;
    $priceField = '';
    $scd_userRole = scd_get_user_role();
    $scd_userID = get_current_user_id();
    $variation_ids = array();
    $data = array();
    //parse_str( $_POST['formdata'], $data );
    $data = $_POST;
    $variable_post_id = isset($data['variable_post_id']) ? $data['variable_post_id'] : $variation_ids;
    $max_loop = max(array_keys($variable_post_id));

    $variable_regular_price = isset($data['scd_variable_regular_price']) ? $data['scd_variable_regular_price'] : array();
    $variable_sale_price = isset($data['scd_variable_sale_price']) ? $data['scd_variable_sale_price'] : array();

    $regular_price = wc_format_decimal($variable_regular_price[$i]);
    $sale_price = ( $variable_sale_price[$i] === '' ? '' : wc_format_decimal($variable_sale_price[$i]) );

    // save data
    $user_curr = scd_get_user_currency();
    $curr_opt = scd_get_user_currency_option();
    if ($user_curr !== FALSE && $curr_opt == 'only-default-currency') {
        $scd_currencyVal = $user_curr;
        $priceField = 'regular_' . $scd_currencyVal . '_' . $regular_price . '-sale_' . $scd_currencyVal . '_' . $sale_price;
        //save the equivalent price entered by user in base currency
        $converted = scd_function_convert_subtotal($regular_price, get_option('wocommerce_currency'), $scd_currencyVal, 2, TRUE);
        update_post_meta($variation_id, '_regular_price', $converted);
        update_post_meta($variation_id,'_meta_regular_price',$regular_price);
        if ($sale_price !== '') {
            $converted = scd_function_convert_subtotal($sale_price, get_option('wocommerce_currency'), $scd_currencyVal, 2, TRUE);
            update_post_meta($variation_id, '_sale_price', $converted);
            update_post_meta($variation_id,'_meta_sale_price',$sale_price);

            update_post_meta($variation_id, '_price', $converted);
        } else {
            update_post_meta($variation_id, '_price', $converted);
        }
    }
    if ($priceField !== '')
        update_post_meta($variation_id, 'scd_other_options', array(
            "currencyUserID" => $scd_userID,
            "currencyUserRole" => $scd_userRole,
            "currencyVal" => $scd_currencyVal,
            "currencyPrice" => $priceField
        ));
    }
}
