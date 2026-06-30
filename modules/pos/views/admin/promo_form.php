<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
<div class="content">

<div class="row">
  <div class="col-md-12">
    <div class="page-top-bar">
      <h4 class="page-title bold"><?php echo $title; ?></h4>
      <a href="<?php echo admin_url('pos/promos'); ?>" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Back to Promos & Bundles</a>
    </div>
  </div>
</div>

<div class="row">

  <!-- Left: Core info -->
  <div class="col-md-7">
    <div class="panel_s">
      <div class="panel-body">
        <h5 class="bold no-margin-top">Details</h5>
        <hr style="margin-top:8px;margin-bottom:16px;">

        <div class="form-group">
          <label class="control-label">Name <span class="text-danger">*</span></label>
          <input type="text" id="promo-name" class="form-control" placeholder="e.g. Boba Set A" value="<?php echo htmlspecialchars($promo['name'] ?? ''); ?>">
        </div>

        <div class="form-group">
          <label class="control-label">Type <span class="text-danger">*</span></label>
          <select id="promo-type" class="form-control" onchange="onTypeChange()">
            <option value="promo"  <?php echo ($promo['type'] ?? 'promo') === 'promo'  ? 'selected' : ''; ?>>Promo — a single product with a promotional price/discount</option>
            <option value="bundle" <?php echo ($promo['type'] ?? '') === 'bundle' ? 'selected' : ''; ?>>Bundle — a product that consists of multiple components</option>
          </select>
        </div>

        <div class="form-group">
          <label class="control-label">Linked Product <small class="text-muted">(the POS item sold as this promo/bundle)</small></label>
          <select id="promo-item" class="form-control selectpicker" data-live-search="true" title="— None / Unlinked —">
            <?php foreach ($all_items as $item) { ?>
            <option value="<?php echo $item['id']; ?>" <?php echo ($promo['pos_item_id'] ?? null) == $item['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($item['sku_name']); ?> (<?php echo htmlspecialchars($item['sku_code']); ?>)
            </option>
            <?php } ?>
          </select>
          <p class="text-muted small mtop5">When this product is sold, it will appear in Promo & Bundles reports.</p>
        </div>

        <div class="form-group">
          <label class="control-label">Description</label>
          <textarea id="promo-description" class="form-control" rows="3" placeholder="Optional notes about this promo or bundle…"><?php echo htmlspecialchars($promo['description'] ?? ''); ?></textarea>
        </div>

        <div class="row" id="discount-row">
          <div class="col-sm-5">
            <div class="form-group">
              <label class="control-label">Discount Type</label>
              <select id="promo-discount-type" class="form-control">
                <option value="">— None —</option>
                <option value="percentage" <?php echo ($promo['discount_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                <option value="fixed"      <?php echo ($promo['discount_type'] ?? '') === 'fixed'      ? 'selected' : ''; ?>>Fixed Amount (RM)</option>
              </select>
            </div>
          </div>
          <div class="col-sm-4">
            <div class="form-group">
              <label class="control-label">Discount Value</label>
              <input type="number" id="promo-discount-value" class="form-control" min="0" step="0.01" value="<?php echo number_format(($promo['discount_value'] ?? 0), 2, '.', ''); ?>" placeholder="0.00">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="control-label">Status</label>
          <select id="promo-active" class="form-control" style="width:160px;">
            <option value="1" <?php echo ($promo['active'] ?? 1) ? 'selected' : ''; ?>>Active</option>
            <option value="0" <?php echo !($promo['active'] ?? 1) ? 'selected' : ''; ?>>Inactive</option>
          </select>
        </div>

      </div>
    </div>
  </div>

  <!-- Right: Components -->
  <div class="col-md-5">
    <div class="panel_s">
      <div class="panel-body">
        <div class="clearfix">
          <h5 class="bold no-margin-top pull-left">Components</h5>
          <button type="button" class="btn btn-default btn-xs pull-right" onclick="addComponent('product')"><i class="fa fa-plus"></i> Add Product</button>
          <button type="button" class="btn btn-default btn-xs pull-right" style="margin-right:4px;" onclick="addComponent('modifier')"><i class="fa fa-plus"></i> Add Modifier</button>
        </div>
        <hr style="margin-top:8px;margin-bottom:12px;">
        <p class="text-muted small">List the products and/or modifiers that make up this bundle or promo. For reporting purposes only.</p>
        <div id="components-list"></div>
        <p id="no-components-msg" class="text-muted text-center small" style="padding:10px 0;">No components yet. Add products or modifiers above.</p>
      </div>
    </div>

    <div class="panel_s">
      <div class="panel-body">
        <button type="button" class="btn btn-info btn-block" onclick="savePromo()">
          <i class="fa fa-save"></i> <?php echo $promo ? 'Save Changes' : 'Create Promo/Bundle'; ?>
        </button>
        <a href="<?php echo admin_url('pos/promos'); ?>" class="btn btn-default btn-block mtop5">Cancel</a>
      </div>
    </div>
  </div>

</div><!-- /.row -->
</div>
</div>

<style>
.component-row { background:#f9f9f9; border:1px solid #e8e8e8; border-radius:4px; padding:8px 10px; margin-bottom:6px; }
.component-row .form-control { height:28px; font-size:12px; padding:3px 8px; }
.component-row label { font-size:11px; color:#999; text-transform:uppercase; font-weight:600; margin-bottom:2px; }
.component-row .remove-btn { color:#d9534f; cursor:pointer; line-height:28px; }
</style>

<script>
var ADMIN_URL    = '<?php echo admin_url(); ?>';
var ALL_ITEMS    = <?php echo json_encode(array_map(function($i){ return ['id'=>$i['id'],'label'=>$i['sku_name'].' ('.$i['sku_code'].')']; }, $all_items)); ?>;
var ALL_MODIFIERS = <?php echo json_encode(array_map(function($m){ return ['id'=>$m['id'],'label'=>$m['group_name'].' – '.$m['modifier_name']]; }, $all_modifiers)); ?>;
var PROMO_ID     = <?php echo $promo ? $promo['id'] : 'null'; ?>;
var _components  = <?php echo $promo ? json_encode($promo['components']) : '[]'; ?>;
var _compIdx     = 0;

function onTypeChange() {
    // Could hide discount row for bundles, etc. — keep both available for flexibility
}

function buildItemSelect(selectedId) {
    var opts = '<option value="">— Product —</option>';
    ALL_ITEMS.forEach(function(it) {
        opts += '<option value="' + it.id + '"' + (it.id == selectedId ? ' selected' : '') + '>' + it.label + '</option>';
    });
    return opts;
}

function buildModifierSelect(selectedId) {
    var opts = '<option value="">— Modifier —</option>';
    ALL_MODIFIERS.forEach(function(m) {
        opts += '<option value="' + m.id + '"' + (m.id == selectedId ? ' selected' : '') + '>' + m.label + '</option>';
    });
    return opts;
}

function addComponent(type, c) {
    c = c || {};
    var idx = _compIdx++;
    var isProduct = (c.component_type || type) === 'product';
    var selectHtml = isProduct
        ? '<select class="form-control comp-id" onchange="autoFillName(this,' + idx + ')">' + buildItemSelect(c.component_id || '') + '</select>'
        : '<select class="form-control comp-id" onchange="autoFillName(this,' + idx + ')">' + buildModifierSelect(c.component_id || '') + '</select>';

    var row = document.createElement('div');
    row.className = 'component-row';
    row.dataset.idx = idx;
    row.innerHTML =
        '<input type="hidden" class="comp-type" value="' + (c.component_type || type) + '">'
        + '<div class="row" style="margin:0 -4px;">'
        + '<div class="col-xs-5" style="padding:0 4px;"><label>' + (isProduct ? 'Product' : 'Modifier') + '</label>' + selectHtml + '</div>'
        + '<div class="col-xs-3" style="padding:0 4px;"><label>Name</label><input type="text" class="form-control comp-name" placeholder="Auto-fill or custom" value="' + (c.component_name || '') + '"></div>'
        + '<div class="col-xs-2" style="padding:0 4px;"><label>Qty</label><input type="number" class="form-control comp-qty" min="0.01" step="0.01" value="' + (c.quantity || 1) + '"></div>'
        + '<div class="col-xs-1" style="padding:0 4px;padding-top:16px;"><span class="remove-btn" onclick="removeComponent(this)" title="Remove"><i class="fa fa-times"></i></span></div>'
        + '</div>'
        + '<div style="margin-top:4px;"><label>Notes</label><input type="text" class="form-control comp-notes" placeholder="e.g. included free, default sweetness" value="' + (c.notes || '') + '"></div>';
    document.getElementById('components-list').appendChild(row);
    updateNoMsg();
}

function autoFillName(sel, idx) {
    var row = document.querySelector('[data-idx="' + idx + '"]');
    if (!row) return;
    var nameInput = row.querySelector('.comp-name');
    if (nameInput && !nameInput.dataset.userEdited) {
        nameInput.value = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
    }
}

function removeComponent(btn) {
    var row = btn.closest('.component-row');
    if (row) row.remove();
    updateNoMsg();
}

function updateNoMsg() {
    var hasRows = document.querySelectorAll('.component-row').length > 0;
    document.getElementById('no-components-msg').style.display = hasRows ? 'none' : '';
}

function collectComponents() {
    var comps = [];
    document.querySelectorAll('.component-row').forEach(function(row) {
        comps.push({
            component_type: row.querySelector('.comp-type').value,
            component_id:   row.querySelector('.comp-id').value || null,
            component_name: row.querySelector('.comp-name').value,
            quantity:       row.querySelector('.comp-qty').value || 1,
            notes:          row.querySelector('.comp-notes').value,
        });
    });
    return comps;
}

function savePromo() {
    var name = document.getElementById('promo-name').value.trim();
    if (!name) { alert('Name is required.'); document.getElementById('promo-name').focus(); return; }

    var btn = document.querySelector('[onclick="savePromo()"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…'; }

    $.post(ADMIN_URL + 'pos/ajax_save_promo', {
        id:             PROMO_ID || '',
        name:           name,
        type:           document.getElementById('promo-type').value,
        pos_item_id:    document.getElementById('promo-item').value || '',
        description:    document.getElementById('promo-description').value,
        discount_type:  document.getElementById('promo-discount-type').value,
        discount_value: document.getElementById('promo-discount-value').value || 0,
        active:         document.getElementById('promo-active').value,
        components:     collectComponents(),
    }).done(function(r) {
        if (typeof r === 'string') { try { r = JSON.parse(r); } catch(e){} }
        if (r && r.success) {
            window.location.href = ADMIN_URL + 'pos/promos';
        } else {
            alert((r && r.message) || 'Failed to save.');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> <?php echo $promo ? "Save Changes" : "Create Promo/Bundle"; ?>'; }
        }
    }).fail(function() {
        alert('Request failed. Please try again.');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> <?php echo $promo ? "Save Changes" : "Create Promo/Bundle"; ?>'; }
    });
}

// Init existing components
window.addEventListener('load', function() {
    _components.forEach(function(c) { addComponent(c.component_type, c); });
    updateNoMsg();
    // Track user edits to comp-name fields
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('comp-name')) e.target.dataset.userEdited = '1';
    });
});
</script>

<?php init_tail(); ?>
