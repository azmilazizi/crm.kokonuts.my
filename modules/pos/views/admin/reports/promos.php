<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<style>
.promo-sort-icon { margin-left: 4px; opacity: 0.4; font-size: 10px; }
.promo-sort-icon.active { opacity: 1; color: #337ab7; }
th.promo-sort:hover { background: #f5f5f5; cursor: pointer; }
.promo-type-badge-promo  { background: #ffeeba; color: #856404; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.promo-type-badge-bundle { background: #cce5ff; color: #004085; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.promo-type-badge-set    { background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; white-space: nowrap; }
#promo-chart-wrap canvas { max-height: 220px; }
</style>

<script>
var _lastData      = null;
var _promoRows     = [];
var _sortCol       = 'gross_revenue';
var _sortDir       = 'desc';
var _filterType    = '';
var _filterSearch  = '';
var _promoChart    = null;
var _trendChart    = null;

function kpiCard(cls, label, value) {
    return '<div class="col-md-3 col-xs-6"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

function _doSort(arr, col, dir) {
    return arr.slice().sort(function(a, b) {
        var av = isNaN(parseFloat(a[col])) ? (a[col]||'').toLowerCase() : parseFloat(a[col]||0);
        var bv = isNaN(parseFloat(b[col])) ? (b[col]||'').toLowerCase() : parseFloat(b[col]||0);
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ?  1 : -1;
        return 0;
    });
}

function _sortIcon(col) {
    if (col !== _sortCol) return '<span class="promo-sort-icon fa fa-sort"></span>';
    return _sortDir === 'asc'
        ? '<span class="promo-sort-icon active fa fa-sort-asc"></span>'
        : '<span class="promo-sort-icon active fa fa-sort-desc"></span>';
}

function sortPromos(col) {
    _sortDir = (_sortCol === col && _sortDir === 'desc') ? 'asc' : 'desc';
    _sortCol = col;
    _applyFilters();
}

function onFilterChange() {
    _filterType   = document.getElementById('promo-filter-type')   ? document.getElementById('promo-filter-type').value   : '';
    _filterSearch = document.getElementById('promo-filter-search') ? document.getElementById('promo-filter-search').value.toLowerCase() : '';
    _applyFilters();
}

function clearFilters() {
    var t = document.getElementById('promo-filter-type');
    var s = document.getElementById('promo-filter-search');
    if (t) t.value = '';
    if (s) s.value = '';
    _filterType = ''; _filterSearch = '';
    _applyFilters();
}

function _applyFilters() {
    var filtered = _promoRows.filter(function(r) {
        if (_filterType   && r.promo_type !== _filterType) return false;
        if (_filterSearch && (r.promo_name + ' ' + r.item_name).toLowerCase().indexOf(_filterSearch) === -1) return false;
        return true;
    });
    _renderTable(filtered);
    _renderBarChart(filtered);
    var cnt = document.getElementById('promo-row-count');
    if (cnt) cnt.textContent = filtered.length + ' of ' + _promoRows.length + ' item' + (_promoRows.length !== 1 ? 's' : '');
}

function _renderBarChart(rows) {
    var canvas = document.getElementById('promo-bar-chart');
    if (!canvas) return;
    if (_promoChart) { _promoChart.destroy(); _promoChart = null; }
    var top = _doSort(rows, 'gross_revenue', 'desc').slice(0, 15);
    if (!top.length) return;
    var labels     = top.map(function(r){ return r.promo_name; });
    var rev        = top.map(function(r){ return parseFloat(r.gross_revenue||0); });
    var alacarte   = top.map(function(r){
        var lo = parseFloat(r.alacarte_min||0), hi = parseFloat(r.alacarte_max||r.alacarte_value||0);
        return ((lo + hi) / 2) * parseFloat(r.units_sold||0);
    });
    var h = Math.max(180, top.length * 44);
    canvas.parentNode.style.height = h + 'px';
    canvas.style.height = h + 'px';
    _promoChart = new Chart(canvas.getContext('2d'), {
        type: 'horizontalBar',
        data: { labels: labels, datasets: [
            { label: 'Net Revenue (RM)',          data: rev,      backgroundColor: 'rgba(51,122,183,0.75)', borderColor: '#337ab7', borderWidth: 1 },
            { label: 'Ala-Carte Value Sold (RM)', data: alacarte, backgroundColor: 'rgba(240,173,78,0.55)', borderColor: '#f0ad4e', borderWidth: 1 }
        ]},
        options: {
            responsive: true, maintainAspectRatio: false,
            animation: { duration: rows.length > 60 ? 0 : 300 },
            legend: { position: 'top' },
            scales: {
                xAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); }, fontSize: 11 }, beginAtZero: true }],
                yAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }]
            },
            tooltips: { mode: 'index', intersect: false,
                callbacks: { label: function(item, data) { return ' ' + data.datasets[item.datasetIndex].label + ': RM ' + parseFloat(item.xLabel||0).toFixed(2); } }
            }
        }
    });
}

