<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.bh-table th  { font-size:12px; color:#888; font-weight:600; text-transform:uppercase; letter-spacing:.4px; border-bottom:2px solid #e8e8e8; }
.bh-table td  { font-size:13px; vertical-align:middle; }
.bh-table tr:hover td { background:#fafbff; }

.type-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; text-transform:uppercase; }
.type-promotion   { background:#d4edda; color:#155724; }
.type-announcement{ background:#fff3cd; color:#856404; }

.trig-badge { display:inline-block; padding:1px 7px; border-radius:8px; font-size:11px; background:#e8d5f5; color:#5a189a; font-weight:600; }
.ch-push { display:inline-block; padding:1px 6px; border-radius:6px; font-size:11px; background:#d0ebff; color:#1971c2; font-weight:600; margin-right:2px; }
.ch-sms  { display:inline-block; padding:1px 6px; border-radius:6px; font-size:11px; background:#d3f9d8; color:#2f9e44; font-weight:600; }

.stat-ok   { color:#27ae60; font-weight:600; }
.stat-fail { color:#e74c3c; font-weight:600; }
.stat-zero { color:#ccc; }

.member-panel { display:none; padding:12px 16px; background:#f8f9fa; border-top:1px solid #eee; }
.member-panel table { width:100%; font-size:12px; margin:0; }
.member-panel th { color:#999; font-weight:600; padding:4px 8px; }
.member-panel td { padding:5px 8px; border-top:1px solid #f0f0f0; }
.member-panel .tick  { color:#27ae60; }
.member-panel .cross { color:#e74c3c; }
.member-panel .dash  { color:#ccc; }

.expand-btn { cursor:pointer; color:#337ab7; border:none; background:none; padding:2px 6px; font-size:13px; }
.expand-btn:hover { color:#23527c; }

.empty-state { text-align:center; padding:60px 20px; color:#aaa; }
.empty-state i { font-size:48px; margin-bottom:12px; display:block; }

.filter-bar { background:#fff; border:1px solid #e8e8e8; border-radius:4px; padding:12px 16px; margin-bottom:14px; }
.filter-bar .form-group { margin-bottom:0; }
</style>

<div id="wrapper">
<div class="content">

    <div class="row" style="margin-bottom:14px;">
        <div class="col-sm-8">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Blast History</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">Blast History</li>
            </ol>
        </div>
        <div class="col-sm-4 text-right" style="padding-top:4px;">
            <a href="<?php echo admin_url('loyalty/blast_history_export?' . http_build_query(array_filter([
                'date_from'  => $filters['date_from'] ?? '',
                'date_to'    => $filters['date_to'] ?? '',
                'blast_type' => $filters['blast_type'] ?? '',
                'channel'    => $filters['channel'] ?? '',
            ]))); ?>" class="btn btn-default btn-sm">
                <i class="fa fa-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="GET" action="<?php echo admin_url('loyalty/blast_history'); ?>" class="filter-bar">
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    <label style="font-size:11px;margin-bottom:2px;">From</label>
                    <input type="date" name="date_from" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label style="font-size:11px;margin-bottom:2px;">To</label>
                    <input type="date" name="date_to" class="form-control input-sm" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-group">
                    <label style="font-size:11px;margin-bottom:2px;">Type</label>
                    <select name="blast_type" class="form-control input-sm">
                        <option value="">All types</option>
                        <option value="promotion"    <?php if (($filters['blast_type'] ?? '') === 'promotion')    echo 'selected'; ?>>Promotion</option>
                        <option value="announcement" <?php if (($filters['blast_type'] ?? '') === 'announcement') echo 'selected'; ?>>Announcement</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-2">
                <div class="form-group">
                    <label style="font-size:11px;margin-bottom:2px;">Channel</label>
                    <select name="channel" class="form-control input-sm">
                        <option value="">All channels</option>
                        <option value="push" <?php if (($filters['channel'] ?? '') === 'push') echo 'selected'; ?>>Push</option>
                        <option value="sms"  <?php if (($filters['channel'] ?? '') === 'sms')  echo 'selected'; ?>>SMS</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-2" style="padding-top:18px;">
                <button type="submit" class="btn btn-primary btn-sm btn-block">
                    <i class="fa fa-filter"></i> Filter
                </button>
                <?php if (!empty(array_filter($filters ?? []))): ?>
                <a href="<?php echo admin_url('loyalty/blast_history'); ?>" class="btn btn-default btn-sm btn-block" style="margin-top:4px;">
                    <i class="fa fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (empty($logs)): ?>
    <div class="empty-state">
        <i class="fa fa-history"></i>
        <p>No blasts match the selected filters.</p>
    </div>
    <?php else: ?>

    <div class="table-responsive">
        <table class="table bh-table">
            <thead>
                <tr>
                    <th style="width:30px;"></th>
                    <th>Date &amp; Time</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Trigger</th>
                    <th>Channels</th>
                    <th style="text-align:center;">Recipients</th>
                    <th style="text-align:center;">Push</th>
                    <th style="text-align:center;">SMS OK</th>
                    <th style="text-align:center;">SMS Fail</th>
                    <th style="text-align:center;" title="Voucher redemptions after this blast">Voucher Uses</th>
                </tr>
            </thead>
            <tbody id="bh_tbody">
            <?php
            $trigger_labels = [
                'standard'        => 'Standard',
                'birthday'        => 'Birthday',
                'signup_freebies' => 'Sign Up',
                'stale_points'    => 'Stale Points',
                'announcement'    => '—',
            ];
            ?>
            <?php foreach ($logs as $log): ?>
            <?php $trig_label = $trigger_labels[$log['trigger_type']] ?? ucfirst($log['trigger_type']); ?>
            <tr class="bh-row" data-log-id="<?php echo (int)$log['id']; ?>">
                <td>
                    <button class="expand-btn" title="View recipients" onclick="toggleMembers(<?php echo (int)$log['id']; ?>, this)">
                        <i class="fa fa-chevron-right"></i>
                    </button>
                </td>
                <td style="white-space:nowrap;">
                    <?php echo date('d M Y', strtotime($log['blasted_at'])); ?><br>
                    <span style="font-size:11px;color:#aaa;"><?php echo date('H:i', strtotime($log['blasted_at'])); ?></span>
                </td>
                <td><?php echo htmlspecialchars($log['promo_title']); ?></td>
                <td>
                    <span class="type-badge type-<?php echo htmlspecialchars($log['blast_type']); ?>">
                        <?php echo ucfirst($log['blast_type']); ?>
                    </span>
                </td>
                <td>
                    <?php if ($log['blast_type'] !== 'announcement'): ?>
                    <span class="trig-badge"><?php echo htmlspecialchars($trig_label); ?></span>
                    <?php else: ?>
                    <span style="color:#ccc;">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (strpos($log['channels'], 'push') !== false): ?>
                    <span class="ch-push"><i class="fa fa-bell"></i> Push</span>
                    <?php endif; ?>
                    <?php if (strpos($log['channels'], 'sms') !== false): ?>
                    <span class="ch-sms"><i class="fa fa-mobile"></i> SMS</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;font-weight:600;"><?php echo (int)$log['recipient_count']; ?></td>
                <td style="text-align:center;">
                    <?php if ((int)$log['push_sent'] > 0): ?>
                    <span class="stat-ok"><?php echo (int)$log['push_sent']; ?></span>
                    <?php else: ?><span class="stat-zero">—</span><?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ((int)$log['sms_sent'] > 0): ?>
                    <span class="stat-ok"><?php echo (int)$log['sms_sent']; ?></span>
                    <?php else: ?><span class="stat-zero">—</span><?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if ((int)$log['sms_failed'] > 0): ?>
                    <span class="stat-fail"><?php echo (int)$log['sms_failed']; ?></span>
                    <?php else: ?><span class="stat-zero">—</span><?php endif; ?>
                </td>
                <td style="text-align:center;">
                    <?php if (!empty($log['voucher_redemptions'])): ?>
                    <span class="stat-ok" title="Voucher redemptions recorded after this blast"><i class="fa fa-ticket"></i> <?php echo (int)$log['voucher_redemptions']; ?></span>
                    <?php else: ?><span class="stat-zero">—</span><?php endif; ?>
                </td>
            </tr>
            <tr class="member-detail-row" id="detail-<?php echo (int)$log['id']; ?>" style="display:none;">
                <td colspan="11" style="padding:0;">
                    <div class="member-panel" id="panel-<?php echo (int)$log['id']; ?>">
                        <div class="text-center" style="padding:20px;color:#aaa;">
                            <i class="fa fa-spinner fa-spin"></i> Loading recipients…
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($result['page_count'] > 1): ?>
    <div style="margin-top:10px;">
        <?php
        $q = array_filter([
            'date_from'  => $filters['date_from'] ?? '',
            'date_to'    => $filters['date_to'] ?? '',
            'blast_type' => $filters['blast_type'] ?? '',
            'channel'    => $filters['channel'] ?? '',
        ]);
        for ($p = 1; $p <= $result['page_count']; $p++):
            $q['page'] = $p;
        ?>
        <a href="<?php echo admin_url('loyalty/blast_history?' . http_build_query($q)); ?>"
           class="btn btn-sm <?php echo $p === $result['page'] ? 'btn-primary' : 'btn-default'; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

    <p style="font-size:12px;color:#bbb;margin-top:10px;">
        <?php echo number_format($result['total']); ?> blast event(s) total.
        Click <i class="fa fa-chevron-right"></i> to see individual recipients for that blast.
    </p>

    <?php endif; ?>

</div>
</div>

<?php init_tail(); ?>
<script>
$(function () {

var loaded = {};

window.toggleMembers = function (logId, btn) {
    var $row  = $('#detail-' + logId);
    var $icon = $(btn).find('i');
    var open  = $row.is(':visible');

    if (open) {
        $row.hide();
        $icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
        return;
    }

    $row.show();
    $icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');

    if (loaded[logId]) return;

    $.getJSON('<?php echo admin_url('loyalty/ajax_blast_log_members'); ?>', { log_id: logId }, function (r) {
        loaded[logId] = true;
        var $panel = $('#panel-' + logId);

        if (!r.success || !r.members || !r.members.length) {
            $panel.html('<p style="color:#aaa;font-size:12px;margin:0;">No recipient details recorded.</p>');
            return;
        }

        var rows = r.members.map(function (m) {
            var pushIcon = m.push_sent  ? '<i class="fa fa-check tick"></i>'  : '<span class="dash">—</span>';
            var smsIcon  = m.sms_sent   ? '<i class="fa fa-check tick"></i>'  :
                           (m.sms_failed ? '<i class="fa fa-times cross"></i>' : '<span class="dash">—</span>');
            return '<tr>' +
                '<td>' + $('<span>').text(m.customer_name  || '—').html() + '</td>' +
                '<td>' + $('<span>').text(m.customer_phone || '—').html() + '</td>' +
                '<td style="text-align:center;">' + pushIcon + '</td>' +
                '<td style="text-align:center;">' + smsIcon  + '</td>' +
                '</tr>';
        }).join('');

        $panel.html(
            '<table>' +
            '<thead><tr><th>Name</th><th>Phone</th><th style="text-align:center;">Push</th><th style="text-align:center;">SMS</th></tr></thead>' +
            '<tbody>' + rows + '</tbody>' +
            '</table>' +
            '<p style="font-size:11px;color:#aaa;margin:8px 0 0;">' + r.members.length + ' recipient(s)</p>'
        );
    }).fail(function () {
        $('#panel-' + logId).html('<p style="color:#e74c3c;font-size:12px;margin:0;">Failed to load recipients.</p>');
    });
};

}); // end $(function)
</script>
