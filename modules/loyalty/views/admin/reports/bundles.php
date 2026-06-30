<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('loyalty/admin/reports/_toolbar'); ?>

</div>
</div>

<style>
.feasibility-bar { height: 8px; border-radius: 4px; background: #eee; overflow: hidden; min-width: 80px; }
.feasibility-fill { height: 100%; border-radius: 4px; transition: width .4s; }
.fill-green  { background: #5cb85c; }
.fill-yellow { background: #f0ad4e; }
.fill-red    { background: #d9534f; }
.badge-ok     { background: #5cb85c; }
.badge-watch  { background: #f0ad4e; }
.badge-risk   { background: #d9534f; }
.section-heading { font-size: 15px; font-weight: 700; margin: 0 0 12px; border-bottom: 2px solid #f0ad4e; padding-bottom: 6px; }
</style>

<script>
var _feasChart = null, _bundleChart = null;

function renderReport(r) {
    var el      = document.getElementById('report-content');
    var promos  = r.crm_promos   || [];
    var bundles = r.pos_bundles  || [];

    // ---- KPIs ----
    var totalSavings  = promos.reduce(function(a, b) { return a + parseFloat(b.total_savings || 0); }, 0);
    var totalUnits    = promos.reduce(function(a, b) { return a + parseFloat(b.units_sold || 0); }, 0);
    var activePromos  = promos.length;
    var avgSavingsPct = promos.length
        ? (promos.reduce(function(a,b){ return a + parseFloat(b.savings_pct||0); }, 0) / promos.length).toFixed(1)
        : 0;
    var highRisk      = promos.filter(function(p){ return parseFloat(p.savings_pct) >= 35; }).length;
    var bundleSavings = bundles.reduce(function(a,b){ return a + parseFloat(b.savings||0); }, 0);

    el.innerHTML = ''

        // KPI row
        + '<div class="row">'
        + kpiCard('orange', 'Active CRM Promos/Bundles', activePromos)
        + kpiCard('blue',   'Avg Customer Saving %',     avgSavingsPct + '%')
        + kpiCard('red',    'Total Savings Given (Period)', 'RM ' + fmt2(totalSavings))
        + kpiCard(highRisk > 0 ? 'red' : 'green', 'High-Risk Promos (≥35% off)', highRisk)
        + '</div>'

        // Chart row
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">CRM Promo/Bundle — Selling Price vs Ala-Carte Value</h5>'
        + '<canvas id="chart-feasibility" height="90"></canvas>'
        + '<p id="chart-empty" class="text-muted text-center small" style="display:none;">No CRM promos/bundles defined yet.</p>'
        + '</div></div></div>'
        + '</div>'

        // CRM Promos table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="section-heading">CRM Promos &amp; Bundles — Feasibility</h5>'
        + '<p class="text-muted small" style="margin-top:-6px;margin-bottom:10px;">Ala-carte value is computed from the configured components/bundle groups (average option price per group for bundles). Savings % is the discount a customer receives vs buying items separately.</p>'
        + '<div style="overflow-x:auto;">'
        + '<table class="table table-condensed table-bordered no-margin table-hover">'
        + '<thead><tr>'
        + '<th>#</th><th>Name</th><th>Type</th>'
        + '<th class="text-right">Selling Price</th>'
        + '<th class="text-right">Ala-Carte Value</th>'
        + '<th class="text-right">Saving/Use</th>'
        + '<th class="text-right" style="min-width:120px;">Saving %</th>'
        + '<th class="text-right">Units Sold</th>'
        + '<th class="text-right">Revenue</th>'
        + '<th class="text-right">Total Savings Given</th>'
        + '<th class="text-right">Gross Margin</th>'
        + '<th>Status</th>'
        + '</tr></thead>'
        + '<tbody id="promos-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        // POS Bundles table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="section-heading">POS Bundles — Price vs Ala-Carte</h5>'
        + '<p class="text-muted small" style="margin-top:-6px;margin-bottom:10px;">Static analysis: ala-carte sum is computed from component item prices (rate × quantity). Transaction tracking for POS bundles is not linked by bundle ID.</p>'
        + '<div style="overflow-x:auto;">'
        + '<table class="table table-condensed table-bordered no-margin table-hover">'
        + '<thead><tr>'
        + '<th>#</th><th>Bundle Name</th>'
        + '<th class="text-right">Bundle Price</th>'
        + '<th class="text-right">Ala-Carte Sum</th>'
        + '<th class="text-right">Customer Saving</th>'
        + '<th class="text-right" style="min-width:120px;">Saving %</th>'
        + '<th class="text-right">Ala-Carte Markup</th>'
        + '<th>Items</th><th>Status</th>'
        + '</tr></thead>'
        + '<tbody id="bundles-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // ---- Grouped bar chart: selling price vs ala-carte ----
    if (_feasChart) _feasChart.destroy();
    if (promos.length) {
        document.getElementById('chart-empty').style.display = 'none';
        _feasChart = new Chart(document.getElementById('chart-feasibility').getContext('2d'), {
            type: 'bar',
            data: {
                labels: promos.map(function(p){ return p.promo_name; }),
                datasets: [
                    { label: 'Selling Price (RM)',    data: promos.map(function(p){ return parseFloat(p.selling_price||0); }),  backgroundColor: 'rgba(51,122,183,0.75)',  borderColor: '#337ab7', borderWidth: 1 },
                    { label: 'Ala-Carte Value (RM)',  data: promos.map(function(p){ return parseFloat(p.alacarte_value||0); }), backgroundColor: 'rgba(240,173,78,0.55)',  borderColor: '#f0ad4e', borderWidth: 1 }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    xAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }],
                    yAxes: [{ ticks: { callback: function(v){ return 'RM ' + v.toLocaleString(); } } }]
                },
                tooltips: { callbacks: { label: function(ti, d) { return ' ' + d.datasets[ti.datasetIndex].label + ': RM ' + fmt2(ti.yLabel); } } }
            }
        });
    } else {
        document.getElementById('chart-empty').style.display = '';
        if (document.getElementById('chart-feasibility')) document.getElementById('chart-feasibility').style.display = 'none';
    }

    // ---- CRM promos tbody ----
    document.getElementById('promos-tbody').innerHTML = promos.length ? promos.map(function(p, i) {
        var sp   = parseFloat(p.selling_price  || 0);
        var av   = parseFloat(p.alacarte_value || 0);
        var pct  = parseFloat(p.savings_pct   || 0);
        var cls  = pct >= 35 ? 'risk' : (pct >= 20 ? 'watch' : 'ok');
        var fcls = pct >= 35 ? 'fill-red' : (pct >= 20 ? 'fill-yellow' : 'fill-green');
        var bClass = pct >= 35 ? 'badge-risk' : (pct >= 20 ? 'badge-watch' : 'badge-ok');
        var label  = pct >= 35 ? 'High Risk' : (pct >= 20 ? 'Watch' : 'OK');
        var barW   = Math.min(100, pct).toFixed(0);
        var mgPct  = parseFloat(p.gross_margin_pct || 0);
        var mgCls  = mgPct < 0 ? 'text-danger' : (mgPct < 20 ? 'text-warning' : 'text-success');
        var typeLabel = p.promo_type === 'bundle' ? '<span class="label label-info">Bundle</span>' : '<span class="label label-primary">Promo</span>';
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(p.promo_name) + '</strong><br><small class="text-muted">' + htmlEnc(p.item_name) + '</small></td>'
            + '<td>' + typeLabel + '</td>'
            + '<td class="text-right">RM ' + fmt2(sp) + '</td>'
            + '<td class="text-right">' + (av > 0 ? 'RM ' + fmt2(av) : '<span class="text-muted">—</span>') + '</td>'
            + '<td class="text-right text-success"><strong>' + (av > 0 ? 'RM ' + fmt2(p.savings_per_use) : '—') + '</strong></td>'
            + '<td class="text-right">'
            +   (av > 0
                    ? '<div class="feasibility-bar"><div class="feasibility-fill ' + fcls + '" style="width:' + barW + '%"></div></div>'
                    +   '<small>' + pct + '%</small>'
                    : '<small class="text-muted">No components</small>')
            + '</td>'
            + '<td class="text-right">' + fmtInt(p.units_sold) + '</td>'
            + '<td class="text-right">RM ' + fmt2(p.total_revenue) + '</td>'
            + '<td class="text-right text-warning"><strong>RM ' + fmt2(p.total_savings) + '</strong></td>'
            + '<td class="text-right ' + mgCls + '">' + (parseFloat(p.total_revenue) > 0 ? mgPct + '%' : '—') + '</td>'
            + '<td><span class="label ' + bClass + '">' + label + '</span></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="12" class="text-muted text-center">No active CRM promos or bundles defined.</td></tr>';

    // ---- POS bundles tbody ----
    document.getElementById('bundles-tbody').innerHTML = bundles.length ? bundles.map(function(b, i) {
        var bp   = parseFloat(b.bundle_price  || 0);
        var av   = parseFloat(b.alacarte_value || 0);
        var pct  = parseFloat(b.savings_pct   || 0);
        var fcls = pct >= 35 ? 'fill-red' : (pct >= 20 ? 'fill-yellow' : 'fill-green');
        var bClass = pct >= 35 ? 'badge-risk' : (pct >= 20 ? 'badge-watch' : 'badge-ok');
        var label  = pct >= 35 ? 'High Risk' : (pct >= 20 ? 'Watch' : 'OK');
        var barW   = Math.min(100, pct).toFixed(0);
        var mkPct  = parseFloat(b.markup_pct || 0);
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td><strong>' + htmlEnc(b.bundle_name) + '</strong></td>'
            + '<td class="text-right">RM ' + fmt2(bp) + '</td>'
            + '<td class="text-right">' + (av > 0 ? 'RM ' + fmt2(av) : '<span class="text-muted">—</span>') + '</td>'
            + '<td class="text-right text-success">' + (av > 0 ? '<strong>RM ' + fmt2(b.savings) + '</strong>' : '—') + '</td>'
            + '<td class="text-right">'
            +   (av > 0
                    ? '<div class="feasibility-bar"><div class="feasibility-fill ' + fcls + '" style="width:' + barW + '%"></div></div><small>' + pct + '%</small>'
                    : '<small class="text-muted">No items</small>')
            + '</td>'
            + '<td class="text-right">' + (av > 0 && bp > 0 ? '+' + mkPct + '%' : '—') + '</td>'
            + '<td><small class="text-muted">' + b.component_count + ' item(s)</small></td>'
            + '<td><span class="label ' + bClass + '">' + label + '</span></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="9" class="text-muted text-center">No active POS bundles found.</td></tr>';
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
