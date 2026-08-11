<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                                <p class="text-muted small">Manage mixed ingredient costing with the same breakdown structure as your workbook.</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-info" onclick="openMixedCostDialog()">
                                    <i class="fa fa-plus"></i> New Mixed Ingredient
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
                                <input type="text" id="filter-search" class="form-control" placeholder="Search SKU or Name..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-8 text-right text-muted" style="padding-top:6px;">
                                <span id="row-count"><?php echo count($mixed); ?> items</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="mixed-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Item Name</th>
                                        <th>Item SKU</th>
                                        <th>Total Cost (RM)</th>
                                        <th>Total Units</th>
                                        <th>Cost Per Unit (RM)</th>
                                        <th>Components</th>
                                        <th style="width:120px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mixed as $row) { ?>
                                    <tr class="mixed-row" data-search="<?php echo htmlspecialchars(strtolower(($row['sku_code'] ?? '') . ' ' . ($row['sku_name'] ?? ''))); ?>">
                                        <td><?php echo (int)$row['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($row['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['sku_code'] ?? ''); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($row['total_cost'] ?? 0), 4); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($row['total_batches_yield'] ?? 0), 2); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($row['cost_per_unit'] ?? 0), 6); ?></td>
                                        <td class="text-right"><?php echo (int)($row['components_count'] ?? 0); ?></td>
                                        <td>
                                            <button class="btn btn-default btn-sm" onclick="openMixedCostDialog(<?php echo (int)$row['id']; ?>)">
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

