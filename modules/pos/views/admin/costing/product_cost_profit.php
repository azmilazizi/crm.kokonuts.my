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
.modal .bootstrap-select.open {
    position: relative;
    z-index: 3050;
}
.modal .bootstrap-select.open .dropdown-menu {
    z-index: 3050 !important;
}
#productCostModal .modal-dialog {
    width: 95%;
    max-width: 1150px;
}
.product-component-status {
    display: block;
    margin-top: 4px;
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
                                <p class="text-muted small">Workbook-style summary for product-level cost, profit, and margin.</p>
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
                            <table class="table table-bordered table-striped table-hover" id="product-cost-profit-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th style="width:120px;">Selling Price (RM)</th>
                                        <th style="width:120px;">Total Cost (RM)</th>
                                        <th style="width:120px;">Profit (RM)</th>
                                        <th style="width:120px;">Profit Margin (%)</th>
                                        <th style="width:90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item) {
                                        $category = $item['sub_category_name'] ?: ($item['category_name'] ?: '-');
                                        $isRange = !empty($item['is_range']);
                                    ?>
                                    <tr class="product-row"
                                        data-subgroup="<?php echo (int)($item['sub_group'] ?? 0); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['sku_code'] ?? '') . ' ' . ($item['sku_name'] ?? ''))); ?>">
                                        <td><?php echo htmlspecialchars($item['sku_code'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['selling_price'] ?? 0), 2); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['total_cost_min'] ?? 0, $item['total_cost_max'] ?? 0, $isRange, 4); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['profit_min'] ?? 0, $item['profit_max'] ?? 0, $isRange, 4); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['margin_min'] ?? 0, $item['margin_max'] ?? 0, $isRange, 2); ?></td>
                                        <td>
                                            <button class="btn btn-default btn-sm" onclick="openProductCostDialog(<?php echo (int)$item['id']; ?>)">
                                                <i class="fa fa-pencil"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="productCostModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form onsubmit="return saveProductCostDetail(this)">
                <input type="hidden" name="item_id" id="product-cost-item-id" value="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Product Cost Profit</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        <strong>Group / Requires</strong> — give alternative components the same <em>Group</em> tag (e.g. "lid") to make them mutually exclusive.
                        Set <em>Requires</em> on a row to a modifier option (e.g. Dome Lid requires the "Cream Toppings: Whipped Cream" modifier) so it only applies when
                        the customer picks that option at POS; the row in the group with no <em>Requires</em> set is the default used otherwise. Since the actual choice
                        happens per order, Total Cost / Profit / Margin below show as a range whenever a Requires condition is in play.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-condensed">
                                <tr>
                                    <th style="width:140px;">Item SKU:</th>
                                    <td id="product-detail-sku">-</td>
                                </tr>
                                <tr>
                                    <th>Item Name:</th>
                                    <td id="product-detail-name">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Selling Price (RM)</th>
                                        <th>Total Cost (RM)</th>
                                        <th>Profit (RM)</th>
                                        <th>Profit Margin (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td id="summary-selling-price" class="text-right">0.00</td>
                                        <td id="summary-total-cost" class="text-right">0.0000</td>
                                        <td id="summary-profit" class="text-right">0.0000</td>
                                        <td id="summary-margin" class="text-right">0.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h5><strong>Mixed Ingredients</strong></h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th style="width:100px;">Quantity</th>
                                        <th style="width:130px;">Cost Per Unit (RM)</th>
                                        <th style="width:130px;">Total Cost (RM)</th>
                                        <th style="width:90px;">Group</th>
                                        <th style="width:220px;">Requires (optional)</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="section-mixed-ingredients"></tbody>
                            </table>
                            <button type="button" class="btn btn-success btn-sm" onclick="addProductComponentRow('mixed_ingredients')"><i class="fa fa-plus"></i> Add Mixed Ingredient</button>
                        </div>
                    </div>

                    <div class="row mtop20">
                        <div class="col-md-12">
                            <h5><strong>Ingredients</strong></h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th style="width:100px;">Quantity</th>
                                        <th style="width:130px;">Cost Per Unit (RM)</th>
                                        <th style="width:130px;">Total Cost (RM)</th>
                                        <th style="width:90px;">Group</th>
                                        <th style="width:220px;">Requires (optional)</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="section-ingredients"></tbody>
                            </table>
                            <button type="button" class="btn btn-success btn-sm" onclick="addProductComponentRow('ingredients')"><i class="fa fa-plus"></i> Add Ingredient</button>
                        </div>
                    </div>

                    <div class="row mtop20">
                        <div class="col-md-12">
                            <h5><strong>Packaging</strong></h5>
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th style="width:100px;">Quantity</th>
                                        <th style="width:130px;">Cost Per Unit (RM)</th>
                                        <th style="width:130px;">Total Cost (RM)</th>
                                        <th style="width:90px;">Group</th>
                                        <th style="width:220px;">Requires (optional)</th>
                                        <th style="width:50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="section-packaging"></tbody>
                            </table>
                            <button type="button" class="btn btn-success btn-sm" onclick="addProductComponentRow('packaging')"><i class="fa fa-plus"></i> Add Packaging</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fa fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var getProductDetailUrl = '<?php echo admin_url('pos/ajax_get_product_cost_profit_detail'); ?>';
var saveProductDetailUrl = '<?php echo admin_url('pos/ajax_save_product_cost_profit_detail'); ?>';

var productSectionItems = {
    mixed_ingredients: <?php echo json_encode(array_values($mixed_items)); ?>,
    ingredients: <?php echo json_encode(array_values($ingredient_items)); ?>,
    packaging: <?php echo json_encode(array_values($packaging_items)); ?>
};

function productSectionCostMap() {
    var map = {};
    ['mixed_ingredients', 'ingredients', 'packaging'].forEach(function (section) {
        productSectionItems[section].forEach(function (item) {
            map[item.id] = parseFloat(item.cost_per_unit || 0);
        });
    });
    return map;
}

function productItemOptions(selectedId, section) {
    var items = productSectionItems[section] || [];
    var html = '<option value="">-- Select --</option>';
    for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var selected = parseInt(item.id, 10) === parseInt(selectedId || 0, 10) ? ' selected' : '';
        var label = (item.sku_code ? '[' + item.sku_code + '] ' : '') + item.sku_name;
        html += '<option value="' + item.id + '"' + selected + '>' + label + '</option>';
    }
    return html;
}

