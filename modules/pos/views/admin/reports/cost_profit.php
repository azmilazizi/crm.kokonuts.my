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
var _tabMode    = 'by_product';

var _byProdRows = [], _catalogRows = [], _lowMarginRows = [];
var _sortColBP  = 'gross_profit', _sortDirBP  = 'desc';
var _sortColCC  = 'margin_pct',   _sortDirCC  = 'asc';
var _sortColLM  = 'margin_pct',   _sortDirLM  = 'asc';

function setTabMode(mode) {
    _tabMode = mode;
    document.querySelectorAll('.cp-tab-btn').forEach(function(b) {
        b.classList.toggle('active', b.getAttribute('data-mode') === mode);
    });
    document.getElementById('cp-by-product').style.display = mode === 'by_product' ? '' : 'none';
    document.getElementById('cp-catalog').style.display    = mode === 'catalog'    ? '' : 'none';
    document.getElementById('cp-low-margin').style.display = mode === 'low_margin' ? '' : 'none';
}

function _doSort(arr, col, dir) {
    return arr.slice().sort(function(a, b) {
        var av, bv;
        if (!isNaN(parseFloat(a[col]))) {
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

function sortBP(col) {
    if (_sortColBP === col) {
        _sortDirBP = _sortDirBP === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColBP = col;
        _sortDirBP = (col === 'item_name' || col === 'category_name') ? 'asc' : 'desc';
    }
    _renderByProductTable(_byProdRows);
}

function sortCC(col) {
    if (_sortColCC === col) {
        _sortDirCC = _sortDirCC === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColCC = col;
        _sortDirCC = (col === 'sku_name' || col === 'sku_code' || col === 'item_type') ? 'asc' : 'desc';
    }
    _renderCatalogTable(_catalogRows, 'cc-table-wrap', 'cc-table');
}

function sortLM(col) {
    if (_sortColLM === col) {
        _sortDirLM = _sortDirLM === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColLM = col;
        _sortDirLM = (col === 'sku_name' || col === 'sku_code' || col === 'item_type') ? 'asc' : 'desc';
    }
    _renderCatalogTable(_lowMarginRows, 'lm-table-wrap', 'lm-table');
}

function _renderTrend(r) {
    if (_trendChart) { _trendChart.destroy(); _trendChart = null; }
    var gb    = r.group_by || 'daily';
    var gbLbl = GROUP_BY_LABEL[gb] || 'Daily';
    var canvas = document.getElementById('cp-chart-trend');
    if (!canvas) return;

    var trendAll = r.product_trend_all || [];
    if (!trendAll.length) return;

    var labelMap = {};
    trendAll.forEach(function(d) {
        if (!labelMap[d.label]) {
            labelMap[d.label] = { label: d.label, total_revenue: 0, total_cost: 0 };
        }
        labelMap[d.label].total_revenue += parseFloat(d.total_revenue || d.net_revenue || 0);
        labelMap[d.label].total_cost    += parseFloat(d.total_cost || 0);
    });
    var trend = Object.keys(labelMap).map(function(k){ return labelMap[k]; });
    trend.sort(function(a,b){ return a.label.localeCompare(b.label); });

    var labels = trend.map(function(d){ return d.label; });
    var revData = trend.map(function(d){ return d.total_revenue; });
    var costData = trend.map(function(d){ return d.total_cost; });

    var isBar = (gb === 'hourly' || gb === 'hourly_by_day' || gb === 'dow');

    _trendChart = new Chart(canvas.getContext('2d'), {
        type: isBar ? 'bar' : 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Revenue',
                    data: revData,
                    backgroundColor: '#5cb85c',
                    borderColor: '#5cb85c',
                    borderWidth: isBar ? 1 : 2,
                    fill: !isBar,
                    pointRadius: !isBar && labels.length > 14 ? 0 : 3
                },
                {
                    label: 'Cost',
                    data: costData,
                    backgroundColor: '#f0ad4e',
                    borderColor: '#f0ad4e',
                    borderWidth: isBar ? 1 : 2,
                    fill: !isBar,
                    pointRadius: !isBar && labels.length > 14 ? 0 : 3
                }
            ]
        },
        options: {
            animation: animOpts(labels.length), responsive: true,
            scales: {
                xAxes: [{ stacked: false, ticks: { fontSize: 11, maxRotation: 60 }, gridLines: { display: false } }],
                yAxes: [{ stacked: false, ticks: { callback: function(v){ return 'RM '+v.toLocaleString(); } } }]
            },
            tooltips: {
                mode: 'index', intersect: false,
                callbacks: {
                    label: function(item, data) {
                        var v = parseFloat(data.datasets[item.datasetIndex].data[item.index] || 0);
                        return ' ' + data.datasets[item.datasetIndex].label + ': RM ' + fmt2(v);
                    }
                }
            }
        }
    });
}

function _renderByProductTable(rows) {
    var sorted = _doSort(rows, _sortColBP, _sortDirBP);
    var ths    = 'style="cursor:pointer;white-space:nowrap;user-select:none;"';
    var wrap   = document.getElementById('bp-table-wrap');
    if (!wrap) return;

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin" id="bp-table">'
        + '<thead><tr>'
        + '<th ' + ths + ' onclick="sortBP(\'item_name\')">Product' + _sortIcon('item_name', _sortColBP, _sortDirBP) + '</th>'
        + '<th ' + ths + ' onclick="sortBP(\'category_name\')">Category' + _sortIcon('category_name', _sortColBP, _sortDirBP) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortBP(\'qty_sold\')">Qty Sold' + _sortIcon('qty_sold', _sortColBP, _sortDirBP) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortBP(\'total_revenue\')">Total Revenue' + _sortIcon('total_revenue', _sortColBP, _sortDirBP) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortBP(\'total_cost\')">Total Cost' + _sortIcon('total_cost', _sortColBP, _sortDirBP) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortBP(\'gross_profit\')">Gross Profit' + _sortIcon('gross_profit', _sortColBP, _sortDirBP) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortBP(\'margin_pct\')">Margin %' + _sortIcon('margin_pct', _sortColBP, _sortDirBP) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortBP(\'avg_unit_price\')">Avg Unit Price' + _sortIcon('avg_unit_price', _sortColBP, _sortDirBP) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="sortBP(\'avg_unit_cost\')">Avg Unit Cost' + _sortIcon('avg_unit_cost', _sortColBP, _sortDirBP) + '</th>'
        + '</tr></thead>'
        + '<tbody>'
        + (sorted.length ? sorted.map(function(p) {
            var qty = parseFloat(p.qty_sold || 0);
            var rev = parseFloat(p.total_revenue || 0);
            var cost = parseFloat(p.total_cost || 0);
            var gp = rev - cost;
            var margin = rev > 0 ? (gp / rev * 100) : 0;
            var avgPrice = qty > 0 ? rev / qty : 0;
            var avgCost = qty > 0 ? cost / qty : 0;
            var marginCls = margin < 30 ? 'text-danger' : (margin < 35 ? 'text-warning' : '');
            return '<tr>'
                + '<td>' + htmlEnc(p.item_name || '') + '</td>'
                + '<td><small class="text-muted">' + htmlEnc(p.category_name || '') + '</small></td>'
                + '<td class="text-right">' + fmtInt(qty) + '</td>'
                + '<td class="text-right">RM ' + fmt2(rev) + '</td>'
                + '<td class="text-right">RM ' + fmt2(cost) + '</td>'
                + '<td class="text-right"><strong' + (gp < 0 ? ' class="text-danger"' : '') + '>RM ' + fmt2(gp) + '</strong></td>'
                + '<td class="text-right ' + marginCls + '"><strong>' + margin.toFixed(1) + '%</strong></td>'
                + '<td class="text-right">RM ' + fmt2(avgPrice) + '</td>'
                + '<td class="text-right">RM ' + fmt2(avgCost) + '</td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="9" class="text-muted text-center">No sales data</td></tr>')
        + '</tbody>'
        + '</table>';

    if (sorted.length) {
        var totRev = sorted.reduce(function(a,r){ return a + parseFloat(r.total_revenue||0); }, 0);
        var totCost = sorted.reduce(function(a,r){ return a + parseFloat(r.total_cost||0); }, 0);
        var totGP = totRev - totCost;
        var totMargin = totRev > 0 ? (totGP / totRev * 100) : 0;
        var totQty = sorted.reduce(function(a,r){ return a + parseFloat(r.qty_sold||0); }, 0);
        var avgPrice = totQty > 0 ? totRev / totQty : 0;
        var avgCost = totQty > 0 ? totCost / totQty : 0;
        var marginCls = totMargin < 30 ? 'text-danger' : (totMargin < 35 ? 'text-warning' : '');
        wrap.querySelector('table').insertAdjacentHTML('beforeend',
            '<tfoot class="rpt-total"><tr>'
            + '<td><strong>Total</strong></td>'
            + '<td></td>'
            + '<td class="text-right"><strong>' + fmtInt(totQty) + '</strong></td>'
            + '<td class="text-right"><strong>RM ' + fmt2(totRev) + '</strong></td>'
            + '<td class="text-right"><strong>RM ' + fmt2(totCost) + '</strong></td>'
            + '<td class="text-right"><strong' + (totGP < 0 ? ' class="text-danger"' : '') + '>RM ' + fmt2(totGP) + '</strong></td>'
            + '<td class="text-right ' + marginCls + '"><strong>' + totMargin.toFixed(1) + '%</strong></td>'
            + '<td class="text-right"><strong>RM ' + fmt2(avgPrice) + '</strong></td>'
            + '<td class="text-right"><strong>RM ' + fmt2(avgCost) + '</strong></td>'
            + '</tr></tfoot>'
        );
    }
    scrollTable(wrap, sorted.length, 20);
}

function _renderCatalogTable(rows, wrapId, tableId) {
    var col = wrapId === 'lm-table-wrap' ? _sortColLM : _sortColCC;
    var dir = wrapId === 'lm-table-wrap' ? _sortDirLM : _sortDirCC;
    var sortFn = wrapId === 'lm-table-wrap' ? 'sortLM' : 'sortCC';
    var sorted = _doSort(rows, col, dir);
    var ths    = 'style="cursor:pointer;white-space:nowrap;user-select:none;"';
    var wrap   = document.getElementById(wrapId);
    if (!wrap) return;

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin" id="' + tableId + '">'
        + '<thead><tr>'
        + '<th ' + ths + ' onclick="' + sortFn + '(\'sku_name\')">Product' + _sortIcon('sku_name', col, dir) + '</th>'
        + '<th ' + ths + ' onclick="' + sortFn + '(\'sku_code\')">SKU' + _sortIcon('sku_code', col, dir) + '</th>'
        + '<th ' + ths + ' onclick="' + sortFn + '(\'item_type\')">Type' + _sortIcon('item_type', col, dir) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="' + sortFn + '(\'current_cost_per_unit\')">Current Unit Cost' + _sortIcon('current_cost_per_unit', col, dir) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="' + sortFn + '(\'selling_price\')">Selling Price' + _sortIcon('selling_price', col, dir) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="' + sortFn + '(\'profit_per_unit\')">Profit / Unit' + _sortIcon('profit_per_unit', col, dir) + '</th>'
        + '<th class="text-right" ' + ths + ' onclick="' + sortFn + '(\'margin_pct\')">Margin %' + _sortIcon('margin_pct', col, dir) + '</th>'
        + '</tr></thead>'
        + '<tbody>'
        + (sorted.length ? sorted.map(function(p) {
            var cost = parseFloat(p.current_cost_per_unit || p.purchase_price || 0);
            var price = parseFloat(p.selling_price || 0);
            var profit = price - cost;
            var margin = price > 0 ? (profit / price * 100) : 0;
            var marginCls = margin < 30 ? 'text-danger' : (margin < 35 ? 'text-warning' : '');
            var flag = margin < 30 ? ' <i class="fa fa-exclamation-triangle text-danger" title="Margin below 30%"></i>' : '';
            var typeLabel = '';
            switch(p.item_type) {
                case 'finished_product': typeLabel = 'Finished'; break;
                case 'combo':            typeLabel = 'Combo';    break;
                case 'mixed_ingredient': typeLabel = 'Mixed';    break;
                default:                 typeLabel = p.item_type || '';
            }
            return '<tr>'
                + '<td>' + htmlEnc(p.sku_name || '') + flag + '</td>'
                + '<td><code>' + htmlEnc(p.sku_code || '') + '</code></td>'
                + '<td><span class="label label-default">' + htmlEnc(typeLabel) + '</span></td>'
                + '<td class="text-right">RM ' + fmt2(cost) + '</td>'
                + '<td class="text-right"><strong>RM ' + fmt2(price) + '</strong></td>'
                + '<td class="text-right"' + (profit < 0 ? ' class="text-danger"' : '') + '>RM ' + fmt2(profit) + '</td>'
                + '<td class="text-right ' + marginCls + '"><strong>' + margin.toFixed(1) + '%</strong></td>'
                + '</tr>';
        }).join('') : '<tr><td colspan="7" class="text-muted text-center">No catalog data</td></tr>')
        + '</tbody>'
        + '</table>';

    scrollTable(wrap, sorted.length, 20);
}

function _delta(curr, prev, fmt) {
    var c = parseFloat(curr || 0), p = parseFloat(prev || 0);
    if (c === 0 && p === 0) return '';
    if (p === 0) return c > 0 ? '<span class="kpi-badge up">▲ new</span>' : '';
    var diff = c - p;
    var pct  = diff / Math.abs(p) * 100;
    if (Math.abs(pct) < 0.05) return '<span class="kpi-badge flat">= no change</span>';
    var isUp  = diff > 0;
    var cls   = isUp ? 'up' : 'down';
    var arrow = isUp ? '▲' : '▼';
    var abs   = Math.abs(diff);
    var absStr = fmt === 'rm'  ? 'RM ' + fmt2(abs)
               : fmt === 'int' ? fmtInt(Math.round(abs))
               : fmt === 'pct' ? parseFloat(abs).toFixed(1) + '%'
               :                 parseFloat(abs).toFixed(1);
    return '<span class="kpi-badge ' + cls + '">'
        + arrow + ' ' + Math.abs(pct).toFixed(1) + '%'
        + '<br><span class="kpi-badge-abs">' + absStr + ' ' + (isUp ? 'more' : 'less') + '</span>'
        + '</span>';
}

function _prevLabel(from, to) {
    if (!from) return '';
    try {
        var opts = { month: 'short', day: 'numeric' };
        var f = new Date(from + 'T00:00:00'), t = new Date(to + 'T00:00:00');
        return from === to
            ? f.toLocaleDateString('en-MY', opts)
            : f.toLocaleDateString('en-MY', opts) + ' – ' + t.toLocaleDateString('en-MY', opts);
    } catch(e) { return from === to ? from : from + ' – ' + to; }
}

function kpiCard(cls, label, value, deltaHtml) {
    return '<div class="col-md-2 col-xs-6"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + (deltaHtml ? '<div class="kpi-vs">' + deltaHtml + '</div>' : '')
        + '</div></div></div>';
}

function renderReport(r) {
    _lastData = r;
    var el   = document.getElementById('report-content');
    var gb   = r.group_by || 'daily';

    _byProdRows = (r.by_product || []).map(function(row) {
        var qty = parseFloat(row.qty_sold || 0);
        var rev = parseFloat(row.total_revenue || 0);
        var cost = parseFloat(row.total_cost || 0);
        var gp = rev - cost;
        return Object.assign({}, row, {
            gross_profit: gp,
            margin_pct: rev > 0 ? (gp / rev * 100) : 0,
            avg_unit_price: qty > 0 ? rev / qty : 0,
            avg_unit_cost: qty > 0 ? cost / qty : 0
        });
    });

    _catalogRows = (r.catalog_now || []).map(function(row) {
        var cost = parseFloat(row.current_cost_per_unit || row.purchase_price || 0);
        var price = parseFloat(row.selling_price || 0);
        var profit = price - cost;
        return Object.assign({}, row, {
            profit_per_unit: profit,
            margin_pct: price > 0 ? (profit / price * 100) : 0
        });
    });

    _lowMarginRows = _catalogRows.filter(function(r){ return r.margin_pct < 35; });

    var gt = r.grand_totals || {};
    var prevGT = r.prev_grand_totals || {};

    var totalRev   = parseFloat(gt.total_revenue   || 0);
    var totalCost  = parseFloat(gt.total_cost      || 0);
    var totalGP    = totalRev - totalCost;
    var avgMargin  = totalRev > 0 ? (totalGP / totalRev * 100) : 0;
    var receipts   = parseInt(r.receipt_count || gt.receipt_count || 0);
    var itemsSold  = parseInt(gt.qty_sold || 0);

    var pTotalRev  = parseFloat(prevGT.total_revenue   || 0);
    var pTotalCost = parseFloat(prevGT.total_cost      || 0);
    var pTotalGP   = pTotalRev - pTotalCost;
    var pAvgMargin = pTotalRev > 0 ? (pTotalGP / pTotalRev * 100) : 0;
    var pReceipts  = parseInt(r.prev_receipt_count || prevGT.receipt_count || 0);
    var pItemsSold = parseInt(prevGT.qty_sold || 0);

    var prevLbl = _prevLabel(r.prev_date_from, r.prev_date_to);
    var showTrend = (r.date_from !== r.date_to) || gb === 'hourly' || gb === 'hourly_by_day';

    var tabBtns = '<div class="btn-group btn-group-sm">'
        + '<button class="btn btn-default cp-tab-btn' + (_tabMode === 'by_product' ? ' active' : '') + '" data-mode="by_product" onclick="setTabMode(\'by_product\')"><i class="fa fa-list"></i> By Product (Actual)</button>'
        + '<button class="btn btn-default cp-tab-btn' + (_tabMode === 'catalog' ? ' active' : '') + '" data-mode="catalog" onclick="setTabMode(\'catalog\')"><i class="fa fa-book"></i> Catalog Costing</button>'
        + '<button class="btn btn-default cp-tab-btn' + (_tabMode === 'low_margin' ? ' active' : '') + '" data-mode="low_margin" onclick="setTabMode(\'low_margin\')"><i class="fa fa-exclamation-triangle text-warning"></i> Low Margin Alerts <span class="badge" style="background:#d9534f;">' + _lowMarginRows.length + '</span></button>'
        + '</div>';

    el.innerHTML = ''
        + (showTrend
            ? '<div class="row">'
              + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
              + '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:10px;">'
              + '<h4 class="no-margin bold" style="flex:1;min-width:0;">Revenue vs Cost — <span class="text-muted" style="font-size:14px;font-weight:400;">' + (GROUP_BY_LABEL[gb] || 'Daily') + '</span></h4>'
              + '</div>'
              + '<canvas id="cp-chart-trend" height="60"></canvas>'
              + '</div></div></div>'
              + '</div>'
            : '')
        + (prevLbl
            ? '<div class="no-print" style="margin-bottom:6px;">'
              + '<span class="kpi-prev-bar"><i class="fa fa-exchange" style="margin-right:4px;"></i>vs ' + prevLbl + '</span>'
              + '</div>'
            : '')
        + '<div class="row">'
        + kpiCard('green',  'Total Revenue',    'RM ' + fmt2(totalRev),  _delta(totalRev,   pTotalRev,   'rm'))
        + kpiCard('orange', 'Total Cost',       'RM ' + fmt2(totalCost), _delta(totalCost,  pTotalCost,  'rm'))
        + kpiCard('blue',   'Gross Profit',     'RM ' + fmt2(totalGP),   _delta(totalGP,    pTotalGP,    'rm'))
        + kpiCard('purple', 'Avg Margin %',     avgMargin.toFixed(1) + '%', _delta(avgMargin, pAvgMargin, 'pct'))
        + kpiCard('teal',   'Receipts',          fmtInt(receipts),       _delta(receipts,   pReceipts,   'int'))
        + kpiCard('',       'Items Sold',        fmtInt(itemsSold),      _delta(itemsSold,  pItemsSold,  'int'))
        + '</div>'

        + '<div class="row" style="margin-top:12px;">'
        + '<div class="col-md-12"><div class="panel_s"><div class="panel-body">'
        + '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:12px;">'
        + tabBtns
        + '</div>'

        + '<div id="cp-by-product" style="display:' + (_tabMode === 'by_product' ? '' : 'none') + ';">'
        + '<h5 class="no-margin-top bold">By Product (from actual sales) <span class="rpt-row-count" id="bp-count"></span></h5>'
        + '<div id="bp-table-wrap" style="overflow-x:auto;"></div>'
        + '</div>'

        + '<div id="cp-catalog" style="display:' + (_tabMode === 'catalog' ? '' : 'none') + ';">'
        + '<h5 class="no-margin-top bold">Catalog Costing <span class="rpt-row-count" id="cc-count"></span></h5>'
        + '<div id="cc-table-wrap" style="overflow-x:auto;"></div>'
        + '</div>'

        + '<div id="cp-low-margin" style="display:' + (_tabMode === 'low_margin' ? '' : 'none') + ';">'
        + '<h5 class="no-margin-top bold">Low Margin Alerts <small class="text-muted">(margin &lt; 35%, sorted ascending)</small> <span class="rpt-row-count" id="lm-count"></span></h5>'
        + '<div id="lm-table-wrap" style="overflow-x:auto;"></div>'
        + '</div>'

        + '</div></div></div>'
        + '</div>';

    if (showTrend) _renderTrend(r);

    document.getElementById('bp-count').textContent = _byProdRows.length + ' rows';
    document.getElementById('cc-count').textContent = _catalogRows.length + ' rows';
    document.getElementById('lm-count').textContent = _lowMarginRows.length + ' rows';
    _renderByProductTable(_byProdRows);
    _renderCatalogTable(_catalogRows, 'cc-table-wrap', 'cc-table');
    _renderCatalogTable(_lowMarginRows, 'lm-table-wrap', 'lm-table');
}

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

    if (_tabMode === 'catalog') {
        var col = _sortColCC, dir = _sortDirCC;
        var sorted = _doSort(_catalogRows, col, dir);
        var cols = [
            { key: 'sku_name',             label: 'Product' },
            { key: 'sku_code',             label: 'SKU' },
            { key: 'item_type',            label: 'Type' },
            { key: 'current_cost_per_unit',label: 'Current Unit Cost (RM)' },
            { key: 'selling_price',        label: 'Selling Price (RM)' },
            { key: 'profit_per_unit',      label: 'Profit / Unit (RM)' },
            { key: 'margin_pct',           label: 'Margin %' }
        ];
        var rows = sorted.map(function(r){
            var cost = parseFloat(r.current_cost_per_unit || r.purchase_price || 0);
            var price = parseFloat(r.selling_price || 0);
            var profit = price - cost;
            var margin = price > 0 ? (profit / price * 100) : 0;
            return Object.assign({}, r, {
                current_cost_per_unit: cost.toFixed(2),
                selling_price: price.toFixed(2),
                profit_per_unit: profit.toFixed(2),
                margin_pct: margin.toFixed(1)
            });
        });
        return { filename: 'cost-profit-catalog' + suffix + '.csv', cols: cols, rows: rows };
    }

    if (_tabMode === 'low_margin') {
        var col = _sortColLM, dir = _sortDirLM;
        var sorted = _doSort(_lowMarginRows, col, dir);
        var cols = [
            { key: 'sku_name',             label: 'Product' },
            { key: 'sku_code',             label: 'SKU' },
            { key: 'item_type',            label: 'Type' },
            { key: 'current_cost_per_unit',label: 'Current Unit Cost (RM)' },
            { key: 'selling_price',        label: 'Selling Price (RM)' },
            { key: 'profit_per_unit',      label: 'Profit / Unit (RM)' },
            { key: 'margin_pct',           label: 'Margin %' }
        ];
        var rows = sorted.map(function(r){
            var cost = parseFloat(r.current_cost_per_unit || r.purchase_price || 0);
            var price = parseFloat(r.selling_price || 0);
            var profit = price - cost;
            var margin = price > 0 ? (profit / price * 100) : 0;
            return Object.assign({}, r, {
                current_cost_per_unit: cost.toFixed(2),
                selling_price: price.toFixed(2),
                profit_per_unit: profit.toFixed(2),
                margin_pct: margin.toFixed(1)
            });
        });
        return { filename: 'cost-profit-low-margin' + suffix + '.csv', cols: cols, rows: rows };
    }

    var sorted = _doSort(_byProdRows, _sortColBP, _sortDirBP);
    var cols = [
        { key: 'item_name',     label: 'Product' },
        { key: 'category_name', label: 'Category' },
        { key: 'qty_sold',      label: 'Qty Sold' },
        { key: 'total_revenue', label: 'Total Revenue (RM)' },
        { key: 'total_cost',    label: 'Total Cost (RM)' },
        { key: 'gross_profit',  label: 'Gross Profit (RM)' },
        { key: 'margin_pct',    label: 'Margin %' },
        { key: 'avg_unit_price',label: 'Avg Unit Price (RM)' },
        { key: 'avg_unit_cost', label: 'Avg Unit Cost (RM)' }
    ];
    var rows = sorted.map(function(r){
        var qty = parseFloat(r.qty_sold || 0);
        var rev = parseFloat(r.total_revenue || 0);
        var cost = parseFloat(r.total_cost || 0);
        var gp = rev - cost;
        var margin = rev > 0 ? (gp / rev * 100) : 0;
        var avgPrice = qty > 0 ? rev / qty : 0;
        var avgCost = qty > 0 ? cost / qty : 0;
        return Object.assign({}, r, {
            total_revenue: rev.toFixed(2),
            total_cost: cost.toFixed(2),
            gross_profit: gp.toFixed(2),
            margin_pct: margin.toFixed(1),
            avg_unit_price: avgPrice.toFixed(2),
            avg_unit_cost: avgCost.toFixed(2)
        });
    });
    return { filename: 'cost-profit-by-product' + suffix + '.csv', cols: cols, rows: rows };
}
</script>

<?php init_tail(); ?>
