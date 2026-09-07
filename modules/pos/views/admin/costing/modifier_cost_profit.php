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
#modifierCostModal .modal-dialog {
    width: 95%;
    max-width: 950px;
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
                                    Reference only — defines what ingredients/packaging a modifier implies (with serving size/unit) for cost
                                    awareness. Does not affect any product's Total Cost/Profit Margin or the POS recipe view; those are still
                                    driven entirely by each product's own recipe (Alternate For / Requires).
                                </p>
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
                            <div class="col-md-8">
                                <input type="text" id="filter-search" class="form-control" placeholder="Search modifier or group..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-4 text-right text-muted" style="padding-top:6px;">
                                <span id="row-count"><?php echo count($items); ?> modifiers</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="modifier-cost-profit-table">
                                <thead>
                                    <tr>
                                        <th>Modifier Group</th>
                                        <th>Modifier</th>
                                        <th style="width:140px;">Price Adjustment (RM)</th>
                                        <th style="width:160px;">Reference Ingredient Cost (RM)</th>
                                        <th style="width:110px;">Ingredients</th>
                                        <th style="width:90px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item) { ?>
                                    <tr class="modifier-row"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['group_name'] ?? '') . ' ' . ($item['modifier_name'] ?? ''))); ?>">
                                        <td><?php echo htmlspecialchars($item['group_name'] ?? '-'); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['modifier_name'] ?? ''); ?></strong></td>
                                        <td class="text-right"><?php echo number_format((float)($item['price_adjustment'] ?? 0), 2); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['reference_cost'] ?? 0), 4); ?></td>
                                        <td class="text-center"><?php echo (int)($item['ingredient_count'] ?? 0); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-info btn-xs" onclick="openModifierCostDialog(<?php echo (int)$item['id']; ?>)">
                                                <i class="fa fa-edit"></i> Edit
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

<!-- Modifier Cost Modal -->
<div class="modal fade" id="modifierCostModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Modifier Recipe Reference</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modifier-cost-modifier-id">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-condensed">
                            <tr>
                                <th style="width:140px;">Modifier Group:</th>
                                <td id="modifier-detail-group">-</td>
                            </tr>
                            <tr>
                                <th>Modifier:</th>
                                <td id="modifier-detail-name">-</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Price Adjustment (RM)</th>
                                    <th>Reference Ingredient Cost (RM)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td id="modifier-summary-price" class="text-right">0.00</td>
                                    <td id="modifier-summary-cost" class="text-right">0.0000</td>
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
                                    <th style="width:110px;">Serving Qty</th>
                                    <th style="width:130px;">Cost Per Unit (RM)</th>
                                    <th style="width:130px;">Total Cost (RM)</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="modifier-section-mixed-ingredients"></tbody>
                        </table>
                        <button type="button" class="btn btn-success btn-sm" onclick="addModifierComponentRow('mixed_ingredients')"><i class="fa fa-plus"></i> Add Mixed Ingredient</button>
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
                                    <th style="width:110px;">Serving Qty</th>
                                    <th style="width:130px;">Cost Per Unit (RM)</th>
                                    <th style="width:130px;">Total Cost (RM)</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="modifier-section-ingredients"></tbody>
                        </table>
                        <button type="button" class="btn btn-success btn-sm" onclick="addModifierComponentRow('ingredients')"><i class="fa fa-plus"></i> Add Ingredient</button>
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
                                    <th style="width:110px;">Serving Qty</th>
                                    <th style="width:130px;">Cost Per Unit (RM)</th>
                                    <th style="width:130px;">Total Cost (RM)</th>
                                    <th style="width:50px;"></th>
                                </tr>
                            </thead>
                            <tbody id="modifier-section-packaging"></tbody>
                        </table>
                        <button type="button" class="btn btn-success btn-sm" onclick="addModifierComponentRow('packaging')"><i class="fa fa-plus"></i> Add Packaging</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="saveModifierCostDetail()"><i class="fa fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var getModifierDetailUrl = '<?php echo admin_url('pos/ajax_get_modifier_cost_profit_detail'); ?>';
