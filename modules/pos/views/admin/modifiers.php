<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('pos/modifier_form'); ?>" class="btn btn-info">
                                    <i class="fa fa-plus"></i> Add Modifier
                                </a>
                            </div>
                        </div>
                        <hr />

                        <?php if (empty($groups)) { ?>
                            <p class="text-muted text-center mtop20">No modifiers yet. <a href="<?php echo admin_url('pos/modifier_form'); ?>">Create your first modifier.</a></p>
                        <?php } else { ?>
                        <ul class="list-group" id="modifiers-list">
                            <?php foreach ($groups as $group) {
                                $option_names = array_column($group['modifiers'], 'name');
                                $subtitle     = empty($option_names) ? '<em class="text-muted">No options</em>' : htmlspecialchars(implode(', ', $option_names));
                                $inactive     = (int)$group['active'] === 0 ? ' <span class="label label-default">Inactive</span>' : '';
                            ?>
                            <li class="list-group-item" id="modifier-item-<?php echo $group['id']; ?>">
                                <div class="row">
                                    <div class="col-md-1 text-muted" style="padding-top:4px;">
                                        <i class="fa fa-bars"></i>
                                    </div>
                                    <div class="col-md-9">
                                        <strong><?php echo htmlspecialchars($group['name']); ?></strong><?php echo $inactive; ?>
                                        <div class="text-muted small"><?php echo $subtitle; ?></div>
                                    </div>
                                    <div class="col-md-2 text-right">
                                        <a href="<?php echo admin_url('pos/modifier_form/' . $group['id']); ?>" class="btn btn-xs btn-default">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <button class="btn btn-xs btn-danger" onclick="deleteModifierGroup(<?php echo $group['id']; ?>, this)">
                                            <i class="fa fa-trash"></i>
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

function deleteModifierGroup(id, btn) {
    if (!confirm('Delete this modifier and all its options? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_modifier_group/' + id, function(resp) {
        if (resp.success) {
            $('#modifier-item-' + id).fadeOut(250, function() { $(this).remove(); });
        } else {
            alert('Failed to delete.');
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
