<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.modal .bootstrap-select.open {
    position: relative;
    z-index: 3050;
}
.modal .bootstrap-select.open .dropdown-menu {
    z-index: 3050 !important;
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
                                    ?>
                                    <tr class="product-row"
                                        data-subgroup="<?php echo (int)($item['sub_group'] ?? 0); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['sku_code'] ?? '') . ' ' . ($item['sku_name'] ?? ''))); ?>">
                                        <td><?php echo htmlspecialchars($item['sku_code'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['selling_price'] ?? 0), 2); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['total_cost'] ?? 0), 4); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['profit_per_unit'] ?? 0), 4); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['margin_pct'] ?? 0), 2); ?></td>
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
                                        <th style="width:120px;">Quantity</th>
                                        <th style="width:140px;">Cost Per Unit (RM)</th>
                                        <th style="width:140px;">Total Cost (RM)</th>
                                        <th style="width:60px;"></th>
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
                                        <th style="width:120px;">Quantity</th>
                                        <th style="width:140px;">Cost Per Unit (RM)</th>
                                        <th style="width:140px;">Total Cost (RM)</th>
                                        <th style="width:60px;"></th>
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
                                        <th style="width:120px;">Quantity</th>
                                        <th style="width:140px;">Cost Per Unit (RM)</th>
                                        <th style="width:140px;">Total Cost (RM)</th>
                                        <th style="width:60px;"></th>
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
        + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="removeProductComponentRow(this)"><i class="fa fa-times"></i></button></td>';
    document.getElementById('section-' + section.replace('_', '-')).appendChild(tr);
    bindProductRow(tr);
    if (typeof $().selectpicker !== 'undefined') {
        $(tr).find('.selectpicker-inline').selectpicker();
    }
}

function bindProductRow(tr) {
    $(tr).find('.product-component-item, .product-component-qty').on('change keyup', function () {
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
    var total = qty * cost;
    $(tr).find('.product-component-cost').val(cost ? cost.toFixed(6) : '');
    $(tr).find('.product-component-total').val(total ? total.toFixed(6) : '');
}

function recomputeProductSummary() {
    var totalCost = 0;
    $('.product-component-row').each(function () {
        totalCost += parseFloat($(this).find('.product-component-total').val() || 0);
    });
    var sellingPrice = parseFloat($('#summary-selling-price').text() || 0);
    var profit = sellingPrice - totalCost;
    var margin = sellingPrice > 0 ? ((profit / sellingPrice) * 100) : 0;
    $('#summary-total-cost').text(totalCost.toFixed(4));
    $('#summary-profit').text(profit.toFixed(4));
    $('#summary-margin').text(margin.toFixed(2));
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
        $('#product-detail-sku').text(item.sku_code || '-');
        $('#product-detail-name').text(item.sku_name || '-');
        $('#summary-selling-price').text(parseFloat(item.selling_price || 0).toFixed(2));
        $('#summary-total-cost').text(parseFloat(item.total_cost || 0).toFixed(4));
        $('#summary-profit').text(parseFloat(item.profit || 0).toFixed(4));
        $('#summary-margin').text(parseFloat(item.margin_pct || 0).toFixed(2));

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
        payload[section].push({
            component_item_id: parseInt($(this).find('.product-component-item').val() || 0, 10),
            quantity: $(this).find('.product-component-qty').val(),
            note: ''
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