function productConditionOptions() {
    return $('#productCostModal').data('conditionOptions') || [];
}

function productRequiresOptions(selectedType, selectedId) {
    var items = productConditionOptions();
    var selectedValue = selectedId ? (selectedType + '::' + selectedId) : '';
    var html = '<option value="">Always (default)</option>';
    for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var value = item.type + '::' + item.id;
        var selected = value === selectedValue ? ' selected' : '';
        html += '<option value="' + value + '"' + selected + '>' + item.label + '</option>';
    }
    return html;
}

function addProductComponentRow(section, row) {
    row = row || {};
    var tr = document.createElement('tr');
    tr.className = 'product-component-row';
    tr.setAttribute('data-section', section);
    tr.innerHTML = ''
        + '<td><select class="form-control input-sm product-component-item selectpicker-inline" data-live-search="true">' + productItemOptions(row.component_item_id || 0, section) + '</select></td>'
        + '<td><input type="number" step="0.0001" class="form-control input-sm product-component-qty" value="' + (row.quantity || '') + '"></td>'
        + '<td><input type="text" class="form-control input-sm product-component-cost" value="' + (row.cost_per_unit != null ? row.cost_per_unit : '') + '" readonly></td>'
        + '<td><input type="text" class="form-control input-sm product-component-total" value="' + (row.total_cost != null ? row.total_cost : '') + '" readonly></td>'
        + '<td><input type="text" class="form-control input-sm product-component-group" placeholder="e.g. lid" value="' + (row.group_key ? String(row.group_key).replace(/"/g, '&quot;') : '') + '"></td>'
        + '<td>'
        +   '<select class="form-control input-sm product-component-requires selectpicker-inline" data-live-search="true">' + productRequiresOptions(row.requires_modifier_type || '', row.requires_modifier_id || 0) + '</select>'
        +   '<small class="product-component-status text-muted"></small>'
        + '</td>'
        + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="removeProductComponentRow(this)"><i class="fa fa-times"></i></button></td>';
    document.getElementById('section-' + section.replace('_', '-')).appendChild(tr);
    bindProductRow(tr);
    if (typeof $().selectpicker !== 'undefined') {
        $(tr).find('.selectpicker-inline').selectpicker();
    }
    recomputeProductRow(tr);
    recomputeProductSummary();
}

function bindProductRow(tr) {
    $(tr).find('.product-component-item, .product-component-qty, .product-component-group, .product-component-requires').on('change keyup', function () {
        recomputeProductRow(tr);
        recomputeProductSummary();
    });
}

function removeProductComponentRow(btn) {
    $(btn).closest('tr').remove();
    recomputeProductSummary();
}

function recomputeProductRow(tr) {
    var itemId = parseInt($(tr).find('.product-component-item').val() || 0, 10);
    var qty = parseFloat($(tr).find('.product-component-qty').val() || 0);
    var costMap = $('#productCostModal').data('componentCostMap') || {};
    var cost = parseFloat(costMap[itemId] || 0);
    var total = itemId > 0 ? qty * cost : 0;
    $(tr).find('.product-component-cost').val(itemId > 0 ? cost.toFixed(6) : '');
    $(tr).find('.product-component-total').val(itemId > 0 ? total.toFixed(6) : '');
}

// Mirrors Pos_model::resolve_bom_cost_range(): rows sharing a Group are
// mutually exclusive; if any of them has a Requires condition, which one
// applies depends on what the customer picks at POS, so the group
// contributes a [min,max] range instead of one fixed number.
function computeProductCostRange() {
    var rows = $('.product-component-row').toArray();
    var groups = {};
    var ungroupedTotal = 0;
    var lineCost = {};

    rows.forEach(function (tr, idx) {
        lineCost[idx] = parseFloat($(tr).find('.product-component-total').val() || 0);
        var key = ($(tr).find('.product-component-group').val() || '').trim();
        if (!key) {
            ungroupedTotal += lineCost[idx];
            $(tr).find('.product-component-status').text('Always included').removeClass('text-info').addClass('text-muted');
            return;
        }
        groups[key] = groups[key] || [];
        groups[key].push(idx);
    });

    var min = ungroupedTotal;
    var max = ungroupedTotal;
    var isRange = false;

    Object.keys(groups).forEach(function (key) {
        var indexes = groups[key];
        var conditional = [];
        var defaults = [];
        indexes.forEach(function (idx) {
            var requiresValue = $(rows[idx]).find('.product-component-requires').val() || '';
            var label = $(rows[idx]).find('.product-component-requires option:selected').text();
            if (requiresValue) {
                conditional.push(idx);
                $(rows[idx]).find('.product-component-status').text('Only if: ' + label).removeClass('text-muted').addClass('text-info');
            } else {
                defaults.push(idx);
                $(rows[idx]).find('.product-component-status').text('Default (used otherwise)').removeClass('text-info').addClass('text-muted');
            }
        });

        if (conditional.length) {
            isRange = true;
            var groupCosts = indexes.map(function (idx) { return lineCost[idx]; });
            min += Math.min.apply(null, groupCosts);
            max += Math.max.apply(null, groupCosts);
        } else {
            var pickIdx = defaults.length ? defaults[0] : indexes[0];
            min += lineCost[pickIdx];
            max += lineCost[pickIdx];
        }
    });

    return { min: min, max: max, is_range: isRange };
}

function formatCostRange(min, max, isRange, decimals) {
    if (isRange) {
        return min.toFixed(decimals) + ' – ' + max.toFixed(decimals);
    }
    return max.toFixed(decimals);
}

function recomputeProductSummary() {
    var range = computeProductCostRange();
    var sellingPrice = parseFloat($('#summary-selling-price').text() || 0);
    var profitMin = sellingPrice - range.max;
    var profitMax = sellingPrice - range.min;
    var marginMin = sellingPrice > 0 ? ((profitMin / sellingPrice) * 100) : 0;
    var marginMax = sellingPrice > 0 ? ((profitMax / sellingPrice) * 100) : 0;
    $('#summary-total-cost').text(formatCostRange(range.min, range.max, range.is_range, 4));
    $('#summary-profit').text(formatCostRange(profitMin, profitMax, range.is_range, 4));
    $('#summary-margin').text(formatCostRange(marginMin, marginMax, range.is_range, 2));
}

function openProductCostDialog(itemId) {
    $('#product-cost-item-id').val(itemId);
    $('#section-mixed-ingredients, #section-ingredients, #section-packaging').html('');
    $('#product-detail-sku, #product-detail-name').text('-');
    $('#summary-selling-price').text('0.00');
    $('#summary-total-cost').text('0.0000');
    $('#summary-profit').text('0.0000');
    $('#summary-margin').text('0.00');

    $.post(getProductDetailUrl, { item_id: itemId }, function (res) {
        if (!(res && res.success && res.data)) {
            alert_float('danger', (res && res.error) || 'Failed to load detail');
            return;
        }
        var data = res.data;
        var item = data.item || {};
        var sections = data.sections || {};
        var costMap = productSectionCostMap();
        ['mixed_ingredients', 'ingredients', 'packaging'].forEach(function (sectionName) {
            var rows = sections[sectionName] || [];
            for (var i = 0; i < rows.length; i++) {
                costMap[rows[i].component_item_id] = parseFloat(rows[i].cost_per_unit || 0);
            }
        });
        $('#productCostModal').data('componentCostMap', costMap);
        $('#productCostModal').data('conditionOptions', data.condition_options || []);
        $('#product-detail-sku').text(item.sku_code || '-');
        $('#product-detail-name').text(item.sku_name || '-');
        $('#summary-selling-price').text(parseFloat(item.selling_price || 0).toFixed(2));

        ['mixed_ingredients', 'ingredients', 'packaging'].forEach(function (sectionName) {
            var rows = sections[sectionName] || [];
            if (!rows.length) {
                addProductComponentRow(sectionName);
            } else {
                for (var i = 0; i < rows.length; i++) addProductComponentRow(sectionName, rows[i]);
            }
        });

        recomputeProductSummary();
        $('#productCostModal').modal('show');
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function saveProductCostDetail(form) {
    var payload = {
        mixed_ingredients: [],
        ingredients: [],
        packaging: []
    };

    $('.product-component-row').each(function () {
        var section = $(this).data('section');
        var requiresValue = $(this).find('.product-component-requires').val() || '';
        var requiresParts = requiresValue ? requiresValue.split('::') : ['', ''];
        payload[section].push({
            component_item_id: parseInt($(this).find('.product-component-item').val() || 0, 10),
            quantity: $(this).find('.product-component-qty').val(),
            note: '',
            group_key: ($(this).find('.product-component-group').val() || '').trim(),
            requires_modifier_type: requiresParts[0],
            requires_modifier_id: parseInt(requiresParts[1] || 0, 10)
        });
    });

    $.post(saveProductDetailUrl, {
        item_id: $('#product-cost-item-id').val(),
        sections: JSON.stringify(payload)
    }, function (res) {
        if (res && res.success) {
            $('#productCostModal').modal('hide');
            alert_float('success', 'Saved. Reloading...');
            setTimeout(function () { location.reload(); }, 600);
        } else {
            alert_float('danger', (res && res.error) || 'Save failed');
        }
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
    return false;
}

function applyFilters() {
    var cat = parseInt($('#filter-category').val() || 0, 10);
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.product-row').each(function () {
        var ok = true;
        if (cat > 0 && parseInt($(this).data('subgroup'), 10) !== cat) ok = false;
        if (q && ('' + ($(this).data('search') || '')).indexOf(q) < 0) ok = false;
        $(this).toggle(ok);
        if (ok) visible++;
    });
    $('#row-count').text(visible + ' items');
}

function exportTable() {
    var table = document.getElementById('product-cost-profit-table');
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
    a.download = 'product_cost_profit_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
