<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.kpi-card { border-left: 4px solid #ddd; }
.kpi-card.green  { border-left-color: #5cb85c; }
.kpi-card.blue   { border-left-color: #337ab7; }
.kpi-card.orange { border-left-color: #f0ad4e; }
.kpi-card.red    { border-left-color: #d9534f; }
.kpi-value { font-size: 26px; font-weight: 700; margin: 4px 0; }
.kpi-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: .5px; }
.kpi-change { font-size: 12px; margin-top: 2px; }
.kpi-change .up   { color: #5cb85c; }
.kpi-change .down { color: #d9534f; }
.kpi-change .flat { color: #999; }
.period-btn.active { background: #337ab7; color: #fff; border-color: #337ab7; }
.chart-panel { min-height: 260px; }
.dashboard-loader { text-align:center; padding: 60px 0; color: #aaa; }
</style>
<div id="wrapper">
    <div class="content">

        <!-- Toolbar -->
        <div class="row mtop10 mbottom10">
            <div class="col-md-7">
                <div class="btn-group" id="period-btns">
                    <button class="btn btn-default btn-sm period-btn active" data-period="today"       onclick="onPeriodBtn(this)">Today</button>
                    <button class="btn btn-default btn-sm period-btn"        data-period="yesterday"   onclick="onPeriodBtn(this)">Yesterday</button>
                    <button class="btn btn-default btn-sm period-btn"        data-period="week"        onclick="onPeriodBtn(this)">Last 7 days</button>
                    <button class="btn btn-default btn-sm period-btn"        data-period="month"       onclick="onPeriodBtn(this)">This month</button>
                    <button class="btn btn-default btn-sm period-btn"        data-period="last_month"  onclick="onPeriodBtn(this)">Last month</button>
                </div>
                <span class="mleft10">
                    <input type="text" id="custom-from" class="form-control input-sm" style="width:110px;display:inline-block;" placeholder="From">
                    <input type="text" id="custom-to"   class="form-control input-sm" style="width:110px;display:inline-block;" placeholder="To">
                    <button class="btn btn-default btn-sm" onclick="applyCustom()">Go</button>
                </span>
            </div>
            <div class="col-md-3">
                <select id="warehouse-filter" class="form-control input-sm selectpicker" data-live-search="true" title="All Warehouses" onchange="onWarehouseChange()">
                    <?php foreach ($warehouses as $w) { ?>
                    <option value="<?php echo $w['warehouse_id']; ?>"><?php echo htmlspecialchars($w['warehouse_name']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2 text-right">
                <span id="date-range-label" class="text-muted small" style="line-height:30px;"></span>
            </div>
        </div>

        <!-- Loading state -->
        <div id="dashboard-loader" class="dashboard-loader">
            <i class="fa fa-spinner fa-spin fa-2x"></i><br><span class="mtop10 inline-block">Loading...</span>
        </div>

        <div id="dashboard-content" style="display:none;">

            <!-- KPI Cards -->
            <div class="row" id="kpi-row">
                <div class="col-md-3">
                    <div class="panel_s kpi-card green">
                        <div class="panel-body">
                            <div class="kpi-label">Net Sales</div>
                            <div class="kpi-value" id="kpi-net-sales">—</div>
                            <div class="kpi-change" id="kpi-net-sales-change"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="panel_s kpi-card blue">
                        <div class="panel-body">
                            <div class="kpi-label">Transactions</div>
                            <div class="kpi-value" id="kpi-transactions">—</div>
                            <div class="kpi-change" id="kpi-transactions-change"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="panel_s kpi-card orange">
                        <div class="panel-body">
                            <div class="kpi-label">Avg Transaction</div>
                            <div class="kpi-value" id="kpi-atv">—</div>
                            <div class="kpi-change" id="kpi-atv-change"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="panel_s kpi-card red">
                        <div class="panel-body">
                            <div class="kpi-label">Refund Rate</div>
                            <div class="kpi-value" id="kpi-refund-rate">—</div>
                            <div class="kpi-change" id="kpi-refund-rate-change"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts row -->
            <div class="row">
                <div class="col-md-8">
                    <div class="panel_s chart-panel">
                        <div class="panel-body">
                            <h5 class="no-margin-top bold">Sales Trend</h5>
                            <canvas id="chart-trend" height="90"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="panel_s chart-panel">
                        <div class="panel-body">
                            <h5 class="no-margin-top bold">Payment Breakdown</h5>
                            <canvas id="chart-payments" height="170"></canvas>
                            <div id="payment-legend" class="mtop10 small"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products + Hourly -->
            <div class="row">
                <div class="col-md-6">
                    <div class="panel_s">
                        <div class="panel-body">
                            <h5 class="no-margin-top bold">Top Products</h5>
                            <table class="table table-condensed no-margin" id="tbl-products">
                                <thead><tr><th>#</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Revenue</th></tr></thead>
                                <tbody id="top-products-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="panel_s chart-panel">
                        <div class="panel-body">
                            <h5 class="no-margin-top bold">Sales by Hour</h5>
                            <canvas id="chart-hourly" height="140"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Secondary KPIs -->
            <div class="row">
                <div class="col-md-3">
                    <div class="panel_s">
                        <div class="panel-body text-center">
                            <div class="kpi-label">Total Discounts</div>
                            <div class="kpi-value text-warning" id="kpi-discounts">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="panel_s">
                        <div class="panel-body text-center">
                            <div class="kpi-label">Total Tax Collected</div>
                            <div class="kpi-value text-info" id="kpi-tax">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="panel_s">
                        <div class="panel-body text-center">
                            <div class="kpi-label">Total Refunds</div>
                            <div class="kpi-value text-danger" id="kpi-refunds">—</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="panel_s">
                        <div class="panel-body text-center">
                            <div class="kpi-label">Cancelled Transactions</div>
                            <div class="kpi-value text-muted" id="kpi-cancelled">—</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Shifts -->
            <div class="row">
                <div class="col-md-12">
                    <div class="panel_s">
                        <div class="panel-body">
                            <h5 class="no-margin-top bold">Recent Shifts</h5>
                            <table class="table table-condensed" id="tbl-shifts">
                                <thead>
                                    <tr>
                                        <th>Opened</th>
                                        <th>Closed</th>
                                        <th>Warehouse</th>
                                        <th class="text-right">Opening Float</th>
                                        <th class="text-right">Expected</th>
                                        <th class="text-right">Actual</th>
                                        <th class="text-right">Over/Short</th>
                                        <th class="text-right">Transactions</th>
                                        <th class="text-right">Sales</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="shifts-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /dashboard-content -->
    </div>
</div>

<script>
var ADMIN_URL  = '<?php echo admin_url(); ?>';
var trendChart = null, payChart = null, hourlyChart = null;

// ── Period helpers ──────────────────────────────────────────────────────────
function getPeriodDates(period) {
    var today = new Date(), y = today.getFullYear(), m = today.getMonth(), d = today.getDate();
    var fmt = function(dt) {
        return dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate());
    };
    var sub = function(days) {
        var dt = new Date(y, m, d - days); return dt;
    };

    switch (period) {
        case 'today':
            return { from: fmt(today), to: fmt(today),
                     pFrom: fmt(sub(1)), pTo: fmt(sub(1)) };
        case 'yesterday':
            return { from: fmt(sub(1)), to: fmt(sub(1)),
                     pFrom: fmt(sub(2)), pTo: fmt(sub(2)) };
        case 'week':
            return { from: fmt(sub(6)), to: fmt(today),
                     pFrom: fmt(sub(13)), pTo: fmt(sub(7)) };
        case 'month':
            var mStart = new Date(y, m, 1), lmEnd = new Date(y, m, 0), lmStart = new Date(y, m-1, 1);
            return { from: fmt(mStart), to: fmt(today), pFrom: fmt(lmStart), pTo: fmt(lmEnd) };
        case 'last_month':
            var lmS = new Date(y, m-1, 1), lmE = new Date(y, m, 0), llmS = new Date(y, m-2, 1), llmE = new Date(y, m-1, 0);
            return { from: fmt(lmS), to: fmt(lmE), pFrom: fmt(llmS), pTo: fmt(llmE) };
    }
}

function pad(n) { return n < 10 ? '0'+n : n; }

// ── Fetch ───────────────────────────────────────────────────────────────────
function loadDashboard(from, to, pFrom, pTo) {
    $('#dashboard-loader').show();
    $('#dashboard-content').hide();
    $('#date-range-label').text(from === to ? from : from + ' – ' + to);

    $.post(ADMIN_URL + 'pos/ajax_dashboard_data', {
        date_from:    from,
        date_to:      to,
        prev_from:    pFrom,
        prev_to:      pTo,
        warehouse_id: $('#warehouse-filter').val() || ''
    })
    .done(function(resp) {
        try {
            if (typeof resp === 'string') resp = JSON.parse(resp);
        } catch(e) {}

        if (!resp || !resp.success) {
            var msg = (resp && resp.error) ? resp.error : 'Unknown error';
            $('#dashboard-loader').html('<i class="fa fa-exclamation-circle text-danger fa-2x"></i><br><span class="text-danger mtop10 inline-block">Error: ' + msg + '</span>');
            return;
        }
        renderKpis(resp.summary, resp.previous);
        renderTrend(resp.daily);
        renderHourly(resp.hourly);
        renderProducts(resp.products);
        renderPayments(resp.payments);
        renderShifts(resp.shifts);
        $('#dashboard-loader').hide();
        $('#dashboard-content').show();
    })
    .fail(function(xhr) {
        var msg = xhr.responseText ? xhr.responseText.substring(0, 300) : 'Request failed (' + xhr.status + ')';
        $('#dashboard-loader').html('<i class="fa fa-exclamation-circle text-danger fa-2x"></i><br><pre class="text-danger small mtop10" style="text-align:left;max-width:600px;margin:10px auto;">' + msg + '</pre>');
    });
}

// ── KPIs ────────────────────────────────────────────────────────────────────
function renderKpis(cur, prev) {
    setKpi('kpi-net-sales',   'RM ' + fmt2(cur.net_sales),   pctChange(cur.net_sales,      prev.net_sales));
    setKpi('kpi-transactions', cur.transaction_count,         pctChange(cur.transaction_count, prev.transaction_count));
    setKpi('kpi-atv',         'RM ' + fmt2(cur.avg_transaction), pctChange(cur.avg_transaction, prev.avg_transaction));
    setKpi('kpi-refund-rate', cur.refund_rate + '%',          pctChange(cur.refund_rate,    prev.refund_rate, true));

    $('#kpi-discounts').text('RM ' + fmt2(cur.total_discounts));
    $('#kpi-tax').text('RM ' + fmt2(cur.total_tax));
    $('#kpi-refunds').text('RM ' + fmt2(cur.total_refunds));
    $('#kpi-cancelled').text(cur.cancelled_count);
}

function setKpi(id, value, change) {
    $('#' + id).text(value);
    $('#' + id + '-change').html(change);
}

function pctChange(cur, prev, lowerIsBetter) {
    cur  = parseFloat(cur)  || 0;
    prev = parseFloat(prev) || 0;
    if (prev === 0) return '<span class="flat">— vs prev period</span>';
    var pct  = ((cur - prev) / prev * 100).toFixed(1);
    var up   = cur >= prev;
    var good = lowerIsBetter ? !up : up;
    var cls  = good ? 'up' : 'down';
    var arrow = up ? '▲' : '▼';
    return '<span class="' + cls + '">' + arrow + ' ' + Math.abs(pct) + '% vs prev period</span>';
}

function fmt2(n) {
    return parseFloat(n).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ── Trend Chart ─────────────────────────────────────────────────────────────
function renderTrend(rows) {
    var labels = rows.map(function(r) { return r.date; });
    var data   = rows.map(function(r) { return parseFloat(r.revenue); });
    var ctx    = document.getElementById('chart-trend').getContext('2d');
    if (trendChart) trendChart.destroy();
    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (RM)',
                data:  data,
                borderColor: '#337ab7',
                backgroundColor: 'rgba(51,122,183,0.08)',
                borderWidth: 2,
                pointRadius: labels.length > 14 ? 2 : 4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            legend: { display: false },
            scales: {
                xAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }],
                yAxes: [{ ticks: { fontSize: 11, callback: function(v) { return 'RM ' + v.toLocaleString(); } } }]
            },
            tooltips: {
                callbacks: {
                    label: function(ti, d) { return ' RM ' + parseFloat(d.datasets[ti.datasetIndex].data[ti.index]).toLocaleString('en-MY', {minimumFractionDigits:2}); }
                }
            }
        }
    });
}

// ── Hourly Chart ────────────────────────────────────────────────────────────
function renderHourly(rows) {
    var labels = [], data = [];
    for (var h = 0; h < 24; h++) {
        var found = rows.find(function(r) { return parseInt(r.hour) === h; });
        labels.push(h + ':00');
        data.push(found ? parseFloat(found.revenue) : 0);
    }
    var ctx = document.getElementById('chart-hourly').getContext('2d');
    if (hourlyChart) hourlyChart.destroy();
    hourlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue',
                data:  data,
                backgroundColor: 'rgba(92,184,92,0.7)',
                borderColor: '#5cb85c',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            legend: { display: false },
            scales: {
                xAxes: [{ ticks: { fontSize: 10 }, gridLines: { display: false } }],
                yAxes: [{ ticks: { fontSize: 10, callback: function(v) { return 'RM ' + v; } } }]
            }
        }
    });
}

// ── Payment Donut ────────────────────────────────────────────────────────────
var COLORS = ['#337ab7','#5cb85c','#f0ad4e','#d9534f','#9b59b6','#1abc9c','#e67e22','#34495e'];
function renderPayments(rows) {
    var labels = rows.map(function(r) { return r.payment_name; });
    var data   = rows.map(function(r) { return parseFloat(r.total); });
    var ctx    = document.getElementById('chart-payments').getContext('2d');
    if (payChart) payChart.destroy();

    if (!rows.length) {
        $('#payment-legend').html('<span class="text-muted">No data</span>');
        return;
    }

    payChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{ data: data, backgroundColor: COLORS.slice(0, data.length), borderWidth: 2 }]
        },
        options: {
            responsive: true,
            legend: { display: false },
            cutoutPercentage: 60,
            tooltips: {
                callbacks: {
                    label: function(ti, d) {
                        var v = d.datasets[0].data[ti.index];
                        return ' ' + d.labels[ti.index] + ': RM ' + parseFloat(v).toLocaleString('en-MY', {minimumFractionDigits:2});
                    }
                }
            }
        }
    });

    var total = data.reduce(function(a,b){return a+b;},0);
    var html  = '';
    rows.forEach(function(r, i) {
        var pct = total > 0 ? (parseFloat(r.total)/total*100).toFixed(1) : 0;
        html += '<span style="margin-right:10px;">'
              + '<span style="display:inline-block;width:10px;height:10px;background:'+COLORS[i]+';border-radius:2px;margin-right:4px;"></span>'
              + htmlEncode(r.payment_name) + ' <strong>' + pct + '%</strong>'
              + '</span>';
    });
    $('#payment-legend').html(html);
}

