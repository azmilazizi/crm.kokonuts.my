<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
    <div class="col-md-6">
        <table class="table table-condensed no-mbot">
            <tbody>
                <tr>
                    <td class="bold"><?php echo _l('draft_name'); ?></td>
                    <td><?php echo pur_html_entity_decode($draft['order_name']); ?></td>
                </tr>
                <?php if (!empty($draft['order_number'])) { ?>
                <tr>
                    <td class="bold"><?php echo _l('order_number_lable'); ?></td>
                    <td><?php echo pur_html_entity_decode($draft['order_number']); ?></td>
                </tr>
                <?php } ?>
                <?php if (!empty($draft['vendor_name'])) { ?>
                <tr>
                    <td class="bold"><?php echo _l('vendor'); ?></td>
                    <td><?php echo pur_html_entity_decode($draft['vendor_name']); ?></td>
                </tr>
                <?php } ?>
                <?php if (!empty($draft['vendor_code'])) { ?>
                <tr>
                    <td class="bold"><?php echo _l('vendor_code'); ?></td>
                    <td><?php echo pur_html_entity_decode($draft['vendor_code']); ?></td>
                </tr>
                <?php } ?>
                <?php if (!empty($draft['order_date'])) { ?>
                <tr>
                    <td class="bold"><?php echo _l('order_date'); ?></td>
                    <td><?php echo _d($draft['order_date']); ?></td>
                </tr>
                <?php } ?>
                <tr>
                    <td class="bold"><?php echo _l('payment_status'); ?></td>
                    <td>
                        <?php if ($draft['is_paid']) { ?>
                            <span class="label label-success"><?php echo _l('paid'); ?></span>
                        <?php } else { ?>
                            <span class="label label-default"><?php echo _l('unpaid'); ?></span>
                        <?php } ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="col-md-6">
        <table class="table table-condensed no-mbot">
            <tbody>
                <tr>
                    <td class="bold"><?php echo _l('subtotal'); ?></td>
                    <td class="text-right"><?php echo app_format_money((float) $draft['items_subtotal'], $currency->symbol); ?></td>
                </tr>
                <?php if ((float) $draft['total_discount'] > 0) { ?>
                <tr>
                    <td class="bold"><?php echo _l('total_discount'); ?></td>
                    <td class="text-right"><?php echo app_format_money((float) $draft['total_discount'], $currency->symbol); ?></td>
                </tr>
                <?php } ?>
                <?php if ((float) $draft['shipping_fee'] > 0) { ?>
                <tr>
                    <td class="bold"><?php echo _l('pur_shipping_fee'); ?></td>
                    <td class="text-right"><?php echo app_format_money((float) $draft['shipping_fee'], $currency->symbol); ?></td>
                </tr>
                <?php } ?>
                <tr>
                    <td class="bold"><?php echo _l('grand_total'); ?></td>
                    <td class="text-right"><strong><?php echo app_format_money((float) $draft['grand_total'], $currency->symbol); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($draft['items'])) { ?>
<hr>
<h5 class="bold"><?php echo _l('items'); ?></h5>
<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th><?php echo _l('item'); ?></th>
                <th><?php echo _l('description'); ?></th>
                <th class="text-center"><?php echo _l('qty'); ?></th>
                <th class="text-right"><?php echo _l('subtotal'); ?></th>
                <?php if (!empty(array_filter(array_column($draft['items'], 'discount'), function($v) { return (float)$v > 0; }))) { ?>
                <th class="text-right"><?php echo _l('discount'); ?></th>
                <?php } ?>
                <th class="text-right"><?php echo _l('total'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            $has_discount = !empty(array_filter(array_column($draft['items'], 'discount'), function($v) { return (float)$v > 0; }));
            foreach ($draft['items'] as $item) {
            ?>
            <tr>
                <td><?php echo pur_html_entity_decode($item['inventory_item_name'] ?? ''); ?></td>
                <td><?php echo pur_html_entity_decode($item['description'] ?? ''); ?></td>
                <td class="text-center"><?php echo (float) $item['quantity']; ?></td>
                <td class="text-right"><?php echo app_format_money((float) $item['subtotal'], $currency->symbol); ?></td>
                <?php if ($has_discount) { ?>
                <td class="text-right"><?php echo app_format_money((float) $item['discount'], $currency->symbol); ?></td>
                <?php } ?>
                <td class="text-right"><?php echo app_format_money((float) $item['total'], $currency->symbol); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>

<?php if (!empty($draft['payments'])) { ?>
<hr>
<h5 class="bold"><?php echo _l('payments'); ?></h5>
<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th><?php echo _l('date'); ?></th>
                <th><?php echo _l('payment_mode'); ?></th>
                <th class="text-right"><?php echo _l('amount'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($draft['payments'] as $payment) { ?>
            <tr>
                <td><?php echo !empty($payment['payment_date']) ? _d($payment['payment_date']) : '-'; ?></td>
                <td><?php echo pur_html_entity_decode($payment['initial_payment_mode_label'] ?? '-'); ?></td>
                <td class="text-right"><?php echo app_format_money((float) $payment['amount'], $currency->symbol); ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php } ?>
