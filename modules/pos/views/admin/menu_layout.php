<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <div class="pull-left">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                                <p class="text-muted" style="margin-bottom:0;font-size:13px;">
                                    Arrange how your menu appears on delivery apps: group categories into sections, then rank sections, categories, and items.
                                </p>
                            </div>
                            <div class="pull-right">
                                <a href="<?php echo admin_url('pos/products'); ?>" class="btn btn-default">
                                    <i class="fa fa-cube"></i> Products
                                </a>
                                <?php if (has_permission('pos', '', 'create')) { ?>
                                <button class="btn btn-info" onclick="openSectionModal()">
                                    <i class="fa fa-plus"></i> Add Section
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr />

                        <?php if (empty($sections)) { ?>
                        <p class="text-muted text-center mtop30">No menu sections yet. Add one to get started.</p>
                        <?php } ?>

                        <?php foreach ($sections as $i => $section) {
                            $section_categories = $categories_by_section[$section['id']] ?? [];
                        ?>
                        <div class="panel panel-default" style="margin-bottom:18px;">
                            <div class="panel-heading" style="display:flex;align-items:center;justify-content:space-between;">
                                <div>
                                    <strong><?php echo htmlspecialchars($section['name']); ?></strong>
                                    <?php if (!(int)$section['active']) { ?>
                                        <span class="label label-default">Inactive</span>
                                    <?php } ?>
                                    <span class="text-muted small">— <?php echo count($section_categories); ?> categor<?php echo count($section_categories) === 1 ? 'y' : 'ies'; ?></span>
                                </div>
                                <div>
                                    <button class="btn btn-xs btn-default" title="Move up" onclick="reorderSection(<?php echo $section['id']; ?>, 'up')" <?php echo $i === 0 ? 'disabled' : ''; ?>><i class="fa fa-arrow-up"></i></button>
                                    <button class="btn btn-xs btn-default" title="Move down" onclick="reorderSection(<?php echo $section['id']; ?>, 'down')" <?php echo $i === count($sections) - 1 ? 'disabled' : ''; ?>><i class="fa fa-arrow-down"></i></button>
                                    <?php if (has_permission('pos', '', 'edit')) { ?>
                                    <button class="btn btn-xs btn-default" onclick='openSectionModal(<?php echo json_encode($section); ?>)'><i class="fa fa-pencil"></i></button>
                                    <?php } ?>
                                    <?php if (has_permission('pos', '', 'delete')) { ?>
                                    <button class="btn btn-xs btn-danger" onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo htmlspecialchars(addslashes($section['name'])); ?>')"><i class="fa fa-trash"></i></button>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="panel-body">
                                <?php if (empty($section_categories)) { ?>
                                <p class="text-muted small">No categories assigned to this section yet.</p>
                                <?php } ?>

                                <?php foreach ($section_categories as $ci => $cat) {
                                    $cat_items = $items_by_category[$cat['id']] ?? [];
                                ?>
                                <div style="border:1px solid #eee;border-radius:4px;padding:10px 12px;margin-bottom:10px;">
                                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                                        <div>
                                            <i class="fa fa-folder-o text-muted"></i>
                                            <strong><?php echo htmlspecialchars($cat['sub_group_name']); ?></strong>
                                            <span class="text-muted small">(<?php echo count($cat_items); ?> item<?php echo count($cat_items) === 1 ? '' : 's'; ?>)</span>
                                        </div>
                                        <div style="display:flex;align-items:center;gap:6px;">
                                            <select class="form-control input-sm" style="width:170px;" onchange="moveCategoryToSection(<?php echo $cat['id']; ?>, this.value)">
                                                <?php foreach ($sections as $s) { ?>
                                                <option value="<?php echo $s['id']; ?>" <?php echo (int)$s['id'] === (int)$section['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['name']); ?></option>
                                                <?php } ?>
                                            </select>
                                            <button class="btn btn-xs btn-default" title="Move up" onclick="reorderCategory(<?php echo $cat['id']; ?>, 'up')" <?php echo $ci === 0 ? 'disabled' : ''; ?>><i class="fa fa-arrow-up"></i></button>
                                            <button class="btn btn-xs btn-default" title="Move down" onclick="reorderCategory(<?php echo $cat['id']; ?>, 'down')" <?php echo $ci === count($section_categories) - 1 ? 'disabled' : ''; ?>><i class="fa fa-arrow-down"></i></button>
                                        </div>
                                    </div>

                                    <?php if (!empty($cat_items)) { ?>
                                    <table class="table table-condensed" style="margin:8px 0 0;">
                                        <tbody>
                                            <?php foreach ($cat_items as $ii => $item) { ?>
                                            <tr>
                                                <td style="width:40px;">
                                                    <?php if (!empty($item['image'])) { ?>
                                                    <img src="<?php echo base_url('uploads/pos_items/' . $item['id'] . '/' . $item['image']); ?>" style="width:28px;height:28px;object-fit:cover;border-radius:4px;">
                                                    <?php } else { ?>
                                                    <span class="text-muted"><i class="fa fa-image"></i></span>
                                                    <?php } ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['sku_name']); ?></td>
                                                <td style="width:80px;">RM <?php echo number_format((float)$item['rate'], 2); ?></td>
                                                <td style="width:170px;">
                                                    <span class="label label-success">GrabFood</span>
                                                    <span class="label label-default" title="Coming soon">FoodPanda</span>
                                                    <span class="label label-default" title="Coming soon">ShopeeFood</span>
                                                </td>
                                                <td style="width:120px;">
                                                    <label class="checkbox-inline" style="margin:0;font-size:12px;">
                                                        <input type="checkbox" onchange="toggleItemStock(<?php echo $item['id']; ?>, this.checked)" <?php echo !empty($item['out_of_stock']) ? 'checked' : ''; ?>>
                                                        Out of stock
                                                    </label>
                                                </td>
                                                <td class="text-right" style="width:110px;white-space:nowrap;">
                                                    <button class="btn btn-xs btn-default" title="Move up" onclick="reorderItem(<?php echo $item['id']; ?>, 'up')" <?php echo $ii === 0 ? 'disabled' : ''; ?>><i class="fa fa-arrow-up"></i></button>
                                                    <button class="btn btn-xs btn-default" title="Move down" onclick="reorderItem(<?php echo $item['id']; ?>, 'down')" <?php echo $ii === count($cat_items) - 1 ? 'disabled' : ''; ?>><i class="fa fa-arrow-down"></i></button>
                                                    <a class="btn btn-xs btn-primary" href="<?php echo admin_url('pos/products?edit=' . $item['id']); ?>" title="Edit details"><i class="fa fa-pencil"></i></a>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                    <?php } ?>
                                </div>
                                <?php } ?>

                                <?php if (!empty($items_by_category[0]) && $i === 0) { ?>
                                <div style="border:1px dashed #ddd;border-radius:4px;padding:10px 12px;">
                                    <strong class="text-muted"><i class="fa fa-folder-o"></i> Uncategorized</strong>
                                    <span class="text-muted small">— products with no Sub Group assigned</span>
                                    <table class="table table-condensed" style="margin:8px 0 0;">
                                        <tbody>
                                            <?php foreach ($items_by_category[0] as $item) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['sku_name']); ?></td>
                                                <td class="text-right">
                                                    <a class="btn btn-xs btn-primary" href="<?php echo admin_url('pos/products?edit=' . $item['id']); ?>" title="Assign a sub group"><i class="fa fa-pencil"></i></a>
                                                </td>
                                            </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Section Modal -->
<div class="modal fade" id="section-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="section-modal-title">Add Section</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="section-edit-id" value="">
                <div class="form-group">
                    <label>Section Name <span class="text-danger">*</span></label>
                    <input type="text" id="section-name" class="form-control" placeholder="e.g. Breakfast, All Day Menu">
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="section-active" checked>
                        Active
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="section-save-btn" onclick="saveSection()">
                    <i class="fa fa-check"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function openSectionModal(section) {
    section = section || {};
    $('#section-edit-id').val(section.id || '');
    $('#section-name').val(section.name || '');
    $('#section-active').prop('checked', section.id ? !!parseInt(section.active) : true);
    $('#section-modal-title').text(section.id ? 'Edit Section' : 'Add Section');
    $('#section-modal').modal('show');
}

function saveSection() {
    var id     = $('#section-edit-id').val();
    var name   = $.trim($('#section-name').val());
    var active = $('#section-active').is(':checked') ? 1 : 0;

    if (!name) { alert('Section name is required'); return; }

    var btn = $('#section-save-btn').prop('disabled', true);
    $.post(ADMIN_URL + 'pos/ajax_save_menu_section', { id: id, name: name, active: active }, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to save section');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

function deleteSection(id, name) {
    if (!confirm('Delete section "' + name + '"?\n\nIts categories will become unassigned, not deleted.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_menu_section', { id: id }, function (resp) {
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to delete section');
        }
    }, 'json');
}

function reorderSection(id, direction) {
    $.post(ADMIN_URL + 'pos/ajax_reorder_menu_section', { id: id, direction: direction }, function (resp) {
        if (resp.success) { location.reload(); } else { alert('Failed to reorder'); }
    }, 'json');
}

function moveCategoryToSection(subGroupId, sectionId) {
    $.post(ADMIN_URL + 'pos/ajax_save_category_section', { sub_group_id: subGroupId, section_id: sectionId }, function (resp) {
        if (resp.success) { location.reload(); } else { alert('Failed to move category'); }
    }, 'json');
}

function reorderCategory(subGroupId, direction) {
    $.post(ADMIN_URL + 'pos/ajax_reorder_category', { sub_group_id: subGroupId, direction: direction }, function (resp) {
        if (resp.success) { location.reload(); } else { alert('Failed to reorder'); }
    }, 'json');
}

function reorderItem(itemId, direction) {
    $.post(ADMIN_URL + 'pos/ajax_reorder_item', { item_id: itemId, direction: direction }, function (resp) {
        if (resp.success) { location.reload(); } else { alert('Failed to reorder'); }
    }, 'json');
}

function toggleItemStock(itemId, outOfStock) {
    $.post(ADMIN_URL + 'pos/ajax_toggle_item_stock', { item_id: itemId, out_of_stock: outOfStock ? 1 : 0 }, function (resp) {
        if (!resp.success) { alert('Failed to update stock status'); }
    }, 'json');
}
</script>

<?php init_tail(); ?>
