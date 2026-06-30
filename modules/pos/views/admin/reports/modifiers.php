<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<?php $this->load->view('pos/admin/reports/_toolbar'); ?>

</div>
</div>

<style>
.rpt-ctrl-groupby { display: none !important; }
.mod-sort-icon { margin-left: 4px; opacity: 0.4; font-size: 10px; }
.mod-sort-icon.active { opacity: 1; color: #337ab7; }
th.sortable-col:hover { background: #f5f5f5; }
.mod-filter-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
    padding: 10px 12px;
    background: #f9f9f9;
    border: 1px solid #e8e8e8;
    border-radius: 4px;
}
.mod-filter-bar .form-control { height: 30px; font-size: 12px; }
.mod-filter-group  { flex: 0 1 220px; min-width: 160px; }
.mod-filter-search { flex: 1 1 180px; min-width: 140px; }
.mod-filter-clear  { flex-shrink: 0; }
#mod-chart-wrap canvas { max-height: 220px; }
</style>

<script>
var _lastData     = null;
var _modRows      = [];
var _sortColMod   = 'order_count';
var _sortDirMod   = 'desc';
var _filterGroup  = '';
var _filterSearch = '';
var _modChart     = null;

function kpiCard(cls, label, value) {
    return '<div class="col-md-3"><div class="panel_s kpi-card ' + cls + '">'
        + '<div class="panel-body">'
        + '<div class="kpi-label">' + label + '</div>'
        + '<div class="kpi-value">' + value + '</div>'
        + '</div></div></div>';
}

function _doSort(arr, col, dir) {
    return arr.slice().sort(function(a, b) {
        var av, bv;
        if (!isNaN(parseFloat(a[col]))) {
            av = parseFloat(a[col] || 0); bv = parseFloat(b[col] || 0);
        } else {
            av = (a[col] || '').toString().toLowerCase();
            bv = (b[col] || '').toString().toLowerCase();
        }
        if (av < bv) return dir === 'asc' ? -1 : 1;
        if (av > bv) return dir === 'asc' ? 1  : -1;
        return 0;
    });
}

function _sortIcon(col, activeCol, dir) {
    if (col !== activeCol) return '<span class="mod-sort-icon fa fa-sort"></span>';
    return dir === 'asc'
        ? '<span class="mod-sort-icon active fa fa-sort-asc"></span>'
        : '<span class="mod-sort-icon active fa fa-sort-desc"></span>';
}

function sortMod(col) {
    _sortDirMod = (_sortColMod === col && _sortDirMod === 'desc') ? 'asc' : 'desc';
    _sortColMod = col;
    _applyModFilters();
}

function onModFilterChange() {
    var $grp = document.getElementById('mod-filter-group');
    var $src = document.getElementById('mod-filter-search');
    _filterGroup  = $grp ? $grp.value.trim()  : '';
    _filterSearch = $src ? $src.value.trim().toLowerCase() : '';
    _applyModFilters();
}

function clearModFilters() {
    var $grp = document.getElementById('mod-filter-group');
    var $src = document.getElementById('mod-filter-search');
    if ($grp) $grp.value = '';
    if ($src) $src.value = '';
    _filterGroup  = '';
    _filterSearch = '';
    _applyModFilters();
}

function _applyModFilters() {
    var filtered = _modRows.filter(function(r) {
        if (_filterGroup  && r.group_name    !== _filterGroup)                            return false;
        if (_filterSearch && r.modifier_name.toLowerCase().indexOf(_filterSearch) === -1) return false;
        return true;
    });
    _renderModTable(filtered);
    var countEl = document.getElementById('mod-row-count');
    if (countEl) {
        var showing = filtered.length, total = _modRows.length;
        countEl.textContent = showing === total
            ? total + ' modifier' + (total !== 1 ? 's' : '')
            : showing + ' of ' + total + ' modifiers';
    }
    _renderChart(filtered);
}

function _renderChart(rows) {
    var canvas = document.getElementById('mod-bar-chart');
    if (!canvas) return;
    if (_modChart) { _modChart.destroy(); _modChart = null; }
    var top = _doSort(rows, 'order_count', 'desc').slice(0, 15);
    if (!top.length) return;
    var labels   = top.map(function(r){ return r.group_name + ' – ' + r.modifier_name; });
    var orders   = top.map(function(r){ return parseInt(r.order_count   || 0); });
    var items    = top.map(function(r){ return parseInt(r.attach_count  || 0); });
    _modChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Orders',        data: orders, backgroundColor: 'rgba(51,122,183,0.75)', borderColor: '#337ab7', borderWidth: 1 },
                { label: 'Product Items', data: items,  backgroundColor: 'rgba(92,184,92,0.55)',  borderColor: '#5cb85c', borderWidth: 1 }
            ]
        },
        options: {
            responsive: true,
            animation: { duration: rows.length > 60 ? 0 : 400 },
            legend: { position: 'top' },
            scales: {
                xAxes: [{ ticks: { maxRotation: 45, minRotation: 20, fontSize: 11 } }],
                yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }]
            },
            tooltips: {
                callbacks: {
                    label: function(item, data) {
                        return ' ' + data.datasets[item.datasetIndex].label + ': ' + item.yLabel.toLocaleString();
                    }
                }
            }
        }
    });
}

