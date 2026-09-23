<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                                <p class="text-muted small">How much of what's currently in the till is already spoken for — stock that needs replacing and bills that are due — before anything counts as profit to roll out.</p>
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
                            <div class="col-md-4 col-sm-6">
                                <div class="panel_s" style="padding:12px 16px;">
                                    <div class="text-muted small">Stock reserve needed</div>
                                    <div class="h4 no-margin-top no-margin-bottom" id="summary-stock">RM 0.00</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="panel_s" style="padding:12px 16px;">
                                    <div class="text-muted small">Recurring reserve needed</div>
                                    <div class="h4 no-margin-top no-margin-bottom" id="summary-recurring">RM 0.00</div>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="panel_s" style="padding:12px 16px; background:#f7f9fc;">
                                    <div class="text-muted small">Total capital reserve</div>
                                    <div class="h4 no-margin-top no-margin-bottom" id="summary-total"><strong>RM 0.00</strong></div>
                                </div>
                            </div>
                        </div>

                        <h5>Stock Reserve <small class="text-muted">— items that deplete with sales, tracked by par level</small></h5>
                        <div class="row mbot15">
                            <div class="col-md-8">
                                <input type="text" id="filter-search" class="form-control" placeholder="Search SKU or Name..." onkeyup="applyFilters()">
                            </div>
                            <div class="col-md-4 text-right">
                                <button class="btn btn-success" onclick="saveVisibleRows()">
                                    <i class="fa fa-save"></i> Save
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover" id="reserve-table">
                                <thead>
                                    <tr>
                                        <th style="width:60px;">Track?</th>
                                        <th>SKU</th>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th style="width:130px;">Par Level</th>
                                        <th style="width:110px;">Current Stock</th>
                                        <th style="width:100px;">Depleted</th>
                                        <th style="width:120px;">Unit Cost</th>
                                        <th style="width:130px;">Reserve Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_items as $item) {
                                        $id = (int)$item['id'];
                                        $enabled = !empty($item['capital_reserve_enabled']);
                                        $parLevel = $item['capital_reserve_par_level'];
                                    ?>
                                    <tr class="reserve-row"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['sku_code'] ?? '') . ' ' . ($item['sku_name'] ?? ''))); ?>">
                                        <td class="text-center">
                                            <input type="checkbox" class="reserve-enabled" data-itemid="<?php echo $id; ?>" <?php echo $enabled ? 'checked' : ''; ?>>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['sku_code'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($item['category_name'] ?: '-'); ?></td>
                                        <td>
                                            <input type="number" step="0.0001" min="0" class="form-control input-sm reserve-par-level" placeholder="&mdash;" value="<?php echo $parLevel !== null ? number_format((float)$parLevel, 4, '.', '') : ''; ?>" data-itemid="<?php echo $id; ?>">
                                        </td>
                                        <td class="text-right reserve-current-stock">
                                            <?php echo number_format((float)($item['current_stock'] ?? 0), 3); ?> <?php echo htmlspecialchars($item['item_unit_name'] ?? ''); ?>
                                        </td>
                                        <td class="text-right reserve-depleted-pct" data-value="<?php echo (float)($item['depleted_pct'] ?? 0); ?>">
                                            <?php echo number_format((float)($item['depleted_pct'] ?? 0), 1); ?>%
                                        </td>
                                        <td class="text-right reserve-unit-cost">
                                            <?php echo number_format((float)($item['unit_cost'] ?? 0), 4); ?>
                                        </td>
                                        <td class="text-right reserve-target" data-value="<?php echo (float)($item['reserve_target'] ?? 0); ?>">
                                            <?php echo number_format((float)($item['reserve_target'] ?? 0), 2); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <hr />

                        <h5>Recurring Reserve <small class="text-muted">— bills/expenses marked recurring, accrued by time elapsed in their period</small></h5>
                        <p class="text-muted small">Read-only here — edit the recurring schedule or amount from Expenses/Bills.</p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="recurring-table">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th style="width:120px;">Amount</th>
                                        <th style="width:160px;">Repeats every</th>
                                        <th style="width:110px;">Period (days)</th>
                                        <th style="width:110px;">Elapsed</th>
                                        <th style="width:130px;">Reserve Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recurring_expenses as $exp) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($exp['category_name'] ?: '-'); ?></td>
                                        <td class="text-right"><?php echo number_format((float)$exp['amount'], 2); ?></td>
                                        <td><?php echo (int)$exp['repeat_every']; ?> <?php echo htmlspecialchars($exp['recurring_type'] ?? ''); ?></td>
                                        <td class="text-right"><?php echo (int)$exp['period_days']; ?></td>
                                        <td class="text-right recurring-elapsed-pct" data-value="<?php echo (float)($exp['elapsed_pct'] ?? 0); ?>">
                                            <?php echo number_format((float)($exp['elapsed_pct'] ?? 0), 1); ?>% (<?php echo (int)$exp['elapsed_days']; ?>d)
                                        </td>
                                        <td class="text-right recurring-target" data-value="<?php echo (float)($exp['reserve_target'] ?? 0); ?>">
                                            <?php echo number_format((float)($exp['reserve_target'] ?? 0), 2); ?>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                    <?php if (empty($recurring_expenses)) { ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No recurring expenses found.</td>
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
var saveUrl = '<?php echo admin_url('pos/ajax_save_capital_reserve_setting'); ?>';

function computeSummary() {
    var stockTotal = 0;
    $('#reserve-table .reserve-row:visible .reserve-target').each(function () {
        stockTotal += parseFloat($(this).data('value')) || 0;
    });
    var recurringTotal = 0;
    $('#recurring-table .recurring-target').each(function () {
        recurringTotal += parseFloat($(this).data('value')) || 0;
    });
    $('#summary-stock').text('RM ' + stockTotal.toFixed(2));
    $('#summary-recurring').text('RM ' + recurringTotal.toFixed(2));
    $('#summary-total').html('<strong>RM ' + (stockTotal + recurringTotal).toFixed(2) + '</strong>');
}

function applyRowData(row, d) {
    if (!d) return;
    if (typeof d.current_stock !== 'undefined') {
        row.find('.reserve-current-stock').text(parseFloat(d.current_stock).toFixed(3) + ' ' + (d.item_unit_name || ''));
    }
    if (typeof d.depleted_pct !== 'undefined') {
        row.find('.reserve-depleted-pct').attr('data-value', d.depleted_pct).text(parseFloat(d.depleted_pct).toFixed(1) + '%');
    }
    if (typeof d.unit_cost !== 'undefined') {
        row.find('.reserve-unit-cost').text(parseFloat(d.unit_cost).toFixed(4));
    }
    if (typeof d.reserve_target !== 'undefined') {
        row.find('.reserve-target').attr('data-value', d.reserve_target).text(parseFloat(d.reserve_target).toFixed(2));
    }
}

function saveRowSetting(itemId, row, done) {
    var data = {
        item_id: itemId,
        enabled: row.find('.reserve-enabled[data-itemid=' + itemId + ']').is(':checked') ? 1 : 0,
        par_level: row.find('.reserve-par-level[data-itemid=' + itemId + ']').val()
    };
    $.post(saveUrl, data, function (res) {
        if (res && res.success) {
            applyRowData(row, res.data);
        }
        if (typeof done === 'function') done(res);
    }, 'json').fail(function (xhr) {
        if (typeof done === 'function') {
            done({ success: false, error: 'HTTP ' + xhr.status + ': ' + ((xhr.responseText || '').slice(0, 200) || 'no response') });
        }
    });
}

function saveVisibleRows() {
    var rows = $('#reserve-table .reserve-row:visible');
    if (!rows.length) return;
    var pending = rows.length;
    var failed = 0;
    var firstError = '';
    rows.each(function () {
        var row = $(this);
        var itemId = parseInt(row.find('.reserve-enabled').data('itemid'), 10);
        saveRowSetting(itemId, row, function (res) {
            if (!(res && res.success)) {
                failed++;
                if (!firstError) firstError = (res && (res.error || res.message)) || 'Unknown error';
            }
            pending--;
            if (pending === 0) {
                computeSummary();
                if (failed > 0) {
                    alert_float('warning', failed + ' row(s) failed to save — ' + firstError);
                } else {
                    alert_float('success', 'Saved');
                }
            }
        });
    });
}

function applyFilters() {
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    $('#reserve-table .reserve-row').each(function () {
        var $r = $(this);
        var ok = !q || ('' + ($r.data('search') || '')).indexOf(q) >= 0;
        $r.toggle(ok);
    });
    computeSummary();
}

$(document).ready(function () {
    computeSummary();
});
</script>
