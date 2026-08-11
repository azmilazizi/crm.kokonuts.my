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
                                <p class="text-muted small">Historical snapshots of costs &amp; selling prices. Compare any two snapshots to analyze margin drift.</p>
                            </div>
                        </div>
                        <hr />

                        <div class="row mbot20" style="background: #f8f9fa; padding: 14px; border-radius: 6px; border: 1px solid #e5e7eb;">
                            <div class="col-md-12">
                                <h5 class="no-margin-top mbot10"><i class="fa fa-exchange"></i> Side-by-side Compare</h5>
                            </div>
                            <div class="col-md-4 form-group no-margin-bottom">
                                <label class="control-label small">Snapshot A</label>
                                <select id="cmp-a" class="form-control selectpicker">
                                    <option value="">-- Pick snapshot --</option>
                                    <?php foreach ($snapshots as $s) { ?>
                                        <option value="<?php echo (int)$s['id']; ?>">
                                            <?php echo htmlspecialchars(($s['snapshot_name'] ? $s['snapshot_name'] . ' — ' : '') . substr($s['snapshot_date'], 0, 10)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group no-margin-bottom">
                                <label class="control-label small">Snapshot B</label>
                                <select id="cmp-b" class="form-control selectpicker">
                                    <option value="">-- Pick snapshot --</option>
                                    <?php foreach ($snapshots as $s) { ?>
                                        <option value="<?php echo (int)$s['id']; ?>">
                                            <?php echo htmlspecialchars(($s['snapshot_name'] ? $s['snapshot_name'] . ' — ' : '') . substr($s['snapshot_date'], 0, 10)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group no-margin-bottom" style="padding-top:24px;">
                                <button class="btn btn-info btn-block" onclick="runCompare()">
                                    <i class="fa fa-balance-scale"></i> Compare
                                </button>
                            </div>
                        </div>

                        <?php if (empty($snapshots)) { ?>
                            <p class="text-muted text-center mtop30">No snapshots yet. Use <strong>Recalculate All Costs &rarr; Also create snapshot</strong> from the Product Costing page.</p>
                        <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Name</th>
                                        <th>Created By</th>
                                        <th>Notes</th>
                                        <th class="text-center"># Items</th>
                                        <th>Created At</th>
                                        <th style="width:240px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($snapshots as $s) {
                                        $sid = (int)$s['id'];
                                        $values = $this->db->where('snapshot_id', $sid)->get(db_prefix() . 'pos_cost_snapshot_values')->result_array();
                                        $count = count($values);
                                    ?>
                                    <tr id="snap-row-<?php echo $sid; ?>">
                                        <td><?php echo $sid; ?></td>
                                        <td><?php echo htmlspecialchars($s['snapshot_date'] ?? ''); ?></td>
                                        <td><strong><?php echo htmlspecialchars($s['snapshot_name'] ?? ''); ?></strong></td>
                                        <td><?php
                                            if (!empty($s['created_by_staff_id'])) {
                                                $st = $this->db->select('firstname, lastname')->where('staffid', (int)$s['created_by_staff_id'])->get(db_prefix() . 'staff')->row_array();
                                                echo htmlspecialchars(($st['firstname'] ?? '') . ' ' . ($st['lastname'] ?? ''));
                                            } else {
                                                echo '-';
                                            }
                                        ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($s['notes'] ?? '-'); ?></td>
                                        <td class="text-center"><span class="label label-info"><?php echo $count; ?></span></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($s['created_at'] ?? ''); ?></td>
                                        <td>
                                            <button class="btn btn-default btn-sm" onclick='viewSnapshot(<?php echo json_encode(["id" => $sid, "name" => $s["snapshot_name"] ?? ("Snapshot #" . $sid), "values" => $values], JSON_HEX_TAG); ?>)'>
                                                <i class="fa fa-eye"></i> View
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick='downloadSnapshotCsv(<?php echo json_encode(["id" => $sid, "values" => $values], JSON_HEX_TAG); ?>)'>
                                                <i class="fa fa-download"></i> CSV
                                            </button>
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

<div class="modal fade" id="snapViewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xlg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="snap-view-title">Snapshot Values</h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-condensed" id="snap-view-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Type</th>
                                <th class="text-right">Cost/Unit</th>
                                <th class="text-right">Sell Price</th>
                                <th class="text-right">Profit/Unit</th>
                                <th class="text-right">Margin %</th>
                            </tr>
                        </thead>
                        <tbody id="snap-view-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="snapCompareModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xlg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="snap-compare-title">Snapshot Comparison</h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                    <table class="table table-bordered table-striped table-condensed" id="snap-cmp-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-right">Cost Delta</th>
                                <th class="text-right">Price Delta</th>
                                <th class="text-right">Profit Delta</th>
                                <th class="text-right">Margin Δ (pp)</th>
                            </tr>
                        </thead>
                        <tbody id="snap-cmp-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
function viewSnapshot(data) {
    document.getElementById('snap-view-title').textContent = data.name || ('Snapshot #' + data.id);
    var body = document.getElementById('snap-view-body');
    body.innerHTML = '';
    var rows = data.values || [];
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        var cost = parseFloat(r.cost_per_unit || 0);
        var price = parseFloat(r.sell_price || 0);
        var profit = price - cost;
        var margin = price > 0 ? ((profit / price) * 100) : 0;
        var tr = document.createElement('tr');
        var typeClass = 'default';
        if (r.item_type === 'finished_product') typeClass = 'success';
        else if (r.item_type === 'combo') typeClass = 'primary';
        else if (r.item_type === 'mixed_ingredient') typeClass = 'warning';
        tr.innerHTML = '<td><strong>' + (r.product_name ? escapeHtml(r.product_name) : ('Item #' + r.item_id)) + '</strong></td>' +
            '<td><span class="label label-' + typeClass + '">' + (r.item_type ? escapeHtml(ucWords(r.item_type.replace(/_/g, ' '))) : 'Item') + '</span></td>' +
            '<td class="text-right">' + cost.toFixed(4) + '</td>' +
            '<td class="text-right">' + price.toFixed(2) + '</td>' +
            '<td class="text-right">' + profit.toFixed(4) + '</td>' +
            '<td class="text-right">' + margin.toFixed(2) + '%</td>';
        body.appendChild(tr);
    }
    if (rows.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No values in this snapshot.</td></tr>';
    }
    $('#snapViewModal').modal('show');
}

function downloadSnapshotCsv(data) {
    var rows = data.values || [];
    var csv = ['Product,Type,Cost/Unit,Sell Price,Profit/Unit,Margin %'];
    for (var i = 0; i < rows.length; i++) {
        var r = rows[i];
        var cost = parseFloat(r.cost_per_unit || 0);
        var price = parseFloat(r.sell_price || 0);
        var profit = price - cost;
        var margin = price > 0 ? ((profit / price) * 100) : 0;
        var pname = (r.product_name || ('Item #' + r.item_id)).replace(/"/g, '""');
        csv.push('"' + pname + '","' + (r.item_type || 'item') + '",' + cost.toFixed(4) + ',' + price.toFixed(2) + ',' + profit.toFixed(4) + ',' + margin.toFixed(2));
    }
    var blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'snapshot_' + data.id + '_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function runCompare() {
    var a = parseInt(document.getElementById('cmp-a').value || 0, 10);
    var b = parseInt(document.getElementById('cmp-b').value || 0, 10);
    if (!a || !b) { alert_float('warning', 'Please pick both snapshots'); return; }
    if (a === b) { alert_float('warning', 'Pick two different snapshots'); return; }

    var allData = <?php echo json_encode(array_map(function($s) use ($ci) {
        $vals = $ci->db->where('snapshot_id', (int)$s['id'])->get(db_prefix() . 'pos_cost_snapshot_values')->result_array();
        return ["id" => (int)$s['id'], "name" => $s['snapshot_name'] ?? ("Snapshot #" . $s['id']), "values" => $vals];
    }, $snapshots)); ?>;
    var snapA = null, snapB = null;
    for (var k = 0; k < allData.length; k++) {
        if (parseInt(allData[k].id, 10) === a) snapA = allData[k];
        if (parseInt(allData[k].id, 10) === b) snapB = allData[k];
    }
    if (!snapA || !snapB) { alert_float('danger', 'Snapshot data not found'); return; }

    document.getElementById('snap-compare-title').textContent = 'Compare: ' + snapA.name + ' → ' + snapB.name;

    var mapA = {}, mapB = {};
    for (var i = 0; i < snapA.values.length; i++) {
        var ra = snapA.values[i];
        mapA[parseInt(ra.item_id, 10)] = ra;
    }
    for (var j = 0; j < snapB.values.length; j++) {
        var rb = snapB.values[j];
        mapB[parseInt(rb.item_id, 10)] = rb;
    }

    var ids = Object.keys(mapA).concat(Object.keys(mapB)).filter(function (v, i, a) { return a.indexOf(v) === i; });
    var body = document.getElementById('snap-cmp-body');
    body.innerHTML = '';

    for (var k = 0; k < ids.length; k++) {
        var id = parseInt(ids[k], 10);
        var ra = mapA[id] || { cost_per_unit: 0, sell_price: 0, product_name: ('Item #' + id) };
        var rb = mapB[id] || { cost_per_unit: 0, sell_price: 0, product_name: ra.product_name };
        var cA = parseFloat(ra.cost_per_unit || 0), cB = parseFloat(rb.cost_per_unit || 0);
        var pA = parseFloat(ra.sell_price || 0), pB = parseFloat(rb.sell_price || 0);
        var profA = pA - cA, profB = pB - cB;
        var marA = pA > 0 ? ((profA / pA) * 100) : 0;
        var marB = pB > 0 ? ((profB / pB) * 100) : 0;
        var dC = cB - cA, dP = pB - pA, dPr = profB - profA, dM = marB - marA;
        function cell(v, dec) {
            var cls = v > 0 ? 'text-success' : (v < 0 ? 'text-danger' : 'text-muted');
            var sign = v > 0 ? '+' : '';
            return '<td class="text-right ' + cls + '">' + sign + v.toFixed(dec || 2) + '</td>';
        }
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><strong>' + escapeHtml(rb.product_name || ra.product_name) + '</strong></td>' +
            cell(dC, 4) + cell(dP, 4) + cell(dPr, 4) + cell(dM, 2);
        body.appendChild(tr);
    }
    if (ids.length === 0) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Nothing to compare.</td></tr>';
    }
    $('#snapCompareModal').modal('show');
}

function escapeHtml(s) {
    return ('' + (s == null ? '' : s)).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
function ucWords(s) {
    return ('' + s).replace(/\b\w/g, function (l) { return l.toUpperCase(); });
}
</script>
