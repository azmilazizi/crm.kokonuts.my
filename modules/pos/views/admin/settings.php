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

/* Payment mode toggle */
.pm-toggle {
    position: relative;
    display: inline-block;
    width: 42px;
    height: 24px;
    flex-shrink: 0;
}
.pm-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}
.pm-toggle-slider {
    position: absolute;
    cursor: pointer;
    inset: 0;
    background: #ccc;
    border-radius: 24px;
    transition: background 0.2s;
}
.pm-toggle-slider:before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    left: 3px;
    top: 3px;
    background: #fff;
    border-radius: 50%;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,.2);
}
.pm-toggle input:checked + .pm-toggle-slider {
    background: #5cb85c;
}
.pm-toggle input:checked + .pm-toggle-slider:before {
    transform: translateX(18px);
}
.pm-toggle input:disabled + .pm-toggle-slider {
    opacity: 0.5;
    cursor: not-allowed;
}

/* CFD display type cards */
.cfd-type-card {
    display: block;
    border: 2px solid #ddd;
    border-radius: 6px;
    padding: 12px 10px;
    text-align: center;
    cursor: pointer;
    background: #fafafa;
    transition: border-color 0.15s, background 0.15s;
    margin: 0;
    font-weight: normal;
}
.cfd-type-card:hover {
    border-color: #aac9e8;
    background: #f0f7ff;
}
.cfd-type-active {
    border-color: #3c8dbc !important;
    background: #eef6fd !important;
}
.cfd-type-icon {
    font-size: 22px;
    color: #3c8dbc;
    margin-bottom: 6px;
}
.cfd-type-label {
    font-size: 13px;
    font-weight: 600;
    color: #333;
}
.cfd-type-desc {
    font-size: 11px;
    color: #888;
    margin-top: 3px;
    line-height: 1.4;
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
                        <li class="<?php echo $section === 'payment_modes' ? 'active' : ''; ?>">
                            <a href="<?php echo admin_url('pos/settings/payment_modes'); ?>">Payment Modes</a>
                        </li>
                        <li class="<?php echo $section === 'cfd' ? 'active' : ''; ?>">
                            <a href="<?php echo admin_url('pos/settings/cfd' . ($warehouse_id ? '?store=' . $warehouse_id : '')); ?>">Customer Facing Display</a>
                        </li>
                    </ul>

                    <!-- Integrations -->
                    <div class="pos-settings-group-header">
                        <div class="pos-settings-group-icon">
                            <i class="fa fa-plug"></i>
                        </div>
                        <div>
                            <div class="pos-settings-group-title">Integrations</div>
                            <div class="pos-settings-group-subtitle">Third-party platforms</div>
                        </div>
                    </div>
                    <ul class="pos-settings-nav">
                        <li class="<?php echo $section === 'grabfood' ? 'active' : ''; ?>">
                            <a href="<?php echo admin_url('pos/settings/grabfood' . ($warehouse_id ? '?store=' . $warehouse_id : '')); ?>">GrabFood</a>
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

                <?php elseif ($section === 'cfd'): ?>
                <!-- ── CUSTOMER FACING DISPLAY ──────────────────── -->
                <div class="panel_s">
                    <div class="panel-body">

                        <div class="row" style="margin-bottom:4px;">
                            <div class="col-sm-6">
                                <h4 class="no-margin-top">Customer Facing Display</h4>
                            </div>
                            <div class="col-sm-6 text-right">
                                <div style="display:inline-flex;align-items:center;gap:8px;">
                                    <span class="text-muted" style="font-size:12px;white-space:nowrap;">Store</span>
                                    <select id="cfd-store-selector" class="form-control input-sm" style="width:200px;" onchange="changeCfdStore(this.value)">
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

                        <input type="hidden" id="cfd-warehouse-id" value="<?php echo (int)$warehouse_id; ?>">

                        <!-- DISPLAY TYPE -->
                        <div class="form-group">
                            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:10px;">Display Mode</label>
                            <div class="row">
                                <?php
                                $current_type = $cfd_settings['display_type'] ?? 'static_image';
                                $types = [
                                    'static_image'  => ['icon' => 'fa-image',       'label' => 'Static Image',     'desc' => 'One image shown at all times'],
                                    'slideshow'      => ['icon' => 'fa-clone',       'label' => 'Slideshow',        'desc' => 'Cycle through multiple images'],
                                    'video'          => ['icon' => 'fa-play-circle', 'label' => 'Looped Video',     'desc' => 'Single video played on loop'],
                                    'playlist'       => ['icon' => 'fa-list',        'label' => 'Playlist',         'desc' => 'Mix of images and videos in order'],
                                ];
                                ?>
                                <?php foreach ($types as $type_key => $type_meta): ?>
                                <div class="col-xs-6 col-sm-3" style="margin-bottom:10px;">
                                    <label class="cfd-type-card <?php echo $current_type === $type_key ? 'cfd-type-active' : ''; ?>"
                                           onclick="selectCfdType('<?php echo $type_key; ?>')">
                                        <input type="radio" name="cfd_display_type" value="<?php echo $type_key; ?>"
                                               <?php echo $current_type === $type_key ? 'checked' : ''; ?> style="display:none;">
                                        <div class="cfd-type-icon"><i class="fa <?php echo $type_meta['icon']; ?>"></i></div>
                                        <div class="cfd-type-label"><?php echo $type_meta['label']; ?></div>
                                        <div class="cfd-type-desc"><?php echo $type_meta['desc']; ?></div>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- SLIDE DURATION (hidden for video-only) -->
                        <div id="cfd-slide-duration-row" class="form-group" style="<?php echo $current_type === 'video' ? 'display:none;' : ''; ?>">
                            <label style="font-size:13px;font-weight:600;">Slide Duration <small class="text-muted">(seconds per image)</small></label>
                            <div style="display:flex;align-items:center;gap:10px;margin-top:6px;">
                                <input type="number" id="cfd-slide-duration" class="form-control" min="1" max="120"
                                       style="width:100px;"
                                       value="<?php echo (int)($cfd_settings['slide_duration'] ?? 5); ?>">
                                <span class="text-muted" style="font-size:12px;">seconds</span>
                            </div>
                        </div>

                        <div class="text-right" style="margin-bottom:20px;">
                            <button id="btn-save-cfd" class="btn btn-primary" onclick="saveCfdSettings()">
                                <i class="fa fa-save"></i> Save Display Settings
                            </button>
                        </div>

                        <hr>

                        <!-- MEDIA LIBRARY -->
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <div>
                                <strong style="font-size:13px;">Media Library</strong>
                                <p class="text-muted" style="font-size:12px;margin:2px 0 0;">
                                    Upload images (JPG, PNG, GIF, WEBP) or videos (MP4, MOV, WEBM) — max 50 MB each.
                                    Drag rows to reorder.
                                </p>
                            </div>
                            <button class="btn btn-default btn-sm" onclick="document.getElementById('cfd-media-input').click()">
                                <i class="fa fa-plus"></i> Add Media
                            </button>
                        </div>
                        <input type="file" id="cfd-media-input" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm"
                               style="display:none;" multiple onchange="uploadCfdMedia(this)">

                        <div id="cfd-media-list">
                            <?php if (empty($cfd_media_items)): ?>
                            <div id="cfd-empty-state" class="text-center text-muted" style="padding:30px;border:2px dashed #ddd;border-radius:6px;">
                                <i class="fa fa-photo" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                                No media added yet. Click <strong>Add Media</strong> to upload images or videos.
                            </div>
                            <?php else: ?>
                            <table class="table table-hover" id="cfd-media-table" style="margin-bottom:0;">
                                <thead>
                                    <tr>
                                        <th style="width:32px;"></th>
                                        <th style="width:70px;">Preview</th>
                                        <th>File</th>
                                        <th style="width:80px;" class="text-center">Type</th>
                                        <th style="width:90px;" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="cfd-media-tbody">
                                    <?php foreach ($cfd_media_items as $item): ?>
                                    <tr data-id="<?php echo (int)$item['id']; ?>">
                                        <td class="cfd-drag-handle" style="cursor:move;color:#bbb;padding-top:18px;"><i class="fa fa-bars"></i></td>
                                        <td>
                                            <?php if ($item['media_type'] === 'video'): ?>
                                            <video src="<?php echo base_url(htmlspecialchars($item['file_path'])); ?>"
                                                   style="width:60px;height:40px;object-fit:cover;border-radius:3px;" muted></video>
                                            <?php else: ?>
                                            <img src="<?php echo base_url(htmlspecialchars($item['file_path'])); ?>"
                                                 style="width:60px;height:40px;object-fit:cover;border-radius:3px;" alt="">
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px;word-break:break-all;vertical-align:middle;">
                                            <?php echo htmlspecialchars(basename($item['file_path'])); ?>
                                        </td>
                                        <td class="text-center" style="vertical-align:middle;">
                                            <?php if ($item['media_type'] === 'video'): ?>
                                            <span class="label label-info">Video</span>
                                            <?php else: ?>
                                            <span class="label label-default">Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center" style="vertical-align:middle;">
                                            <a href="#" class="text-danger" onclick="deleteCfdMedia(event, <?php echo (int)$item['id']; ?>)" title="Remove">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($section === 'payment_modes'): ?>
                <!-- ── PAYMENT MODES ─────────────────────────────── -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin-top">Payment Modes</h4>
                        <p class="text-muted" style="font-size:13px;margin-bottom:16px;">
                            Control which payment modes are available in the POS app. Toggling a mode off hides it from the cashier screen. Modes are managed globally in
                            <a href="<?php echo admin_url('settings?group=payment_gateways'); ?>">Settings &rsaquo; Payment Gateways</a>.
                        </p>
                        <hr style="margin-top:0;">

                        <?php if (empty($payment_modes)): ?>
                        <p class="text-muted text-center" style="margin-top:30px;">
                            No active payment modes found. Add payment modes in Settings first.
                        </p>
                        <?php else: ?>
                        <table class="table" style="margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>Payment Mode</th>
                                    <th style="width:200px;" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_modes as $mode): ?>
                                <tr id="pm-row-<?php echo (int)$mode['id']; ?>">
                                    <td style="vertical-align:middle;">
                                        <strong><?php echo htmlspecialchars($mode['name']); ?></strong>
                                    </td>
                                    <td class="text-center" style="vertical-align:middle;">
                                        <div style="display:inline-flex;align-items:center;gap:12px;">
                                            <?php if (strtolower($mode['name']) === 'duitnow qr'): ?>
                                            <a href="<?php echo admin_url('pos/chip_settings'); ?>"
                                               class="btn btn-default btn-sm"
                                               style="border:1px solid #3c8dbc;color:#3c8dbc;">
                                                <i class="fa fa-cog"></i> Configure
                                            </a>
                                            <?php endif; ?>
                                            <label class="pm-toggle" title="<?php echo (int)$mode['pos_enabled'] ? 'Enabled in POS' : 'Disabled in POS'; ?>">
                                                <input type="checkbox"
                                                       <?php echo (int)$mode['pos_enabled'] ? 'checked' : ''; ?>
                                                       onchange="togglePaymentMode(<?php echo (int)$mode['id']; ?>, this)">
                                                <span class="pm-toggle-slider"></span>
                                            </label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($section === 'grabfood'): ?>
                <!-- ── GRABFOOD INTEGRATION ──────────────────────── -->
                <div class="panel_s">
                    <div class="panel-body">

                        <div class="row" style="margin-bottom:4px;">
                            <div class="col-sm-6">
                                <h4 class="no-margin-top">GrabFood Integration</h4>
                                <p class="text-muted" style="font-size:13px;margin-bottom:0;">
                                    Connect your GrabFood merchant account to sync orders for reporting and analytics.
                                    Credentials are available from the
                                    <a href="https://developer.grab.com" target="_blank">Grab Developer Portal</a>.
                                </p>
                            </div>
                            <div class="col-sm-6 text-right">
                                <div style="display:inline-flex;align-items:center;gap:8px;">
                                    <span class="text-muted" style="font-size:12px;white-space:nowrap;">Store</span>
                                    <select id="gf-store-selector" class="form-control input-sm" style="width:200px;" onchange="changeGfStore(this.value)">
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

                        <input type="hidden" id="gf-warehouse-id" value="<?php echo (int)$warehouse_id; ?>">

                        <?php if (!empty($grabfood_settings['last_sync_at'])): ?>
                        <div class="alert alert-info" style="font-size:12px;padding:8px 14px;margin-bottom:16px;">
                            <i class="fa fa-clock-o"></i>
                            Last synced: <strong><?php echo date('d M Y, H:i', strtotime($grabfood_settings['last_sync_at'])); ?></strong>
                            &nbsp;&mdash;&nbsp;
                            <a href="<?php echo admin_url('pos/grabfood_orders?store=' . (int)$warehouse_id); ?>">View GrabFood Orders</a>
                        </div>
                        <?php endif; ?>

                        <!-- Environment -->
                        <div class="form-group">
                            <label style="font-size:13px;font-weight:600;">Environment</label>
                            <div style="display:flex;gap:10px;margin-top:6px;">
                                <label style="font-weight:normal;display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="radio" name="gf_environment" value="sandbox" id="gf-env-sandbox"
                                        <?php echo ($grabfood_settings['environment'] ?? 'sandbox') === 'sandbox' ? 'checked' : ''; ?>>
                                    Sandbox <small class="text-muted">(testing)</small>
                                </label>
                                <label style="font-weight:normal;display:flex;align-items:center;gap:6px;cursor:pointer;">
                                    <input type="radio" name="gf_environment" value="production" id="gf-env-prod"
                                        <?php echo ($grabfood_settings['environment'] ?? '') === 'production' ? 'checked' : ''; ?>>
                                    Production <small class="text-muted">(live orders)</small>
                                </label>
                            </div>
                        </div>

                        <hr>

                        <!-- OAuth Credentials -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Client ID</label>
                                    <input type="text" id="gf-client-id" class="form-control" autocomplete="off"
                                           placeholder="From Grab Developer Portal"
                                           value="<?php echo htmlspecialchars($grabfood_settings['client_id'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Client Secret</label>
                                    <div class="input-group">
                                        <input type="password" id="gf-client-secret" class="form-control" autocomplete="new-password"
                                               placeholder="From Grab Developer Portal"
                                               value="<?php echo htmlspecialchars($grabfood_settings['client_secret'] ?? ''); ?>">
                                        <span class="input-group-btn">
                                            <button class="btn btn-default" type="button" onclick="toggleSecret()" title="Show/hide">
                                                <i class="fa fa-eye" id="gf-secret-icon"></i>
                                            </button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <!-- Merchant / Store IDs -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Partner / Merchant ID <small class="text-muted">(from Grab)</small></label>
                                    <input type="text" id="gf-partner-id" class="form-control"
                                           placeholder="e.g. 4-CXXXXXXXXXXXXXXXX"
                                           value="<?php echo htmlspecialchars($grabfood_settings['partner_id'] ?? ''); ?>">
                                    <p class="help-block" style="font-size:11px;">Your merchant ID from the GrabFood Partner dashboard.</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>GrabFood Store ID <small class="text-muted">(outlet)</small></label>
                                    <input type="text" id="gf-store-id" class="form-control"
                                           placeholder="e.g. store-XXXXXXXX"
                                           value="<?php echo htmlspecialchars($grabfood_settings['grabfood_store_id'] ?? ''); ?>">
                                    <p class="help-block" style="font-size:11px;">Your specific outlet/store ID on GrabFood.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Active toggle -->
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="gf-active" value="1" <?php echo !empty($grabfood_settings['active']) ? 'checked' : ''; ?>>
                                &nbsp;Enable GrabFood integration for this store
                            </label>
                        </div>

                        <div id="gf-test-result" style="display:none;" class="alert" style="margin-top:10px;"></div>

                        <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;">
                            <button id="btn-save-gf" class="btn btn-primary" onclick="saveGrabfoodSettings()">
                                <i class="fa fa-save"></i> Save Settings
                            </button>
                            <button id="btn-test-gf" class="btn btn-default" onclick="testGrabfoodConnection()">
                                <i class="fa fa-plug"></i> Test Connection
                            </button>
                            <a href="<?php echo admin_url('pos/grabfood_orders?store=' . (int)$warehouse_id); ?>" class="btn btn-default">
                                <i class="fa fa-list"></i> View Orders
                            </a>
                        </div>

                        <?php endif; ?>

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
        dataType:    'json',
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

