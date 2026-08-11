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
                                <p class="text-muted small">Define mixed ingredients / prep recipes with component breakdowns. Yield and prep time feed into product costs.</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-info" onclick="openMixedModal()">
                                    <i class="fa fa-plus"></i> New Mixed Ingredient
                                </button>
                            </div>
                        </div>
                        <hr />

                        <?php if (empty($mixed)) { ?>
                            <p class="text-muted text-center mtop30">No mixed ingredients yet. Click <strong>New Mixed Ingredient</strong> to create one.</p>
                        <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Item Code</th>
                                        <th>Yield</th>
                                        <th>UOM</th>
                                        <th>Prep (min)</th>
                                        <th>Components</th>
                                        <th>Cost/Unit</th>
                                        <th style="width:220px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mixed as $m) {
                                        $comp_count = 0;
                                        $components = $this->db->where('mixed_ingredient_id', (int)$m['id'])
                                            ->get(db_prefix() . 'pos_mixed_components')->result_array();
                                        $comp_count = count($components);
                                        $cost = (float)($m['cost_per_unit'] ?? 0);
                                    ?>
                                    <tr id="mixed-row-<?php echo (int)$m['id']; ?>">
                                        <td><?php echo (int)$m['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($m['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($m['sku_code'] ?? ''); ?></td>
                                        <td><?php echo number_format((float)($m['yield'] ?? 0), 2); ?></td>
                                        <td><?php echo htmlspecialchars($m['yield_uom'] ?? '-'); ?></td>
                                        <td><?php echo (int)($m['prep_minutes'] ?? 0); ?></td>
                                        <td><?php echo $comp_count; ?></td>
                                        <td class="text-right mixed-cost-cell" data-mid="<?php echo (int)$m['id']; ?>">
                                            <strong><?php echo number_format($cost, 4); ?></strong>
                                        </td>
                                        <td>
                                            <button class="btn btn-default btn-sm" onclick='openMixedModal(<?php echo json_encode([
                                                "id" => (int)$m["id"],
                                                "item_id" => (int)$m["item_id"],
                                                "yield" => $m["yield"],
                                                "yield_uom" => $m["yield_uom"],
                                                "prep_minutes" => (int)$m["prep_minutes"],
                                                "instructions" => $m["instructions"] ?? "",
                                                "components" => $components
                                            ], JSON_HEX_TAG); ?>)'><i class="fa fa-pencil"></i> Edit</button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteMixed(<?php echo (int)$m['id']; ?>)"><i class="fa fa-trash"></i> Delete</button>
                                            <button class="btn btn-info btn-sm" onclick="calcMixed(<?php echo (int)$m['id']; ?>)"><i class="fa fa-calculator"></i> Calc</button>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mixedModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form onsubmit="return saveMixed(this)">
                <input type="hidden" name="id" id="mixed-id" value="">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="mixed-modal-title">New Mixed Ingredient</h4>
                </div>
                <div class="modal-body">

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Item (as mixed ingredient) <span class="text-danger">*</span></label>
                            <select name="item_id" id="mixed-item-id" class="form-control selectpicker" data-live-search="true" required>
                                <option value="">-- Select item --</option>
                                <?php foreach ($all_items as $ai) { ?>
                                    <option value="<?php echo (int)$ai['id']; ?>"><?php echo htmlspecialchars(($ai['sku_code'] ? '[' . $ai['sku_code'] . '] ' : '') . $ai['sku_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Yield</label>
                            <input type="number" step="0.0001" name="yield" class="form-control" value="1">
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Yield UOM</label>
                            <input type="text" name="yield_uom" class="form-control" placeholder="g, ml, pcs">
                        </div>
                        <div class="col-md-2 form-group">
                            <label>Prep (min)</label>
                            <input type="number" name="prep_minutes" class="form-control" value="0" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea name="instructions" class="form-control" rows="2" placeholder="Optional prep instructions..."></textarea>
                    </div>

                    <hr />
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="no-margin-top"><strong>Components</strong></h5>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="button" class="btn btn-success btn-sm" onclick="addComponentRow()">
                                <i class="fa fa-plus"></i> Add Component
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive mtop10">
                        <table class="table table-bordered" id="components-table">
                            <thead>
                                <tr>
                                    <th style="width:120px;">Type</th>
                                    <th>Component Item</th>
                                    <th style="width:120px;">Qty</th>
                                    <th style="width:100px;">UOM</th>
                                    <th>Note</th>
                                    <th style="width:60px;"></th>
                                </tr>
                            </thead>
                            <tbody id="components-body">
                            </tbody>
                        </table>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info" id="mixed-save-btn"><i class="fa fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var saveMixedUrl = '<?php echo admin_url('pos/ajax_save_mixed_ingredient'); ?>';
var allItems = <?php echo json_encode(array_map(function($a) {
    return ["id" => (int)$a["id"], "sku_code" => $a["sku_code"], "sku_name" => $a["sku_name"]];
}, $all_items)); ?>;

function itemOptions(selectedId) {
    var html = '<option value="">-- Select --</option>';
    for (var i = 0; i < allItems.length; i++) {
        var it = allItems[i];
        var sel = (selectedId && parseInt(it.id, 10) === parseInt(selectedId, 10)) ? ' selected' : '';
        var label = (it.sku_code ? '[' + it.sku_code + '] ' : '') + it.sku_name;
        html += '<option value="' + it.id + '"' + sel + '>' + label + '</option>';
    }
    return html;
}

function addComponentRow(comp) {
    comp = comp || {};
    var tr = document.createElement('tr');
    tr.className = 'component-row';
    tr.innerHTML = '<td>' +
        '<select name="component_type" class="form-control input-sm">' +
        '<option value="item"' + (comp.component_type === 'item' ? ' selected' : '') + '>Item</option>' +
        '<option value="mixed"' + (comp.component_type === 'mixed' ? ' selected' : '') + '>Mixed</option>' +
        '</select>' +
        '</td>' +
        '<td><select name="component_item_id" class="form-control input-sm selectpicker-inline" data-live-search="true">' + itemOptions(comp.component_item_id || null) + '</select></td>' +
        '<td><input type="number" step="0.0001" name="quantity" class="form-control input-sm" value="' + (comp.quantity || 0) + '"></td>' +
        '<td><input type="text" name="uom" class="form-control input-sm" value="' + (comp.uom || '') + '" placeholder="g,ml"></td>' +
        '<td><input type="text" name="note" class="form-control input-sm" value="' + (comp.note || '') + '"></td>' +
        '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="this.closest(\'tr\').remove()"><i class="fa fa-times"></i></button></td>';
    document.getElementById('components-body').appendChild(tr);
}

function openMixedModal(data) {
    data = data || {};
    var title = data && data.id ? ('Edit Mixed Ingredient #' + data.id) : 'New Mixed Ingredient';
    document.getElementById('mixed-modal-title').textContent = title;
    document.getElementById('mixed-id').value = data.id || '';

    var form = document.querySelector('#mixedModal form');
    form.reset();
    form.querySelector('[name=id]').value = data.id || '';
    form.querySelector('[name=item_id]').value = data.item_id || '';
    form.querySelector('[name=yield]').value = data.yield != null ? data.yield : 1;
    form.querySelector('[name=yield_uom]').value = data.yield_uom || '';
    form.querySelector('[name=prep_minutes]').value = data.prep_minutes || 0;
    form.querySelector('[name=instructions]').value = data.instructions || '';

    document.getElementById('components-body').innerHTML = '';
    var comps = data.components || [];
    if (comps.length === 0) {
        addComponentRow();
    } else {
        for (var i = 0; i < comps.length; i++) addComponentRow(comps[i]);
    }
    if (typeof $().selectpicker !== 'undefined') {
        setTimeout(function () {
            $('#mixedModal .selectpicker').selectpicker('refresh');
        }, 50);
    }
    $('#mixedModal').modal('show');
}

function collectComponents() {
    var rows = document.querySelectorAll('#components-body .component-row');
    var out = [];
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        out.push({
            component_type: r.querySelector('[name=component_type]').value,
            component_item_id: parseInt(r.querySelector('[name=component_item_id]').value || 0, 10),
            quantity: r.querySelector('[name=quantity]').value,
            uom: r.querySelector('[name=uom]').value,
            note: r.querySelector('[name=note]').value
        });
    }
    return out;
}

function saveMixed(form) {
    var fd = $(form).serializeArray();
    fd.push({ name: 'components', value: JSON.stringify(collectComponents()) });
    var btn = document.getElementById('mixed-save-btn');
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving...';
    $.post(saveMixedUrl, $.param(fd), function (res) {
        btn.disabled = false;
        btn.innerHTML = orig;
        if (res && res.success) {
            $('#mixedModal').modal('hide');
            alert_float('success', 'Saved. Reloading...');
            setTimeout(function () { location.reload(); }, 700);
        } else {
            alert_float('danger', (res && res.message) || (res && res.error) || 'Save failed');
        }
    }, 'json').fail(function () {
        btn.disabled = false;
        btn.innerHTML = orig;
        alert_float('danger', 'Network error');
    });
    return false;
}

function deleteMixed(id) {
    if (!confirm('Delete mixed ingredient #' + id + '? This cannot be undone.')) return;
    $.post('<?php echo admin_url('pos/ajax_save_mixed_ingredient'); ?>', {
        id: id, item_id: 0, components: '[]', __delete: 1
    }, function () { location.reload(); }).fail(function () { location.reload(); });
}

function calcMixed(id) {
    $.post('<?php echo admin_url('pos/ajax_save_mixed_ingredient'); ?>', {
        id: id, item_id: 0, components: '[]', __recalc_only: 1
    }, function (res) {
        if (res && res.success && typeof res.new_cost_per_unit === 'number') {
            var cell = document.querySelector('.mixed-cost-cell[data-mid="' + id + '"] strong');
            if (cell) cell.textContent = parseFloat(res.new_cost_per_unit).toFixed(4);
            alert_float('success', 'Cost recalculated');
        } else {
            location.reload();
        }
    }, 'json').fail(function () { location.reload(); });
}
</script>
