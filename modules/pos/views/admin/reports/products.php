<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<script>
var _trendChart = null;
var _lastData   = null;
var _viewMode   = 'product'; // 'product' | 'category'

// Indexed row caches (rows include _idx for period sort)
var _prodRows = [], _catRows = [];

// Sort state — default to chronological period order
var _sortColProd = '_period', _sortDirProd = 'asc';
var _sortColCat  = '_period', _sortDirCat  = 'asc';

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
        var av, bv;
        if (col === '_period') {
            av = a._idx; bv = b._idx;
        } else if (!isNaN(parseFloat(a[col]))) {
            av = parseFloat(a[col]||0); bv = parseFloat(b[col]||0);
        } else {
            av = (a[col]||'').toString().toLowerCase();
            bv = (b[col]||'').toString().toLowerCase();
        }
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ? 1 : -1;
        return 0;
    });
}

function _sortIcon(col, activeCol, dir) {
    if (col !== activeCol) return ' <i class="fa fa-sort text-muted" style="font-size:10px;opacity:.5;"></i>';
    return dir === 'asc' ? ' <i class="fa fa-sort-asc"></i>' : ' <i class="fa fa-sort-desc"></i>';
}

function sortProd(col) {
    if (_sortColProd === col) {
        _sortDirProd = _sortDirProd === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColProd = col;
        _sortDirProd = (col === 'item_name' || col === '_period') ? 'asc' : 'desc';
    }
    _renderProductTable(_prodRows);
}

function sortCat(col) {
    if (_sortColCat === col) {
        _sortDirCat = _sortDirCat === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColCat = col;
        _sortDirCat = (col === 'category_name' || col === '_period') ? 'asc' : 'desc';
    }
    _renderCategoryTable(_catRows);
}

// Return the column header label for the period based on group_by
function _periodLabel(gb) {
    switch (gb) {
        case 'hourly':        return 'Hour';
        case 'dow':           return 'Day of Week';
        case 'weekly':        return 'Week';
        case 'monthly':       return 'Month';
        default:              return 'Date'; // daily & hourly_by_day date part
    }
}

// For hourly_by_day labels like "22 Jun 14:00" → { date: "22 Jun", hour: "14:00" }
function _splitHBD(label) {
    var m = (label||'').match(/^(.+)\s(\d{2}:\d{2})$/);
    return m ? { date: m[1], hour: m[2] } : { date: label, hour: '' };
}

