<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<style>
.modal .bootstrap-select.open {
    position: relative;
    z-index: 3050;
}
.modal .bootstrap-select.open .dropdown-menu {
    z-index: 3050 !important;
}
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                                <p class="text-muted small">Break a purchased item down into derived items it yields (e.g. 1 Coconut Fruit &rarr; 110ml Coconut Juice + 50g Coconut Meat). Each derived item's cost is computed from the source's cost instead of its own purchase price, and stays selectable anywhere as a regular item.</p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-info" onclick="openYieldDialog()">
                                    <i class="fa fa-plus"></i> New Yield Breakdown
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
                            <div class="col-md-4">
                                <input type="text" id="filter-search" class="form-control" placeholder="Search SKU or Name..." onkeyup="applyYieldFilters()">
                            </div>
                            <div class="col-md-8 text-right text-muted" style="padding-top:6px;">
                                <span id="row-count"><?php echo count($yields); ?> items</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="yield-table">
                                <thead>
                                    <tr>
                                        <th>Source Item</th>
                                        <th>SKU</th>
                                        <th>Cost Per Unit (RM)</th>
                                        <th>Derived Items</th>
                                        <th style="width:160px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($yields as $row) { ?>
                                    <tr class="yield-summary-row" data-search="<?php echo htmlspecialchars(strtolower(($row['sku_code'] ?? '') . ' ' . ($row['sku_name'] ?? ''))); ?>">
                                        <td><strong><?php echo htmlspecialchars($row['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($row['sku_code'] ?? ''); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($row['source_cost_per_unit'] ?? 0), 4); ?></td>
                                        <td class="text-right"><?php echo (int)($row['outputs_count'] ?? 0); ?></td>
                                        <td>
                                            <button class="btn btn-default btn-sm" onclick="openYieldDialog(<?php echo (int)$row['source_item_id']; ?>)">
                                                <i class="fa fa-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="deleteYieldBreakdown(<?php echo (int)$row['source_item_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['sku_name'] ?? ''), ENT_QUOTES); ?>')">
                                                <i class="fa fa-trash"></i>
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

<div class="modal fade" id="yieldModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form onsubmit="return saveYieldBreakdown(this)">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title" id="yield-modal-title">Yield Breakdown</h4>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Source Item</label>
                            <select class="form-control selectpicker" data-live-search="true" data-size="8" id="yield-source-item" required></select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Source Cost / Unit</label>
                            <input type="text" class="form-control" id="yield-source-cost" readonly>
                        </div>
                    </div>

                    <hr />
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="no-margin-top"><strong>Derived Items</strong></h5>
                        </div>
                        <div class="col-md-6 text-right">
                            <button type="button" class="btn btn-success btn-sm" onclick="addYieldRow()">
                                <i class="fa fa-plus"></i> Add Derived Item
                            </button>
                        </div>
                    </div>
                    <p class="text-muted small">Reference Price is optional. Leave it blank and each derived item absorbs the full source cost independently. Fill it in (e.g. a supplier/market price per unit) on <em>any</em> row and the source's cost is instead split across all rows here by relative market value (qty &times; reference price), so they add up to exactly the source's cost instead of each one counting the full amount.</p>
                    <table class="table table-bordered mtop10">
                        <thead>
                            <tr>
                                <th>Derived Item</th>
                                <th style="width:130px;">Yield Qty (per 1 unit)</th>
                                <th style="width:130px;">Reference Price</th>
                                <th style="width:130px;">Cost Per Unit</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="yield-rows-body"></tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fa fa-save"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var getItemYieldsUrl = '<?php echo admin_url('pos/ajax_get_item_yields'); ?>';
var saveItemYieldsUrl = '<?php echo admin_url('pos/ajax_save_item_yields'); ?>';
var yieldIngredientItems = <?php echo json_encode(array_values($ingredient_items), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function yieldItemCostMap() {
    var map = {};
    yieldIngredientItems.forEach(function (item) {
        map[item.id] = parseFloat(item.cost_per_unit || 0);
    });
    return map;
}

function yieldSourceOptions(selectedId) {
    var html = '<option value="">-- Select --</option>';
    yieldIngredientItems.forEach(function (item) {
        var label = (item.sku_code ? '[' + item.sku_code + '] ' : '') + item.sku_name;
        var selected = parseInt(item.id, 10) === parseInt(selectedId || 0, 10) ? ' selected' : '';
        html += '<option value="' + item.id + '"' + selected + '>' + label + '</option>';
    });
    return html;
}

function yieldOutputOptions(sourceId, selectedId) {
    var html = '<option value="">-- Select --</option>';
    yieldIngredientItems.forEach(function (item) {
        if (parseInt(item.id, 10) === parseInt(sourceId || 0, 10)) { return; }
        var label = (item.sku_code ? '[' + item.sku_code + '] ' : '') + item.sku_name;
        var selected = parseInt(item.id, 10) === parseInt(selectedId || 0, 10) ? ' selected' : '';
        html += '<option value="' + item.id + '"' + selected + '>' + label + '</option>';
    });
    return html;
}

function addYieldRow(component) {
    component = component || {};
    var sourceId = $('#yield-source-item').val();
    var qtyValue = (component.quantity !== undefined && component.quantity !== null && component.quantity !== '') ? component.quantity : '';
    var refPriceValue = (component.reference_price !== undefined && component.reference_price !== null && component.reference_price !== '' && parseFloat(component.reference_price) !== 0) ? component.reference_price : '';
    var tr = document.createElement('tr');
    tr.className = 'yield-row';
    tr.innerHTML = ''
        + '<td><select class="form-control input-sm yield-output-item selectpicker-inline" data-live-search="true" data-size="8">' + yieldOutputOptions(sourceId, component.output_item_id || 0) + '</select></td>'
        + '<td><input type="number" step="0.0001" min="0" class="form-control input-sm yield-qty" value="' + qtyValue + '"></td>'
        + '<td><input type="number" step="0.0001" min="0" class="form-control input-sm yield-ref-price" placeholder="optional" value="' + refPriceValue + '"></td>'
        + '<td><input type="text" class="form-control input-sm yield-cost" readonly value="' + (component.derived_cost_per_unit != null ? component.derived_cost_per_unit : '') + '"></td>'
        + '<td class="text-center"><button type="button" class="btn btn-danger btn-xs" onclick="removeYieldRow(this)"><i class="fa fa-times"></i></button></td>';
    document.getElementById('yield-rows-body').appendChild(tr);
    if (typeof $().selectpicker !== 'undefined') {
        $(tr).find('.selectpicker-inline').selectpicker();
    }
    recomputeAllYieldRows();
}

function removeYieldRow(btn) {
    $(btn).closest('tr').remove();
    recomputeAllYieldRows();
}

// Recomputes every row together rather than one at a time: once any row has a
// Reference Price, each row's cost depends on every other row's quantity *
// reference price (see calc_yield_output_unit_cost() server-side — same rule).
function recomputeAllYieldRows() {
    var sourceCost = parseFloat($('#yield-source-item').data('cost') || 0);
    var $rows = $('#yield-rows-body .yield-row');
    var entries = [];
    var totalMarketValue = 0;

    $rows.each(function () {
        var qty = parseFloat($(this).find('.yield-qty').val() || 0);
        var refPrice = parseFloat($(this).find('.yield-ref-price').val() || 0);
        var marketValue = qty > 0 ? qty * refPrice : 0;
        entries.push({ tr: this, qty: qty, marketValue: marketValue });
        totalMarketValue += marketValue;
    });

    entries.forEach(function (entry) {
        var cost = 0;
        if (entry.qty > 0) {
            cost = totalMarketValue > 0
                ? (sourceCost * (entry.marketValue / totalMarketValue)) / entry.qty
                : (sourceCost / entry.qty);
        }
        $(entry.tr).find('.yield-cost').val(entry.qty > 0 ? cost.toFixed(4) : '');
    });
}

function refreshYieldOutputOptions() {
    var sourceId = $('#yield-source-item').val();
    $('#yield-rows-body .yield-row').each(function () {
        var $sel = $(this).find('.yield-output-item');
        var current = $sel.val();
        // bootstrap-select's 'refresh' doesn't reliably rebuild the styled dropdown
        // when the <option> list is swapped wholesale via .html() (it can leave the
        // popup rendering as an unstyled inline list, especially with live-search
        // on) — destroy and re-init from scratch instead, same as a fresh row.
        if (typeof $sel.selectpicker === 'function') {
            $sel.selectpicker('destroy');
        }
        $sel.html(yieldOutputOptions(sourceId, current));
        if (typeof $sel.selectpicker === 'function') {
            $sel.selectpicker();
        }
    });
}

$('#yieldModal').on('change', '#yield-source-item', function () {
    var costMap = yieldItemCostMap();
    var cost = parseFloat(costMap[$(this).val()] || 0);
    $(this).data('cost', cost);
    $('#yield-source-cost').val(cost.toFixed(4));
    refreshYieldOutputOptions();
    recomputeAllYieldRows();
});

$('#yieldModal').on('change', '.yield-output-item', function () {
    recomputeAllYieldRows();
});
$('#yieldModal').on('keyup change', '.yield-qty, .yield-ref-price', function () {
    recomputeAllYieldRows();
});

function openYieldDialog(sourceItemId) {
    $('#yield-modal-title').text(sourceItemId ? 'Edit Yield Breakdown' : 'New Yield Breakdown');
    $('#yield-rows-body').html('');
    $('#yield-source-cost').val('0.0000');
    var $sourceSel = $('#yield-source-item');
    if (typeof $sourceSel.selectpicker === 'function') {
        $sourceSel.selectpicker('destroy');
    }
    $sourceSel.html(yieldSourceOptions(sourceItemId || 0)).data('cost', 0);
    if (typeof $sourceSel.selectpicker === 'function') {
        $sourceSel.selectpicker();
    }

    if (!sourceItemId) {
        addYieldRow();
        $('#yieldModal').modal('show');
        return;
    }

    $.post(getItemYieldsUrl, { item_id: sourceItemId }, function (res) {
        if (!(res && res.success && res.data)) {
            alert_float('danger', (res && res.error) || 'Failed to load yield breakdown');
            return;
        }
        var data = res.data;
        var cost = parseFloat(data.source_cost_per_unit || 0);
        $('#yield-source-item').data('cost', cost);
        $('#yield-source-cost').val(cost.toFixed(4));
        var components = data.rows || [];
        for (var k = 0; k < components.length; k++) {
            addYieldRow(components[k]);
        }
        if (!components.length) addYieldRow();
        $('#yieldModal').modal('show');
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function saveYieldBreakdown(form) {
    var sourceId = parseInt($('#yield-source-item').val() || 0, 10);
    if (!sourceId) {
        alert_float('danger', 'Source item is required');
        return false;
    }

    var rows = [];
    var incompleteRowCount = 0;
    $('#yield-rows-body .yield-row').each(function () {
        var outputId = parseInt($(this).find('.yield-output-item').val() || 0, 10);
        var qtyRaw = $(this).find('.yield-qty').val();
        var qty = parseFloat(qtyRaw || 0);
        // A row with no item picked and no quantity is just an unfilled blank row
        // (e.g. the default empty row) — skip it silently rather than posting it.
        if (!outputId && !qtyRaw) { return; }
        if (!outputId || !(qty > 0)) { incompleteRowCount++; return; }
        rows.push({
            output_item_id: outputId,
            quantity: qtyRaw,
            reference_price: $(this).find('.yield-ref-price').val() || 0
        });
    });

    if (incompleteRowCount > 0) {
        alert_float('danger', incompleteRowCount + ' derived item row(s) are missing an item or a quantity — fix or remove them before saving.');
        return false;
    }
    if (!rows.length) {
        alert_float('danger', 'Add at least one derived item before saving.');
        return false;
    }

    $.post(saveItemYieldsUrl, {
        item_id: sourceId,
        has_yield_breakdown: 1,
        item_yields: JSON.stringify(rows)
    }, function (res) {
        if (res && res.success) {
            var savedCount = (res.data && res.data.rows) ? res.data.rows.length : 0;
            if (savedCount !== rows.length) {
                // Sent N rows but the server reports a different count back — something
                // is dropping rows server-side despite reporting success. Surface it
                // instead of a plain "Saved" toast so it doesn't look like it worked.
                alert_float('warning', 'Sent ' + rows.length + ' row(s) but only ' + savedCount + ' were saved. Please check and try again.');
                return;
            }
            $('#yieldModal').modal('hide');
            alert_float('success', 'Saved. Reloading...');
            setTimeout(function () { location.reload(); }, 600);
        } else {
            alert_float('danger', (res && res.error) || 'Save failed');
        }
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
    return false;
}

function deleteYieldBreakdown(sourceItemId, sourceName) {
    if (!confirm('Remove the yield breakdown for "' + sourceName + '"? Its derived items will fall back to their own purchase price.')) {
        return;
    }
    $.post(saveItemYieldsUrl, {
        item_id: sourceItemId,
        has_yield_breakdown: 0,
        item_yields: '[]'
    }, function (res) {
        if (res && res.success) {
            alert_float('success', 'Removed. Reloading...');
            setTimeout(function () { location.reload(); }, 600);
        } else {
            alert_float('danger', (res && res.error) || 'Delete failed');
        }
    }, 'json').fail(function () {
        alert_float('danger', 'Network error');
    });
}

function applyYieldFilters() {
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.yield-summary-row').each(function () {
        var ok = !q || ('' + ($(this).data('search') || '')).indexOf(q) > -1;
        $(this).toggle(ok);
        if (ok) visible++;
    });
    $('#row-count').text(visible + ' items');
}
</script>
