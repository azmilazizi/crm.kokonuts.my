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
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-info" onclick="openGroupModal()">
                                    <i class="fa fa-plus"></i> New Modifier Group
                                </button>
                            </div>
                        </div>
                        <hr />

                        <div id="modifier-groups-container">
                        <?php if (empty($groups)) { ?>
                            <p class="text-muted" id="empty-state">No modifier groups yet. Create one to get started.</p>
                        <?php } ?>
                        <?php foreach ($groups as $group) { ?>
                        <div class="panel panel-default modifier-group-panel" id="group-panel-<?php echo $group['id']; ?>">
                            <div class="panel-heading">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong><?php echo htmlspecialchars($group['name']); ?></strong>
                                        <span class="label label-default mleft5"><?php echo $group['selection_type']; ?></span>
                                        <?php if ((int)$group['active'] === 0) { ?>
                                            <span class="label label-warning mleft5">Inactive</span>
                                        <?php } ?>
                                        <small class="text-muted mleft5">
                                            min: <?php echo $group['min_selections']; ?> / max: <?php echo $group['max_selections']; ?>
                                        </small>
                                    </div>
                                    <div class="col-md-6 text-right">
                                        <button class="btn btn-xs btn-default" onclick="openModifierModal(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                                            <i class="fa fa-plus"></i> Add Option
                                        </button>
                                        <button class="btn btn-xs btn-default" onclick="openGroupModal(<?php echo $group['id']; ?>, <?php echo htmlspecialchars(json_encode($group)); ?>)">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        <button class="btn btn-xs btn-danger" onclick="deleteGroup(<?php echo $group['id']; ?>, this)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="panel-body no-padding">
                                <table class="table no-margin modifier-table" id="modifier-table-<?php echo $group['id']; ?>">
                                    <thead>
                                        <tr>
                                            <th>Option Name</th>
                                            <th>Price Adjustment</th>
                                            <th>Sort</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="modifier-tbody-<?php echo $group['id']; ?>">
                                    <?php if (empty($group['modifiers'])) { ?>
                                        <tr class="empty-row"><td colspan="5" class="text-muted text-center">No options yet.</td></tr>
                                    <?php } ?>
                                    <?php foreach ($group['modifiers'] as $mod) { ?>
                                        <?php echo modifier_row($mod); ?>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
function modifier_row($mod) {
    $price = (float)$mod['price_adjustment'];
    $price_label = $price == 0 ? '<span class="text-muted">0.00</span>'
        : ($price > 0 ? '<span class="text-success">+'.number_format($price, 2).'</span>'
                      : '<span class="text-danger">'.number_format($price, 2).'</span>');
    $status = (int)$mod['active'] === 1
        ? '<span class="label label-success">Active</span>'
        : '<span class="label label-default">Inactive</span>';
    $encoded = htmlspecialchars(json_encode($mod), ENT_QUOTES);
    return '<tr id="modifier-row-'.$mod['id'].'">
        <td>'.htmlspecialchars($mod['name']).'</td>
        <td>'.$price_label.'</td>
        <td>'.htmlspecialchars($mod['sort_order']).'</td>
        <td>'.$status.'</td>
        <td class="text-right">
            <button class="btn btn-xs btn-default" onclick=\'openModifierModal('.$mod['modifier_group_id'].', null, '.$encoded.')\'>
                <i class="fa fa-pencil"></i>
            </button>
            <button class="btn btn-xs btn-danger" onclick="deleteModifier('.$mod['id'].', '.$mod['modifier_group_id'].', this)">
                <i class="fa fa-trash"></i>
            </button>
        </td>
    </tr>';
}
?>

<!-- Modifier Group Modal -->
<div class="modal fade" id="modifier-group-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="group-modal-title">New Modifier Group</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="group-id" value="">
                <div class="form-group">
                    <label>Name <span class="text-danger">*</span></label>
                    <input type="text" id="group-name" class="form-control" placeholder="e.g. Milk Type, Size">
                </div>
                <div class="form-group">
                    <label>Selection Type</label>
                    <select id="group-selection-type" class="form-control">
                        <option value="single">Single — pick one</option>
                        <option value="multiple">Multiple — pick many</option>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Min Selections</label>
                            <input type="number" id="group-min" class="form-control" value="0" min="0">
                            <small class="text-muted">0 = optional</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Max Selections</label>
                            <input type="number" id="group-max" class="form-control" value="1" min="1">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="group-active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="saveGroup()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Modifier Option Modal -->
<div class="modal fade" id="modifier-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="modifier-modal-title">Add Option</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modifier-id" value="">
                <input type="hidden" id="modifier-group-id" value="">
                <div class="form-group">
                    <label>Option Name <span class="text-danger">*</span></label>
                    <input type="text" id="modifier-name" class="form-control" placeholder="e.g. Oat Milk">
                </div>
                <div class="form-group">
                    <label>Price Adjustment</label>
                    <input type="number" id="modifier-price" class="form-control" value="0.00" step="0.01">
                    <small class="text-muted">Positive = surcharge, negative = discount, 0 = no change</small>
                </div>
                <div class="form-group">
                    <label>Sort Order</label>
                    <input type="number" id="modifier-sort" class="form-control" value="0" min="0">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="modifier-active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="saveModifier()">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function openGroupModal(id, data) {
    $('#group-id').val(id || '');
    $('#group-name').val(data ? data.name : '');
    $('#group-selection-type').val(data ? data.selection_type : 'single');
    $('#group-min').val(data ? data.min_selections : 0);
    $('#group-max').val(data ? data.max_selections : 1);
    $('#group-active').val(data ? data.active : 1);
    $('#group-modal-title').text(id ? 'Edit Modifier Group' : 'New Modifier Group');
    $('#modifier-group-modal').modal('show');
}

function saveGroup() {
    var id = $('#group-id').val();
    var name = $.trim($('#group-name').val());
    if (!name) { alert('Name is required'); return; }

    $.post(ADMIN_URL + 'pos/ajax_save_modifier_group', {
        id: id,
        name: name,
        selection_type: $('#group-selection-type').val(),
        min_selections: $('#group-min').val(),
        max_selections: $('#group-max').val(),
        active: $('#group-active').val()
    }, function(resp) {
        if (resp.success) {
            $('#modifier-group-modal').modal('hide');
            location.reload();
        } else {
            alert(resp.message || 'Failed to save');
        }
    }, 'json');
}

function deleteGroup(id, btn) {
    if (!confirm('Delete this modifier group and all its options?')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_modifier_group/' + id, function(resp) {
        if (resp.success) {
            $('#group-panel-' + id).fadeOut(300, function() { $(this).remove(); });
        }
    }, 'json');
}

