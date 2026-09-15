<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<?php
$type_labels = [
    'sop_open'  => 'Opening SOP',
    'sop_close' => 'Closing SOP',
    'equipment' => 'Equipment',
];
$warehouse_name_map = [];
foreach ($warehouses as $w) {
    $warehouse_name_map[$w['id']] = $w['name'];
}
?>

<div id="wrapper">
    <div class="content">

        <div class="row" style="margin-bottom:16px;">
            <div class="col-sm-6">
                <h4 class="no-margin-top" style="margin-bottom:4px;">Checklists</h4>
                <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                    <li><a href="<?php echo admin_url('pos/dashboard'); ?>">Dashboard</a></li>
                    <li class="active">Checklists</li>
                </ol>
            </div>
            <div class="col-sm-6 text-right">
                <a href="<?php echo admin_url('pos/checklist_form'); ?>" class="btn btn-info">
                    <i class="fa fa-plus"></i> Add Checklist
                </a>
            </div>
        </div>

        <form method="GET" action="<?php echo admin_url('pos/checklists'); ?>" id="filter-form">
        <div class="filter-bar" style="background:#fff;border:1px solid #ddd;border-radius:4px;padding:12px 16px;margin-bottom:18px;">
            <div class="row">
                <div class="col-md-3">
                    <select name="type" class="form-control input-sm" onchange="this.form.submit()">
                        <option value="">All Types</option>
                        <?php foreach ($type_labels as $val => $label): ?>
                        <option value="<?php echo $val; ?>" <?php echo $filters['type'] === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="store" class="form-control input-sm selectpicker" data-live-search="true" title="All Outlets" onchange="this.form.submit()">
                        <option value="" <?php echo $filters['warehouse_id'] === '' ? 'selected' : ''; ?>>All Outlets</option>
                        <?php foreach ($warehouses as $w): ?>
                        <option value="<?php echo (int)$w['id']; ?>" <?php echo (string)$filters['warehouse_id'] === (string)$w['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($w['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        </form>

        <?php if (empty($templates)) { ?>
            <p class="text-muted text-center mtop20">No checklists yet. <a href="<?php echo admin_url('pos/checklist_form'); ?>">Create your first checklist.</a></p>
        <?php } else { ?>
        <div class="panel_s">
            <div class="panel-body">
                <ul class="list-group no-margin" id="checklist-list">
                    <?php foreach ($templates as $t) {
                        if (empty($t['warehouse_ids'])) {
                            $outlet_label = '<span class="label label-default"><i class="fa fa-globe"></i> All outlets</span>';
                        } else {
                            $names = array_map(function ($wid) use ($warehouse_name_map) {
                                return htmlspecialchars($warehouse_name_map[$wid] ?? ('Outlet #' . $wid));
                            }, $t['warehouse_ids']);
                            $outlet_label = '<span class="label label-info">' . implode('</span> <span class="label label-info">', $names) . '</span>';
                        }
                        $inactive = (int)$t['is_active'] === 0 ? ' <span class="label label-default">Inactive</span>' : '';
                    ?>
                    <li class="list-group-item" id="checklist-item-<?php echo $t['id']; ?>" style="border-left:none;border-right:none;">
                        <div class="row" style="display:flex;align-items:center;">
                            <div class="col-md-7">
                                <span class="label label-info"><?php echo $type_labels[$t['type']] ?? $t['type']; ?></span>
                                <strong><?php echo htmlspecialchars($t['name']); ?></strong><?php echo $inactive; ?>
                                <div class="text-muted small mtop4"><?php echo $outlet_label; ?></div>
                            </div>
                            <div class="col-md-5 text-right">
                                <a href="<?php echo admin_url('pos/checklist_form/' . $t['id']); ?>" class="btn btn-default btn-sm">
                                    <i class="fa fa-pencil"></i> Edit
                                </a>
                                &nbsp;
                                <button type="button" class="btn btn-danger btn-sm" onclick="deleteChecklist(<?php echo $t['id']; ?>)">
                                    <i class="fa fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
        <?php } ?>

    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function deleteChecklist(id) {
    if (!confirm('Delete this checklist and all its groups/items? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_checklist_template', { id: id }, function (resp) {
        if (resp.success) {
            $('#checklist-item-' + id).fadeOut(200, function () { $(this).remove(); });
        } else {
            alert('Failed to delete.');
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