// ── CFD ─────────────────────────────────────────────────────────────────────
var cfdWarehouseId = parseInt(document.getElementById('cfd-warehouse-id') ? document.getElementById('cfd-warehouse-id').value : 0) || 0;

function changeCfdStore(id) {
    window.location.href = ADMIN_URL + 'pos/settings/cfd?store=' + id;
}

function selectCfdType(type) {
    document.querySelectorAll('.cfd-type-card').forEach(function(el) {
        el.classList.remove('cfd-type-active');
    });
    var radio = document.querySelector('input[name="cfd_display_type"][value="' + type + '"]');
    if (radio) {
        radio.checked = true;
        radio.closest('.cfd-type-card').classList.add('cfd-type-active');
    }
    var durationRow = document.getElementById('cfd-slide-duration-row');
    if (durationRow) {
        durationRow.style.display = (type === 'video') ? 'none' : '';
    }
}

function saveCfdSettings() {
    if (!cfdWarehouseId) return;
    var btn = document.getElementById('btn-save-cfd');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

    var type = document.querySelector('input[name="cfd_display_type"]:checked');
    if (!type) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> Save Display Settings'; return; }

    $.post(ADMIN_URL + 'pos/ajax_save_cfd_settings', {
        warehouse_id:   cfdWarehouseId,
        display_type:   type.value,
        slide_duration: $('#cfd-slide-duration').val() || 5,
    }, function(resp) {
        if (resp.success) {
            btn.innerHTML = '<i class="fa fa-check"></i> Saved';
            setTimeout(function() { btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> Save Display Settings'; }, 2200);
        } else {
            alert(resp.message || 'Failed to save.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save"></i> Save Display Settings';
        }
    }, 'json').fail(function() {
        alert('Request failed. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save"></i> Save Display Settings';
    });
}

function uploadCfdMedia(input) {
    if (!input.files || !input.files.length || !cfdWarehouseId) return;
    var files = Array.from(input.files);

    files.forEach(function(file) {
        var fd = new FormData();
        fd.append('media', file);
        fd.append('warehouse_id', cfdWarehouseId);

        $.ajax({
            url:         ADMIN_URL + 'pos/ajax_upload_cfd_media',
            type:        'POST',
            data:        fd,
            processData: false,
            contentType: false,
            dataType:    'json',
            success: function(resp) {
                if (resp.success) {
                    appendCfdMediaRow(resp.id, resp.media_type, resp.url, file.name);
                } else {
                    alert(resp.message || 'Upload failed: ' + file.name);
                }
            },
            error: function() { alert('Upload failed: ' + file.name); }
        });
    });

    input.value = '';
}

function appendCfdMediaRow(id, mediaType, url, filename) {
    var empty = document.getElementById('cfd-empty-state');
    if (empty) {
        var tableHtml = '<table class="table table-hover" id="cfd-media-table" style="margin-bottom:0;">' +
            '<thead><tr><th style="width:32px;"></th><th style="width:70px;">Preview</th>' +
            '<th>File</th><th style="width:80px;" class="text-center">Type</th>' +
            '<th style="width:90px;" class="text-center">Actions</th></tr></thead>' +
            '<tbody id="cfd-media-tbody"></tbody></table>';
        document.getElementById('cfd-media-list').innerHTML = tableHtml;
        initCfdSortable();
    }

    var preview = mediaType === 'video'
        ? '<video src="' + url + '" style="width:60px;height:40px;object-fit:cover;border-radius:3px;" muted></video>'
        : '<img src="' + url + '" style="width:60px;height:40px;object-fit:cover;border-radius:3px;" alt="">';
    var badge = mediaType === 'video'
        ? '<span class="label label-info">Video</span>'
        : '<span class="label label-default">Image</span>';

    var basename = filename.split('/').pop().split('\\').pop();

    var tr = '<tr data-id="' + id + '">' +
        '<td class="cfd-drag-handle" style="cursor:move;color:#bbb;padding-top:18px;"><i class="fa fa-bars"></i></td>' +
        '<td>' + preview + '</td>' +
        '<td style="font-size:12px;word-break:break-all;vertical-align:middle;">' + basename + '</td>' +
        '<td class="text-center" style="vertical-align:middle;">' + badge + '</td>' +
        '<td class="text-center" style="vertical-align:middle;">' +
        '<a href="#" class="text-danger" onclick="deleteCfdMedia(event,' + id + ')" title="Remove"><i class="fa fa-trash"></i></a>' +
        '</td></tr>';

    $('#cfd-media-tbody').append(tr);
}

function deleteCfdMedia(e, id) {
    e.preventDefault();
    if (!confirm('Remove this media item?')) return;

    $.post(ADMIN_URL + 'pos/ajax_delete_cfd_media', { id: id, warehouse_id: cfdWarehouseId }, function(resp) {
        if (resp.success) {
            $('tr[data-id="' + id + '"]').remove();
            if ($('#cfd-media-tbody tr').length === 0) {
                document.getElementById('cfd-media-list').innerHTML =
                    '<div id="cfd-empty-state" class="text-center text-muted" style="padding:30px;border:2px dashed #ddd;border-radius:6px;">' +
                    '<i class="fa fa-photo" style="font-size:24px;display:block;margin-bottom:8px;"></i>' +
                    'No media added yet. Click <strong>Add Media</strong> to upload images or videos.</div>';
            }
        }
    }, 'json');
}

function saveCfdOrder() {
    var ids = [];
    $('#cfd-media-tbody tr').each(function() { ids.push($(this).data('id')); });
    $.post(ADMIN_URL + 'pos/ajax_reorder_cfd_media', { warehouse_id: cfdWarehouseId, ids: ids }, null, 'json');
}

function initCfdSortable() {
    var tbody = document.getElementById('cfd-media-tbody');
    if (!tbody || typeof Sortable === 'undefined') return;
    Sortable.create(tbody, { handle: '.cfd-drag-handle', animation: 150, onEnd: saveCfdOrder });
}

// ── Payment modes toggle ─────────────────────────────────────────────────────
function togglePaymentMode(id, checkbox) {
    checkbox.disabled = true;
    $.post(ADMIN_URL + 'pos/ajax_toggle_payment_mode', {
        payment_mode_id: id,
        enabled:         checkbox.checked ? 1 : 0
    }, function(resp) {
        checkbox.disabled = false;
        if (!resp.success) {
            checkbox.checked = !checkbox.checked;
            alert(resp.message || 'Failed to update payment mode.');
        }
    }, 'json').fail(function() {
        checkbox.disabled = false;
        checkbox.checked = !checkbox.checked;
        alert('Request failed. Please try again.');
    });
}

// Init sortable on page load if table exists
if (document.getElementById('cfd-media-tbody')) {
    if (typeof Sortable !== 'undefined') {
        initCfdSortable();
    } else {
        // Load SortableJS lazily
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
        s.onload = initCfdSortable;
        document.head.appendChild(s);
    }
}

// ── GrabFood Settings ────────────────────────────────────────────────────────
var gfWarehouseId = parseInt(document.getElementById('gf-warehouse-id') ? document.getElementById('gf-warehouse-id').value : 0) || 0;

function changeGfStore(id) {
    window.location.href = ADMIN_URL + 'pos/settings/grabfood?store=' + id;
}

function toggleSecret() {
    var f = document.getElementById('gf-client-secret');
    var i = document.getElementById('gf-secret-icon');
    if (!f) return;
    if (f.type === 'password') { f.type = 'text';     i.className = 'fa fa-eye-slash'; }
    else                        { f.type = 'password'; i.className = 'fa fa-eye'; }
}

function _gfPayload() {
    var env = document.querySelector('input[name="gf_environment"]:checked');
    return {
        warehouse_id:      gfWarehouseId,
        client_id:         (document.getElementById('gf-client-id')     || {}).value || '',
        client_secret:     (document.getElementById('gf-client-secret') || {}).value || '',
        partner_id:        (document.getElementById('gf-partner-id')    || {}).value || '',
        grabfood_store_id: (document.getElementById('gf-store-id')      || {}).value || '',
        environment:       env ? env.value : 'sandbox',
        active:            (document.getElementById('gf-active') || {}).checked ? 1 : 0,
    };
}

function saveGrabfoodSettings() {
    if (!gfWarehouseId) return;
    var btn = document.getElementById('btn-save-gf');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

    $.post(ADMIN_URL + 'pos/ajax_grabfood_save_settings', _gfPayload(), function(resp) {
        if (resp.success) {
            btn.innerHTML = '<i class="fa fa-check"></i> Saved';
            setTimeout(function() { btn.disabled = false; btn.innerHTML = '<i class="fa fa-save"></i> Save Settings'; }, 2200);
        } else {
            alert(resp.message || 'Failed to save settings.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-save"></i> Save Settings';
        }
    }, 'json').fail(function() {
        alert('Request failed. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-save"></i> Save Settings';
    });
}

function testGrabfoodConnection() {
    if (!gfWarehouseId) return;
    var btn = document.getElementById('btn-test-gf');
    var res = document.getElementById('gf-test-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing…';
    if (res) { res.style.display = 'none'; }

    $.post(ADMIN_URL + 'pos/ajax_grabfood_test_connection', _gfPayload(), function(resp) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-plug"></i> Test Connection';
        if (res) {
            res.className = 'alert ' + (resp.success ? 'alert-success' : 'alert-danger');
            res.innerHTML = resp.success ? ('<i class="fa fa-check"></i> ' + (resp.message || 'Connection successful!')) : ('<i class="fa fa-times"></i> ' + (resp.error || 'Connection failed.'));
            res.style.display = '';
        }
    }, 'json').fail(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-plug"></i> Test Connection';
        if (res) {
            res.className = 'alert alert-danger';
            res.innerHTML = '<i class="fa fa-times"></i> Request failed. Please try again.';
            res.style.display = '';
        }
    });
}
</script>
</body>
</html>
