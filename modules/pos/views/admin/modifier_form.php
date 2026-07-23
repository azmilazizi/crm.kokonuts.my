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

                        <div class="form-group">
                            <label>Selection type</label>
                            <select id="selection-type" class="form-control">
                                <option value="single" <?php echo ($group && $group['selection_type'] === 'single') ? 'selected' : ''; ?>>Single — customer picks one</option>
                                <option value="multiple" <?php echo (!$group || $group['selection_type'] === 'multiple') ? 'selected' : ''; ?>>Multiple — customer picks many</option>
                            </select>
                        </div>

                        <div class="checkbox" style="margin-bottom:14px;">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="is-promo-modifier" onchange="togglePromoModifierMode()"
                                    style="width:15px;height:15px;margin:0;flex-shrink:0;"
                                    <?php echo ($group && !empty($group['is_promo_modifier'])) ? 'checked' : ''; ?>>
                                <span style="font-weight:600;">Promo Modifier Group
                                    <small class="text-muted" style="font-weight:normal;">— options are picked from existing modifier items (for bundle components)</small>
                                </span>
                            </label>
                        </div>

                        <hr />

                        <!-- Column headers — swap between normal and promo mode -->
                        <div id="opt-headers-normal" class="row" style="margin-bottom:6px; padding: 0 15px;">
                            <div class="col-md-5"><label class="text-muted small">Option name</label></div>
                            <div class="col-md-3"><label class="text-muted small">Price adj.</label></div>
                            <div class="col-md-3"><label class="text-muted small">→ CRM Promo</label></div>
                            <div class="col-md-1"></div>
                        </div>
                        <div id="opt-headers-promo" class="row" style="margin-bottom:6px; padding: 0 15px; display:none;">
                            <div class="col-md-11"><label class="text-muted small">Modifier item</label></div>
                            <div class="col-md-1"></div>
                        </div>

                        <div id="options-list">
                            <?php if ($group && !empty($group['modifiers'])) {
                                $is_promo = !empty($group['is_promo_modifier']);
                                foreach ($group['modifiers'] as $opt) { ?>
                            <div class="option-row row" style="margin-bottom:6px;" data-src="<?php echo (int)($opt['source_modifier_id'] ?? 0); ?>" data-option-id="<?php echo (int)($opt['id'] ?? 0); ?>">
                                <?php if ($is_promo): ?>
                                <div class="col-md-11">
                                    <select class="form-control option-src-modifier">
                                        <option value="">— Select modifier item —</option>
                                        <?php foreach ($all_modifiers_flat as $mf): ?>
                                        <option value="<?php echo $mf['id']; ?>"
                                            <?php echo ($opt['source_modifier_id'] ?? null) == $mf['id'] ? 'selected' : ''; ?>
                                            data-price="<?php echo $mf['price_adjustment']; ?>">
                                            <?php echo htmlspecialchars($mf['group_name'] . ' — ' . $mf['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php else: ?>
                                <div class="col-md-5">
                                    <input type="text" class="form-control option-name" placeholder="Option name"
                                        value="<?php echo htmlspecialchars($opt['name']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <span class="input-group-addon">RM</span>
                                        <input type="number" class="form-control option-price" step="0.01"
                                            placeholder="0.00" value="<?php echo number_format((float)$opt['price_adjustment'], 2); ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <select class="form-control option-promo" title="→ Links to CRM Promo">
                                        <option value="">— No link —</option>
                                        <?php foreach ($crm_promos as $cp): ?>
                                        <option value="<?php echo $cp['id']; ?>" <?php echo ($opt['crm_promo_id'] ?? null) == $cp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cp['name']); ?> (<?php echo $cp['type']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-1" style="padding-top:6px;">
                                    <button type="button" class="btn btn-xs btn-link text-danger" onclick="removeOption(this)">
                                        <i class="fa fa-trash" style="font-size:16px;"></i>
                                    </button>
                                </div>
                                <div class="col-md-12 mtop5">
                                    <?php if (!$is_promo) { ?>
                                        <div class="small text-muted text-uppercase" style="letter-spacing:.4px;margin-bottom:5px;">Inventory Tracking</div>
                                        <div class="modifier-inventory-rules">
                                            <?php
                                            $opt_rules = $opt['inventory_rules'] ?? [];
                                            if (empty($opt_rules)) {
                                                $opt_rules = [[
                                                    'role_key' => '',
                                                    'action_type' => 'deduct',
                                                    'inventory_item_id' => '',
                                                    'quantity' => 1,
                                                ]];
                                            }
                                            foreach ($opt_rules as $rule) { ?>
                                                <div class="row modifier-inventory-rule-row" style="margin:4px 0;">
                                                    <div class="col-md-3"><input type="text" class="form-control input-sm mir-role" placeholder="Role e.g. lid" value="<?php echo htmlspecialchars($rule['role_key'] ?? ''); ?>"></div>
                                                    <div class="col-md-2">
                                                        <select class="form-control input-sm mir-action">
                                                            <option value="deduct" <?php echo (($rule['action_type'] ?? 'deduct') === 'deduct') ? 'selected' : ''; ?>>Deduct</option>
                                                            <option value="replace" <?php echo (($rule['action_type'] ?? '') === 'replace') ? 'selected' : ''; ?>>Replace</option>
                                                            <option value="remove" <?php echo (($rule['action_type'] ?? '') === 'remove') ? 'selected' : ''; ?>>Remove</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <select class="form-control input-sm mir-item">
                                                            <option value="">Select inventory item...</option>
                                                            <?php foreach ($inventory_items as $inv): ?>
                                                                <option value="<?php echo $inv['id']; ?>" <?php echo ((int)($rule['inventory_item_id'] ?? 0) === (int)$inv['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($inv['sku_name'] . (!empty($inv['sku_code']) ? ' (' . $inv['sku_code'] . ')' : '')); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-2"><input type="number" class="form-control input-sm mir-qty" step="0.001" min="0" value="<?php echo htmlspecialchars((string)($rule['quantity'] ?? 1)); ?>"></div>
                                                    <div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest('.modifier-inventory-rule-row').remove()"><i class="fa fa-trash"></i></button></div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                        <button type="button" class="btn btn-link btn-xs" onclick="addModifierInventoryRule(this)"><i class="fa fa-plus-circle"></i> Add Item</button>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php } } ?>
                        </div>

                        <div class="mtop10">
                            <button type="button" class="btn btn-link" onclick="addOption()">
                                <i class="fa fa-plus-circle"></i> Add option
                            </button>
                        </div>

                        <div class="mtop15" style="border-top:1px solid #eee;padding-top:12px;">
                            <h5 style="margin:0 0 8px;">Inventory Tracking</h5>
                            <p class="text-muted small">Each modifier option can add, replace, or remove stock deductions. Use the same role key as the product default when you need one option to replace another, for example a flat lid being replaced by a dome lid.</p>
                        </div>

                        <hr />

                        <div class="form-group">
                            <label>Available at Warehouses <small class="text-muted">— leave blank for all warehouses</small></label>
                            <select id="warehouse-ids" name="warehouse_ids[]" class="form-control selectpicker" multiple
                                data-live-search="true"
                                data-selected-text-format="count > 1"
                                title="All warehouses (global)">
                                <?php foreach ($warehouses as $w) { ?>
                                <option value="<?php echo $w['warehouse_id']; ?>"
                                    <?php echo in_array($w['warehouse_id'], $assigned_warehouses) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($w['warehouse_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                            <p class="help-block small">Select specific warehouses to restrict this modifier. If none selected, it appears on all POS terminals.</p>
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
                                        <?php echo htmlspecialchars($item['sku_name']); ?>
                                        <?php if ($item['sku_code']) { ?>
                                            (<?php echo htmlspecialchars($item['sku_code']); ?>)
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

                        <table class="table table-bordered mtop15" id="linked-items-table">
                            <thead><tr><th>SKU Name</th><th>SKU Code</th><th></th></tr></thead>
                            <tbody id="linked-items-tbody">
                                <?php if (empty($linked_items)) { ?>
                                <tr id="linked-empty-row"><td colspan="3" class="text-muted text-center">No items linked yet.</td></tr>
                                <?php } ?>
                                <?php foreach ($linked_items as $item) { ?>
                                <tr id="linked-row-<?php echo $item['id']; ?>">
                                    <td><?php echo htmlspecialchars($item['sku_name']); ?></td>
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
var ADMIN_URL       = '<?php echo admin_url(); ?>';
var _crmPromos      = <?php echo json_encode(array_map(function($p) {
    return ['id' => $p['id'], 'label' => $p['name'] . ' (' . $p['type'] . ')'];
}, $crm_promos)); ?>;
var _allModFlat     = <?php echo json_encode(array_map(function($m) {
    return ['id' => $m['id'], 'label' => $m['group_name'] . ' — ' . $m['name'], 'price' => $m['price_adjustment']];
}, $all_modifiers_flat)); ?>;
var _inventoryItems = <?php echo json_encode(array_map(function($i) {
    return ['id' => $i['id'], 'label' => $i['sku_name'] . ($i['sku_code'] ? ' (' . $i['sku_code'] . ')' : '')];
}, $inventory_items)); ?>;
var _isPromoMode    = <?php echo (!empty($group['is_promo_modifier'])) ? 'true' : 'false'; ?>;

function togglePromoModifierMode() {
    _isPromoMode = $('#is-promo-modifier').is(':checked');
    $('#opt-headers-normal').toggle(!_isPromoMode);
    $('#opt-headers-promo').toggle(_isPromoMode);
    // Rebuild existing rows to switch input type
    var rows = $('.option-row');
    var data = [];
    rows.each(function() {
        if (_isPromoMode) {
            data.push({ name: $(this).find('.option-name').val() || '', price: $(this).find('.option-price').val() || '0', promoId: $(this).find('.option-promo').val() || '', srcId: '' });
        } else {
            data.push({ srcId: $(this).find('.option-src-modifier').val() || '', name: '', price: '0', promoId: '' });
        }
    });
    $('#options-list').empty();
    data.forEach(function(d) { addOption(d); });
}

function _buildPromoOpts(selectedId) {
    var html = '<option value="">— No link —</option>';
    _crmPromos.forEach(function(p) {
        html += '<option value="' + p.id + '"' + (selectedId && p.id == selectedId ? ' selected' : '') + '>' + p.label + '</option>';
    });
    return html;
}

function _buildModifierOpts(selectedId) {
    var html = '<option value="">— Select modifier item —</option>';
    _allModFlat.forEach(function(m) {
        html += '<option value="' + m.id + '"' + (selectedId && m.id == selectedId ? ' selected' : '') + ' data-price="' + m.price + '">' + m.label + '</option>';
    });
    return html;
}

function _buildInventoryItemOpts(selectedId) {
    var html = '<option value="">Select inventory item...</option>';
    _inventoryItems.forEach(function(item) {
        html += '<option value="' + item.id + '"' + (selectedId && String(selectedId) === String(item.id) ? ' selected' : '') + '>' + $('<span>').text(item.label).html() + '</option>';
    });
    return html;
}

function _inventoryRulesHtml(rules) {
    rules = rules || [];
    var html = '<div class="modifier-inventory-rules">';
    if (!rules.length) {
        html += _inventoryRuleRowHtml({});
    } else {
        rules.forEach(function(rule) { html += _inventoryRuleRowHtml(rule); });
    }
    html += '</div><button type="button" class="btn btn-link btn-xs" onclick="addModifierInventoryRule(this)"><i class="fa fa-plus-circle"></i> Add Item</button>';
    return html;
}

function _inventoryRuleRowHtml(rule) {
    rule = rule || {};
    return '' +
        '<div class="row modifier-inventory-rule-row" style="margin:4px 0;">' +
            '<div class="col-md-3"><input type="text" class="form-control input-sm mir-role" placeholder="Role e.g. lid" value="' + $('<span>').text(rule.role_key || '').html() + '"></div>' +
            '<div class="col-md-2"><select class="form-control input-sm mir-action">' +
                '<option value="deduct">Deduct</option>' +
                '<option value="replace">Replace</option>' +
                '<option value="remove">Remove</option>' +
            '</select></div>' +
            '<div class="col-md-4"><select class="form-control input-sm mir-item">' + _buildInventoryItemOpts(rule.inventory_item_id || '') + '</select></div>' +
            '<div class="col-md-2"><input type="number" class="form-control input-sm mir-qty" step="0.001" min="0" value="' + (rule.quantity !== undefined ? rule.quantity : '1') + '"></div>' +
            '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest(\'.modifier-inventory-rule-row\').remove()"><i class="fa fa-trash"></i></button></div>' +
        '</div>';
}

function addModifierInventoryRule(btn, rule) {
    var wrap = $(btn).siblings('.modifier-inventory-rules');
    wrap.append(_inventoryRuleRowHtml(rule || {}));
    var row = wrap.find('.modifier-inventory-rule-row').last();
    row.find('.mir-action').val((rule && rule.action_type) || 'deduct');
    syncModifierInventoryRuleRow(row);
}

function syncModifierInventoryRuleRow(row) {
    var action = row.find('.mir-action').val();
    var disabled = action === 'remove';
    row.find('.mir-item').prop('disabled', disabled);
    row.find('.mir-qty').prop('disabled', disabled);
}

function collectModifierInventoryRules(row) {
    var rules = [];
    row.find('.modifier-inventory-rule-row').each(function(idx) {
        var $row = $(this);
        var action = $row.find('.mir-action').val() || 'deduct';
        var inventoryItemId = $row.find('.mir-item').val();
        if (action !== 'remove' && !inventoryItemId) {
            return;
        }

        rules.push({
            role_key: $.trim($row.find('.mir-role').val()),
            action_type: action,
            inventory_item_id: inventoryItemId,
            quantity: $row.find('.mir-qty').val() || 1,
            priority: 100,
            sort_order: idx
        });
    });
    return rules;
}

function addOption(d) {
    d = d || {};
    var row;
    if (_isPromoMode) {
        row = $(
            '<div class="option-row row" style="margin-bottom:6px;">' +
            '<div class="col-md-11"><select class="form-control option-src-modifier" onchange="onSrcModifierChange(this)">' +
            _buildModifierOpts(d.srcId || '') +
            '</select></div>' +
            '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="removeOption(this)">' +
            '<i class="fa fa-trash" style="font-size:16px;"></i></button></div>' +
            '</div>'
        );
    } else {
        row = $(
            '<div class="option-row row" style="margin-bottom:6px;" data-option-id="' + (d.id || '') + '">' +
            '<div class="col-md-5"><input type="text" class="form-control option-name" placeholder="Option name" value="' + (d.name || '') + '"></div>' +
            '<div class="col-md-3"><div class="input-group"><span class="input-group-addon">RM</span>' +
            '<input type="number" class="form-control option-price" step="0.01" placeholder="0.00" value="' + (d.price !== undefined ? d.price : '0.00') + '"></div></div>' +
            '<div class="col-md-3"><select class="form-control option-promo">' + _buildPromoOpts(d.promoId || '') + '</select></div>' +
            '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="removeOption(this)">' +
            '<i class="fa fa-trash" style="font-size:16px;"></i></button></div>' +
            '<div class="col-md-12 mtop5"><div class="small text-muted text-uppercase" style="letter-spacing:.4px;margin-bottom:5px;">Inventory Tracking</div>' + _inventoryRulesHtml(d.inventory_rules || []) + '</div>' +
            '</div>'
        );
        if (!d.name) row.find('.option-name').focus();
    }
    $('#options-list').append(row);
    row.find('.mir-action').each(function() { syncModifierInventoryRuleRow($(this).closest('.modifier-inventory-rule-row')); });
}

function onSrcModifierChange(sel) {
    // price_adjustment could be used in future; no action needed now
}

function removeOption(btn) {
    $(btn).closest('.option-row').remove();
}

function renderLinkedItems(items) {
    var tbody = $('#linked-items-tbody');
    tbody.empty();
    if (!items || items.length === 0) {
        tbody.html('<tr id="linked-empty-row"><td colspan="3" class="text-muted text-center">No items linked yet.</td></tr>');
        return;
    }
    $.each(items, function (i, item) {
        tbody.append(
            '<tr id="linked-row-' + item.id + '">' +
            '<td>' + $('<span>').text(item.sku_name || '').html() + '</td>' +
            '<td>' + $('<span>').text(item.sku_code || '').html() + '</td>' +
            '<td class="text-right"><button class="btn btn-xs btn-danger" onclick="unlinkItem(' + item.id + ')">' +
            '<i class="fa fa-times"></i> Remove</button></td>' +
            '</tr>'
        );
    });
}

function linkItems() {
    var itemIds = $('#link-items-select').val();
    if (!itemIds || itemIds.length === 0) { alert('Please select at least one item.'); return; }
    $.post(ADMIN_URL + 'pos/ajax_assign_items_to_modifier', {
        modifier_group_id: $('#modifier-id').val(),
        item_ids: itemIds
    }, function (resp) {
        if (resp.success) {
            renderLinkedItems(resp.data);
            $.each(itemIds, function (i, id) { $('#link-items-select option[value="' + id + '"]').remove(); });
            $('#link-items-select').selectpicker('refresh').selectpicker('deselectAll');
        } else { alert(resp.message || 'Failed to link items.'); }
    }, 'json');
}

function unlinkItem(itemId) {
    $.post(ADMIN_URL + 'pos/ajax_unassign_item_from_modifier', {
        modifier_group_id: $('#modifier-id').val(),
        item_id: itemId
    }, function (resp) {
        if (resp.success) {
            var row = $('#linked-row-' + itemId);
            var label = row.find('td:first').text() + (row.find('td:eq(1)').text() ? ' (' + row.find('td:eq(1)').text() + ')' : '');
            $('#link-items-select').append('<option value="' + itemId + '">' + label + '</option>').selectpicker('refresh');
            renderLinkedItems(resp.data);
        } else { alert('Failed to remove item.'); }
    }, 'json');
}

function saveModifier() {
    var name = $.trim($('#modifier-name').val());
    if (!name) { $('#modifier-name').focus(); alert('Modifier name is required.'); return; }

    var isPromo = $('#is-promo-modifier').is(':checked');
    var options = [];
    $('.option-row').each(function () {
        if (isPromo) {
            var srcId = $(this).find('.option-src-modifier').val();
            if (!srcId) return;
            var label = $(this).find('.option-src-modifier option:selected').text().split(' — ').pop();
            options.push({ id: $(this).data('option-id') || '', name: label, price_adjustment: '0', source_modifier_id: srcId, inventory_rules: collectModifierInventoryRules($(this)) });
        } else {
            var optName = $.trim($(this).find('.option-name').val());
            if (!optName) return;
            options.push({
                id: $(this).data('option-id') || '',
                name: optName,
                price_adjustment: $(this).find('.option-price').val() || '0',
                crm_promo_id: $(this).find('.option-promo').val() || null,
                inventory_rules: collectModifierInventoryRules($(this))
            });
        }
    });

    $.post(ADMIN_URL + 'pos/ajax_save_modifier_form', {
        id:                 $('#modifier-id').val(),
        name:               name,
        selection_type:     $('#selection-type').val(),
        is_promo_modifier:  isPromo ? 1 : 0,
        options:            options,
        warehouse_ids:      $('#warehouse-ids').val() || [],
    }, function (resp) {
        if (resp.success) {
            window.location.href = ADMIN_URL + 'pos/modifiers';
        } else { alert(resp.message || 'Failed to save.'); }
    }, 'json');
}

function deleteModifier() {
    if (!confirm('Delete this modifier and all its options? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_modifier_group/' + $('#modifier-id').val(), function (resp) {
        if (resp.success) window.location.href = ADMIN_URL + 'pos/modifiers';
    }, 'json');
}

// Init: apply promo mode headers
$(function() {
    if (_isPromoMode) {
        $('#opt-headers-normal').hide();
        $('#opt-headers-promo').show();
    }
    $(document).on('change', '.mir-action', function() {
        syncModifierInventoryRuleRow($(this).closest('.modifier-inventory-rule-row'));
    });
});
</script>
<?php init_tail(); ?>
