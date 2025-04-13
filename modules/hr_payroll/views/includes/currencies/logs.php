<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div  class="row">
	<div class="row">    
      <div class="_buttons col-md-12">
			<hr>
        <div class="col-md-3">
        	<?php echo render_select('from_currency_logs',$currencies,array('id','name'),'hrp_from_currency', '', array('multiple' => true, 'data-actions-box' => true), array(), '', '', false); ?>
        </div>
        <div class="col-md-3">
        	<?php echo render_select('to_currency_logs',$currencies,array('id','name'),'hrp_to_currency', '', array('multiple' => true, 'data-actions-box' => true), array(), '', '', false); ?>
        </div>
        <div class="col-md-3">
        	<?php echo render_date_input('date','date_created', _d(date('Y-m-d'))); ?>
        </div>
    </div>
  </div>
	<div class="clearfix"></div>
	<br>
	<div class="clearfix"></div>
	<div  class="col-md-12">
		<table class="table table-currency-rate-logs scroll-responsive">
			<thead>
				<tr>
					<th><?php echo _l('hrp_type'); ?></th>
					<th><?php echo _l('hrp_currency_rate'); ?></th>
					<th><?php echo _l('date_created'); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
			<tfoot>
				<td></td>
				<td></td>
				<td></td>
			</tfoot>
		</table>
	</div>
</div>


<div id="modal_wrapper"></div>

