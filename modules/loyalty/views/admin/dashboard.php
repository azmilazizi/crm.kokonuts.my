<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.loyalty-kpi { border-left: 4px solid #ddd; }
.loyalty-kpi.green  { border-left-color: #5cb85c; }
.loyalty-kpi.blue   { border-left-color: #337ab7; }
.loyalty-kpi.orange { border-left-color: #f0ad4e; }
.loyalty-kpi.purple { border-left-color: #9b59b6; }
.loyalty-kpi .kpi-label { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: .5px; }
.loyalty-kpi .kpi-value { font-size: 26px; font-weight: 700; margin: 4px 0 0; color: #333; }
.loyalty-kpi .kpi-sub   { font-size: 12px; color: #aaa; margin-top: 2px; }

.loyalty-stat-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 16px 20px; margin-bottom: 18px; }
.loyalty-stat-card .stat-value { font-size: 24px; font-weight: 700; color: #337ab7; }
.loyalty-stat-card .stat-label { font-size: 12px; color: #888; margin-top: 2px; }

.period-btn.active { background: #337ab7 !important; color: #fff !important; border-color: #337ab7 !important; }

.chart-panel { min-height: 260px; }

.txn-type-earn   { color: #5cb85c; font-weight: 600; }
.txn-type-redeem { color: #f0ad4e; font-weight: 600; }
.txn-type-adjust { color: #337ab7; font-weight: 600; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom: 16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom: 4px;">Loyalty Dashboard</h4>
            <ol class="breadcrumb" style="margin: 0; padding: 0; background: none; font-size: 12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">Dashboard</li>
            </ol>
        </div>
        <div class="col-sm-6 text-right" style="padding-top: 6px;">
            <a href="<?php echo admin_url('loyalty/import_members'); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-upload"></i> Import Members
            </a>
            <a href="<?php echo admin_url('loyalty/customers'); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-users"></i> All Members
            </a>
        </div>
    </div>

    <!-- Period Filter -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-12">
            <div class="btn-group">
                <?php foreach (['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'year' => 'This Year'] as $key => $label): ?>
                <a href="<?php echo admin_url('loyalty/dashboard?period=' . $key); ?>"
                   class="btn btn-default btn-sm period-btn <?php echo $period === $key ? 'active' : ''; ?>">
                    <?php echo $label; ?>
                </a>
                <?php endforeach; ?>
            </div>
            <span class="text-muted" style="font-size: 12px; margin-left: 10px;">
                <?php echo date('d M Y', strtotime($period_stats['date_from'])); ?>
                <?php if ($period_stats['date_from'] !== $period_stats['date_to']): ?>
                — <?php echo date('d M Y', strtotime($period_stats['date_to'])); ?>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- Period KPI Cards -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="panel_s loyalty-kpi green">
                <div class="panel-body">
                    <div class="kpi-label">New Members</div>
                    <div class="kpi-value"><?php echo number_format($period_stats['new_members']); ?></div>
                    <div class="kpi-sub">in selected period</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="panel_s loyalty-kpi blue">
                <div class="panel-body">
                    <div class="kpi-label">Points Earned</div>
                    <div class="kpi-value"><?php echo number_format($period_stats['points_earned'], 0); ?></div>
                    <div class="kpi-sub">in selected period</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="panel_s loyalty-kpi orange">
                <div class="panel-body">
                    <div class="kpi-label">Points Redeemed</div>
                    <div class="kpi-value"><?php echo number_format($period_stats['points_redeemed'], 0); ?></div>
                    <div class="kpi-sub">in selected period</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="panel_s loyalty-kpi purple">
                <div class="panel-body">
                    <div class="kpi-label">Transactions</div>
                    <div class="kpi-value"><?php echo number_format($period_stats['txn_count']); ?></div>
                    <div class="kpi-sub">earn / redeem / adjust</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Overall Stats -->
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_members']); ?></div>
                <div class="stat-label">Total Members</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_points'], 0); ?></div>
                <div class="stat-label">Points Outstanding</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_earned'], 0); ?></div>
                <div class="stat-label">All-time Points Earned</div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="loyalty-stat-card">
                <?php
                $redemption_rate = $stats['total_earned'] > 0
                    ? round(($stats['total_redeemed'] / $stats['total_earned']) * 100, 1) : 0;
                ?>
                <div class="stat-value"><?php echo $redemption_rate; ?>%</div>
                <div class="stat-label">Redemption Rate</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Member Growth -->
        <div class="col-md-8">
            <div class="panel_s chart-panel">
                <div class="panel-body">
                    <h5 class="no-margin-top bold">Member Growth <small class="text-muted">(last 12 months)</small></h5>
                    <canvas id="chart-member-growth" height="90"></canvas>
                </div>
            </div>
        </div>
        <!-- Tier Distribution -->
        <div class="col-md-4">
            <div class="panel_s chart-panel">
                <div class="panel-body">
                    <h5 class="no-margin-top bold">Tier Distribution</h5>
                    <?php if (!empty($tier_dist)): ?>
                    <canvas id="chart-tier-dist" height="160"></canvas>
                    <?php else: ?>
                    <p class="text-muted text-center" style="padding: 60px 0;">No tier data available.<br><small>Configure tiers in the MA module.</small></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Transaction Trend -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel_s chart-panel">
                <div class="panel-body">
                    <h5 class="no-margin-top bold">Points Activity <small class="text-muted">(last 30 days)</small></h5>
                    <canvas id="chart-txn-trend" height="60"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="row">
        <div class="col-md-12">
            <div class="panel_s">
                <div class="panel-body" style="padding-bottom: 0;">
                    <div class="row" style="margin-bottom: 12px;">
                        <div class="col-sm-6">
                            <h5 class="no-margin-top bold">Recent Transactions</h5>
                        </div>
                        <div class="col-sm-6 text-right">
                            <a href="<?php echo admin_url('loyalty/transactions'); ?>" class="btn btn-default btn-xs">View All</a>
                        </div>
                    </div>
                </div>
                <div class="panel-body no-padding">
                    <table class="table table-hover no-margin">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Member</th>
                                <th>Type</th>
                                <th class="text-right">Points</th>
                                <th>Description</th>
                                <th>Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_txns)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding: 30px;">No transactions yet.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recent_txns as $t): ?>
                            <tr>
                                <td class="text-muted" style="white-space: nowrap; font-size: 12px;">
                                    <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('loyalty/customer/' . (int)$t['customer_id']); ?>">
                                        <?php echo htmlspecialchars($t['customer_name'] ?: $t['customer_phone'] ?: '—'); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="txn-type-<?php echo $t['type']; ?>">
                                        <?php echo ucfirst($t['type']); ?>
                                    </span>
                                </td>
                                <td class="text-right">
                                    <?php $sign = $t['type'] === 'redeem' ? '-' : ($t['type'] === 'adjust' && $t['points'] < 0 ? '' : '+'); ?>
                                    <strong style="color: <?php echo $t['type'] === 'redeem' || (float)$t['points'] < 0 ? '#d9534f' : '#5cb85c'; ?>">
                                        <?php echo $sign . number_format(abs((float)$t['points']), 2); ?>
                                    </strong>
                                </td>
                                <td class="text-muted" style="font-size: 12px;">
                                    <?php echo htmlspecialchars($t['description'] ?: '—'); ?>
                                </td>
                                <td class="text-muted" style="font-size: 12px;">
                                    <?php echo $t['receipt_number'] ? '#' . htmlspecialchars($t['receipt_number']) : '—'; ?>
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

</div>
</div>

<?php
$member_growth_labels  = json_encode(array_column($member_growth, 'label'));
$member_growth_counts  = json_encode(array_column($member_growth, 'count'));
$txn_labels            = json_encode(array_column($txn_trend, 'label'));
$txn_earn              = json_encode(array_column($txn_trend, 'earn'));
$txn_redeem            = json_encode(array_column($txn_trend, 'redeem'));
$tier_labels           = json_encode(array_column($tier_dist, 'name'));
$tier_counts           = json_encode(array_column($tier_dist, 'count'));
$tier_colors           = json_encode(array_column($tier_dist, 'color'));
?>

<script>
(function () {
    // Member Growth Chart
    var ctxGrowth = document.getElementById('chart-member-growth');
    if (ctxGrowth) {
        new Chart(ctxGrowth.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo $member_growth_labels; ?>,
                datasets: [{
                    label: 'New Members',
                    data: <?php echo $member_growth_counts; ?>,
                    backgroundColor: 'rgba(92, 184, 92, 0.5)',
                    borderColor: '#5cb85c',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] },
                legend: { display: false }
            }
        });
    }

    // Transaction Trend Chart
    var ctxTrend = document.getElementById('chart-txn-trend');
    if (ctxTrend) {
        new Chart(ctxTrend.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo $txn_labels; ?>,
                datasets: [
                    {
                        label: 'Points Earned',
                        data: <?php echo $txn_earn; ?>,
                        borderColor: '#5cb85c',
                        backgroundColor: 'rgba(92,184,92,0.1)',
                        fill: true,
                        pointRadius: 2,
                        borderWidth: 2
                    },
                    {
                        label: 'Points Redeemed',
                        data: <?php echo $txn_redeem; ?>,
                        borderColor: '#f0ad4e',
                        backgroundColor: 'rgba(240,173,78,0.1)',
                        fill: true,
                        pointRadius: 2,
                        borderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                scales: { yAxes: [{ ticks: { beginAtZero: true } }] },
                legend: { position: 'top' }
            }
        });
    }

    // Tier Distribution Chart
    var ctxTier = document.getElementById('chart-tier-dist');
    if (ctxTier) {
        new Chart(ctxTier.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo $tier_labels; ?>,
                datasets: [{
                    data: <?php echo $tier_counts; ?>,
                    backgroundColor: <?php echo $tier_colors; ?>,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                legend: { position: 'bottom', labels: { boxWidth: 12, fontSize: 11 } },
                cutoutPercentage: 55
            }
        });
    }
}());
</script>

<?php init_tail(); ?>
