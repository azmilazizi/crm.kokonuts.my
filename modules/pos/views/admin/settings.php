<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.pos-settings-sidebar {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 20px;
}

.pos-settings-group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #f7f7f7;
    border-bottom: 1px solid #e8e8e8;
}

.pos-settings-group-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #fff;
    border: 1px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: #666;
    font-size: 14px;
}

.pos-settings-group-title {
    font-weight: 600;
    font-size: 13px;
    color: #222;
    line-height: 1.3;
}

.pos-settings-group-subtitle {
    font-size: 11px;
    color: #999;
    margin-top: 1px;
}

.pos-settings-nav {
    list-style: none;
    margin: 0;
    padding: 4px 0;
    border-bottom: 1px solid #e8e8e8;
}

.pos-settings-nav:last-child {
    border-bottom: none;
}

.pos-settings-nav li a {
    display: block;
    padding: 9px 16px 9px 20px;
    font-size: 13px;
    color: #444;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: background 0.12s;
}

.pos-settings-nav li a:hover {
    background: #f5f5f5;
    text-decoration: none;
    color: #333;
}

.pos-settings-nav li.active a {
    color: #3c8dbc;
    font-weight: 600;
    background: #eef6fd;
    border-left-color: #3c8dbc;
}

/* Logo upload card */
.pos-logo-card {
    width: 180px;
    height: 120px;
    border: 2px dashed #ccc;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    background: #fafafa;
    transition: border-color 0.2s, background 0.2s;
}

.pos-logo-card:hover {
    border-color: #3c8dbc;
    background: #f0f7ff;
}

.pos-logo-card img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    display: block;
}

.pos-logo-placeholder {
    text-align: center;
    color: #bbb;
    pointer-events: none;
}

.pos-logo-placeholder i {
    font-size: 30px;
    display: block;
    margin-bottom: 6px;
}

.pos-logo-placeholder span {
    font-size: 11px;
    line-height: 1.5;
}

.pos-logo-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    opacity: 0;
    transition: opacity 0.18s;
    pointer-events: none;
}

.pos-logo-card:hover .pos-logo-overlay {
    opacity: 1;
}
</style>

