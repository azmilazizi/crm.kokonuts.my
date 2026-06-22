<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _trendChart = null, _dowChart = null;
var _lastData   = null;

function renderReport(r) {
    _lastData = r;
    var s    = r.summary  || {};
    var trend = normalizeTrend(r.trend || [], r.group_by);
    var dow   = r.dow     || [];
    var el    = document.getElementById('report-content');
    var gb    = r.group_by || 'daily';
    var gbLbl = GROUP_BY_LABEL[gb] || 'Daily';

    // ── 1. PRIMARY: Trend chart ───────────────────────────────────────────────
    el.innerHTML = ''
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h4 class="no-margin-top bold">Sales Trend — <span class="text-muted" style="font-size:14px;font-weight:400;">' + gbLbl + '</span></h4>'
        + '<canvas id="chart-trend" height="60"></canvas>'
        + '</div></div></div>'
        + '</div>'

        // ── 2. KPI summary ────────────────────────────────────────────────────
        + '<div class="row">'
        + kpiCard('green',  'Net Sales',        'RM ' + fmt2(s.net_sales))
        + kpiCard('blue',   'Gross Sales',       'RM ' + fmt2(s.gross_sales))
        + kpiCard('orange', 'Transactions',      fmtInt(s.transaction_count))
        + kpiCard('purple', 'Avg Transaction',   'RM ' + fmt2(s.avg_transaction))
        + '</div>'
        + '<div class="row">'
        + kpiCard('',      'Tax Collected',     'RM ' + fmt2(s.total_tax))
        + kpiCard('',      'Total Discounts',   'RM ' + fmt2(s.total_discounts))
        + kpiCard('',      'Refunds',           'RM ' + fmt2(s.total_refunds) + ' (' + fmtInt(s.refund_count) + ')')
        + kpiCard('',      'Items Sold',         fmtInt(s.items_sold))
        + '</div>'

        // ── 3. Day-of-week + breakdown table ──────────────────────────────────
        + '<div class="row">'
        + '<div class="col-md-5"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Sales by Day of Week</h5>'
        + '<canvas id="chart-dow" height="160"></canvas>'
        + '</div></div></div>'
        + '<div class="col-md-7"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Trend Breakdown <small class="text-muted">(' + gbLbl + ')</small></h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin" id="tbl-trend">'
        + '<thead><tr><th>' + gbLbl + '</th><th class="text-right">Gross</th><th class="text-right">Discounts</th><th class="text-right">Net Sales</th><th class="text-right">Txns</th></tr></thead>'
        + '<tbody id="trend-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // Trend chart
    if (_trendChart) _trendChart.destroy();
    var labels   = trend.map(function(d){ return d.label; });
    var netData  = trend.map(function(d){ return parseFloat(d.net_sales||0); });
    var txnData  = trend.map(function(d){ return parseInt(d.transaction_count||0); });
    var isBar    = (gb === 'hourly' || gb === 'dow');
    _trendChart  = new Chart(document.getElementById('chart-trend').getContext('2d'), {
        type: isBar ? 'bar' : 'line',
        data: { labels: labels, datasets: [
            { label: 'Net Sales (RM)', data: netData,
              borderColor: '#337ab7', backgroundColor: isBar ? 'rgba(51,122,183,0.7)' : 'rgba(51,122,183,0.08)',
              borderWidth: 2, pointRadius: (!isBar && labels.length > 14) ? 0 : 4,
              fill: !isBar, yAxisID: 'y-rev' },
            { label: 'Transactions', data: txnData, type: 'line',
              borderColor: '#5cb85c', backgroundColor: 'transparent',
              borderWidth: 2, pointRadius: labels.length > 14 ? 0 : 3,
              fill: false, yAxisID: 'y-txn' }
        ]},
        options: {
            animation: animOpts(labels.length),
            responsive: true,
            scales: {
                xAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }],
                yAxes: [
                    { id: 'y-rev', position: 'left',  ticks: { fontSize: 11, callback: function(v){ return 'RM '+v.toLocaleString(); } } },
                    { id: 'y-txn', position: 'right', ticks: { fontSize: 11 }, gridLines: { display: false } }
                ]
            },
            tooltips: { mode: 'index', intersect: false }
        }
    });

    // DoW chart
    var DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var dowMap = {};
    dow.forEach(function(d){ dowMap[d.day_name] = d; });
    var dowLabels = DAYS.map(function(d){ return d.slice(0,3); });
    var dowData   = DAYS.map(function(d){ return dowMap[d] ? parseFloat(dowMap[d].net_sales||0) : 0; });
    if (_dowChart) _dowChart.destroy();
    _dowChart = new Chart(document.getElementById('chart-dow').getContext('2d'), {
        type: 'bar',
        data: { labels: dowLabels, datasets: [{
            label: 'Net Sales (RM)', data: dowData,
            backgroundColor: 'rgba(240,173,78,0.75)', borderColor: '#f0ad4e', borderWidth: 1
        }]},
        options: { animation: animOpts(7), responsive: true, legend: { display: false },
            scales: {
                xAxes: [{ gridLines: { display: false } }],
                yAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
            }
        }
    });

    // Trend breakdown table + total row
    var trendWrap = document.getElementById('tbl-trend').parentNode;
    var tbody = document.getElementById('trend-tbody');
    tbody.innerHTML = trend.length ? trend.map(function(d) {
        return '<tr>'
            + '<td>' + htmlEnc(d.label) + '</td>'
            + '<td class="text-right">RM ' + fmt2(d.gross_sales) + '</td>'
            + '<td class="text-right text-warning">RM ' + fmt2(d.total_discounts) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(d.net_sales) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(d.transaction_count) + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="5" class="text-muted text-center">No data for this period</td></tr>';
    if (trend.length) {
        document.getElementById('tbl-trend').insertAdjacentHTML('beforeend', mkTotal(trend, [
            { label: 'Total' },
            { key: 'gross_sales',       sum: true, fmt: 'rm' },
            { key: 'total_discounts',   sum: true, fmt: 'rm' },
            { key: 'net_sales',         sum: true, fmt: 'rm' },
            { key: 'transaction_count', sum: true, fmt: 'int' }
        ]));
    }
    scrollTable(trendWrap, trend.length);
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

function getCSVData() {
    var trend = normalizeTrend((_lastData && _lastData.trend) || [], (_lastData && _lastData.group_by) || 'daily');
    return {
        filename: 'sales-report.csv',
        cols: [
            { key: 'label',             label: GROUP_BY_LABEL[(_lastData && _lastData.group_by) || 'daily'] },
            { key: 'gross_sales',        label: 'Gross Sales (RM)' },
            { key: 'total_discounts',    label: 'Discounts (RM)' },
            { key: 'net_sales',          label: 'Net Sales (RM)' },
            { key: 'total_tax',          label: 'Tax (RM)' },
            { key: 'transaction_count',  label: 'Transactions' }
        ],
        rows: trend
    };
}
</script>
<?php init_tail(); ?>
