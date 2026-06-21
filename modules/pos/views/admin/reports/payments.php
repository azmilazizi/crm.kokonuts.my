<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _payChart = null, _payTrendChart = null;

function renderReport(r) {
    var el   = document.getElementById('report-content');
    var rows = r.breakdown || [];
    var totalAmt = rows.reduce(function(a,b){ return a+parseFloat(b.total_amount||0); },0);
    var totalTxn = rows.reduce(function(a,b){ return a+parseInt(b.transaction_count||0); },0);
    var totalCB  = rows.reduce(function(a,b){ return a+parseFloat(b.total_cashback||0); },0);

    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('green',  'Total Collected',   'RM ' + fmt2(totalAmt))
        + kpiCard('blue',   'Transactions',       fmtInt(totalTxn))
        + kpiCard('orange', 'Payment Methods',    rows.length)
        + kpiCard('red',    'Cash Back Given',    'RM ' + fmt2(totalCB))
        + '</div>'

        // Donut + Breakdown table
        + '<div class="row">'
        + '<div class="col-md-4"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Payment Breakdown</h5>'
        + '<canvas id="chart-pay" height="200"></canvas>'
        + '<div id="pay-legend" class="mtop10 small"></div>'
        + '</div></div></div>'
        + '<div class="col-md-8"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Payment Methods Detail</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>Method</th><th>Type</th><th class="text-right">Transactions</th><th class="text-right">Total Amount</th><th class="text-right">Cash Back</th><th class="text-right">Share</th></tr></thead>'
        + '<tbody id="pay-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // Daily trend chart
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Daily Payment Trend by Method</h5>'
        + '<canvas id="chart-pay-trend" height="80"></canvas>'
        + '</div></div></div>'
        + '</div>'

        // Refunds breakdown
        + '<div class="row">'
        + '<div class="col-md-6"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Refunds by Payment Method</h5>'
        + '<table class="table table-condensed no-margin">'
        + '<thead><tr><th>Method</th><th class="text-right">Refunds</th><th class="text-right">Total Refunded</th></tr></thead>'
        + '<tbody id="refund-tbody"></tbody>'
        + '</table>'
        + '</div></div></div>'
        + '</div>';

    // Payment donut
    if (_payChart) _payChart.destroy();
    if (rows.length) {
        var payLabels = rows.map(function(r){ return r.payment_name; });
        var payData   = rows.map(function(r){ return parseFloat(r.total_amount||0); });
        _payChart = new Chart(document.getElementById('chart-pay').getContext('2d'), {
            type: 'doughnut',
            data: { labels: payLabels, datasets: [{ data: payData, backgroundColor: CHART_COLORS.slice(0,payData.length), borderWidth: 2 }]},
            options: { responsive: true, legend: { display: false }, cutoutPercentage: 55,
                tooltips: { callbacks: { label: function(ti,d){ return ' '+d.labels[ti.index]+': RM '+fmt2(d.datasets[0].data[ti.index]); } } }
            }
        });
        document.getElementById('pay-legend').innerHTML = payLabels.map(function(l,i){
            var pct = totalAmt > 0 ? (payData[i]/totalAmt*100).toFixed(1) : 0;
            return '<span style="margin-right:10px;"><span style="display:inline-block;width:10px;height:10px;background:'+CHART_COLORS[i]+';border-radius:2px;margin-right:4px;"></span>'
                + htmlEnc(l) + ' <strong>' + pct + '%</strong></span>';
        }).join('');
    }

    // Payment detail table
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

    // Daily trend by payment method (multi-line)
    if (_payTrendChart) _payTrendChart.destroy();
    var daily = r.daily || [];
    if (daily.length) {
        var allDates   = [...new Set(daily.map(function(d){ return d.date; }))].sort();
        var allMethods = [...new Set(daily.map(function(d){ return d.payment_name; }))];
        var datasets   = allMethods.map(function(method, idx) {
            return {
                label: method,
                data: allDates.map(function(date) {
                    var found = daily.find(function(d){ return d.date === date && d.payment_name === method; });
                    return found ? parseFloat(found.total_amount||0) : 0;
                }),
                borderColor: CHART_COLORS[idx % CHART_COLORS.length],
                backgroundColor: 'transparent',
                borderWidth: 2, pointRadius: allDates.length > 14 ? 2 : 4
            };
        });
        _payTrendChart = new Chart(document.getElementById('chart-pay-trend').getContext('2d'), {
            type: 'line',
            data: { labels: allDates, datasets: datasets },
            options: { responsive: true,
                scales: {
                    xAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }],
                    yAxes: [{ ticks: { fontSize: 11, callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
                }
            }
        });
    }

    // Refunds table
    var refunds = r.refunds || [];
    document.getElementById('refund-tbody').innerHTML = refunds.length ? refunds.map(function(rf) {
        return '<tr>'
            + '<td>' + htmlEnc(rf.payment_name) + '</td>'
            + '<td class="text-right">' + fmtInt(rf.refund_count) + '</td>'
            + '<td class="text-right text-danger"><strong>RM ' + fmt2(rf.total_refunded) + '</strong></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="3" class="text-muted text-center">No refunds for this period</td></tr>';
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
