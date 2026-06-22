<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _trendChart = null;
var _lastData    = null;
var _viewMode    = 'product'; // 'product' | 'category'

// Sort state
var _sortColProd = 'net_revenue', _sortDirProd = 'desc';
var _sortColCat  = 'net_revenue', _sortDirCat  = 'desc';

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

// ── Sort helpers ──────────────────────────────────────────────────────────────
function _doSort(arr, col, dir) {
    return arr.slice().sort(function(a, b) {
        var av = isNaN(parseFloat(a[col])) ? (a[col]||'').toString().toLowerCase() : parseFloat(a[col]||0);
        var bv = isNaN(parseFloat(b[col])) ? (b[col]||'').toString().toLowerCase() : parseFloat(b[col]||0);
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ? 1 : -1;
        return 0;
    });
}

function _sortIcon(col, activeCol, dir) {
    if (col !== activeCol) return ' <i class="fa fa-sort text-muted" style="font-size:10px;opacity:.5;"></i>';
    return dir === 'asc'
        ? ' <i class="fa fa-sort-asc"></i>'
        : ' <i class="fa fa-sort-desc"></i>';
}

function sortProd(col) {
    if (_sortColProd === col) {
        _sortDirProd = _sortDirProd === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColProd = col;
        _sortDirProd = (col === 'item_name' || col === 'category_name') ? 'asc' : 'desc';
    }
    if (_lastData) _renderProductTable(_lastData.top_by_revenue || []);
}

