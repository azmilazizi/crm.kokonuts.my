<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin-top"><?php echo $title; ?></h4>
                        <hr />

                        <input type="hidden" id="modifier-id" value="<?php echo $group ? $group['id'] : ''; ?>">

                        <div class="form-group">
                            <label>Modifier name <span class="text-danger">*</span></label>
                            <input type="text" id="modifier-name" class="form-control input-lg"
                                placeholder="e.g. Cup Size, Milk Type"
                                value="<?php echo $group ? htmlspecialchars($group['name']) : ''; ?>">
                        </div>

                        <hr />

                        <div class="row" style="margin-bottom:6px; padding: 0 15px;">
                            <div class="col-md-7"><label class="text-muted small">Option name</label></div>
                            <div class="col-md-4"><label class="text-muted small">Price adjustment</label></div>
                            <div class="col-md-1"></div>
                        </div>

                        <div id="options-list">
                            <?php if ($group && !empty($group['modifiers'])) {
                                foreach ($group['modifiers'] as $opt) { ?>
                            <div class="option-row row" style="margin-bottom:6px;">
                                <div class="col-md-7">
                                    <input type="text" class="form-control option-name" placeholder="Option name"
                                        value="<?php echo htmlspecialchars($opt['name']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-addon">RM</span>
                                        <input type="number" class="form-control option-price" step="0.01"
                                            placeholder="0.00" value="<?php echo number_format((float)$opt['price_adjustment'], 2); ?>">
                                    </div>
                                </div>
                                <div class="col-md-1" style="padding-top:6px;">
                                    <button type="button" class="btn btn-xs btn-link text-danger" onclick="removeOption(this)">
                                        <i class="fa fa-trash" style="font-size:16px;"></i>
                                    </button>
                                </div>
                            </div>
                            <?php } } ?>
                        </div>

                        <div class="mtop10">
                            <button type="button" class="btn btn-link" onclick="addOption()">
                                <i class="fa fa-plus-circle"></i> Add option
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Linked Items -->
                <?php if ($group) { ?>
                <div class="panel_s mtop15">
                    <div class="panel-body">
                        <h5 class="no-margin-top bold">Linked Items</h5>
                        <p class="text-muted small">Items below will prompt this modifier when added to an order.</p>
                        <hr class="mtop10 mbottom10" />

                        <!-- Bulk link -->
                        <div class="row">
                            <div class="col-md-9">
                                <select id="link-items-select" class="form-control selectpicker" multiple
                                    data-live-search="true"
                                    data-selected-text-format="count > 2"
                                    title="Search and select items...">
                                    <?php
                                    $linked_ids = array_column($linked_items, 'id');
                                    foreach ($all_items as $item) {
                                        if (!in_array($item['id'], $linked_ids)) { ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['commodity_name']); ?>
                                        <?php if ($item['commodity_code']) { ?>
                                            (<?php echo htmlspecialchars($item['commodity_code']); ?>)
                                        <?php } ?>
                                    </option>
                                    <?php } } ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-info btn-block" onclick="linkItems()">
                                    <i class="fa fa-link"></i> Link Selected
                                </button>
                            </div>
                        </div>

                        <!-- Linked items table -->
                        <table class="table table-bordered mtop15" id="linked-items-table">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Code</th>
                                    <th>SKU</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="linked-items-tbody">
                                <?php if (empty($linked_items)) { ?>
                                <tr id="linked-empty-row"><td colspan="4" class="text-muted text-center">No items linked yet.</td></tr>
                                <?php } ?>
                                <?php foreach ($linked_items as $item) { ?>
                                <tr id="linked-row-<?php echo $item['id']; ?>">
                                    <td><?php echo htmlspecialchars($item['commodity_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['commodity_code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['sku_code']); ?></td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-danger" onclick="unlinkItem(<?php echo $item['id']; ?>)">
                                            <i class="fa fa-times"></i> Remove
                                        </button>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php } else { ?>
                <div class="alert alert-info mtop15">
                    <i class="fa fa-info-circle"></i> Save this modifier first, then you can link items to it.
                </div>
                <?php } ?>

                <!-- Actions -->
                <div class="row mtop10 mbottom20">
                    <?php if ($group) { ?>
                    <div class="col-md-3">
                        <button class="btn btn-danger btn-block" onclick="deleteModifier()">Delete</button>
                    </div>
                    <div class="col-md-9 text-right">
                    <?php } else { ?>
                    <div class="col-md-12 text-right">
                    <?php } ?>
                        <a href="<?php echo admin_url('pos/modifiers'); ?>" class="btn btn-default">Cancel</a>
                        &nbsp;
                        <button class="btn btn-info" onclick="saveModifier()">Save</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function addOption(name, price) {
    var row = $(
        '<div class="option-row row" style="margin-bottom:6px;">' +
        '<div class="col-md-7"><input type="text" class="form-control option-name" placeholder="Option name" value="' + (name || '') + '"></div>' +
        '<div class="col-md-4"><div class="input-group"><span class="input-group-addon">RM</span>' +
        '<input type="number" class="form-control option-price" step="0.01" placeholder="0.00" value="' + (price !== undefined ? price : '0.00') + '"></div></div>' +
        '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="removeOption(this)">' +
        '<i class="fa fa-trash" style="font-size:16px;"></i></button></div>' +
        '</div>'
    );
    $('#options-list').append(row);
    row.find('.option-name').focus();
}

function removeOption(btn) {
    $(btn).closest('.option-row').remove();
}

function renderLinkedItems(items) {
    var tbody = $('#linked-items-tbody');
    tbody.empty();
    if (!items || items.length === 0) {
        tbody.html('<tr id="linked-empty-row"><td colspan="4" class="text-muted text-center">No items linked yet.</td></tr>');
        return;
    }
    $.each(items, function (i, item) {
        tbody.append(
            '<tr id="linked-row-' + item.id + '">' +
            '<td>' + $('<span>').text(item.commodity_name).html() + '</td>' +
            '<td>' + $('<span>').text(item.commodity_code || '').html() + '</td>' +
            '<td>' + $('<span>').text(item.sku_code || '').html() + '</td>' +
            '<td class="text-right"><button class="btn btn-xs btn-danger" onclick="unlinkItem(' + item.id + ')">' +
            '<i class="fa fa-times"></i> Remove</button></td>' +
            '</tr>'
        );
    });
}

function linkItems() {
    var itemIds = $('#link-items-select').val();
    if (!itemIds || itemIds.length === 0) {
        alert('Please select at least one item.');
        return;
    }
    var groupId = $('#modifier-id').val();
    $.post(ADMIN_URL + 'pos/ajax_assign_items_to_modifier', {
        modifier_group_id: groupId,
        item_ids:          itemIds
    }, function (resp) {
        if (resp.success) {
            renderLinkedItems(resp.data);
            // Remove linked items from the dropdown
            $.each(itemIds, function (i, id) {
                $('#link-items-select option[value="' + id + '"]').remove();
            });
            $('#link-items-select').selectpicker('refresh').selectpicker('deselectAll');
        } else {
            alert(resp.message || 'Failed to link items.');
        }
    }, 'json');
}

function unlinkItem(itemId) {
    var groupId = $('#modifier-id').val();
    $.post(ADMIN_URL + 'pos/ajax_unassign_item_from_modifier', {
        modifier_group_id: groupId,
        item_id:           itemId
    }, function (resp) {
        if (resp.success) {
            // Put item back in the dropdown
            var row = $('#linked-row-' + itemId);
            var name = row.find('td:first').text();
            var code = row.find('td:eq(1)').text();
            var label = name + (code ? ' (' + code + ')' : '');
            $('#link-items-select').append('<option value="' + itemId + '">' + label + '</option>');
            $('#link-items-select').selectpicker('refresh');
            renderLinkedItems(resp.data);
        } else {
            alert('Failed to remove item.');
        }
    }, 'json');
}

function saveModifier() {
    var name = $.trim($('#modifier-name').val());
    if (!name) {
        $('#modifier-name').focus();
        alert('Modifier name is required.');
        return;
    }

    var options = [];
    $('.option-row').each(function () {
        var optName = $.trim($(this).find('.option-name').val());
        if (optName) {
            options.push({
                name:             optName,
                price_adjustment: $(this).find('.option-price').val() || '0'
            });
        }
    });

    $.post(ADMIN_URL + 'pos/ajax_save_modifier_form', {
        id:             $('#modifier-id').val(),
        name:           name,
        selection_type: 'multiple',
        min_selections: 0,
        max_selections: 999,
        options:        options
    }, function (resp) {
        if (resp.success) {
            window.location.href = ADMIN_URL + 'pos/modifiers';
        } else {
            alert(resp.message || 'Failed to save.');
        }
    }, 'json');
}

function deleteModifier() {
    if (!confirm('Delete this modifier and all its options? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_modifier_group/' + $('#modifier-id').val(), function (resp) {
        if (resp.success) {
            window.location.href = ADMIN_URL + 'pos/modifiers';
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
