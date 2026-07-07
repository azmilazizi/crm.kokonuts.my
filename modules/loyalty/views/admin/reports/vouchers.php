<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('loyalty/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _convChart = null;

function renderReport(r) {
    var el       = document.getElementById('report-content');
    var vouchers = r.vouchers        || [];
    var blasts   = r.blast_conversion || [];

    var today = new Date().toISOString().slice(0,10);

    var totalIssued    = vouchers.reduce(function(a,b){ return a + parseInt(b.instances_issued_period||0); }, 0);
    var totalRedeemed  = vouchers.reduce(function(a,b){ return a + parseInt(b.redemptions_in_period||0);   }, 0);
    var totalEver      = vouchers.reduce(function(a,b){ return a + parseInt(b.total_redemptions_ever||0);  }, 0);
    var redeemRate     = totalIssued > 0 ? (totalRedeemed / totalIssued * 100).toFixed(1) : '—';

    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('blue',   'Vouchers in System',        vouchers.length)
        + kpiCard('green',  'Issued (period)',            fmtInt(totalIssued))
        + kpiCard('orange', 'Redeemed (period)',          fmtInt(totalRedeemed))
        + kpiCard('purple', 'Redemption Rate',            redeemRate + (redeemRate === '—' ? '' : '%'))
        + '</div>'

        // Per-voucher table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Voucher Performance</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr>'
        + '<th>#</th><th>Voucher</th><th>Code</th><th>Reward</th>'
        + '<th class="text-right">Issued</th>'
        + '<th class="text-right">Redeemed</th>'
        + '<th class="text-right">Rate</th>'
        + '<th class="text-right">All-time Redeemed</th>'
        + '<th>Valid Until</th><th>Status</th>'
        + '</tr></thead>'
        + '<tbody id="vouchers-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // Blast → conversion table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Blast &rarr; Conversion</h5>'
        + '<p class="text-muted" style="font-size:12px;margin-bottom:10px;">Only blasts with an attached voucher are shown. Redemptions counted from blast time onward.</p>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr>'
        + '<th>Date</th><th>Blast / Promo</th><th>Trigger</th>'
        + '<th class="text-right">Recipients</th>'
        + '<th class="text-right">Sent (SMS+Push)</th>'
        + '<th class="text-right">Redeemed</th>'
        + '<th class="text-right">Conversion</th>'
        + '</tr></thead>'
        + '<tbody id="blasts-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    var REWARD_LABELS = {
        discount_pct:   '% Discount',
        discount_fixed: 'Fixed Discount',
        points_bonus:   'Points Bonus',
        free_item:      'Free Item'
    };
    var TRIGGER_LABELS = {
        standard:       'Standard',
        birthday:       'Birthday',
        signup_freebies:'Sign-up',
        stale_points:   'Win-back'
    };

    // Vouchers table
    document.getElementById('vouchers-tbody').innerHTML = vouchers.length
        ? vouchers.map(function(v, i) {
            var issued   = parseInt(v.instances_issued_period || 0);
            var redeemed = parseInt(v.redemptions_in_period   || 0);
            var rate     = issued > 0 ? (redeemed / issued * 100).toFixed(1) + '%' : '—';
            var expired  = v.valid_until && v.valid_until < today;
            var status   = !parseInt(v.is_active)
                ? '<span class="label label-default">Inactive</span>'
                : expired
                    ? '<span class="label label-danger">Expired</span>'
                    : '<span class="label label-success">Active</span>';
            var reward = v.reward_type === 'free_item'
                ? 'Free: ' + htmlEnc(v.reward_item || '—')
                : (v.reward_type === 'discount_pct'
                    ? parseFloat(v.reward_value||0).toFixed(0) + '% off'
                    : 'RM ' + fmt2(v.reward_value) + ' off');
            return '<tr>'
                + '<td class="text-muted">' + (i+1) + '</td>'
                + '<td><strong>' + htmlEnc(v.title) + '</strong><br><small class="text-muted">' + htmlEnc(REWARD_LABELS[v.reward_type]||v.reward_type) + '</small></td>'
                + '<td><code>' + htmlEnc(v.base_code) + '</code>'
                + (v.code_mode === 'unique_per_member' ? ' <span class="label label-info" style="font-size:10px;">unique</span>' : '')
                + '</td>'
                + '<td>' + htmlEnc(reward) + '</td>'
                + '<td class="text-right">' + (v.code_mode === 'unique_per_member' ? fmtInt(issued) : '<span class="text-muted">Shared</span>') + '</td>'
                + '<td class="text-right"><strong>' + fmtInt(redeemed) + '</strong></td>'
                + '<td class="text-right">' + rate + '</td>'
                + '<td class="text-right text-muted">' + fmtInt(v.total_redemptions_ever) + '</td>'
                + '<td style="font-size:12px;">' + (v.valid_until ? htmlEnc(v.valid_until) : '<span class="text-muted">—</span>') + '</td>'
                + '<td>' + status + '</td>'
                + '</tr>';
          }).join('')
        : '<tr><td colspan="10" class="text-muted text-center" style="padding:20px;">No vouchers found.</td></tr>';

    // Blasts table
    document.getElementById('blasts-tbody').innerHTML = blasts.length
        ? blasts.map(function(b) {
            var sent       = parseInt(b.sms_sent||0) + parseInt(b.push_sent||0);
            var redeemed   = parseInt(b.redemptions||0);
            var recipients = parseInt(b.recipient_count||0);
            var conv       = recipients > 0 ? (redeemed / recipients * 100).toFixed(1) + '%' : '—';
            var trigLabel  = TRIGGER_LABELS[b.trigger_type] || b.trigger_type;
            var trigClass  = b.trigger_type === 'birthday' ? 'label-danger'
                : b.trigger_type === 'stale_points'        ? 'label-warning'
                : b.trigger_type === 'signup_freebies'     ? 'label-success'
                : 'label-default';
            return '<tr>'
                + '<td style="white-space:nowrap;font-size:12px;color:#337ab7;">' + htmlEnc((b.blasted_at||'').slice(0,16).replace('T',' ')) + '</td>'
                + '<td><strong>' + htmlEnc(b.promo_title) + '</strong></td>'
                + '<td><span class="label ' + trigClass + '">' + htmlEnc(trigLabel) + '</span></td>'
                + '<td class="text-right">' + fmtInt(recipients) + '</td>'
                + '<td class="text-right">' + fmtInt(sent) + '</td>'
                + '<td class="text-right"><strong>' + fmtInt(redeemed) + '</strong></td>'
                + '<td class="text-right">' + (redeemed > 0 ? '<strong class="text-success">' + conv + '</strong>' : '<span class="text-muted">' + conv + '</span>') + '</td>'
                + '</tr>';
          }).join('')
        : '<tr><td colspan="7" class="text-muted text-center" style="padding:20px;">No voucher blasts in this period.</td></tr>';
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}
</script>
<?php init_tail(); ?>