<div id="wrapper">
    <div class="content">
        <div class="row">

            <!-- ── SIDEBAR ─────────────────────────────────────────── -->
            <div class="col-md-3">
                <div class="pos-settings-sidebar">

                    <!-- System Settings -->
                    <div class="pos-settings-group-header">
                        <div class="pos-settings-group-icon">
                            <i class="fa fa-cog"></i>
                        </div>
                        <div>
                            <div class="pos-settings-group-title">Settings</div>
                            <div class="pos-settings-group-subtitle">System settings</div>
                        </div>
                    </div>
                    <ul class="pos-settings-nav">
                        <li class="<?php echo $section === 'receipt' ? 'active' : ''; ?>">
                            <a href="<?php echo admin_url('pos/settings/receipt'); ?>">Receipt</a>
                        </li>
                    </ul>

                    <!-- Stores Settings -->
                    <div class="pos-settings-group-header">
                        <div class="pos-settings-group-icon">
                            <i class="fa fa-building-o"></i>
                        </div>
                        <div>
                            <div class="pos-settings-group-title">Stores</div>
                            <div class="pos-settings-group-subtitle">Store & POS settings</div>
                        </div>
                    </div>
                    <ul class="pos-settings-nav">
                        <li class="<?php echo $section === 'stores' ? 'active' : ''; ?>">
                            <a href="<?php echo admin_url('pos/settings/stores'); ?>">Stores</a>
                        </li>
                    </ul>

                </div>
            </div>

            <!-- ── MAIN CONTENT ────────────────────────────────────── -->
            <div class="col-md-9">

                <?php if ($section === 'receipt'): ?>
                <!-- ── RECEIPT SETTINGS ──────────────────────────── -->
                <div class="panel_s">
                    <div class="panel-body">

                        <div class="row" style="margin-bottom:4px;">
                            <div class="col-sm-6">
                                <h4 class="no-margin-top">Receipt settings</h4>
                            </div>
                            <div class="col-sm-6 text-right">
                                <div style="display:inline-flex;align-items:center;gap:8px;">
                                    <span class="text-muted" style="font-size:12px;white-space:nowrap;">Store</span>
                                    <select id="store-selector" class="form-control input-sm" style="width:200px;" onchange="changeStore(this.value)">
                                        <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?php echo (int)$wh['id']; ?>" <?php echo (int)$wh['id'] === (int)$warehouse_id ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($wh['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <hr style="margin-top:10px;">

                        <?php if (!$warehouse_id || empty($warehouses)): ?>
                        <p class="text-muted text-center" style="margin-top:30px;">
                            No stores available. Please configure a warehouse first.
                        </p>
                        <?php else: ?>

                        <input type="hidden" id="current-warehouse-id" value="<?php echo (int)$warehouse_id; ?>">

                        <!-- LOGO -->
                        <div class="form-group">
                            <label style="display:block;margin-bottom:10px;font-size:13px;font-weight:600;">Logo</label>

                            <div class="pos-logo-card" id="logo-card" onclick="triggerLogoUpload()" title="Click to upload logo">
                                <?php if (!empty($receipt_settings['logo'])): ?>
                                    <img src="<?php echo base_url(htmlspecialchars($receipt_settings['logo'])); ?>" id="logo-img" alt="Receipt Logo">
                                    <div class="pos-logo-overlay">Click to change</div>
                                <?php else: ?>
                                    <div class="pos-logo-placeholder" id="logo-placeholder">
                                        <i class="fa fa-picture-o"></i>
                                        <span>Click to upload<br>JPG, PNG, GIF, WEBP<br>Max 2 MB</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <input type="file" id="logo-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;" onchange="uploadLogo(this)">

                            <div style="margin-top:6px;">
                                <a href="#" id="logo-remove-link" class="text-danger" style="font-size:12px;<?php echo empty($receipt_settings['logo']) ? 'display:none;' : ''; ?>" onclick="deleteLogo(event)">
                                    <i class="fa fa-times"></i> Remove logo
                                </a>
                            </div>
                        </div>

                        <hr>

                        <!-- COMPANY INFO -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Company Name</label>
                                    <input type="text" id="field-company-name" class="form-control" placeholder="e.g. Kokonuts Sdn Bhd"
                                        value="<?php echo htmlspecialchars($receipt_settings['company_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Company Registration ID</label>
                                    <input type="text" id="field-company-reg-id" class="form-control" placeholder="e.g. 202103244312 (003308920-W)"
                                        value="<?php echo htmlspecialchars($receipt_settings['company_reg_id'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label>Store Address</label>
                                    <input type="text" id="field-address" class="form-control" placeholder="Full store address"
                                        value="<?php echo htmlspecialchars($receipt_settings['address'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" id="field-phone" class="form-control" placeholder="+60 12-345 6789"
                                        value="<?php echo htmlspecialchars($receipt_settings['phone'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- HEADER / FOOTER -->
                        <div class="form-group">
                            <label>Header <small class="text-muted">(appears at the top of the receipt)</small></label>
                            <textarea id="field-header" class="form-control" rows="3" maxlength="500"
                                placeholder="e.g. Thank you for shopping with us!"><?php echo htmlspecialchars($receipt_settings['header'] ?? ''); ?></textarea>
                            <div class="text-right text-muted" style="font-size:11px;margin-top:3px;">
                                <span id="header-count"><?php echo strlen($receipt_settings['header'] ?? ''); ?></span> / 500
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Footer <small class="text-muted">(appears at the bottom of the receipt)</small></label>
                            <textarea id="field-footer" class="form-control" rows="3" maxlength="500"
                                placeholder="e.g. For freshness, strongly recommend consuming upon purchase."><?php echo htmlspecialchars($receipt_settings['footer'] ?? ''); ?></textarea>
                            <div class="text-right text-muted" style="font-size:11px;margin-top:3px;">
                                <span id="footer-count"><?php echo strlen($receipt_settings['footer'] ?? ''); ?></span> / 500
                            </div>
                        </div>

                        <div class="text-right" style="margin-top:20px;">
                            <button id="btn-save-receipt" class="btn btn-primary" onclick="saveReceiptSettings()">
                                <i class="fa fa-save"></i> Save Settings
                            </button>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($section === 'stores'): ?>
                <!-- ── STORES LIST ────────────────────────────────── -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin-top">Stores</h4>
                        <p class="text-muted" style="font-size:13px;margin-bottom:16px;">
                            Stores are linked to your warehouses. To add or edit stores, manage them via the Warehouse module.
                        </p>
                        <hr style="margin-top:0;">

                        <table class="table table-hover" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>Store Name</th>
                                    <th class="text-center" style="width:100px;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($warehouses)): ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted" style="padding:24px;">
                                        No stores found. Configure warehouses to get started.
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($warehouses as $i => $wh): ?>
                                <tr>
                                    <td class="text-muted"><?php echo $i + 1; ?></td>
                                    <td><strong><?php echo htmlspecialchars($wh['name']); ?></strong></td>
                                    <td class="text-center">
                                        <span class="label label-success">Active</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php endif; ?>

            </div><!-- /col-md-9 -->
        </div><!-- /row -->
    </div><!-- /content -->
