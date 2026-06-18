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
                                    Choose which categories appear on delivery apps and rank them. Item availability and pricing for delivery platforms are set on the
                                    <a href="<?php echo admin_url('pos/products'); ?>">Products</a> page.
                                    Changes here are a draft — click <strong>Sync</strong> to push them live.
                                </p>
                                <?php if (!empty($last_synced_at)) { ?>
                                <p class="text-muted small" id="last-synced-label" style="margin-bottom:0;">
                                    Last synced: <?php echo date('d M Y, H:i', strtotime($last_synced_at)); ?>
                                </p>
                                <?php } else { ?>
                                <p class="text-muted small" id="last-synced-label" style="margin-bottom:0;">Never synced yet.</p>
                                <?php } ?>
                            </div>
                            <div class="pull-right">
                                <a href="<?php echo admin_url('pos/products'); ?>" class="btn btn-default">
                                    <i class="fa fa-cube"></i> Products
                                </a>
                                <?php if (has_permission('pos', '', 'create')) { ?>
                                <button class="btn btn-default" onclick="openAddCategoryModal()">
                                    <i class="fa fa-plus"></i> Add Category
                                </button>
                                <?php } ?>
                                <?php if (has_permission('pos', '', 'edit')) { ?>
                                <button class="btn btn-info" id="sync-btn" onclick="syncFdMenu()">
                                    <i class="fa fa-refresh"></i> Sync
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr />

                        <?php if (empty($categories)) { ?>
                        <p class="text-muted text-center mtop30">No categories added to the Food Delivery menu yet. Click "Add Category" to get started.</p>
                        <?php } ?>

                        <?php foreach ($categories as $ci => $cat) {
                            $cat_items = $items_by_category[$cat['id']] ?? [];
                            $is_pending = !(int)$cat['published'] || (int)$cat['sort_order'] !== (int)($cat['sort_order_published'] ?? -1);
                        ?>
                        <div style="border:1px solid #eee;border-radius:4px;padding:10px 12px;margin-bottom:10px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px;">
                                <div>
                                    <i class="fa fa-folder-o text-muted"></i>
                                    <strong><?php echo htmlspecialchars($cat['sub_group_name']); ?></strong>
                                    <span class="text-muted small">(<?php echo count($cat_items); ?> item<?php echo count($cat_items) === 1 ? '' : 's'; ?>)</span>
                                    <?php if ($is_pending) { ?>
                                        <span class="label label-warning">Pending sync</span>
                                    <?php } else { ?>
                                        <span class="label label-success">Synced</span>
                                    <?php } ?>
                                </div>
                                <div>
                                    <button class="btn btn-xs btn-default" title="Move up" onclick="reorderCategory(<?php echo $cat['id']; ?>, 'up')" <?php echo $ci === 0 ? 'disabled' : ''; ?>><i class="fa fa-arrow-up"></i></button>
                                    <button class="btn btn-xs btn-default" title="Move down" onclick="reorderCategory(<?php echo $cat['id']; ?>, 'down')" <?php echo $ci === count($categories) - 1 ? 'disabled' : ''; ?>><i class="fa fa-arrow-down"></i></button>
                                    <?php if (has_permission('pos', '', 'edit')) { ?>
                                    <button class="btn btn-xs btn-danger" title="Disable all items in this category for Food Delivery platforms" onclick="disableCategoryFd(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars(addslashes($cat['sub_group_name'])); ?>')">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                    <?php } ?>
                                </div>
                            </div>

                            <?php if (!empty($cat_items)) { ?>
                            <table class="table table-condensed" style="margin:8px 0 0;">
                                <tbody>
                                    <?php foreach ($cat_items as $item) { ?>
                                    <tr>
                                        <td style="width:40px;">
                                            <?php if (!empty($item['image'])) { ?>
                                            <img src="<?php echo base_url('uploads/pos_items/' . $item['id'] . '/' . $item['image']); ?>" style="width:28px;height:28px;object-fit:cover;border-radius:4px;">
                                            <?php } else { ?>
                                            <span class="text-muted"><i class="fa fa-image"></i></span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['sku_name']); ?></td>
                                        <td style="width:90px;">
                                            RM <?php echo number_format((float)($item['fd_price'] ?: $item['rate']), 2); ?>
                                            <?php if (!empty($item['fd_price'])) { ?><span class="text-muted small" title="Regular price RM <?php echo number_format((float)$item['rate'], 2); ?>"><i class="fa fa-info-circle"></i></span><?php } ?>
                                        </td>
                                        <td style="width:130px;">
                                            <?php if (empty($item['fd_available'])) { ?>
                                                <span class="label label-default">Not available</span>
                                            <?php } else { ?>
                                                <span class="label label-success">Available</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <?php } else { ?>
                            <p class="text-muted small" style="margin:8px 0 0;">No products in this category yet.</p>
                            <?php } ?>
                        </div>
                        <?php } ?>

                        <?php if (!empty($items_by_category[0])) { ?>
                        <div style="border:1px dashed #ddd;border-radius:4px;padding:10px 12px;">
                            <strong class="text-muted"><i class="fa fa-folder-o"></i> Uncategorized</strong>
                            <span class="text-muted small">— products with no Sub Group assigned; assign one on the Products page, then add that category here</span>
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
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="add-category-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Add Category</h4>
            </div>
            <div class="modal-body">
                <?php if (!empty($addable_categories)) { ?>
                <div class="form-group">
                    <label>Sub Group <small class="text-muted">— from your POS Products</small></label>
                    <select id="add-category-sub-group" class="form-control selectpicker" data-live-search="true" title="Select a category...">
                        <?php foreach ($addable_categories as $sg) { ?>
                        <option value="<?php echo $sg['id']; ?>"><?php echo htmlspecialchars($sg['sub_group_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <?php } else { ?>
                <p class="text-muted">All your Sub Groups with products are already on the Food Delivery menu.</p>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <?php if (!empty($addable_categories)) { ?>
                <button type="button" class="btn btn-info" id="add-category-save-btn" onclick="saveAddCategory()">
                    <i class="fa fa-check"></i> Add
                </button>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function openAddCategoryModal() {
    $('#add-category-modal').modal('show');
}

function saveAddCategory() {
    var subGroupId = $('#add-category-sub-group').val();
    if (!subGroupId) { return; }

    var btn = $('#add-category-save-btn').prop('disabled', true);
    $.post(ADMIN_URL + 'pos/ajax_add_category', { sub_group_id: subGroupId }, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to add category');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

function disableCategoryFd(subGroupId, name) {
    if (!confirm('Remove "' + name + '" from the Food Delivery Menu Layout?\n\nThe category can be added back using "Add Category". Item availability settings are not changed.')) return;
    $.post(ADMIN_URL + 'pos/ajax_disable_category_fd', { sub_group_id: subGroupId }, function (resp) {
        if (resp.success) {
            location.reload();
        } else {
            alert('Failed to remove category');
        }
    }, 'json');
}

function reorderCategory(subGroupId, direction) {
    $.post(ADMIN_URL + 'pos/ajax_reorder_category', { sub_group_id: subGroupId, direction: direction }, function (resp) {
        if (resp.success) { location.reload(); } else { alert('Failed to reorder'); }
    }, 'json');
}

function syncFdMenu() {
    var btn = $('#sync-btn').prop('disabled', true);
    $.post(ADMIN_URL + 'pos/ajax_sync_fd_menu', {}, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Sync failed');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}
</script>

<?php init_tail(); ?>