function openModifierModal(groupId, groupName, data) {
    $('#modifier-id').val(data ? data.id : '');
    $('#modifier-group-id').val(groupId);
    $('#modifier-name').val(data ? data.name : '');
    $('#modifier-price').val(data ? parseFloat(data.price_adjustment).toFixed(2) : '0.00');
    $('#modifier-sort').val(data ? data.sort_order : 0);
    $('#modifier-active').val(data ? data.active : 1);
    var title = data ? 'Edit Option' : 'Add Option' + (groupName ? ' — ' + groupName : '');
    $('#modifier-modal-title').text(title);
    $('#modifier-modal').modal('show');
}

function saveModifier() {
    var id = $('#modifier-id').val();
    var name = $.trim($('#modifier-name').val());
    var groupId = $('#modifier-group-id').val();
    if (!name) { alert('Name is required'); return; }

    $.post(ADMIN_URL + 'pos/ajax_save_modifier', {
        id: id,
        modifier_group_id: groupId,
        name: name,
        price_adjustment: $('#modifier-price').val(),
        sort_order: $('#modifier-sort').val(),
        active: $('#modifier-active').val()
    }, function(resp) {
        if (resp.success) {
            $('#modifier-modal').modal('hide');
            location.reload();
        } else {
            alert(resp.message || 'Failed to save');
        }
    }, 'json');
}

function deleteModifier(id, groupId, btn) {
    if (!confirm('Delete this option?')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_modifier/' + id, function(resp) {
        if (resp.success) {
            $('#modifier-row-' + id).fadeOut(300, function() {
                $(this).remove();
                if ($('#modifier-tbody-' + groupId + ' tr:visible').length === 0) {
                    $('#modifier-tbody-' + groupId).html('<tr class="empty-row"><td colspan="5" class="text-muted text-center">No options yet.</td></tr>');
                }
            });
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
