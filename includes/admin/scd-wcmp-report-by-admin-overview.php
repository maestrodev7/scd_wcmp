<?php

add_action('wcmp_report_admin_overview','scd_wcmp_report_admin_overview',10,1);
function scd_wcmp_report_admin_overview($data){
    include 'class-scd-wcmp-report-by-admin-overview.php';
    $wcmp_report_admin = new SCD_WCMp_Report_Admin_overview();
    $wcmp_report_admin->output_report();
}