// ── Top Products ─────────────────────────────────────────────────────────────
function renderProducts(rows) {
    var tbody = $('#top-products-tbody').empty();
    if (!rows.length) {
        tbody.html('<tr><td colspan="4" class="text-muted text-center">No sales data</td></tr>');
        return;
    }
    rows.forEach(function(r, i) {
        tbody.append('<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td>' + htmlEncode(r.item_name) + '</td>'
            + '<td class="text-right">' + parseFloat(r.qty_sold).toFixed(0) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(r.revenue) + '</strong></td>'
            + '</tr>');
    });
}

// ── Shifts ───────────────────────────────────────────────────────────────────
function renderShifts(rows) {
    var tbody = $('#shifts-tbody').empty();
    if (!rows.length) {
        tbody.html('<tr><td colspan="10" class="text-muted text-center">No shifts found</td></tr>');
        return;
    }
    rows.forEach(function(r) {
        var diff     = parseFloat(r.difference);
        var diffCls  = diff > 0 ? 'text-success' : (diff < 0 ? 'text-danger' : 'text-muted');
        var diffStr  = (diff >= 0 ? '+' : '') + fmt2(diff);
        var statusBadge = r.status === 'open'
            ? '<span class="label label-success">Open</span>'
            : '<span class="label label-default">Closed</span>';
        tbody.append('<tr>'
            + '<td>' + (r.opened_at || '—') + '</td>'
            + '<td>' + (r.closed_at || '—') + '</td>'
            + '<td>' + htmlEncode(r.warehouse_name || '—') + '</td>'
            + '<td class="text-right">RM ' + fmt2(r.opening_float) + '</td>'
            + '<td class="text-right">RM ' + fmt2(r.expected_cash) + '</td>'
            + '<td class="text-right">RM ' + fmt2(r.actual_cash) + '</td>'
            + '<td class="text-right ' + diffCls + '">' + diffStr + '</td>'
            + '<td class="text-right">' + (r.transaction_count || 0) + '</td>'
            + '<td class="text-right">RM ' + fmt2(r.total_sales) + '</td>'
            + '<td>' + statusBadge + '</td>'
            + '</tr>');
    });
}