function _renderTrendChart(trend) {
    var canvas = document.getElementById('promo-trend-chart');
    if (!canvas) return;
    if (_trendChart) { _trendChart.destroy(); _trendChart = null; }
    if (!trend || !trend.length) return;

    var labels = [], seriesMap = {}, seriesOrder = [];
    trend.forEach(function(d) {
        if (labels.indexOf(d.label) === -1) labels.push(d.label);
        if (!seriesMap[d.promo_name]) { seriesMap[d.promo_name] = {}; seriesOrder.push(d.promo_name); }
        seriesMap[d.promo_name][d.label] = parseFloat(d.revenue||0);
    });
    var colors = ['#337ab7','#5cb85c','#f0ad4e','#d9534f','#9b59b6','#1abc9c','#e67e22'];
    var datasets = seriesOrder.slice(0, 7).map(function(name, i) {
        return {
            label: name,
            data: labels.map(function(lbl){ return seriesMap[name][lbl] || 0; }),
            borderColor: colors[i % colors.length],
            backgroundColor: 'transparent',
            borderWidth: 2, pointRadius: labels.length > 20 ? 0 : 3, fill: false
        };
    });

    _trendChart = new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true, animation: { duration: labels.length > 60 ? 0 : 400 },
            legend: { position: 'top' },
            scales: {
                xAxes: [{ ticks: { fontSize: 11 } }],
                yAxes: [{ ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); }, fontSize: 11 }, beginAtZero: true }]
            },
            tooltips: { mode: 'index', intersect: false }
        }
    });
}

