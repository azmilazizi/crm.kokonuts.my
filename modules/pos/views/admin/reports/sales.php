<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _trendChart = null, _hourlyChart = null, _dowChart = null;

function renderReport(r) {
    var s   = r.summary || {};
    var el  = document.getElementById('report-content');

    el.innerHTML = ''
        // ── KPI row ──
        + '<div class="row" id="rpt-kpi-row">'
        + kpiCard('green',  'Net Sales',         'RM ' + fmt2(s.net_sales))
        + kpiCard('blue',   'Gross Sales',        'RM ' + fmt2(s.gross_sales))
        + kpiCard('orange', 'Transactions',       fmtInt(s.transaction_count))
        + kpiCard('purple', 'Avg Transaction',    'RM ' + fmt2(s.avg_transaction))
        + '</div>'
        + '<div class="row">'
        + kpiCard('',      'Tax Collected',      'RM ' + fmt2(s.total_tax))
        + kpiCard('',      'Total Discounts',    'RM ' + fmt2(s.total_discounts))
        + kpiCard('',      'Refunds',            'RM ' + fmt2(s.total_refunds) + ' (' + fmtInt(s.refund_count) + ')')
        + kpiCard('',      'Items Sold',          fmtInt(s.items_sold))
        + '</div>'
        // ── Daily trend ──
        + '<div class="row">'
        + '<div class="col-md-8"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Daily Sales Trend</h5>'
        + '<canvas id="chart-trend" height="90"></canvas>'
        + '</div></div></div>'
        // ── Hourly breakdown ──
        + '<div class="col-md-4"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Sales by Hour</h5>'
        + '<canvas id="chart-hourly" height="180"></canvas>'
        + '</div></div></div>'
        + '</div>'
        // ── Day-of-week ──
        + '<div class="row">'
        + '<div class="col-md-6"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Sales by Day of Week</h5>'
        + '<canvas id="chart-dow" height="120"></canvas>'
        + '</div></div></div>'
        // ── Daily breakdown table ──
        + '<div class="col-md-6"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Daily Breakdown</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin" id="tbl-daily">'
        + '<thead><tr><th>Date</th><th class="text-right">Gross</th><th class="text-right">Discounts</th><th class="text-right">Net Sales</th><th class="text-right">Txns</th></tr></thead>'
        + '<tbody id="daily-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // Daily trend chart
    var dailyLabels = (r.daily||[]).map(function(d){ return d.date; });
    var dailyData   = (r.daily||[]).map(function(d){ return parseFloat(d.net_sales||0); });
    if (_trendChart) _trendChart.destroy();
    _trendChart = new Chart(document.getElementById('chart-trend').getContext('2d'), {
        type: 'line',
        data: { labels: dailyLabels, datasets: [{
            label: 'Net Sales (RM)', data: dailyData,
            borderColor: '#337ab7', backgroundColor: 'rgba(51,122,183,0.08)',
            borderWidth: 2, pointRadius: dailyLabels.length > 14 ? 2 : 4, fill: true
        }]},
        options: { responsive: true, legend: { display: false },
            scales: {
                xAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }],
                yAxes: [{ ticks: { fontSize: 11, callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
            },
            tooltips: { callbacks: { label: function(ti,d){ return ' RM '+fmt2(d.datasets[0].data[ti.index]); } } }
        }
    });

    // Hourly chart
    var hrLabels = [], hrData = [], hrTxns = [];
    for (var h = 0; h < 24; h++) {
        var found = (r.hourly||[]).find(function(x){ return parseInt(x.hour) === h; });
        hrLabels.push(h + ':00');
        hrData.push(found ? parseFloat(found.net_sales||0) : 0);
        hrTxns.push(found ? parseInt(found.transaction_count||0) : 0);
    }
    if (_hourlyChart) _hourlyChart.destroy();
    _hourlyChart = new Chart(document.getElementById('chart-hourly').getContext('2d'), {
        type: 'bar',
        data: { labels: hrLabels, datasets: [
            { label: 'Revenue (RM)', data: hrData, backgroundColor: 'rgba(92,184,92,0.7)', borderColor: '#5cb85c', borderWidth: 1, yAxisID: 'y-revenue' },
            { label: 'Transactions', data: hrTxns, backgroundColor: 'rgba(51,122,183,0.4)', borderColor: '#337ab7', borderWidth: 1, type: 'line', yAxisID: 'y-txns', pointRadius: 3 }
        ]},
        options: { responsive: true,
            scales: {
                xAxes: [{ ticks: { fontSize: 9 }, gridLines: { display: false } }],
                yAxes: [
                    { id: 'y-revenue', position: 'left',  ticks: { fontSize: 10, callback: function(v){ return 'RM '+v; } } },
                    { id: 'y-txns',    position: 'right', ticks: { fontSize: 10 }, gridLines: { display: false } }
                ]
            }
        }
    });

    // Day-of-week chart
    var DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var dowMap = {};
    (r.dow||[]).forEach(function(d){ dowMap[parseInt(d.day_of_week)] = d; });
    var dowLabels = [], dowData = [], dowTxns = [];
    for (var i = 1; i <= 7; i++) {
        var idx = i === 7 ? 0 : i; // MySQL: 1=Sun..7=Sat
        dowLabels.push(DAYS[idx]);
        dowData.push(dowMap[i] ? parseFloat(dowMap[i].net_sales||0) : 0);
        dowTxns.push(dowMap[i] ? parseInt(dowMap[i].transaction_count||0) : 0);
    }
    if (_dowChart) _dowChart.destroy();
    _dowChart = new Chart(document.getElementById('chart-dow').getContext('2d'), {
        type: 'bar',
        data: { labels: dowLabels, datasets: [
            { label: 'Net Sales (RM)', data: dowData, backgroundColor: 'rgba(240,173,78,0.75)', borderColor: '#f0ad4e', borderWidth: 1 }
        ]},
        options: { responsive: true, legend: { display: false },
            scales: {
                xAxes: [{ gridLines: { display: false } }],
                yAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
            },
            tooltips: { callbacks: {
                label: function(ti,d){ return ' RM '+fmt2(d.datasets[0].data[ti.index])+' ('+dowTxns[ti.index]+' txns)'; }
            }}
        }
    });

    // Daily breakdown table
    var tbody = document.getElementById('daily-tbody');
    var rows  = r.daily || [];
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-muted text-center">No data for this period</td></tr>';
    } else {
        tbody.innerHTML = rows.map(function(d) {
            return '<tr>'
                + '<td>' + d.date + '</td>'
                + '<td class="text-right">RM ' + fmt2(d.gross_sales) + '</td>'
                + '<td class="text-right text-warning">RM ' + fmt2(d.total_discounts) + '</td>'
                + '<td class="text-right"><strong>RM ' + fmt2(d.net_sales) + '</strong></td>'
                + '<td class="text-right">' + fmtInt(d.transaction_count) + '</td>'
                + '</tr>';
        }).join('');
    }
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
