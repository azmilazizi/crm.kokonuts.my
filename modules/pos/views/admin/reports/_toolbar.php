<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
/* ── KPI cards ───────────────────────────────────────────────────────────── */
.kpi-card { border-left: 4px solid #ddd; }
.kpi-card.green  { border-left-color: #5cb85c; }
.kpi-card.blue   { border-left-color: #337ab7; }
.kpi-card.orange { border-left-color: #f0ad4e; }
.kpi-card.red    { border-left-color: #d9534f; }
.kpi-card.purple { border-left-color: #9b59b6; }
.kpi-card.teal   { border-left-color: #1abc9c; }
.kpi-value { font-size: 26px; font-weight: 700; margin: 4px 0; }
.kpi-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: .5px; }

/* ── Report misc ─────────────────────────────────────────────────────────── */
.period-btn.active { background: #337ab7; color: #fff; border-color: #337ab7; }
.chart-panel { min-height: 260px; }
.report-loader { text-align:center; padding: 60px 0; color: #aaa; }
#print-header { display: none; }
.rpt-scroll { max-height: 400px; overflow-y: auto; position: relative; }
.rpt-scroll table thead th,
.rpt-scroll table tfoot td { position: sticky; background: #fff; z-index: 1; }
.rpt-scroll table thead th { top: 0; box-shadow: 0 1px 0 #ddd; }
.rpt-scroll table tfoot td { bottom: 0; box-shadow: 0 -1px 0 #ddd; }
table tfoot.rpt-total td { background: #f7f7f7; font-weight: 700; }
.rpt-row-count { font-size: 11px; color: #aaa; float: right; margin-top: -4px; }

/* ── Toolbar layout ──────────────────────────────────────────────────────── */
.rpt-toolbar { margin-bottom: 16px; }

/* Row 1 — period quick-select (scrolls horizontally, never wraps) */
.rpt-period-scroll {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    white-space: nowrap;
    padding-bottom: 3px;
    margin-bottom: 8px;
}
.rpt-period-scroll::-webkit-scrollbar { height: 3px; }
.rpt-period-scroll::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 2px; }
.rpt-period-scroll .btn-group { display: inline-flex; }

/* Row 2 — custom date range */
.rpt-date-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}
.rpt-date-row .form-control { width: 120px; flex-shrink: 0; }

/* Row 3 — controls: Group by | Warehouse | Export buttons */
.rpt-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.rpt-ctrl-groupby  { flex: 0 1 220px; min-width: 160px; }
.rpt-ctrl-warehouse { flex: 1 1 180px; min-width: 150px; }
.rpt-ctrl-exports  { display: flex; gap: 6px; margin-left: auto; flex-shrink: 0; }

/* Row 4 (products only) — category + search + clear */
.rpt-product-filters {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 10px;
    padding: 10px 12px;
    background: #f9f9f9;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
}
.rpt-pfilt-cat    { flex: 0 1 260px; min-width: 180px; }
.rpt-pfilt-search { flex: 1 1 200px; min-width: 160px; }
.rpt-pfilt-clear  { flex-shrink: 0; }

/* ── Nav tabs — scrollable on small screens ──────────────────────────────── */
.nav-tabs { display: flex; flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.nav-tabs > li { flex: 0 0 auto; float: none; }
.nav-tabs::-webkit-scrollbar { height: 3px; }
.nav-tabs::-webkit-scrollbar-thumb { background: #d0d0d0; border-radius: 2px; }

/* ── Period + date on same row ≥768px ────────────────────────────────────── */
.rpt-period-date-wrap { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.rpt-period-date-wrap .rpt-period-scroll { flex: 1 1 auto; min-width: 0; margin-bottom: 0; }
.rpt-period-date-wrap .rpt-date-row { flex: 0 0 auto; margin-bottom: 0; flex-wrap: nowrap; }

/* Mobile (< 768px) */
@media (max-width: 767px) {
    .rpt-ctrl-groupby  { flex: 1 1 calc(50% - 8px); min-width: 120px; }
    .rpt-ctrl-warehouse { flex: 1 1 calc(50% - 8px); min-width: 120px; }
    .rpt-ctrl-exports  { flex: 1 1 100%; margin-left: 0; }
    .rpt-ctrl-exports .btn { flex: 1; }
    .rpt-pfilt-cat    { flex: 1 1 100%; }
    .rpt-pfilt-search { flex: 1 1 calc(100% - 72px); }
    .kpi-card { margin-bottom: 10px; }
    .kpi-value { font-size: 20px; }
    .rpt-period-date-wrap { flex-direction: column; align-items: stretch; }
    .rpt-period-date-wrap .rpt-date-row { flex-wrap: wrap; }
}

/* Tablet (768–991px) */
@media (min-width: 768px) and (max-width: 991px) {
    .rpt-ctrl-groupby  { flex: 0 1 180px; }
    .rpt-ctrl-warehouse { flex: 1 1 160px; }
    .kpi-card { margin-bottom: 8px; }
}

/* Fix selectpicker in flex container — target the generated button too */
.rpt-ctrl-warehouse .bootstrap-select,
.rpt-pfilt-cat .bootstrap-select { display: block !important; width: 100% !important; }
.rpt-ctrl-warehouse .bootstrap-select > .dropdown-toggle,
.rpt-pfilt-cat .bootstrap-select > .dropdown-toggle { width: 100%; text-align: left; }

/* ── KPI delta / comparison badges ───────────────────────────────────────── */
.kpi-vs        { margin-top: 4px; }
.kpi-badge     { display: inline-block; padding: 2px 8px; border-radius: 10px; font-weight: 600; font-size: 11px; line-height: 1.4; }
.kpi-badge.up  { background: #eafaf1; color: #27ae60; }
.kpi-badge.down { background: #fdf2f2; color: #c0392b; }
.kpi-badge.flat { background: #f5f5f5; color: #888; }
.kpi-badge-abs { display: block; font-size: 10px; font-weight: 400; opacity: 0.75; }
.kpi-prev-bar  { font-size: 11px; color: #aaa; margin-bottom: 6px; padding: 3px 8px; background: #f9f9f9; border-radius: 4px; display: inline-block; }

/* Print */
@media print {
    .navbar-default, .sidebar-menu, .sidebar, .breadcrumb-wrapper,
    .no-print, .nav-tabs, button, select,
    .input-group, .btn-group, .selectpicker,
    .rpt-toolbar { display: none !important; }
    body { margin: 0; background: #fff; }
    .content { margin: 0 !important; padding: 10px !important; }
    #wrapper { margin-left: 0 !important; }
    #print-header { display: block !important; margin-bottom: 16px; }
    canvas { max-width: 100% !important; page-break-inside: avoid; }
    .panel_s { border: 1px solid #ccc !important; page-break-inside: avoid; margin-bottom: 12px; box-shadow: none !important; }
    .kpi-value { font-size: 20px !important; }
    table { font-size: 11px !important; }
    #report-content { display: block !important; }
}
</style>

<!-- Print-only header -->
<div id="print-header">
    <h3 id="print-title" style="margin:0 0 2px;"></h3>
    <p id="print-meta" style="margin:0;color:#555;font-size:12px;"></p>
    <hr style="margin:8px 0 12px;">
</div>

<!-- Sub-navigation tabs -->
<ul class="nav nav-tabs no-print" style="margin-bottom:16px;">
    <li class="<?php echo $active_tab === 'sales'      ? 'active' : ''; ?>"><a href="<?php echo admin_url('pos/reports/sales'); ?>">Sales</a></li>
    <li class="<?php echo $active_tab === 'products'   ? 'active' : ''; ?>"><a href="<?php echo admin_url('pos/reports/products'); ?>">Products</a></li>
    <li class="<?php echo $active_tab === 'payments'   ? 'active' : ''; ?>"><a href="<?php echo admin_url('pos/reports/payments'); ?>">Payment Modes</a></li>
    <li class="<?php echo $active_tab === 'txn_types'  ? 'active' : ''; ?>"><a href="<?php echo admin_url('pos/reports/txn_types'); ?>">Transaction Types</a></li>
</ul>

<div class="rpt-toolbar no-print">

    <!-- Row 1: Period + date — side-by-side on ≥768px, stacked on mobile -->
    <div class="rpt-period-date-wrap">
        <div class="rpt-period-scroll">
            <div class="btn-group" id="period-btns">
                <button class="btn btn-default btn-sm period-btn active" data-period="today"      onclick="onPeriodBtn(this)">Today</button>
                <button class="btn btn-default btn-sm period-btn"       data-period="yesterday"   onclick="onPeriodBtn(this)">Yesterday</button>
                <button class="btn btn-default btn-sm period-btn"       data-period="week"        onclick="onPeriodBtn(this)">Last 7 Days</button>
                <button class="btn btn-default btn-sm period-btn"       data-period="month"       onclick="onPeriodBtn(this)">This Month</button>
                <button class="btn btn-default btn-sm period-btn"       data-period="last_month"  onclick="onPeriodBtn(this)">Last Month</button>
            </div>
        </div>
        <div class="rpt-date-row">
            <input type="text" id="custom-from" class="form-control input-sm" placeholder="From">
            <input type="text" id="custom-to"   class="form-control input-sm" placeholder="To">
            <button class="btn btn-default btn-sm" onclick="applyCustom()">Go</button>
        </div>
    </div>

    <!-- Row 3: Group by + Warehouse + Export -->
    <div class="rpt-controls">
        <div class="rpt-ctrl-groupby">
            <div class="input-group input-group-sm">
                <span class="input-group-addon"><i class="fa fa-clock-o"></i></span>
                <select id="group-by" class="form-control" onchange="onGroupByChange()">
                    <option value="daily">Daily</option>
                    <option value="hourly">Hours of Day</option>
                    <option value="hourly_by_day">Hours by Day</option>
                    <option value="dow">Day of Week</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
        </div>
        <div class="rpt-ctrl-warehouse">
            <select id="warehouse-filter" class="form-control input-sm selectpicker" data-live-search="true" title="All Warehouses" onchange="onWarehouseChange()">
                <?php foreach ($warehouses as $w) { ?>
                <option value="<?php echo $w['warehouse_id']; ?>"><?php echo htmlspecialchars($w['warehouse_name']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="rpt-ctrl-exports">
            <button class="btn btn-default btn-sm" onclick="doExportCSV()"><i class="fa fa-download"></i> <span class="hidden-xs">Export </span>CSV</button>
            <button class="btn btn-default btn-sm" onclick="doExportPDF()"><i class="fa fa-file-pdf-o"></i> <span class="hidden-xs">Export </span>PDF</button>
        </div>
    </div>

    <?php if ($active_tab === 'products' && isset($product_categories)): ?>
    <!-- Row 4 (products only): Category + Product search + Clear -->
    <div class="rpt-product-filters" id="product-filter-row">
        <div class="rpt-pfilt-cat">
            <div class="input-group input-group-sm">
                <span class="input-group-addon"><i class="fa fa-th-large"></i></span>
                <select id="product-category-filter" class="form-control selectpicker" data-live-search="true" title="All Categories" onchange="onProductFilterChange()">
                    <option value="0">Uncategorised</option>
                    <?php foreach ($product_categories as $cat): ?>
                    <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['sub_group_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="rpt-pfilt-search">
            <div class="input-group input-group-sm">
                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                <input type="text" id="product-search-filter" class="form-control" placeholder="Search product…" onkeydown="if(event.key==='Enter')onProductFilterChange()">
                <span class="input-group-btn">
                    <button class="btn btn-default" type="button" onclick="onProductFilterChange()"><i class="fa fa-search"></i></button>
                </span>
            </div>
        </div>
        <div class="rpt-pfilt-clear">
            <button class="btn btn-default btn-sm" type="button" onclick="clearProductFilters()" title="Clear filters"><i class="fa fa-times"></i> Clear</button>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.rpt-toolbar -->

<div id="report-loader" class="report-loader">
    <i class="fa fa-spinner fa-spin fa-2x"></i><br><span class="mtop10 inline-block">Loading...</span>
</div>
<div id="report-content" style="display:none;"></div>

<script>
var ADMIN_URL      = '<?php echo admin_url(); ?>';
var REPORT_SECTION = '<?php echo $active_tab; ?>';
var _ready         = false;
var _activeXhr     = null;

// ── Period helpers ────────────────────────────────────────────────────────────
function getPeriodDates(p) {
    var t = new Date(), y = t.getFullYear(), m = t.getMonth(), d = t.getDate();
    var fmt = function(dt) { return dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate()); };
    var sub = function(n) { return new Date(y, m, d - n); };
    switch (p) {
        case 'today':      return { from: fmt(t),                        to: fmt(t) };
        case 'yesterday':  return { from: fmt(sub(1)),                   to: fmt(sub(1)) };
        case 'week':       return { from: fmt(sub(6)),                   to: fmt(t) };
        case 'month':      return { from: fmt(new Date(y, m, 1)),        to: fmt(t) };
        case 'last_month': return { from: fmt(new Date(y, m-1, 1)),      to: fmt(new Date(y, m, 0)) };
    }
}
function pad(n) { return n < 10 ? '0'+n : ''+n; }

// ── Formatters ────────────────────────────────────────────────────────────────
function fmt2(n)    { return parseFloat(n||0).toLocaleString('en-MY', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function fmtInt(n)  { return parseInt(n||0).toLocaleString('en-MY'); }
function htmlEnc(s) { return $('<span>').text(s||'').html(); }

var CHART_COLORS = ['#337ab7','#5cb85c','#f0ad4e','#d9534f','#9b59b6','#1abc9c','#e67e22','#34495e','#16a085','#e74c3c'];

// ── Group-by helpers ─────────────────────────────────────────────────────────
var GROUP_BY_LABEL = {
    daily:          'Daily',
    hourly:         'By Hour of Day',
    hourly_by_day:  'Hours by Day',
    dow:            'By Day of Week',
    weekly:         'Weekly',
    monthly:        'Monthly'
};

/**
 * Fill gaps for hourly (0–23) and DOW (Sun–Sat) so charts show complete axes.
 * Expects array of objects with a 'label' key. Adds zero-value rows for missing slots.
 */
function normalizeTrend(trend, groupBy) {
    var zero = { label:'', net_sales:0, gross_sales:0, transaction_count:0,
                 total_discounts:0, total_tax:0, net_revenue:0, qty_sold:0,
                 receipt_count:0, total_amount:0, total_receipts:0, total_revenue:0 };
    if (groupBy === 'hourly') {
        var map = {};
        trend.forEach(function(d){ map[d.label] = d; });
        var out = [];
        for (var h = 0; h < 24; h++) {
            var lbl = (h < 10 ? '0' : '') + h + ':00';
            out.push(map[lbl] || Object.assign({}, zero, { label: lbl }));
        }
        return out;
    }
    if (groupBy === 'dow') {
        var DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var map = {};
        trend.forEach(function(d){ map[d.label] = d; });
        return DAYS.map(function(d){ return map[d] || Object.assign({}, zero, { label: d }); });
    }
    return trend;
}

/**
 * Build chart.js labels + multi-series datasets from flat [{label, seriesKey, valueKey}, ...] rows.
 */
function buildMultiSeries(trend, seriesKey, valueKey) {
    var labels = [], seriesMap = {}, seriesOrder = [];
    trend.forEach(function(d) {
        if (labels.indexOf(d.label) === -1) labels.push(d.label);
        if (!seriesMap[d[seriesKey]]) { seriesMap[d[seriesKey]] = {}; seriesOrder.push(d[seriesKey]); }
        seriesMap[d[seriesKey]][d.label] = parseFloat(d[valueKey] || 0);
    });
    var datasets = seriesOrder.map(function(name, idx) {
        return {
            name: name,
            data: labels.map(function(lbl) { return seriesMap[name][lbl] || 0; })
        };
    });
    return { labels: labels, datasets: datasets };
}

/**
 * Generate <tfoot class="rpt-total"> with summed/derived cells.
 * colDefs: array of { label, key, sum, fmt:'rm'|'int'|'pct', derived:fn(rows), skip }
 *   - label  → renders that text (use for the first "Total" cell)
 *   - sum    → sums the column; fmt controls formatting
 *   - derived→ fn(rows) returns a pre-formatted string
 *   - skip   → renders an empty cell
 */
function mkTotal(rows, colDefs) {
    var cells = colDefs.map(function(c) {
        if (c.label !== undefined)
            return '<td><strong>' + c.label + '</strong></td>';
        if (c.skip || !c.sum && !c.derived)
            return '<td></td>';
        if (c.derived)
            return '<td class="text-right"><strong>' + c.derived(rows) + '</strong></td>';
        var v = rows.reduce(function(a, r) { return a + parseFloat(r[c.key] || 0); }, 0);
        if (c.fmt === 'rm')  return '<td class="text-right"><strong>RM ' + fmt2(v) + '</strong></td>';
        if (c.fmt === 'int') return '<td class="text-right"><strong>' + fmtInt(v) + '</strong></td>';
        return '<td class="text-right"><strong>' + fmt2(v) + '</strong></td>';
    });
    return '<tfoot class="rpt-total"><tr>' + cells.join('') + '</tr></tfoot>';
}

/**
 * Apply sticky-header scrolling to a table wrapper div when row count exceeds threshold.
 * wrapEl  — the div wrapping the table
 * rowCount— number of data rows
 * threshold — default 20
 */
function scrollTable(wrapEl, rowCount, threshold) {
    threshold = threshold || 20;
    if (!wrapEl || rowCount <= threshold) return;
    wrapEl.classList.add('rpt-scroll');
}

/**
 * Chart.js animation options — disable for large datasets to stay responsive.
 */
function animOpts(pointCount) {
    return pointCount > 90 ? { duration: 0 } : { duration: 500 };
}

// ── Load / reload ─────────────────────────────────────────────────────────────
function loadReport(from, to) {
    if (_activeXhr) { _activeXhr.abort(); _activeXhr = null; }
    $('#report-loader').show().html('<i class="fa fa-spinner fa-spin fa-2x"></i><br><span class="mtop10 inline-block">Loading...</span>');
    $('#report-content').hide();
    _activeXhr = $.post(ADMIN_URL + 'pos/ajax_report_data', {
        section:      REPORT_SECTION,
        date_from:    from,
        date_to:      to,
        warehouse_id: $('#warehouse-filter').val() || '',
        group_by:     $('#group-by').val() || 'daily'
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

function onPeriodBtn(el) {
    document.querySelectorAll('.period-btn').forEach(function(b){ b.classList.remove('active'); });
    el.classList.add('active');
    var d = getPeriodDates(el.getAttribute('data-period'));
    loadReport(d.from, d.to);
}
function applyCustom() {
    var from = $('#custom-from').val(), to = $('#custom-to').val();
    if (!from || !to) return;
    $('.period-btn').removeClass('active');
    loadReport(from, to);
}
function onWarehouseChange() {
    if (!_ready) return;
    _reloadCurrent();
}
function onGroupByChange() {
    if (!_ready) return;
    _reloadCurrent();
}
function onProductFilterChange() {
    if (!_ready) return;
    _reloadCurrent();
}
function clearProductFilters() {
    var $cat = $('#product-category-filter');
    if ($cat.length) {
        $cat.val('');
        if (typeof $.fn.selectpicker !== 'undefined') { $cat.selectpicker('val', ''); $cat.selectpicker('refresh'); }
    }
    var $ps = $('#product-search-filter');
    if ($ps.length) $ps.val('');
    onProductFilterChange();
}
function _reloadCurrent() {
    var active = document.querySelector('.period-btn.active');
    if (active) {
        var d = getPeriodDates(active.getAttribute('data-period'));
        loadReport(d.from, d.to);
    } else {
        loadReport($('#custom-from').val(), $('#custom-to').val());
    }
}

// ── Export ────────────────────────────────────────────────────────────────────
function doExportCSV() {
    if (typeof getCSVData === 'function') {
        var d = getCSVData();
        _exportCSV(d.rows, d.filename, d.cols);
    }
}
function doExportPDF() {
    var active = document.querySelector('.period-btn.active');
    var from, to;
    if (active) {
        var d = getPeriodDates(active.getAttribute('data-period'));
        from = d.from; to = d.to;
    } else {
        from = $('#custom-from').val(); to = $('#custom-to').val();
    }
    var wh = $('#warehouse-filter option:selected').text();
    if (!wh || wh === 'All Warehouses') wh = 'All Warehouses';
    document.getElementById('print-title').textContent = document.title;
    document.getElementById('print-meta').textContent  =
        'Period: ' + from + ' to ' + to +
        ' | Grouped: ' + (GROUP_BY_LABEL[$('#group-by').val()] || 'Daily') +
        ' | Outlet: ' + wh +
        ' | Generated: ' + new Date().toLocaleDateString('en-MY');
    window.print();
}
function _exportCSV(rows, filename, cols) {
    var header = cols.map(function(c){ return _csvQ(c.label); }).join(',');
    var body   = rows.map(function(r){
        return cols.map(function(c){ return _csvQ(r[c.key] !== undefined ? r[c.key] : ''); }).join(',');
    }).join('\n');
    var blob = new Blob([header + '\n' + body], { type: 'text/csv;charset=utf-8;' });
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a'); a.href = url; a.download = filename; a.style.display = 'none';
    document.body.appendChild(a); a.click();
    setTimeout(function(){ URL.revokeObjectURL(url); a.remove(); }, 200);
}
function _csvQ(v) {
    v = String(v === null || v === undefined ? '' : v);
    return /[,"\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v;
}

// ── Init ──────────────────────────────────────────────────────────────────────
window.addEventListener('load', function() {
    if (typeof $.fn.datetimepicker !== 'undefined') {
        $('#custom-from, #custom-to').datetimepicker({ format: 'Y-m-d', timepicker: false, scrollMonth: false });
    }
    var d = getPeriodDates('today');
    loadReport(d.from, d.to);
    setTimeout(function() { _ready = true; }, 500);
});
</script>
