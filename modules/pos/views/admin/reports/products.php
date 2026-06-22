<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _trendChart = null, _catChart = null, _revenueChart = null;
var _lastData    = null;
var _viewMode    = 'product'; // 'product' | 'category'

// ── View toggle ───────────────────────────────────────────────────────────────
function setViewMode(mode) {
    _viewMode = mode;
    document.querySelectorAll('.view-toggle-btn').forEach(function(b) {
        b.classList.toggle('active', b.getAttribute('data-mode') === mode);
    });
    document.getElementById('section-product').style.display  = mode === 'product'  ? '' : 'none';
    document.getElementById('section-category').style.display = mode === 'category' ? '' : 'none';
    if (_lastData) _renderTrend(_lastData);
}

// ── Primary trend (redrawn on view mode change) ───────────────────────────────
function _renderTrend(r) {
    if (_trendChart) { _trendChart.destroy(); _trendChart = null; }
    var gb    = r.group_by || 'daily';
    var gbLbl = GROUP_BY_LABEL[gb] || 'Daily';
    var title = document.getElementById('trend-title');
    var canvas = document.getElementById('chart-trend');
    if (title) title.innerHTML = (_viewMode === 'category' ? 'Revenue by Category' : 'Revenue by Product (Top 10)') +
        ' — <span class="text-muted" style="font-size:14px;font-weight:400;">' + gbLbl + '</span>';

    var isBar = (gb === 'hourly' || gb === 'hourly_by_day' || gb === 'dow');

    // Shared tooltip config: filter zeros using raw dataset value (not cumulative item.value)
    var stackedTooltip = {
        mode: 'index', intersect: false,
        filter: function(item, data) {
            return parseFloat(data.datasets[item.datasetIndex].data[item.index] || 0) > 0;
        },
        callbacks: { label: function(item, data) {
            var v = parseFloat(data.datasets[item.datasetIndex].data[item.index] || 0);
            return ' ' + data.datasets[item.datasetIndex].label + ': RM ' + fmt2(v);
        }}
    };

    if (_viewMode === 'category') {
        // Stacked area/bar by category
        var catTrend = r.category_trend || [];
        if (!catTrend.length) return;
        var ms = buildMultiSeries(catTrend, 'category_name', 'net_revenue');
        var datasets = ms.datasets.map(function(ds, idx) {
            var col = CHART_COLORS[idx % CHART_COLORS.length];
            return {
                label: ds.name, data: ds.data,
                backgroundColor: col,
                borderColor:     col,
                borderWidth: isBar ? 1 : 2,
                fill: !isBar,
                pointRadius: !isBar && ms.labels.length > 14 ? 0 : 3
            };
        });
        _trendChart = new Chart(canvas.getContext('2d'), {
            type: isBar ? 'bar' : 'line',
            data: { labels: ms.labels, datasets: datasets },
            options: {
                animation: animOpts(ms.labels.length),
                responsive: true,
                scales: {
                    xAxes: [{ stacked: true, ticks: { fontSize: 11, maxRotation: 60 }, gridLines: { display: false } }],
                    yAxes: [{ stacked: !isBar, ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
                },
                tooltips: stackedTooltip
            }
        });
    } else {
        // Stacked area/bar by top 10 products
        var prodTrend = r.product_trend || [];
        if (!prodTrend.length) return;
        var ms = buildMultiSeries(prodTrend, 'item_name', 'net_revenue');
        var datasets = ms.datasets.map(function(ds, idx) {
            var col = CHART_COLORS[idx % CHART_COLORS.length];
            return {
                label: ds.name, data: ds.data,
                backgroundColor: col,
                borderColor:     col,
                borderWidth: isBar ? 1 : 2,
                fill: !isBar,
                pointRadius: !isBar && ms.labels.length > 14 ? 0 : 3
            };
        });
        _trendChart = new Chart(canvas.getContext('2d'), {
            type: isBar ? 'bar' : 'line',
            data: { labels: ms.labels, datasets: datasets },
            options: {
                animation: animOpts(ms.labels.length),
                responsive: true,
                scales: {
                    xAxes: [{ stacked: true, ticks: { fontSize: 11, maxRotation: 60 }, gridLines: { display: false } }],
                    yAxes: [{ stacked: !isBar, ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
                },
                tooltips: stackedTooltip
            }
        });
    }
}

// ── Main render ───────────────────────────────────────────────────────────────
function renderReport(r) {
    _lastData = r;
    var cats = r.by_category    || [];
    var top  = r.top_by_revenue || [];
    var bot  = r.bottom         || [];
    var el   = document.getElementById('report-content');
    var gb   = r.group_by || 'daily';

    var totalRev = cats.reduce(function(a,b){ return a + parseFloat(b.net_revenue||0); }, 0);
    var totalQty = cats.reduce(function(a,b){ return a + parseInt(b.qty_sold||0); }, 0);

    el.innerHTML = ''
        // ── 1. PRIMARY: Trend chart ───────────────────────────────────────────
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">'
        + '<h4 class="no-margin bold" id="trend-title"></h4>'
        + '<div class="btn-group btn-group-sm">'
        + '<button class="btn btn-default view-toggle-btn' + (_viewMode === 'product'  ? ' active' : '') + '" data-mode="product"  onclick="setViewMode(\'product\')"><i class="fa fa-list"></i> By Product</button>'
        + '<button class="btn btn-default view-toggle-btn' + (_viewMode === 'category' ? ' active' : '') + '" data-mode="category" onclick="setViewMode(\'category\')"><i class="fa fa-th-large"></i> By Category</button>'
        + '</div></div>'
        + '<canvas id="chart-trend" height="60"></canvas>'
        + '</div></div></div>'
        + '</div>'

        // ── 2. KPIs ───────────────────────────────────────────────────────────
        + '<div class="row">'
        + kpiCard('green',  'Total Revenue',   'RM ' + fmt2(totalRev))
        + kpiCard('blue',   'Items Sold',       fmtInt(totalQty))
        + kpiCard('orange', 'Categories',       cats.length)
        + kpiCard('purple', 'Unique Products',  top.length)
        + '</div>'

        // ── 3. By Product sections ────────────────────────────────────────────
        + '<div id="section-product" style="display:' + (_viewMode === 'product' ? '' : 'none') + ';">'

        + '<div class="row">'
        + '<div class="col-md-7"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Top Products by Revenue'
        + '<span class="rpt-row-count" id="top-count"></span></h5>'
        + '<div id="top-wrap" style="overflow-x:auto;"><table class="table table-condensed no-margin">'
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

        + '<div class="row">'
        + '<div class="col-md-7"><div class="panel_s chart-panel"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Top 10 Products — Revenue Bar</h5>'
        + '<canvas id="chart-top-revenue" height="160"></canvas>'
        + '</div></div></div>'
        + '<div class="col-md-5"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Bottom Sellers</h5>'
        + '<div id="bot-wrap" style="overflow-x:auto;"><table class="table table-condensed no-margin">'
        + '<thead><tr><th>Product</th><th>Category</th><th class="text-right">Qty</th><th class="text-right">Net Rev.</th></tr></thead>'
        + '<tbody id="bottom-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        + '</div>' // end #section-product

        // ── 4. By Category sections ───────────────────────────────────────────
        + '<div id="section-category" style="display:' + (_viewMode === 'category' ? '' : 'none') + ';">'

        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Category Breakdown'
        + '<span class="rpt-row-count" id="cat-count"></span></h5>'
        + '<div id="cat-table-wrap" style="overflow-x:auto;"><table class="table table-condensed table-bordered no-margin">'
        + '<thead><tr><th>Category</th><th class="text-right">Products</th><th class="text-right">Receipts</th><th class="text-right">Qty Sold</th><th class="text-right">Discounts</th><th class="text-right">Net Revenue</th><th class="text-right">% Share</th></tr></thead>'
        + '<tbody id="cat-tbody"></tbody>'
        + '</table></div>'
        + '</div></div></div>'
        + '</div>'

        + '</div>'; // end #section-category

    // ── Draw trend chart ──────────────────────────────────────────────────────
    _renderTrend(r);

    // ── Top products table ────────────────────────────────────────────────────
    var topWrap = document.getElementById('top-wrap');
    document.getElementById('top-count').textContent = top.length + ' products';
    document.getElementById('top-tbody').innerHTML = top.length ? top.map(function(p, i) {
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

    // Append total row to top products table
    if (top.length) {
        var topTable = topWrap.querySelector('table');
        topTable.insertAdjacentHTML('beforeend', mkTotal(top, [
            { label: 'Total' }, { skip: true }, { skip: true },
            { key: 'qty_sold',        sum: true, fmt: 'int' },
            { key: 'gross_revenue',   sum: true, fmt: 'rm' },
            { key: 'total_discounts', sum: true, fmt: 'rm' },
            { key: 'net_revenue',     sum: true, fmt: 'rm' }
        ]));
    }
    scrollTable(topWrap, top.length, 20);

    // ── Category donut ────────────────────────────────────────────────────────
    if (_catChart) _catChart.destroy();
    if (cats.length) {
        var catLabels = cats.map(function(c){ return c.category_name; });
        var catData   = cats.map(function(c){ return parseFloat(c.net_revenue||0); });
        _catChart = new Chart(document.getElementById('chart-categories').getContext('2d'), {
            type: 'doughnut',
            data: { labels: catLabels, datasets: [{ data: catData, backgroundColor: CHART_COLORS.slice(0,catData.length), borderWidth: 2 }]},
            options: { animation: animOpts(catData.length), responsive: true, legend: { display: false }, cutoutPercentage: 55,
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

    // ── Revenue horizontal bar (top 10) ───────────────────────────────────────
    if (_revenueChart) _revenueChart.destroy();
    var top10 = top.slice(0, 10);
    if (top10.length) {
        _revenueChart = new Chart(document.getElementById('chart-top-revenue').getContext('2d'), {
            type: 'horizontalBar',
            data: { labels: top10.map(function(p){ return p.item_name; }),
                datasets: [{ label: 'Net Revenue (RM)', data: top10.map(function(p){ return parseFloat(p.net_revenue||0); }),
                    backgroundColor: 'rgba(51,122,183,0.75)', borderColor: '#337ab7', borderWidth: 1 }]},
            options: { animation: animOpts(10), responsive: true, legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }],
                    yAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }]
                }
            }
        });
    }

    // ── Bottom sellers ────────────────────────────────────────────────────────
    var botWrap = document.getElementById('bot-wrap');
    document.getElementById('bottom-tbody').innerHTML = bot.length ? bot.map(function(p) {
        return '<tr>'
            + '<td>' + htmlEnc(p.item_name) + '</td>'
            + '<td><small class="text-muted">' + htmlEnc(p.category_name) + '</small></td>'
            + '<td class="text-right">' + fmtInt(p.qty_sold) + '</td>'
            + '<td class="text-right text-danger">RM ' + fmt2(p.net_revenue) + '</td>'
            + '</tr>';
    }).join('') : '<tr><td colspan="4" class="text-muted text-center">No data</td></tr>';
    if (bot.length) {
        botWrap.querySelector('table').insertAdjacentHTML('beforeend', mkTotal(bot, [
            { label: 'Total' }, { skip: true },
            { key: 'qty_sold',   sum: true, fmt: 'int' },
            { key: 'net_revenue',sum: true, fmt: 'rm' }
        ]));
    }
    scrollTable(botWrap, bot.length, 20);

    // ── Category breakdown table ──────────────────────────────────────────────
    var catWrap = document.getElementById('cat-table-wrap');
    var catRevTotal = cats.reduce(function(a,b){return a+parseFloat(b.net_revenue||0);},0);
    document.getElementById('cat-count').textContent = cats.length + ' categories';
    document.getElementById('cat-tbody').innerHTML = cats.length ? cats.map(function(c) {
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
    if (cats.length) {
        catWrap.querySelector('table').insertAdjacentHTML('beforeend', mkTotal(cats, [
            { label: 'Total' }, { skip: true },
            { key: 'receipt_count',   sum: true, fmt: 'int' },
            { key: 'qty_sold',        sum: true, fmt: 'int' },
            { key: 'total_discounts', sum: true, fmt: 'rm' },
            { key: 'net_revenue',     sum: true, fmt: 'rm' },
            { derived: function(){ return '100.0%'; } }
        ]));
    }
    scrollTable(catWrap, cats.length, 20);
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

function getCSVData() {
    if (_viewMode === 'category') {
        var cats = (_lastData && _lastData.by_category) || [];
        return {
            filename: 'products-by-category.csv',
            cols: [
                { key: 'category_name',   label: 'Category' },
                { key: 'item_count',       label: 'Products' },
                { key: 'receipt_count',    label: 'Receipts' },
                { key: 'qty_sold',         label: 'Qty Sold' },
                { key: 'total_discounts',  label: 'Discounts (RM)' },
                { key: 'net_revenue',      label: 'Net Revenue (RM)' }
            ],
            rows: cats
        };
    }
    var top = (_lastData && _lastData.top_by_revenue) || [];
    return {
        filename: 'products-by-product.csv',
        cols: [
            { key: 'item_name',       label: 'Product' },
            { key: 'category_name',   label: 'Category' },
            { key: 'qty_sold',        label: 'Qty Sold' },
            { key: 'gross_revenue',   label: 'Gross Revenue (RM)' },
            { key: 'total_discounts', label: 'Discounts (RM)' },
            { key: 'net_revenue',     label: 'Net Revenue (RM)' },
            { key: 'avg_unit_price',  label: 'Avg Unit Price (RM)' }
        ],
        rows: top
    };
}
</script>
<?php init_tail(); ?>
