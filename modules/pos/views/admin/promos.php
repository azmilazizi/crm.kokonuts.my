<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<div class="row">
  <div class="col-md-12">
    <div class="page-top-bar">
      <h4 class="page-title bold"><?php echo $title; ?></h4>
      <small class="text-muted">Flag products as promos or bundles for CRM reporting. Not synced to the POS app.</small>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-12">
    <div class="panel_s">
      <div class="panel-body">

        <div class="clearfix mbottom10">
          <?php if (has_permission('pos', '', 'create')) { ?>
          <a href="<?php echo admin_url('pos/promo_form'); ?>" class="btn btn-info btn-sm pull-left">
            <i class="fa fa-plus"></i> Add Promo/Bundle
          </a>
          <?php } ?>
          <div class="pull-right" style="display:flex;gap:6px;align-items:center;">
            <div class="input-group input-group-sm" style="width:220px;">
              <span class="input-group-addon"><i class="fa fa-search"></i></span>
              <input type="text" id="promo-search" class="form-control" placeholder="Search…" oninput="filterPromos()">
            </div>
            <select id="promo-type-filter" class="form-control input-sm" style="width:140px;" onchange="filterPromos()">
              <option value="">All Types</option>
              <option value="promo">Promo</option>
              <option value="bundle">Bundle</option>
            </select>
            <?php if (has_permission('pos', '', 'delete')) { ?>
            <button class="btn btn-danger btn-sm" id="btn-bulk-delete" onclick="bulkDelete()" style="display:none;">
              <i class="fa fa-trash"></i> Delete Selected
            </button>
            <?php } ?>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover table-condensed" id="promos-table">
            <thead>
              <tr>
                <th style="width:28px;"><input type="checkbox" id="check-all" onclick="toggleAll(this)"></th>
                <th>Name</th>
                <th>Type</th>
                <th>Linked Product</th>
                <th>Discount</th>
                <th>Components</th>
                <th>Status</th>
                <th style="width:100px;"></th>
              </tr>
            </thead>
            <tbody id="promos-tbody">
            <?php if (empty($promos)) { ?>
              <tr><td colspan="8" class="text-muted text-center" style="padding:30px 0;">No promos or bundles defined yet. <a href="<?php echo admin_url('pos/promo_form'); ?>">Add one</a>.</td></tr>
            <?php } else { foreach ($promos as $p) {
                $discount_str = '';
                if ($p['discount_type'] === 'percentage') $discount_str = number_format($p['discount_value'], 0) . '% off';
                elseif ($p['discount_type'] === 'fixed')  $discount_str = 'RM ' . number_format($p['discount_value'], 2) . ' off';
            ?>
              <tr data-name="<?php echo strtolower(htmlspecialchars($p['name'])); ?>" data-type="<?php echo $p['type']; ?>">
                <td><input type="checkbox" class="promo-check" value="<?php echo $p['id']; ?>" onchange="onCheckChange()"></td>
                <td>
                  <strong><?php echo htmlspecialchars($p['name']); ?></strong>
                  <?php if ($p['description']) { ?><br><small class="text-muted"><?php echo htmlspecialchars(mb_strimwidth($p['description'], 0, 80, '…')); ?></small><?php } ?>
                </td>
                <td>
                  <?php if ($p['type'] === 'bundle') { ?>
                  <span class="label label-info">Bundle</span>
                  <?php } else { ?>
                  <span class="label label-warning">Promo</span>
                  <?php } ?>
                </td>
                <td><?php echo $p['item_name'] ? '<span class="text-primary">' . htmlspecialchars($p['item_name']) . '</span><br><small class="text-muted">' . htmlspecialchars($p['item_code']) . '</small>' : '<span class="text-muted">—</span>'; ?></td>
                <td><?php echo $discount_str ?: '<span class="text-muted">—</span>'; ?></td>
                <td>
                  <a href="#" onclick="viewComponents(<?php echo $p['id']; ?>); return false;" class="text-muted small">
                    <i class="fa fa-list"></i> View
                  </a>
                </td>
                <td>
                  <?php if ($p['active']) { ?>
                  <span class="label label-success">Active</span>
                  <?php } else { ?>
                  <span class="label label-default">Inactive</span>
                  <?php } ?>
                </td>
                <td class="text-right" style="white-space:nowrap;">
                  <?php if (has_permission('pos', '', 'edit')) { ?>
                  <a href="<?php echo admin_url('pos/promo_form/' . $p['id']); ?>" class="btn btn-default btn-xs" title="Edit"><i class="fa fa-pencil"></i></a>
                  <?php } ?>
                  <?php if (has_permission('pos', '', 'delete')) { ?>
                  <button class="btn btn-danger btn-xs" onclick="deletePromo(<?php echo $p['id']; ?>, '<?php echo addslashes(htmlspecialchars($p['name'])); ?>')" title="Delete"><i class="fa fa-trash"></i></button>
                  <?php } ?>
                </td>
              </tr>
            <?php } } ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</div>

