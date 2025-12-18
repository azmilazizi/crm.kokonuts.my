			$purchase_shipping_fee = 0;
			if (get_option('acc_pur_shipping_automatic_conversion') == 1 && get_status_modules_wh('purchase') && (int) $goods_receipt->pr_order_id > 0) {
				$this->load->model('purchase/purchase_model');
				$purchase_order = $this->purchase_model->get_pur_order($goods_receipt->pr_order_id);
				if ($purchase_order) {
					$purchase_shipping_fee = (float) ($purchase_order->shipping_fee ?? 0);
				}
			}

			$shipping_history_added = false;

				if (!$shipping_history_added && get_option('acc_pur_shipping_automatic_conversion') == 1 && $purchase_shipping_fee > 0) {
					$total_shipping = round($purchase_shipping_fee / $currency_rate, 2);

					$node = [];
					$node['split'] = $shipping_payment_account;
					$node['account'] = $shipping_deposit_to;
					$node['tax'] = 0;
					$node['item'] = $item_id;
					$node['date'] = $goods_receipt->date_c;
					$node['debit'] = $total_shipping;
					$node['credit'] = 0;
					$node['description'] = '';
					$node['rel_id'] = $stock_import_id;
					$node['rel_type'] = 'purchase_shipping';
					$node['datecreated'] = date('Y-m-d H:i:s');
					$node['addedfrom'] = get_staff_user_id();
					$data_insert[] = $node;

					$node = [];
					$node['split'] = $shipping_deposit_to;
					$node['date'] = $goods_receipt->date_c;
					$node['account'] = $shipping_payment_account;
					$node['tax'] = 0;
					$node['item'] = $item_id;
					$node['debit'] = 0;
					$node['credit'] = $total_shipping;
					$node['description'] = '';
					$node['rel_id'] = $stock_import_id;
					$node['rel_type'] = 'purchase_shipping';
					$node['datecreated'] = date('Y-m-d H:i:s');
					$node['addedfrom'] = get_staff_user_id();
					$data_insert[] = $node;

					$shipping_history_added = true;
				}
