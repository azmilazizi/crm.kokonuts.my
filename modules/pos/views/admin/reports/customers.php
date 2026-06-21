<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _newChart = null, _loyaltyChart = null;

function renderReport(r) {
    var el  = document.getElementById('report-content');
    var s   = r.summary || {};
    var top = r.top     || [];

    el.innerHTML = ''
        // KPIs
        + '<div class="row">'
        + kpiCard('green',  'New Members',         fmtInt(s.new_members))
        + kpiCard('blue',   'Active Loyalty Customers', fmtInt(s.loyalty_customers_with_sales))
        + kpiCard('orange', 'Points Earned',        fmtInt(s.total_earned))
        + kpiCard('purple', 'Points Redeemed',      fmtInt(s.total_redeemed))
        + '</div>'
        + '<div class="row">'
        + kpiCard('',      'Earning Customers',    fmtInt(s.earning_customers))
        + kpiCard('',      'Redeeming Customers',  fmtInt(s.redeeming_customers))
        + '</div>'

        // New members daily trend + Loyalty activity
        + '<div class="row">'
        + '<div class="col-md-6"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">New Loyalty Members per Day</h5>'
        + '<canvas id="chart-new" height="160"></canvas>'
        + '<p id="new-empty" class="text-muted text-center small" style="display:none;">No new members registered in this period.</p>'
        + '</div></div></div>'
        + '<div class="col-md-6"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Loyalty Activity — Earn vs Redeem</h5>'
        + '<canvas id="chart-loyalty" height="160"></canvas>'
        + '<p id="loyalty-empty" class="text-muted text-center small" style="display:none;">No loyalty transactions in this period.</p>'
        + '</div></div></div>'
        + '</div>'

        // Top customers table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Top Customers by Spend <small class="text-muted">(loyalty members only)</small></h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th class="text-right">Visits</th><th class="text-right">Total Spent</th><th class="text-right">Points Earned</th><th class="text-right">Points Redeemed</th></tr></thead>'
        + '<tbody id="customers-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // New members chart
    if (_newChart) _newChart.destroy();
    var newDaily = r.new_daily || [];
    if (newDaily.length) {
        $('#new-empty').hide();
        _newChart = new Chart(document.getElementById('chart-new').getContext('2d'), {
            type: 'bar',
            data: {
                labels: newDaily.map(function(d){ return d.date; }),
                datasets: [{ label: 'New Members', data: newDaily.map(function(d){ return parseInt(d.new_members||0); }),
                    backgroundColor: 'rgba(92,184,92,0.7)', borderColor: '#5cb85c', borderWidth: 1 }]
            },
            options: { responsive: true, legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { fontSize: 10 }, gridLines: { display: false } }],
                    yAxes: [{ ticks: { stepSize: 1 } }]
                }
            }
        });
    } else {
        document.getElementById('chart-new').style.display = 'none';
        document.getElementById('new-empty').style.display = '';
    }

    // Loyalty earn vs redeem chart
    if (_loyaltyChart) _loyaltyChart.destroy();
    var loyalty = r.loyalty || [];
    if (loyalty.length) {
        $('#loyalty-empty').hide();
        _loyaltyChart = new Chart(document.getElementById('chart-loyalty').getContext('2d'), {
            type: 'line',
            data: {
                labels: loyalty.map(function(d){ return d.date; }),
                datasets: [
                    { label: 'Points Earned', data: loyalty.map(function(d){ return parseInt(d.earn_points||0); }),
                        borderColor: '#5cb85c', backgroundColor: 'rgba(92,184,92,0.1)', borderWidth: 2, fill: true },
                    { label: 'Points Redeemed', data: loyalty.map(function(d){ return parseInt(d.redeem_points||0); }),
                        borderColor: '#d9534f', backgroundColor: 'rgba(217,83,79,0.08)', borderWidth: 2, fill: true }
                ]
            },
            options: { responsive: true,
                scales: {
                    xAxes: [{ ticks: { fontSize: 10 }, gridLines: { display: false } }],
                    yAxes: [{ ticks: { stepSize: 10 } }]
                }
            }
        });
    } else {
        document.getElementById('chart-loyalty').style.display = 'none';
        document.getElementById('loyalty-empty').style.display = '';
    }

    // Top customers table
    document.getElementById('customers-tbody').innerHTML = top.length ? top.map(function(c, i) {
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(c.customer_name) + '</strong></td>'
            + '<td>' + htmlEnc(c.phone||'—') + '</td>'
            + '<td>' + htmlEnc(c.email||'—') + '</td>'
            + '<td class="text-right">' + fmtInt(c.visit_count) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(c.total_spent) + '</strong></td>'
            + '<td class="text-right text-success">' + fmtInt(c.points_earned) + '</td>'
            + '<td class="text-right text-warning">' + fmtInt(c.points_redeemed) + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="8" class="text-muted text-center">No loyalty customer sales in this period</td></tr>';
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
