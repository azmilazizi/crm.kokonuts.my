<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
if (!function_exists('pos_format_cost_range')) {
    function pos_format_cost_range($min, $max, $isRange, $decimals)
    {
        if ($isRange) {
            return number_format((float)$min, $decimals) . ' – ' . number_format((float)$max, $decimals);
        }
        return number_format((float)$max, $decimals);
    }
}
?>
<style>
#simulatorModal .modal-dialog {
    width: 95%;
    max-width: 900px;
}
th.sortable {
    cursor: pointer;
    user-select: none;
}
th.sortable .fa {
    opacity: 0.35;
    margin-left: 4px;
}
th.sortable.sort-asc .fa, th.sortable.sort-desc .fa {
    opacity: 1;
}
.simulator-group {
    margin-bottom: 16px;
}
.simulator-group h5 {
    margin-bottom: 8px;
}
.simulator-tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.simulator-tile {
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 2px;
    min-width: 130px;
    flex: 0 1 auto;
    padding: 8px 14px;
    border: 1px solid #dde1e7;
    border-radius: 6px;
    background: #fff;
    cursor: pointer;
    margin: 0;
    font-weight: normal;
    transition: border-color .15s, background-color .15s;
}
.simulator-tile:hover {
    border-color: #adb8c4;
}
.simulator-tile input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.simulator-tile.selected {
    border-color: #337ab7;
    background: #eaf3fc;
}
.simulator-tile-name {
    font-size: 13px;
    color: #333;
    line-height: 1.3;
}
.simulator-tile.selected .simulator-tile-name {
    color: #1f5c8a;
    font-weight: 600;
}
.simulator-tile-price {
    font-size: 12px;
    color: #5a8f3c;
    font-weight: 600;
}
.simulator-tile-cost {
    font-size: 11px;
    color: #999;
}
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                                <p class="text-muted small">
                                    Same products as Product Cost Profit, but costed from a franchisee's point of view: any
                                    ingredient/packaging/mixed ingredient (or modifier) with a Franchisee Price set uses that price
                                    instead of its raw cost. Set Franchisee Price on the Individual Ingredients, Packaging, Mixed
                                    Ingredients, or Modifiers Cost Profit tabs.
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-default" onclick="exportTable()">
                                    <i class="fa fa-download"></i> Export This Table
                                </button>
                            </div>
                        </div>
                        <hr />
                        <?php if (isset($active_tab, $_tabs)) { ?>
                        <div class="mbot15">
                            <ul class="nav nav-tabs" role="tablist" style="margin-bottom:16px;">
                                <?php foreach ($_tabs as $key => $t) { ?>
                                    <li role="presentation" class="<?php echo $active_tab === $key ? 'active' : ''; ?>">
                                        <a href="<?php echo htmlspecialchars($t['href']); ?>"><?php echo htmlspecialchars($t['label']); ?></a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                        <?php } ?>

                        <div class="row mbot15">
                            <div class="col-md-4">
                                <select id="filter-category" class="form-control" onchange="applyFilters()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($sub_groups as $sg) { ?>
                                        <option value="<?php echo (int)$sg['id']; ?>"><?php echo htmlspecialchars($sg['sub_group_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="filter-search" class="form-control" placeholder="Search SKU or Name..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-4 text-right text-muted" style="padding-top:6px;">
                                <span id="row-count"><?php echo count($items); ?> items</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="franchisee-cost-profit-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th class="sortable" style="width:120px;" data-sort="selling_price">Selling Price (RM) <i class="fa fa-sort"></i></th>
                                        <th class="sortable" style="width:130px;" data-sort="total_cost">Franchisee Cost (RM) <i class="fa fa-sort"></i></th>
                                        <th class="sortable" style="width:130px;" data-sort="profit">Franchisee Profit (RM) <i class="fa fa-sort"></i></th>
                                        <th class="sortable" style="width:140px;" data-sort="margin">Franchisee Margin (%) <i class="fa fa-sort"></i></th>
                                        <th style="width:70px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item) {
                                        $category = $item['sub_category_name'] ?: ($item['category_name'] ?: '-');
                                        $isRange = !empty($item['is_range']);
                                    ?>
                                    <tr class="product-row"
                                        data-subgroup="<?php echo (int)($item['sub_group'] ?? 0); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['sku_code'] ?? '') . ' ' . ($item['sku_name'] ?? ''))); ?>"
                                        data-sort-selling_price="<?php echo (float)($item['selling_price'] ?? 0); ?>"
                                        data-sort-total_cost="<?php echo (float)($item['total_cost_max'] ?? 0); ?>"
                                        data-sort-profit="<?php echo (float)($item['profit_min'] ?? 0); ?>"
                                        data-sort-margin="<?php echo (float)($item['margin_min'] ?? 0); ?>">
                                        <td><?php echo htmlspecialchars($item['sku_code'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['selling_price'] ?? 0), 2); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['total_cost_min'] ?? 0, $item['total_cost_max'] ?? 0, $isRange, 4); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['profit_min'] ?? 0, $item['profit_max'] ?? 0, $isRange, 4); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['margin_min'] ?? 0, $item['margin_max'] ?? 0, $isRange, 2); ?></td>
                                        <td>
                                            <button class="btn btn-info btn-sm" onclick="openSimulator(<?php echo (int)$item['id']; ?>, 'franchisee')" data-toggle="tooltip" title="Mix and match this product's modifiers to see the resulting franchisee cost/profit">
                                                <i class="fa fa-flask"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-muted small">Click a column header to sort by it — useful for spotting the costliest or least profitable items. Franchisee Cost sorts by its worst case, Profit/Margin by their worst case.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="simulatorModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Franchisee Cost Profit Simulator — <span id="simulator-item-name">-</span></h4>
                <p class="text-muted small no-margin-bottom">Read-only. Pick modifiers the way a franchisee's order would and see the resulting franchisee cost/profit for that exact combination.</p>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Selling Price (RM)</th>
                                    <th>Franchisee Cost (RM)</th>
                                    <th>Franchisee Profit (RM)</th>
                                    <th>Franchisee Margin (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="simulator-selling-price" class="text-right">0.00</td>
                                    <td id="simulator-total-cost" class="text-right">0.0000</td>
                                    <td id="simulator-profit" class="text-right">0.0000</td>
                                    <td id="simulator-margin" class="text-right">0.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div id="simulator-groups"></div>
                <p id="simulator-empty" class="text-muted small" style="display:none;">This product has no modifier groups assigned — its cost/profit is fixed regardless of selection.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var getSimulatorOptionsUrl = '<?php echo admin_url('pos/ajax_get_product_modifier_simulator_options'); ?>';
var simulateCostProfitUrl = '<?php echo admin_url('pos/ajax_simulate_product_cost_profit'); ?>';

var sortState = { key: null, dir: 1 };
$(document).on('click', 'th.sortable', function () {
    var key = $(this).data('sort');
    if (sortState.key === key) {
        sortState.dir = -sortState.dir;
    } else {
        sortState.key = key;
        sortState.dir = 1;
    }
    $('th.sortable').removeClass('sort-asc sort-desc').find('.fa').attr('class', 'fa fa-sort');
    $(this).addClass(sortState.dir === 1 ? 'sort-asc' : 'sort-desc')
        .find('.fa').attr('class', sortState.dir === 1 ? 'fa fa-sort-asc' : 'fa fa-sort-desc');

    var $tbody = $('#franchisee-cost-profit-table tbody');
    var rows = $tbody.find('tr.product-row').toArray();
    rows.sort(function (a, b) {
        var av = parseFloat($(a).data('sort-' + key)) || 0;
        var bv = parseFloat($(b).data('sort-' + key)) || 0;
        return (av - bv) * sortState.dir;
    });
    rows.forEach(function (r) { $tbody.append(r); });
});

var simulatorState = { itemId: 0, mode: 'franchisee', selected: {} };

function openSimulator(itemId, mode) {
    simulatorState.itemId = itemId;
    simulatorState.mode = mode || 'franchisee';
    simulatorState.selected = {};
    $('#simulator-groups').html('');
    $('#simulator-empty').hide();
    $('#simulator-item-name').text('...');
    $('#simulatorModal').modal('show');

    $.post(getSimulatorOptionsUrl, { item_id: itemId }, function (res) {
        if (!(res && res.success)) {
            alert_float('danger', (res && res.error) || 'Failed to load modifiers');
            return;
        }
        renderSimulatorGroups(res.data || []);
        runSimulation();
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function renderSimulatorGroups(groups) {
    if (!groups.length) {
        $('#simulator-empty').show();
        return;
    }
    var html = '';
    groups.forEach(function (group) {
        var isMulti = group.selection_type === 'multiple';
        html += '<div class="simulator-group" data-group-key="' + group.key + '" data-multi="' + (isMulti ? '1' : '0') + '">';
        html += '<h5><strong>' + $('<div>').text(group.name).html() + '</strong> <small class="text-muted">(' + (isMulti ? 'pick any' : 'pick one') + ')</small></h5>';
        html += '<div class="simulator-tiles">';
        group.options.forEach(function (opt) {
            var refCost = simulatorState.mode === 'franchisee' ? opt.franchisee_reference_cost : opt.reference_cost;
            var priceLabel = opt.price_adjustment ? ('+RM' + parseFloat(opt.price_adjustment).toFixed(2)) : '';
            var costLabel = refCost ? ('+RM' + parseFloat(refCost).toFixed(4) + ' cost') : '';
            html += '<label class="simulator-tile">'
                + '<input type="' + (isMulti ? 'checkbox' : 'radio') + '" name="sim-group-' + group.key + '" class="simulator-option" value="' + opt.key + '" data-group-key="' + group.key + '">'
                + '<span class="simulator-tile-name">' + $('<div>').text(opt.name).html() + '</span>'
                + (priceLabel ? '<span class="simulator-tile-price">' + priceLabel + '</span>' : '')
                + (costLabel ? '<span class="simulator-tile-cost">' + costLabel + '</span>' : '')
                + '</label>';
        });
        html += '</div>';
        html += '</div>';
    });
    $('#simulator-groups').html(html);

    if (typeof $().tooltip !== 'undefined') {
        $('[data-toggle="tooltip"]').tooltip();
    }
}

$(document).on('change', '.simulator-option', function () {
    var groupKey = $(this).data('group-key');
    var $group = $(this).closest('.simulator-group');
    var isMulti = $group.data('multi') == 1;
    if (!isMulti) {
        $group.find('.simulator-tile').removeClass('selected');
        Object.keys(simulatorState.selected).forEach(function (k) {
            if (simulatorState.selected[k].group === groupKey) delete simulatorState.selected[k];
        });
        if (this.checked) simulatorState.selected[this.value] = { group: groupKey };
    } else {
        if (this.checked) {
            simulatorState.selected[this.value] = { group: groupKey };
        } else {
            delete simulatorState.selected[this.value];
        }
    }
    $(this).closest('.simulator-tile').toggleClass('selected', this.checked);
    runSimulation();
});

function runSimulation() {
    var keys = Object.keys(simulatorState.selected);
    $.post(simulateCostProfitUrl, {
        item_id: simulatorState.itemId,
        selected_keys: JSON.stringify(keys),
        mode: simulatorState.mode
    }, function (res) {
        if (!(res && res.success && res.data)) {
            alert_float('danger', (res && res.error) || 'Simulation failed');
            return;
        }
        var d = res.data;
        $('#simulator-item-name').text((d.item.sku_code ? '[' + d.item.sku_code + '] ' : '') + d.item.sku_name);
        $('#simulator-selling-price').text(parseFloat(d.selling_price).toFixed(2));
        $('#simulator-total-cost').text(parseFloat(d.total_cost).toFixed(4));
        $('#simulator-profit').text(parseFloat(d.profit).toFixed(4));
        $('#simulator-margin').text(parseFloat(d.margin_pct).toFixed(2));
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function applyFilters() {
    var cat = parseInt($('#filter-category').val() || 0, 10);
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.product-row').each(function () {
        var $r = $(this);
        var ok = true;
        if (cat > 0 && parseInt($r.data('subgroup'), 10) !== cat) ok = false;
        if (q && ('' + ($r.data('search') || '')).indexOf(q) < 0) ok = false;
        $r.toggle(ok);
        if (ok) visible++;
    });
    $('#row-count').text(visible + ' items');
}

function exportTable() {
    var table = document.getElementById('franchisee-cost-profit-table');
    var csv = [];
    for (var r = 0; r < table.rows.length; r++) {
        var row = [];
        for (var c = 0; c < table.rows[r].cells.length - 1; c++) {
            var t = (table.rows[r].cells[c].innerText || table.rows[r].cells[c].textContent || '').replace(/"/g, '""').trim();
            row.push('"' + t + '"');
        }
        csv.push(row.join(','));
    }
    var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'franchisee_cost_profit_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