<div class="modal fade" id="mixedCostModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form onsubmit="return saveMixedCostDetail(this)">
                <input type="hidden" name="mixed_id" id="mixed-cost-id" value="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="mixed-cost-title">Mixed Ingredients Cost</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Item Name</label>
                            <select name="item_id" id="mixed-item-id" class="form-control selectpicker" data-live-search="true" required>
                                <option value="">-- Select item --</option>
                                <?php foreach ($all_items as $item) { ?>
                                    <option value="<?php echo (int)$item['id']; ?>"><?php echo htmlspecialchars(($item['sku_code'] ? '[' . $item['sku_code'] . '] ' : '') . $item['sku_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Total Cost</label>
                            <input type="text" class="form-control" id="mixed-total-cost" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Total Units</label>
                            <input type="number" step="0.0001" class="form-control" name="total_units" id="mixed-total-units" value="1">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group">
                            <label>Cost Per Unit</label>
                            <input type="text" class="form-control" id="mixed-cost-per-unit" readonly>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Yield UOM</label>
                            <input type="text" class="form-control" name="yield_uom" id="mixed-yield-uom">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Prep (min)</label>
                            <input type="number" class="form-control" name="prep_minutes" id="mixed-prep-minutes" min="0" value="0">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Instructions</label>
                            <input type="text" class="form-control" name="instructions" id="mixed-instructions">
                        </div>
                    </div>

                    <hr />
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="no-margin-top"><strong>Ingredients</strong></h5>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="button" class="btn btn-success btn-sm" onclick="addMixedComponentRow()">
                                <i class="fa fa-plus"></i> Add Row
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive mtop10">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th style="width:120px;">Quantity</th>
                                    <th style="width:140px;">Cost Per Unit</th>
                                    <th style="width:140px;">Total Cost</th>
                                    <th style="width:60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="mixed-components-body"></tbody>
                        </table>
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
var getMixedDetailUrl = '<?php echo admin_url('pos/ajax_get_mixed_cost_detail'); ?>';
var saveMixedDetailUrl = '<?php echo admin_url('pos/ajax_save_mixed_cost_detail'); ?>';
var allMixedItems = <?php echo json_encode(array_map(function ($item) {
    return [
        'id' => (int)$item['id'],
        'sku_code' => $item['sku_code'],
        'sku_name' => $item['sku_name'],
        'item_type' => $item['item_type'] ?? ''
    ];
}, $all_items)); ?>;

function mixedItemOptions(selectedId) {
    var html = '<option value="">-- Select --</option>';
    for (var i = 0; i < allMixedItems.length; i++) {
        var item = allMixedItems[i];
        var label = (item.sku_code ? '[' + item.sku_code + '] ' : '') + item.sku_name;
        var selected = parseInt(item.id, 10) === parseInt(selectedId || 0, 10) ? ' selected' : '';
        html += '<option value="' + item.id + '"' + selected + '>' + label + '</option>';
    }
    return html;
}

function addMixedComponentRow(component) {
    component = component || {};
    var tr = document.createElement('tr');
    tr.className = 'mixed-component-row';
    tr.innerHTML = ''
        + '<td><select class="form-control input-sm mixed-component-item selectpicker-inline" data-live-search="true">' + mixedItemOptions(component.component_item_id || 0) + '</select></td>'
        + '<td><input type="number" step="0.0001" class="form-control input-sm mixed-component-qty" value="' + (component.quantity || '') + '"></td>'
        + '<td><input type="text" class="form-control input-sm mixed-component-cost" value="' + (component.cost_per_unit != null ? component.cost_per_unit : '') + '" readonly></td>'
        + '<td><input type="text" class="form-control input-sm mixed-component-total" value="' + (component.total_cost != null ? component.total_cost : '') + '" readonly></td>'
        + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="removeMixedComponentRow(this)"><i class="fa fa-times"></i></button></td>';
    document.getElementById('mixed-components-body').appendChild(tr);
    bindMixedRow(tr);
    if (typeof $().selectpicker !== 'undefined') {
        $(tr).find('.selectpicker-inline').selectpicker();
    }
}

function bindMixedRow(tr) {
    $(tr).find('.mixed-component-item, .mixed-component-qty').on('change keyup', function () {
        recomputeMixedRow(tr);
        recomputeMixedSummary();
    });
}

function removeMixedComponentRow(btn) {
    $(btn).closest('tr').remove();
    recomputeMixedSummary();
}

function recomputeMixedRow(tr) {
    var option = $(tr).find('.mixed-component-item option:selected').text();
    var itemId = parseInt($(tr).find('.mixed-component-item').val() || 0, 10);
    var qty = parseFloat($(tr).find('.mixed-component-qty').val() || 0);
    var cost = 0;
    var total = 0;
    if (itemId > 0) {
        var existing = $('#mixedCostModal').data('componentCostMap') || {};
        cost = parseFloat(existing[itemId] || 0);
    }
    total = qty * cost;
    $(tr).find('.mixed-component-cost').val(cost ? cost.toFixed(6) : '');
    $(tr).find('.mixed-component-total').val(total ? total.toFixed(6) : '');
}

function recomputeMixedSummary() {
    var total = 0;
    $('#mixed-components-body .mixed-component-row').each(function () {
        total += parseFloat($(this).find('.mixed-component-total').val() || 0);
    });
    var units = parseFloat($('#mixed-total-units').val() || 0);
    $('#mixed-total-cost').val(total ? total.toFixed(6) : '0.000000');
    $('#mixed-cost-per-unit').val(units > 0 ? (total / units).toFixed(6) : '0.000000');
}

function openMixedCostDialog(mixedId) {
    $('#mixed-cost-id').val(mixedId || '');
    $('#mixed-cost-title').text(mixedId ? 'Edit Mixed Ingredients Cost' : 'New Mixed Ingredients Cost');
    $('#mixed-components-body').html('');
    $('#mixed-total-cost').val('0.000000');
    $('#mixed-cost-per-unit').val('0.000000');
    $('#mixed-total-units').val('1');
    $('#mixed-yield-uom').val('');
    $('#mixed-prep-minutes').val('0');
    $('#mixed-instructions').val('');
    $('#mixedCostModal').data('componentCostMap', {});
    $('#mixed-item-id').val('');
    if (typeof $().selectpicker !== 'undefined') {
        $('#mixed-item-id').selectpicker('refresh');
    }

    if (!mixedId) {
        addMixedComponentRow();
        $('#mixedCostModal').modal('show');
        return;
    }

    $.post(getMixedDetailUrl, { mixed_id: mixedId }, function (res) {
        if (!(res && res.success && res.data)) {
            alert_float('danger', (res && res.error) || 'Failed to load mixed ingredient');
            return;
        }
        var data = res.data;
        var mixed = data.mixed || {};
        var components = data.components || [];
        var costMap = {};
        for (var i = 0; i < allMixedItems.length; i++) {
            costMap[allMixedItems[i].id] = 0;
        }
        for (var j = 0; j < components.length; j++) {
            costMap[components[j].component_item_id] = parseFloat(components[j].cost_per_unit || 0);
        }
        $('#mixedCostModal').data('componentCostMap', costMap);
        $('#mixed-item-id').val(mixed.item_id || '');
        $('#mixed-total-cost').val(parseFloat(mixed.total_cost || 0).toFixed(6));
        $('#mixed-cost-per-unit').val(parseFloat(mixed.cost_per_unit || 0).toFixed(6));
        $('#mixed-total-units').val(mixed.total_units || 1);
        $('#mixed-yield-uom').val(mixed.yield_uom || '');
        $('#mixed-prep-minutes').val(mixed.prep_minutes || 0);
        $('#mixed-instructions').val(mixed.instructions || '');
        for (var k = 0; k < components.length; k++) {
            addMixedComponentRow(components[k]);
        }
        if (!components.length) addMixedComponentRow();
        if (typeof $().selectpicker !== 'undefined') {
            $('#mixed-item-id').selectpicker('refresh');
        }
        recomputeMixedSummary();
        $('#mixedCostModal').modal('show');
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function saveMixedCostDetail(form) {
    var payload = {
        item_id: parseInt($('#mixed-item-id').val() || 0, 10),
        total_units: $('#mixed-total-units').val(),
        yield_uom: $('#mixed-yield-uom').val(),
        prep_minutes: $('#mixed-prep-minutes').val(),
        instructions: $('#mixed-instructions').val(),
        components: []
    };

    $('#mixed-components-body .mixed-component-row').each(function () {
        payload.components.push({
            component_item_id: parseInt($(this).find('.mixed-component-item').val() || 0, 10),
            quantity: $(this).find('.mixed-component-qty').val(),
            note: ''
        });
    });

    $.post(saveMixedDetailUrl, {
        mixed_id: $('#mixed-cost-id').val(),
        payload: JSON.stringify(payload)
    }, function (res) {
        if (res && res.success) {
            $('#mixedCostModal').modal('hide');
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
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.mixed-row').each(function () {
        var ok = !q || ('' + ($(this).data('search') || '')).indexOf(q) > -1;
        $(this).toggle(ok);
        if (ok) visible++;
    });
    $('#row-count').text(visible + ' items');
}
</script>
