<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head();

$s  = $report['shift'];
$diff = (float)$report['difference'];
?>

<style>
.detail-section-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #999; margin-bottom: 10px; }
.meta-row { display: flex; flex-wrap: wrap; gap: 0; margin-bottom: 0; }
.meta-item { min-width: 160px; flex: 1; padding: 10px 16px; border-right: 1px solid #f0f0f0; border-bottom: 1px solid #f0f0f0; }
.meta-item:last-child { border-right: none; }
.meta-label { font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
.meta-value { font-size: 14px; color: #333; font-weight: 500; }
.badge-open   { background: #5cb85c; color: #fff; border-radius: 3px; padding: 3px 9px; font-size: 12px; }
.badge-closed { background: #777;    color: #fff; border-radius: 3px; padding: 3px 9px; font-size: 12px; }
.diff-pos { color: #5cb85c; font-weight: 700; }
.diff-neg { color: #d9534f; font-weight: 700; }
.stat-box { border: 1px solid #e8e8e8; border-radius: 4px; padding: 14px 16px; margin-bottom: 12px; }
.stat-box .stat-label { font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: .4px; }
.stat-box .stat-value { font-size: 22px; font-weight: 700; color: #333; margin-top: 2px; }
.cash-row td { padding: 7px 12px; font-size: 13px; border-top: 1px solid #f5f5f5; }
.cash-row td:last-child { text-align: right; font-weight: 600; min-width: 110px; }
.cash-total td { font-weight: 700; font-size: 15px; border-top: 2px solid #eee !important; }
.movement-in  { color: #5cb85c; }
.movement-out { color: #d9534f; }
</style>

<div id="wrapper">
    <div class="content">

        <!-- breadcrumb -->
        <div style="margin-bottom:16px;">
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('pos/dashboard'); ?>">Dashboard</a></li>
                <li><a href="<?php echo admin_url('pos/shifts'); ?>">Shifts</a></li>
                <li class="active"><?php echo htmlspecialchars($s['shift_code']); ?></li>
            </ol>
        </div>

        <div class="row">

            <!-- ── LEFT COLUMN ──────────────────────────────────── -->
            <div class="col-md-8">

                <!-- Header meta strip -->
                <div class="panel_s" style="margin-bottom:18px;">
                    <div class="panel-body no-padding">
                        <div class="meta-row">
                            <div class="meta-item">
                                <div class="meta-label">Shift Code</div>
                                <div class="meta-value" style="font-family:monospace;"><?php echo htmlspecialchars($s['shift_code']); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Status</div>
                                <div class="meta-value">
                                    <?php if ($s['status'] === 'open'): ?>
                                        <span class="badge-open">Open</span>
                                    <?php else: ?>
                                        <span class="badge-closed">Closed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Store</div>
                                <div class="meta-value"><?php echo htmlspecialchars($s['warehouse_name'] ?? '—'); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Opened</div>
                                <div class="meta-value"><?php echo date('d M Y, H:i', strtotime($s['opened_at'])); ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Closed</div>
                                <div class="meta-value"><?php echo $s['closed_at'] ? date('d M Y, H:i', strtotime($s['closed_at'])) : '—'; ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Opening Float</div>
                                <div class="meta-value"><?php echo number_format((float)$s['opening_float'], 2); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($s['notes'])): ?>
                        <div style="padding:10px 16px;border-top:1px solid #f0f0f0;font-size:13px;color:#555;">
                            <i class="fa fa-sticky-note-o text-muted"></i>&nbsp; <?php echo htmlspecialchars($s['notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sales summary stats -->
                <div class="row" style="margin-bottom:4px;">
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Total Sales</div>
                            <div class="stat-value"><?php echo number_format((float)$report['total_sales'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Net Sales</div>
                            <div class="stat-value"><?php echo number_format((float)$report['net_sales'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Total Refunds</div>
                            <div class="stat-value" style="color:#d9534f;"><?php echo number_format((float)$report['total_refunds'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Transactions</div>
                            <div class="stat-value"><?php echo (int)$report['transaction_count']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-bottom:18px;">
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Discounts</div>
                            <div class="stat-value"><?php echo number_format((float)$report['total_discounts'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Tax</div>
                            <div class="stat-value"><?php echo number_format((float)$report['total_tax'], 2); ?></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Cancelled</div>
                            <div class="stat-value"><?php echo (int)($report['cancelled_count'] ?? 0); ?></div>
                            <div class="stat-label"><?php echo number_format((float)($report['cancelled_amount'] ?? 0), 2); ?></div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="stat-box">
                            <div class="stat-label">Cash Rounding</div>
                            <div class="stat-value"><?php echo number_format((float)($report['cash_rounded'] ?? 0), 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Payment type breakdown -->
                <div class="panel_s" style="margin-bottom:18px;">
                    <div class="panel-body">
                        <div class="detail-section-title">Payment Breakdown</div>
                        <?php if (empty($report['by_payment_type'])): ?>
                        <p class="text-muted text-center">No payment records.</p>
                        <?php else: ?>
                        <table class="table table-condensed" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th class="text-right">Sales</th>
                                    <th class="text-right">Sales Count</th>
                                    <th class="text-right">Refunds</th>
                                    <th class="text-right">Refund Count</th>
                                    <th class="text-right">Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['by_payment_type'] as $pt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pt['payment_name']); ?></td>
                                    <td class="text-right"><?php echo number_format($pt['sales_total'], 2); ?></td>
                                    <td class="text-right text-muted"><?php echo $pt['sales_count']; ?></td>
                                    <td class="text-right text-muted">-<?php echo number_format($pt['refunds_total'], 2); ?></td>
                                    <td class="text-right text-muted"><?php echo $pt['refunds_count']; ?></td>
                                    <td class="text-right"><strong><?php echo number_format($pt['sales_total'] - $pt['refunds_total'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top items -->
                <?php if (!empty($report['top_items'])): ?>
                <div class="panel_s" style="margin-bottom:18px;">
                    <div class="panel-body">
                        <div class="detail-section-title">Top Items</div>
                        <table class="table table-condensed" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-right">Qty Sold</th>
                                    <th class="text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['top_items'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                    <td class="text-right"><?php echo rtrim(rtrim(number_format((float)$item['qty_sold'], 3), '0'), '.'); ?></td>
                                    <td class="text-right"><strong><?php echo number_format((float)$item['revenue'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Hourly breakdown -->
                <?php if (!empty($report['hourly_breakdown'])): ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="detail-section-title">Hourly Breakdown</div>
                        <table class="table table-condensed" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>Hour</th>
                                    <th class="text-right">Transactions</th>
                                    <th class="text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report['hourly_breakdown'] as $h): ?>
                                <tr>
                                    <td><?php echo str_pad($h['hour'], 2, '0', STR_PAD_LEFT) . ':00'; ?></td>
                                    <td class="text-right"><?php echo (int)$h['transactions']; ?></td>
                                    <td class="text-right"><strong><?php echo number_format((float)$h['revenue'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- ── RIGHT COLUMN ─────────────────────────────────── -->
            <div class="col-md-4">

                <!-- Cash reconciliation -->
                <div class="panel_s" style="margin-bottom:18px;">
                    <div class="panel-body no-padding">
                        <div style="padding:12px 16px 8px;"><div class="detail-section-title" style="margin-bottom:0;">Cash Reconciliation</div></div>
                        <table class="table no-margin">
                            <tbody>
                                <tr class="cash-row">
                                    <td class="text-muted">Opening Float</td>
                                    <td><?php echo number_format((float)$s['opening_float'], 2); ?></td>
                                </tr>
                                <tr class="cash-row">
                                    <td class="text-muted">Cash Sales</td>
                                    <td><?php echo number_format((float)$report['cash_sales'], 2); ?></td>
                                </tr>
                                <tr class="cash-row">
                                    <td class="text-muted">Cash Refunds</td>
                                    <td>-<?php echo number_format((float)$report['cash_refunds'], 2); ?></td>
                                </tr>
                                <tr class="cash-row">
                                    <td class="text-muted">Expected Cash</td>
                                    <td><?php echo number_format((float)$report['expected_cash'], 2); ?></td>
                                </tr>
                                <tr class="cash-row">
                                    <td class="text-muted">Actual Cash</td>
                                    <td><?php echo number_format((float)$s['actual_cash'], 2); ?></td>
                                </tr>
                                <tr class="cash-row cash-total">
                                    <td>Difference</td>
                                    <td class="<?php echo $diff > 0 ? 'diff-pos' : ($diff < 0 ? 'diff-neg' : ''); ?>">
                                        <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Cash movements -->
                <div class="panel_s" style="margin-bottom:18px;">
                    <div class="panel-body">
                        <div class="detail-section-title">Cash Movements</div>
                        <?php if (empty($s['cash_movements'])): ?>
                        <p class="text-muted text-center" style="margin:0;">No cash movements recorded.</p>
                        <?php else: ?>
                        <?php foreach ($s['cash_movements'] as $m): ?>
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:6px 0;border-bottom:1px solid #f5f5f5;">
                            <div>
                                <span class="<?php echo $m['type'] === 'pay_in' ? 'movement-in' : 'movement-out'; ?>">
                                    <i class="fa fa-<?php echo $m['type'] === 'pay_in' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                                    <?php echo $m['type'] === 'pay_in' ? 'Pay In' : 'Pay Out'; ?>
                                </span>
                                <?php if (!empty($m['reason'])): ?>
                                <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($m['reason']); ?></div>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:11px;"><?php echo date('H:i', strtotime($m['created_at'])); ?></div>
                            </div>
                            <strong class="<?php echo $m['type'] === 'pay_in' ? 'movement-in' : 'movement-out'; ?>">
                                <?php echo ($m['type'] === 'pay_out' ? '-' : '+') . number_format((float)$m['amount'], 2); ?>
                            </strong>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div style="margin-top:10px;">
                    <a href="<?php echo admin_url('pos/shifts'); ?>" class="btn btn-default btn-block" style="margin-bottom:8px;">
                        <i class="fa fa-arrow-left"></i> Back to Shifts
                    </a>
                    <?php
                    $txn_params = [
                        'shift_id'  => $s['id'],
                        'store'     => $s['warehouse_id'],
                        'date_from' => date('Y-m-d', strtotime($s['opened_at'])),
                        'date_to'   => $s['closed_at'] ? date('Y-m-d', strtotime($s['closed_at'])) : date('Y-m-d'),
                    ];
                    ?>
                    <a href="<?php echo admin_url('pos/transactions?' . http_build_query($txn_params)); ?>" class="btn btn-primary btn-block" style="margin-bottom:8px;">
                        <i class="fa fa-list"></i> View Transactions
                    </a>
                    <?php if (has_permission('pos', '', 'delete')): ?>
                    <button type="button" class="btn btn-danger btn-block" onclick="confirmDelete()">
                        <i class="fa fa-trash"></i> Delete This Shift
                    </button>
                    <?php endif; ?>
                </div>

            </div>
        </div><!-- /row -->
    </div>
</div>

<?php init_tail(); ?>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteShiftModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Delete Shift</h4>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete <strong><?php echo htmlspecialchars($s['shift_code']); ?></strong>?</p>
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
    $('#deleteShiftModal').modal('show');
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Deleting…';
    $.post('<?php echo admin_url('pos/ajax_delete_shift'); ?>', { id: <?php echo (int)$s['id']; ?> }, function (resp) {
        if (resp.success) {
            window.location = '<?php echo admin_url('pos/shifts'); ?>';
        } else {
            $('#deleteShiftModal').modal('hide');
            alert('Failed to delete shift.');
            btn.disabled = false;
            btn.textContent = 'Delete';
        }
    }, 'json').fail(function () {
        $('#deleteShiftModal').modal('hide');
        alert('Request failed.');
        btn.disabled = false;
        btn.textContent = 'Delete';
    });
});
</script>
