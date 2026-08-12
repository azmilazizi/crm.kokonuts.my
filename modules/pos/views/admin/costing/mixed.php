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
                    <input type="hidden" name="item_id" id="mixed-item-id" value="">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Item Name</label>
                            <input type="text" class="form-control" name="item_name" id="mixed-item-name" placeholder="e.g. Brown Sugar Syrup" required>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Total Units</label>
                            <input type="number" step="0.0001" class="form-control" name="total_units" id="mixed-total-units" value="1">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>Unit</label>
                            <select class="form-control" name="yield_uom" id="mixed-yield-uom">
                                <option value="">-- Select --</option>
                                <?php foreach ($uoms as $u) { ?>
                                    <option value="<?php echo htmlspecialchars($u['name']); ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Cost Per Unit</label>
                            <input type="text" class="form-control" id="mixed-cost-per-unit" readonly>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Total Cost</label>
                            <input type="text" class="form-control" id="mixed-total-cost" readonly>
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
                    <table class="table table-bordered mtop10">
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

                    <hr />
                    <div class="row">
                        <div class="col-md-12 form-group">
                            <label>Instructions</label>
                            <textarea class="form-control" name="instructions" id="mixed-instructions" rows="6"></textarea>
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
var getMixedDetailUrl = '<?php echo admin_url('pos/ajax_get_mixed_cost_detail'); ?>';
var saveMixedDetailUrl = '<?php echo admin_url('pos/ajax_save_mixed_cost_detail'); ?>';
var ingredientItems = <?php echo json_encode(array_values($ingredient_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function mixedIngredientCostMap() {
    var map = {};
    ingredientItems.forEach(function (item) {
        map[item.id] = parseFloat(item.cost_per_unit || 0);
    });
    return map;
}

function mixedComponentOptions(selectedId) {
    var html = '<option value="">-- Select --</option>';
    for (var i = 0; i < ingredientItems.length; i++) {
        var item = ingredientItems[i];
        var label = (item.sku_code ? '[' + item.sku_code + '] ' : '') + item.sku_name;
        var selected = parseInt(item.id, 10) === parseInt(selectedId || 0, 10) ? ' selected' : '';
        html += '<option value="' + item.id + '"' + selected + '>' + label + '</option>';
    }
    return html;
}

function addMixedComponentRow(component) {
    component = component || {};
    var qtyValue = (component.quantity !== undefined && component.quantity !== null && component.quantity !== '') ? component.quantity : 1;
    var tr = document.createElement('tr');
    tr.className = 'mixed-component-row';
    tr.innerHTML = ''
        + '<td><select class="form-control input-sm mixed-component-item selectpicker-inline" data-live-search="true">' + mixedComponentOptions(component.component_item_id || 0) + '</select></td>'
        + '<td><input type="number" step="0.0001" class="form-control input-sm mixed-component-qty" value="' + qtyValue + '"></td>'
        + '<td><input type="text" class="form-control input-sm mixed-component-cost" value="' + (component.cost_per_unit != null ? component.cost_per_unit : '') + '" readonly></td>'
        + '<td><input type="text" class="form-control input-sm mixed-component-total" value="' + (component.total_cost != null ? component.total_cost : '') + '" readonly></td>'
        + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="removeMixedComponentRow(this)"><i class="fa fa-times"></i></button></td>';
    document.getElementById('mixed-components-body').appendChild(tr);
    if (typeof $().selectpicker !== 'undefined') {
        $(tr).find('.selectpicker-inline').selectpicker();
    }
    recomputeMixedRow(tr);
}

// Delegated on the modal (bound once, survives rows being added/removed/re-rendered
// by .selectpicker() — a handler bound directly to a row can end up attached to a
// node bootstrap-select no longer considers "the" select after it re-inits).
// bootstrap-select's live-search dropdown (v1.13.12) can fire a second, late
// 'change' event as it settles internally after a click — reading .val()
// synchronously inside the handler sometimes races that cleanup and reads a
// stale/blank value. Deferring one tick reads the final, settled DOM state.
$('#mixedCostModal').on('change keyup', '.mixed-component-item, .mixed-component-qty', function () {
    var tr = $(this).closest('tr');
    setTimeout(function () {
        recomputeMixedRow(tr);
        recomputeMixedSummary();
    }, 0);
});

function removeMixedComponentRow(btn) {
    $(btn).closest('tr').remove();
    recomputeMixedSummary();
}

function recomputeMixedRow(tr) {
    var itemId = parseInt($(tr).find('.mixed-component-item').val() || 0, 10);
    var qty = parseFloat($(tr).find('.mixed-component-qty').val() || 0);
    var cost = 0;
    var total = 0;
    if (itemId > 0) {
        var existing = $('#mixedCostModal').data('componentCostMap') || {};
        cost = parseFloat(existing[itemId] || 0);
    }
    total = qty * cost;
    $(tr).find('.mixed-component-cost').val(itemId > 0 ? cost.toFixed(6) : '');
    $(tr).find('.mixed-component-total').val(itemId > 0 ? total.toFixed(6) : '');
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

function ensureYieldUomOption(value) {
    if (!value) return;
    var select = $('#mixed-yield-uom');
    if (select.find('option[value="' + value.replace(/"/g, '\\"') + '"]').length === 0) {
        select.append('<option value="' + value + '">' + value + '</option>');
    }
    select.val(value);
}

function initInstructionsEditor(html) {
    if (typeof tinymce !== 'undefined') {
        var existing = tinymce.get('mixed-instructions');
        if (existing) {
            existing.remove();
        }
        init_editor('#mixed-instructions', {
            height: 220,
            min_height: 220,
            menubar: false,
            statusbar: false,
            plugins: ['lists', 'table'],
            toolbar: 'bold italic | bullist numlist | table',
            setup: function (ed) {
                ed.on('init', function () {
                    ed.setContent(html || '');
                });
            }
        });
    } else {
        $('#mixed-instructions').val(html || '');
    }
}

function getInstructionsContent() {
    if (typeof tinymce !== 'undefined') {
        var ed = tinymce.get('mixed-instructions');
        if (ed) {
            return ed.getContent();
        }
    }
    return $('#mixed-instructions').val();
}

$('#mixedCostModal').on('hidden.bs.modal', function () {
    if (typeof tinymce !== 'undefined') {
        var ed = tinymce.get('mixed-instructions');
        if (ed) {
            ed.remove();
        }
    }
});

function openMixedCostDialog(mixedId) {
    $('#mixed-cost-id').val(mixedId || '');
    $('#mixed-cost-title').text(mixedId ? 'Edit Mixed Ingredients Cost' : 'New Mixed Ingredients Cost');
    $('#mixed-components-body').html('');
    $('#mixed-total-cost').val('0.000000');
    $('#mixed-cost-per-unit').val('0.000000');
    $('#mixed-total-units').val('1');
    $('#mixed-yield-uom').val('');
    $('#mixed-item-id').val('');
    $('#mixed-item-name').val('');
    $('#mixedCostModal').data('componentCostMap', mixedIngredientCostMap());

    if (!mixedId) {
        initInstructionsEditor('');
        addMixedComponentRow();
        recomputeMixedSummary();
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
        var costMap = mixedIngredientCostMap();
        for (var j = 0; j < components.length; j++) {
            costMap[components[j].component_item_id] = parseFloat(components[j].cost_per_unit || 0);
        }
        $('#mixedCostModal').data('componentCostMap', costMap);
        $('#mixed-item-id').val(mixed.item_id || '');
        $('#mixed-item-name').val(mixed.sku_name || '');
        $('#mixed-total-cost').val(parseFloat(mixed.total_cost || 0).toFixed(6));
        $('#mixed-cost-per-unit').val(parseFloat(mixed.cost_per_unit || 0).toFixed(6));
        $('#mixed-total-units').val(mixed.total_units || 1);
        ensureYieldUomOption(mixed.yield_uom || '');
        initInstructionsEditor(mixed.instructions || '');
        for (var k = 0; k < components.length; k++) {
            addMixedComponentRow(components[k]);
        }
        if (!components.length) addMixedComponentRow();
        recomputeMixedSummary();
        $('#mixedCostModal').modal('show');
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function saveMixedCostDetail(form) {
    var payload = {
        item_id: parseInt($('#mixed-item-id').val() || 0, 10),
        item_name: $('#mixed-item-name').val(),
        total_units: $('#mixed-total-units').val(),
        yield_uom: $('#mixed-yield-uom').val(),
        instructions: getInstructionsContent(),
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
