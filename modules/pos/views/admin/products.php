<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="clearfix">
                            <h4 class="no-margin-top pull-left"><?php echo $title; ?></h4>
                            <div class="pull-right">
                                <a href="<?php echo admin_url('pos/menu_layout'); ?>" class="btn btn-default">
                                    <i class="fa fa-sitemap"></i> Menu Layout
                                </a>
                                <?php if (has_permission('pos', '', 'edit')) { ?>
                                <button class="btn btn-default" id="bulk-warehouses-btn" onclick="openBulkWarehousesModal()" disabled>
                                    <i class="fa fa-building-o"></i> <span id="bulk-btn-label">Bulk Warehouses</span>
                                </button>
                                <?php } ?>
                                <?php if (has_permission('pos', '', 'create')) { ?>
                                <button class="btn btn-info" onclick="openProductModal()">
                                    <i class="fa fa-plus"></i> Add Product
                                </button>
                                <?php } ?>
                            </div>
                        </div>
                        <hr />
                        <table class="table dt-table" id="pos-products-table">
                            <thead>
                                <tr>
                                    <th style="width:30px;"><input type="checkbox" id="select-all-products" onchange="onSelectAllProducts(this)" title="Select all"></th>
                                    <th style="width:40px;"></th>
                                    <th>SKU Name</th>
                                    <th>SKU Code</th>
                                    <th>Group</th>
                                    <th>Sub Group</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Food Delivery</th>
                                    <th>Warehouses</th>
                                    <th>Modifiers</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item) { ?>
                                <tr id="product-row-<?php echo $item['id']; ?>">
                                    <td><input type="checkbox" class="product-select-cb" value="<?php echo $item['id']; ?>" onchange="onProductCheck(this)"></td>
                                    <td>
                                        <?php if (!empty($item['image'])) { ?>
                                        <img src="<?php echo base_url('uploads/pos_items/' . $item['id'] . '/' . $item['image']); ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px;">
                                        <?php } else { ?>
                                        <span class="text-muted"><i class="fa fa-image"></i></span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['sku_name']); ?>
                                        <?php if (isset($promo_flags[$item['id']])) {
                                            $pf = $promo_flags[$item['id']];
                                            $cls = $pf['type'] === 'bundle' ? 'label-info' : 'label-warning';
                                        ?>
                                        <span class="label <?php echo $cls; ?>" style="margin-left:4px;font-size:10px;"><?php echo ucfirst($pf['type']); ?></span>
                                        <?php } ?>
                                    </td>
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
                                        <?php if (empty($item['fd_available'])) { ?>
                                            <span class="label label-default">Not available</span>
                                        <?php } else { ?>
                                            <span class="label label-success">Available</span>
                                        <?php } ?>
                                        <?php if ((int)$item['fd_available'] !== (int)($item['fd_available_published'] ?? -1) || (float)($item['fd_price'] ?? 0) !== (float)($item['fd_price_published'] ?? 0)) { ?>
                                            <span class="label label-warning" title="Saved here, but not yet pushed to delivery platforms">Pending sync</span>
                                        <?php } ?>
                                    </td>
                                    <td id="warehouse-cell-<?php echo $item['id']; ?>">
                                        <?php
                                        $wids = $item_warehouse_ids[$item['id']] ?? [];
                                        if (empty($wids)) { ?>
                                        <span class="text-muted small"><i class="fa fa-globe"></i> All</span>
                                        <?php } else {
                                            $wnames = [];
                                            foreach ($warehouses as $w) {
                                                if (in_array((int)$w['warehouse_id'], array_map('intval', $wids))) {
                                                    $wnames[] = htmlspecialchars($w['warehouse_name']);
                                                }
                                            }
                                        ?>
                                        <span class="small"><?php echo implode(', ', $wnames); ?></span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <span id="modifier-count-<?php echo $item['id']; ?>" class="text-muted small">—</span>
                                    </td>
                                    <td class="text-right" style="white-space:nowrap;">
                                        <button class="btn btn-sm btn-default"
                                            onclick="openModifiersModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['sku_name'])); ?>')">
                                            <i class="fa fa-sliders"></i> Modifiers
                                        </button>
                                        <?php if (has_permission('pos', '', 'edit')) { ?>
                                        <button class="btn btn-sm btn-primary"
                                            onclick="editProduct(<?php echo $item['id']; ?>)">
                                            <i class="fa fa-pencil"></i>
                                        </button>
                                        <?php } ?>
                                        <?php if (has_permission('pos', '', 'delete')) { ?>
                                        <button class="btn btn-sm btn-danger"
                                            onclick="deleteProduct(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['sku_name'])); ?>')">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                        <?php } ?>
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

<!-- Bulk Warehouses Modal -->
<div class="modal fade" id="bulk-warehouses-modal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-building-o"></i> Bulk Warehouse Availability</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted small" id="bulk-selected-info"></p>

                <div class="form-group">
                    <label>Action</label>
                    <select id="bulk-mode" class="form-control">
                        <option value="replace">Replace — set exactly these warehouses</option>
                        <option value="add">Add — add these warehouses to existing</option>
                        <option value="remove">Remove — remove these warehouses from existing</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Warehouses</label>
                    <?php if (!empty($warehouses)) { ?>
                    <div id="bulk-warehouse-checks" style="border:1px solid #ddd; border-radius:4px; padding:8px 12px; max-height:200px; overflow-y:auto;">
                        <?php foreach ($warehouses as $w) { ?>
                        <div class="checkbox" style="margin:4px 0;">
                            <label>
                                <input type="checkbox" class="bulk-wh-cb" value="<?php echo $w['warehouse_id']; ?>">
                                <?php echo htmlspecialchars($w['warehouse_name']); ?>
                            </label>
                        </div>
                        <?php } ?>
                    </div>
                    <p id="bulk-replace-hint" class="help-block small text-muted" style="margin-top:4px;">
                        With <em>Replace</em>: leaving all unchecked means available at <strong>all</strong> warehouses.
                    </p>
                    <?php } else { ?>
                    <p class="text-muted">No warehouses configured.</p>
                    <?php } ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="bulk-save-btn" onclick="saveBulkWarehouses()">
                    <i class="fa fa-check"></i> Apply
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Product Modal -->
<div class="modal fade" id="product-modal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="product-modal-title">Add Product</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="product-edit-id" value="">

                <div class="form-group">
                    <label>SKU Name <span class="text-danger">*</span></label>
                    <input type="text" id="product-sku-name" class="form-control" placeholder="e.g. Iced Americano">
                </div>

                <div class="form-group">
                    <label>Description <small class="text-muted">— shown to customers on delivery apps</small></label>
                    <textarea id="product-description" class="form-control" rows="2" placeholder="e.g. Espresso, cold milk, ice"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>SKU Code <small class="text-muted">(auto-generated if empty)</small></label>
                            <input type="text" id="product-sku-code" class="form-control" placeholder="e.g. ICED-AMR">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Price (RM) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon">RM</span>
                                <input type="number" id="product-rate" class="form-control" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="product-image-group">
                    <label>Image <small class="text-muted">— used on GrabFood and other delivery channels</small></label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <img id="product-image-preview" src="" style="display:none;width:64px;height:64px;object-fit:cover;border:1px solid #ddd;border-radius:4px;">
                        <div>
                            <input type="file" id="product-image-file" accept="image/png,image/jpeg,image/webp" onchange="uploadProductImage()">
                            <p class="help-block small" id="product-image-hint" style="margin-bottom:0;">Save the product first, then upload an image.</p>
                            <button type="button" class="btn btn-xs btn-link text-danger" id="product-image-remove-btn" style="display:none;padding-left:0;" onclick="removeProductImage()">
                                <i class="fa fa-trash"></i> Remove image
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Group</label>
                            <select id="product-group-id" class="form-control selectpicker" data-live-search="true" title="Select group...">
                                <option value="">— None —</option>
                                <?php foreach ($item_groups as $g) { ?>
                                <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Sub Group</label>
                            <select id="product-sub-group" class="form-control selectpicker" data-live-search="true" title="Select sub group...">
                                <option value="">— None —</option>
                                <?php foreach ($sub_groups as $sg) { ?>
                                <option value="<?php echo $sg['id']; ?>" data-group="<?php echo $sg['group_id']; ?>"><?php echo htmlspecialchars($sg['sub_group_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select id="product-active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div style="background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:12px 14px;margin-bottom:15px;">
                    <div style="margin:0 0 8px;">
                        <label style="font-weight:normal;cursor:pointer;">
                            <input type="checkbox" id="product-fd-available" style="margin-right:6px;">
                            Available on Food Delivery Platforms <small class="text-muted">(GrabFood, and FoodPanda/ShopeeFood once connected)</small>
                        </label>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Food Delivery Price <small class="text-muted">— leave blank to use the price above</small></label>
                        <div class="input-group">
                            <span class="input-group-addon">RM</span>
                            <input type="number" id="product-fd-price" class="form-control" step="0.01" min="0" placeholder="Same as regular price">
                        </div>
                    </div>
                    <p class="help-block small" style="margin:8px 0 0;">
                        Changes here are saved as a draft. Click <strong>Sync</strong> on the
                        <a href="<?php echo admin_url('pos/menu_layout'); ?>">Food Delivery Menu Layout</a> page to push them live.
                    </p>
                </div>

                <div class="form-group">
                    <label>Available at Warehouses <small class="text-muted">— leave blank for all warehouses</small></label>
                    <select id="product-warehouse-ids" class="form-control selectpicker" multiple
                        data-live-search="true"
                        data-selected-text-format="count > 1"
                        title="All warehouses (global)">
                        <?php foreach ($warehouses as $w) { ?>
                        <option value="<?php echo $w['warehouse_id']; ?>"><?php echo htmlspecialchars($w['warehouse_name']); ?></option>
                        <?php } ?>
                    </select>
                    <p class="help-block small">Select specific warehouses to restrict this product. If none selected, it appears on all POS terminals.</p>
                </div>

                <?php if (!empty($warehouses)) { ?>
                <div class="form-group">
                    <label>Warehouse Prices <small class="text-muted">— leave blank to use default price</small></label>
                    <table class="table table-condensed" id="warehouse-prices-table" style="margin-bottom:0;">
                        <thead>
                            <tr>
                                <th style="width:55%">Warehouse</th>
                                <th>Price (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($warehouses as $w) { ?>
                            <tr>
                                <td style="vertical-align:middle;"><?php echo htmlspecialchars($w['warehouse_name']); ?></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-addon">RM</span>
                                        <input type="number"
                                            class="form-control warehouse-price-input"
                                            data-warehouse-id="<?php echo $w['warehouse_id']; ?>"
                                            id="wh-price-<?php echo $w['warehouse_id']; ?>"
                                            step="0.01" min="0"
                                            placeholder="Default">
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>

                <!-- ── CRM: Promo / Bundle Flag ─────────────────────────────── -->
                <div style="border-top:1px solid #eee;margin-top:16px;padding-top:14px;">
                    <div class="checkbox" style="margin:0 0 6px;">
                        <label style="font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" id="product-promo-enabled" onchange="onPromoToggle()" style="width:15px;height:15px;margin:0;flex-shrink:0;">
                            <span>Flag as Promo / Bundle <small class="text-muted" style="font-weight:normal;">(CRM reporting only — not synced to POS)</small></span>
                        </label>
                    </div>

                    <div id="promo-fields" style="display:none;background:#f9f9f9;border:1px solid #e4e4e4;border-radius:4px;padding:12px 14px;margin-top:8px;">

                        <div class="row">
                            <div class="col-sm-5">
                                <div class="form-group" style="margin-bottom:10px;">
                                    <label class="small text-muted" style="text-transform:uppercase;letter-spacing:.4px;">Type</label>
                                    <select id="product-promo-type" class="form-control input-sm" onchange="onPromoTypeChange()">
                                        <option value="promo">Promo — discounted single item</option>
                                        <option value="bundle">Bundle — composed of multiple items</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-3">
                                <div class="form-group" style="margin-bottom:10px;">
                                    <label class="small text-muted" style="text-transform:uppercase;letter-spacing:.4px;">Discount</label>
                                    <select id="product-promo-discount-type" class="form-control input-sm">
                                        <option value="">None</option>
                                        <option value="percentage">% off</option>
                                        <option value="fixed">RM off</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="form-group" style="margin-bottom:10px;">
                                    <label class="small text-muted" style="text-transform:uppercase;letter-spacing:.4px;">Value</label>
                                    <input type="number" id="product-promo-discount-value" class="form-control input-sm" min="0" step="0.01" placeholder="0.00" value="0">
                                </div>
                            </div>
                        </div>

                        <!-- Flat components (promo type) -->
                        <div id="promo-comp-section">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <label class="small text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin:0;">Components</label>
                                <button type="button" class="btn btn-default btn-xs" onclick="promoAddComponent()"><i class="fa fa-plus"></i> Add Component</button>
                            </div>
                            <div id="promo-components-list"></div>
                            <p id="promo-no-components" class="text-muted small" style="margin:4px 0 0;">No components yet.</p>
                        </div>

                        <!-- Bundle groups (bundle type) -->
                        <div id="bundle-groups-section" style="display:none;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <label class="small text-muted" style="text-transform:uppercase;letter-spacing:.4px;margin:0;">Bundle Groups</label>
                                <div>
                                    <button type="button" class="btn btn-default btn-xs" onclick="bundleAddChoiceGroup()"><i class="fa fa-plus"></i> Choice Group</button>
                                    <button type="button" class="btn btn-default btn-xs" style="margin-left:3px;" onclick="bundleAddModifierGroup()"><i class="fa fa-plus"></i> Modifier Group</button>
                                </div>
                            </div>
                            <div id="bundle-groups-list"></div>
                            <p id="bundle-no-groups" class="text-muted small" style="margin:4px 0 0;">No groups yet — add a Choice Group or Modifier Group.</p>
                        </div>

                    </div>
                </div>
                <!-- ── /CRM Promo Flag ──────────────────────────────────────── -->
            </div><!-- /.modal-body -->
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="product-save-btn" onclick="saveProduct()">
                    <i class="fa fa-check"></i> <span id="product-save-label">Add Product</span>
                </button>
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
                            <th>Type</th>
                            <th>Options</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="individual-tbody">
                        <tr id="individual-empty"><td colspan="4" class="text-muted text-center">No individual modifiers.</td></tr>
                    </tbody>
                </table>

                <!-- Add / edit individual modifier inline form -->
                <div id="indiv-form-panel" style="background:#f9f9f9; border:1px solid #ddd; border-radius:4px; padding:14px; margin-top:10px;">
                    <input type="hidden" id="indiv-edit-id" value="">
                    <div class="row" style="margin-bottom:8px;">
                        <div class="col-md-6">
                            <label class="text-muted small">Modifier name <span class="text-danger">*</span></label>
                            <input type="text" id="indiv-name" class="form-control" placeholder="e.g. Cup Size, Sugar Level">
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small">Selection type</label>
                            <select id="indiv-selection-type" class="form-control">
                                <option value="single">Single — pick one</option>
                                <option value="multiple">Multiple — pick many</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="text-muted small">Sort</label>
                            <input type="number" id="indiv-sort" class="form-control" value="0" min="0">
                        </div>
                    </div>

                    <div class="row" style="margin-bottom:4px; padding:0 15px;">
                        <div class="col-md-7"><label class="text-muted small">Option name</label></div>
                        <div class="col-md-4"><label class="text-muted small">Price adj. (RM)</label></div>
                        <div class="col-md-1"></div>
                    </div>
                    <div id="indiv-options-list"></div>
                    <div class="mtop6">
                        <button type="button" class="btn btn-link btn-sm" onclick="indivAddOption()">
                            <i class="fa fa-plus-circle"></i> Add option
                        </button>
                    </div>

                    <div class="mtop10 text-right">
                        <button type="button" class="btn btn-default btn-sm" onclick="indivResetForm()">Clear</button>
                        &nbsp;
                        <button type="button" class="btn btn-info btn-sm" onclick="saveIndividualModifier()">
                            <i class="fa fa-check"></i> <span id="indiv-save-label">Add Modifier</span>
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

<style>
.promo-comp-row { background:#fff; border:1px solid #ddd; border-radius:3px; padding:6px 8px; margin-bottom:4px; }
.promo-comp-row select, .promo-comp-row input[type=text], .promo-comp-row input[type=number] { height:26px; font-size:11px; padding:2px 6px; }
.promo-comp-row .remove-comp { color:#d9534f; cursor:pointer; line-height:26px; padding:0 4px; }
.bundle-group-card { background:#fff; border:1px solid #d0d0d0; border-radius:4px; padding:8px 10px; margin-bottom:8px; }
.bundle-group-card .bg-header { display:flex; align-items:center; gap:6px; margin-bottom:6px; }
.bundle-group-card .bg-header input { height:26px; font-size:11px; padding:2px 6px; flex:1; }
.bundle-group-card .bg-header select { height:26px; font-size:11px; padding:2px 4px; width:auto; }
.bundle-group-card .bg-ref-wrap { margin-bottom:6px; }
.bundle-group-card .bg-ref-wrap select { height:26px; font-size:11px; padding:2px 6px; width:100%; }
.bg-opt-row { display:flex; gap:4px; margin-bottom:3px; align-items:center; }
.bg-opt-row select { height:26px; font-size:11px; padding:2px 6px; flex:1; }
.bg-opt-row .rm { color:#d9534f; cursor:pointer; line-height:26px; padding:0 4px; flex-shrink:0; }

#bulk-warehouse-checks .checkbox label {
    padding-left: 0;
    display: flex;
    align-items: center;
    gap: 6px;
}
#bulk-warehouse-checks .checkbox input[type="checkbox"] {
    position: static;
    margin-left: 0;
    -webkit-appearance: checkbox;
    -moz-appearance: checkbox;
    appearance: checkbox;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    cursor: pointer;
}
</style>
<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';
var BASE_URL  = '<?php echo base_url(); ?>';
var _allSubGroups = <?php echo json_encode(array_map(function($sg) {
    return ['id' => $sg['id'], 'name' => $sg['sub_group_name'], 'group_id' => $sg['group_id'] ?? null];
}, $sub_groups)); ?>;

var _promoAllItems = <?php echo json_encode(array_map(function($i) {
    return ['id' => $i['id'], 'label' => $i['sku_name'] . ' (' . $i['sku_code'] . ')'];
}, $items)); ?>;
var _promoAllGroups = <?php echo json_encode(array_map(function($g) {
    return ['id' => $g['id'], 'name' => $g['name']];
}, $modifier_groups)); ?>;
var _promoAllModFlat = <?php echo json_encode(array_map(function($m) {
    return ['id' => $m['id'], 'label' => $m['group_name'] . ' — ' . $m['modifier_name']];
}, $modifiers)); ?>;
var _promoModifierGroups = <?php echo json_encode(array_map(function($g) {
    return ['id' => $g['id'], 'name' => $g['name']];
}, $promo_modifier_groups)); ?>;
var _promoCompIdx = 0;

var _selectedProducts = {};
var _allProductIds    = <?php echo json_encode(array_values(array_column($items, 'id'))); ?>;
var _itemWarehouses   = <?php echo json_encode($item_warehouse_ids ?? new stdClass()); ?>;

function onSelectAllProducts(cb) {
    if (cb.checked) {
        for (var i = 0; i < _allProductIds.length; i++) {
            _selectedProducts[String(_allProductIds[i])] = true;
        }
    } else {
        _selectedProducts = {};
    }
    var boxes = document.querySelectorAll('#pos-products-table .product-select-cb');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = !!_selectedProducts[boxes[i].value];
    }
    updateBulkButton();
}

function onProductCheck(cb) {
    if (cb.checked) {
        _selectedProducts[cb.value] = true;
    } else {
        delete _selectedProducts[cb.value];
    }
    updateBulkButton();
    var total   = document.querySelectorAll('#pos-products-table .product-select-cb').length;
    var checked = document.querySelectorAll('#pos-products-table .product-select-cb:checked').length;
    var sa = document.getElementById('select-all-products');
    if (sa) {
        sa.checked       = total > 0 && checked === total;
        sa.indeterminate = checked > 0 && checked < total;
    }
}

function updateBulkButton() {
    var n   = Object.keys(_selectedProducts).length;
    var btn = document.getElementById('bulk-warehouses-btn');
    if (btn) { btn.disabled = n === 0; }
    var lbl = document.getElementById('bulk-btn-label');
    if (lbl) { lbl.textContent = n > 0 ? 'Bulk Warehouses (' + n + ')' : 'Bulk Warehouses'; }
}

function syncPageCheckboxes() {
    var boxes = document.querySelectorAll('#pos-products-table .product-select-cb');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = !!_selectedProducts[boxes[i].value];
    }
    var total   = boxes.length;
    var checked = document.querySelectorAll('#pos-products-table .product-select-cb:checked').length;
    var sa = document.getElementById('select-all-products');
    if (sa) {
        sa.checked       = total > 0 && checked === total;
        sa.indeterminate = checked > 0 && checked < total;
    }
}

$(function () {
    $('#pos-products-table').on('draw.dt', function () {
        syncPageCheckboxes();
        loadAllWarehouseCells();
    });

    $('#pos-products-table').DataTable({
        order: [[2, 'asc']],
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: [0, 1, 9, 10, 11] }]
    });

    loadAllModifierCounts();
    loadAllWarehouseCells();

    $('#product-group-id').on('change', function () {
        filterSubGroups('', '');
    });

    // Deep link from the Menu Layout page's "Edit" buttons (?edit=<item id>)
    var editId = new URLSearchParams(window.location.search).get('edit');
    if (editId) {
        editProduct(editId);
    }

    $('#bulk-mode').on('change', function () {
        $('#bulk-replace-hint').toggle($(this).val() === 'replace');
    });
});

function openBulkWarehousesModal() {
    var n = Object.keys(_selectedProducts).length;
    if (n === 0) return;
    $('#bulk-selected-info').text(n + ' product' + (n > 1 ? 's' : '') + ' selected.');
    $('.bulk-wh-cb').prop('checked', false);
    $('#bulk-mode').val('replace');
    $('#bulk-replace-hint').show();
    $('#bulk-warehouses-modal').modal('show');
}

function saveBulkWarehouses() {
    var itemIds = Object.keys(_selectedProducts);
    var warehouseIds = [];
    $('.bulk-wh-cb:checked').each(function () { warehouseIds.push($(this).val()); });
    var mode = $('#bulk-mode').val();

    if (mode !== 'replace' && warehouseIds.length === 0) {
        alert('Please select at least one warehouse for Add or Remove actions.');
        return;
    }

    var btn = $('#bulk-save-btn').prop('disabled', true);

    $.post(ADMIN_URL + 'pos/ajax_bulk_set_warehouses', {
        item_ids:      itemIds,
        warehouse_ids: warehouseIds,
        mode:          mode
    }, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            $('#bulk-warehouses-modal').modal('hide');
            var numWids = warehouseIds.map(Number);
            for (var i = 0; i < itemIds.length; i++) {
                var id = itemIds[i];
                if (mode === 'replace') {
                    _itemWarehouses[id] = numWids.slice();
                } else if (mode === 'add') {
                    var cur = (_itemWarehouses[id] || []).concat(numWids);
                    _itemWarehouses[id] = cur.filter(function (v, idx, arr) { return arr.indexOf(v) === idx; });
                } else if (mode === 'remove') {
                    _itemWarehouses[id] = (_itemWarehouses[id] || []).filter(function (v) { return numWids.indexOf(v) === -1; });
                }
                loadWarehouseCell(id);
            }
            _selectedProducts = {};
            var boxes = document.querySelectorAll('.product-select-cb');
            for (var i = 0; i < boxes.length; i++) { boxes[i].checked = false; }
            var sa = document.getElementById('select-all-products');
            if (sa) { sa.checked = false; sa.indeterminate = false; }
            updateBulkButton();
        } else {
            alert(resp.message || 'Failed to update warehouse availability.');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

// ============================================================
// Product CRUD
// ============================================================

var _warehouseMap = <?php echo json_encode(array_column($warehouses, 'warehouse_name', 'warehouse_id')); ?>;

function clearWarehousePrices() {
    $('.warehouse-price-input').val('').attr('placeholder', 'Default');
}

function resetProductImage() {
    $('#product-image-file').val('');
    $('#product-image-preview').attr('src', '').hide();
    $('#product-image-remove-btn').hide();
}

// ── Promo / Bundle helpers ────────────────────────────────────────────────

function onPromoToggle() {
    var on = $('#product-promo-enabled').is(':checked');
    $('#promo-fields').toggle(on);
    if (on) onPromoTypeChange();
}

function onPromoTypeChange() {
    var isBundle = $('#product-promo-type').val() === 'bundle';
    $('#promo-comp-section').toggle(!isBundle);
    $('#bundle-groups-section').toggle(isBundle);
}

// ── Flat components (promo type) ─────────────────────────────────────────

function _promoBuildUnifiedSelect(selType, selId) {
    var cur = selType && selId ? (selType === 'modifier_group' ? 'group:' : 'product:') + selId : '';
    var opts = '<option value="">— Select component —</option><optgroup label="Products">';
    _promoAllItems.forEach(function(it) {
        var v = 'product:' + it.id;
        opts += '<option value="' + v + '"' + (v === cur ? ' selected' : '') + '>' + it.label + '</option>';
    });
    opts += '</optgroup><optgroup label="Modifier Groups">';
    _promoAllGroups.forEach(function(g) {
        var v = 'group:' + g.id;
        opts += '<option value="' + v + '"' + (v === cur ? ' selected' : '') + '>' + g.name + '</option>';
    });
    opts += '</optgroup>';
    return opts;
}

function promoAddComponent(c) {
    c = c || {};
    var row = document.createElement('div');
    row.className = 'promo-comp-row';
    row.innerHTML =
        '<div style="display:flex;gap:4px;align-items:center;">'
        + '<select class="form-control pc-selection" style="flex:3;">'
        + _promoBuildUnifiedSelect(c.component_type || '', c.component_id || '')
        + '</select>'
        + '<input type="number" class="form-control pc-qty" style="width:56px;flex-shrink:0;" min="0.01" step="0.01" value="' + (c.quantity || 1) + '" title="Qty">'
        + '<span class="remove-comp" onclick="this.closest(\'.promo-comp-row\').remove();promoUpdateMsg()" title="Remove"><i class="fa fa-times"></i></span>'
        + '</div>';
    document.getElementById('promo-components-list').appendChild(row);
    promoUpdateMsg();
}

function promoUpdateMsg() {
    var has = document.querySelectorAll('.promo-comp-row').length > 0;
    document.getElementById('promo-no-components').style.display = has ? 'none' : '';
}

function promoCollectComponents() {
    var out = [];
    document.querySelectorAll('.promo-comp-row').forEach(function(row) {
        var val = (row.querySelector('.pc-selection') || {}).value || '';
        if (!val) return;
        var sep  = val.indexOf(':');
        var kind = val.substring(0, sep);
        var id   = parseInt(val.substring(sep + 1));
        out.push({
            component_type: kind === 'group' ? 'modifier_group' : 'product',
            component_id:   id || null,
            quantity:       (row.querySelector('.pc-qty') || {}).value || 1,
        });
    });
    return out;
}

// ── Bundle groups builder ─────────────────────────────────────────────────

function bundleUpdateMsg() {
    var has = document.querySelectorAll('.bundle-group-card').length > 0;
    document.getElementById('bundle-no-groups').style.display = has ? 'none' : '';
}

function _bgItemOpts(selectedId) {
    var h = '<option value="">— Select product —</option>';
    _promoAllItems.forEach(function(it) {
        h += '<option value="' + it.id + '"' + (it.id == selectedId ? ' selected' : '') + '>' + it.label + '</option>';
    });
    return h;
}

function _bgModifierOpts(selectedId) {
    var h = '<option value="">— Select modifier item —</option>';
    _promoAllModFlat.forEach(function(m) {
        h += '<option value="' + m.id + '"' + (m.id == selectedId ? ' selected' : '') + '>' + m.label + '</option>';
    });
    return h;
}

function _bgPromoGroupOpts(selectedId) {
    var h = '<option value="">— Select Promo Modifier Group —</option>';
    _promoModifierGroups.forEach(function(g) {
        h += '<option value="' + g.id + '"' + (g.id == selectedId ? ' selected' : '') + '>' + g.name + '</option>';
    });
    return h;
}

function bundleAddChoiceGroup(g) {
    g = g || {};
    var card = document.createElement('div');
    card.className = 'bundle-group-card';
    card.dataset.gtype = 'product_choice';
    card.innerHTML =
        '<div class="bg-header">'
        + '<span class="label label-info" style="font-size:10px;white-space:nowrap;">CHOICE</span>'
        + '<input type="text" class="form-control input-sm bg-name" placeholder="Group name e.g. Choice of Shake" value="' + ((g.name || '').replace(/"/g, '&quot;')) + '">'
        + '<span onclick="this.closest(\'.bundle-group-card\').remove();bundleUpdateMsg();" style="cursor:pointer;color:#d9534f;padding:0 4px;flex-shrink:0;"><i class="fa fa-times"></i></span>'
        + '</div>'
        + '<div class="bg-options-list"></div>'
        + '<button type="button" class="btn btn-link btn-xs" style="padding:2px 0;" onclick="bundleAddOption(this.previousElementSibling, \'item\')"><i class="fa fa-plus-circle"></i> Add product option</button>';
    document.getElementById('bundle-groups-list').appendChild(card);
    (g.options || []).forEach(function(opt) { bundleAddOption(card.querySelector('.bg-options-list'), 'item', opt.option_id); });
    bundleUpdateMsg();
}

function bundleAddModifierGroup(g) {
    g = g || {};
    var srcType = g.source_type || 'custom';
    var card = document.createElement('div');
    card.className = 'bundle-group-card';
    card.dataset.gtype = 'modifier_choice';
    card.dataset.stype = srcType;
    card.innerHTML =
        '<div class="bg-header">'
        + '<span class="label label-success" style="font-size:10px;white-space:nowrap;">MODIFIER</span>'
        + '<input type="text" class="form-control input-sm bg-name" placeholder="Group name e.g. Add-ons" value="' + ((g.name || '').replace(/"/g, '&quot;')) + '">'
        + '<select class="form-control input-sm bg-src" onchange="bundleToggleGroupSource(this)">'
        + '<option value="custom"' + (srcType === 'custom' ? ' selected' : '') + '>Custom options</option>'
        + '<option value="modifier_group_ref"' + (srcType === 'modifier_group_ref' ? ' selected' : '') + '>Promo Modifier Group</option>'
        + '</select>'
        + '<span onclick="this.closest(\'.bundle-group-card\').remove();bundleUpdateMsg();" style="cursor:pointer;color:#d9534f;padding:0 4px;flex-shrink:0;"><i class="fa fa-times"></i></span>'
        + '</div>'
        + '<div class="bg-ref-wrap" style="' + (srcType === 'modifier_group_ref' ? '' : 'display:none;') + 'margin-bottom:4px;">'
        + '<select class="form-control input-sm bg-ref-group">' + _bgPromoGroupOpts(g.modifier_group_id || '') + '</select>'
        + '</div>'
        + '<div class="bg-custom-wrap" style="' + (srcType === 'custom' ? '' : 'display:none;') + '">'
        + '<div class="bg-options-list"></div>'
        + '<button type="button" class="btn btn-link btn-xs" style="padding:2px 0;" onclick="bundleAddOption(this.previousElementSibling, \'modifier\')"><i class="fa fa-plus-circle"></i> Add modifier option</button>'
        + '</div>';
    document.getElementById('bundle-groups-list').appendChild(card);
    if (srcType === 'custom') {
        (g.options || []).forEach(function(opt) { bundleAddOption(card.querySelector('.bg-options-list'), 'modifier', opt.option_id); });
    }
    bundleUpdateMsg();
}

function bundleToggleGroupSource(sel) {
    var card = sel.closest('.bundle-group-card');
    var isRef = sel.value === 'modifier_group_ref';
    card.dataset.stype = sel.value;
    card.querySelector('.bg-ref-wrap').style.display  = isRef ? '' : 'none';
    card.querySelector('.bg-custom-wrap').style.display = isRef ? 'none' : '';
}

function bundleAddOption(listEl, optType, selectedId) {
    var row = document.createElement('div');
    row.className = 'bg-opt-row';
    var optHtml = optType === 'modifier' ? _bgModifierOpts(selectedId || '') : _bgItemOpts(selectedId || '');
    row.innerHTML = '<select class="form-control input-sm bg-opt-sel">' + optHtml + '</select>'
        + '<span class="rm" onclick="this.closest(\'.bg-opt-row\').remove()"><i class="fa fa-times"></i></span>';
    listEl.appendChild(row);
}

function bundleCollectGroups() {
    var groups = [];
    document.querySelectorAll('.bundle-group-card').forEach(function(card) {
        var gtype  = card.dataset.gtype;
        var stype  = card.dataset.stype || 'custom';
        var g = {
            name:              card.querySelector('.bg-name').value,
            group_type:        gtype,
            source_type:       stype,
            modifier_group_id: null,
            options:           [],
        };
        if (stype === 'modifier_group_ref') {
            g.modifier_group_id = card.querySelector('.bg-ref-group').value || null;
        } else {
            card.querySelectorAll('.bg-opt-row').forEach(function(r) {
                var v = r.querySelector('.bg-opt-sel').value;
                if (v) g.options.push({ option_id: v });
            });
        }
        groups.push(g);
    });
    return groups;
}

// ── Discount value gate ───────────────────────────────────────────────────

function _promoSyncDiscountValue() {
    var hasType = !!$('#product-promo-discount-type').val();
    $('#product-promo-discount-value').prop('disabled', !hasType);
    if (!hasType) $('#product-promo-discount-value').val('0');
}

// ── Reset / populate ──────────────────────────────────────────────────────

function promoResetFields() {
    $('#product-promo-enabled').prop('checked', false);
    $('#promo-fields').hide();
    $('#product-promo-type').val('promo');
    $('#product-promo-discount-type').val('');
    _promoSyncDiscountValue();
    document.getElementById('promo-components-list').innerHTML = '';
    document.getElementById('bundle-groups-list').innerHTML = '';
    promoUpdateMsg();
    bundleUpdateMsg();
    onPromoTypeChange();
}

function promoPopulate(promo) {
    if (!promo) { promoResetFields(); return; }
    $('#product-promo-enabled').prop('checked', true);
    $('#promo-fields').show();
    $('#product-promo-type').val(promo.type || 'promo');
    $('#product-promo-discount-type').val(promo.discount_type || '');
    $('#product-promo-discount-value').val(promo.discount_value || '0');
    _promoSyncDiscountValue();
    document.getElementById('promo-components-list').innerHTML = '';
    document.getElementById('bundle-groups-list').innerHTML = '';
    (promo.components || []).forEach(function(c) { promoAddComponent(c); });
    (promo.bundle_groups || []).forEach(function(g) {
        if (g.group_type === 'modifier_choice') bundleAddModifierGroup(g);
        else bundleAddChoiceGroup(g);
    });
    promoUpdateMsg();
    bundleUpdateMsg();
    onPromoTypeChange();
}

$(document).on('change', '#product-promo-discount-type', _promoSyncDiscountValue);

// ── Product modal open / edit / save ─────────────────────────────────────

function openProductModal(id) {
    $('#product-edit-id').val('');
    $('#product-sku-name').val('');
    $('#product-sku-code').val('');
    $('#product-description').val('');
    $('#product-rate').val('');
    $('#product-group-id').selectpicker('val', '');
    filterSubGroups('', '');
    $('#product-active').val('1');
    $('#product-fd-available').prop('checked', true);
    $('#product-fd-price').val('');
    $('#product-warehouse-ids').selectpicker('val', []);
    clearWarehousePrices();
    resetProductImage();
    promoResetFields();
    $('#product-image-hint').text('Save the product first, then upload an image.').show();
    $('#product-image-file').prop('disabled', true);
    $('#product-modal-title').text('Add Product');
    $('#product-save-label').text('Add Product');
    $('#product-modal').modal('show');
    $('#product-sku-name').focus();
}

function editProduct(id) {
    $.getJSON(ADMIN_URL + 'pos/ajax_get_pos_product/' + id, function (resp) {
        if (!resp.success) { alert(resp.message || 'Failed to load product'); return; }
        var p = resp.data;
        $('#product-edit-id').val(p.id);
        $('#product-sku-name').val(p.sku_name);
        $('#product-sku-code').val(p.sku_code);
        $('#product-description').val(p.description || '');
        $('#product-rate').val(parseFloat(p.rate).toFixed(2));
        $('#product-active').val(p.active == 1 ? '1' : '0');
        $('#product-fd-available').prop('checked', p.fd_available == 1);
        $('#product-fd-price').val(p.fd_price !== null && p.fd_price !== '' ? parseFloat(p.fd_price).toFixed(2) : '');
        $('#product-modal-title').text('Edit Product');
        $('#product-save-label').text('Save Changes');

        resetProductImage();
        $('#product-image-file').prop('disabled', false);
        $('#product-image-hint').text('JPG, PNG, or WEBP.').show();
        if (p.image) {
            $('#product-image-preview').attr('src', BASE_URL + 'uploads/pos_items/' + p.id + '/' + p.image + '?t=' + Date.now()).show();
            $('#product-image-remove-btn').show();
        }

        $('#product-group-id').selectpicker('val', p.group_id || '');
        filterSubGroups('', p.sub_group || '');

        var wids = (p.warehouse_ids || []).map(String);
        $('#product-warehouse-ids').selectpicker('val', wids);

        // Populate warehouse prices
        clearWarehousePrices();
        var defaultRate = parseFloat(p.rate).toFixed(2);
        $('.warehouse-price-input').attr('placeholder', 'Default (' + defaultRate + ')');
        var priceMap = {};
        (p.warehouse_prices || []).forEach(function (row) {
            priceMap[row.warehouse_id] = row.price;
        });
        $('.warehouse-price-input').each(function () {
            var wid = $(this).data('warehouse-id');
            if (priceMap[wid] !== undefined) {
                $(this).val(parseFloat(priceMap[wid]).toFixed(2));
            }
        });

        promoPopulate(p.crm_promo || null);

        $('#product-modal').modal('show');
        $('#product-sku-name').focus();
    });
}

function filterSubGroups(groupId, selectedSubGroup) {
    var $sel = $('#product-sub-group');
    $sel.empty().append('<option value="">— None —</option>');

    $.each(_allSubGroups, function (i, sg) {
        if (!groupId || sg.group_id == groupId || sg.group_id === null || sg.group_id === '') {
            $sel.append('<option value="' + sg.id + '">' + $('<span>').text(sg.name).html() + '</option>');
        }
    });

    $sel.selectpicker('refresh');
    if (selectedSubGroup) {
        $sel.selectpicker('val', String(selectedSubGroup));
    }
}

function saveProduct() {
    var id          = $('#product-edit-id').val();
    var skuName     = $.trim($('#product-sku-name').val());
    var description = $.trim($('#product-description').val());
    var skuCode     = $.trim($('#product-sku-code').val());
    var rate        = $('#product-rate').val();
    var groupId     = $('#product-group-id').val();
    var subGroup    = $('#product-sub-group').val();
    var active      = $('#product-active').val();
    var fdAvailable = $('#product-fd-available').is(':checked') ? 1 : 0;
    var fdPrice     = $.trim($('#product-fd-price').val());

    if (!skuName) { alert('Product name is required'); $('#product-sku-name').focus(); return; }
    if (rate === '' || isNaN(parseFloat(rate))) { alert('Price is required'); $('#product-rate').focus(); return; }

    var warehouseIds    = $('#product-warehouse-ids').val() || [];
    var warehousePrices = {};
    $('.warehouse-price-input').each(function () {
        var val = $.trim($(this).val());
        if (val !== '' && !isNaN(parseFloat(val))) {
            warehousePrices[$(this).data('warehouse-id')] = parseFloat(val).toFixed(2);
        }
    });

    var btn = $('#product-save-btn').prop('disabled', true);

    var promoEnabled = $('#product-promo-enabled').is(':checked') ? 1 : 0;

    $.post(ADMIN_URL + 'pos/ajax_save_pos_product', {
        id:                      id,
        sku_name:                skuName,
        description:             description,
        sku_code:                skuCode,
        rate:                    rate,
        group_id:                groupId,
        sub_group:               subGroup,
        active:                  active,
        fd_available:            fdAvailable,
        fd_price:                fdPrice,
        warehouse_ids:           warehouseIds,
        warehouse_prices:        warehousePrices,
        promo_enabled:           promoEnabled,
        promo_type:              $('#product-promo-type').val(),
        promo_discount_type:     $('#product-promo-discount-type').val(),
        promo_discount_value:    $('#product-promo-discount-value').val() || 0,
        promo_components:        promoCollectComponents(),
        bundle_groups:           bundleCollectGroups(),
    }, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            $('#product-modal').modal('hide');
            location.reload(); // reload to reflect promo badge changes in table
        } else {
            alert(resp.message || 'Failed to save product');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

function uploadProductImage() {
    var id   = $('#product-edit-id').val();
    var file = $('#product-image-file')[0].files[0];
    if (!id || !file) return;

    var formData = new FormData();
    formData.append('item_id', id);
    formData.append('image', file);

    $('#product-image-hint').text('Uploading...');
    $.ajax({
        url: ADMIN_URL + 'pos/ajax_upload_item_image',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
    }).done(function (resp) {
        if (resp.success) {
            $('#product-image-preview').attr('src', resp.url + '?t=' + Date.now()).show();
            $('#product-image-remove-btn').show();
            $('#product-image-hint').text('JPG, PNG, or WEBP.');
        } else {
            alert(resp.message || 'Failed to upload image');
            $('#product-image-hint').text('JPG, PNG, or WEBP.');
        }
    }).fail(function () {
        alert('Upload failed. Please try again.');
        $('#product-image-hint').text('JPG, PNG, or WEBP.');
    });
}

function removeProductImage() {
    var id = $('#product-edit-id').val();
    if (!id || !confirm('Remove this product image?')) return;

    $.post(ADMIN_URL + 'pos/ajax_remove_item_image', { item_id: id }, function (resp) {
        if (resp.success) {
            resetProductImage();
        } else {
            alert(resp.message || 'Failed to remove image');
        }
    }, 'json');
}

function deleteProduct(id, name) {
    if (!confirm('Delete product "' + name + '"?\n\nThis cannot be undone.')) return;

    $.post(ADMIN_URL + 'pos/ajax_delete_pos_product', { id: id }, function (resp) {
        if (resp.success) {
            $('#product-row-' + id).fadeOut(300, function () { $(this).remove(); });
        } else {
            alert(resp.message || 'Failed to delete product.');
        }
    }, 'json');
}

// ============================================================
// Modifier counts
// ============================================================

function loadAllWarehouseCells() {
    for (var id in _itemWarehouses) {
        if (_itemWarehouses.hasOwnProperty(id)) {
            loadWarehouseCell(id);
        }
    }
}

function loadWarehouseCell(itemId) {
    var wids = _itemWarehouses[itemId] || [];
    var el = $('#warehouse-cell-' + itemId);
    if (!el.length) return;
    if (!wids.length) {
        el.html('<span class="text-muted small"><i class="fa fa-globe"></i> All</span>');
    } else {
        var names = wids.map(function (id) { return _warehouseMap[id] || ('W#' + id); });
        el.html('<span class="small">' + names.join(', ') + '</span>');
    }
}

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

// ============================================================
// Modifiers modal
// ============================================================

function openModifiersModal(itemId, itemName) {
    $('#modal-item-id').val(itemId);
    $('#modal-item-name').text(itemName);
    indivResetForm();
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
        if (resp.success) {
            _indivRows = resp.data;
            renderIndividualModifiers(resp.data);
        }
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
        var typeBadge = row.selection_type === 'single'
            ? '<span class="label label-default">Single</span>'
            : '<span class="label label-primary">Multiple</span>';

        var optionNames = [];
        if (row.options && row.options.length) {
            $.each(row.options, function(j, opt) {
                var p = parseFloat(opt.price_adjustment);
                var pStr = p !== 0 ? ' (' + (p > 0 ? '+' : '') + p.toFixed(2) + ')' : '';
                optionNames.push($('<span>').text(opt.name).html() + '<small class="text-muted">' + pStr + '</small>');
            });
        }
        var optionsHtml = optionNames.length ? optionNames.join(', ') : '<em class="text-muted">No options</em>';

        tbody.append(
            '<tr id="individual-row-' + row.id + '">' +
            '<td><strong>' + $('<span>').text(row.name).html() + '</strong></td>' +
            '<td>' + typeBadge + '</td>' +
            '<td>' + optionsHtml + '</td>' +
            '<td class="text-center" style="white-space:nowrap;">' +
            '<button class="btn btn-xs btn-default" onclick="indivEditModifier(' + row.id + ')"><i class="fa fa-pencil"></i></button> ' +
            '<button class="btn btn-xs btn-danger" onclick="deleteIndividualModifier(' + row.id + ')"><i class="fa fa-times"></i></button>' +
            '</td>' +
            '</tr>'
        );
    });
}

var _indivRows = [];

function indivAddOption(name, price) {
    var row = $(
        '<div class="indiv-opt-row row" style="margin-bottom:4px;">' +
        '<div class="col-md-7"><input type="text" class="form-control indiv-opt-name" placeholder="Option name" value="' + (name ? $('<span>').text(name).html() : '') + '"></div>' +
        '<div class="col-md-4"><div class="input-group"><span class="input-group-addon">RM</span>' +
        '<input type="number" class="form-control indiv-opt-price" step="0.01" placeholder="0.00" value="' + (price !== undefined ? price : '0.00') + '"></div></div>' +
        '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest(\'.indiv-opt-row\').remove()">' +
        '<i class="fa fa-trash"></i></button></div>' +
        '</div>'
    );
    $('#indiv-options-list').append(row);
    row.find('.indiv-opt-name').focus();
}

function indivResetForm() {
    $('#indiv-edit-id').val('');
    $('#indiv-name').val('');
    $('#indiv-selection-type').val('single');
    $('#indiv-sort').val('0');
    $('#indiv-options-list').empty();
    $('#indiv-save-label').text('Add Modifier');
}

function indivEditModifier(id) {
    var row = null;
    $.each(_indivRows, function(i, r) { if (r.id == id) { row = r; return false; } });
    if (!row) return;

    $('#indiv-edit-id').val(row.id);
    $('#indiv-name').val(row.name);
    $('#indiv-selection-type').val(row.selection_type || 'single');
    $('#indiv-sort').val(row.sort_order || 0);
    $('#indiv-save-label').text('Update Modifier');

    $('#indiv-options-list').empty();
    if (row.options && row.options.length) {
        $.each(row.options, function(i, opt) {
            indivAddOption(opt.name, parseFloat(opt.price_adjustment).toFixed(2));
        });
    }
    $('#indiv-name').focus();
}

function saveIndividualModifier() {
    var itemId = $('#modal-item-id').val();
    var name   = $.trim($('#indiv-name').val());
    var type   = $('#indiv-selection-type').val();
    var sort   = $('#indiv-sort').val();
    var editId = $('#indiv-edit-id').val();

    if (!name) { alert('Modifier name is required'); $('#indiv-name').focus(); return; }

    var options = [];
    $('#indiv-options-list .indiv-opt-row').each(function() {
        var optName = $.trim($(this).find('.indiv-opt-name').val());
        if (optName) {
            options.push({ name: optName, price_adjustment: $(this).find('.indiv-opt-price').val() || '0' });
        }
    });

    $.post(ADMIN_URL + 'pos/ajax_save_item_modifier', {
        item_id:        itemId,
        id:             editId,
        name:           name,
        selection_type: type,
        sort_order:     sort,
        options:        options
    }, function(resp) {
        if (resp.success) {
            _indivRows = resp.data;
            renderIndividualModifiers(resp.data);
            loadModifierCount(itemId);
            indivResetForm();
        } else {
            alert(resp.message || 'Failed to save.');
        }
    }, 'json');
}

function deleteIndividualModifier(id) {
    if (!confirm('Delete this modifier?')) return;
    var itemId = $('#modal-item-id').val();
    $.post(ADMIN_URL + 'pos/ajax_delete_item_modifier', {
        item_id: itemId,
        id:      id
    }, function(resp) {
        if (resp.success) {
            _indivRows = resp.data;
            renderIndividualModifiers(resp.data);
            loadModifierCount(itemId);
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
