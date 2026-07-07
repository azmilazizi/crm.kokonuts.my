<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('loyalty/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _discChart = null, _promoChart = null;

function renderReport(r) {
    var el     = document.getElementById('report-content');
    var disc   = r.discount_types   || [];
    var pros   = r.promotions       || [];
    var items  = r.discounted_items || [];
    var events = r.events           || [];

    var totalDiscount      = disc.reduce(function(a,b){ return a+parseFloat(b.total_discount||0); },0);
    var totalPromosUsed    = pros.reduce(function(a,b){ return a+parseInt(b.receipts_used||0); },0);
    var totalPromoDiscount = pros.reduce(function(a,b){ return a+parseFloat(b.total_discount_given||0); },0);
    var totalEvtBlasts     = events.reduce(function(a,b){ return a+parseInt(b.blast_count||0); },0);
    var totalEvtRecp       = events.reduce(function(a,b){ return a+parseInt(b.total_recipients||0); },0);
    var totalEvtRedeem     = events.reduce(function(a,b){ return a+parseInt(b.voucher_redemptions||0); },0);

    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('red',    'Total Discounts Given', 'RM ' + fmt2(totalDiscount))
        + kpiCard('orange', 'Active Promotions',      pros.length)
        + kpiCard('blue',   'Promo Receipts',         fmtInt(totalPromosUsed))
        + kpiCard('purple', 'Promo Discount Total',   'RM ' + fmt2(totalPromoDiscount))
        + '</div>'
        + '<div class="row">'
        + kpiCard('blue',   'Events / Loyalty Promos', events.length)
        + kpiCard('',       'Total Blasts (period)',    fmtInt(totalEvtBlasts))
        + kpiCard('',       'Total Blast Recipients',   fmtInt(totalEvtRecp))
        + kpiCard('green',  'Voucher Redemptions',      fmtInt(totalEvtRedeem))
        + '</div>'

        // Discount type donut + Promo performance bar
        + '<div class="row">'
        + '<div class="col-md-4"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Discount by Type</h5>'
        + '<canvas id="chart-disc" height="200"></canvas>'
        + '<div id="disc-legend" class="mtop10 small"></div>'
        + '<p id="disc-empty" class="text-muted text-center small" style="display:none;">No discounts in this period.</p>'
        + '</div></div></div>'
        + '<div class="col-md-8"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Promotion Performance</h5>'
        + '<canvas id="chart-promos" height="140"></canvas>'
        + '<p id="promo-empty" class="text-muted text-center small" style="display:none;">No promotions used in this period.</p>'
        + '</div></div></div>'
        + '</div>'

        // Promotions detail table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Promotion Detail</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>#</th><th>Promotion</th><th>Type</th><th class="text-right">Receipts Used</th><th class="text-right">Items in Promo</th><th class="text-right">Discount Given</th></tr></thead>'
        + '<tbody id="promos-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // Most discounted items table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Most Discounted Items</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>#</th><th>Product</th><th>Category</th><th class="text-right">Times Discounted</th><th class="text-right">Avg Discount/Line</th><th class="text-right">Total Discount</th></tr></thead>'
        + '<tbody id="disc-items-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // Events & Loyalty Promotions table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Events &amp; Loyalty Promotions</h5>'
        + '<p class="text-muted small" style="margin-bottom:10px;">Shows all loyalty events and promotions. Blast counts and redemptions are filtered to the selected period; status reflects current active state.</p>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr>'
        + '<th>#</th><th>Title</th><th>Type</th><th>Trigger</th>'
        + '<th>Period</th>'
        + '<th class="text-right">Blasts</th>'
        + '<th class="text-right">Recipients</th>'
        + '<th class="text-right">SMS</th>'
        + '<th class="text-right">Push</th>'
        + '<th>Voucher</th>'
        + '<th class="text-right">Redemptions</th>'
        + '<th class="text-right">Unique</th>'
        + '<th class="text-right">Conv. Rate</th>'
        + '<th>Status</th>'
        + '</tr></thead>'
        + '<tbody id="events-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    var DISC_LABELS = { promotion: 'Promotion', manual: 'Manual', loyalty: 'Loyalty Redemption' };
    var DISC_COLORS = { promotion: '#f0ad4e', manual: '#d9534f', loyalty: '#9b59b6' };

    // Discount donut
    if (_discChart) _discChart.destroy();
    if (disc.length) {
        document.getElementById('disc-empty').style.display = 'none';
        var dLabels = disc.map(function(d){ return DISC_LABELS[d.discount_type] || d.discount_type; });
        var dData   = disc.map(function(d){ return parseFloat(d.total_discount||0); });
        var dColors = disc.map(function(d){ return DISC_COLORS[d.discount_type] || '#aaa'; });
        _discChart = new Chart(document.getElementById('chart-disc').getContext('2d'), {
            type: 'doughnut',
            data: { labels: dLabels, datasets: [{ data: dData, backgroundColor: dColors, borderWidth: 2 }]},
            options: { responsive: true, legend: { display: false }, cutoutPercentage: 55,
                tooltips: { callbacks: { label: function(ti,d){ return ' '+d.labels[ti.index]+': RM '+fmt2(d.datasets[0].data[ti.index]); } } }
            }
        });
        var dTotal = dData.reduce(function(a,b){return a+b;},0);
        document.getElementById('disc-legend').innerHTML = dLabels.map(function(l,i){
            var pct = dTotal > 0 ? (dData[i]/dTotal*100).toFixed(1) : 0;
            return '<span style="margin-right:12px;"><span style="display:inline-block;width:10px;height:10px;background:'+dColors[i]+';border-radius:2px;margin-right:4px;"></span>'
                + htmlEnc(l) + ' <strong>RM ' + fmt2(dData[i]) + '</strong> (' + pct + '%)</span>';
        }).join('');
    } else {
        document.getElementById('chart-disc').style.display = 'none';
        document.getElementById('disc-empty').style.display = '';
    }

    // Promo horizontal bar
    if (_promoChart) _promoChart.destroy();
    if (pros.length) {
        document.getElementById('promo-empty').style.display = 'none';
        _promoChart = new Chart(document.getElementById('chart-promos').getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: pros.map(function(p){ return p.promotion_name; }),
                datasets: [{ label: 'Discount Given (RM)', data: pros.map(function(p){ return parseFloat(p.total_discount_given||0); }),
                    backgroundColor: 'rgba(240,173,78,0.75)', borderColor: '#f0ad4e', borderWidth: 1 }]
            },
            options: { responsive: true, legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }],
                    yAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }]
                }
            }
        });
    } else {
        document.getElementById('chart-promos').style.display = 'none';
        document.getElementById('promo-empty').style.display = '';
    }

    // Promos detail table
    document.getElementById('promos-tbody').innerHTML = pros.length ? pros.map(function(p, i) {
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(p.promotion_name) + '</strong></td>'
            + '<td><span class="label label-default">' + htmlEnc(p.promotion_type) + '</span></td>'
            + '<td class="text-right">' + fmtInt(p.receipts_used) + '</td>'
            + '<td class="text-right">' + fmtInt(p.items_sold_in_promo) + '</td>'
            + '<td class="text-right text-warning"><strong>RM ' + fmt2(p.total_discount_given) + '</strong></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="6" class="text-muted text-center">No promotions applied in this period.</td></tr>';

    // Most discounted items table
    document.getElementById('disc-items-tbody').innerHTML = items.length ? items.map(function(p, i) {
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(p.item_name) + '</strong></td>'
            + '<td><small class="text-muted">' + htmlEnc(p.category_name) + '</small></td>'
            + '<td class="text-right">' + fmtInt(p.times_discounted) + '</td>'
            + '<td class="text-right">RM ' + fmt2(p.avg_discount_per_line) + '</td>'
            + '<td class="text-right text-danger"><strong>RM ' + fmt2(p.total_discount) + '</strong></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="6" class="text-muted text-center">No discounted items in this period.</td></tr>';

    // Events & Loyalty Promotions table
    var TYPE_LABELS    = { promotion: 'Promotion', event: 'Event' };
    var TRIGGER_LABELS = { standard: 'Standard', birthday: 'Birthday', signup_freebies: 'Sign-up', stale_points: 'Win-back' };
    var REWARD_LABELS  = { discount_pct: '% off', discount_fixed: 'RM off', points_bonus: 'Pts bonus', free_item: 'Free item' };
    var today          = new Date().toISOString().slice(0, 10);

    document.getElementById('events-tbody').innerHTML = events.length ? events.map(function(e, i) {
        var typeLabel  = TYPE_LABELS[e.type] || e.type || '—';
        var trigLabel  = TRIGGER_LABELS[e.trigger_type] || e.trigger_type || '—';
        var trigClass  = e.trigger_type === 'birthday'      ? 'label-danger'
                       : e.trigger_type === 'stale_points'  ? 'label-warning'
                       : e.trigger_type === 'signup_freebies' ? 'label-success'
                       : 'label-default';
        var period     = (e.start_date || e.end_date)
            ? (e.start_date || '?') + ' – ' + (e.end_date || '∞')
            : '<span class="text-muted">—</span>';
        var isExpired  = e.end_date && e.end_date < today;
        var statusBadge = !parseInt(e.is_active)
            ? '<span class="label label-default">Inactive</span>'
            : isExpired
                ? '<span class="label label-danger">Expired</span>'
                : '<span class="label label-success">Active</span>';
        var rewardStr  = '';
        if (e.voucher_id) {
            if (e.voucher_reward_type === 'free_item') {
                rewardStr = 'Free: ' + htmlEnc(e.voucher_reward_item || '—');
            } else if (e.voucher_reward_type === 'discount_pct') {
                rewardStr = parseFloat(e.voucher_reward_value||0).toFixed(0) + '% off';
            } else if (e.voucher_reward_type === 'discount_fixed') {
                rewardStr = 'RM ' + fmt2(e.voucher_reward_value) + ' off';
            } else if (e.voucher_reward_type === 'points_bonus') {
                rewardStr = '+' + fmtInt(e.voucher_reward_value) + ' pts';
            }
        }
        var voucherCell = e.voucher_id
            ? '<code style="font-size:10px;">' + htmlEnc(e.voucher_code) + '</code>'
              + '<br><small class="text-muted">' + htmlEnc(rewardStr) + '</small>'
            : '<span class="text-muted">—</span>';

        var recipients  = parseInt(e.total_recipients   || 0);
        var redeemed    = parseInt(e.voucher_redemptions || 0);
        var unique      = parseInt(e.unique_redeemers    || 0);
        var convRate    = e.voucher_id && recipients > 0
            ? (redeemed / recipients * 100).toFixed(1) + '%' : '—';

        var lastBlasted = e.last_blasted_at
            ? '<br><small class="text-muted">Last: ' + htmlEnc((e.last_blasted_at||'').slice(0,10)) + '</small>' : '';

        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(e.title) + '</strong></td>'
            + '<td><span class="label label-default">' + htmlEnc(typeLabel) + '</span></td>'
            + '<td><span class="label ' + trigClass + '">' + htmlEnc(trigLabel) + '</span></td>'
            + '<td style="font-size:11px;white-space:nowrap;">' + period + '</td>'
            + '<td class="text-right">' + fmtInt(e.blast_count) + lastBlasted + '</td>'
            + '<td class="text-right">' + fmtInt(recipients) + '</td>'
            + '<td class="text-right">' + fmtInt(e.total_sms_sent) + '</td>'
            + '<td class="text-right">' + fmtInt(e.total_push_sent) + '</td>'
            + '<td>' + voucherCell + '</td>'
            + '<td class="text-right"><strong>' + fmtInt(redeemed) + '</strong></td>'
            + '<td class="text-right text-muted">' + fmtInt(unique) + '</td>'
            + '<td class="text-right">' + (redeemed > 0 ? '<strong class="text-success">' + convRate + '</strong>' : '<span class="text-muted">' + convRate + '</span>') + '</td>'
            + '<td>' + statusBadge + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="14" class="text-muted text-center" style="padding:20px;">No events or loyalty promotions found.</td></tr>';
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