function _renderTable(rows) {
    var sorted = _doSort(rows, _sortCol, _sortDir);
    var wrap   = document.getElementById('promo-table-wrap');
    if (!wrap) return;
    if (!sorted.length) {
        wrap.innerHTML = '<p class="text-muted text-center small" style="padding:20px 0;">No promo or bundle data for this period.</p>';
        return;
    }

    var totalGross = sorted.reduce(function(a,r){ return a + parseFloat(r.gross_revenue||0); }, 0);

    var cols = [
        { key: 'promo_name',     label: 'Name' },
        { key: 'promo_type',     label: 'Type',           cls: 'text-center' },
        { key: 'item_name',      label: 'Linked Product' },
        { key: 'order_count',    label: 'Orders',          cls: 'text-right' },
        { key: 'units_sold',     label: 'Units',           cls: 'text-right' },
        { key: 'selling_price',  label: 'Sell Price',      cls: 'text-right' },
        { key: 'alacarte_value', label: 'Ala-Carte Value', cls: 'text-right' },
        { key: 'savings_per_use',label: 'Saving/Sale',     cls: 'text-right' },
        { key: 'savings_pct',    label: 'Saving %',        cls: 'text-right' },
        { key: 'net_revenue',    label: 'Net Revenue',     cls: 'text-right' },
        { key: '_status',        label: 'Status',          cls: 'text-center' },
    ];

    var thead = '<thead><tr>' + cols.map(function(c) {
        var cls = (c.cls||'') + ' promo-sort';
        return '<th class="' + cls + '" onclick="sortPromos(\'' + c.key + '\')" style="white-space:nowrap;">' + c.label + _sortIcon(c.key) + '</th>';
    }).join('') + '<th></th></tr></thead>';

    var tbody = '<tbody>' + sorted.map(function(r) {
        var typeBadge = r.promo_type === 'bundle'
            ? '<span class="promo-type-badge-bundle">Bundle</span>'
            : r.promo_type === 'set'
            ? '<span class="promo-type-badge-set">Set</span>'
            : '<span class="promo-type-badge-promo">Discount</span>';
        var sp    = parseFloat(r.selling_price  || 0);
        var avMin = parseFloat(r.alacarte_min  || 0);
        var avMax = parseFloat(r.alacarte_max  || r.alacarte_value || 0);
        var pct   = parseFloat(r.savings_pct   || 0);
        var hasAv = avMax > 0;
        var avDisplay = hasAv
            ? (avMin < avMax ? 'RM ' + fmt2(avMin) + '~' + fmt2(avMax) : 'RM ' + fmt2(avMax))
            : '<span class="text-muted">—</span>';
        var statusCls = pct >= 35 ? 'label-danger' : (pct >= 20 ? 'label-warning' : (hasAv ? 'label-success' : 'label-default'));
        var statusLbl = pct >= 35 ? 'High Risk'    : (pct >= 20 ? 'Watch'         : (hasAv ? 'OK'            : 'No Data'));
        var barStyle  = 'display:inline-block;width:' + Math.min(100,pct).toFixed(0) + 'px;height:6px;border-radius:3px;background:'
            + (pct >= 35 ? '#d9534f' : pct >= 20 ? '#f0ad4e' : '#5cb85c') + ';vertical-align:middle;margin-right:4px;';
        return '<tr>'
            + '<td><strong>' + htmlEnc(r.promo_name) + '</strong></td>'
            + '<td class="text-center">' + typeBadge + '</td>'
            + '<td>' + (r.item_name ? htmlEnc(r.item_name) + (r.item_code ? '<br><small class="text-muted">' + htmlEnc(r.item_code) + '</small>' : '') : '<span class="text-muted">—</span>') + '</td>'
            + '<td class="text-right"><strong>' + fmtInt(r.order_count) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(r.units_sold) + '</td>'
            + '<td class="text-right">RM ' + fmt2(sp) + '</td>'
            + '<td class="text-right">' + avDisplay + '</td>'
            + '<td class="text-right text-success">' + (hasAv ? '<strong>RM ' + fmt2(r.savings_per_use) + '</strong>' : '—') + '</td>'
            + '<td class="text-right">'
            +   (hasAv ? '<span style="' + barStyle + '"></span>' + pct + '%' : '<span class="text-muted small">No components</span>')
            + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(r.net_revenue) + '</strong></td>'
            + '<td class="text-center"><span class="label ' + statusCls + '">' + statusLbl + '</span></td>'
            + '<td><a href="#" onclick="openPromoDetail(' + r.promo_id + ',\'' + r.promo_name.replace(/'/g,"\\'") + '\');return false;" class="text-muted small" title="Detail"><i class="fa fa-bar-chart"></i></a></td>'
            + '</tr>';
    }).join('') + '</tbody>';

    var tfoot = '<tfoot class="rpt-total"><tr>'
        + '<td colspan="3"><strong>Total</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a+parseInt(r.order_count||0); },0)) + '</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a+parseFloat(r.units_sold||0); },0)) + '</strong></td>'
        + '<td colspan="4"></td>'
        + '<td class="text-right"><strong>RM ' + fmt2(sorted.reduce(function(a,r){ return a+parseFloat(r.net_revenue||0); },0)) + '</strong></td>'
        + '<td colspan="2"></td>'
        + '</tr></tfoot>';

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin">' + thead + tbody + tfoot + '</table>';
    scrollTable(wrap, sorted.length);
}

