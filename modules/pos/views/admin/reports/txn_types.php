<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _trendChart = null, _donutChart = null;
var _lastData   = null;

var TYPE_COLORS = {
    'Dine-in':       '#337ab7',
    'Takeaway':      '#5cb85c',
    'GrabFood':      '#00b14f',
    'FoodPanda':     '#e31837',
    'Delivery':      '#f0ad4e',
    'Walk-in / POS': '#9b59b6'
};
function typeColor(t) { return TYPE_COLORS[t] || '#aaa'; }

function renderReport(r) {
    _lastData = r;
    var types = r.by_type || [];
    var trend = r.trend   || [];
    var el    = document.getElementById('report-content');
    var gb    = r.group_by || 'daily';
    var gbLbl = GROUP_BY_LABEL[gb] || 'Daily';

    var totalReceipts = types.reduce(function(a,b){ return a + parseInt(b.total_receipts||0); }, 0);
    var totalRevenue  = types.reduce(function(a,b){ return a + parseFloat(b.total_revenue||0); }, 0);

    // ── 1. PRIMARY: Channel Trend ─────────────────────────────────────────────
    el.innerHTML = ''
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h4 class="no-margin-top bold">Channel Trend — <span class="text-muted" style="font-size:14px;font-weight:400;">' + gbLbl + '</span></h4>'
        + '<canvas id="chart-trend" height="60"></canvas>'
        + '<p id="trend-empty" class="text-muted text-center small" style="display:none;">No transactions in this period.</p>'
        + '</div></div></div>'
        + '</div>'

        // ── 2. KPIs ───────────────────────────────────────────────────────────
        + '<div class="row">'
        + kpiCard('blue',   'Total Transactions', fmtInt(totalReceipts))
        + kpiCard('green',  'Total Revenue',      'RM ' + fmt2(totalRevenue))
        + kpiCard('orange', 'Order Channels',     fmtInt(types.length))
        + '</div>'

        // ── 3. Revenue donut + Summary table ──────────────────────────────────
        + '<div class="row">'
        + '<div class="col-md-4"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Revenue by Channel</h5>'
        + '<canvas id="chart-donut" height="200"></canvas>'
        + '<div id="donut-legend" class="mtop10 small"></div>'
        + '<p id="donut-empty" class="text-muted text-center small" style="display:none;">No transactions in this period.</p>'
        + '</div></div></div>'
        + '<div class="col-md-8"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Transaction Type Breakdown</h5>'
        + '<div id="types-table-wrap" style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>Channel / Type</th><th class="text-right">Transactions</th><th class="text-right">% of Total</th><th class="text-right">Revenue</th><th class="text-right">Avg Order Value</th><th class="text-right">Total Discount</th></tr></thead>'
        + '<tbody id="types-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // Stacked bar / multi-line trend
    if (_trendChart) _trendChart.destroy();
    if (trend.length) {
        document.getElementById('trend-empty').style.display = 'none';
        var ms     = buildMultiSeries(trend, 'txn_type', 'total_revenue');
        var isLine = (gb !== 'hourly' && gb !== 'dow' && ms.labels.length > 7);
        var datasets = ms.datasets.map(function(ds) {
            var col = typeColor(ds.name);
            return {
                label:           ds.name,
                data:            ds.data,
                backgroundColor: col,
                borderColor:     col,
                borderWidth:     isLine ? 2 : 1,
                fill:            false,
                pointRadius:     isLine ? (ms.labels.length > 14 ? 0 : 3) : undefined
            };
        });
        _trendChart = new Chart(document.getElementById('chart-trend').getContext('2d'), {
            type: isLine ? 'line' : 'bar',
            data: { labels: ms.labels, datasets: datasets },
            options: {
                animation: animOpts(ms.labels.length),
                responsive: true,
                scales: {
                    xAxes: [{ stacked: !isLine, ticks: { fontSize: 11 }, gridLines: { display: false } }],
                    yAxes: [{ stacked: !isLine, ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
                },
                tooltips: { mode: 'index', intersect: false }
            }
        });
    } else {
        document.getElementById('chart-trend').style.display = 'none';
        document.getElementById('trend-empty').style.display = '';
    }

    // Revenue donut
    if (_donutChart) _donutChart.destroy();
    if (types.length) {
        document.getElementById('donut-empty').style.display = 'none';
        var dLabels = types.map(function(t){ return t.txn_type; });
        var dData   = types.map(function(t){ return parseFloat(t.total_revenue||0); });
        var dColors = types.map(function(t){ return typeColor(t.txn_type); });
        _donutChart = new Chart(document.getElementById('chart-donut').getContext('2d'), {
            type: 'doughnut',
            data: { labels: dLabels, datasets: [{ data: dData, backgroundColor: dColors, borderWidth: 2 }] },
            options: { animation: animOpts(dData.length), responsive: true, legend: { display: false }, cutoutPercentage: 55,
                tooltips: { callbacks: { label: function(ti,d){ return ' '+d.labels[ti.index]+': RM '+fmt2(d.datasets[0].data[ti.index]); } } }
            }
        });
        var dTotal = dData.reduce(function(a,b){ return a+b; }, 0);
        document.getElementById('donut-legend').innerHTML = dLabels.map(function(l,i){
            var pct = dTotal > 0 ? (dData[i]/dTotal*100).toFixed(1) : 0;
            return '<span style="margin-right:10px;white-space:nowrap;">'
                + '<span style="display:inline-block;width:10px;height:10px;background:'+dColors[i]+';border-radius:2px;margin-right:4px;"></span>'
                + htmlEnc(l) + ' <strong>'+pct+'%</strong></span>';
        }).join('');
    } else {
        document.getElementById('chart-donut').style.display = 'none';
        document.getElementById('donut-empty').style.display = '';
    }

    // Summary table + total row
    var typesWrap = document.getElementById('types-table-wrap');
    document.getElementById('types-tbody').innerHTML = types.length ? types.map(function(t) {
        var pct = totalReceipts > 0 ? (parseInt(t.total_receipts||0)/totalReceipts*100).toFixed(1) : '0.0';
        var dot = '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:'+typeColor(t.txn_type)+';margin-right:6px;"></span>';
        return '<tr>'
            + '<td>' + dot + '<strong>' + htmlEnc(t.txn_type) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(t.total_receipts) + '</td>'
            + '<td class="text-right text-muted">' + pct + '%</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(t.total_revenue) + '</strong></td>'
            + '<td class="text-right">RM ' + fmt2(t.avg_order_value) + '</td>'
            + '<td class="text-right text-warning">RM ' + fmt2(t.total_discount) + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="6" class="text-muted text-center">No transactions in this period.</td></tr>';
    if (types.length) {
        typesWrap.querySelector('table').insertAdjacentHTML('beforeend', mkTotal(types, [
            { label: 'Total' },
            { key: 'total_receipts', sum: true, fmt: 'int' },
            { derived: function(){ return '100.0%'; } },
            { key: 'total_revenue',  sum: true, fmt: 'rm' },
            { derived: function(rows){
                var rev = rows.reduce(function(a,r){ return a+parseFloat(r.total_revenue||0); },0);
                var cnt = rows.reduce(function(a,r){ return a+parseInt(r.total_receipts||0); },0);
                return 'RM ' + fmt2(cnt > 0 ? rev/cnt : 0);
            }},
            { key: 'total_discount', sum: true, fmt: 'rm' }
        ]));
    }
    scrollTable(typesWrap, types.length);
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-4"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

function getCSVData() {
    var types = (_lastData && _lastData.by_type) || [];
    return {
        filename: 'transaction-types-report.csv',
        cols: [
            { key: 'txn_type',        label: 'Channel / Type' },
            { key: 'total_receipts',  label: 'Transactions' },
            { key: 'total_revenue',   label: 'Revenue (RM)' },
            { key: 'avg_order_value', label: 'Avg Order Value (RM)' },
            { key: 'total_discount',  label: 'Total Discount (RM)' }
        ],
        rows: types
    };
}
</script>
<?php init_tail(); ?>
