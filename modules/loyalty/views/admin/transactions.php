<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.txn-table tbody tr { cursor:pointer; }
.txn-table tbody tr:hover { background:#f5f9ff; }
.txn-earn   { color:#5cb85c; font-weight:600; }
.txn-redeem { color:#f0ad4e; font-weight:600; }
.txn-adjust { color:#337ab7; font-weight:600; }
.filter-bar { background:#fff; border:1px solid #ddd; border-radius:4px; padding:12px 16px; margin-bottom:18px; }
.pagination-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Loyalty Transactions</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/customers'); ?>">Loyalty</a></li>
                <li class="active">Transactions</li>
            </ol>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?php echo admin_url('loyalty/transactions'); ?>">
    <div class="filter-bar">
        <div class="row">
            <div class="col-md-3">
                <select name="type" class="form-control input-sm" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach (['earn' => 'Earn', 'redeem' => 'Redeem', 'adjust' => 'Adjust'] as $val => $label): ?>
                    <option value="<?php echo $val; ?>" <?php echo $filters['type'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_from']); ?>" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_to']); ?>" placeholder="To">
            </div>
            <div class="col-md-3">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Search name, phone, receipt…" value="<?php echo htmlspecialchars($filters['search']); ?>">
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
    </form>

    <!-- Results Info -->
    <div class="row" style="margin-bottom:10px;">
        <div class="col-sm-6">
            <span class="text-muted" style="font-size:13px;">
                Page <strong><?php echo $result['page']; ?></strong> of <strong><?php echo $result['page_count']; ?></strong>
                &nbsp;|&nbsp; <strong><?php echo number_format($result['total']); ?></strong> records
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

    <!-- Table -->
    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin txn-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Member</th>
                        <th>Phone</th>
                        <th>Type</th>
                        <th class="text-right">Points</th>
                        <th>Description</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding:30px;">
                            No transactions found for the selected filters.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $t):
                        $cls  = 'txn-' . $t['type'];
                        $sign = $t['type'] === 'earn' ? '+' : ($t['type'] === 'redeem' ? '−' : ($t['points'] >= 0 ? '+' : ''));
                    ?>
                    <tr onclick="window.location='<?php echo admin_url('loyalty/customer/' . (int)$t['customer_id']); ?>'">
                        <td style="white-space:nowrap;color:#337ab7;">
                            <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                        </td>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($t['customer_name'] ?: '—'); ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars($t['customer_phone'] ?: '—'); ?></td>
                        <td><span class="<?php echo $cls; ?>"><?php echo ucfirst($t['type']); ?></span></td>
                        <td class="text-right <?php echo $cls; ?>">
                            <?php echo $sign . number_format(abs((float)$t['points']), 2); ?>
                        </td>
                        <td class="text-muted"><?php echo htmlspecialchars($t['description'] ?: '—'); ?></td>
                        <td style="font-family:monospace;font-size:12px;">
                            <?php if (!empty($t['receipt_number'])): ?>
                            <a href="<?php echo admin_url('pos/transaction/' . urlencode($t['receipt_number'])); ?>" onclick="event.stopPropagation();">
                                <?php echo htmlspecialchars($t['receipt_number']); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bottom Pagination -->
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
            <?php endfor; ?>
            <?php if ($end < $result['page_count']) echo '<li class="disabled"><a>…</a></li>'; ?>
            <?php if ($result['page'] < $result['page_count']): ?>
            <li><a href="#" onclick="goPage(<?php echo $result['page'] + 1; ?>);return false;">&raquo;</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>
</div>

<script>
function goPage(p) {
    document.getElementById('page-input').value = p;
    document.getElementById('page-input').closest('form').submit();
}
</script>

<?php init_tail(); ?>
