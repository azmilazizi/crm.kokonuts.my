
<?php 
$inv = '';
$inv_id = '';
$hash = '';
if(isset($invoice)){
	$inv_id = $invoice->id;
	$hash = $invoice->hash;
} 
$is_return_order = false;
if(is_numeric($order->original_order_id)){
	$is_return_order = true;
}

$currency_name = '';
if(isset($base_currency)){
	$currency_name = $base_currency->name;
}
$status = get_status_by_index($order->status);   
?>
<table>
	<tr>
		<td>
			<table>
				<tr>
					<td>
						<?php // Add logo
						echo pdf_logo_url();
						?>
					</td>
					<td class="text-right">
						<strong class="fs-18" >#<?php  echo ( isset($order) ? $order->order_number : ''); ?></strong><br>
						<?php if(isset($order) && $order->seller > 0){ ?>
							<span class="mright15 fs-12"><?php echo _l('seller');  ?>: <?php echo get_staff_full_name($order->seller); ?></span><br>
						<?php } ?>
						<span class="fs-12"><?php echo _l('order_date');  ?>: <?php  echo ( isset($order) ? $order->datecreator : ''); ?></span><br>
						<?php 
						if(!$is_return_order){
							if(isset($invoice)){ ?>
								<span class="fs-12"><?php echo _l('invoice');  ?>: <a href="<?php echo admin_url('invoices#'.$invoice->id) ?>"><?php echo html_entity_decode($order->invoice); ?></a></span><br>
							<?php	}
						}else{ ?>
							<span class="fs-12"><?php echo _l('from_order');  ?>: <a href="<?php echo admin_url('omni_sales/view_order_detailt/'.$order->original_order_id) ?>"><?php echo get_order_number($order->original_order_id); ?></a></span><br>
						<?php }	?>
						<?php 
						if(isset($order)){
							$payment_method =  $order->payment_method_title;
							if($payment_method == ''){
								$data_multi_payment = $this->omni_sales_model->get_order_multi_payment($order->id);
								if($data_multi_payment){
									foreach ($data_multi_payment as $key => $mtpayment) {
										$payment_method .= $mtpayment['payment_name'].', ';
									}
									$payment_method = rtrim($payment_method, ', ');
								}
								else{
									$this->load->model('payment_modes_model');	
									$data_payment = $this->payment_modes_model->get($order->allowed_payment_modes);
									if($data_payment){
										$name = isset($data_payment->name) ? $data_payment->name : '';
										if($name !=''){
											$payment_method = $name;              
										}            
									}
								}
							}	
							if($payment_method != ''){ ?>
								<span class="fs-12"><?php echo _l('payment_method');  ?>: <span class="text-primary"><?php echo html_entity_decode($payment_method); ?></span></span><br>
							<?php }		
						}
						?>

						<?php
						if(isset($order) && $order->estimate_id != null &&  is_numeric($order->estimate_id)){ ?>
							<span class="fs-12"><?php echo _l('estimates');  ?>: <a href="<?php echo admin_url('estimates#'.$order->estimate_id) ?>"><?php echo format_estimate_number($order->estimate_id); ?></a></span><br>
						<?php	}
						?>
						<span class="fs-12"><?php echo _l('status'); ?>: <?php echo _l($status); ?></span>
					</td>
				</tr>
			</table>
			<br>
			<br>
			<table>
				<tr>
					<td>
						<h4 class="fs-12">
							<?php echo _l('customer_details'); ?>
						</h4>
						<hr />
					</td>
					<td>
						<h4 class="fs-12">
							<?php echo _l('billing_address'); ?>
						</h4>
						<hr />
					</td>
					<td>
						<h4 class="fs-12">
							<?php echo _l('shipping_address'); ?>
						</h4>
						<hr />
					</td>
				</tr>
				<tr>
					<td class="fs-12">
						<?php echo (isset($order) ? $order->company : ''); ?><br>
						<?php echo (isset($order) ? $order->phonenumber : ''); ?><br>
						<?php echo (isset($order) ? $order->address : ''); ?><br>
						<?php echo (isset($order) ? $order->city : ''); ?> <?php echo ( isset($order) ? $order->state : ''); ?><br>
						<?php echo isset($order) ? get_country_short_name($order->country) : ''; ?> <?php echo ( isset($order) ? $order->zip : ''); ?>
					</td>
					<td class="fs-12">
						<?php echo isset($order) ? $order->billing_street : ''; ?>
						<br><?php echo isset($order) ? $order->billing_city : ''; ?> <?php echo isset($order) ? $order->billing_state : ''; ?>
						<br><?php echo isset($order) ? get_country_short_name($order->billing_country) : ''; ?> <?php echo isset($order) ? $order->billing_zip : ''; ?>
					</td>
					<td class="fs-12">
						<?php echo isset($order) ? $order->shipping_street : ''; ?>
						<br><?php echo isset($order) ? $order->shipping_city : ''; ?> <?php echo isset($order) ? $order->shipping_state : ''; ?>
						<br><?php echo isset($order) ? get_country_short_name($order->shipping_country) : ''; ?> <?php echo isset($order) ? $order->shipping_zip : ''; ?>
					</td>
				</tr>
			</table>

		</td>
	</tr>
