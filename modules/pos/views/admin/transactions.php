<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.txn-table tbody tr { cursor: pointer; }
.txn-table tbody tr:hover { background: #f5f9ff; }
.badge-sale      { background: #5cb85c; color: #fff; }
.badge-return    { background: #f0ad4e; color: #fff; }
.badge-cancelled { background: #d9534f; color: #fff; }
.filter-bar { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 12px 16px; margin-bottom: 18px; }
.pagination-wrap { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
th.sortable { cursor: pointer; user-select: none; }
th.sortable:hover { background: #f0f4ff; }
th.sortable::after { content: ' \2195'; color: #ccc; font-size: 10px; }
th.sort-asc::after  { content: ' \25B2'; color: #337ab7; font-size: 10px; }
th.sort-desc::after { content: ' \25BC'; color: #337ab7; font-size: 10px; }
</style>

<div id="wrapper">
    <div class="content">

        <!-- ── TOOLBAR ─────────────────────────────────────────── -->
        <div class="row" style="margin-bottom:16px;">
            <div class="col-sm-6">
                <h4 class="no-margin-top" style="margin-bottom:4px;">Transactions</h4>
                <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                    <li><a href="<?php echo admin_url('pos/dashboard'); ?>">Dashboard</a></li>
                    <li class="active">Transactions</li>
                </ol>
            </div>
            <div class="col-sm-6 text-right" style="padding-top:4px;">
                <a href="<?php echo admin_url('pos/export_transactions_csv?' . http_build_query([
                    'store'     => $filters['warehouse_id'],
                    'date_from' => $filters['date_from'],
                    'date_to'   => $filters['date_to'],
                    'q'         => $filters['search'],
                ])); ?>" class="btn btn-default btn-sm">
                    <i class="fa fa-upload"></i> Export to CSV
                </a>
            </div>
        </div>

        <!-- ── FILTER BAR ──────────────────────────────────────── -->
        <form method="GET" action="<?php echo admin_url('pos/transactions'); ?>" id="filter-form">
        <div class="filter-bar">
            <div class="row">
                <div class="col-md-3">
                    <select name="store" class="form-control input-sm selectpicker" data-live-search="true" title="All Stores" onchange="this.form.submit()">
                        <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo (int)$w['id']; ?>" <?php echo (int)$filters['warehouse_id'] === (int)$w['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($w['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                <div class="col-md-3">
                    <div class="input-group input-group-sm">
                        <input type="text" name="q" class="form-control" placeholder="Search receipt number…" value="<?php echo htmlspecialchars($filters['search']); ?>">
                        <span class="input-group-btn">
                            <button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
                        </span>
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="limit" class="form-control input-sm" onchange="this.form.submit()">
                        <?php foreach ([10, 20, 50, 100] as $l): ?>
                        <option value="<?php echo $l; ?>" <?php echo (int)$filters['limit'] === $l ? 'selected' : ''; ?>><?php echo $l; ?> per page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <input type="hidden" name="page" id="page-input" value="1">
        <input type="hidden" name="sort" id="sort-input" value="<?php echo htmlspecialchars($filters['sort']); ?>">
        <input type="hidden" name="dir"  id="dir-input"  value="<?php echo htmlspecialchars($filters['dir']); ?>">
        </form>

        <!-- ── RESULTS INFO ────────────────────────────────────── -->
        <div class="row" style="margin-bottom:10px;">
            <div class="col-sm-6">
                <span class="text-muted" style="font-size:13px;">
                    Page <strong><?php echo $result['page']; ?></strong> of <strong><?php echo max(1, $result['page_count']); ?></strong>
                    &nbsp;|&nbsp; Found <strong><?php echo number_format($result['total']); ?></strong> records
                </span>
            </div>
            <div class="col-sm-6 text-right">
                <div class="pagination-wrap" style="justify-content:flex-end;">
                    <?php if ($result['page'] > 1): ?>
                    <button class="btn btn-default btn-sm" onclick="goPage(<?php echo $result['page'] - 1; ?>)">
                        <i class="fa fa-chevron-left"></i>
                    </button>
                    <?php endif; ?>
                    <span class="text-muted" style="font-size:12px;">Page <?php echo $result['page']; ?></span>
                    <?php if ($result['page'] < $result['page_count']): ?>
                    <button class="btn btn-default btn-sm" onclick="goPage(<?php echo $result['page'] + 1; ?>)">
                        <i class="fa fa-chevron-right"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── TABLE ───────────────────────────────────────────── -->
        <div class="panel_s">
            <div class="panel-body no-padding">
                <table class="table table-hover no-margin txn-table">
                    <thead>
                        <tr>
                            <?php $s = $filters['sort']; $d = $filters['dir']; ?>
                            <th class="sortable <?php echo $s==='receipt_date'   ? 'sort-'.$d : ''; ?>" onclick="sortBy('receipt_date')">Time</th>
                            <th>No.</th>
                            <th class="sortable <?php echo $s==='warehouse_name' ? 'sort-'.$d : ''; ?>" onclick="sortBy('warehouse_name')">Store</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Order Type</th>
                            <th class="text-right sortable <?php echo $s==='subtotal'       ? 'sort-'.$d : ''; ?>" onclick="sortBy('subtotal')">Subtotal</th>
                            <th class="text-right sortable <?php echo $s==='total_discount' ? 'sort-'.$d : ''; ?>" onclick="sortBy('total_discount')">Discount</th>
                            <th class="text-right sortable <?php echo $s==='total_tax'      ? 'sort-'.$d : ''; ?>" onclick="sortBy('total_tax')">Tax</th>
                            <th class="text-right sortable <?php echo $s==='total_money'    ? 'sort-'.$d : ''; ?>" onclick="sortBy('total_money')">Total</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result['data'])): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted" style="padding:30px;">
                                No transactions found for the selected filters.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($result['data'] as $r):
                            $is_grabfood = (($r['source'] ?? '') === 'GRABFOOD');
                            if (!empty($r['cancelled_at'])) {
                                $type_label = 'Cancelled'; $type_class = 'badge-cancelled';
                            } elseif (!empty($r['refund_for'])) {
                                $type_label = 'Return'; $type_class = 'badge-return';
                            } else {
                                $type_label = 'Sale'; $type_class = 'badge-sale';
                            }
                            $status_map = [
                                'completed' => ['label' => 'Completed', 'class' => 'label-success'],
                                'refunded'  => ['label' => 'Refunded',  'class' => 'label-warning'],
                                'return'    => ['label' => 'Return',    'class' => 'label-info'],
                                'cancelled' => ['label' => 'Cancelled', 'class' => 'label-danger'],
                            ];
                            $status     = $r['status'] ?? 'completed';
                            $status_cfg = $status_map[$status] ?? $status_map['completed'];
                            $row_url    = admin_url('pos/transaction/' . urlencode($r['receipt_number']));

                            // GrabFood's featureFlags.orderType comes through as raw API values —
                            // translate to the merchant-facing terms used elsewhere in the UI.
                            $gf_order_type_label = ['DeliveredByGrab' => 'Delivery', 'TakeAway' => 'Pickup'];
                        ?>
                        <tr onclick="window.location='<?php echo $row_url; ?>'">
                            <td style="white-space:nowrap;color:#337ab7;">
                                <?php echo date('d/m/Y H:i', strtotime($r['receipt_date'])); ?>
                            </td>
                            <td style="font-family:monospace;font-size:12px;">
                                <?php echo htmlspecialchars($r['receipt_number']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['warehouse_name'] ?? '—'); ?></td>
                            <td><span class="label <?php echo $status_cfg['class']; ?>"><?php echo $status_cfg['label']; ?></span></td>
                            <td><span class="badge <?php echo $type_class; ?>"><?php echo $type_label; ?></span></td>
                            <td class="text-muted">
                                <?php if ($is_grabfood): ?>
                                <span style="display:inline-block;background:#00b14f;color:#fff;font-size:9px;font-weight:700;padding:1px 6px;border-radius:8px;letter-spacing:.3px;">
                                    <?php echo htmlspecialchars($gf_order_type_label[$r['dining_option']] ?? ($r['dining_option'] ?: 'GrabFood')); ?>
                                </span>
                                <?php else: ?>
                                <?php echo htmlspecialchars($r['dining_option'] ?: '—'); ?>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?php echo number_format((float)$r['items_subtotal'], 2); ?></td>
                            <td class="text-right"><?php echo number_format((float)$r['total_discount'], 2); ?></td>
                            <td class="text-right"><?php echo number_format((float)$r['total_tax'], 2); ?></td>
                            <td class="text-right"><strong><?php echo number_format((float)$r['total_money'], 2); ?></strong></td>
                            <td onclick="event.stopPropagation();">
                                <button class="btn btn-danger btn-xs" onclick="confirmDeleteTxn(<?php echo (int)$r['id']; ?>, '<?php echo htmlspecialchars($r['receipt_number'], ENT_QUOTES); ?>')" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── BOTTOM PAGINATION ───────────────────────────────── -->
        <?php if ($result['page_count'] > 1): ?>
        <div class="text-center" style="margin-top:16px;">
            <ul class="pagination pagination-sm" style="margin:0;">
                <?php if ($result['page'] > 1): ?>
                <li><a href="#" onclick="goPage(<?php echo $result['page'] - 1; ?>);return false;">&laquo;</a></li>
                <?php endif; ?>

                <?php
                $start = max(1, $result['page'] - 2);
                $end   = min($result['page_count'], $result['page'] + 2);
                if ($start > 1) echo '<li class="disabled"><a>…</a></li>';
                for ($p = $start; $p <= $end; $p++):
                ?>
                <li class="<?php echo $p === $result['page'] ? 'active' : ''; ?>">
                    <a href="#" onclick="goPage(<?php echo $p; ?>);return false;"><?php echo $p; ?></a>
                </li>
                <?php endfor;
                if ($end < $result['page_count']) echo '<li class="disabled"><a>…</a></li>';
                ?>

                <?php if ($result['page'] < $result['page_count']): ?>
                <li><a href="#" onclick="goPage(<?php echo $result['page'] + 1; ?>);return false;">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

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
                <p>Are you sure you want to permanently delete transaction <strong id="deleteTxnNumber"></strong>?</p>
                <p class="text-danger" style="font-size:12px;"><i class="fa fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteTxnBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
function goPage(p) {
    document.getElementById('page-input').value = p;
    document.getElementById('filter-form').submit();
}

function sortBy(col) {
    var sortInput = document.getElementById('sort-input');
    var dirInput  = document.getElementById('dir-input');
    if (sortInput.value === col) {
        dirInput.value = dirInput.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortInput.value = col;
        dirInput.value  = 'desc';
    }
    document.getElementById('page-input').value = 1;
    document.getElementById('filter-form').submit();
}

var _deleteTxnId = null;

function confirmDeleteTxn(id, receiptNumber) {
    _deleteTxnId = id;
    document.getElementById('deleteTxnNumber').textContent = receiptNumber;
    $('#deleteTxnModal').modal('show');
}

document.getElementById('confirmDeleteTxnBtn').addEventListener('click', function () {
    if (!_deleteTxnId) return;
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Deleting…';

    $.post('<?php echo admin_url('pos/ajax_delete_transaction'); ?>', { id: _deleteTxnId }, function (resp) {
        $('#deleteTxnModal').modal('hide');
        if (resp.success) {
            location.reload();
        } else {
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