var saveModifierDetailUrl = '<?php echo admin_url('pos/ajax_save_modifier_cost_profit_detail'); ?>';

var modifierSectionItems = {
    mixed_ingredients: <?php echo json_encode(array_values($mixed_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    ingredients: <?php echo json_encode(array_values($ingredient_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    packaging: <?php echo json_encode(array_values($packaging_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
};

function modifierSectionCostMap() {
    var map = {};
    ['mixed_ingredients', 'ingredients', 'packaging'].forEach(function (section) {
        modifierSectionItems[section].forEach(function (item) {
            map[item.id] = parseFloat(item.cost_per_unit || 0);
        });
    });
    return map;
}

function modifierItemOptions(selectedId, section) {
    var items = modifierSectionItems[section] || [];
    var html = '<option value="">-- Select --</option>';
    for (var i = 0; i < items.length; i++) {
        var item = items[i];
        var selected = parseInt(item.id, 10) === parseInt(selectedId || 0, 10) ? ' selected' : '';
        var label = (item.sku_code ? '[' + item.sku_code + '] ' : '') + item.sku_name;
        html += '<option value="' + item.id + '"' + selected + '>' + label + '</option>';
    }
    return html;
}

function modifierServingLabelFor(section, itemId) {
    var items = modifierSectionItems[section] || [];
    for (var i = 0; i < items.length; i++) {
        if (parseInt(items[i].id, 10) === parseInt(itemId || 0, 10)) {
            return items[i].serving_label || '';
        }
    }
    return '';
}

function addModifierComponentRow(section, row) {
    row = row || {};
    var qtyValue = (row.quantity !== undefined && row.quantity !== null && row.quantity !== '') ? row.quantity : 1;
    var tr = document.createElement('tr');
    tr.className = 'modifier-component-row';
    tr.setAttribute('data-section', section);
    tr.innerHTML = ''
        + '<td><select class="form-control input-sm modifier-component-item selectpicker-inline" data-live-search="true">' + modifierItemOptions(row.component_item_id || 0, section) + '</select></td>'
        + '<td><input type="number" step="0.0001" class="form-control input-sm modifier-component-qty" value="' + qtyValue + '"></td>'
        + '<td>'
        +   '<input type="number" step="0.0001" class="form-control input-sm modifier-component-serving-qty" value="' + (row.serving_quantity != null ? row.serving_quantity : '') + '">'
        +   '<small class="modifier-component-serving-hint text-muted"></small>'
        + '</td>'
        + '<td><input type="text" class="form-control input-sm modifier-component-cost" value="' + (row.cost_per_unit != null ? row.cost_per_unit : '') + '" readonly></td>'
        + '<td><input type="text" class="form-control input-sm modifier-component-total" value="' + (row.total_cost != null ? row.total_cost : '') + '" readonly></td>'
        + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="removeModifierComponentRow(this)"><i class="fa fa-times"></i></button></td>';
    document.getElementById('modifier-section-' + section.replace('_', '-')).appendChild(tr);
    if (typeof $().selectpicker !== 'undefined') {
        $(tr).find('.selectpicker-inline').selectpicker();
    }
    recomputeModifierRow(tr);
}

$('#modifierCostModal').on('change', '.modifier-component-item', function () {
    var tr = $(this).closest('tr');
    setTimeout(function () {
        recomputeModifierRow(tr);
        recomputeModifierSummary();
    }, 0);
});
$('#modifierCostModal').on('change keyup', '.modifier-component-qty', function () {
    var tr = $(this).closest('tr');
    recomputeModifierRow(tr);
    recomputeModifierSummary();
});

function removeModifierComponentRow(btn) {
    $(btn).closest('tr').remove();
    recomputeModifierSummary();
}

function recomputeModifierRow(tr) {
    var section = $(tr).data('section');
    var itemId = parseInt($(tr).find('select.modifier-component-item').val() || 0, 10);
    var qty = parseFloat($(tr).find('.modifier-component-qty').val() || 0);
    var costMap = $('#modifierCostModal').data('componentCostMap') || {};
    var cost = parseFloat(costMap[itemId] || 0);
    var total = itemId > 0 ? qty * cost : 0;
    $(tr).find('.modifier-component-cost').val(itemId > 0 ? cost.toFixed(4) : '');
    $(tr).find('.modifier-component-total').val(itemId > 0 ? total.toFixed(4) : '');

    var servingLabel = modifierServingLabelFor(section, itemId);
    var $servingQty = $(tr).find('.modifier-component-serving-qty');
    var $hint = $(tr).find('.modifier-component-serving-hint');
    if (servingLabel) {
        $servingQty.prop('disabled', false);
        $hint.text(servingLabel).show();
    } else {
        $servingQty.prop('disabled', true).val('');
        $hint.text('Set a Serving Unit on this ingredient first').show();
    }
}

function recomputeModifierSummary() {
    var totalCost = 0;
    $('.modifier-component-row').each(function () {
        totalCost += parseFloat($(this).find('.modifier-component-total').val() || 0);
    });
    $('#modifier-summary-cost').text(totalCost.toFixed(4));
}

function openModifierCostDialog(modifierId) {
    $('#modifier-cost-modifier-id').val(modifierId);
    $('#modifier-section-mixed-ingredients, #modifier-section-ingredients, #modifier-section-packaging').html('');
    $('#modifier-detail-group, #modifier-detail-name').text('-');
    $('#modifier-summary-price').text('0.00');
    $('#modifier-summary-cost').text('0.0000');

    $.post(getModifierDetailUrl, { modifier_id: modifierId }, function (res) {
        if (!(res && res.success && res.data && res.data.modifier)) {
            alert_float('danger', (res && res.error) || 'Failed to load detail');
            return;
        }
        var data = res.data;
        var modifier = data.modifier;
        var sections = data.sections || {};
        var costMap = modifierSectionCostMap();
        ['mixed_ingredients', 'ingredients', 'packaging'].forEach(function (sectionName) {
            var rows = sections[sectionName] || [];
            for (var i = 0; i < rows.length; i++) {
                costMap[rows[i].component_item_id] = parseFloat(rows[i].cost_per_unit || 0);
            }
        });
        $('#modifierCostModal').data('componentCostMap', costMap);
        $('#modifier-detail-group').text(modifier.group_name || '-');
        $('#modifier-detail-name').text(modifier.name || '-');
        $('#modifier-summary-price').text(parseFloat(modifier.price_adjustment || 0).toFixed(2));

        ['mixed_ingredients', 'ingredients', 'packaging'].forEach(function (sectionName) {
            var rows = sections[sectionName] || [];
            for (var i = 0; i < rows.length; i++) addModifierComponentRow(sectionName, rows[i]);
        });

        recomputeModifierSummary();
        $('#modifierCostModal').modal('show');
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function saveModifierCostDetail() {
    var payload = {
        mixed_ingredients: [],
        ingredients: [],
        packaging: []
    };

    $('.modifier-component-row').each(function () {
        var section = $(this).data('section');
        payload[section].push({
            component_item_id: parseInt($(this).find('select.modifier-component-item').val() || 0, 10),
            quantity: $(this).find('.modifier-component-qty').val(),
            serving_quantity: $(this).find('.modifier-component-serving-qty').val(),
            note: ''
        });
    });

    $.post(saveModifierDetailUrl, {
        modifier_id: $('#modifier-cost-modifier-id').val(),
        sections: JSON.stringify(payload)
    }, function (res) {
        if (res && res.success) {
            $('#modifierCostModal').modal('hide');
            alert_float('success', 'Saved. Reloading...');
            setTimeout(function () { window.location.reload(); }, 600);
        } else {
            alert_float('danger', (res && res.error) || 'Failed to save');
        }
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function applyFilters() {
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.modifier-row').each(function () {
        var $r = $(this);
        var ok = !q || ('' + ($r.data('search') || '')).indexOf(q) >= 0;
        $r.toggle(ok);
        if (ok) visible++;
    });
    $('#row-count').text(visible + ' modifiers');
}
</script>
