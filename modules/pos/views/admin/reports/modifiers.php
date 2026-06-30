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
</style>

<script>
var _lastData     = null;
var _modRows      = [];
var _grpRows      = [];
var _sortColMod   = 'attach_count';
var _sortDirMod   = 'desc';
var _sortColGrp   = 'attach_count';
var _sortDirGrp   = 'desc';
var _filterGroup  = '';
var _filterSearch = '';

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
    if (_sortColMod === col) {
        _sortDirMod = _sortDirMod === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColMod = col;
        _sortDirMod = 'desc';
    }
    _applyModFilters();
}

function sortGrp(col) {
    if (_sortColGrp === col) {
        _sortDirGrp = _sortDirGrp === 'asc' ? 'desc' : 'asc';
    } else {
        _sortColGrp = col;
        _sortDirGrp = 'desc';
    }
    _renderGroupTable(_grpRows);
}

function onModFilterChange() {
    var $grp = document.getElementById('mod-filter-group');
    var $src = document.getElementById('mod-filter-search');
    _filterGroup  = $grp  ? $grp.value.trim()  : '';
    _filterSearch = $src  ? $src.value.trim().toLowerCase() : '';
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
        if (_filterGroup  && r.group_name    !== _filterGroup)           return false;
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
        { key: 'price_adjustment',label: 'Price Adj',  cls: 'text-right' },
        { key: 'order_count',     label: 'Orders',     cls: 'text-right' },
        { key: 'attach_count',    label: 'Line Items', cls: 'text-right' },
        { key: 'total_quantity',  label: 'Total Qty',  cls: 'text-right' },
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
        return '<tr>'
            + '<td>' + htmlEnc(r.group_name) + '</td>'
            + '<td>' + htmlEnc(r.modifier_name) + '</td>'
            + '<td class="text-right">' + adjHtml + '</td>'
            + '<td class="text-right"><strong>' + fmtInt(r.order_count) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(r.attach_count) + '</td>'
            + '<td class="text-right">' + fmtInt(r.total_quantity) + '</td>'
            + '</tr>';
    }).join('') + '</tbody>';

    var tfoot = '<tfoot class="rpt-total"><tr>'
        + '<td colspan="3"><strong>Total</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a + parseInt(r.order_count||0); }, 0)) + '</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a + parseInt(r.attach_count||0); }, 0)) + '</strong></td>'
        + '<td class="text-right"><strong>' + fmtInt(sorted.reduce(function(a,r){ return a + parseFloat(r.total_quantity||0); }, 0)) + '</strong></td>'
        + '</tr></tfoot>';

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin">' + thead + tbody + tfoot + '</table>';
    scrollTable(wrap, sorted.length);
}