function renderReport(r) {
    _lastData    = r;
    var s        = r.summary || {};
    var detail   = r.detail  || [];
    var trend    = r.trend   || [];
    _promoRows   = detail;
    _filterType  = ''; _filterSearch = '';

    var el = document.getElementById('report-content');

    var hasDefined = detail.length > 0;
    var noDataMsg  = !hasDefined
        ? '<div class="alert alert-info"><i class="fa fa-info-circle"></i> No promos or bundles are defined yet. <a href="' + ADMIN_URL + 'pos/products">Go to Products</a> and flag items as Promo/Bundle to set them up.</div>'
        : '';

    el.innerHTML = noDataMsg
        // KPIs
        + '<div class="row">'
        + kpiCard('blue',   'Promos Defined',     fmtInt(s.total_promos_defined))
        + kpiCard('green',  'With Sales',          fmtInt(s.promos_with_sales))
        + kpiCard('orange', 'Total Revenue',       'RM ' + fmt2(s.total_revenue))
        + kpiCard('red',    'Discount Given',      'RM ' + fmt2(s.total_discount_given))
        + '</div>'

        // Trend chart
        + (trend.length
            ? '<div class="panel_s"><div class="panel-body">'
              + '<h5 class="no-margin-top bold">Revenue by Promo/Bundle Over Time</h5>'
              + '<div id="promo-chart-wrap"><canvas id="promo-trend-chart" height="70"></canvas></div>'
              + '</div></div>'
            : '')

        // Bar chart
        + (hasDefined
            ? '<div class="panel_s"><div class="panel-body">'
              + '<h5 class="no-margin-top bold">Revenue & Discount — Top 15 <span class="text-muted" style="font-weight:400;font-size:12px;">by gross revenue</span></h5>'
              + '<div id="promo-chart-wrap"><canvas id="promo-bar-chart" height="80"></canvas></div>'
              + '</div></div>'
            : '')

        // Table
        + '<div class="panel_s"><div class="panel-body">'
        + '<div class="clearfix">'
        + '<h5 class="no-margin-top bold pull-left">Promos & Bundles <span id="promo-row-count" class="rpt-row-count"></span></h5>'
        + '<a href="' + ADMIN_URL + 'pos/products" class="btn btn-default btn-xs pull-right no-print"><i class="fa fa-cog"></i> Manage</a>'
        + '</div>'
        + '<div class="mod-filter-bar" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;padding:10px 12px;background:#f9f9f9;border:1px solid #e8e8e8;border-radius:4px;">'
        + '<select id="promo-filter-type" class="form-control input-sm" style="width:140px;" onchange="onFilterChange()"><option value="">All Types</option><option value="promo">Discount</option><option value="bundle">Bundle</option><option value="set">Set</option></select>'
        + '<div class="input-group input-group-sm" style="flex:1;min-width:160px;max-width:260px;">'
        + '<span class="input-group-addon"><i class="fa fa-search"></i></span>'
        + '<input type="text" id="promo-filter-search" class="form-control" placeholder="Search name or product…" oninput="onFilterChange()">'
        + '</div>'
        + '<button class="btn btn-default btn-sm" onclick="clearFilters()" title="Clear"><i class="fa fa-times"></i></button>'
        + '</div>'
        + '<div id="promo-table-wrap" style="overflow-x:auto;"></div>'
        + '</div></div>'

        // Manage link
        + (!hasDefined
            ? ''
            : '<div class="text-muted small" style="padding:4px 0 10px;"><i class="fa fa-info-circle"></i> To add or edit promo definitions, flag items as Promo/Bundle from <a href="' + ADMIN_URL + 'pos/products">Products</a>.</div>');

    _applyFilters();
    _renderBarChart(detail);
    _renderTrendChart(trend);
}

