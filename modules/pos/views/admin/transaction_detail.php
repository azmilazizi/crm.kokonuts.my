<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head();

$r = $receipt;

if (!empty($r['cancelled_at'])) {
    $type_label = 'Cancelled'; $type_class = 'danger';
} elseif (!empty($r['refund_for'])) {
    $type_label = 'Return'; $type_class = 'warning';
} else {
    $type_label = 'Sale'; $type_class = 'success';
}
?>

<style>
.detail-section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: #999;
    margin-bottom: 10px;
}
.meta-row { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 0; }
.meta-item { min-width: 180px; flex: 1; padding: 10px 16px; border-right: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; }
.meta-item:last-child { border-right: none; }
.meta-label { font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
.meta-value { font-size: 14px; color: #333; font-weight: 500; }
.totals-row td { padding: 6px 12px; font-size: 13px; }
.totals-row td:last-child { text-align: right; min-width: 100px; }
.grand-total td { font-weight: 700; font-size: 15px; border-top: 2px solid #eee; }
.modifier-tag { display: inline-block; background: #f0f4ff; color: #5b7ed6; border-radius: 3px; font-size: 11px; padding: 1px 6px; margin: 1px 2px 1px 0; }
</style>

<div id="wrapper">
    <div class="content">

        <!-- breadcrumb -->
        <div style="margin-bottom:16px;">
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('pos/dashboard'); ?>">Dashboard</a></li>
                <li><a href="<?php echo admin_url('pos/transactions'); ?>">Transactions</a></li>
                <li class="active"><?php echo htmlspecialchars($r['receipt_number']); ?></li>
            </ol>
        </div>

        <div class="row">

            <!-- ── LEFT COLUMN ────────────────────────────────── -->
            <div class="col-md-8">

                <!-- Receipt header meta -->
                <div class="panel_s" style="margin-bottom:18px;">
                    <div class="panel-body no-padding">
                        <div class="meta-row">
                            <div class="meta-item">
                                <div class="meta-label">Receipt No.</div>
                                <div class="meta-value" style="font-family:monospace;font-size:13px;"><?php echo htmlspecialchars($r['receipt_number']); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Date &amp; Time</div>
                                <div class="meta-value"><?php echo date('d M Y, H:i', strtotime($r['receipt_date'])); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Type</div>
                                <div class="meta-value">
                                    <span class="label label-<?php echo $type_class; ?>"><?php echo $type_label; ?></span>
                                    <?php if (!empty($r['refund_for'])): ?>
                                    <span class="text-muted" style="font-size:11px;"> for <?php echo htmlspecialchars($r['refund_for']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Order Type</div>
                                <div class="meta-value"><?php echo htmlspecialchars($r['dining_option'] ?: '—'); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Store</div>
                                <div class="meta-value"><?php echo htmlspecialchars($r['warehouse_name'] ?? '—'); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Employee</div>
                                <div class="meta-value"><?php echo htmlspecialchars($r['employee_name'] ?? '—'); ?></div>
                            </div>
                            <?php if (!empty($r['shift_id'])): ?>
                            <div class="meta-item">
                                <div class="meta-label">Shift</div>
                                <div class="meta-value">#<?php echo $r['shift_id']; ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($r['note'])): ?>
                            <div class="meta-item" style="flex-basis:100%;border-right:none;">
                                <div class="meta-label">Note</div>
                                <div class="meta-value"><?php echo htmlspecialchars($r['note']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Line items -->
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="detail-section-title">Items</div>
                        <table class="table table-hover" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center" style="width:60px;">Qty</th>
                                    <th class="text-right" style="width:90px;">Unit Price</th>
                                    <th class="text-right" style="width:90px;">Modifiers</th>
                                    <th class="text-right" style="width:80px;">Discount</th>
                                    <th class="text-right" style="width:80px;">Tax</th>
                                    <th class="text-right" style="width:90px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($r['line_items'])): ?>
                                <tr><td colspan="7" class="text-center text-muted">No items recorded.</td></tr>
                                <?php else: ?>
                                <?php foreach ($r['line_items'] as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                        <?php if (!empty($item['variant_name'])): ?>
                                        <span class="text-muted" style="font-size:12px;"> — <?php echo htmlspecialchars($item['variant_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['modifier_names'])): ?>
                                        <div style="margin-top:4px;">
                                            <?php foreach ($item['modifier_names'] as $mod): ?>
                                            <span class="modifier-tag"><?php echo htmlspecialchars($mod); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($item['line_note'])): ?>
                                        <div class="text-muted" style="font-size:11px;margin-top:2px;"><i class="fa fa-comment-o"></i> <?php echo htmlspecialchars($item['line_note']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?php echo rtrim(rtrim(number_format((float)$item['quantity'], 3), '0'), '.'); ?></td>
                                    <td class="text-right"><?php echo number_format((float)$item['unit_price'], 2); ?></td>
                                    <td class="text-right text-muted">
                                        <?php echo (float)$item['modifiers_price'] != 0 ? '+' . number_format((float)$item['modifiers_price'], 2) : number_format(0, 2); ?>
                                    </td>
                                    <td class="text-right text-muted">
                                        <?php echo (float)$item['total_discount'] != 0 ? '-' . number_format((float)$item['total_discount'], 2) : number_format(0, 2); ?>
                                    </td>
                                    <td class="text-right text-muted"><?php echo number_format((float)$item['total_tax'], 2); ?></td>
                                    <td class="text-right"><strong><?php echo number_format((float)$item['total_money'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- ── RIGHT COLUMN ───────────────────────────────── -->
            <div class="col-md-4">

                <!-- Payment breakdown -->
                <div class="panel_s" style="margin-bottom:18px;">
                    <div class="panel-body">
                        <div class="detail-section-title">Payment</div>
                        <?php if (empty($r['payments'])): ?>
                        <p class="text-muted text-center">No payment records.</p>
                        <?php else: ?>
                        <table class="table table-condensed no-margin">
                            <tbody>
                                <?php foreach ($r['payments'] as $pay): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pay['payment_name']); ?></td>
                                    <td class="text-right"><strong><?php echo number_format((float)$pay['money_amount'], 2); ?></strong></td>
                                </tr>
                                <?php if ((float)$pay['cash_back'] > 0): ?>
                                <tr>
                                    <td class="text-muted" style="padding-left:20px;">Change</td>
                                    <td class="text-right text-muted"><?php echo number_format((float)$pay['cash_back'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order totals -->
                <div class="panel_s">
                    <div class="panel-body no-padding">
                        <table class="table no-margin">
                            <tbody class="totals-row">
                                <tr>
                                    <td>Subtotal</td>
                                    <td><?php echo number_format((float)$r['subtotal'], 2); ?></td>
                                </tr>
                                <?php if (!empty($r['grabfood_price'])): $gfp = $r['grabfood_price']; ?>
                                <?php if ($gfp['delivery_fee'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Delivery Fee</td>
                                    <td class="text-muted"><?php echo number_format($gfp['delivery_fee'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($gfp['service_charge_fee'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Service Charge Fee</td>
                                    <td class="text-muted"><?php echo number_format($gfp['service_charge_fee'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($gfp['small_order_fee'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Small Order Fee</td>
                                    <td class="text-muted"><?php echo number_format($gfp['small_order_fee'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($gfp['merchant_charge_fee_min'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Merchant Charge Fee</td>
                                    <td class="text-muted"><?php echo number_format($gfp['merchant_charge_fee_min'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if ((float)$r['total_discount'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Discount</td>
                                    <td class="text-muted">-<?php echo number_format((float)$r['total_discount'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ((float)$r['total_tax'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Tax</td>
                                    <td class="text-muted"><?php echo number_format((float)$r['total_tax'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ((float)$r['tip'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Tip</td>
                                    <td class="text-muted"><?php echo number_format((float)$r['tip'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ((float)$r['surcharge'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Surcharge</td>
                                    <td class="text-muted"><?php echo number_format((float)$r['surcharge'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="grand-total">
                                    <td>Total</td>
                                    <td><?php echo number_format((float)$r['total_money'], 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="text-center" style="margin-top:10px;">
                    <a href="<?php echo admin_url('pos/transactions'); ?>" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Back to Transactions
                    </a>
                    <?php if (has_permission('pos', '', 'delete')): ?>
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete()">
                        <i class="fa fa-trash"></i> Delete
                    </button>
                    <?php endif; ?>
                </div>

            </div>
        </div><!-- /row -->
    </div>
</div>

<?php init_tail(); ?>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteTxnModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Delete Transaction</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete <strong><?php echo htmlspecialchars($r['receipt_number']); ?></strong>?</p>
                <p class="text-danger" style="font-size:12px;"><i class="fa fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete() {
    $('#deleteTxnModal').modal('show');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Deleting…';

    $.post('<?php echo admin_url('pos/ajax_delete_transaction'); ?>', { id: <?php echo (int)$r['id']; ?> }, function (resp) {
        if (resp.success) {
            window.location = '<?php echo admin_url('pos/transactions'); ?>';
        } else {
            $('#deleteTxnModal').modal('hide');
            alert('Failed to delete transaction.');
            btn.disabled = false;
            btn.textContent = 'Delete';
        }
    }, 'json').fail(function () {
        $('#deleteTxnModal').modal('hide');
        alert('Request failed.');
        btn.disabled = false;
        btn.textContent = 'Delete';
    });
});
</script>
</body>
</html>
