<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <h4 class="no-margin-top pull-left"><?php echo $title; ?></h4>
                            <?php if (has_permission('pos', '', 'create')) { ?>
                            <button class="btn btn-info pull-right" onclick="openProductModal()">
                                <i class="fa fa-plus"></i> Add Product
                            </button>
                            <?php } ?>
                        </div>
                        <hr />
                        <table class="table dt-table" id="pos-products-table">
                            <thead>
                                <tr>
                                    <th>SKU Name</th>
                                    <th>SKU Code</th>
                                    <th>Group</th>
                                    <th>Sub Group</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Modifiers</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item) { ?>
                                <tr id="product-row-<?php echo $item['id']; ?>">
                                    <td><?php echo htmlspecialchars($item['sku_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['group_name'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($item['sub_group_name'] ?? '—'); ?></td>
                                    <td><?php echo number_format((float)$item['rate'], 2); ?></td>
                                    <td>
                                        <?php if ((int)$item['active'] === 1) { ?>
                                            <span class="label label-success">Active</span>
                                        <?php } else { ?>
                                            <span class="label label-default">Inactive</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span id="modifier-count-<?php echo $item['id']; ?>" class="text-muted small">—</span>
                                    </td>
                                    <td class="text-right" style="white-space:nowrap;">
                                        <button class="btn btn-sm btn-default"
                                            onclick="openModifiersModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['sku_name'])); ?>')">
                                            <i class="fa fa-sliders"></i> Modifiers
                                        </button>
                                        <?php if (has_permission('pos', '', 'edit')) { ?>
                                        <button class="btn btn-sm btn-primary"
                                            onclick="editProduct(<?php echo $item['id']; ?>)">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        <?php } ?>
                                        <?php if (has_permission('pos', '', 'delete')) { ?>
                                        <button class="btn btn-sm btn-danger"
                                            onclick="deleteProduct(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['sku_name'])); ?>')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        <?php } ?>
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

<!-- Add / Edit Product Modal -->
<div class="modal fade" id="product-modal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="product-modal-title">Add Product</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="product-edit-id" value="">

                <div class="form-group">
                    <label>SKU Name <span class="text-danger">*</span></label>
                    <input type="text" id="product-sku-name" class="form-control" placeholder="e.g. Iced Americano">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>SKU Code <small class="text-muted">(auto-generated if empty)</small></label>
                            <input type="text" id="product-sku-code" class="form-control" placeholder="e.g. ICED-AMR">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Price (RM) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon">RM</span>
                                <input type="number" id="product-rate" class="form-control" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Group</label>
                            <select id="product-group-id" class="form-control selectpicker" data-live-search="true" title="Select group...">
                                <option value="">— None —</option>
                                <?php foreach ($item_groups as $g) { ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sub Group</label>
                            <select id="product-sub-group" class="form-control selectpicker" data-live-search="true" title="Select sub group...">
                                <option value="">— None —</option>
                                <?php foreach ($sub_groups as $sg) { ?>
                                <option value="<?php echo $sg['id']; ?>" data-group="<?php echo $sg['group_id']; ?>"><?php echo htmlspecialchars($sg['sub_group_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select id="product-active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="product-save-btn" onclick="saveProduct()">
                    <i class="fa fa-check"></i> <span id="product-save-label">Add Product</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Item Modifiers Modal -->
<div class="modal fade" id="item-modifiers-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Modifiers — <span id="modal-item-name" class="bold"></span></h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modal-item-id">

                <!-- Assigned modifier groups -->
                <h5>Assigned Modifier Groups</h5>
                <table class="table table-bordered" id="assigned-table">
                    <thead>
                        <tr>
                            <th>Group Name</th>
                            <th>Type</th>
                            <th>Sort</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="assigned-tbody">
                        <tr id="assigned-empty"><td colspan="4" class="text-muted text-center">No modifier groups assigned.</td></tr>
                    </tbody>
                </table>

                <hr />

                <!-- Add a modifier group -->
                <h5>Add Modifier Group</h5>
                <div class="row">
                    <div class="col-md-6">
                        <select id="add-modifier-group-id" class="form-control selectpicker" data-live-search="true" title="Select modifier group...">
                            <?php foreach ($modifier_groups as $mg) { ?>
                            <option value="<?php echo $mg['id']; ?>"><?php echo htmlspecialchars($mg['name']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" id="add-sort-order" class="form-control" placeholder="Sort order" value="0" min="0">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-info btn-block" onclick="assignGroup()">
                            <i class="fa fa-plus"></i> Assign
                        </button>
                    </div>
                </div>

                <?php if (empty($modifier_groups)) { ?>
                <p class="text-muted mtop10">
                    No modifier groups found. <a href="<?php echo admin_url('pos/modifiers'); ?>">Create one first.</a>
                </p>
                <?php } ?>

                <hr />

                <!-- Individual item modifiers -->
                <h5>Individual Modifiers <small class="text-muted">applied only to this item</small></h5>
                <table class="table table-bordered" id="individual-modifiers-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Options</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="individual-tbody">
                        <tr id="individual-empty"><td colspan="4" class="text-muted text-center">No individual modifiers.</td></tr>
                    </tbody>
                </table>

                <!-- Add / edit individual modifier inline form -->
                <div id="indiv-form-panel" style="background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:14px; margin-top:10px;">
                    <input type="hidden" id="indiv-edit-id" value="">
                    <div class="row" style="margin-bottom:8px;">
                        <div class="col-md-6">
                            <label class="text-muted small">Modifier name <span class="text-danger">*</span></label>
                            <input type="text" id="indiv-name" class="form-control" placeholder="e.g. Cup Size, Sugar Level">
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Selection type</label>
                            <select id="indiv-selection-type" class="form-control">
                                <option value="single">Single — pick one</option>
                                <option value="multiple">Multiple — pick many</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="text-muted small">Sort</label>
                            <input type="number" id="indiv-sort" class="form-control" value="0" min="0">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom:4px; padding:0 15px;">
                        <div class="col-md-7"><label class="text-muted small">Option name</label></div>
                        <div class="col-md-4"><label class="text-muted small">Price adj. (RM)</label></div>
                        <div class="col-md-1"></div>
                    </div>
                    <div id="indiv-options-list"></div>
                    <div class="mtop6">
                        <button type="button" class="btn btn-link btn-sm" onclick="indivAddOption()">
                            <i class="fa fa-plus-circle"></i> Add option
                        </button>
                    </div>

                    <div class="mtop10 text-right">
                        <button type="button" class="btn btn-default btn-sm" onclick="indivResetForm()">Clear</button>
                        &nbsp;
                        <button type="button" class="btn btn-info btn-sm" onclick="saveIndividualModifier()">
                            <i class="fa fa-check"></i> <span id="indiv-save-label">Add Modifier</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';
var _allSubGroups = <?php echo json_encode(array_map(function($sg) {
    return ['id' => $sg['id'], 'name' => $sg['sub_group_name'], 'group_id' => $sg['group_id'] ?? null];
}, $sub_groups)); ?>;

$(function () {
    $('#pos-products-table').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: [6, 7] }]
    });
    loadAllModifierCounts();

    $('#product-group-id').on('change', function () {
        filterSubGroups('', '');
    });
});

// ============================================================
// Product CRUD
// ============================================================

function openProductModal(id) {
    $('#product-edit-id').val('');
    $('#product-sku-name').val('');
    $('#product-sku-code').val('');
    $('#product-rate').val('');
    $('#product-group-id').selectpicker('val', '');
    filterSubGroups('', '');
    $('#product-active').val('1');
    $('#product-modal-title').text('Add Product');
    $('#product-save-label').text('Add Product');
    $('#product-modal').modal('show');
    $('#product-sku-name').focus();
}

function editProduct(id) {
    $.getJSON(ADMIN_URL + 'pos/ajax_get_pos_product/' + id, function (resp) {
        if (!resp.success) { alert(resp.message || 'Failed to load product'); return; }
        var p = resp.data;
        $('#product-edit-id').val(p.id);
        $('#product-sku-name').val(p.sku_name);
        $('#product-sku-code').val(p.sku_code);
        $('#product-rate').val(parseFloat(p.rate).toFixed(2));
        $('#product-active').val(p.active == 1 ? '1' : '0');
        $('#product-modal-title').text('Edit Product');
        $('#product-save-label').text('Save Changes');

        $('#product-group-id').selectpicker('val', p.group_id || '');
        filterSubGroups('', p.sub_group || '');

        $('#product-modal').modal('show');
        $('#product-sku-name').focus();
    });
}

function filterSubGroups(groupId, selectedSubGroup) {
    var $sel = $('#product-sub-group');
    $sel.empty().append('<option value="">— None —</option>');

    $.each(_allSubGroups, function (i, sg) {
        if (!groupId || sg.group_id == groupId || sg.group_id === null || sg.group_id === '') {
            $sel.append('<option value="' + sg.id + '">' + $('<span>').text(sg.name).html() + '</option>');
        }
    });

    $sel.selectpicker('refresh');
    if (selectedSubGroup) {
        $sel.selectpicker('val', String(selectedSubGroup));
    }
}

function saveProduct() {
    var id       = $('#product-edit-id').val();
    var skuName  = $.trim($('#product-sku-name').val());
    var skuCode  = $.trim($('#product-sku-code').val());
    var rate     = $('#product-rate').val();
    var groupId  = $('#product-group-id').val();
    var subGroup = $('#product-sub-group').val();
    var active   = $('#product-active').val();

    if (!skuName) { alert('Product name is required'); $('#product-sku-name').focus(); return; }
    if (rate === '' || isNaN(parseFloat(rate))) { alert('Price is required'); $('#product-rate').focus(); return; }

    var btn = $('#product-save-btn').prop('disabled', true);

    $.post(ADMIN_URL + 'pos/ajax_save_pos_product', {
        id:         id,
        sku_name:   skuName,
        sku_code:   skuCode,
        rate:       rate,
        group_id:   groupId,
        sub_group:  subGroup,
        active:     active,
    }, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            $('#product-modal').modal('hide');
            location.reload();
        } else {
            alert(resp.message || 'Failed to save product');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

function deleteProduct(id, name) {
    if (!confirm('Delete product "' + name + '"?\n\nThis cannot be undone.')) return;

    $.post(ADMIN_URL + 'pos/ajax_delete_pos_product', { id: id }, function (resp) {
        if (resp.success) {
            $('#product-row-' + id).fadeOut(300, function () { $(this).remove(); });
        } else {
            alert(resp.message || 'Failed to delete product.');
        }
    }, 'json');
}

// ============================================================
// Modifier counts
// ============================================================

function loadAllModifierCounts() {
    <?php foreach ($items as $item) { ?>
    loadModifierCount(<?php echo $item['id']; ?>);
    <?php } ?>
}

function loadModifierCount(itemId) {
    var groupsDone = false, individualDone = false;
    var groupCount = 0, individualCount = 0;

    function renderCount() {
        if (!groupsDone || !individualDone) return;
        var el = $('#modifier-count-' + itemId);
        if (groupCount === 0 && individualCount === 0) {
            el.html('<span class="text-muted">None</span>');
        } else {
            var parts = [];
            if (groupCount > 0) parts.push('<span class="label label-info">' + groupCount + ' group' + (groupCount > 1 ? 's' : '') + '</span>');
            if (individualCount > 0) parts.push('<span class="label label-warning">' + individualCount + ' individual</span>');
            el.html(parts.join(' '));
        }
    }

    $.getJSON(ADMIN_URL + 'pos/ajax_get_item_modifiers/' + itemId, function(resp) {
        if (resp.success) groupCount = resp.data.length;
        groupsDone = true;
        renderCount();
    });

    $.getJSON(ADMIN_URL + 'pos/ajax_get_item_individual_modifiers/' + itemId, function(resp) {
        if (resp.success) individualCount = resp.data.length;
        individualDone = true;
        renderCount();
    });
}

// ============================================================
// Modifiers modal
// ============================================================

function openModifiersModal(itemId, itemName) {
    $('#modal-item-id').val(itemId);
    $('#modal-item-name').text(itemName);
    indivResetForm();
    $('#item-modifiers-modal').modal('show');
    loadAssigned(itemId);
    loadIndividualModifiers(itemId);
}

function loadAssigned(itemId) {
    $.getJSON(ADMIN_URL + 'pos/ajax_get_item_modifiers/' + itemId, function(resp) {
        renderAssigned(resp.data);
    });
}

function renderAssigned(rows) {
    var tbody = $('#assigned-tbody');
    tbody.empty();

    if (!rows || rows.length === 0) {
        tbody.html('<tr id="assigned-empty"><td colspan="4" class="text-muted text-center">No modifier groups assigned.</td></tr>');
        return;
    }

    $.each(rows, function(i, row) {
        var typeBadge = row.selection_type === 'single'
            ? '<span class="label label-default">Single</span>'
            : '<span class="label label-primary">Multiple</span>';

        tbody.append(
            '<tr id="assigned-row-' + row.modifier_group_id + '">' +
            '<td>' + $('<span>').text(row.name).html() + '</td>' +
            '<td>' + typeBadge + '</td>' +
            '<td>' + row.sort_order + '</td>' +
            '<td class="text-center"><button class="btn btn-sm btn-danger" onclick="unassignGroup(' + row.modifier_group_id + ')">' +
            '<i class="fa fa-times"></i> Remove</button></td>' +
            '</tr>'
        );
    });
}

function assignGroup() {
    var itemId     = $('#modal-item-id').val();
    var groupId    = $('#add-modifier-group-id').val();
    var sortOrder  = $('#add-sort-order').val();

    if (!groupId) { alert('Please select a modifier group'); return; }

    $.post(ADMIN_URL + 'pos/ajax_assign_modifier_group', {
        item_id:           itemId,
        modifier_group_id: groupId,
        sort_order:        sortOrder
    }, function(resp) {
        if (resp.success) {
            renderAssigned(resp.data);
            loadModifierCount(itemId);
            $('#add-modifier-group-id').selectpicker('val', '');
            $('#add-sort-order').val(0);
        }
    }, 'json');
}

function unassignGroup(groupId) {
    var itemId = $('#modal-item-id').val();
    $.post(ADMIN_URL + 'pos/ajax_unassign_modifier_group', {
        item_id:           itemId,
        modifier_group_id: groupId
    }, function(resp) {
        if (resp.success) {
            renderAssigned(resp.data);
            loadModifierCount(itemId);
        }
    }, 'json');
}

// Individual modifiers

function loadIndividualModifiers(itemId) {
    $.getJSON(ADMIN_URL + 'pos/ajax_get_item_individual_modifiers/' + itemId, function(resp) {
        if (resp.success) {
            _indivRows = resp.data;
            renderIndividualModifiers(resp.data);
        }
    });
}

function renderIndividualModifiers(rows) {
    var tbody = $('#individual-tbody');
    tbody.empty();

    if (!rows || rows.length === 0) {
        tbody.html('<tr id="individual-empty"><td colspan="4" class="text-muted text-center">No individual modifiers.</td></tr>');
        return;
    }

    $.each(rows, function(i, row) {
        var typeBadge = row.selection_type === 'single'
            ? '<span class="label label-default">Single</span>'
            : '<span class="label label-primary">Multiple</span>';

        var optionNames = [];
        if (row.options && row.options.length) {
            $.each(row.options, function(j, opt) {
                var p = parseFloat(opt.price_adjustment);
                var pStr = p !== 0 ? ' (' + (p > 0 ? '+' : '') + p.toFixed(2) + ')' : '';
                optionNames.push($('<span>').text(opt.name).html() + '<small class="text-muted">' + pStr + '</small>');
            });
        }
        var optionsHtml = optionNames.length ? optionNames.join(', ') : '<em class="text-muted">No options</em>';

        tbody.append(
            '<tr id="individual-row-' + row.id + '">' +
            '<td><strong>' + $('<span>').text(row.name).html() + '</strong></td>' +
            '<td>' + typeBadge + '</td>' +
            '<td>' + optionsHtml + '</td>' +
            '<td class="text-center" style="white-space:nowrap;">' +
            '<button class="btn btn-xs btn-default" onclick="indivEditModifier(' + row.id + ')"><i class="fa fa-pencil"></i></button> ' +
            '<button class="btn btn-xs btn-danger" onclick="deleteIndividualModifier(' + row.id + ')"><i class="fa fa-times"></i></button>' +
            '</td>' +
            '</tr>'
        );
    });
}

var _indivRows = [];

function indivAddOption(name, price) {
    var row = $(
        '<div class="indiv-opt-row row" style="margin-bottom:4px;">' +
        '<div class="col-md-7"><input type="text" class="form-control indiv-opt-name" placeholder="Option name" value="' + (name ? $('<span>').text(name).html() : '') + '"></div>' +
        '<div class="col-md-4"><div class="input-group"><span class="input-group-addon">RM</span>' +
        '<input type="number" class="form-control indiv-opt-price" step="0.01" placeholder="0.00" value="' + (price !== undefined ? price : '0.00') + '"></div></div>' +
        '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest(\'.indiv-opt-row\').remove()">' +
        '<i class="fa fa-trash"></i></button></div>' +
        '</div>'
    );
    $('#indiv-options-list').append(row);
    row.find('.indiv-opt-name').focus();
}

function indivResetForm() {
    $('#indiv-edit-id').val('');
    $('#indiv-name').val('');
    $('#indiv-selection-type').val('single');
    $('#indiv-sort').val('0');
    $('#indiv-options-list').empty();
    $('#indiv-save-label').text('Add Modifier');
}

function indivEditModifier(id) {
    var row = null;
    $.each(_indivRows, function(i, r) { if (r.id == id) { row = r; return false; } });
    if (!row) return;

    $('#indiv-edit-id').val(row.id);
    $('#indiv-name').val(row.name);
    $('#indiv-selection-type').val(row.selection_type || 'single');
    $('#indiv-sort').val(row.sort_order || 0);
    $('#indiv-save-label').text('Update Modifier');

    $('#indiv-options-list').empty();
    if (row.options && row.options.length) {
        $.each(row.options, function(i, opt) {
            indivAddOption(opt.name, parseFloat(opt.price_adjustment).toFixed(2));
        });
    }
    $('#indiv-name').focus();
}

function saveIndividualModifier() {
    var itemId = $('#modal-item-id').val();
    var name   = $.trim($('#indiv-name').val());
    var type   = $('#indiv-selection-type').val();
    var sort   = $('#indiv-sort').val();
    var editId = $('#indiv-edit-id').val();

    if (!name) { alert('Modifier name is required'); $('#indiv-name').focus(); return; }

    var options = [];
    $('#indiv-options-list .indiv-opt-row').each(function() {
        var optName = $.trim($(this).find('.indiv-opt-name').val());
        if (optName) {
            options.push({ name: optName, price_adjustment: $(this).find('.indiv-opt-price').val() || '0' });
        }
    });

    $.post(ADMIN_URL + 'pos/ajax_save_item_modifier', {
        item_id:        itemId,
        id:             editId,
        name:           name,
        selection_type: type,
        sort_order:     sort,
        options:        options
    }, function(resp) {
        if (resp.success) {
            _indivRows = resp.data;
            renderIndividualModifiers(resp.data);
            loadModifierCount(itemId);
            indivResetForm();
        } else {
            alert(resp.message || 'Failed to save.');
        }
    }, 'json');
}

function deleteIndividualModifier(id) {
    if (!confirm('Delete this modifier?')) return;
    var itemId = $('#modal-item-id').val();
    $.post(ADMIN_URL + 'pos/ajax_delete_item_modifier', {
        item_id: itemId,
        id:      id
    }, function(resp) {
        if (resp.success) {
            _indivRows = resp.data;
            renderIndividualModifiers(resp.data);
            loadModifierCount(itemId);
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