function _renderGroupTable(rows) {
    var sorted = _doSort(rows, _sortColGrp, _sortDirGrp);
    var wrap   = document.getElementById('grp-table-wrap');
    if (!wrap) return;
    if (!sorted.length) {
        wrap.innerHTML = '<p class="text-muted text-center small" style="padding:20px 0;">No group data in this period.</p>';
        return;
    }

    var cols = [
        { key: 'group_name',     label: 'Group' },
        { key: 'selection_type', label: 'Type' },
        { key: 'modifier_count', label: '# Options', cls: 'text-right' },
        { key: 'attach_count',   label: 'Line Items', cls: 'text-right' },
        { key: 'total_quantity', label: 'Total Qty',  cls: 'text-right' },
    ];

    var thead = '<thead><tr>' + cols.map(function(c) {
        var cls  = (c.cls || '') + ' sortable-col';
        var icon = _sortIcon(c.key, _sortColGrp, _sortDirGrp);
        return '<th class="' + cls + '" onclick="sortGrp(\'' + c.key + '\')" style="cursor:pointer;white-space:nowrap;">' + c.label + icon + '</th>';
    }).join('') + '</tr></thead>';

    var tbody = '<tbody>' + sorted.map(function(r) {
        var typeBadge = r.selection_type === 'multiple'
            ? '<span class="label label-info">Multiple</span>'
            : '<span class="label label-default">Single</span>';
        return '<tr>'
            + '<td><a href="#" onclick="filterByGroup(' + "'" + r.group_name.replace(/'/g,"\'") + "'" + ');return false;">' + htmlEnc(r.group_name) + '</a></td>'
            + '<td>' + typeBadge + '</td>'
            + '<td class="text-right">' + fmtInt(r.modifier_count) + '</td>'
            + '<td class="text-right"><strong>' + fmtInt(r.attach_count) + '</strong></td>'
            + '<td class="text-right">' + fmtInt(r.total_quantity) + '</td>'
            + '</tr>';
    }).join('') + '</tbody>';

    wrap.innerHTML = '<table class="table table-condensed table-bordered no-margin">' + thead + tbody + '</table>';
    scrollTable(wrap, sorted.length);
}

function filterByGroup(name) {
    var $grp = document.getElementById('mod-filter-group');
    if (!$grp) return;
    $grp.value    = name;
    _filterGroup  = name;
    _filterSearch = '';
    var $src = document.getElementById('mod-filter-search');
    if ($src) $src.value = '';
    _applyModFilters();
}

function _buildGroupOptions(rows) {
    var groups = [];
    rows.forEach(function(r) {
        if (groups.indexOf(r.group_name) === -1) groups.push(r.group_name);
    });
    groups.sort();
    return '<option value="">All Groups</option>'
        + groups.map(function(g) {
            return '<option value="' + htmlEnc(g) + '"' + (g === _filterGroup ? ' selected' : '') + '>' + htmlEnc(g) + '</option>';
        }).join('');
}

function renderReport(r) {
    _lastData = r;
    var s    = r.summary       || {};
    var mods = r.top_modifiers || [];
    var grps = r.by_group      || [];

    _modRows  = mods.map(function(row, i) { return Object.assign({}, row, { _idx: i }); });
    _grpRows  = grps.map(function(row, i) { return Object.assign({}, row, { _idx: i }); });
    _filterGroup  = '';
    _filterSearch = '';

    var revenue    = parseFloat(s.total_modifier_revenue    || 0);
    var lineItems  = parseInt(s.line_items_with_modifiers   || 0);
    var receipts   = parseInt(s.receipts_with_modifiers     || 0);
    var totalRec   = parseInt(s.total_receipts              || 0);
    var attachRate = totalRec > 0 ? (receipts / totalRec * 100).toFixed(1) : '0.0';

    var el = document.getElementById('report-content');
    el.innerHTML = ''
        + '<div class="row">'
        + kpiCard('purple', 'Modifier Revenue',        'RM ' + fmt2(revenue))
        + kpiCard('blue',   'Line Items w/ Modifiers',  fmtInt(lineItems))
        + kpiCard('green',  'Receipts w/ Modifiers',    fmtInt(receipts))
        + kpiCard('orange', 'Modifier Attach Rate',      attachRate + '%')
        + '</div>'

        + '<div class="row">'
        + '<div class="col-md-8"><div class="panel_s"><div class="panel-body">'
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
        + '</div></div></div>'

        + '<div class="col-md-4"><div class="panel_s"><div class="panel-body">'
        + '<h5 class="no-margin-top bold">By Modifier Group <span class="rpt-row-count">'
        +     _grpRows.length + ' group' + (_grpRows.length !== 1 ? 's' : '') + '</span></h5>'
        + '<p class="text-muted small no-margin-bottom" style="margin-bottom:8px;">Click a group to filter the modifier list.</p>'
        + '<div id="grp-table-wrap" style="overflow-x:auto;"></div>'
        + '</div></div></div>'
        + '</div>';

    _applyModFilters();
    _renderGroupTable(_grpRows);
}

function getCSVData() {
    var active = document.querySelector('.period-btn.active');
    var from = active ? getPeriodDates(active.getAttribute('data-period')).from : ($('#custom-from').val() || '');
    var to   = active ? getPeriodDates(active.getAttribute('data-period')).to   : ($('#custom-to').val()   || '');
    var suffix = (from && to ? '_' + from + '_' + to : '');
    var filtered = _modRows.filter(function(r) {
        if (_filterGroup  && r.group_name    !== _filterGroup)                           return false;
        if (_filterSearch && r.modifier_name.toLowerCase().indexOf(_filterSearch) === -1) return false;
        return true;
    });
    var sorted = _doSort(filtered, _sortColMod, _sortDirMod);
    var cols = [
        { key: 'group_name',      label: 'Group' },
        { key: 'modifier_name',   label: 'Modifier' },
        { key: 'price_adjustment',label: 'Price Adjustment' },
        { key: 'order_count',     label: 'Orders' },
        { key: 'attach_count',    label: 'Line Items' },
        { key: 'total_quantity',  label: 'Total Qty' },
    ];
    return { filename: 'modifiers' + suffix + '.csv', cols: cols, rows: sorted };
}
</script>
<?php init_tail(); ?>