function htmlEncode(s) { return $('<span>').text(s).html(); }

// ── Controls ─────────────────────────────────────────────────────────────────
function applyCustom() {
    var from = $('#custom-from').val(), to = $('#custom-to').val();
    if (!from || !to) return;
    var days = Math.round((new Date(to) - new Date(from)) / 86400000) + 1;
    var pTo  = new Date(new Date(from) - 86400000);
    var pFrom = new Date(pTo - (days-1)*86400000);
    var fmt = function(dt) { return dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate()); };
    $('.period-btn').removeClass('active');
    loadDashboard(from, to, fmt(pFrom), fmt(pTo));
}

function onPeriodBtn(el) {
    var btns = document.querySelectorAll('.period-btn');
    for (var i = 0; i < btns.length; i++) { btns[i].classList.remove('active'); }
    el.classList.add('active');
    var d = getPeriodDates(el.getAttribute('data-period'));
    loadDashboard(d.from, d.to, d.pFrom, d.pTo);
}

function onWarehouseChange() {
    var active = document.querySelector('.period-btn.active');
    if (active) {
        var d = getPeriodDates(active.getAttribute('data-period'));
        loadDashboard(d.from, d.to, d.pFrom, d.pTo);
    }
}

// Fire after ALL scripts (including init_tail) have loaded
window.addEventListener('load', function() {
    if (typeof $.fn.datetimepicker !== 'undefined') {
        $('#custom-from, #custom-to').datetimepicker({ format: 'Y-m-d', timepicker: false, scrollMonth: false });
    }
    var d = getPeriodDates('today');
    loadDashboard(d.from, d.to, d.pFrom, d.pTo);
});
</script>
<?php init_tail(); ?>
