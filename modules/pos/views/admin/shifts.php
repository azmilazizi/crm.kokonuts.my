<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.shift-table tbody tr { cursor: pointer; }
.shift-table tbody tr:hover { background: #f5f9ff; }
.badge-open   { background: #5cb85c; color: #fff; border-radius: 3px; padding: 2px 7px; font-size: 11px; }
.badge-closed { background: #777;    color: #fff; border-radius: 3px; padding: 2px 7px; font-size: 11px; }
.diff-pos { color: #5cb85c; font-weight: 600; }
.diff-neg { color: #d9534f; font-weight: 600; }
.filter-bar { background: #fff; border: 1px solid #ddd; border-radius: 4px; padding: 12px 16px; margin-bottom: 18px; }
.pagination-wrap { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
</style>

<div id="wrapper">
    <div class="content">

        <!-- ── TOOLBAR ─────────────────────────────────────────── -->
        <div class="row" style="margin-bottom:16px;">
            <div class="col-sm-6">
                <h4 class="no-margin-top" style="margin-bottom:4px;">Shifts</h4>
                <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                    <li><a href="<?php echo admin_url('pos/dashboard'); ?>">Dashboard</a></li>
                    <li class="active">Shifts</li>
                </ol>
            </div>
        </div>

        <!-- ── FILTER BAR ──────────────────────────────────────── -->
        <form method="GET" action="<?php echo admin_url('pos/shifts'); ?>" id="filter-form">
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
                    <select name="status" class="form-control input-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="open"   <?php echo $filters['status'] === 'open'   ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo $filters['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-default btn-sm btn-block" type="submit"><i class="fa fa-search"></i></button>
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
        <input type="hidden" name="page" id="page-input" value="<?php echo (int)$filters['page']; ?>">
        </form>

        <!-- ── RESULTS INFO ────────────────────────────────────── -->
        <div class="row" style="margin-bottom:10px;">
            <div class="col-sm-6">
                <span class="text-muted" style="font-size:13px;">
                    Page <strong><?php echo $result['page']; ?></strong> of <strong><?php echo max(1, $result['page_count']); ?></strong>
                    &nbsp;|&nbsp; Found <strong><?php echo number_format($result['total']); ?></strong> shifts
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
                <table class="table table-hover no-margin shift-table">
                    <thead>
                        <tr>
                            <th>Opened</th>
                            <th>Closed</th>
                            <th>Shift Code</th>
                            <th>Store</th>
                            <th>Status</th>
                            <th class="text-right">Opening Float</th>
                            <th class="text-right">Total Sales</th>
                            <th class="text-right">Expected Cash</th>
                            <th class="text-right">Actual Cash</th>
                            <th class="text-right">Difference</th>
                            <th class="text-right">Transactions</th>
                            <th style="width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result['data'])): ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted" style="padding:30px;">
                                No shifts found for the selected filters.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($result['data'] as $s):
                            $diff       = (float)$s['difference'];
                            $diff_class = $diff > 0 ? 'diff-pos' : ($diff < 0 ? 'diff-neg' : '');
                            $detail_url = admin_url('pos/shift_detail/' . (int)$s['id']);
                        ?>
                        <tr onclick="window.location='<?php echo $detail_url; ?>'">
                            <td style="white-space:nowrap;color:#337ab7;">
                                <?php echo date('d/m/Y H:i', strtotime($s['opened_at'])); ?>
                            </td>
                            <td style="white-space:nowrap;" class="text-muted">
                                <?php echo $s['closed_at'] ? date('d/m/Y H:i', strtotime($s['closed_at'])) : '—'; ?>
                            </td>
                            <td style="font-family:monospace;font-size:12px;"><?php echo htmlspecialchars($s['shift_code']); ?></td>
                            <td><?php echo htmlspecialchars($s['warehouse_name'] ?? '—'); ?></td>
                            <td>
                                <?php if ($s['status'] === 'open'): ?>
                                    <span class="badge-open">Open</span>
                                <?php else: ?>
                                    <span class="badge-closed">Closed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right"><?php echo number_format((float)$s['opening_float'], 2); ?></td>
                            <td class="text-right"><strong><?php echo number_format((float)$s['total_sales'], 2); ?></strong></td>
                            <td class="text-right"><?php echo number_format((float)$s['expected_cash'], 2); ?></td>
                            <td class="text-right"><?php echo number_format((float)$s['actual_cash'], 2); ?></td>
                            <td class="text-right <?php echo $diff_class; ?>">
                                <?php echo ($diff > 0 ? '+' : '') . number_format($diff, 2); ?>
                            </td>
                            <td class="text-right"><?php echo (int)$s['transaction_count']; ?></td>
                            <td onclick="event.stopPropagation();">
                                <?php if (has_permission('pos', '', 'delete')): ?>
                                <button class="btn btn-danger btn-sm" onclick="confirmDeleteShift(<?php echo (int)$s['id']; ?>, '<?php echo htmlspecialchars($s['shift_code'], ENT_QUOTES); ?>')" title="Delete shift">
                                    <i class="fa fa-trash"></i> Delete
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

        <!-- ── BOTTOM PAGINATION ───────────────────────────────── -->
        <?php if ($result['page_count'] > 1): ?>
        <div class="text-center" style="margin-top:16px;">
            <ul class="pagination pagination-sm" style="margin:0;">
                <?php if ($result['page'] > 1): ?>
                <li><a href="#" onclick="goPage(<?php echo $result['page'] - 1; ?>);return false;">&laquo;</a></li>
                <?php endif; ?>
                <?php for ($p = max(1, $result['page'] - 3); $p <= min($result['page_count'], $result['page'] + 3); $p++): ?>
                <li class="<?php echo $p === $result['page'] ? 'active' : ''; ?>">
                    <a href="#" onclick="goPage(<?php echo $p; ?>);return false;"><?php echo $p; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($result['page'] < $result['page_count']): ?>
                <li><a href="#" onclick="goPage(<?php echo $result['page'] + 1; ?>);return false;">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div><!-- /.content -->
</div><!-- /#wrapper -->

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
                <p>Are you sure you want to permanently delete shift <strong id="deleteShiftCode"></strong>?</p>
                <p class="text-danger" style="font-size:12px;"><i class="fa fa-exclamation-triangle"></i> This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteShiftBtn">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
function goPage(p) {
    document.getElementById('page-input').value = p;
    document.getElementById('filter-form').submit();
}

var _deleteShiftId = null;

function confirmDeleteShift(id, code) {
    _deleteShiftId = id;
    document.getElementById('deleteShiftCode').textContent = code;
    $('#deleteShiftModal').modal('show');
}

document.getElementById('confirmDeleteShiftBtn').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Deleting…';
    $.post('<?php echo admin_url('pos/ajax_delete_shift'); ?>', { id: _deleteShiftId }, function (resp) {
        if (resp.success) {
            location.reload();
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
