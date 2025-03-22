<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div id="leave-reports" class="hide reports_fr">
   <table class="table table-leave-report scroll-responsive">
      <thead>
         <tr>
            <th><?php echo _l('staffid'); ?></th>
            <th><?php echo _l('full_name'); ?></th>
            <th><?php echo _l('total_annual_leave'); ?></th>
            <?php
               $list_month = [];
               $list_month['01'] = _l('month_1');
               $list_month['02'] = _l('month_2');
               $list_month['03'] = _l('month_3');
               $list_month['04'] = _l('month_4');
               $list_month['05'] = _l('month_5');
               $list_month['06'] = _l('month_6');
               $list_month['07'] = _l('month_7');
               $list_month['08'] = _l('month_8');
               $list_month['09'] = _l('month_9');
               $list_month['10'] = _l('month_10');
               $list_month['11'] = _l('month_11');
               $list_month['12'] = _l('month_12');
               $date_data = $this->timesheets_model->get_date_leave(date('Y')); 
               $list_date = $this->timesheets_model->get_list_month($date_data->from_date, $date_data->ending_date);
               foreach($list_date as $date){
                  $explode = explode('-', $date); ?>
                  <th><?php echo html_entity_decode($list_month[$explode[1]]); ?></th>
               <?php } ?>
            <th><?php echo _l('the_total_was_off'); ?></th>
            <th><?php echo _l('number_leave_days_allowed'); ?></th>
         </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
         <td></td>
      </tfoot>
   </table>
</div>
