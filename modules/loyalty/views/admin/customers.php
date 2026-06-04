<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.loyalty-stat-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:18px 22px; margin-bottom:18px; }
.loyalty-stat-card .stat-value { font-size:26px; font-weight:700; color:#333; }
.loyalty-stat-card .stat-label { font-size:12px; color:#888; margin-top:2px; }
.tier-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; background:#eee; color:#555; }
.member-table tbody tr { cursor:pointer; }
.member-table tbody tr:hover { background:#f5f9ff; }
.filter-bar { background:#fff; border:1px solid #ddd; border-radius:4px; padding:12px 16px; margin-bottom:18px; }
.pagination-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Loyalty Members</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/customers'); ?>">Loyalty</a></li>
                <li class="active">Members</li>
            </ol>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_members']); ?></div>
                <div class="stat-label">Total Members</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_points'], 2); ?></div>
                <div class="stat-label">Points Outstanding</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_earned'], 2); ?></div>
                <div class="stat-label">Total Points Earned</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_redeemed'], 2); ?></div>
                <div class="stat-label">Total Points Redeemed</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?php echo admin_url('loyalty/customers'); ?>">
    <div class="filter-bar">
        <div class="row">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Search name, phone, email…" value="<?php echo htmlspecialchars($filters['search']); ?>">
                    <span class="input-group-btn">
                        <button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
                    </span>
                </div>
            </div>
            <div class="col-md-2">
                <select name="limit" class="form-control input-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 50, 100] as $l): ?>
                    <option value="<?php echo $l; ?>" <?php echo (int)$filters['per_page'] === $l ? 'selected' : ''; ?>><?php echo $l; ?> per page</option>
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
                &nbsp;|&nbsp; <strong><?php echo number_format($result['total']); ?></strong> members
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
            <table class="table table-hover no-margin member-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="text-right">Points</th>
                        <th>Tier</th>
                        <th class="text-right">Total Spent</th>
                        <th>Last Visit</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:30px;">
                            No members found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr onclick="window.location='<?php echo admin_url('loyalty/customer/' . (int)$r['id']); ?>'">
                        <td style="font-weight:500;"><?php echo htmlspecialchars($r['name'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['phone'] ?: '—'); ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars($r['email'] ?: '—'); ?></td>
                        <td class="text-right"><strong style="color:#337ab7;"><?php echo number_format((float)$r['total_points'], 2); ?></strong></td>
                        <td>
                            <?php if ($r['tier']): ?>
                            <span class="tier-badge" style="background:<?php echo htmlspecialchars($r['tier']['color'] ?? '#eee'); ?>;color:#fff;">
                                <?php echo htmlspecialchars($r['tier']['name'] ?? ''); ?>
                            </span>
                            <?php else: ?>
                            <span class="tier-badge">Member</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">RM <?php echo number_format((float)$r['total_spent'], 2); ?></td>
                        <td class="text-muted" style="white-space:nowrap;">
                            <?php echo $r['last_visit'] ? date('d/m/Y', strtotime($r['last_visit'])) : '—'; ?>
                        </td>
                        <td class="text-muted" style="white-space:nowrap;font-size:12px;">
                            <?php echo date('d/m/Y', strtotime($r['registered_at'])); ?>
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