// ── Primary trend chart ───────────────────────────────────────────────────────
function _renderTrend(r) {
    if (_trendChart) { _trendChart.destroy(); _trendChart = null; }
    var gb    = r.group_by || 'daily';
    var gbLbl = GROUP_BY_LABEL[gb] || 'Daily';
    var title  = document.getElementById('trend-title');
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

    var seriesKey = _viewMode === 'category' ? 'category_name' : 'item_name';
    var trendData = _viewMode === 'category' ? (r.category_trend || []) : (r.product_trend || []);
    if (!trendData.length) return;

    var ms = buildMultiSeries(trendData, seriesKey, 'net_revenue');
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

// ── Render sortable product table (uses product_trend rows) ───────────────────
function _renderProductTable(rows) {
    var gb     = (_lastData && _lastData.group_by) || 'daily';
    var isHBD  = gb === 'hourly_by_day';
    var sorted = _doSort(rows, _sortColProd, _sortDirProd);
    var ths    = 'style="cursor:pointer;white-space:nowrap;user-select:none;"';
    var wrap   = document.getElementById('prod-table-wrap');
    if (!wrap) return;

    var colCount = isHBD ? 6 : 5;

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin" id="prod-table">'
        + '<thead><tr>'
        + '<th ' + ths + ' onclick="sortProd(\'_period\')">' + _periodLabel(gb) + _sortIcon('_period', _sortColProd, _sortDirProd) + '</th>'
        + (isHBD ? '<th ' + ths + ' onclick="sortProd(\'_period\')">Hour' + _sortIcon('_period', _sortColProd, _sortDirProd) + '</th>' : '')
        + '<th ' + ths + ' onclick="sortProd(\'item_name\')">Product' + _sortIcon('item_name', _sortColProd, _sortDirProd) + '</th>'
        + '<th ' + ths + ' onclick="sortProd(\'category_name\')">Category' + _sortIcon('category_name', _sortColProd, _sortDirProd) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortProd(\'qty_sold\')">Qty Sold' + _sortIcon('qty_sold', _sortColProd, _sortDirProd) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortProd(\'net_revenue\')">Net Revenue (RM)' + _sortIcon('net_revenue', _sortColProd, _sortDirProd) + '</th>'
        + '</tr></thead>'
        + '<tbody>'
        + (sorted.length ? sorted.map(function(p) {
            var split = isHBD ? _splitHBD(p.label) : null;
            return '<tr>'
                + '<td>' + htmlEnc(isHBD ? split.date : p.label) + '</td>'
                + (isHBD ? '<td>' + htmlEnc(split.hour) + '</td>' : '')
                + '<td>' + htmlEnc(p.item_name) + '</td>'
                + '<td><small class="text-muted">' + htmlEnc(p.category_name || '') + '</small></td>'
                + '<td class="text-right">' + fmtInt(p.qty_sold) + '</td>'
                + '<td class="text-right"><strong>RM ' + fmt2(p.net_revenue) + '</strong></td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="' + colCount + '" class="text-muted text-center">No sales data</td></tr>')
        + '</tbody>'
        + '</table>';

    if (sorted.length) {
        var totDef = [{ label: 'Total' }];
        if (isHBD) totDef.push({ skip: true });
        totDef.push({ skip: true }); // Product
        totDef.push({ skip: true }); // Category
        totDef.push({ key: 'qty_sold',    sum: true, fmt: 'int' });
        totDef.push({ key: 'net_revenue', sum: true, fmt: 'rm' });
        document.getElementById('prod-table').insertAdjacentHTML('beforeend', mkTotal(sorted, totDef));
    }
    scrollTable(wrap, sorted.length, 20);
}

// ── Render sortable category table (uses category_trend rows) ─────────────────
function _renderCategoryTable(rows) {
    var gb     = (_lastData && _lastData.group_by) || 'daily';
    var isHBD  = gb === 'hourly_by_day';
    var sorted = _doSort(rows, _sortColCat, _sortDirCat);
    var ths    = 'style="cursor:pointer;white-space:nowrap;user-select:none;"';
    var wrap   = document.getElementById('cat-table-wrap');
    if (!wrap) return;

    var colCount = isHBD ? 5 : 4;

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin" id="cat-table">'
        + '<thead><tr>'
        + '<th ' + ths + ' onclick="sortCat(\'_period\')">' + _periodLabel(gb) + _sortIcon('_period', _sortColCat, _sortDirCat) + '</th>'
        + (isHBD ? '<th ' + ths + ' onclick="sortCat(\'_period\')">Hour' + _sortIcon('_period', _sortColCat, _sortDirCat) + '</th>' : '')
        + '<th ' + ths + ' onclick="sortCat(\'category_name\')">Category' + _sortIcon('category_name', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortCat(\'qty_sold\')">Qty Sold' + _sortIcon('qty_sold', _sortColCat, _sortDirCat) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortCat(\'net_revenue\')">Net Revenue (RM)' + _sortIcon('net_revenue', _sortColCat, _sortDirCat) + '</th>'
        + '</tr></thead>'
        + '<tbody>'
        + (sorted.length ? sorted.map(function(c) {
            var split = isHBD ? _splitHBD(c.label) : null;
            return '<tr>'
                + '<td><strong>' + htmlEnc(isHBD ? split.date : c.label) + '</strong></td>'
                + (isHBD ? '<td>' + htmlEnc(split.hour) + '</td>' : '')
                + '<td>' + htmlEnc(c.category_name) + '</td>'
                + '<td class="text-right">' + fmtInt(c.qty_sold) + '</td>'
                + '<td class="text-right"><strong>RM ' + fmt2(c.net_revenue) + '</strong></td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="' + colCount + '" class="text-muted text-center">No category data</td></tr>')
        + '</tbody>'
        + '</table>';

    if (sorted.length) {
        var totDef = [{ label: 'Total' }];
        if (isHBD) totDef.push({ skip: true });
        totDef.push({ skip: true });
        totDef.push({ key: 'qty_sold',    sum: true, fmt: 'int' });
        totDef.push({ key: 'net_revenue', sum: true, fmt: 'rm' });
        document.getElementById('cat-table').insertAdjacentHTML('beforeend', mkTotal(sorted, totDef));
    }
    scrollTable(wrap, sorted.length, 20);
}

// ── Main render ───────────────────────────────────────────────────────────────
function renderReport(r) {
    _lastData = r;
    var cats = r.by_category    || [];
    var el   = document.getElementById('report-content');
    var gb   = r.group_by || 'daily';

    // Use all-products trend for the table; keep top-10 in r.product_trend for the chart
    _prodRows = (r.product_trend_all || []).map(function(row, i){ return Object.assign({}, row, { _idx: i }); });
    _catRows  = (r.category_trend    || []).map(function(row, i){ return Object.assign({}, row, { _idx: i }); });

    // Reset sort to chronological on new data load
    _sortColProd = '_period'; _sortDirProd = 'asc';
    _sortColCat  = '_period'; _sortDirCat  = 'asc';

    // Unique product count from the all-products data
    var uniqueProds = {};
    _prodRows.forEach(function(row){ uniqueProds[row.item_name] = 1; });
    var uniqueProdCount = Object.keys(uniqueProds).length;

    var totalRev = cats.reduce(function(a,b){ return a + parseFloat(b.net_revenue||0); }, 0);
    var totalQty = cats.reduce(function(a,b){ return a + parseInt(b.qty_sold||0); }, 0);

    // Hide trend chart for single-day non-hourly views (one data point — not useful)
    var showTrend = (r.date_from !== r.date_to) || gb === 'hourly' || gb === 'hourly_by_day';

    el.innerHTML = ''
        // ── 1. Trend chart (multi-day or hourly only)
        + (showTrend
            ? '<div class="row">'
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
            : // Single-day: still show the view toggle, just no chart
              '<div class="row no-print" style="margin-bottom:8px;">'
              + '<div class="col-md-12 text-right">'
              + '<div class="btn-group btn-group-sm">'
              + '<button class="btn btn-default view-toggle-btn' + (_viewMode === 'product'  ? ' active' : '') + '" data-mode="product"  onclick="setViewMode(\'product\')"><i class="fa fa-list"></i> By Product</button>'
              + '<button class="btn btn-default view-toggle-btn' + (_viewMode === 'category' ? ' active' : '') + '" data-mode="category" onclick="setViewMode(\'category\')"><i class="fa fa-th-large"></i> By Category</button>'
              + '</div></div>'
              + '</div>'
          )

        // ── 2. KPIs
        + '<div class="row">'
        + kpiCard('green',  'Total Revenue',  'RM ' + fmt2(totalRev))
        + kpiCard('blue',   'Items Sold',      fmtInt(totalQty))
        + kpiCard('orange', 'Categories',      cats.length)
        + kpiCard('purple', 'Unique Products', uniqueProdCount)
        + '</div>'

        // ── 3. By Product — sortable table with date column
        + '<div id="section-product" style="display:' + (_viewMode === 'product' ? '' : 'none') + ';">'
        + '<div class="row"><div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Products <span class="rpt-row-count" id="prod-count"></span></h5>'
        + '<div id="prod-table-wrap" style="overflow-x:auto;"></div>'
        + '</div></div></div></div>'
        + '</div>'

        // ── 4. By Category — sortable table with date column
        + '<div id="section-category" style="display:' + (_viewMode === 'category' ? '' : 'none') + ';">'
        + '<div class="row"><div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Categories <span class="rpt-row-count" id="cat-count"></span></h5>'
        + '<div id="cat-table-wrap" style="overflow-x:auto;"></div>'
        + '</div></div></div></div>'
        + '</div>';

    if (showTrend) _renderTrend(r);

    document.getElementById('prod-count').textContent = _prodRows.length + ' rows';
    _renderProductTable(_prodRows);

    document.getElementById('cat-count').textContent = _catRows.length + ' rows';
    _renderCategoryTable(_catRows);
}

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

// ── Override toolbar loadReport to send product filters ───────────────────────
function loadReport(from, to) {
    if (_activeXhr) { _activeXhr.abort(); _activeXhr = null; }
    $('#report-loader').show().html('<i class="fa fa-spinner fa-spin fa-2x"></i><br><span class="mtop10 inline-block">Loading...</span>');
    $('#report-content').hide();
    _activeXhr = $.post(ADMIN_URL + 'pos/ajax_report_data', {
        section:        REPORT_SECTION,
        date_from:      from,
        date_to:        to,
        warehouse_id:   $('#warehouse-filter').val()        || '',
        group_by:       $('#group-by').val()                || 'daily',
        category_id:    $('#product-category-filter').val() || '',
        product_search: ($('#product-search-filter').val()  || '').trim()
    }).done(function(resp) {
        _activeXhr = null;
        if (typeof resp === 'string') { try { resp = JSON.parse(resp); } catch(e) {} }
        if (!resp || !resp.success) {
            var msg = (resp && resp.error) ? resp.error : 'Unknown error';
            $('#report-loader').html('<i class="fa fa-exclamation-circle text-danger fa-2x"></i><br><span class="text-danger mtop10 inline-block">'+msg+'</span>');
            return;
        }
        try { renderReport(resp); } catch(e) { console.error(e); }
        $('#report-loader').hide();
        $('#report-content').show();
    }).fail(function(xhr) {
        _activeXhr = null;
        if (xhr.statusText === 'abort') return;
        $('#report-loader').html('<i class="fa fa-exclamation-circle text-danger fa-2x"></i><br><span class="text-danger mtop10 inline-block">Request failed ('+xhr.status+')</span>');
    });
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
    var gb     = $('#group-by').val() || 'daily';
    var catTxt = ($('#product-category-filter option:selected').text() || '').trim();
    if (catTxt && catTxt !== 'All Categories') {
        gb += '_' + catTxt.replace(/[^a-z0-9]/gi, '-').toLowerCase();
    }
    var search = ($('#product-search-filter').val() || '').trim();
    if (search) {
        gb += '_' + search.replace(/[^a-z0-9]/gi, '-').toLowerCase().substring(0, 20);
    }
    return (from && to ? '_' + from + '_' + to : '') + '_' + gb;
}

function getCSVData() {
    var suffix = _getExportSuffix();
    var gb     = (_lastData && _lastData.group_by) || 'daily';
    var isHBD  = gb === 'hourly_by_day';

    if (_viewMode === 'category') {
        var sorted = _doSort(_catRows, _sortColCat, _sortDirCat);
        var cols   = [];
        if (isHBD) {
            cols.push({ key: '_date', label: 'Date' });
            cols.push({ key: '_hour', label: 'Hour' });
        } else {
            cols.push({ key: 'label', label: _periodLabel(gb) });
        }
        cols.push({ key: 'category_name', label: 'Category' });
        cols.push({ key: 'qty_sold',      label: 'Qty Sold' });
        cols.push({ key: 'net_revenue',   label: 'Net Revenue (RM)' });
        var rows = isHBD ? sorted.map(function(r) {
            var sp = _splitHBD(r.label);
            return Object.assign({}, r, { _date: sp.date, _hour: sp.hour });
        }) : sorted;
        return { filename: 'products-by-category' + suffix + '.csv', cols: cols, rows: rows };
    }

    var sorted = _doSort(_prodRows, _sortColProd, _sortDirProd);
    var cols   = [];
    if (isHBD) {
        cols.push({ key: '_date', label: 'Date' });
        cols.push({ key: '_hour', label: 'Hour' });
    } else {
        cols.push({ key: 'label', label: _periodLabel(gb) });
    }
    cols.push({ key: 'item_name',    label: 'Product' });
    cols.push({ key: 'category_name', label: 'Category' });
    cols.push({ key: 'qty_sold',     label: 'Qty Sold' });
    cols.push({ key: 'net_revenue',  label: 'Net Revenue (RM)' });
    var rows = isHBD ? sorted.map(function(r) {
        var sp = _splitHBD(r.label);
        return Object.assign({}, r, { _date: sp.date, _hour: sp.hour });
    }) : sorted;
    return { filename: 'products-by-product' + suffix + '.csv', cols: cols, rows: rows };
}
</script>
<?php init_tail(); ?>
