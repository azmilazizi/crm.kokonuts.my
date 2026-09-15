<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin-top"><?php echo $title; ?></h4>
                        <hr />

                        <input type="hidden" id="checklist-id" value="<?php echo $template ? $template['id'] : ''; ?>">

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Type <span class="text-danger">*</span></label>
                                    <select id="checklist-type" class="form-control" onchange="onTypeChange()">
                                        <option value="sop_open"  <?php echo (!$template || $template['type'] === 'sop_open')  ? 'selected' : ''; ?>>Opening SOP</option>
                                        <option value="sop_close" <?php echo ($template && $template['type'] === 'sop_close') ? 'selected' : ''; ?>>Closing SOP</option>
                                        <option value="equipment" <?php echo ($template && $template['type'] === 'equipment') ? 'selected' : ''; ?>>Equipment</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Outlets</label>
                                    <select id="checklist-warehouses" name="warehouse_ids[]" class="form-control selectpicker" multiple
                                        data-live-search="true"
                                        data-selected-text-format="count > 2"
                                        title="All outlets (global fallback)"
                                        onchange="onOutletsChange()">
                                        <?php
                                        $selected_ids = $template['warehouse_ids'] ?? [];
                                        foreach ($warehouses as $w) { ?>
                                        <option value="<?php echo $w['warehouse_id']; ?>"
                                            <?php echo in_array((int)$w['warehouse_id'], array_map('intval', $selected_ids)) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($w['warehouse_name']); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <p class="help-block small">Leave as "All outlets" for a fallback template used when an outlet has none of its own. Select specific outlets to scope this checklist to them.</p>
                                </div>
                            </div>
                        </div>

                        <div class="checkbox mbottom15">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                <input type="checkbox" id="checklist-active"
                                    style="width:15px;height:15px;margin:0;flex-shrink:0;"
                                    <?php echo (!$template || $template['is_active']) ? 'checked' : ''; ?>>
                                <span>Active</span>
                            </label>
                        </div>

                        <hr />

                        <!-- SOP mode: free-text instructions -->
                        <div id="sop-text-section">
                            <h5 class="bold">Instructions</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <textarea id="checklist-sop-text" class="form-control" rows="14"
                                        style="font-family:inherit;font-size:14px;"
                                        placeholder="Write the step-by-step procedure here..."><?php echo $template ? htmlspecialchars($template['sop_text'] ?? '') : ''; ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="text-muted small">Insert equipment name</label>
                                    <div id="equipment-variables-list" class="mtop4">
                                        <span class="text-muted small">Loading...</span>
                                    </div>
                                    <p class="help-block small">Click a name to insert it at your cursor. This lists the Equipment checklist items for the outlet(s) selected above (or the global Equipment checklist if none are selected).</p>
                                </div>
                            </div>
                        </div>

                        <!-- Equipment mode: tri-state group/item builder -->
                        <div id="equipment-builder-section">
                            <h5 class="bold">Groups <small class="text-muted">— containers/sections, e.g. "Mesh bag", "Blue ice container". Drag <i class="fa fa-bars"></i> to reorder.</small></h5>
                            <div id="groups-list">
                                <?php if ($template && !empty($template['groups'])) { foreach ($template['groups'] as $group) { ?>
                                    <?php include __DIR__ . '/checklist_form_group.php'; ?>
                                <?php } } ?>
                            </div>
                            <div class="mtop10 mbottom20">
                                <button type="button" class="btn btn-link" onclick="addGroup()">
                                    <i class="fa fa-plus-circle"></i> Add Group
                                </button>
                            </div>

                            <hr />

                            <h5 class="bold">Standalone Items <small class="text-muted">— not part of any group. Drag <i class="fa fa-bars"></i> to reorder.</small></h5>
                            <div id="standalone-items-list">
                                <?php if ($template && !empty($template['items'])) { foreach ($template['items'] as $item) { ?>
                                    <div class="item-row row" style="margin-bottom:6px;">
                                        <div class="col-md-1 text-center item-drag-handle" style="cursor:move;padding-top:8px;"><i class="fa fa-bars text-muted"></i></div>
                                        <div class="col-md-4"><input type="text" class="form-control item-label" placeholder="Item label" value="<?php echo htmlspecialchars($item['label']); ?>"></div>
                                        <div class="col-md-6"><input type="text" class="form-control item-description" placeholder="Description (optional)" value="<?php echo htmlspecialchars($item['description'] ?? ''); ?>"></div>
                                        <div class="col-md-1" style="padding-top:6px;">
                                            <button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest('.item-row').remove()"><i class="fa fa-trash"></i></button>
                                        </div>
                                    </div>
                                <?php } } ?>
                            </div>
                            <div class="mtop10">
                                <button type="button" class="btn btn-link" onclick="addStandaloneItem()">
                                    <i class="fa fa-plus-circle"></i> Add Item
                                </button>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row mtop10 mbottom20">
                    <?php if ($template) { ?>
                    <div class="col-md-3">
                        <button class="btn btn-danger btn-block" onclick="deleteChecklist()">Delete</button>
                    </div>
                    <div class="col-md-9 text-right">
                    <?php } else { ?>
                    <div class="col-md-12 text-right">
                    <?php } ?>
                        <a href="<?php echo admin_url('pos/checklists'); ?>" class="btn btn-default">Cancel</a>
                        &nbsp;
                        <button class="btn btn-info" onclick="saveChecklist()">Save</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';
var _groupSeq = 0;

function isSopType() {
    return $('#checklist-type').val() !== 'equipment';
}

function onTypeChange() {
    if (isSopType()) {
        $('#sop-text-section').show();
        $('#equipment-builder-section').hide();
        loadEquipmentVariables();
    } else {
        $('#sop-text-section').hide();
        $('#equipment-builder-section').show();
    }
}

function onOutletsChange() {
    if (isSopType()) {
        loadEquipmentVariables();
    }
}

function insertVariable(label) {
    var el = document.getElementById('checklist-sop-text');
    var start = el.selectionStart || 0;
    var end = el.selectionEnd || 0;
    var text = el.value;
    var placeholder = '{{' + label + '}}';
    el.value = text.slice(0, start) + placeholder + text.slice(end);
    var pos = start + placeholder.length;
    el.focus();
    el.setSelectionRange(pos, pos);
}

function loadEquipmentVariables() {
    var $list = $('#equipment-variables-list');
    $list.html('<span class="text-muted small">Loading...</span>');
    $.post(ADMIN_URL + 'pos/ajax_get_checklist_equipment_variables', {
        warehouse_ids: $('#checklist-warehouses').val() || []
    }, function (resp) {
        if (!resp.success || !resp.labels || !resp.labels.length) {
            $list.html('<span class="text-muted small">No equipment items configured yet for this scope.</span>');
            return;
        }
        $list.empty();
        resp.labels.forEach(function (label) {
            $('<button type="button" class="btn btn-default btn-xs" style="margin:0 4px 4px 0;"></button>')
                .text(label)
                .on('click', function () { insertVariable(label); })
                .appendTo($list);
        });
    }, 'json');
}

function itemRowHtml(item) {
    item = item || {};
    return '' +
        '<div class="item-row row" style="margin-bottom:6px;">' +
            '<div class="col-md-1 text-center item-drag-handle" style="cursor:move;padding-top:8px;"><i class="fa fa-bars text-muted"></i></div>' +
            '<div class="col-md-4"><input type="text" class="form-control item-label" placeholder="Item label" value="' + $('<span>').text(item.label || '').html() + '"></div>' +
            '<div class="col-md-6"><input type="text" class="form-control item-description" placeholder="Description (optional)" value="' + $('<span>').text(item.description || '').html() + '"></div>' +
            '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest(\'.item-row\').remove()"><i class="fa fa-trash"></i></button></div>' +
        '</div>';
}

function groupBlockHtml(group) {
    group = group || {};
    var items = group.items || [];
    var itemsHtml = '';
    items.forEach(function (it) { itemsHtml += itemRowHtml(it); });
    var gid = 'g' + (++_groupSeq);
    return '' +
        '<div class="group-block" style="border:1px solid #e5e5e5;border-radius:4px;padding:12px;margin-bottom:12px;" data-gid="' + gid + '">' +
            '<div class="row">' +
                '<div class="col-md-1 text-center group-drag-handle" style="cursor:move;padding-top:8px;"><i class="fa fa-bars text-muted"></i></div>' +
                '<div class="col-md-3"><input type="text" class="form-control group-name" placeholder="Group name, e.g. Mesh bag" value="' + $('<span>').text(group.name || '').html() + '"></div>' +
                '<div class="col-md-3"><input type="text" class="form-control group-transport-role" placeholder="Transport role (optional)" value="' + $('<span>').text(group.transport_role || '').html() + '"></div>' +
                '<div class="col-md-3"><input type="text" class="form-control group-onsite-role" placeholder="On-site role (optional)" value="' + $('<span>').text(group.onsite_role || '').html() + '"></div>' +
                '<div class="col-md-2 text-right"><button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest(\'.group-block\').remove()"><i class="fa fa-trash"></i> Remove group</button></div>' +
            '</div>' +
            '<div class="group-items mtop10">' + itemsHtml + '</div>' +
            '<button type="button" class="btn btn-link btn-xs" onclick="addGroupItem(this)"><i class="fa fa-plus-circle"></i> Add item to group</button>' +
        '</div>';
}

function initGroupItemsSortable($scope) {
    $scope.sortable({
        handle: '.item-drag-handle',
        items: '> .item-row',
        axis: 'y'
    });
}

function addGroup(group) {
    var $block = $(groupBlockHtml(group || {}));
    $('#groups-list').append($block);
    initGroupItemsSortable($block.find('.group-items'));
    $('#groups-list').sortable('refresh');
}

function addGroupItem(btn) {
    $(btn).siblings('.group-items').append(itemRowHtml({})).sortable('refresh');
}

function addStandaloneItem() {
    $('#standalone-items-list').append(itemRowHtml({})).sortable('refresh');
}

function collectItems($container) {
    var items = [];
    $container.find('> .item-row').each(function () {
        var label = $.trim($(this).find('.item-label').val());
        if (!label) return;
        items.push({
            label: label,
            description: $.trim($(this).find('.item-description').val())
        });
    });
    return items;
}

function collectGroups() {
    var groups = [];
    $('#groups-list > .group-block').each(function () {
        var name = $.trim($(this).find('.group-name').val());
        if (!name) return;
        groups.push({
            name: name,
            transport_role: $.trim($(this).find('.group-transport-role').val()),
            onsite_role: $.trim($(this).find('.group-onsite-role').val()),
            items: collectItems($(this).find('.group-items'))
        });
    });
    return groups;
}

function saveChecklist() {
    var sop = isSopType();
    $.post(ADMIN_URL + 'pos/ajax_save_checklist_template', {
        id: $('#checklist-id').val(),
        type: $('#checklist-type').val(),
        warehouse_ids: $('#checklist-warehouses').val() || [],
        is_active: $('#checklist-active').is(':checked') ? 1 : 0,
        sop_text: sop ? $('#checklist-sop-text').val() : '',
        groups: sop ? [] : collectGroups(),
        standalone_items: sop ? [] : collectItems($('#standalone-items-list'))
    }, function (resp) {
        if (resp.success) {
            window.location.href = ADMIN_URL + 'pos/checklists';
        } else {
            alert(resp.message || 'Failed to save.');
        }
    }, 'json');
}

function deleteChecklist() {
    if (!confirm('Delete this checklist and all its groups/items? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_checklist_template', { id: $('#checklist-id').val() }, function (resp) {
        if (resp.success) window.location.href = ADMIN_URL + 'pos/checklists';
    }, 'json');
}

// jQuery loads via the admin footer include, which renders after this
// block — a jQuery-based ready handler here would itself throw "$ is not
// defined". DOMContentLoaded needs no library and only fires once every
// script the browser encountered while parsing (jQuery included) has run.
document.addEventListener('DOMContentLoaded', function () {
    $('#groups-list').sortable({
        handle: '.group-drag-handle',
        items: '> .group-block',
        axis: 'y'
    });
    $('#standalone-items-list').sortable({
        handle: '.item-drag-handle',
        items: '> .item-row',
        axis: 'y'
    });
    $('.group-items').each(function () {
        initGroupItemsSortable($(this));
    });
    onTypeChange();
});
</script>
<?php init_tail(); ?>