</div>
</div>

<!-- Components Modal -->
<div class="modal fade" id="components-modal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="comp-modal-title">Components</h4>
      </div>
      <div class="modal-body" id="comp-modal-body" style="padding:20px;min-height:80px;"></div>
    </div>
  </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function filterPromos() {
    var search = document.getElementById('promo-search').value.toLowerCase();
    var type   = document.getElementById('promo-type-filter').value;
    document.querySelectorAll('#promos-tbody tr[data-name]').forEach(function(tr) {
        var nameMatch = !search || tr.getAttribute('data-name').indexOf(search) !== -1;
        var typeMatch = !type  || tr.getAttribute('data-type') === type;
        tr.style.display = (nameMatch && typeMatch) ? '' : 'none';
    });
}

function toggleAll(el) {
    document.querySelectorAll('.promo-check').forEach(function(c){ c.checked = el.checked; });
    onCheckChange();
}

function onCheckChange() {
    var any = document.querySelectorAll('.promo-check:checked').length > 0;
    var btn = document.getElementById('btn-bulk-delete');
    if (btn) btn.style.display = any ? '' : 'none';
    document.getElementById('check-all').checked = document.querySelectorAll('.promo-check:checked').length === document.querySelectorAll('.promo-check').length;
}

function deletePromo(id, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_promo/' + id).done(function(r) {
        if (typeof r === 'string') { try { r = JSON.parse(r); } catch(e){} }
        if (r && r.success) { location.reload(); }
        else { alert('Failed to delete.'); }
    });
}

function bulkDelete() {
    var ids = [];
    document.querySelectorAll('.promo-check:checked').forEach(function(c){ ids.push(c.value); });
    if (!ids.length) return;
    if (!confirm('Delete ' + ids.length + ' item(s)? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_promos_bulk', { ids: ids }).done(function(r) {
        if (typeof r === 'string') { try { r = JSON.parse(r); } catch(e){} }
        if (r && r.success) { location.reload(); }
        else { alert('Failed to delete.'); }
    });
}

function viewComponents(id) {
    document.getElementById('comp-modal-body').innerHTML =
        '<div class="text-center" style="padding:30px;"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>';
    $('#components-modal').modal('show');
    $.post(ADMIN_URL + 'pos/ajax_get_promo/' + id).done(function(r) {
        if (typeof r === 'string') { try { r = JSON.parse(r); } catch(e){} }
        if (!r || !r.success || !r.data) {
            document.getElementById('comp-modal-body').innerHTML = '<p class="text-danger">Failed to load.</p>';
            return;
        }
        var p = r.data;
        document.getElementById('comp-modal-title').textContent = p.name + ' — Components';
        var comps = p.components || [];
        if (!comps.length) {
            document.getElementById('comp-modal-body').innerHTML =
                '<p class="text-muted text-center" style="padding:20px;">No components defined. <a href="' + ADMIN_URL + 'pos/promo_form/' + p.id + '">Edit</a> to add some.</p>';
            return;
        }
        var rows = comps.map(function(c) {
            var typeBadge = c.component_type === 'modifier'
                ? '<span class="label label-default">Modifier</span>'
                : '<span class="label label-primary">Product</span>';
            return '<tr>'
                + '<td>' + typeBadge + '</td>'
                + '<td><strong>' + (c.component_name || '—') + '</strong></td>'
                + '<td class="text-right">' + parseFloat(c.quantity || 1) + '</td>'
                + '<td class="text-muted small">' + (c.notes || '—') + '</td>'
                + '</tr>';
        }).join('');
        document.getElementById('comp-modal-body').innerHTML =
            '<table class="table table-condensed table-bordered no-margin" style="font-size:13px;">'
            + '<thead><tr><th>Type</th><th>Name</th><th class="text-right">Qty</th><th>Notes</th></tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '</table>';
    });
}
</script>

<?php init_tail(); ?>
