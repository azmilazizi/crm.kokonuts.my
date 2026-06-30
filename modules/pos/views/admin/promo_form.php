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
          <label class="control-label">Type <span class="text-danger">*</span></label>
          <select id="promo-type" class="form-control">
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

        <div class="row" id="discount-row">
          <div class="col-sm-5">
            <div class="form-group">
              <label class="control-label">Discount Type</label>
              <select id="promo-discount-type" class="form-control" onchange="syncDiscountValue()">
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
          <button type="button" class="btn btn-default btn-xs pull-right" onclick="addComponent()"><i class="fa fa-plus"></i> Add Component</button>
        </div>
        <hr style="margin-top:8px;margin-bottom:12px;">
        <p class="text-muted small">List the products and/or modifier groups that make up this bundle or promo. For reporting purposes only.</p>
        <div id="components-list"></div>
        <p id="no-components-msg" class="text-muted text-center small" style="padding:10px 0;">No components yet. Add one above.</p>
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
.component-row .remove-btn { color:#d9534f; cursor:pointer; line-height:28px; }
</style>

<script>
var ADMIN_URL         = '<?php echo admin_url(); ?>';
var ALL_ITEMS         = <?php echo json_encode(array_map(function($i){ return ['id'=>$i['id'],'label'=>$i['sku_name'].' ('.$i['sku_code'].')']; }, $all_items)); ?>;
var ALL_GROUPS        = <?php echo json_encode(array_map(function($g){ return ['id'=>$g['id'],'name'=>$g['name']]; }, $modifier_groups)); ?>;
var PROMO_ID          = <?php echo $promo ? $promo['id'] : 'null'; ?>;
var _components       = <?php echo $promo ? json_encode($promo['components']) : '[]'; ?>;

function syncDiscountValue() {
    var hasType = !!document.getElementById('promo-discount-type').value;
    var valEl   = document.getElementById('promo-discount-value');
    valEl.disabled = !hasType;
    if (!hasType) valEl.value = '0.00';
}

function _buildUnifiedSelect(selType, selId) {
    var cur  = selType && selId ? (selType === 'modifier_group' ? 'group:' : 'product:') + selId : '';
    var html = '<option value="">— Select component —</option><optgroup label="Products">';
    ALL_ITEMS.forEach(function(it) {
        var v = 'product:' + it.id;
        html += '<option value="' + v + '"' + (v === cur ? ' selected' : '') + '>' + it.label + '</option>';
    });
    html += '</optgroup><optgroup label="Modifier Groups">';
    ALL_GROUPS.forEach(function(g) {
        var v = 'group:' + g.id;
        html += '<option value="' + v + '"' + (v === cur ? ' selected' : '') + '>' + g.name + '</option>';
    });
    html += '</optgroup>';
    return html;
}

function addComponent(c) {
    c = c || {};
    var row = document.createElement('div');
    row.className = 'component-row';
    row.innerHTML =
        '<div style="display:flex;gap:6px;align-items:center;">'
        + '<select class="form-control comp-selection" style="flex:3;">'
        + _buildUnifiedSelect(c.component_type || '', c.component_id || '')
        + '</select>'
        + '<input type="number" class="form-control comp-qty" style="width:60px;flex-shrink:0;" min="0.01" step="0.01" value="' + (c.quantity || 1) + '" title="Qty">'
        + '<span class="remove-btn" onclick="this.closest(\'.component-row\').remove();updateNoMsg()" title="Remove"><i class="fa fa-times"></i></span>'
        + '</div>';
    document.getElementById('components-list').appendChild(row);
    updateNoMsg();
}

function updateNoMsg() {
    var hasRows = document.querySelectorAll('.component-row').length > 0;
    document.getElementById('no-components-msg').style.display = hasRows ? 'none' : '';
}

function collectComponents() {
    var comps = [];
    document.querySelectorAll('.component-row').forEach(function(row) {
        var val = (row.querySelector('.comp-selection') || {}).value || '';
        if (!val) return;
        var sep  = val.indexOf(':');
        var kind = val.substring(0, sep);
        var id   = parseInt(val.substring(sep + 1));
        comps.push({
            component_type: kind === 'group' ? 'modifier_group' : 'product',
            component_id:   id || null,
            quantity:       (row.querySelector('.comp-qty') || {}).value || 1,
        });
    });
    return comps;
}

function savePromo() {
    var btn = document.querySelector('[onclick="savePromo()"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…'; }

    $.post(ADMIN_URL + 'pos/ajax_save_promo', {
        id:             PROMO_ID || '',
        type:           document.getElementById('promo-type').value,
        pos_item_id:    document.getElementById('promo-item').value || '',
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

window.addEventListener('load', function() {
    syncDiscountValue();
    _components.forEach(function(c) { addComponent(c); });
    updateNoMsg();
});
</script>

<?php init_tail(); ?>
