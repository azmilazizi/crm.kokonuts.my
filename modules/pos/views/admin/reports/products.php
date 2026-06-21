<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _catChart = null, _revenueChart = null;

function renderReport(r) {
    var el = document.getElementById('report-content');

    // KPIs from by_category totals
    var cats  = r.by_category || [];
    var totalRev = cats.reduce(function(a,b){ return a + parseFloat(b.net_revenue||0); }, 0);
    var totalQty = cats.reduce(function(a,b){ return a + parseInt(b.qty_sold||0); }, 0);

    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('green',  'Total Revenue',    'RM ' + fmt2(totalRev))
        + kpiCard('blue',   'Items Sold',        fmtInt(totalQty))
        + kpiCard('orange', 'Categories',        cats.length)
        + kpiCard('purple', 'Unique Products',   (r.top_by_revenue||[]).length)
        + '</div>'

        // Top products + Category donut
        + '<div class="row">'
        + '<div class="col-md-7"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Top Products by Revenue</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed no-margin">'
        + '<thead><tr><th>#</th><th>Product</th><th>Category</th><th class="text-right">Qty</th><th class="text-right">Gross</th><th class="text-right">Discounts</th><th class="text-right">Net Revenue</th></tr></thead>'
        + '<tbody id="top-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '<div class="col-md-5"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Revenue by Category</h5>'
        + '<canvas id="chart-categories" height="200"></canvas>'
        + '<div id="cat-legend" class="mtop10 small"></div>'
        + '</div></div></div>'
        + '</div>'

        // Revenue bar chart + Bottom sellers
        + '<div class="row">'
        + '<div class="col-md-7"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Top 10 Products — Revenue Bar</h5>'
        + '<canvas id="chart-top-revenue" height="160"></canvas>'
        + '</div></div></div>'
        + '<div class="col-md-5"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Bottom Sellers</h5>'
        + '<table class="table table-condensed no-margin">'
        + '<thead><tr><th>Product</th><th>Category</th><th class="text-right">Qty</th><th class="text-right">Net Rev.</th></tr></thead>'
        + '<tbody id="bottom-tbody"></tbody>'
        + '</table>'
        + '</div></div></div>'
        + '</div>'

        // Category breakdown table
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Category Breakdown</h5>'
        + '<div style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>Category</th><th class="text-right">Products</th><th class="text-right">Receipts</th><th class="text-right">Qty Sold</th><th class="text-right">Discounts</th><th class="text-right">Net Revenue</th><th class="text-right">% Share</th></tr></thead>'
        + '<tbody id="cat-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>';

    // Top products table
    var top = r.top_by_revenue || [];
    var topTbody = document.getElementById('top-tbody');
    topTbody.innerHTML = top.length ? top.map(function(p, i) {
        return '<tr>'
            + '<td class="text-muted">' + (i+1) + '</td>'
            + '<td>' + htmlEnc(p.item_name) + '</td>'
            + '<td><small class="text-muted">' + htmlEnc(p.category_name) + '</small></td>'
            + '<td class="text-right">' + fmtInt(p.qty_sold) + '</td>'
            + '<td class="text-right text-muted">RM ' + fmt2(p.gross_revenue) + '</td>'
            + '<td class="text-right text-warning">RM ' + fmt2(p.total_discounts) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(p.net_revenue) + '</strong></td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="7" class="text-muted text-center">No sales data</td></tr>';

    // Category donut
    if (_catChart) _catChart.destroy();
    if (cats.length) {
        var catLabels = cats.map(function(c){ return c.category_name; });
        var catData   = cats.map(function(c){ return parseFloat(c.net_revenue||0); });
        _catChart = new Chart(document.getElementById('chart-categories').getContext('2d'), {
            type: 'doughnut',
            data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: CHART_COLORS.slice(0, catData.length), borderWidth: 2 }]},
            options: { responsive: true, legend: { display: false }, cutoutPercentage: 55,
                tooltips: { callbacks: { label: function(ti,d){ return ' '+d.labels[ti.index]+': RM '+fmt2(d.datasets[0].data[ti.index]); } } }
            }
        });
        var catTotal = catData.reduce(function(a,b){return a+b;},0);
        document.getElementById('cat-legend').innerHTML = catLabels.map(function(l,i){
            var pct = catTotal > 0 ? (catData[i]/catTotal*100).toFixed(1) : 0;
            return '<span style="margin-right:10px;"><span style="display:inline-block;width:10px;height:10px;background:'+CHART_COLORS[i]+';border-radius:2px;margin-right:4px;"></span>'
                + htmlEnc(l) + ' <strong>' + pct + '%</strong></span>';
        }).join('');
    }

    // Revenue bar chart (top 10)
    if (_revenueChart) _revenueChart.destroy();
    var top10 = top.slice(0, 10);
    if (top10.length) {
        _revenueChart = new Chart(document.getElementById('chart-top-revenue').getContext('2d'), {
            type: 'horizontalBar',
            data: { labels: top10.map(function(p){ return p.item_name; }),
                datasets: [{ label: 'Net Revenue (RM)', data: top10.map(function(p){ return parseFloat(p.net_revenue||0); }),
                    backgroundColor: 'rgba(51,122,183,0.75)', borderColor: '#337ab7', borderWidth: 1 }]},
            options: { responsive: true, legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }],
                    yAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }]
                }
            }
        });
    }

    // Bottom sellers table
    var bot = r.bottom || [];
    document.getElementById('bottom-tbody').innerHTML = bot.length ? bot.map(function(p) {
        return '<tr>'
            + '<td>' + htmlEnc(p.item_name) + '</td>'
            + '<td><small class="text-muted">' + htmlEnc(p.category_name) + '</small></td>'
            + '<td class="text-right">' + fmtInt(p.qty_sold) + '</td>'
            + '<td class="text-right text-danger">RM ' + fmt2(p.net_revenue) + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="4" class="text-muted text-center">No data</td></tr>';

    // Category breakdown table
    var catRows = r.by_category || [];
    var catRevTotal = catRows.reduce(function(a,b){return a+parseFloat(b.net_revenue||0);},0);
    document.getElementById('cat-tbody').innerHTML = catRows.length ? catRows.map(function(c) {
        var pct = catRevTotal > 0 ? (parseFloat(c.net_revenue||0)/catRevTotal*100).toFixed(1) : '0.0';
        return '<tr>'
            + '<td><strong>' + htmlEnc(c.category_name) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(c.item_count) + '</td>'
            + '<td class="text-right">' + fmtInt(c.receipt_count) + '</td>'
            + '<td class="text-right">' + fmtInt(c.qty_sold) + '</td>'
            + '<td class="text-right text-warning">RM ' + fmt2(c.total_discounts) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(c.net_revenue) + '</strong></td>'
            + '<td class="text-right">' + pct + '%</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="7" class="text-muted text-center">No category data</td></tr>';
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