function getCSVData() {
    var active = document.querySelector('.period-btn.active');
    var from = active ? getPeriodDates(active.getAttribute('data-period')).from : ($('#custom-from').val() || '');
    var to   = active ? getPeriodDates(active.getAttribute('data-period')).to   : ($('#custom-to').val() || '');
    var sorted = _doSort(_promoRows.filter(function(r) {
        if (_filterType   && r.promo_type !== _filterType) return false;
        if (_filterSearch && (r.promo_name + ' ' + r.item_name).toLowerCase().indexOf(_filterSearch) === -1) return false;
        return true;
    }), _sortCol, _sortDir);
    return {
        filename: 'promos-bundles_' + (from||'') + '_' + (to||'') + '.csv',
        cols: [
            { key: 'promo_name',     label: 'Name' },
            { key: 'promo_type',     label: 'Type' },
            { key: 'item_name',      label: 'Linked Product' },
            { key: 'item_code',      label: 'SKU Code' },
            { key: 'order_count',    label: 'Orders' },
            { key: 'units_sold',     label: 'Units Sold' },
            { key: 'gross_revenue',  label: 'Gross Revenue (RM)' },
            { key: 'total_discount', label: 'Discount Given (RM)' },
            { key: 'net_revenue',    label: 'Net Revenue (RM)' },
        ],
        rows: sorted
    };
}

// ── Promo Detail Modal ──────────────────────────────────────────────────────
function openPromoDetail(promoId, promoName) {
    document.getElementById('promo-detail-title').textContent = promoName;
    document.getElementById('promo-detail-body').innerHTML =
        '<div class="text-center" style="padding:40px;"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>';
    $('#promo-detail-modal').modal('show');
    var active = document.querySelector('.period-btn.active');
    var from, to;
    if (active) { var d = getPeriodDates(active.getAttribute('data-period')); from = d.from; to = d.to; }
    else { from = $('#custom-from').val()||''; to = $('#custom-to').val()||''; }
    $.post(ADMIN_URL + 'pos/ajax_report_data', {
        section: 'promo_detail', promo_id: promoId,
        date_from: from, date_to: to,
        warehouse_id: $('#warehouse-filter').val() || ''
    }).done(function(resp) {
        if (typeof resp === 'string') { try { resp = JSON.parse(resp); } catch(e){} }
        if (!resp || !resp.success) {
            document.getElementById('promo-detail-body').innerHTML = '<p class="text-danger text-center">' + ((resp && resp.error) || 'Failed to load') + '</p>';
            return;
        }
        _renderPromoDetail(resp, from, to);
    }).fail(function() {
        document.getElementById('promo-detail-body').innerHTML = '<p class="text-danger text-center">Request failed.</p>';
    });
}

