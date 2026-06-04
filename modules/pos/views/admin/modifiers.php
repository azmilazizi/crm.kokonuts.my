<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">

                        <!-- Toolbar -->
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <button id="btn-delete-selected" class="btn btn-danger" onclick="deleteSelected()" disabled>
                                    <i class="fa fa-trash"></i> Delete Selected
                                </button>
                                &nbsp;
                                <a href="<?php echo admin_url('pos/modifier_form'); ?>" class="btn btn-info">
                                    <i class="fa fa-plus"></i> Add Modifier
                                </a>
                            </div>
                        </div>
                        <hr />

                        <?php if (empty($groups)) { ?>
                            <p class="text-muted text-center mtop20">No modifiers yet. <a href="<?php echo admin_url('pos/modifier_form'); ?>">Create your first modifier.</a></p>
                        <?php } else { ?>

                        <!-- Select all row -->
                        <div class="row" style="padding: 8px 15px; border-bottom: 1px solid #eee; margin-bottom: 4px;">
                            <div class="col-md-1">
                                <input type="checkbox" id="select-all" title="Select all">
                            </div>
                            <div class="col-md-11 text-muted small" style="padding-top:2px;">
                                Modifier
                            </div>
                        </div>

                        <ul class="list-group no-margin" id="modifiers-list">
                            <?php foreach ($groups as $group) {
                                $option_names = array_column($group['modifiers'], 'name');
                                $subtitle = empty($option_names)
                                    ? '<em class="text-muted">No options</em>'
                                    : htmlspecialchars(implode(', ', $option_names));
                                $inactive = (int)$group['active'] === 0
                                    ? ' <span class="label label-default">Inactive</span>'
                                    : '';
                            ?>
                            <li class="list-group-item" id="modifier-item-<?php echo $group['id']; ?>" style="border-left: none; border-right: none;">
                                <div class="row" style="display:flex; align-items:center;">
                                    <div class="col-md-1">
                                        <input type="checkbox" class="modifier-checkbox" value="<?php echo $group['id']; ?>">
                                    </div>
                                    <div class="col-md-7">
                                        <strong><?php echo htmlspecialchars($group['name']); ?></strong><?php echo $inactive; ?>
                                        <div class="text-muted small"><?php echo $subtitle; ?></div>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <a href="<?php echo admin_url('pos/modifier_form/' . $group['id']); ?>" class="btn btn-default btn-sm">
                                            <i class="fa fa-pencil"></i> Edit
                                        </a>
                                        &nbsp;
                                        <button class="btn btn-danger btn-sm" onclick="deleteSingle(<?php echo $group['id']; ?>, this)">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </li>
                            <?php } ?>
                        </ul>
                        <?php } ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function updateDeleteBtn() {
    var checked = $('.modifier-checkbox:checked').length;
    $('#btn-delete-selected').prop('disabled', checked === 0);
}

function deleteSelected() {
    var ids = $('.modifier-checkbox:checked').map(function () { return $(this).val(); }).get();
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' modifier(s) and all their options? This cannot be undone.')) return;

    $.post(ADMIN_URL + 'pos/ajax_delete_modifier_groups_bulk', { ids: ids }, function (resp) {
        if (resp.success) {
            $.each(ids, function (i, id) {
                $('#modifier-item-' + id).fadeOut(200, function () { $(this).remove(); });
            });
            $('#btn-delete-selected').prop('disabled', true);
            $('#select-all').prop('checked', false).prop('indeterminate', false);
        } else {
            alert('Failed to delete selected items.');
        }
    }, 'json');
}

function deleteSingle(id, btn) {
    if (!confirm('Delete this modifier and all its options? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_modifier_group/' + id, function (resp) {
        if (resp.success) {
            $('#modifier-item-' + id).fadeOut(250, function () {
                $(this).remove();
                updateDeleteBtn();
            });
        } else {
            alert('Failed to delete.');
        }
    }, 'json');
}

$(function () {
    $('#select-all').on('change', function () {
        $('.modifier-checkbox').prop('checked', $(this).is(':checked'));
        updateDeleteBtn();
    });

    $(document).on('change', '.modifier-checkbox', function () {
        var total   = $('.modifier-checkbox').length;
        var checked = $('.modifier-checkbox:checked').length;
        $('#select-all')
            .prop('indeterminate', checked > 0 && checked < total)
            .prop('checked', checked === total);
        updateDeleteBtn();
    });
});
</script>
<?php init_tail(); ?>
