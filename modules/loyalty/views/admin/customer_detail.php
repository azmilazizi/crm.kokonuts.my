<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.profile-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:24px; margin-bottom:20px; }
.points-display { font-size:36px; font-weight:700; color:#337ab7; line-height:1; }
.points-label { font-size:13px; color:#888; margin-top:4px; }
.tier-badge-lg { display:inline-block; padding:4px 14px; border-radius:20px; font-size:13px; font-weight:600; background:#eee; color:#555; }
.stat-mini { text-align:center; padding:12px; border-right:1px solid #eee; }
.stat-mini:last-child { border-right:none; }
.stat-mini .val { font-size:18px; font-weight:600; color:#333; }
.stat-mini .lbl { font-size:11px; color:#aaa; }
.txn-type-earn    { color:#5cb85c; font-weight:600; }
.txn-type-redeem  { color:#f0ad4e; font-weight:600; }
.txn-type-adjust  { color:#337ab7; font-weight:600; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-8">
            <h4 class="no-margin-top" style="margin-bottom:4px;"><?php echo htmlspecialchars($customer['name'] ?: $customer['phone']); ?></h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/customers'); ?>">Members</a></li>
                <li class="active"><?php echo htmlspecialchars($customer['name'] ?: 'Member #' . $customer['id']); ?></li>
            </ol>
        </div>
        <?php if (has_permission('loyalty', '', 'edit')): ?>
        <div class="col-sm-4 text-right" style="padding-top:4px;">
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#adjustModal">
                <i class="fa fa-plus-minus"></i> Adjust Points
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Profile Card -->
    <div class="profile-card">
        <div class="row">
            <div class="col-sm-4">
                <div style="margin-bottom:12px;">
                    <div class="points-display"><?php echo number_format((float)$customer['total_points'], 2); ?></div>
                    <div class="points-label">Points Balance</div>
                </div>
                <?php if ($customer['tier']): ?>
                <span class="tier-badge-lg" style="background:<?php echo htmlspecialchars($customer['tier']['color'] ?? '#eee'); ?>;color:#fff;">
                    <?php echo htmlspecialchars($customer['tier']['name']); ?>
                </span>
                <?php else: ?>
                <span class="tier-badge-lg">Member</span>
                <?php endif; ?>
            </div>
            <div class="col-sm-4">
                <table class="table no-margin" style="font-size:14px;">
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;width:80px;">Name</td>
                        <td style="border:none;padding:4px 0;"><strong><?php echo htmlspecialchars($customer['name'] ?: '—'); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Phone</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($customer['phone'] ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Email</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($customer['email'] ?: '—'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-4">
                <table class="table no-margin" style="font-size:14px;">
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;width:90px;">Total Spent</td>
                        <td style="border:none;padding:4px 0;"><strong>RM <?php echo number_format((float)$customer['total_spent'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Last Visit</td>
                        <td style="border:none;padding:4px 0;">
                            <?php echo $customer['last_visit'] ? date('d/m/Y', strtotime($customer['last_visit'])) : '—'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Joined</td>
                        <td style="border:none;padding:4px 0;font-size:12px;">
                            <?php echo date('d/m/Y', strtotime($customer['registered_at'])); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <h5 style="margin-bottom:12px;font-weight:600;">Points History
        <span class="text-muted" style="font-weight:400;font-size:13px;">(<?php echo number_format($result['total']); ?> records)</span>
    </h5>

    <?php if ($result['page_count'] > 1): ?>
    <div class="row" style="margin-bottom:10px;">
        <div class="col-sm-12 text-right">
            <div class="pagination-wrap" style="justify-content:flex-end;display:flex;gap:8px;align-items:center;">
                <?php if ($result['page'] > 1): ?>
                <a href="<?php echo admin_url('loyalty/customer/' . $customer['id'] . '?page=' . ($result['page'] - 1)); ?>" class="btn btn-default btn-xs"><i class="fa fa-chevron-left"></i></a>
                <?php endif; ?>
                <span class="text-muted" style="font-size:12px;">Page <?php echo $result['page']; ?> of <?php echo $result['page_count']; ?></span>
                <?php if ($result['page'] < $result['page_count']): ?>
                <a href="<?php echo admin_url('loyalty/customer/' . $customer['id'] . '?page=' . ($result['page'] + 1)); ?>" class="btn btn-default btn-xs"><i class="fa fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin" id="txn-history">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Type</th>
                        <th class="text-right">Points</th>
                        <th>Description</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($txns)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding:30px;">No transactions yet.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($txns as $t): ?>
                    <?php
                        $type_class = 'txn-type-' . $t['type'];
                        $type_label = ucfirst($t['type']);
                        $sign       = $t['type'] === 'earn' ? '+' : ($t['type'] === 'redeem' ? '−' : ($t['points'] >= 0 ? '+' : ''));
                    ?>
                    <tr>
                        <td style="white-space:nowrap;color:#337ab7;">
                            <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                        </td>
                        <td><span class="<?php echo $type_class; ?>"><?php echo $type_label; ?></span></td>
                        <td class="text-right <?php echo $type_class; ?>">
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

</div>
</div>

<!-- Adjust Points Modal -->
<?php if (has_permission('loyalty', '', 'edit')): ?>
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Adjust Points</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Points <small class="text-muted">(use negative to deduct)</small></label>
                    <input type="number" id="adj-points" class="form-control" step="0.01" placeholder="e.g. 10 or -5">
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" id="adj-desc" class="form-control" placeholder="Optional note" maxlength="255">
                </div>
                <div id="adj-msg" style="display:none;" class="alert alert-danger"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="adj-submit">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('adj-submit').addEventListener('click', function () {
    var points = parseFloat(document.getElementById('adj-points').value);
    var desc   = document.getElementById('adj-desc').value.trim();
    var msg    = document.getElementById('adj-msg');

    if (isNaN(points) || points === 0) {
        msg.textContent = 'Enter a non-zero points value.';
        msg.style.display = 'block';
        return;
    }

    msg.style.display = 'none';
    this.disabled = true;
    this.textContent = 'Saving…';

    fetch('<?php echo admin_url('loyalty/manual_adjust'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            customer_id: '<?php echo (int)$customer['id']; ?>',
            points:      points,
            description: desc,
            <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
        })
    })
    .then(r => r.json())
    .then(function (res) {
        if (res.success) {
            location.reload();
        } else {
            msg.textContent = res.message || 'Adjustment failed.';
            msg.style.display = 'block';
            document.getElementById('adj-submit').disabled = false;
            document.getElementById('adj-submit').textContent = 'Save';
        }
    });
});
</script>
<?php endif; ?>

<?php init_tail(); ?>
