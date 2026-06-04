<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin-top"><?php echo $title; ?></h4>
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
                                <tr>
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
                                    <td>
                                        <button class="btn btn-xs btn-default"
                                            onclick="openModifiersModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['sku_name'])); ?>')">
                                            <i class="fa fa-sliders"></i> Modifiers
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
                            <th>Price Adj. (RM)</th>
                            <th>Sort</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="individual-tbody">
                        <tr id="individual-empty"><td colspan="4" class="text-muted text-center">No individual modifiers.</td></tr>
                    </tbody>
                </table>

                <!-- Add individual modifier inline form -->
                <div class="row">
                    <div class="col-md-5">
                        <input type="text" id="new-modifier-name" class="form-control" placeholder="Modifier name (e.g. Extra Shot)">
                    </div>
                    <div class="col-md-3">
                        <input type="number" id="new-modifier-price" class="form-control" placeholder="Price adj." value="0.00" step="0.01">
                    </div>
                    <div class="col-md-2">
                        <input type="number" id="new-modifier-sort" class="form-control" placeholder="Sort" value="0" min="0">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-info btn-block" onclick="saveIndividualModifier()">
                            <i class="fa fa-plus"></i> Add
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

$(function () {
    $('#pos-products-table').DataTable({ order: [[0, 'asc']], pageLength: 25, columnDefs: [{ orderable: false, targets: [7, 8] }] });
    loadAllModifierCounts();
});

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

function openModifiersModal(itemId, itemName) {
    $('#modal-item-id').val(itemId);
    $('#modal-item-name').text(itemName);
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
        if (resp.success) renderIndividualModifiers(resp.data);
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
        var price = parseFloat(row.price_adjustment).toFixed(2);
        tbody.append(
            '<tr id="individual-row-' + row.id + '">' +
            '<td>' + $('<span>').text(row.name).html() + '</td>' +
            '<td>' + (parseFloat(price) >= 0 ? '+' : '') + price + '</td>' +
            '<td>' + row.sort_order + '</td>' +
            '<td class="text-center"><button class="btn btn-sm btn-danger" onclick="deleteIndividualModifier(' + row.id + ')">' +
            '<i class="fa fa-times"></i> Remove</button></td>' +
            '</tr>'
        );
    });
}

function saveIndividualModifier() {
    var itemId = $('#modal-item-id').val();
    var name   = $.trim($('#new-modifier-name').val());
    var price  = $('#new-modifier-price').val();
    var sort   = $('#new-modifier-sort').val();

    if (!name) { alert('Modifier name is required'); return; }

    $.post(ADMIN_URL + 'pos/ajax_save_item_modifier', {
        item_id:          itemId,
        name:             name,
        price_adjustment: price,
        sort_order:       sort
    }, function(resp) {
        if (resp.success) {
            renderIndividualModifiers(resp.data);
            loadModifierCount(itemId);
            $('#new-modifier-name').val('');
            $('#new-modifier-price').val('0.00');
            $('#new-modifier-sort').val(0);
        }
    }, 'json');
}

function deleteIndividualModifier(id) {
    var itemId = $('#modal-item-id').val();
    $.post(ADMIN_URL + 'pos/ajax_delete_item_modifier', {
        item_id: itemId,
        id:      id
    }, function(resp) {
        if (resp.success) {
            renderIndividualModifiers(resp.data);
            loadModifierCount(itemId);
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
