<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.profile-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:24px; margin-bottom:20px; }
.owing-display { font-size:36px; font-weight:700; color:#c0392b; line-height:1; }
.owing-display.zero { color:#5cb85c; }
.owing-label { font-size:13px; color:#888; margin-top:4px; }
.pagination-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-8">
            <h4 class="no-margin-top" style="margin-bottom:4px;"><?php echo htmlspecialchars($franchisee['name']); ?></h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/franchise'); ?>">Franchise Settlement</a></li>
                <li class="active"><?php echo htmlspecialchars($franchisee['name']); ?></li>
            </ol>
        </div>
        <?php if (has_permission('loyalty', '', 'edit')): ?>
        <div class="col-sm-4 text-right" style="padding-top:4px;">
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#transfer-modal" <?php echo $outstanding <= 0 ? 'disabled' : ''; ?>>
                <i class="fa fa-money"></i> Record Transfer
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Profile Card -->
    <div class="profile-card">
        <div class="row">
            <div class="col-sm-4">
                <div class="owing-display <?php echo $outstanding <= 0 ? 'zero' : ''; ?>">RM <?php echo number_format($outstanding, 2); ?></div>
                <div class="owing-label">Outstanding — owed to this franchisee</div>
            </div>
            <div class="col-sm-4">
                <table class="table no-margin" style="font-size:14px;">
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;width:90px;">Contact</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($franchisee['contact_person'] ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Phone</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($franchisee['phone'] ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Email</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($franchisee['email'] ?: '—'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-4">
                <table class="table no-margin" style="font-size:14px;">
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;width:90px;">Bank</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($franchisee['bank_name'] ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Account</td>
                        <td style="border:none;padding:4px 0;">
                            <?php echo htmlspecialchars($franchisee['bank_account_name'] ?: '—'); ?>
                            <?php if ($franchisee['bank_account_no']): ?><br><span style="font-size:12px;"><?php echo htmlspecialchars($franchisee['bank_account_no']); ?></span><?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Outlets</td>
                        <td style="border:none;padding:4px 0;">
                            <?php echo empty($outlets) ? '—' : implode(', ', array_map(function ($o) { return htmlspecialchars($o['name']); }, $outlets)); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent unsettled redemptions -->
    <h4>Recent Cashback Redemptions (unsettled)</h4>
    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Outlet</th>
                        <th>Receipt</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($redemptions)): ?>
                    <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">No unsettled redemptions.</td></tr>
                    <?php else: ?>
                    <?php foreach ($redemptions as $r): ?>
                    <tr>
                        <td class="text-muted" style="white-space:nowrap;font-size:12px;"><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($r['customer_name'] ?: $r['customer_phone'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['warehouse_name'] ?: '—'); ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars($r['receipt_number'] ?: '—'); ?></td>
                        <td class="text-right">RM <?php echo number_format((float)$r['points'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Transfer history -->
    <h4 style="margin-top:24px;">Transfer History</h4>
    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-right">Amount</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th>Recorded By</th>
                        <th>Note</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transfers)): ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No transfers recorded yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($transfers as $t): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?php echo date('d/m/Y', strtotime($t['transferred_at'])); ?></td>
                        <td class="text-right"><strong>RM <?php echo number_format((float)$t['amount'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($t['method'] ?: '—'); ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars($t['reference_no'] ?: '—'); ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars(trim(($t['firstname'] ?? '') . ' ' . ($t['lastname'] ?? '')) ?: '—'); ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars($t['note'] ?: '—'); ?></td>
                        <td class="text-right" style="white-space:nowrap;">
                            <?php if (has_permission('loyalty', '', 'delete')): ?>
                            <button class="btn btn-xs btn-danger" onclick="deleteTransfer(<?php echo (int)$t['id']; ?>)" title="Undo — marks the covered redemptions as outstanding again">
                                <i class="fa fa-undo"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($result['page_count'] > 1): ?>
    <div class="text-center" style="margin-top:16px;">
        <ul class="pagination pagination-sm" style="margin:0;">
            <?php for ($p = 1; $p <= $result['page_count']; $p++): ?>
            <li class="<?php echo $p === $result['page'] ? 'active' : ''; ?>">
                <a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Record Transfer Modal -->
<div class="modal fade" id="transfer-modal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Record Transfer to <?php echo htmlspecialchars($franchisee['name']); ?></h4>
            </div>
            <div class="modal-body">
                <p>
                    This will record a transfer of the full outstanding balance,
                    <strong>RM <?php echo number_format($outstanding, 2); ?></strong>,
                    and mark all currently outstanding cashback redemptions at this franchisee's outlets as settled.
                </p>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Transfer Date</label>
                            <input type="date" id="transfer-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Method</label>
                            <select id="transfer-method" class="form-control">
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reference No. / Bank Slip No.</label>
                    <input type="text" id="transfer-reference" class="form-control">
                </div>
                <div class="form-group">
                    <label>Note</label>
                    <textarea id="transfer-note" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="transfer-save-btn" onclick="recordTransfer()">
                    <i class="fa fa-check"></i> Confirm Transfer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';
var FRANCHISEE_ID = <?php echo (int)$franchisee['id']; ?>;

function recordTransfer() {
    var btn = $('#transfer-save-btn').prop('disabled', true);
    $.post(ADMIN_URL + 'loyalty/ajax_record_transfer/' + FRANCHISEE_ID, {
        transferred_at: $('#transfer-date').val(),
        method:         $('#transfer-method').val(),
        reference_no:   $('#transfer-reference').val(),
        note:           $('#transfer-note').val(),
    }, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to record transfer');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

function deleteTransfer(id) {
    if (!confirm('Undo this transfer? The redemptions it covered will become outstanding again.')) return;
    $.post(ADMIN_URL + 'loyalty/ajax_delete_transfer/' + id, function (resp) {
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to undo transfer');
        }
    }, 'json').fail(function () {
        alert('Request failed. Please try again.');
    });
}
</script>

<?php init_tail(); ?>