</table>
<br>
<br>
<br>
<!-- Detail -->
<?php
$tax_total_array = [];
$sub_total = 0;
?>

<table class="fs-12" cellpadding="6">
	<tr style="background-color: #000; color: #ffffff;">
		<th width="5%" align="center">#</th>
		<th width="40%" align="center"><?php echo _l('invoice_table_item_heading'); ?></th>
		<th width="10%" align="center" class="qty"><?php echo _l('quantity'); ?></th>
		<th width="15%" align="center"  valign="center"><?php echo _l('price'); ?></th>
		<th width="15%" align="center"  valign="center"><?php echo _l('tax'); ?></th>
		<th width="15%" align="center"><?php echo _l('line_total'); ?></th>
	</tr>

	<?php 
	$sub_total = 0; 
	$date = date('Y-m-d');
	?>

	<?php
	$has_item_tax = false;
	foreach ($order_detait as $key => $item_cart) { 
		if($item_cart['tax']){
			$has_item_tax = true;
		}

		$discount_price = 0;
		$discountss = $this->omni_sales_model->check_discount($item_cart['product_id'], $date);
		if($discountss){
			$discount_percent = $discountss->discount;
			$discount_price += ($discount_percent * $item_cart['prices']) / 100;
			$price_after_dc = $item_cart['prices']-(($discount_percent * $item_cart['prices']) / 100);
			echo form_hidden('discount_price', $discount_price);
		}else{
			$price_after_dc = $item_cart['prices'];
		}
		?>
		<tr class="main">
			<td>
				<?php echo html_entity_decode($key + 1); ?>
			</td>
			<td>
				<?php   
				echo html_entity_decode($item_cart['product_name']);
				?>
			</td>
			<td align="center" class="middle">
				<?php echo html_entity_decode($item_cart['quantity']); ?>
			</td>
			<td align="center" class="middle">
				<?php if($discountss){ ?>
					<strong><?php 
					echo app_format_money($price_after_dc,$currency_name);
				?></strong>
				<p class="price dark-color">
					<span class="old-price"><?php echo app_format_money($item_cart['prices'], $currency_name); ?></span>&nbsp;  
				</p>
			<?php }else{ ?>
				<strong><?php 
				echo app_format_money($price_after_dc,$currency_name);
			?></strong>
		<?php } ?>
	</td>
	<td align="center" class="middle">
		<?php 
		if($item_cart['tax']){
			$list_tax = json_decode($item_cart['tax']);
			$tax_name = '';
			foreach ($list_tax as $tax_item) {
				$tax_name .= $tax_item->name.' ('.$tax_item->rate.'%)<br>'; 
				$array_tax_index = $tax_item->rate.'_'.$tax_item->id;
				if(isset($tax_total_array[$array_tax_index])){
					$old_value_tax = $tax_total_array[$array_tax_index]['value'];

					if($order->discount_type == 1 && $order->add_discount > 0){
						if($order->discount_type_str == 'before_tax'){
							$discount_val = $item_cart['prices'] * $order->add_discount / 100;
							$new_rate = $item_cart['prices'] - $discount_val;

							$tax_item->value = $new_rate * $item_cart['quantity'] * $tax_item->rate/100;
						}
					}

					$tax_total_array[$array_tax_index] = ['value' => ($old_value_tax + $tax_item->value), 'name' => $tax_item->name.' ('.$tax_item->rate.'%)'];
				}
				else{

					if($order->discount_type == 1 && $order->add_discount > 0){
						if($order->discount_type_str == 'before_tax'){
							$discount_val = $item_cart['prices'] * $order->add_discount / 100;
							$new_rate = $item_cart['prices'] - $discount_val;

							$tax_item->value = $new_rate * $item_cart['quantity'] * $tax_item->rate/100;
						}
					}

					$tax_total_array[$array_tax_index] = ['value' => $tax_item->value, 'name' => $tax_item->name.' ('.$tax_item->rate.'%)'];
				}
			}
			echo html_entity_decode($tax_name);														
		}
		?>
	</td>
	<td align="center" class="middle">
		<strong class="line_total_<?php echo html_entity_decode($key); ?>">
			<?php
			$line_total = (int)$item_cart['quantity']*$item_cart['prices'];
			$sub_total += $line_total;
			echo app_format_money($line_total,$currency_name); ?>
		</strong>

	</td>
</tr>
<?php     } ?>
</table>
<br>
<br>
<table class="table text-right fs-12" cellpadding="6">
	<tbody>
		<tr id="subtotal">
			<td width="85%"><span class="bold"><?php echo _l('invoice_subtotal'); ?> :</span>
			</td>
			<td class="subtotal_s" width="15%">
				<?php
				$sub_total = 0;
				if($order->sub_total){
					$sub_total = $order->sub_total;
				}
				echo app_format_money($sub_total,$currency_name); ?>
			</td>
		</tr>
		<?php if($order->discount){
			if($order->discount>0){ ?>
				<tr>
					<td><span class="bold"><?php echo _l('discount'); ?> :</span>
					</td>
					<td>
						<?php

						$price_discount = $order->sub_total * $order->discount/100;
						echo '-'.app_format_money($order->discount,$currency_name); ?>
					</td>
				</tr>
			<?php }	} ?>

			<?php if($order->channel == 'manual'){ ?>
				<?php if(is_sale_discount_applied($order)){ ?>
					<tr>
						<td>
							<span class="bold"><?php echo _l('invoice_discount').' :'; ?>
							<?php if(is_sale_discount($order,'percent')){ ?>
								(<?php echo app_format_number($order->discount_percent,true); ?>%)
								<?php } ?></span>
							</td>
							<td class="discount">
								<?php echo '-' . app_format_money($order->discount_total,$currency_name); ?>
							</td>
						</tr>
					<?php } ?>
				<?php } ?>

				<?php if(is_numeric($order->estimate_id) && $order->estimate_id != 0){ ?>
					<?php if(isset($order) && $tax_data['html_currency'] != ''){
						echo html_entity_decode($tax_data['html_currency']);
					} ?>
				<?php }else{ ?>
					<?php foreach ($tax_total_array as $tax_item_row) {
						?>
						<tr>
							<td><span class="bold"><?php echo html_entity_decode($tax_item_row['name']); ?> :</span>
							</td>
							<td>
								<?php echo app_format_money($tax_item_row['value'],$currency_name); ?>
							</td>
						</tr>
						<?php 
					}
					?>
				<?php } ?>

				<?php if((int)$order->adjustment != 0){ ?>
					<tr>
						<td>
							<span class="bold"><?php echo _l('invoice_adjustment').' :'; ?></span>
						</td>
						<td class="adjustment_t">
							<?php echo app_format_money($order->adjustment,$currency_name); ?>
						</td>
					</tr>
				<?php } ?>
				<?php 
				if(isset($order->shipping_value) && $order->shipping_value != "0.00"){ ?>
					<tr>
						<td><span class="bold"><?php echo _l('shipping'); ?> :</span>
						</td>
						<td>
							<?php echo app_format_money($order->shipping_value,$currency_name); ?>
						</td>
					</tr>
					<?php 	
				}
				else if(isset($order->shipping) && $order->shipping != "0.00"){ ?>
					<tr>
						<td><span class="bold"><?php echo _l('shipping'); ?> :</span>
						</td>
						<td>
							<?php echo app_format_money($order->shipping,$currency_name); ?>
						</td>
					</tr>
				<?php }	?>
				<?php 
				if(isset($order->shipping_tax)){
					if($order->shipping != "0.00"){ ?>
						<tr>
							<td><span class="bold"><?php echo _l('shipping_tax'); ?> :</span>
							</td>
							<td>
								<?php echo app_format_money($order->shipping_tax,$currency_name); ?>
							</td>
						</tr>
					<?php 	}
				}
				?>
				<?php 
				if(!$has_item_tax){ ?>
					<tr>
						<td><span class="bold"><?php echo _l('tax'); ?> :</span>
						</td>
						<td>
							<?php echo app_format_money($order->tax,$currency_name); ?>
						</td>
					</tr>
				<?php } 
				if(is_numeric($order->fee_for_return_order) && $order->fee_for_return_order > 0){ ?>
					<tr id="fee_for_return_order">
						<td><span class="bold"><?php echo _l('omni_fee_for_return_order'); ?> :</span>
						</td>
						<td>
							<?php echo app_format_money($order->fee_for_return_order,$currency_name); ?>
						</td>
					</tr>
				<?php } ?>
				<tr>
					<td><span class="bold"><?php echo _l('invoice_total'); ?> :</span>
					</td>
					<td class="total_s">			                              	
						<?php
						$total_s = $order->total;
						echo app_format_money($total_s,$currency_name); 
						?>
					</td>
				</tr>
				<?php 
				$invoice_id = '';
				if($order->number_invoice != ''){
					$this->load->model('omni_sales/omni_sales_model');
					$invoice_id = $this->omni_sales_model->get_id_invoice($order->number_invoice); 
				}

				if(is_numeric($invoice_id)){
					$this->load->model('invoices_model');
					$invoice = $this->invoices_model->get($invoice_id);
					$total_paid = sum_from_table(db_prefix().'invoicepaymentrecords',array('field'=>'amount','where'=>array('invoiceid'=>$invoice->id)));
				}
				?>

				<?php if(is_numeric($invoice_id)){ ?>
					<tr>
						<td><span class="bold"><?php echo _l('invoice_total_paid'); ?></span></td>
						<td>
							<?php echo app_format_money($total_paid, $invoice->currency_name); ?>
						</td>
					</tr>

					<tr>
						<td><span class="<?php if($invoice->total_left_to_pay > 0){echo 'text-danger ';} ?>bold"><?php echo _l('invoice_amount_due'); ?></span></td>
						<td>
							<span class="<?php if($invoice->total_left_to_pay > 0){echo 'text-danger';} ?>">
								<?php echo app_format_money($invoice->total_left_to_pay, $invoice->currency_name); ?>
							</span>
						</td>
					</tr>
				<?php } ?>

				<?php
				$total_refund = omni_get_total_refund($order->id);
				if($is_return_order){ ?>
					<tr id="total_refund">
						<td><span class="bold"><?php echo _l('omni_total_refund'); ?> :</span>
						</td>
						<td>
							<?php 
							echo app_format_money($total_refund,$currency_name);
							?>
						</td>
					</tr>
					<tr id="total_refund">
						<td><span class="bold"><?php echo _l('omni_amount_due'); ?> :</span>
						</td>
						<td>
							<?php 
							$amount_due_s = $total_s - $total_refund;
							if($amount_due_s < 0){
								$amount_due_s = 0;
							}
							echo app_format_money($amount_due_s,$currency_name);
							?>
						</td>
					</tr>
				<?php } ?>

				<?php if($order->notes != ''){ ?>
					<tr>
						<td><span class="bold"><?php echo _l('note'); ?> :</span></td>
						<td><?php echo html_entity_decode($order->notes); ?></td>
					</tr>
				<?php } ?>
				<?php if($order->duedate != '' && $order->channel_id == 6){ ?>
					<tr>
						<td><span class="bold"><?php echo _l('omni_expiration_date'); ?> :</span></td>
						<td><?php echo _d($order->duedate); ?></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
