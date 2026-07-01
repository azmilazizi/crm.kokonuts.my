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
          <select id="promo-type" class="form-control" onchange="onPromoTypeChange()">
            <option value="promo"  <?php echo ($promo['type'] ?? 'promo') === 'promo'  ? 'selected' : ''; ?>>Promo — a single product with a promotional price/discount</option>
            <option value="bundle" <?php echo ($promo['type'] ?? '') === 'bundle' ? 'selected' : ''; ?>>Bundle — a product with customer-selectable choice groups</option>
            <option value="set"    <?php echo ($promo['type'] ?? '') === 'set'    ? 'selected' : ''; ?>>Set — a fixed set of items with no customer choice</option>
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

  <!-- Right: Components / Bundle Groups -->
  <div class="col-md-5">
    <div class="panel_s">
      <div class="panel-body">

        <!-- Flat components (promo type) -->
        <div id="pf-comp-section">
          <div class="clearfix" style="margin-bottom:8px;">
            <h5 class="bold no-margin-top pull-left">Components</h5>
            <button type="button" class="btn btn-default btn-xs pull-right" onclick="addComponent()"><i class="fa fa-plus"></i> Add Component</button>
          </div>
          <hr style="margin-top:0;margin-bottom:12px;">
          <p class="text-muted small">List the products and/or modifier groups that make up this promo. For reporting purposes only.</p>
          <div id="components-list"></div>
          <p id="no-components-msg" class="text-muted text-center small" style="padding:10px 0;">No components yet. Add one above.</p>
        </div>

        <!-- Bundle groups (bundle type) -->
        <div id="pf-bundle-section" style="display:none;">
          <div class="clearfix" style="margin-bottom:8px;">
            <h5 class="bold no-margin-top pull-left">Bundle Groups</h5>
            <div class="pull-right">
              <button type="button" class="btn btn-default btn-xs" onclick="bgAddChoiceGroup()"><i class="fa fa-plus"></i> Choice Group</button>
              <button type="button" class="btn btn-default btn-xs" style="margin-left:3px;" onclick="bgAddModifierGroup()"><i class="fa fa-plus"></i> Modifier Group</button>
            </div>
          </div>
          <hr style="margin-top:0;margin-bottom:12px;">
          <p class="text-muted small">Define the choice/modifier groups that make up this bundle.</p>
          <div id="bg-list"></div>
          <p id="no-bg-msg" class="text-muted text-center small" style="padding:10px 0;">No groups yet — add a Choice Group or Modifier Group.</p>

          <hr style="margin:12px 0 8px;">
          <div class="clearfix" style="margin-bottom:6px;">
            <span class="pull-left" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#777;line-height:24px;">Fixed Products</span>
            <button type="button" class="btn btn-default btn-xs pull-right" onclick="bgAddFixedProduct()"><i class="fa fa-plus"></i> Add Fixed Product</button>
          </div>
          <p class="text-muted" style="font-size:11px;margin-bottom:6px;">Always included in this bundle — for reporting/analytics only, not shown as a POS choice.</p>
          <div id="bg-fixed-list"></div>
          <p id="no-fixed-msg" class="text-muted text-center small" style="padding:4px 0;">No fixed products yet.</p>
        </div>

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
.bg-card { background:#fff; border:1px solid #d0d0d0; border-radius:4px; padding:8px 10px; margin-bottom:8px; }
.bg-card .bg-hdr { display:flex; align-items:center; gap:6px; margin-bottom:6px; }
.bg-card .bg-hdr input { height:26px; font-size:11px; padding:2px 6px; flex:1; }
.bg-card .bg-hdr select { height:26px; font-size:11px; padding:2px 4px; width:auto; }
.bg-card .bg-ref { margin-bottom:6px; }
.bg-card .bg-ref select { height:26px; font-size:11px; padding:2px 6px; width:100%; }
.bg-opt-row { display:flex; gap:4px; margin-bottom:3px; align-items:center; }
.bg-opt-row select { height:26px; font-size:11px; padding:2px 6px; flex:1; }
.bg-opt-row .rm { color:#d9534f; cursor:pointer; line-height:26px; padding:0 4px; flex-shrink:0; }
</style>

<script>
var ADMIN_URL           = '<?php echo admin_url(); ?>';
var ALL_ITEMS           = <?php echo json_encode(array_map(function($i){ return ['id'=>$i['id'],'label'=>$i['sku_name'].' ('.$i['sku_code'].')']; }, $all_items)); ?>;
var ALL_GROUPS          = <?php echo json_encode(array_map(function($g){ return ['id'=>$g['id'],'name'=>$g['name']]; }, $modifier_groups)); ?>;
var ALL_MODIFIERS_FLAT  = <?php echo json_encode(array_map(function($m){ return ['id'=>$m['id'],'label'=>$m['group_name'].' — '.$m['modifier_name']]; }, $all_modifiers)); ?>;
var ALL_PROMO_MOD_GROUPS= <?php echo json_encode(array_map(function($g){ return ['id'=>$g['id'],'name'=>$g['name']]; }, $promo_modifier_groups)); ?>;
var PROMO_ID            = <?php echo $promo ? $promo['id'] : 'null'; ?>;
var _components         = <?php echo $promo ? json_encode($promo['components']) : '[]'; ?>;
var _bundle_groups      = <?php echo ($promo && !empty($promo['bundle_groups'])) ? json_encode($promo['bundle_groups']) : '[]'; ?>;

// ── Type toggle ───────────────────────────────────────────────────────────

function onPromoTypeChange() {
    var type     = document.getElementById('promo-type').value;
    var isBundle = type === 'bundle';
    var isSet    = type === 'set';
    document.getElementById('discount-row').style.display      = (isBundle || isSet) ? 'none' : '';
    document.getElementById('pf-comp-section').style.display   = (isBundle) ? 'none' : '';
    document.getElementById('pf-bundle-section').style.display = isBundle ? '' : 'none';
}

// ── Discount value gate ───────────────────────────────────────────────────

function syncDiscountValue() {
    var hasType = !!document.getElementById('promo-discount-type').value;
    var valEl   = document.getElementById('promo-discount-value');
    valEl.disabled = !hasType;
    if (!hasType) valEl.value = '0.00';
}

// ── Flat components ───────────────────────────────────────────────────────

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

// ── Bundle groups builder ─────────────────────────────────────────────────

function bgUpdateMsg() {
    var has = document.querySelectorAll('.bg-card').length > 0;
    document.getElementById('no-bg-msg').style.display = has ? 'none' : '';
}

function _bgItemOpts(selectedId) {
    var h = '<option value="">— Select product —</option>';
    ALL_ITEMS.forEach(function(it) {
        h += '<option value="' + it.id + '"' + (it.id == selectedId ? ' selected' : '') + '>' + it.label + '</option>';
    });
    return h;
}

function _bgModifierOpts(selectedId) {
    var h = '<option value="">— Select modifier item —</option>';
    ALL_MODIFIERS_FLAT.forEach(function(m) {
        h += '<option value="' + m.id + '"' + (m.id == selectedId ? ' selected' : '') + '>' + m.label + '</option>';
    });
    return h;
}

function _bgPromoGroupOpts(selectedId) {
    var h = '<option value="">— Select Promo Modifier Group —</option>';
    ALL_PROMO_MOD_GROUPS.forEach(function(g) {
        h += '<option value="' + g.id + '"' + (g.id == selectedId ? ' selected' : '') + '>' + g.name + '</option>';
    });
    return h;
}

function bgAddChoiceGroup(g) {
    g = g || {};
    var card = document.createElement('div');
    card.className = 'bg-card';
    card.dataset.gtype = 'product_choice';
    card.innerHTML =
        '<div class="bg-hdr">'
        + '<span class="label label-info" style="font-size:10px;white-space:nowrap;">CHOICE</span>'
        + '<input type="text" class="form-control input-sm bg-name" placeholder="Group name e.g. Choice of Shake" value="' + ((g.name || '').replace(/"/g, '&quot;')) + '">'
        + '<span onclick="this.closest(\'.bg-card\').remove();bgUpdateMsg();" style="cursor:pointer;color:#d9534f;padding:0 4px;flex-shrink:0;"><i class="fa fa-times"></i></span>'
        + '</div>'
        + '<div class="bg-opts"></div>'
        + '<button type="button" class="btn btn-link btn-xs" style="padding:2px 0;" onclick="bgAddOption(this.previousElementSibling, \'item\')"><i class="fa fa-plus-circle"></i> Add product option</button>';
    document.getElementById('bg-list').appendChild(card);
    (g.options || []).forEach(function(opt) { bgAddOption(card.querySelector('.bg-opts'), 'item', opt.option_id); });
    bgUpdateMsg();
}

function bgAddModifierGroup(g) {
    g = g || {};
    var srcType = g.source_type || 'custom';
    var card = document.createElement('div');
    card.className = 'bg-card';
    card.dataset.gtype = 'modifier_choice';
    card.dataset.stype = srcType;
    card.innerHTML =
        '<div class="bg-hdr">'
        + '<span class="label label-success" style="font-size:10px;white-space:nowrap;">MODIFIER</span>'
        + '<input type="text" class="form-control input-sm bg-name" placeholder="Group name e.g. Add-ons" value="' + ((g.name || '').replace(/"/g, '&quot;')) + '">'
        + '<select class="form-control input-sm bg-src" onchange="bgToggleSrc(this)">'
        + '<option value="custom"' + (srcType === 'custom' ? ' selected' : '') + '>Custom options</option>'
        + '<option value="modifier_group_ref"' + (srcType === 'modifier_group_ref' ? ' selected' : '') + '>Promo Modifier Group</option>'
        + '</select>'
        + '<span onclick="this.closest(\'.bg-card\').remove();bgUpdateMsg();" style="cursor:pointer;color:#d9534f;padding:0 4px;flex-shrink:0;"><i class="fa fa-times"></i></span>'
        + '</div>'
        + '<div class="bg-ref" style="' + (srcType === 'modifier_group_ref' ? '' : 'display:none;') + 'margin-bottom:4px;">'
        + '<select class="form-control input-sm bg-ref-grp">' + _bgPromoGroupOpts(g.modifier_group_id || '') + '</select>'
        + '</div>'
        + '<div class="bg-custom" style="' + (srcType === 'custom' ? '' : 'display:none;') + '">'
        + '<div class="bg-opts"></div>'
        + '<button type="button" class="btn btn-link btn-xs" style="padding:2px 0;" onclick="bgAddOption(this.previousElementSibling, \'modifier\')"><i class="fa fa-plus-circle"></i> Add modifier option</button>'
        + '</div>';
    document.getElementById('bg-list').appendChild(card);
    if (srcType === 'custom') {
        (g.options || []).forEach(function(opt) { bgAddOption(card.querySelector('.bg-opts'), 'modifier', opt.option_id); });
    }
    bgUpdateMsg();
}

function bgToggleSrc(sel) {
    var card  = sel.closest('.bg-card');
    var isRef = sel.value === 'modifier_group_ref';
    card.dataset.stype = sel.value;
    card.querySelector('.bg-ref').style.display    = isRef ? '' : 'none';
    card.querySelector('.bg-custom').style.display = isRef ? 'none' : '';
}

function bgAddOption(listEl, optType, selectedId) {
    var row = document.createElement('div');
    row.className = 'bg-opt-row';
    var optHtml = optType === 'modifier' ? _bgModifierOpts(selectedId || '') : _bgItemOpts(selectedId || '');
    row.innerHTML = '<select class="form-control input-sm bg-opt-sel">' + optHtml + '</select>'
        + '<span class="rm" onclick="this.closest(\'.bg-opt-row\').remove()"><i class="fa fa-times"></i></span>';
    listEl.appendChild(row);
}

function collectBundleGroups() {
    var groups = [];
    document.querySelectorAll('.bg-card').forEach(function(card) {
        var stype = card.dataset.stype || 'custom';
        var g = {
            name:              card.querySelector('.bg-name').value,
            group_type:        card.dataset.gtype,
            source_type:       stype,
            modifier_group_id: null,
            options:           [],
        };
        if (stype === 'modifier_group_ref') {
            g.modifier_group_id = card.querySelector('.bg-ref-grp').value || null;
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

// ── Fixed products (bundle type) ──────────────────────────────────────────

function bgAddFixedProduct(fp) {
    fp = fp || {};
    var selHtml = '<option value="">— Select product —</option>';
    ALL_ITEMS.forEach(function(it) {
        selHtml += '<option value="' + it.id + '"' + (it.id == (fp.component_id || '') ? ' selected' : '') + '>' + it.label + '</option>';
    });
    var row = document.createElement('div');
    row.className = 'component-row';
    row.innerHTML = '<div style="display:flex;gap:6px;align-items:center;">'
        + '<select class="form-control fp-product" style="flex:3;">' + selHtml + '</select>'
        + '<input type="number" class="form-control fp-qty" style="width:60px;flex-shrink:0;" min="0.01" step="0.01" value="' + (fp.quantity || 1) + '" title="Qty">'
        + '<span class="remove-btn" onclick="this.closest(\'.component-row\').remove();bgUpdateFixedMsg()"><i class="fa fa-times"></i></span>'
        + '</div>';
    document.getElementById('bg-fixed-list').appendChild(row);
    bgUpdateFixedMsg();
}

function bgUpdateFixedMsg() {
    var has = document.getElementById('bg-fixed-list').querySelectorAll('.component-row').length > 0;
    document.getElementById('no-fixed-msg').style.display = has ? 'none' : '';
}

function collectFixedProducts() {
    var fps = [];
    document.querySelectorAll('#bg-fixed-list .component-row').forEach(function(row) {
        var id = row.querySelector('.fp-product').value;
        if (!id) return;
        fps.push({ component_type: 'product', component_id: parseInt(id), quantity: row.querySelector('.fp-qty').value || 1 });
    });
    return fps;
}

// ── Save ──────────────────────────────────────────────────────────────────

function savePromo() {
    var btn = document.querySelector('[onclick="savePromo()"]');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…'; }

    var type = document.getElementById('promo-type').value;
    $.post(ADMIN_URL + 'pos/ajax_save_promo', {
        id:             PROMO_ID || '',
        type:           type,
        pos_item_id:    document.getElementById('promo-item').value || '',
        discount_type:  type === 'promo' ? document.getElementById('promo-discount-type').value : '',
        discount_value: type === 'promo' ? (document.getElementById('promo-discount-value').value || 0) : 0,
        active:         document.getElementById('promo-active').value,
        components:     type === 'bundle' ? collectFixedProducts() : collectComponents(),
        bundle_groups:  collectBundleGroups(),
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

// ── Init ──────────────────────────────────────────────────────────────────

window.addEventListener('load', function() {
    syncDiscountValue();
    onPromoTypeChange();
    var type = document.getElementById('promo-type').value;
    if (type === 'bundle') {
        _components.forEach(function(c) { if (c.component_type === 'product') bgAddFixedProduct(c); });
        bgUpdateFixedMsg();
    } else {
        _components.forEach(function(c) { addComponent(c); });
    }
    _bundle_groups.forEach(function(g) {
        if (g.group_type === 'modifier_choice') bgAddModifierGroup(g);
        else bgAddChoiceGroup(g);
    });
    updateNoMsg();
    bgUpdateMsg();
});
</script>

<?php init_tail(); ?>
