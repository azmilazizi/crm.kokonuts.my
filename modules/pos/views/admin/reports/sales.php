<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<style>
/* Comparison badges */
.kpi-vs       { margin-top: 4px; min-height: 18px; display: flex; align-items: center; gap: 6px; }
.kpi-badge    { display: inline-block; padding: 2px 8px; border-radius: 10px; font-weight: 600; font-size: 11px; line-height: 1.5; }
.kpi-badge.up      { background: #eafaf1; color: #27ae60; }
.kpi-badge.down    { background: #fdf2f2; color: #c0392b; }
.kpi-badge.flat    { background: #f5f5f5; color: #888; }
.kpi-vs-label { font-size: 11px; color: #bbb; white-space: nowrap; }
.kpi-prev-bar { font-size: 11px; color: #aaa; margin-bottom: 6px; padding: 4px 8px; background: #f9f9f9; border-radius: 4px; display: inline-block; }
</style>

<script>
var _trendChart = null, _dowChart = null;
var _lastData   = null;

// ── Delta badge ───────────────────────────────────────────────────────────────
function _delta(curr, prev) {
    var c = parseFloat(curr || 0), p = parseFloat(prev || 0);
    if (c === 0 && p === 0) return '<span class="kpi-badge flat">—</span>';
    if (p === 0) return c > 0 ? '<span class="kpi-badge up">▲ new</span>' : '';
    var pct = (c - p) / Math.abs(p) * 100;
    var cls = Math.abs(pct) < 0.05 ? 'flat' : (c >= p ? 'up' : 'down');
    var arrow = cls === 'flat' ? '=' : (c >= p ? '▲' : '▼');
    return '<span class="kpi-badge ' + cls + '">' + arrow + ' ' + Math.abs(pct).toFixed(1) + '%</span>';
}

// ── Format a date range label for the "vs" subtitle ───────────────────────────
function _prevLabel(from, to) {
    if (!from) return '';
    try {
        var opts = { month: 'short', day: 'numeric' };
        var f = new Date(from + 'T00:00:00'), t = new Date(to + 'T00:00:00');
        return from === to
            ? f.toLocaleDateString('en-MY', opts)
            : f.toLocaleDateString('en-MY', opts) + ' – ' + t.toLocaleDateString('en-MY', opts);
    } catch(e) { return from === to ? from : from + ' – ' + to; }
}

function renderReport(r) {
    _lastData = r;
    var s    = r.summary      || {};
    var ps   = r.prev_summary || {};
    var trend = normalizeTrend(r.trend || [], r.group_by);
    var dow   = r.dow         || [];
    var el    = document.getElementById('report-content');
    var gb    = r.group_by || 'daily';
    var gbLbl = GROUP_BY_LABEL[gb] || 'Daily';

    var showTrend = (r.date_from !== r.date_to) || gb === 'hourly' || gb === 'hourly_by_day';
    var prevLbl   = _prevLabel(r.prev_date_from, r.prev_date_to);

    // Compute avg order value (= net_sales / transactions)
    var avgOrder     = s.transaction_count  > 0 ? s.net_sales  / s.transaction_count  : 0;
    var prevAvgOrder = ps.transaction_count > 0 ? ps.net_sales / ps.transaction_count : 0;

    el.innerHTML = ''
        // ── 1. Trend chart
        + (showTrend
            ? '<div class="row"><div class="col-md-12"><div class="panel_s"><div class="panel-body">'
              + '<h4 class="no-margin-top bold">Sales Trend — <span class="text-muted" style="font-size:14px;font-weight:400;">' + gbLbl + '</span></h4>'
              + '<canvas id="chart-trend" height="60"></canvas>'
              + '</div></div></div></div>'
            : '')

        // ── 2. Prev-period label
        + (prevLbl
            ? '<div class="no-print" style="margin-bottom:6px;">'
              + '<span class="kpi-prev-bar"><i class="fa fa-exchange" style="margin-right:4px;"></i>vs ' + prevLbl + '</span>'
              + '</div>'
            : '')

        // ── 3. Primary KPIs (with comparison)
        + '<div class="row">'
        + kpiCard('green',  'Net Sales',       'RM ' + fmt2(s.net_sales),       _delta(s.net_sales,       ps.net_sales))
        + kpiCard('blue',   'Avg Order Value',  'RM ' + fmt2(avgOrder),          _delta(avgOrder,          prevAvgOrder))
        + kpiCard('orange', 'Transactions',     fmtInt(s.transaction_count),     _delta(s.transaction_count, ps.transaction_count))
        + kpiCard('purple', 'Items Sold',       fmtInt(s.items_sold),            _delta(s.items_sold,      ps.items_sold))
        + '</div>'

        // ── 4. Secondary KPIs (no comparison)
        + '<div class="row">'
        + kpiCard('',  'Gross Sales',     'RM ' + fmt2(s.gross_sales))
        + kpiCard('',  'Tax Collected',   'RM ' + fmt2(s.total_tax))
        + kpiCard('',  'Total Discounts', 'RM ' + fmt2(s.total_discounts))
        + kpiCard('red', 'Refunds',       'RM ' + fmt2(s.total_refunds) + '<small class="text-muted"> (' + fmtInt(s.refund_count) + ')</small>')
        + '</div>'

        // ── 5. DoW chart + breakdown table
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
    if (_trendChart) { _trendChart.destroy(); _trendChart = null; }
    if (showTrend) {
        var labels  = trend.map(function(d){ return d.label; });
        var netData = trend.map(function(d){ return parseFloat(d.net_sales||0); });
        var txnData = trend.map(function(d){ return parseInt(d.transaction_count||0); });
        var isBar   = (gb === 'hourly' || gb === 'hourly_by_day' || gb === 'dow');
        _trendChart = new Chart(document.getElementById('chart-trend').getContext('2d'), {
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
                animation: animOpts(labels.length), responsive: true,
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
    }

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

    // Trend breakdown table
    var trendWrap = document.getElementById('tbl-trend').parentNode;
    document.getElementById('trend-tbody').innerHTML = trend.length ? trend.map(function(d) {
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

function kpiCard(cls, label, value, deltaHtml) {
    return '<div class="col-md-3 col-xs-6"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + (deltaHtml ? '<div class="kpi-vs">' + deltaHtml + '</div>' : '')
        + '</div></div></div>';
}

function getCSVData() {
    var trend = normalizeTrend((_lastData && _lastData.trend) || [], (_lastData && _lastData.group_by) || 'daily');
    var active = document.querySelector('.period-btn.active');
    var from, to;
    if (active) { var d = getPeriodDates(active.getAttribute('data-period')); from = d.from; to = d.to; }
    else { from = $('#custom-from').val()||''; to = $('#custom-to').val()||''; }
    var gb = $('#group-by').val() || 'daily';
    return {
        filename: 'sales-report_' + (from||'') + '_' + (to||'') + '_' + gb + '.csv',
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
