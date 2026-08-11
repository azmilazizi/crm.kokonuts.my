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
                                <p class="text-muted small">Manage product purchase costs, batch sizes, and unit costs. Margins are calculated automatically.</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-default" onclick="exportTable()">
                                    <i class="fa fa-download"></i> Export This Table
                                </button>
                                &nbsp;
                                <button class="btn btn-info" data-toggle="modal" data-target="#recalcModal">
                                    <i class="fa fa-calculator"></i> Recalculate All Costs
                                </button>
                            </div>
                        </div>
                        <hr />

                        <div class="row mbot15">
                            <div class="col-md-4">
                                <select id="filter-category" class="form-control" onchange="applyFilters()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($sub_groups as $sg) { ?>
                                        <option value="<?php echo (int)$sg['id']; ?>"><?php echo htmlspecialchars($sg['sub_group_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="text" id="filter-search" class="form-control" placeholder="Search SKU or Name..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-4 text-right text-muted" style="padding-top:6px;">
                                <span id="row-count"><?php echo count($items); ?> items</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="costing-table">
                                <thead>
                                    <tr>
                                        <th>Item ID</th>
                                        <th>SKU Code</th>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th style="width:120px;">Purchase Price<br /><small class="text-muted">(Per Batch)</small></th>
                                        <th style="width:90px;">Batch Size</th>
                                        <th style="width:90px;">Units/Batch</th>
                                        <th>Unit UOM</th>
                                        <th style="width:110px;">Cost/Unit</th>
                                        <th style="width:110px;">Sell Price</th>
                                        <th style="width:90px;">Margin %</th>
                                        <th>Last Update</th>
                                        <th style="width:80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item) {
                                        $id = (int)$item['id'];
                                        $type = $item['item_type'] ?? 'finished_product';
                                        $typeBadge = 'label-default';
                                        if ($type === 'finished_product') $typeBadge = 'label-success';
                                        elseif ($type === 'combo') $typeBadge = 'label-primary';
                                        elseif ($type === 'mixed_ingredient') $typeBadge = 'label-warning';
                                        $sell = (float)($item['rate'] ?? 0);
                                        $cpu  = (float)($item['cached_cost'] ?? 0);
                                        if ($cpu > 0 && $sell > 0) {
                                            $margin = (($sell - $cpu) / $sell) * 100;
                                        } else {
                                            $margin = 0;
                                        }
                                        $sgMap = [];
                                        foreach ($sub_groups as $sg) { $sgMap[(int)$sg['id']] = $sg['sub_group_name']; }
                                        $catName = $sgMap[(int)($item['sub_group'] ?? 0)] ?? '-';
                                    ?>
                                    <tr class="costing-row"
                                        data-subgroup="<?php echo (int)($item['sub_group'] ?? 0); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['sku_code'] ?? '') . ' ' . ($item['sku_name'] ?? ''))); ?>">
                                        <td><?php echo $id; ?></td>
                                        <td><?php echo htmlspecialchars($item['sku_code'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name'] ?? ''); ?></strong></td>
                                        <td><span class="label <?php echo $typeBadge; ?>"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?></span></td>
                                        <td><?php echo htmlspecialchars($catName); ?></td>
                                        <td><input type="number" step="0.0001" class="form-control input-sm purchase-price" value="<?php echo htmlspecialchars($item['purchase_price'] ?? '0'); ?>" data-itemid="<?php echo $id; ?>"></td>
                                        <td><input type="number" step="0.0001" class="form-control input-sm batch-size" value="<?php echo htmlspecialchars($item['batch_size'] ?? '1'); ?>" data-itemid="<?php echo $id; ?>"></td>
                                        <td><input type="number" step="0.0001" class="form-control input-sm units-per-batch" value="<?php echo htmlspecialchars($item['units_per_batch'] ?? '1'); ?>" data-itemid="<?php echo $id; ?>"></td>
                                        <td>
                                            <select class="form-control input-sm unit-uom" data-itemid="<?php echo $id; ?>">
                                                <option value="">-</option>
                                                <?php foreach ($uoms as $u) {
                                                    $sel = ($item['unit_uom'] ?? '') === $u['uom_code'] ? ' selected' : '';
                                                    echo '<option value="'.htmlspecialchars($u['uom_code']).'"'.$sel.'>'.htmlspecialchars($u['uom_name']).'</option>';
                                                } ?>
                                            </select>
                                        </td>
                                        <td class="text-right cost-per-unit-cell" data-itemid="<?php echo $id; ?>">
                                            <strong><?php echo number_format($cpu, 4); ?></strong>
                                        </td>
                                        <td class="text-right"><?php echo number_format($sell, 2); ?></td>
                                        <td class="text-right margin-cell" data-sell="<?php echo $sell; ?>" data-cost="<?php echo $cpu; ?>">
                                            <?php echo number_format($margin, 1); ?>%
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo !empty($item['last_cost_update']) ? htmlspecialchars($item['last_cost_update']) : '-'; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-success btn-sm" onclick="saveRowCost(<?php echo $id; ?>, this)">
                                                <i class="fa fa-save"></i> Save
                                            </button>
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

<div class="modal fade" id="recalcModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form onsubmit="return doRecalc(this)">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Recalculate All Costs</h4>
                </div>
                <div class="modal-body">
                    <p class="text-muted">This will re-compute unit costs for all products, combos, and mixed ingredients based on their recipes and latest purchase prices.</p>
                    <hr />
                    <div class="checkbox">
                        <label><input type="checkbox" id="create_snapshot" name="create_snapshot" value="1"> Also create a cost snapshot</label>
                    </div>
                    <div id="snap-name-wrap" style="display:none;" class="mtop10">
                        <label>Snapshot Name</label>
                        <input type="text" class="form-control" name="snapshot_name" placeholder="e.g. End of Month Aug 2026">
                    </div>
                    <script>
                        document.getElementById('create_snapshot').addEventListener('change', function () {
                            document.getElementById('snap-name-wrap').style.display = this.checked ? 'block' : 'none';
                        });
                    </script>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fa fa-calculator"></i> Run Recalculation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var saveUrl = '<?php echo admin_url('pos/ajax_save_item_cost'); ?>';
var recalcUrl = '<?php echo admin_url('pos/ajax_recalc_costs'); ?>';

function saveRowCost(itemId, btn) {
    var row = $(btn).closest('tr');
    var data = {
        item_id: itemId,
        purchase_price: row.find('.purchase-price[data-itemid=' + itemId + ']').val(),
        batch_size: row.find('.batch-size[data-itemid=' + itemId + ']').val(),
        units_per_batch: row.find('.units-per-batch[data-itemid=' + itemId + ']').val(),
        unit_uom: row.find('.unit-uom[data-itemid=' + itemId + ']').val(),
    };
    var orig = $(btn).html();
    $(btn).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
    $.post(saveUrl, data, function (res) {
        $(btn).prop('disabled', false).html(orig);
        if (res && res.success) {
            var cpu = parseFloat(res.cost_per_unit || 0);
            var cell = row.find('.cost-per-unit-cell[data-itemid=' + itemId + ']');
            cell.find('strong').text(cpu.toFixed(4));
            var mcell = row.find('.margin-cell');
            var sell = parseFloat(mcell.data('sell') || 0);
            mcell.attr('data-cost', cpu);
            if (sell > 0 && cpu > 0) {
                var m = ((sell - cpu) / sell) * 100;
                mcell.text(m.toFixed(1) + '%');
            } else {
                mcell.text('0.0%');
            }
            cell.effect('highlight', {}, 800);
        } else {
            alert_float('danger', (res && res.message) || 'Save failed');
        }
    }, 'json').fail(function () {
        $(btn).prop('disabled', false).html(orig);
        alert_float('danger', 'Network error');
    });
}

function doRecalc(form) {
    var data = $(form).serialize();
    var btn = $(form).find('button[type=submit]');
    var orig = btn.html();
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Running...');
    $.post(recalcUrl, data, function (res) {
        btn.prop('disabled', false).html(orig);
        $('#recalcModal').modal('hide');
        if (res && res.success) {
            alert_float('success', 'Recalculation complete. Reloading...');
            setTimeout(function () { location.reload(); }, 900);
        } else {
            alert_float('danger', (res && res.error) || 'Recalculation failed');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false).html(orig);
        alert_float('danger', 'Network error');
    });
    return false;
}

function applyFilters() {
    var cat = parseInt($('#filter-category').val() || 0, 10);
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.costing-row').each(function () {
        var $r = $(this);
        var ok = true;
        if (cat > 0 && parseInt($r.data('subgroup'), 10) !== cat) ok = false;
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
        for (var c = 0; c < table.rows[r].cells.length - 1; c++) {
            var t = (table.rows[r].cells[c].innerText || table.rows[r].cells[c].textContent || '').replace(/"/g, '""').trim();
            row.push('"' + t + '"');
        }
        csv.push(row.join(','));
    }
    var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'product_costing_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
