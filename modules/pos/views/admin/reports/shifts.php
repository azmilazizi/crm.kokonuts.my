<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _staffChart = null, _varianceChart = null;

function renderReport(r) {
    var el     = document.getElementById('report-content');
    var shifts = r.shifts || [];
    var staff  = r.staff  || [];
    var cms    = r.cash_movements || [];

    var totalSales = staff.reduce(function(a,b){ return a+parseFloat(b.total_sales||0); },0);
    var totalTxns  = staff.reduce(function(a,b){ return a+parseInt(b.total_transactions||0); },0);
    var totalDiff  = shifts.reduce(function(a,b){ return a+parseFloat(b.difference||0); },0);
    var payIn      = (cms.find(function(c){ return c.type==='pay_in'; }) || {}).total_amount || 0;
    var payOut     = (cms.find(function(c){ return c.type==='pay_out'; }) || {}).total_amount || 0;

    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('green',  'Total Sales',      'RM ' + fmt2(totalSales))
        + kpiCard('blue',   'Transactions',      fmtInt(totalTxns))
        + kpiCard('orange', 'Total Shifts',      shifts.length)
        + kpiCard(totalDiff >= 0 ? '' : 'red', 'Net Cash Variance', (totalDiff >= 0 ? '+' : '') + 'RM ' + fmt2(totalDiff))
        + '</div>'

        // Staff performance bar + cash movements
        + '<div class="row">'
        + '<div class="col-md-8"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Sales by Staff Member</h5>'
        + '<canvas id="chart-staff" height="130"></canvas>'
        + '</div></div></div>'
        + '<div class="col-md-4"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Cash Movements</h5>'
        + '<table class="table table-condensed no-margin">'
        + '<thead><tr><th>Type</th><th class="text-right">Count</th><th class="text-right">Total</th></tr></thead>'
        + '<tbody id="cm-tbody"></tbody>'
        + '</table>'
        + '<hr style="margin:10px 0;">'
        + '<div class="row text-center" style="font-size:13px;">'
        + '<div class="col-xs-6"><div class="text-muted" style="font-size:11px;">PAY IN</div><strong class="text-success">RM ' + fmt2(payIn) + '</strong></div>'
        + '<div class="col-xs-6"><div class="text-muted" style="font-size:11px;">PAY OUT</div><strong class="text-danger">RM ' + fmt2(payOut) + '</strong></div>'
        + '</div>'
        + '</div></div></div>'
        + '</div>'

        // Staff performance table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Staff Performance</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>Staff</th><th class="text-right">Shifts</th><th class="text-right">Transactions</th><th class="text-right">Total Sales</th><th class="text-right">Avg / Shift</th><th class="text-right">Discounts</th><th class="text-right">Refunds</th></tr></thead>'
        + '<tbody id="staff-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // All shifts table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">All Shifts in Period</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin" style="font-size:12px;">'
        + '<thead><tr><th>Code</th><th>Opened By</th><th>Closed By</th><th>Warehouse</th><th>Opened</th><th>Closed</th><th class="text-right">Opening Float</th><th class="text-right">Expected</th><th class="text-right">Actual</th><th class="text-right">Variance</th><th class="text-right">Sales</th><th class="text-right">Txns</th><th>Status</th></tr></thead>'
        + '<tbody id="shifts-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // Staff bar chart
    if (_staffChart) _staffChart.destroy();
    if (staff.length) {
        _staffChart = new Chart(document.getElementById('chart-staff').getContext('2d'), {
            type: 'horizontalBar',
            data: {
                labels: staff.map(function(s){ return s.employee_name; }),
                datasets: [{ label: 'Total Sales (RM)', data: staff.map(function(s){ return parseFloat(s.total_sales||0); }),
                    backgroundColor: CHART_COLORS.slice(0, staff.length).map(function(c){ return c+'bb'; }),
                    borderColor: CHART_COLORS.slice(0, staff.length), borderWidth: 1 }]
            },
            options: { responsive: true, legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }],
                    yAxes: [{ ticks: { fontSize: 12 }, gridLines: { display: false } }]
                },
                tooltips: { callbacks: { label: function(ti,d){ return ' RM '+fmt2(d.datasets[0].data[ti.index]); } } }
            }
        });
    }

    // Cash movements table
    document.getElementById('cm-tbody').innerHTML = cms.length ? cms.map(function(c) {
        var cls = c.type === 'pay_in' ? 'text-success' : 'text-danger';
        return '<tr><td><span class="' + cls + '">' + htmlEnc(c.type) + '</span></td>'
            + '<td class="text-right">' + fmtInt(c.movement_count) + '</td>'
            + '<td class="text-right ' + cls + '"><strong>RM ' + fmt2(c.total_amount) + '</strong></td></tr>';
    }).join('') : '<tr><td colspan="3" class="text-muted text-center">No movements</td></tr>';

    // Staff performance table
    document.getElementById('staff-tbody').innerHTML = staff.length ? staff.map(function(s, i) {
        return '<tr>'
            + '<td><span style="display:inline-block;width:10px;height:10px;background:'+CHART_COLORS[i % CHART_COLORS.length]+';border-radius:2px;margin-right:6px;"></span>'
            + '<strong>' + htmlEnc(s.employee_name) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(s.shift_count) + '</td>'
            + '<td class="text-right">' + fmtInt(s.total_transactions) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(s.total_sales) + '</strong></td>'
            + '<td class="text-right">RM ' + fmt2(s.avg_sales_per_shift) + '</td>'
            + '<td class="text-right text-warning">RM ' + fmt2(s.total_discounts) + '</td>'
            + '<td class="text-right text-danger">RM ' + fmt2(s.total_refunds) + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="7" class="text-muted text-center">No closed shifts found</td></tr>';

    // All shifts table
    document.getElementById('shifts-tbody').innerHTML = shifts.length ? shifts.map(function(s) {
        var diff    = parseFloat(s.difference||0);
        var diffCls = diff > 0 ? 'text-success' : (diff < 0 ? 'text-danger' : 'text-muted');
        var badge   = s.status === 'open'
            ? '<span class="label label-success">Open</span>'
            : '<span class="label label-default">Closed</span>';
        return '<tr>'
            + '<td><code>' + htmlEnc(s.shift_code) + '</code></td>'
            + '<td>' + htmlEnc(s.employee_name||'—') + '</td>'
            + '<td>' + htmlEnc(s.closed_by_name||'—') + '</td>'
            + '<td>' + htmlEnc(s.warehouse_name||'—') + '</td>'
            + '<td>' + (s.opened_at||'—') + '</td>'
            + '<td>' + (s.closed_at||'—') + '</td>'
            + '<td class="text-right">RM ' + fmt2(s.opening_float) + '</td>'
            + '<td class="text-right">RM ' + fmt2(s.expected_cash) + '</td>'
            + '<td class="text-right">RM ' + fmt2(s.actual_cash) + '</td>'
            + '<td class="text-right ' + diffCls + '">' + (diff >= 0 ? '+' : '') + 'RM ' + fmt2(diff) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(s.total_sales) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(s.transaction_count) + '</td>'
            + '<td>' + badge + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="13" class="text-muted text-center">No shifts for this period</td></tr>';
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