function _renderModTable(rows) {
    var sorted = _doSort(rows, _sortColMod, _sortDirMod);
    var wrap   = document.getElementById('mod-table-wrap');
    if (!wrap) return;
    if (!sorted.length) {
        wrap.innerHTML = '<p class="text-muted text-center small" style="padding:20px 0;">No modifiers match the current filters.</p>';
        return;
    }

    var cols = [
        { key: 'group_name',      label: 'Group' },
        { key: 'modifier_name',   label: 'Modifier' },
        { key: 'price_adjustment',label: 'Price Adj',    cls: 'text-right' },
        { key: 'order_count',     label: 'Orders',       cls: 'text-right' },
        { key: 'attach_count',    label: 'Product Items',cls: 'text-right' },
        { key: 'total_quantity',  label: 'Total Qty',    cls: 'text-right' },
    ];

    var thead = '<thead><tr>' + cols.map(function(c) {
        var cls  = (c.cls || '') + ' sortable-col';
        var icon = _sortIcon(c.key, _sortColMod, _sortDirMod);
        return '<th class="' + cls + '" onclick="sortMod(\'' + c.key + '\')" style="cursor:pointer;white-space:nowrap;">' + c.label + icon + '</th>';
    }).join('') + '</tr></thead>';

    var tbody = '<tbody>' + sorted.map(function(r) {
        var adj = parseFloat(r.price_adjustment || 0);
        var adjHtml = adj > 0  ? '<span class="text-success">+RM ' + fmt2(adj) + '</span>'
                    : adj < 0  ? '<span class="text-danger">−RM ' + fmt2(Math.abs(adj)) + '</span>'
                    : '<span class="text-muted">—</span>';
        var modId   = r.modifier_id;
        var grpLink = '<a href="#" onclick="openModifierDetail(' + modId + ',\'' + (r.group_name||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\',\'' + (r.modifier_name||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\');return false;">' + htmlEnc(r.group_name) + '</a>';
        var modLink = '<a href="#" onclick="openModifierDetail(' + modId + ',\'' + (r.group_name||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\',\'' + (r.modifier_name||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'") + '\');return false;">' + htmlEnc(r.modifier_name) + '</a>';
        return '<tr>'
            + '<td>' + grpLink + '</td>'
            + '<td>' + modLink + '</td>'
            + '<td class="text-right">' + adjHtml + '</td>'
            + '<td class="text-right"><strong>' + fmtInt(r.order_count) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(r.attach_count) + '</td>'
            + '<td class="text-right">' + fmtInt(r.total_quantity) + '</td>'
            + '</tr>';
    }).join('') + '</tbody>';

    var tfoot = '<tfoot class="rpt-total"><tr>'
        + '<td colspan="3"><strong>Total</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a + parseInt(r.order_count||0);   }, 0)) + '</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a + parseInt(r.attach_count||0);  }, 0)) + '</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a + parseFloat(r.total_quantity||0); }, 0)) + '</strong></td>'
        + '</tr></tfoot>';

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin">' + thead + tbody + tfoot + '</table>';
    scrollTable(wrap, sorted.length);
}

function _buildGroupOptions(rows) {
    var groups = [];
    rows.forEach(function(r) { if (groups.indexOf(r.group_name) === -1) groups.push(r.group_name); });
    groups.sort();
    return '<option value="">All Groups</option>'
        + groups.map(function(g) {
            return '<option value="' + htmlEnc(g) + '"' + (g === _filterGroup ? ' selected' : '') + '>' + htmlEnc(g) + '</option>';
        }).join('');
}

function renderReport(r) {
    _lastData     = r;
    _filterGroup  = '';
    _filterSearch = '';
    var s    = r.summary       || {};
    var mods = r.top_modifiers || [];

    _modRows = mods.map(function(row, i) { return Object.assign({}, row, { _idx: i }); });

    var revenue    = parseFloat(s.total_modifier_revenue    || 0);
    var lineItems  = parseInt(s.line_items_with_modifiers   || 0);
    var receipts   = parseInt(s.receipts_with_modifiers     || 0);
    var totalRec   = parseInt(s.total_receipts              || 0);
    var attachRate = totalRec > 0 ? (receipts / totalRec * 100).toFixed(1) : '0.0';

    var el = document.getElementById('report-content');
    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('purple', 'Modifier Revenue',        'RM ' + fmt2(revenue))
        + kpiCard('blue',   'Product Items w/ Modifiers', fmtInt(lineItems))
        + kpiCard('green',  'Receipts w/ Modifiers',   fmtInt(receipts))
        + kpiCard('orange', 'Modifier Attach Rate',     attachRate + '%')
        + '</div>'

        + '<div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Modifier Usage Overview <span class="text-muted" style="font-weight:400;font-size:12px;">— top 15 by orders</span></h5>'
        + '<div id="mod-chart-wrap"><canvas id="mod-bar-chart" height="80"></canvas></div>'
        + '</div></div>'

        + '<div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">Modifiers <span id="mod-row-count" class="rpt-row-count">'
        +     _modRows.length + ' modifier' + (_modRows.length !== 1 ? 's' : '') + '</span></h5>'
        + '<div class="mod-filter-bar">'
        +   '<div class="mod-filter-group">'
        +     '<select id="mod-filter-group" class="form-control" onchange="onModFilterChange()">'
        +       _buildGroupOptions(_modRows)
        +     '</select>'
        +   '</div>'
        +   '<div class="mod-filter-search">'
        +     '<div class="input-group input-group-sm">'
        +       '<span class="input-group-addon"><i class="fa fa-search"></i></span>'
        +       '<input type="text" id="mod-filter-search" class="form-control" placeholder="Search modifier…"'
        +         ' oninput="onModFilterChange()" onkeydown="if(event.key===\'Enter\')onModFilterChange()">'
        +     '</div>'
        +   '</div>'
        +   '<div class="mod-filter-clear">'
        +     '<button class="btn btn-default btn-sm" onclick="clearModFilters()" title="Clear filters"><i class="fa fa-times"></i></button>'
        +   '</div>'
        + '</div>'
        + '<div id="mod-table-wrap" style="overflow-x:auto;"></div>'
        + '</div></div>';

    _applyModFilters();
}

function getCSVData() {
    var active = document.querySelector('.period-btn.active');
    var from = active ? getPeriodDates(active.getAttribute('data-period')).from : ($('#custom-from').val() || '');
    var to   = active ? getPeriodDates(active.getAttribute('data-period')).to   : ($('#custom-to').val()   || '');
    var suffix = (from && to ? '_' + from + '_' + to : '');
    var filtered = _modRows.filter(function(r) {
        if (_filterGroup  && r.group_name    !== _filterGroup)                            return false;
        if (_filterSearch && r.modifier_name.toLowerCase().indexOf(_filterSearch) === -1) return false;
        return true;
    });
    var sorted = _doSort(filtered, _sortColMod, _sortDirMod);
    return {
        filename: 'modifiers' + suffix + '.csv',
        cols: [
            { key: 'group_name',      label: 'Group' },
            { key: 'modifier_name',   label: 'Modifier' },
            { key: 'price_adjustment',label: 'Price Adjustment' },
            { key: 'order_count',     label: 'Orders' },
            { key: 'attach_count',    label: 'Product Items' },
            { key: 'total_quantity',  label: 'Total Qty' },
        ],
        rows: sorted
    };
}

// ── Modifier Detail Dialog ────────────────────────────────────────────────────
function _currentRange() {
    var active = document.querySelector('.period-btn.active');
    if (active) {
        var d = getPeriodDates(active.getAttribute('data-period'));
        return { from: d.from, to: d.to };
    }
    return { from: $('#custom-from').val() || '', to: $('#custom-to').val() || '' };
}

function openModifierDetail(modifierId, groupName, modifierName) {
    document.getElementById('mod-detail-title').textContent = groupName + ' – ' + modifierName;
    document.getElementById('mod-detail-body').innerHTML =
        '<div class="text-center" style="padding:40px;"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>';
    $('#mod-detail-modal').modal('show');
    var rng = _currentRange();
    $.post(ADMIN_URL + 'pos/ajax_report_data', {
        section:      'modifier_detail',
        modifier_id:  modifierId,
        date_from:    rng.from,
        date_to:      rng.to,
        warehouse_id: $('#warehouse-filter').val() || ''
    }).done(function(resp) {
        if (typeof resp === 'string') { try { resp = JSON.parse(resp); } catch(e){} }
        if (!resp || !resp.success) {
            document.getElementById('mod-detail-body').innerHTML =
                '<p class="text-danger text-center">' + ((resp && resp.error) || 'Failed to load data') + '</p>';
            return;
        }
        _renderModifierDetail(groupName, modifierName, rng, resp);
    }).fail(function() {
        document.getElementById('mod-detail-body').innerHTML = '<p class="text-danger text-center">Request failed.</p>';
    });
}

function _renderModifierDetail(groupName, modifierName, rng, resp) {
    var txns    = resp.transactions || [];
    var coItems = resp.co_items     || [];

    var totalOrders = txns.reduce(function(set, r) { set[r.receipt_number] = 1; return set; }, {});
    var totalRev    = txns.reduce(function(a, r) { return a + parseFloat(r.total_money || 0); }, 0);
    var totalQty    = txns.reduce(function(a, r) { return a + parseFloat(r.quantity    || 0); }, 0);

    var kpis = '<div class="row" style="margin-bottom:12px;">'
        + '<div class="col-xs-4"><div class="panel_s kpi-card blue"><div class="panel-body"><div class="kpi-label">Orders</div><div class="kpi-value" style="font-size:20px;">' + fmtInt(Object.keys(totalOrders).length) + '</div></div></div></div>'
        + '<div class="col-xs-4"><div class="panel_s kpi-card green"><div class="panel-body"><div class="kpi-label">Total Qty</div><div class="kpi-value" style="font-size:20px;">' + fmtInt(totalQty) + '</div></div></div></div>'
        + '<div class="col-xs-4"><div class="panel_s kpi-card purple"><div class="panel-body"><div class="kpi-label">Revenue on Items</div><div class="kpi-value" style="font-size:20px;">RM ' + fmt2(totalRev) + '</div></div></div></div>'
        + '</div>';

    var txnRows = txns.map(function(r) {
        return '<tr>'
            + '<td><a href="' + ADMIN_URL + 'pos/transaction/' + encodeURIComponent(r.receipt_number) + '" target="_blank">' + htmlEnc(r.receipt_number) + ' <i class="fa fa-external-link fa-xs"></i></a></td>'
            + '<td style="white-space:nowrap;">' + htmlEnc(r.receipt_date) + '</td>'
            + '<td>' + htmlEnc(r.cashier_name || '—') + '</td>'
            + '<td>' + htmlEnc(r.warehouse_name || '—') + '</td>'
            + '<td>' + htmlEnc(r.item_name) + '</td>'
            + '<td class="text-right">' + fmtInt(r.quantity) + '</td>'
            + '<td class="text-right">RM ' + fmt2(r.unit_price) + '</td>'
            + '<td class="text-right"><strong>RM ' + fmt2(r.total_money) + '</strong></td>'
            + '</tr>';
    }).join('');

    var txnTable = txns.length
        ? '<div style="max-height:280px;overflow-y:auto;"><table class="table table-condensed table-bordered no-margin" style="font-size:12px;">'
          + '<thead><tr><th>Receipt</th><th>Date</th><th>Cashier</th><th>Outlet</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Item Total</th></tr></thead>'
          + '<tbody>' + txnRows + '</tbody>'
          + '</table></div>'
        : '<p class="text-muted text-center" style="padding:20px 0;">No transactions found.</p>';

    var coSection = '';
    if (coItems.length) {
        var coRows = coItems.map(function(r) {
            return '<tr>'
                + '<td>' + htmlEnc(r.item_name) + '</td>'
                + '<td class="text-right"><strong>' + fmtInt(r.order_count) + '</strong></td>'
                + '<td class="text-right">' + fmtInt(r.total_qty) + '</td>'
                + '<td class="text-right">RM ' + fmt2(r.total_revenue) + '</td>'
                + '</tr>';
        }).join('');
        coSection = '<h5 class="bold" style="margin-top:16px;">Items Ordered in Same Receipts</h5>'
            + '<div style="max-height:200px;overflow-y:auto;"><table class="table table-condensed table-bordered no-margin" style="font-size:12px;">'
            + '<thead><tr><th>Item</th><th class="text-right">Orders</th><th class="text-right">Total Qty</th><th class="text-right">Revenue</th></tr></thead>'
            + '<tbody>' + coRows + '</tbody>'
            + '</table></div>';
    }

    document.getElementById('mod-detail-body').innerHTML =
        '<p class="text-muted" style="margin-bottom:10px;font-size:12px;"><i class="fa fa-calendar-o"></i> ' + htmlEnc(rng.from) + ' — ' + htmlEnc(rng.to) + '</p>'
        + kpis
        + '<h5 class="bold" style="margin-top:4px;">Transactions</h5>'
        + txnTable
        + coSection;
}
</script>

<!-- Modifier Detail Modal -->
<div class="modal fade" id="mod-detail-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document" style="width:90%;max-width:1100px;">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="mod-detail-title"></h4>
            </div>
            <div class="modal-body" id="mod-detail-body" style="padding:20px;"></div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