function sortCat(col) {
    if (_sortColCat === col) {
        _sortDirCat = _sortDirCat === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColCat = col;
        _sortDirCat = col === 'category_name' ? 'asc' : 'desc';
    }
    if (_lastData) _renderCategoryTable(_lastData.by_category || []);
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
        var catTrend = r.category_trend || [];
        if (!catTrend.length) return;
        var ms = buildMultiSeries(catTrend, 'category_name', 'net_revenue');
        var datasets = ms.datasets.map(function(ds, idx) {
            var col = CHART_COLORS[idx % CHART_COLORS.length];
            return {
                label: ds.name, data: ds.data,
                backgroundColor: col, borderColor: col,
                borderWidth: isBar ? 1 : 2,
                fill: !isBar,
                pointRadius: !isBar && ms.labels.length > 14 ? 0 : 3
            };
        });
        _trendChart = new Chart(canvas.getContext('2d'), {
            type: isBar ? 'bar' : 'line',
            data: { labels: ms.labels, datasets: datasets },
            options: {
                animation: animOpts(ms.labels.length), responsive: true,
                scales: {
                    xAxes: [{ stacked: true, ticks: { fontSize: 11, maxRotation: 60 }, gridLines: { display: false } }],
                    yAxes: [{ stacked: !isBar, ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
                },
                tooltips: stackedTooltip
            }
        });
    } else {
        var prodTrend = r.product_trend || [];
        if (!prodTrend.length) return;
        var ms = buildMultiSeries(prodTrend, 'item_name', 'net_revenue');
        var datasets = ms.datasets.map(function(ds, idx) {
            var col = CHART_COLORS[idx % CHART_COLORS.length];
            return {
                label: ds.name, data: ds.data,
                backgroundColor: col, borderColor: col,
                borderWidth: isBar ? 1 : 2,
                fill: !isBar,
                pointRadius: !isBar && ms.labels.length > 14 ? 0 : 3
            };
        });
        _trendChart = new Chart(canvas.getContext('2d'), {
            type: isBar ? 'bar' : 'line',
            data: { labels: ms.labels, datasets: datasets },
            options: {
                animation: animOpts(ms.labels.length), responsive: true,
                scales: {
                    xAxes: [{ stacked: true, ticks: { fontSize: 11, maxRotation: 60 }, gridLines: { display: false } }],
                    yAxes: [{ stacked: !isBar, ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
                },
                tooltips: stackedTooltip
            }
        });
    }
}

// ── Render sortable product table ─────────────────────────────────────────────
function _renderProductTable(top) {
    var sorted = _doSort(top, _sortColProd, _sortDirProd);
    var ths    = 'style="cursor:pointer;white-space:nowrap;user-select:none;"';
    var wrap   = document.getElementById('prod-table-wrap');
    if (!wrap) return;

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin" id="prod-table">'
        + '<thead><tr>'
        + '<th style="width:36px;">#</th>'
        + '<th ' + ths + ' onclick="sortProd(\'item_name\')">Product' + _sortIcon('item_name', _sortColProd, _sortDirProd) + '</th>'
        + '<th ' + ths + ' onclick="sortProd(\'category_name\')">Category' + _sortIcon('category_name', _sortColProd, _sortDirProd) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortProd(\'qty_sold\')">Qty Sold' + _sortIcon('qty_sold', _sortColProd, _sortDirProd) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortProd(\'gross_revenue\')">Gross (RM)' + _sortIcon('gross_revenue', _sortColProd, _sortDirProd) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortProd(\'total_discounts\')">Discounts (RM)' + _sortIcon('total_discounts', _sortColProd, _sortDirProd) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortProd(\'net_revenue\')">Net Revenue (RM)' + _sortIcon('net_revenue', _sortColProd, _sortDirProd) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortProd(\'avg_unit_price\')">Avg Price (RM)' + _sortIcon('avg_unit_price', _sortColProd, _sortDirProd) + '</th>'
        + '</tr></thead>'
        + '<tbody>'
        + (sorted.length ? sorted.map(function(p, i) {
            return '<tr>'
                + '<td class="text-muted">' + (i+1) + '</td>'
                + '<td>' + htmlEnc(p.item_name) + '</td>'
                + '<td><small class="text-muted">' + htmlEnc(p.category_name) + '</small></td>'
                + '<td class="text-right">' + fmtInt(p.qty_sold) + '</td>'
                + '<td class="text-right text-muted">RM ' + fmt2(p.gross_revenue) + '</td>'
                + '<td class="text-right text-warning">RM ' + fmt2(p.total_discounts) + '</td>'
                + '<td class="text-right"><strong>RM ' + fmt2(p.net_revenue) + '</strong></td>'
                + '<td class="text-right">RM ' + fmt2(p.avg_unit_price) + '</td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="8" class="text-muted text-center">No sales data</td></tr>')
        + '</tbody>'
        + '</table>';

    if (sorted.length) {
        document.getElementById('prod-table').insertAdjacentHTML('beforeend', mkTotal(sorted, [
            { label: 'Total' }, { skip: true }, { skip: true },
            { key: 'qty_sold',        sum: true, fmt: 'int' },
            { key: 'gross_revenue',   sum: true, fmt: 'rm' },
            { key: 'total_discounts', sum: true, fmt: 'rm' },
            { key: 'net_revenue',     sum: true, fmt: 'rm' },
            { skip: true }
        ]));
    }
    scrollTable(wrap, sorted.length, 20);
}

// ── Render sortable category table ────────────────────────────────────────────
function _renderCategoryTable(cats) {
    var sorted      = _doSort(cats, _sortColCat, _sortDirCat);
    var catRevTotal = sorted.reduce(function(a,b){ return a + parseFloat(b.net_revenue||0); }, 0);
    var ths         = 'style="cursor:pointer;white-space:nowrap;user-select:none;"';
    var wrap        = document.getElementById('cat-table-wrap');
    if (!wrap) return;

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin" id="cat-table">'
        + '<thead><tr>'
        + '<th ' + ths + ' onclick="sortCat(\'category_name\')">Category' + _sortIcon('category_name', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortCat(\'item_count\')">Products' + _sortIcon('item_count', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortCat(\'receipt_count\')">Receipts' + _sortIcon('receipt_count', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortCat(\'qty_sold\')">Qty Sold' + _sortIcon('qty_sold', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortCat(\'total_discounts\')">Discounts (RM)' + _sortIcon('total_discounts', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortCat(\'net_revenue\')">Net Revenue (RM)' + _sortIcon('net_revenue', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right">% Share</th>'
        + '</tr></thead>'
        + '<tbody>'
        + (sorted.length ? sorted.map(function(c) {
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
        }).join('') : '<tr><td colspan="7" class="text-muted text-center">No category data</td></tr>')
        + '</tbody>'
        + '</table>';

    if (sorted.length) {
        document.getElementById('cat-table').insertAdjacentHTML('beforeend', mkTotal(sorted, [
            { label: 'Total' }, { skip: true },
            { key: 'receipt_count',   sum: true, fmt: 'int' },
            { key: 'qty_sold',        sum: true, fmt: 'int' },
            { key: 'total_discounts', sum: true, fmt: 'rm' },
            { key: 'net_revenue',     sum: true, fmt: 'rm' },
            { derived: function(){ return '100.0%'; } }
        ]));
    }
    scrollTable(wrap, sorted.length, 20);
}

// ── Main render ───────────────────────────────────────────────────────────────
function renderReport(r) {
    _lastData = r;
    var cats = r.by_category    || [];
    var top  = r.top_by_revenue || [];
    var el   = document.getElementById('report-content');

    var totalRev = cats.reduce(function(a,b){ return a + parseFloat(b.net_revenue||0); }, 0);
    var totalQty = cats.reduce(function(a,b){ return a + parseInt(b.qty_sold||0); }, 0);

    el.innerHTML = ''
        // ── 1. Trend chart
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

        // ── 2. KPIs
        + '<div class="row">'
        + kpiCard('green',  'Total Revenue',  'RM ' + fmt2(totalRev))
        + kpiCard('blue',   'Items Sold',      fmtInt(totalQty))
        + kpiCard('orange', 'Categories',      cats.length)
        + kpiCard('purple', 'Unique Products', top.length)
        + '</div>'

        // ── 3. By Product — sortable table
        + '<div id="section-product" style="display:' + (_viewMode === 'product' ? '' : 'none') + ';">'
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Products <span class="rpt-row-count" id="prod-count"></span></h5>'
        + '<div id="prod-table-wrap" style="overflow-x:auto;"></div>'
        + '</div></div></div>'
        + '</div>'
        + '</div>'

        // ── 4. By Category — sortable table
        + '<div id="section-category" style="display:' + (_viewMode === 'category' ? '' : 'none') + ';">'
        + '<div class="row">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Category Breakdown <span class="rpt-row-count" id="cat-count"></span></h5>'
        + '<div id="cat-table-wrap" style="overflow-x:auto;"></div>'
        + '</div></div></div>'
        + '</div>'
        + '</div>';

    _renderTrend(r);

    document.getElementById('prod-count').textContent = top.length + ' products';
    _renderProductTable(top);

    document.getElementById('cat-count').textContent = cats.length + ' categories';
    _renderCategoryTable(cats);
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

// ── Export helpers ────────────────────────────────────────────────────────────
function _getExportSuffix() {
    var active = document.querySelector('.period-btn.active');
    var from, to;
    if (active) {
        var d = getPeriodDates(active.getAttribute('data-period'));
        from = d.from; to = d.to;
    } else {
        from = $('#custom-from').val() || '';
        to   = $('#custom-to').val()   || '';
    }
    var gb = $('#group-by').val() || 'daily';
    return (from && to ? '_' + from + '_' + to : '') + '_' + gb;
}

function getCSVData() {
    var suffix = _getExportSuffix();
    if (_viewMode === 'category') {
        var cats   = (_lastData && _lastData.by_category) || [];
        var sorted = _doSort(cats, _sortColCat, _sortDirCat);
        return {
            filename: 'products-by-category' + suffix + '.csv',
            cols: [
                { key: 'category_name',   label: 'Category' },
                { key: 'item_count',       label: 'Products' },
                { key: 'receipt_count',    label: 'Receipts' },
                { key: 'qty_sold',         label: 'Qty Sold' },
                { key: 'total_discounts',  label: 'Discounts (RM)' },
                { key: 'net_revenue',      label: 'Net Revenue (RM)' }
            ],
            rows: sorted
        };
    }
    var top    = (_lastData && _lastData.top_by_revenue) || [];
    var sorted = _doSort(top, _sortColProd, _sortDirProd);
    return {
        filename: 'products-by-product' + suffix + '.csv',
        cols: [
            { key: 'item_name',       label: 'Product' },
            { key: 'category_name',   label: 'Category' },
            { key: 'qty_sold',        label: 'Qty Sold' },
            { key: 'gross_revenue',   label: 'Gross Revenue (RM)' },
            { key: 'total_discounts', label: 'Discounts (RM)' },
            { key: 'net_revenue',     label: 'Net Revenue (RM)' },
            { key: 'avg_unit_price',  label: 'Avg Unit Price (RM)' }
        ],
        rows: sorted
    };
}
</script>
<?php init_tail(); ?>
