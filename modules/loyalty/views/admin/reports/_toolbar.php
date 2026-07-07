<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
.kpi-card { border-left: 4px solid #ddd; }
.kpi-card.green  { border-left-color: #5cb85c; }
.kpi-card.blue   { border-left-color: #337ab7; }
.kpi-card.orange { border-left-color: #f0ad4e; }
.kpi-card.red    { border-left-color: #d9534f; }
.kpi-card.purple { border-left-color: #9b59b6; }
.kpi-value { font-size: 26px; font-weight: 700; margin: 4px 0; }
.kpi-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: .5px; }
.period-btn.active { background: #337ab7; color: #fff; border-color: #337ab7; }
.chart-panel { min-height: 260px; }
.report-loader { text-align:center; padding: 60px 0; color: #aaa; }
</style>

<!-- Sub-navigation tabs -->
<ul class="nav nav-tabs" style="margin-bottom:18px;">
    <li class="<?php echo $active_tab === 'customers'  ? 'active' : ''; ?>"><a href="<?php echo admin_url('loyalty/reports/customers'); ?>">Customers</a></li>
    <li class="<?php echo $active_tab === 'promotions' ? 'active' : ''; ?>"><a href="<?php echo admin_url('loyalty/reports/promotions'); ?>">Promotions</a></li>
    <li class="<?php echo $active_tab === 'vouchers'   ? 'active' : ''; ?>"><a href="<?php echo admin_url('loyalty/reports/vouchers'); ?>">Vouchers</a></li>
</ul>

<!-- Date + Warehouse filter toolbar -->
<div class="row" style="margin-bottom:18px;">
    <div class="col-md-8">
        <div class="btn-group" id="period-btns">
            <button class="btn btn-default btn-sm period-btn active" data-period="today"      onclick="onPeriodBtn(this)">Today</button>
            <button class="btn btn-default btn-sm period-btn"       data-period="yesterday"   onclick="onPeriodBtn(this)">Yesterday</button>
            <button class="btn btn-default btn-sm period-btn"       data-period="week"        onclick="onPeriodBtn(this)">Last 7 Days</button>
            <button class="btn btn-default btn-sm period-btn"       data-period="month"       onclick="onPeriodBtn(this)">This Month</button>
            <button class="btn btn-default btn-sm period-btn"       data-period="last_month"  onclick="onPeriodBtn(this)">Last Month</button>
        </div>
        <span class="mleft10">
            <input type="text" id="custom-from" class="form-control input-sm" style="width:110px;display:inline-block;" placeholder="From">
            <input type="text" id="custom-to"   class="form-control input-sm" style="width:110px;display:inline-block;" placeholder="To">
            <button class="btn btn-default btn-sm" onclick="applyCustom()">Go</button>
        </span>
    </div>
    <div class="col-md-4">
        <select id="warehouse-filter" class="form-control input-sm selectpicker" data-live-search="true" title="All Warehouses" onchange="onWarehouseChange()">
            <?php foreach ($warehouses as $w) { ?>
            <option value="<?php echo $w['warehouse_id']; ?>"><?php echo htmlspecialchars($w['warehouse_name']); ?></option>
            <?php } ?>
        </select>
    </div>
</div>

<div id="report-loader" class="report-loader">
    <i class="fa fa-spinner fa-spin fa-2x"></i><br><span class="mtop10 inline-block">Loading...</span>
</div>
<div id="report-content" style="display:none;"></div>

<script>
var ADMIN_URL      = '<?php echo admin_url(); ?>';
var REPORT_SECTION = '<?php echo $active_tab; ?>';
var _ready         = false;
var _activeXhr     = null;

function getPeriodDates(p) {
    var t = new Date(), y = t.getFullYear(), m = t.getMonth(), d = t.getDate();
    var fmt = function(dt) { return dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate()); };
    var sub = function(n) { return new Date(y, m, d - n); };
    switch (p) {
        case 'today':      return { from: fmt(t),          to: fmt(t) };
        case 'yesterday':  return { from: fmt(sub(1)),     to: fmt(sub(1)) };
        case 'week':       return { from: fmt(sub(6)),     to: fmt(t) };
        case 'month':      return { from: fmt(new Date(y, m, 1)), to: fmt(t) };
        case 'last_month': return { from: fmt(new Date(y, m-1, 1)), to: fmt(new Date(y, m, 0)) };
    }
}
function pad(n) { return n < 10 ? '0'+n : ''+n; }
function fmt2(n) { return parseFloat(n||0).toLocaleString('en-MY', {minimumFractionDigits:2, maximumFractionDigits:2}); }
function fmtInt(n) { return parseInt(n||0).toLocaleString('en-MY'); }
function htmlEnc(s) { return $('<span>').text(s||'').html(); }

var CHART_COLORS = ['#337ab7','#5cb85c','#f0ad4e','#d9534f','#9b59b6','#1abc9c','#e67e22','#34495e','#16a085','#e74c3c'];

function loadReport(from, to) {
    if (_activeXhr) { _activeXhr.abort(); _activeXhr = null; }
    $('#report-loader').show();
    $('#report-content').hide();
    _activeXhr = $.post(ADMIN_URL + 'loyalty/ajax_report_data', {
        section:      REPORT_SECTION,
        date_from:    from,
        date_to:      to,
        warehouse_id: $('#warehouse-filter').val() || ''
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
    var active = document.querySelector('.period-btn.active');
    if (active) {
        var d = getPeriodDates(active.getAttribute('data-period'));
        loadReport(d.from, d.to);
    } else {
        loadReport($('#custom-from').val(), $('#custom-to').val());
    }
}
window.addEventListener('load', function() {
    if (typeof $.fn.datetimepicker !== 'undefined') {
        $('#custom-from, #custom-to').datetimepicker({ format: 'Y-m-d', timepicker: false, scrollMonth: false });
    }
    var d = getPeriodDates('today');
    loadReport(d.from, d.to);
    setTimeout(function() { _ready = true; }, 500);
});
</script>
