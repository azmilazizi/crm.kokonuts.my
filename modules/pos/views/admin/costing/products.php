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
                                <p class="text-muted small">Purchase-linked ingredient cost list. Values are derived from the latest purchase order for each item.</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-default" onclick="exportTable()">
                                    <i class="fa fa-download"></i> Export This Table
                                </button>
                                &nbsp;
                                <button class="btn btn-success" onclick="saveVisibleRows()">
                                    <i class="fa fa-save"></i> Save
                                </button>
                            </div>
                        </div>
                        <hr />
                        <?php if (isset($active_tab, $_tabs)) { ?>
                        <div class="mbot15">
                            <ul class="nav nav-tabs" role="tablist" style="margin-bottom:16px;">
                                <?php foreach ($_tabs as $key => $t) { ?>
                                    <li role="presentation" class="<?php echo $active_tab === $key ? 'active' : ''; ?>">
                                        <a href="<?php echo htmlspecialchars($t['href']); ?>"><?php echo htmlspecialchars($t['label']); ?></a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </div>
                        <?php } ?>

                        <div class="row mbot15">
                            <?php if (empty($hide_category_filter)) { ?>
                            <div class="col-md-4">
                                <select id="filter-category" class="form-control" onchange="applyFilters()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($item_groups as $ig) { ?>
                                        <option value="<?php echo (int)$ig['id']; ?>"><?php echo htmlspecialchars($ig['name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="filter-search" class="form-control" placeholder="Search SKU or Name..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-4 text-right text-muted" style="padding-top:6px;">
                                <span id="row-count"><?php echo count($items); ?> items</span>
                            </div>
                            <?php } else { ?>
                            <div class="col-md-8">
                                <input type="text" id="filter-search" class="form-control" placeholder="Search SKU or Name..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-4 text-right text-muted" style="padding-top:6px;">
                                <span id="row-count"><?php echo count($items); ?> items</span>
                            </div>
                            <?php } ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="costing-table">
                                <thead>
                                    <tr>
                                        <th>SKU Code</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th style="width:140px;">Purchase Price</th>
                                        <th style="width:110px;">Batch Size</th>
                                        <th style="width:120px;">Units/Batch Item</th>
                                        <th style="width:120px;">Unit</th>
                                        <th style="width:120px;">Cost/Unit</th>
                                        <th style="width:220px;">Purchase Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item) {
                                        $id = (int)$item['id'];
                                        $category = isset($force_category_label) ? $force_category_label : ($item['category_name'] ?: ($item['sub_category_name'] ?: '-'));
                                        $purchasePrice = (float)($item['purchase_price_display'] ?? 0);
                                        $costPerUnit = (float)($item['cost_per_unit_fallback'] ?? 0);
                                        $purchaseOrderUrl = $item['purchase_order_url'] ?? '';
                                        $purchaseOrderLabel = trim((string)($item['purchase_order_label'] ?? ''));
                                    ?>
                                    <tr class="costing-row"
                                        data-group="<?php echo (int)($item['group_id'] ?? 0); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['sku_code'] ?? '') . ' ' . ($item['sku_name'] ?? ''))); ?>">
                                        <td><?php echo htmlspecialchars($item['sku_code'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td class="text-right">
                                            <input type="number" step="0.0001" class="form-control input-sm purchase-price" value="<?php echo number_format($purchasePrice, 4, '.', ''); ?>" data-itemid="<?php echo $id; ?>" readonly>
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" class="form-control input-sm batch-size" value="<?php echo htmlspecialchars($item['batch_size'] ?? '1'); ?>" data-itemid="<?php echo $id; ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" class="form-control input-sm units-per-batch" value="<?php echo htmlspecialchars($item['units_per_batch'] ?? '1'); ?>" data-itemid="<?php echo $id; ?>">
                                        </td>
                                        <td>
                                            <input type="text" class="form-control input-sm unit-uom" value="<?php echo htmlspecialchars($item['item_unit_name'] ?? ''); ?>" data-itemid="<?php echo $id; ?>" readonly>
                                        </td>
                                        <td class="text-right">
                                            <input type="text" class="form-control input-sm cost-per-unit" value="<?php echo number_format($costPerUnit, 4, '.', ''); ?>" data-itemid="<?php echo $id; ?>" readonly>
                                        </td>
                                        <td class="small">
                                            <?php if ($purchaseOrderUrl && $purchaseOrderLabel !== '') { ?>
                                                <a href="<?php echo htmlspecialchars($purchaseOrderUrl); ?>" target="_blank"><?php echo htmlspecialchars($purchaseOrderLabel); ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">-</span>
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
</div>

<?php init_tail(); ?>
<script>
var saveUrl = '<?php echo admin_url('pos/ajax_save_item_cost'); ?>';

function recomputeRowCost(row) {
    var purchasePrice = parseFloat(row.find('.purchase-price').val() || 0);
    var unitsPerBatch = parseFloat(row.find('.units-per-batch').val() || 0);
    var cost = unitsPerBatch > 0 ? (purchasePrice / unitsPerBatch) : purchasePrice;
    row.find('.cost-per-unit').val(cost.toFixed(4));
}

$(document).on('input change', '.costing-row .batch-size, .costing-row .units-per-batch', function () {
    recomputeRowCost($(this).closest('.costing-row'));
});

function saveRowCost(itemId, row, done) {
    var data = {
        item_id: itemId,
        batch_size: row.find('.batch-size[data-itemid=' + itemId + ']').val(),
        units_per_batch: row.find('.units-per-batch[data-itemid=' + itemId + ']').val()
    };
    $.post(saveUrl, data, function (res) {
        if (res && res.success) {
            row.find('.cost-per-unit[data-itemid=' + itemId + ']').val(parseFloat(res.cost_per_unit || 0).toFixed(4));
        }
        if (typeof done === 'function') done(res);
    }, 'json').fail(function () {
        if (typeof done === 'function') done({ success: false });
    });
}

function saveVisibleRows() {
    var rows = $('.costing-row:visible');
    if (!rows.length) return;
    var pending = rows.length;
    var failed = 0;
    rows.each(function () {
        var row = $(this);
        var itemId = parseInt(row.find('.batch-size').data('itemid'), 10);
        saveRowCost(itemId, row, function (res) {
            if (!(res && res.success)) failed++;
            pending--;
            if (pending === 0) {
                if (failed > 0) {
                    alert_float('warning', failed + ' row(s) failed to save');
                } else {
                    alert_float('success', 'Saved');
                }
            }
        });
    });
}

function applyFilters() {
    var cat = parseInt($('#filter-category').val() || 0, 10);
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.costing-row').each(function () {
        var $r = $(this);
        var ok = true;
        if (cat > 0 && parseInt($r.data('group'), 10) !== cat) ok = false;
        if (q && ('' + ($r.data('search') || '')).indexOf(q) < 0) ok = false;
        $r.toggle(ok);
        if (ok) visible++;
    });
    $('#row-count').text(visible + ' items');
}

function exportTable() {
    var table = document.getElementById('costing-table');
    var csv = [];
    for (var r = 0; r < table.rows.length; r++) {
        var row = [];
        for (var c = 0; c < table.rows[r].cells.length; c++) {
            var t = (table.rows[r].cells[c].innerText || table.rows[r].cells[c].textContent || '').replace(/"/g, '""').trim();
            row.push('"' + t + '"');
        }
        csv.push(row.join(','));
    }
    var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'ingredients_cost_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
