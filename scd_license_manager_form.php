<?php

/* ------------------------------------------------------------------------
   This module handles the operations of the SCD License Manager tab
   ------------------------------------------------------------------------ */ 

/**
 * Render setting: License Key
 */ 
function scd_form_render_license() {

    $key = get_option('scd_license_key');
    
    ?>
    
    <input id="scd_license" name='scd_license_manager[scd_license_key]' style="margin-left: 20px; margin-top: 3px; width:300px" type='text' value='<?php echo base64_decode($key); ?>' readonly/>

    <?php
}

/**
 * Render setting: License Expiry Date
 */ 
function scd_form_render_license_expiry() {

    $expiry = get_option('scd_license_expiry_date');

    ?>
    
    <input id="scd_license_expiry" name='scd_license_manager[scd_license_expiry]' style="margin-left: 20px; margin-top: 3px; width:300px" type='text' value='<?php echo base64_decode($expiry); ?>' readonly/>

    <?php
    if(!empty($expiry)){
        $todaydate = new DateTime(date('Y-m-d'));
        $enddate = new DateTime(base64_decode($expiry));
        if($todaydate < $enddate) {
            $daysLeft = $todaydate->diff($enddate)->days;
        } else {
            $daysLeft = 0;
        }
    ?>

        <div class="scd-pp" style="margin-left: 20px; margin-top: 10px">
            <p>License validity time left : <strong><?php echo $daysLeft ?> days </strong><p>
        </div>

        <?php
            if($daysLeft >= 0 && $daysLeft<31){
                if($daysLeft == 0){
                    $msg = "Your license has expired.";
                }
                else{
                    $msg = "Your license will expire in ".$daysLeft. " days.";
                }
                ?>
                <div class="scd-notice" style="margin-left: 20px; margin-top: 10px">
                    <p> <?php echo $msg ?>
                    Please <a href="https://gajelabs.com/product/scd/" target="_blank"> update your license </a> to continue to benefit SCD features. </p>
                </div>
                <?php
            }
        ?>

    <?php
    }
    ?>

    <?php
}




   ?>