function _renderPromoDetail(resp, from, to) {
    var promo  = resp.promo      || {};
    var txns   = resp.transactions || [];
    var comps  = resp.components   || [];

    var totalRev = txns.reduce(function(a,r){ return a+parseFloat(r.total_money||0); }, 0);
    var totalQty = txns.reduce(function(a,r){ return a+parseFloat(r.quantity||0); }, 0);
    var totalDisc= txns.reduce(function(a,r){ return a+parseFloat(r.total_discount||0); }, 0);

    var kpis = '<div class="row" style="margin-bottom:12px;">'
        + '<div class="col-xs-3"><div class="panel_s kpi-card blue"><div class="panel-body"><div class="kpi-label">Orders</div><div class="kpi-value" style="font-size:20px;">' + fmtInt(new Set(txns.map(function(t){ return t.receipt_number; })).size) + '</div></div></div></div>'
        + '<div class="col-xs-3"><div class="panel_s kpi-card green"><div class="panel-body"><div class="kpi-label">Units Sold</div><div class="kpi-value" style="font-size:20px;">' + fmtInt(totalQty) + '</div></div></div></div>'
        + '<div class="col-xs-3"><div class="panel_s kpi-card orange"><div class="panel-body"><div class="kpi-label">Gross Revenue</div><div class="kpi-value" style="font-size:20px;">RM ' + fmt2(totalRev) + '</div></div></div></div>'
        + '<div class="col-xs-3"><div class="panel_s kpi-card red"><div class="panel-body"><div class="kpi-label">Discount</div><div class="kpi-value" style="font-size:20px;">RM ' + fmt2(totalDisc) + '</div></div></div></div>'
        + '</div>';

    var compSection = '';
    var bundleGroups = resp.bundle_groups || [];
    var sellingPrice = parseFloat(promo.selling_price || 0);

    if (comps.length) {
        var alaCarteTotal = 0;
        var compRows = comps.map(function(c) {
            var typeBadge = c.component_type === 'modifier'
                ? '<span class="label label-default">Modifier</span>'
                : '<span class="label label-primary">Product</span>';
            var qty      = parseFloat(c.quantity || 1);
            var rate     = parseFloat(c.unit_rate || 0);
            var lineVal  = qty * rate;
            alaCarteTotal += lineVal;
            return '<tr><td>' + typeBadge + '</td>'
                + '<td><strong>' + htmlEnc(c.display_name || c.component_name) + '</strong></td>'
                + '<td class="text-right">' + qty + '</td>'
                + '<td class="text-right">' + (rate > 0 ? 'RM ' + fmt2(rate) : '<span class="text-muted">—</span>') + '</td>'
                + '<td class="text-right">' + (lineVal > 0 ? 'RM ' + fmt2(lineVal) : '—') + '</td>'
                + '<td class="text-muted small">' + (c.notes||'—') + '</td></tr>';
        }).join('');
        var saving    = Math.max(0, alaCarteTotal - sellingPrice);
        var savingPct = alaCarteTotal > 0 ? (saving / alaCarteTotal * 100).toFixed(1) : 0;
        var feasRow   = (alaCarteTotal > 0 && sellingPrice > 0)
            ? '<tr class="' + (savingPct >= 35 ? 'danger' : savingPct >= 20 ? 'warning' : 'success') + '">'
              + '<td colspan="4" class="text-right"><strong>Customer saves per sale</strong></td>'
              + '<td class="text-right"><strong>RM ' + fmt2(saving) + ' (' + savingPct + '% off)</strong></td>'
              + '<td></td></tr>'
            : '';
        compSection = '<h5 class="bold" style="margin-top:16px;">Components &amp; Ala-Carte Value</h5>'
            + '<table class="table table-condensed table-bordered no-margin" style="font-size:12px;margin-bottom:12px;">'
            + '<thead><tr><th>Type</th><th>Name</th><th class="text-right">Qty</th><th class="text-right">Unit Rate</th><th class="text-right">Ala-Carte</th><th>Notes</th></tr></thead>'
            + '<tbody>' + compRows + '</tbody>'
            + '<tfoot><tr class="active"><td colspan="4" class="text-right"><strong>Ala-Carte Total</strong></td>'
            +   '<td class="text-right"><strong>RM ' + fmt2(alaCarteTotal) + '</strong></td><td></td></tr>'
            + '<tr><td colspan="4" class="text-right">Selling Price</td>'
            +   '<td class="text-right">RM ' + fmt2(sellingPrice) + '</td><td></td></tr>'
            + feasRow
            + '</tfoot></table>';
    } else if (bundleGroups.length) {
        // Bundle type — show groups with options and avg pricing
        var bundleTotal = 0;
        var groupRows = bundleGroups.map(function(g) {
            bundleTotal += g.avg_price;
            var optNames = g.options.slice(0,4).map(function(o){ return htmlEnc(o.option_name); }).join(', ')
                + (g.options.length > 4 ? ', …' : '');
            return '<tr>'
                + '<td><strong>' + htmlEnc(g.name || 'Group') + '</strong></td>'
                + '<td><small class="text-muted">' + optNames + '</small></td>'
                + '<td class="text-right">' + g.options.length + '</td>'
                + '<td class="text-right">RM ' + fmt2(g.min_price) + '</td>'
                + '<td class="text-right">RM ' + fmt2(g.avg_price) + '</td>'
                + '</tr>';
        }).join('');
        var bSaving    = Math.max(0, bundleTotal - sellingPrice);
        var bSavingPct = bundleTotal > 0 ? (bSaving / bundleTotal * 100).toFixed(1) : 0;
        compSection = '<h5 class="bold" style="margin-top:16px;">Bundle Groups &amp; Ala-Carte Value <small class="text-muted">(avg option price per slot)</small></h5>'
            + '<table class="table table-condensed table-bordered no-margin" style="font-size:12px;margin-bottom:12px;">'
            + '<thead><tr><th>Slot</th><th>Options</th><th class="text-right"># Options</th><th class="text-right">Min Price</th><th class="text-right">Avg Price</th></tr></thead>'
            + '<tbody>' + groupRows + '</tbody>'
            + '<tfoot><tr class="active"><td colspan="4" class="text-right"><strong>Estimated Ala-Carte (avg)</strong></td>'
            +   '<td class="text-right"><strong>RM ' + fmt2(bundleTotal) + '</strong></td></tr>'
            + '<tr><td colspan="4" class="text-right">Bundle Price</td>'
            +   '<td class="text-right">RM ' + fmt2(sellingPrice) + '</td></tr>'
            + (bundleTotal > 0 && sellingPrice > 0
                ? '<tr class="' + (bSavingPct >= 35 ? 'danger' : bSavingPct >= 20 ? 'warning' : 'success') + '">'
                  + '<td colspan="4" class="text-right"><strong>Customer saves per sale</strong></td>'
                  + '<td class="text-right"><strong>RM ' + fmt2(bSaving) + ' (' + bSavingPct + '% off)</strong></td></tr>'
                : '')
            + '</tfoot></table>';
    }

    var txnTable = txns.length
        ? '<div style="max-height:250px;overflow-y:auto;"><table class="table table-condensed table-bordered no-margin" style="font-size:12px;">'
          + '<thead><tr><th>Receipt</th><th>Date</th><th>Outlet</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Discount</th><th class="text-right">Total</th></tr></thead>'
          + '<tbody>' + txns.map(function(t) {
              return '<tr>'
                  + '<td><a href="' + ADMIN_URL + 'pos/transaction/' + encodeURIComponent(t.receipt_number) + '" target="_blank">' + htmlEnc(t.receipt_number) + ' <i class="fa fa-external-link fa-xs"></i></a></td>'
                  + '<td style="white-space:nowrap;">' + htmlEnc(t.receipt_date) + '</td>'
                  + '<td>' + htmlEnc(t.warehouse_name||'—') + '</td>'
                  + '<td class="text-right">' + fmtInt(t.quantity) + '</td>'
                  + '<td class="text-right">RM ' + fmt2(t.unit_price) + '</td>'
                  + '<td class="text-right text-danger">' + (parseFloat(t.total_discount||0) > 0 ? 'RM ' + fmt2(t.total_discount) : '—') + '</td>'
                  + '<td class="text-right"><strong>RM ' + fmt2(t.total_money) + '</strong></td>'
                  + '</tr>';
          }).join('') + '</tbody>'
          + '</table></div>'
        : '<p class="text-muted text-center" style="padding:20px 0;">No transactions found for this period.</p>';

    document.getElementById('promo-detail-body').innerHTML =
        '<p class="text-muted" style="margin-bottom:10px;font-size:12px;"><i class="fa fa-calendar-o"></i> ' + htmlEnc(from) + ' — ' + htmlEnc(to) + '</p>'
        + kpis
        + compSection
        + '<h5 class="bold" style="margin-top:8px;">Transactions (last 100)</h5>'
        + txnTable;
}
</script>

<!-- Promo Detail Modal -->
<div class="modal fade" id="promo-detail-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document" style="width:90%;max-width:1100px;">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="promo-detail-title"></h4>
      </div>
      <div class="modal-body" id="promo-detail-body" style="padding:20px;"></div>
    </div>
  </div>
</div>

<?php init_tail(); ?>
