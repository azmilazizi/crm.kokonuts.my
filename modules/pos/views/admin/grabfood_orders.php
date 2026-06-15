<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.gf-badge { display:inline-block; padding:3px 8px; border-radius:12px; font-size:11px; font-weight:600; letter-spacing:.3px; }
.gf-badge-placed      { background:#fff3cd; color:#856404; }
.gf-badge-accepted    { background:#d4edda; color:#155724; }
.gf-badge-cancelled   { background:#f8d7da; color:#721c24; }
.gf-badge-delivered   { background:#cce5ff; color:#004085; }
.gf-badge-ready       { background:#d1ecf1; color:#0c5460; }
.gf-badge-driver      { background:#e2d9f3; color:#432874; }
.gf-badge-default     { background:#e9ecef; color:#495057; }
.gf-status-filter { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:0; }
.gf-status-filter a { font-size:12px; padding:3px 10px; border-radius:12px; border:1px solid #ddd; color:#555; text-decoration:none; background:#fff; }
.gf-status-filter a.active { background:#337ab7; border-color:#337ab7; color:#fff; }
.gf-status-filter a:hover:not(.active) { background:#f0f4ff; }
.filter-bar { background:#fff; border:1px solid #ddd; border-radius:4px; padding:12px 16px; margin-bottom:18px; }
.txn-table tbody tr { cursor:pointer; }
.txn-table tbody tr:hover { background:#f5f9ff; }
.pagination-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom:4px;">
                <img src="https://food.grab.com/favicon.ico" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;" onerror="this.style.display='none'">
                GrabFood Orders
            </h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('pos/dashboard'); ?>">POS</a></li>
                <li class="active">GrabFood Orders</li>
            </ol>
        </div>
        <div class="col-sm-6 text-right" style="padding-top:4px;">
            <a href="<?php echo admin_url('pos/grabfood_export_csv?' . http_build_query([
                'store'     => $filters['warehouse_id'],
                'date_from' => $filters['date_from'],
                'date_to'   => $filters['date_to'],
                'q'         => $filters['search'],
                'status'    => $filters['status'],
            ])); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-upload"></i> Export CSV
            </a>
            <button class="btn btn-success btn-sm" id="btn-sync" onclick="openSyncModal()">
                <i class="fa fa-refresh"></i> Sync Orders
            </button>
        </div>
    </div>

    <!-- Status Filter Pills -->
    <?php
    $statuses = [
        '' => 'All',
        'PLACED'           => 'Placed',
        'ACCEPTED'         => 'Accepted',
        'READY_FOR_PICKUP' => 'Ready',
        'DELIVERED'        => 'Delivered',
        'CANCELLED'        => 'Cancelled',
    ];
    ?>
    <div class="gf-status-filter" style="margin-bottom:14px;">
        <?php foreach ($statuses as $val => $label): ?>
        <a href="<?php echo admin_url('pos/grabfood_orders?' . http_build_query(array_merge($filters, ['status' => $val, 'page' => 1]))); ?>"
           class="<?php echo $filters['status'] === $val ? 'active' : ''; ?>">
            <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?php echo admin_url('pos/grabfood_orders'); ?>" id="filter-form">
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
                    <input type="text" name="q" class="form-control" placeholder="Search order ref, customer…" value="<?php echo htmlspecialchars($filters['search']); ?>">
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
    <input type="hidden" name="page"   id="page-input"   value="1">
    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filters['status']); ?>">
    </form>

    <!-- Last Sync -->
    <?php if ($last_sync): ?>
    <p class="text-muted" style="font-size:12px;margin-bottom:10px;">
        <i class="fa fa-clock-o"></i> Last synced: <?php echo date('d M Y, H:i', strtotime($last_sync)); ?>
    </p>
    <?php endif; ?>

    <!-- Results -->
    <div class="row" style="margin-bottom:10px;">
        <div class="col-sm-6">
            <span class="text-muted" style="font-size:13px;">
                Page <strong><?php echo $result['page']; ?></strong> of <strong><?php echo max(1, $result['page_count']); ?></strong>
                &nbsp;|&nbsp; Found <strong><?php echo number_format($result['total']); ?></strong> orders
            </span>
        </div>
        <div class="col-sm-6 text-right">
            <div class="pagination-wrap" style="justify-content:flex-end;">
                <?php if ($result['page'] > 1): ?>
                <button class="btn btn-default btn-sm" onclick="goPage(<?php echo $result['page'] - 1; ?>)"><i class="fa fa-chevron-left"></i></button>
                <?php endif; ?>
                <span class="text-muted" style="font-size:12px;">Page <?php echo $result['page']; ?></span>
                <?php if ($result['page'] < $result['page_count']): ?>
                <button class="btn btn-default btn-sm" onclick="goPage(<?php echo $result['page'] + 1; ?>)"><i class="fa fa-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (empty($result['data'])): ?>
    <div class="panel_s">
        <div class="panel-body text-center text-muted" style="padding:60px 20px;">
            <i class="fa fa-motorcycle fa-3x" style="display:block;margin-bottom:12px;color:#ddd;"></i>
            No GrabFood orders found for the selected period.<br>
            <small>Use the <strong>Sync Orders</strong> button to pull orders from GrabFood.</small>
        </div>
    </div>
    <?php else: ?>

    <div class="panel_s" style="margin-bottom:0;">
        <table class="table txn-table" style="margin-bottom:0;">
            <thead>
                <tr>
                    <th>Order No.</th>
                    <th>Store</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th style="text-align:right;">Total</th>
                    <th style="text-align:center;">Status</th>
                    <th>Date</th>
                    <th style="width:80px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['data'] as $row): ?>
                <?php $short = $row['order_short_ref'] ?: substr($row['grabfood_order_id'], 0, 12); ?>
                <tr onclick="window.location='<?php echo admin_url('pos/grabfood_order/' . (int)$row['id']); ?>'">
                    <td>
                        <strong><?php echo htmlspecialchars($short); ?></strong>
                        <?php if ($row['order_short_ref'] && $row['order_short_ref'] !== $row['grabfood_order_id']): ?>
                        <br><small class="text-muted" style="font-size:10px;"><?php echo htmlspecialchars(substr($row['grabfood_order_id'], 0, 20)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['warehouse_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_name'] ?: '—'); ?></td>
                    <td>
                        <?php if ($row['order_type'] === 'DELIVERY'): ?>
                        <span class="text-muted"><i class="fa fa-motorcycle"></i> Delivery</span>
                        <?php elseif ($row['order_type'] === 'SELF_PICKUP'): ?>
                        <span class="text-muted"><i class="fa fa-shopping-bag"></i> Pickup</span>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <strong>RM <?php echo number_format($row['total'], 2); ?></strong>
                    </td>
                    <td style="text-align:center;">
                        <?php echo gf_status_badge($row['order_status']); ?>
                    </td>
                    <td style="font-size:12px;color:#777;">
                        <?php echo date('d M y, H:i', strtotime($row['created_at'])); ?>
                    </td>
                    <td onclick="event.stopPropagation();" style="text-align:center;">
                        <a href="<?php echo admin_url('pos/grabfood_order/' . (int)$row['id']); ?>" class="btn btn-default btn-xs">
                            <i class="fa fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

</div>
</div>

<!-- Sync Modal -->
<div class="modal fade" id="sync-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Sync GrabFood Orders</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Store</label>
                    <select id="sync-store" class="form-control">
                        <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo (int)$w['id']; ?>" <?php echo (int)$filters['warehouse_id'] === (int)$w['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($w['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>From</label>
                            <input type="date" id="sync-from" class="form-control" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            <label>To</label>
                            <input type="date" id="sync-to" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>
                <div id="sync-result" style="display:none;" class="alert"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="btn-do-sync" onclick="doSync()">
                    <i class="fa fa-refresh"></i> Sync Now
                </button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<?php
function gf_status_badge($status) {
    $map = [
        'PLACED'           => ['class' => 'gf-badge-placed',    'label' => 'Placed'],
        'ACCEPTED'         => ['class' => 'gf-badge-accepted',  'label' => 'Accepted'],
        'CANCELLED'        => ['class' => 'gf-badge-cancelled', 'label' => 'Cancelled'],
        'DELIVERED'        => ['class' => 'gf-badge-delivered', 'label' => 'Delivered'],
        'READY_FOR_PICKUP' => ['class' => 'gf-badge-ready',     'label' => 'Ready'],
        'DRIVER_ALLOCATED' => ['class' => 'gf-badge-driver',    'label' => 'Driver Allocated'],
        'FAILED'           => ['class' => 'gf-badge-cancelled', 'label' => 'Failed'],
    ];
    $s = strtoupper($status);
    $info = $map[$s] ?? ['class' => 'gf-badge-default', 'label' => ucfirst(strtolower(str_replace('_', ' ', $s)))];
    return '<span class="gf-badge ' . $info['class'] . '">' . htmlspecialchars($info['label']) . '</span>';
}
?>
<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function goPage(p) {
    document.getElementById('page-input').value = p;
    document.getElementById('filter-form').submit();
}

function openSyncModal() {
    document.getElementById('sync-result').style.display = 'none';
    $('#sync-modal').modal('show');
}

function doSync() {
    var btn = document.getElementById('btn-do-sync');
    var res = document.getElementById('sync-result');

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Syncing…';
    res.style.display = 'none';

    $.post(ADMIN_URL + 'pos/ajax_grabfood_sync', {
        warehouse_id: $('#sync-store').val(),
        date_from:    $('#sync-from').val(),
        date_to:      $('#sync-to').val(),
    }, function(data) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Sync Now';

        if (data.success) {
            res.className = 'alert alert-success';
            res.innerHTML = '<strong>Done!</strong> Synced ' + data.synced + ' of ' + data.total + ' orders.';
            if (data.errors && data.errors.length) {
                res.innerHTML += '<br><small>' + data.errors.slice(0, 3).join(', ') + '</small>';
            }
            res.style.display = '';
            setTimeout(function() { window.location.reload(); }, 2000);
        } else {
            res.className = 'alert alert-danger';
            res.innerHTML = '<strong>Error:</strong> ' + (data.error || 'Sync failed. Check your GrabFood credentials in POS > Settings > Integrations.');
            res.style.display = '';
        }
    }, 'json').fail(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-refresh"></i> Sync Now';
        res.className = 'alert alert-danger';
        res.innerHTML = 'Request failed. Please try again.';
        res.style.display = '';
    });
}
</script>
</body>
</html>
