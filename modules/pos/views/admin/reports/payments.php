<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _trendChart = null, _payChart = null;
var _lastData   = null;

function renderReport(r) {
    _lastData = r;
    var rows  = r.breakdown || [];
    var trend = r.trend     || [];
    var el    = document.getElementById('report-content');
    var gb    = r.group_by || 'daily';
    var gbLbl = GROUP_BY_LABEL[gb] || 'Daily';

    var totalAmt = rows.reduce(function(a,b){ return a+parseFloat(b.total_amount||0); },0);
    var totalTxn = rows.reduce(function(a,b){ return a+parseInt(b.transaction_count||0); },0);
    var totalCB  = rows.reduce(function(a,b){ return a+parseFloat(b.total_cashback||0); },0);

    // ── 1. PRIMARY: Payment Trend ─────────────────────────────────────────────
    el.innerHTML = ''
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h4 class="no-margin-top bold">Payment Trend by Method — <span class="text-muted" style="font-size:14px;font-weight:400;">' + gbLbl + '</span></h4>'
        + '<canvas id="chart-trend" height="60"></canvas>'
        + '<p id="trend-empty" class="text-muted text-center small" style="display:none;">No payment data in this period.</p>'
        + '</div></div></div>'
        + '</div>'

        // ── 2. KPIs ───────────────────────────────────────────────────────────
        + '<div class="row">'
        + kpiCard('green',  'Total Collected',  'RM ' + fmt2(totalAmt))
        + kpiCard('blue',   'Transactions',      fmtInt(totalTxn))
        + kpiCard('orange', 'Payment Methods',   rows.length)
        + kpiCard('red',    'Cash Back Given',   'RM ' + fmt2(totalCB))
        + '</div>'

        // ── 3. Payment donut + Methods detail ────────────────────────────────
        + '<div class="row">'
        + '<div class="col-md-4"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Payment Breakdown</h5>'
        + '<canvas id="chart-pay" height="200"></canvas>'
        + '<div id="pay-legend" class="mtop10 small"></div>'
        + '</div></div></div>'
        + '<div class="col-md-8"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Payment Methods Detail</h5>'
        + '<div id="pay-table-wrap" style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>Method</th><th>Type</th><th class="text-right">Transactions</th><th class="text-right">Total Amount</th><th class="text-right">Cash Back</th><th class="text-right">Share</th></tr></thead>'
        + '<tbody id="pay-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // ── 4. Refunds ────────────────────────────────────────────────────────
        + '<div class="row">'
        + '<div class="col-md-6"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Refunds by Payment Method</h5>'
        + '<div id="refund-table-wrap" style="overflow-x:auto;"><table class="table table-condensed no-margin">'
        + '<thead><tr><th>Method</th><th class="text-right">Refunds</th><th class="text-right">Total Refunded</th></tr></thead>'
        + '<tbody id="refund-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // Multi-series trend (one line per payment method)
    if (_trendChart) _trendChart.destroy();
    if (trend.length) {
        document.getElementById('trend-empty').style.display = 'none';
        var ms = buildMultiSeries(trend, 'payment_name', 'total_amount');
        var isBar = (gb === 'hourly' || gb === 'hourly_by_day' || gb === 'dow');
        var datasets = ms.datasets.map(function(ds, idx) {
            return {
                label: ds.name,
                data:  ds.data,
                borderColor:     CHART_COLORS[idx % CHART_COLORS.length],
                backgroundColor: isBar
                    ? CHART_COLORS[idx % CHART_COLORS.length].replace(')', ', 0.7)').replace('rgb', 'rgba')
                    : 'transparent',
                borderWidth: 2,
                pointRadius: ms.labels.length > 14 ? 0 : 3,
                fill: false
            };
        });
        _trendChart = new Chart(document.getElementById('chart-trend').getContext('2d'), {
            type: isBar ? 'bar' : 'line',
            data: { labels: ms.labels, datasets: datasets },
            options: {
                animation: animOpts(ms.labels.length),
                responsive: true,
                scales: {
                    xAxes: [{ stacked: isBar, ticks: { fontSize: 11 }, gridLines: { display: false } }],
                    yAxes: [{ stacked: isBar, ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
                },
                tooltips: { mode: 'index', intersect: false }
            }
        });
    } else {
        document.getElementById('chart-trend').style.display = 'none';
        document.getElementById('trend-empty').style.display = '';
    }

    // Payment donut
    if (_payChart) _payChart.destroy();
    if (rows.length) {
        var payLabels = rows.map(function(r){ return r.payment_name; });
        var payData   = rows.map(function(r){ return parseFloat(r.total_amount||0); });
        _payChart = new Chart(document.getElementById('chart-pay').getContext('2d'), {
            type: 'doughnut',
            data: { labels: payLabels, datasets: [{ data: payData, backgroundColor: CHART_COLORS.slice(0,payData.length), borderWidth: 2 }]},
            options: { animation: animOpts(payData.length), responsive: true, legend: { display: false }, cutoutPercentage: 55,
                tooltips: { callbacks: { label: function(ti,d){ return ' '+d.labels[ti.index]+': RM '+fmt2(d.datasets[0].data[ti.index]); } } }
            }
        });
        document.getElementById('pay-legend').innerHTML = payLabels.map(function(l,i){
            var pct = totalAmt > 0 ? (payData[i]/totalAmt*100).toFixed(1) : 0;
            return '<span style="margin-right:10px;"><span style="display:inline-block;width:10px;height:10px;background:'+CHART_COLORS[i]+';border-radius:2px;margin-right:4px;"></span>'
                + htmlEnc(l) + ' <strong>' + pct + '%</strong></span>';
        }).join('');
    }

    // Methods detail table + total row
    var payWrap = document.getElementById('pay-tbody').closest('div[style]') ||
                  document.getElementById('pay-tbody').parentNode.parentNode;
    document.getElementById('pay-tbody').innerHTML = rows.length ? rows.map(function(p, i) {
        return '<tr>'
            + '<td><span style="display:inline-block;width:10px;height:10px;background:'+CHART_COLORS[i]+';border-radius:2px;margin-right:6px;"></span>'
            + '<strong>' + htmlEnc(p.payment_name) + '</strong></td>'
            + '<td><small class="text-muted">' + htmlEnc(p.payment_type||'') + '</small></td>'
            + '<td class="text-right">' + fmtInt(p.transaction_count) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(p.total_amount) + '</strong></td>'
            + '<td class="text-right text-muted">RM ' + fmt2(p.total_cashback) + '</td>'
            + '<td class="text-right">' + fmt2(p.percentage) + '%</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="6" class="text-muted text-center">No payment data</td></tr>';
    if (rows.length) {
        document.querySelector('#pay-table-wrap table').insertAdjacentHTML('beforeend', mkTotal(rows, [
            { label: 'Total' }, { skip: true },
            { key: 'transaction_count', sum: true, fmt: 'int' },
            { key: 'total_amount',      sum: true, fmt: 'rm' },
            { key: 'total_cashback',    sum: true, fmt: 'rm' },
            { derived: function(){ return '100.0%'; } }
        ]));
    }
    scrollTable(document.getElementById('pay-table-wrap'), rows.length);

    // Refunds table + total row
    var refunds = r.refunds || [];
    document.getElementById('refund-tbody').innerHTML = refunds.length ? refunds.map(function(rf) {
        return '<tr>'
            + '<td>' + htmlEnc(rf.payment_name) + '</td>'
            + '<td class="text-right">' + fmtInt(rf.refund_count) + '</td>'
            + '<td class="text-right text-danger"><strong>RM ' + fmt2(rf.total_refunded) + '</strong></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="3" class="text-muted text-center">No refunds for this period</td></tr>';
    if (refunds.length) {
        document.querySelector('#refund-table-wrap table').insertAdjacentHTML('beforeend', mkTotal(refunds, [
            { label: 'Total' },
            { key: 'refund_count',   sum: true, fmt: 'int' },
            { key: 'total_refunded', sum: true, fmt: 'rm' }
        ]));
    }
    scrollTable(document.getElementById('refund-table-wrap'), refunds.length);
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

function getCSVData() {
    var rows = (_lastData && _lastData.breakdown) || [];
    return {
        filename: 'payments-report.csv',
        cols: [
            { key: 'payment_name',      label: 'Payment Method' },
            { key: 'payment_type',       label: 'Type' },
            { key: 'transaction_count',  label: 'Transactions' },
            { key: 'total_amount',       label: 'Total Amount (RM)' },
            { key: 'total_cashback',     label: 'Cash Back (RM)' },
            { key: 'percentage',         label: 'Share (%)' }
        ],
        rows: rows
    };
}
</script>
<?php init_tail(); ?>
