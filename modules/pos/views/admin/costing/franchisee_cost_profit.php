<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
if (!function_exists('pos_format_cost_range')) {
    function pos_format_cost_range($min, $max, $isRange, $decimals)
    {
        if ($isRange) {
            return number_format((float)$min, $decimals) . ' – ' . number_format((float)$max, $decimals);
        }
        return number_format((float)$max, $decimals);
    }
}
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                                <p class="text-muted small">
                                    Same products as Product Cost Profit, but costed from a franchisee's point of view: any
                                    ingredient/packaging/mixed ingredient (or modifier) with a Franchisee Price set uses that price
                                    instead of its raw cost. Set Franchisee Price on the Individual Ingredients, Packaging, Mixed
                                    Ingredients, or Modifiers Cost Profit tabs.
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <button class="btn btn-default" onclick="exportTable()">
                                    <i class="fa fa-download"></i> Export This Table
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
                            <table class="table table-bordered table-striped table-hover" id="franchisee-cost-profit-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Product Name</th>
                                        <th>Category</th>
                                        <th style="width:120px;">Selling Price (RM)</th>
                                        <th style="width:130px;">Franchisee Cost (RM)</th>
                                        <th style="width:130px;">Franchisee Profit (RM)</th>
                                        <th style="width:140px;">Franchisee Margin (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item) {
                                        $category = $item['sub_category_name'] ?: ($item['category_name'] ?: '-');
                                        $isRange = !empty($item['is_range']);
                                    ?>
                                    <tr class="product-row"
                                        data-subgroup="<?php echo (int)($item['sub_group'] ?? 0); ?>"
                                        data-search="<?php echo htmlspecialchars(strtolower(($item['sku_code'] ?? '') . ' ' . ($item['sku_name'] ?? ''))); ?>">
                                        <td><?php echo htmlspecialchars($item['sku_code'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($item['sku_name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td class="text-right"><?php echo number_format((float)($item['selling_price'] ?? 0), 2); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['total_cost_min'] ?? 0, $item['total_cost_max'] ?? 0, $isRange, 4); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['profit_min'] ?? 0, $item['profit_max'] ?? 0, $isRange, 4); ?></td>
                                        <td class="text-right"><?php echo pos_format_cost_range($item['margin_min'] ?? 0, $item['margin_max'] ?? 0, $isRange, 2); ?></td>
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
function applyFilters() {
    var cat = parseInt($('#filter-category').val() || 0, 10);
    var q = ($('#filter-search').val() || '').toLowerCase().trim();
    var visible = 0;
    $('.product-row').each(function () {
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
    var table = document.getElementById('franchisee-cost-profit-table');
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
    a.download = 'franchisee_cost_profit_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}
</script>