</div><!-- /wrapper -->

<?php init_tail(); ?>
<script>
var ADMIN_URL    = '<?php echo admin_url(); ?>';
var warehouseId  = <?php echo (int)($warehouse_id ?? 0); ?>;

function changeStore(id) {
    window.location.href = ADMIN_URL + 'pos/settings/receipt?store=' + id;
}

function triggerLogoUpload() {
    document.getElementById('logo-file-input').click();
}

function uploadLogo(input) {
    if (!input.files || !input.files[0]) return;

    var fd = new FormData();
    fd.append('logo', input.files[0]);
    fd.append('warehouse_id', warehouseId);

    var card = document.getElementById('logo-card');
    card.innerHTML = '<div style="text-align:center;color:#aaa;"><i class="fa fa-spinner fa-spin fa-2x"></i><br><small style="font-size:11px;margin-top:6px;display:block;">Uploading…</small></div>';

    $.ajax({
        url:         ADMIN_URL + 'pos/ajax_upload_receipt_logo',
        type:        'POST',
        data:        fd,
        processData: false,
        contentType: false,
        success: function (resp) {
            if (resp.success) {
                card.innerHTML =
                    '<img src="' + resp.logo_url + '" id="logo-img" alt="Receipt Logo" style="max-width:100%;max-height:100%;object-fit:contain;">' +
                    '<div class="pos-logo-overlay">Click to change</div>';
                document.getElementById('logo-remove-link').style.display = '';
            } else {
                alert(resp.message || 'Upload failed.');
                restorePlaceholder(card);
            }
        },
        error: function () {
            alert('Upload failed. Please try again.');
            restorePlaceholder(card);
        }
    });

    input.value = '';
}

function restorePlaceholder(card) {
    card.innerHTML =
        '<div class="pos-logo-placeholder" id="logo-placeholder">' +
        '<i class="fa fa-picture-o"></i>' +
        '<span>Click to upload<br>JPG, PNG, GIF, WEBP<br>Max 2 MB</span>' +
        '</div>';
}

function deleteLogo(e) {
    e.preventDefault();
    if (!confirm('Remove this logo?')) return;

    $.post(ADMIN_URL + 'pos/ajax_delete_receipt_logo', { warehouse_id: warehouseId }, function (resp) {
        if (resp.success) {
            var card = document.getElementById('logo-card');
            restorePlaceholder(card);
            document.getElementById('logo-remove-link').style.display = 'none';
        }
    }, 'json');
}

function saveReceiptSettings() {
    var btn = document.getElementById('btn-save-receipt');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

    $.post(ADMIN_URL + 'pos/ajax_save_receipt_settings', {
        warehouse_id:   warehouseId,
        company_name:   $('#field-company-name').val(),
        company_reg_id: $('#field-company-reg-id').val(),
        address:        $('#field-address').val(),
        phone:          $('#field-phone').val(),
        header:         $('#field-header').val(),
        footer:         $('#field-footer').val()
    }, function (resp) {
        if (resp.success) {
            btn.innerHTML = '<i class="fa fa-check"></i> Saved';
            setTimeout(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-save"></i> Save Settings';
            }, 2200);
        } else {
            alert(resp.message || 'Failed to save settings.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save"></i> Save Settings';
        }
    }, 'json').fail(function () {
        alert('Request failed. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save"></i> Save Settings';
    });
}

// Character counters
document.getElementById('field-header') && document.getElementById('field-header').addEventListener('input', function () {
    document.getElementById('header-count').textContent = this.value.length;
});
document.getElementById('field-footer') && document.getElementById('field-footer').addEventListener('input', function () {
    document.getElementById('footer-count').textContent = this.value.length;
});
</script>
</body>
</html>
