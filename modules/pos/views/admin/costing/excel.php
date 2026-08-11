<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s">
                    <div class="panel-body">

                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                                <p class="text-muted small">Bulk-update product costs via Excel. Download the standard template, fill it in, then upload to apply changes.</p>
                            </div>
                        </div>
                        <hr />

                        <div class="row">
                            <div class="col-md-6">
                                <div class="panel_s" style="border: 2px solid #d9e2ef; border-radius: 6px;">
                                    <div class="panel-body">
                                        <h5 class="no-margin-top text-primary" style="font-weight:600;">
                                            <i class="fa fa-file-excel-o"></i> Export Costing Template
                                        </h5>
                                        <p class="small text-muted">Download a pre-built XLSX template with all current products, SKUs, existing costs, and columns pre-mapped for editing.</p>
                                        <div class="text-center mtop20 mbot20">
                                            <a href="<?php echo admin_url('pos/costing_download_template'); ?>" class="btn btn-primary btn-lg" style="width:100%; font-weight:600;">
                                                <i class="fa fa-download"></i> Download Template (XLSX)
                                            </a>
                                        </div>
                                        <ul class="small text-muted">
                                            <li>Includes all active products, combos, and mixed ingredients</li>
                                            <li>Columns: ID, SKU, Name, Purchase Price, Batch Size, Units/Batch, UOM</li>
                                            <li>Update only columns you need; other columns are ignored</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="panel_s" style="border: 2px solid #d9e2ef; border-radius: 6px;">
                                    <div class="panel-body">
                                        <h5 class="no-margin-top text-info" style="font-weight:600;">
                                            <i class="fa fa-upload"></i> Import from Cost_Profit.xlsx
                                        </h5>
                                        <p class="small text-muted">Upload a filled-in template. Changes are validated first and then applied to matching items.</p>

                                        <div class="mtop20 mbot20">
                                            <p class="small no-margin-bottom"><strong>Step 1</strong> — Choose the filled XLSX file and upload for preview:</p>
                                            <input type="file" id="xlsx-file" accept=".xlsx,.xls" class="form-control mtop5 mbot15">
                                            <button class="btn btn-info btn-block mtop5 mbot15" id="upload-btn" onclick="startUpload()">
                                                <i class="fa fa-upload"></i> Upload &amp; Preview
                                            </button>
                                            <p class="small no-margin-bottom"><strong>Step 2</strong> — Review preview, then apply import:</p>
                                            <button class="btn btn-success btn-block mtop5 mbot15" id="apply-btn" onclick="applyImport()" disabled>
                                                <i class="fa fa-check-circle"></i> Apply Import
                                            </button>
                                        </div>

                                        <div id="upload-progress" style="display:none;">
                                            <div class="progress" style="margin-bottom:4px;">
                                                <div id="upload-progress-bar" class="progress-bar progress-bar-info progress-bar-striped active" style="width:0%"></div>
                                            </div>
                                            <p id="upload-progress-text" class="small text-muted text-center mbot15">Uploading...</p>
                                        </div>

                                        <div id="preview-section" style="display:none;">
                                            <div class="panel panel-default mbot15">
                                                <div class="panel-heading">
                                                    <strong><i class="fa fa-eye"></i> Preview — First 5 rows of each detected sheet</strong>
                                                </div>
                                                <div class="panel-body" id="preview-content" style="max-height:300px; overflow-y:auto;">
                                                </div>
                                            </div>
                                        </div>

                                        <div id="upload-result" style="display:none; padding: 10px 12px; border-radius: 4px;"></div>

                                        <div id="apply-progress" style="display:none;">
                                            <div class="progress" style="margin-bottom:4px;">
                                                <div id="apply-progress-bar" class="progress-bar progress-bar-success progress-bar-striped active" style="width:0%"></div>
                                            </div>
                                            <p id="apply-progress-text" class="small text-muted text-center mbot15">Applying import...</p>
                                        </div>

                                        <div id="apply-result" style="display:none; padding: 10px 12px; border-radius: 4px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var uploadUrl = '<?php echo admin_url('pos/costing_upload_excel'); ?>';
var applyUrl  = '<?php echo admin_url('pos/costing_apply_import'); ?>';

function resetUI() {
    document.getElementById('apply-result').style.display = 'none';
    document.getElementById('apply-result').className = '';
    document.getElementById('apply-result').innerHTML = '';
    document.getElementById('preview-section').style.display = 'none';
    document.getElementById('preview-content').innerHTML = '';
    document.getElementById('apply-btn').disabled = true;
}

function startUpload() {
    var fileEl = document.getElementById('xlsx-file');
    var file = fileEl.files[0];
    var resultEl = document.getElementById('upload-result');
    var progressWrap = document.getElementById('upload-progress');
    var progressBar = document.getElementById('upload-progress-bar');
    var progressText = document.getElementById('upload-progress-text');
    var btn = document.getElementById('upload-btn');

    resetUI();

    resultEl.style.display = 'none';
    resultEl.className = '';
    resultEl.innerHTML = '';

    if (!file) {
        resultEl.style.display = 'block';
        resultEl.className = 'alert alert-warning';
        resultEl.textContent = 'Please choose an XLSX file first.';
        return;
    }
    var ext = (file.name.split('.').pop() || '').toLowerCase();
    if (ext !== 'xlsx' && ext !== 'xls') {
        resultEl.style.display = 'block';
        resultEl.className = 'alert alert-warning';
        resultEl.textContent = 'Only .xlsx or .xls files are supported.';
        return;
    }

    progressWrap.style.display = 'block';
    progressBar.style.width = '5%';
    progressText.textContent = 'Preparing upload...';
    btn.disabled = true;
    var btnOrig = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading...';

    var fd = new FormData();
    fd.append('file_xlsx', file);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', uploadUrl, true);
    xhr.upload.onprogress = function (e) {
        if (e.lengthComputable) {
            var pct = Math.max(5, Math.round((e.loaded / e.total) * 80));
            progressBar.style.width = pct + '%';
            progressText.textContent = 'Uploading ' + pct + '%...';
        }
    };
    xhr.onload = function () {
        progressBar.style.width = '95%';
        progressText.textContent = 'Processing...';
        try {
            var res = JSON.parse(xhr.responseText);
            progressBar.style.width = '100%';
            setTimeout(function () {
                progressWrap.style.display = 'none';
                progressBar.style.width = '0%';
                btn.disabled = false;
                btn.innerHTML = btnOrig;
                if (res && res.success) {
                    resultEl.style.display = 'block';
                    resultEl.className = 'alert alert-success';
                    resultEl.innerHTML = '<strong><i class="fa fa-check"></i> Upload successful.</strong> Review the preview below, then click <strong>Apply Import</strong>.';

                    if (res.preview && typeof res.preview === 'object') {
                        var html = '';
                        var sheetOrder = ['Ingredients', 'Packaging', 'Mixed Ingredients Summary', 'Products Summary', 'BOM - Mixed Ingredients', 'BOM - Products'];
                        for (var s = 0; s < sheetOrder.length; s++) {
                            var sheetName = sheetOrder[s];
                            var rows = res.preview[sheetName] || [];
                            html += '<div class="mbot15">';
                            html += '<h6 style="font-weight:600; margin-bottom:5px;">' + sheetName + ' <span class="text-muted small">(' + (rows.length ? rows.length + ' row(s) previewed' : 'not found / empty') + ')</span></h6>';
                            if (rows && rows.length) {
                                html += '<div class="table-responsive"><table class="table table-bordered table-condensed small" style="margin-bottom:0;">';
                                for (var r = 0; r < rows.length; r++) {
                                    html += '<tr>';
                                    var row = rows[r] || [];
                                    var limit = Math.min(row.length, 10);
                                    for (var c = 0; c < limit; c++) {
                                        var cell = row[c] == null ? '' : ('' + row[c]);
                                        cell = cell.replace(/</g, '&lt;').replace(/>/g, '&gt;');
                                        if (r === 0) {
                                            html += '<th style="background:#f5f7fa;">' + cell + '</th>';
                                        } else {
                                            html += '<td>' + cell + '</td>';
                                        }
                                    }
                                    if (row.length > limit && r === 0) {
                                        html += '<th style="background:#f5f7fa;">…+' + (row.length - limit) + ' more cols</th>';
                                    } else if (row.length > limit) {
                                        html += '<td class="text-muted">…</td>';
                                    }
                                    html += '</tr>';
                                }
                                html += '</table></div>';
                            }
                            html += '</div>';
                        }
                        document.getElementById('preview-content').innerHTML = html;
                        document.getElementById('preview-section').style.display = 'block';
                    }
                    document.getElementById('apply-btn').disabled = false;
                } else {
                    resultEl.style.display = 'block';
                    resultEl.className = 'alert alert-danger';
                    resultEl.innerHTML = '<strong><i class="fa fa-exclamation-triangle"></i> Upload failed.</strong><div class="small mtop5">' +
                        ((res && res.message) ? ('' + res.message).replace(/</g, '&lt;').replace(/>/g, '&gt;') : 'Unknown server response') +
                        '</div>';
                }
            }, 250);
        } catch (e) {
            progressWrap.style.display = 'none';
            btn.disabled = false;
            btn.innerHTML = btnOrig;
            resultEl.style.display = 'block';
            resultEl.className = 'alert alert-danger';
            resultEl.textContent = 'Could not parse server response. Raw: ' + (xhr.responseText || '').slice(0, 200);
        }
    };
    xhr.onerror = function () {
        progressWrap.style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = btnOrig;
        resultEl.style.display = 'block';
        resultEl.className = 'alert alert-danger';
        resultEl.textContent = 'Network error during upload. Please try again.';
    };
    xhr.send(fd);
}

function applyImport() {
    if (!confirm('Apply the import now? This will update costing data and create a snapshot.')) {
        return;
    }

    var resultEl = document.getElementById('apply-result');
    var progressWrap = document.getElementById('apply-progress');
    var progressBar = document.getElementById('apply-progress-bar');
    var progressText = document.getElementById('apply-progress-text');
    var applyBtn = document.getElementById('apply-btn');
    var uploadBtn = document.getElementById('upload-btn');

    resultEl.style.display = 'none';
    resultEl.className = '';
    resultEl.innerHTML = '';

    progressWrap.style.display = 'block';
    progressBar.style.width = '10%';
    progressText.textContent = 'Applying import...';
    applyBtn.disabled = true;
    uploadBtn.disabled = true;
    var applyBtnOrig = applyBtn.innerHTML;
    applyBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Applying...';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', applyUrl, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function () {
        progressBar.style.width = '90%';
        try {
            var res = JSON.parse(xhr.responseText);
            progressBar.style.width = '100%';
            setTimeout(function () {
                progressWrap.style.display = 'none';
                progressBar.style.width = '0%';
                applyBtn.disabled = false;
                uploadBtn.disabled = false;
                applyBtn.innerHTML = applyBtnOrig;

                if (res && res.success) {
                    resultEl.style.display = 'block';
                    resultEl.className = 'alert alert-success';
                    var html = '<h5 style="margin-top:0; margin-bottom:10px;"><i class="fa fa-check-circle"></i> Import applied successfully</h5>';

                    if (res.stats && typeof res.stats === 'object') {
                        html += '<div class="mbot10"><strong>Per-sheet results:</strong><ul class="mbot0 mtop5" style="padding-left:20px;">';
                        var sheetKeys = Object.keys(res.stats);
                        for (var sk = 0; sk < sheetKeys.length; sk++) {
                            var skName = sheetKeys[sk];
                            var skVal = res.stats[skName] || {};
                            var created = skVal.created != null ? skVal.created : 0;
                            var updated = skVal.updated != null ? skVal.updated : 0;
                            var processed = skVal.processed != null ? skVal.processed : null;
                            var rows = skVal.rows != null ? skVal.rows : null;
                            var sheetProcessed = skVal.sheets_processed != null ? skVal.sheets_processed : null;
                            var parts = [];
                            if (created || updated) {
                                parts.push('created=' + created + ', updated=' + updated);
                            }
                            if (processed != null) parts.push('processed=' + processed);
                            if (rows != null) parts.push('rows=' + rows);
                            if (sheetProcessed != null) parts.push('sheets_processed=' + sheetProcessed);
                            if (skVal.errors && Array.isArray(skVal.errors) && skVal.errors.length) {
                                parts.push('errors=' + skVal.errors.length);
                            }
                            html += '<li><strong>' + skName + ':</strong> ' + (parts.length ? parts.join(', ') : 'n/a') + '</li>';
                        }
                        html += '</ul></div>';
                    }

                    if (res.snapshot_id) {
                        html += '<div class="mbot10"><strong>Snapshot created:</strong> ID #' + res.snapshot_id + (res.snapshot_name ? ' — ' + res.snapshot_name : '') + '</div>';
                    }

                    if (res.recalc && typeof res.recalc === 'object') {
                        html += '<div class="mbot10"><strong>Recalc summary:</strong> ';
                        var rc = [];
                        if (res.recalc.raw_count != null) rc.push('raw=' + res.recalc.raw_count);
                        if (res.recalc.mixed_count != null) rc.push('mixed=' + res.recalc.mixed_count);
                        if (res.recalc.product_count != null) rc.push('products=' + res.recalc.product_count);
                        if (res.recalc.combo_count != null) rc.push('combos=' + res.recalc.combo_count);
                        html += rc.join(', ') || 'n/a';
                        html += '</div>';
                    }

                    resultEl.innerHTML = html;
                    document.getElementById('apply-btn').disabled = true;
                } else {
                    resultEl.style.display = 'block';
                    resultEl.className = 'alert alert-danger';
                    resultEl.innerHTML = '<strong><i class="fa fa-exclamation-triangle"></i> Apply import failed.</strong><div class="small mtop5">' +
                        ((res && res.message) ? ('' + res.message).replace(/</g, '&lt;').replace(/>/g, '&gt;') : 'Unknown server response') +
                        '</div>';
                }
            }, 250);
        } catch (e) {
            progressWrap.style.display = 'none';
            applyBtn.disabled = false;
            uploadBtn.disabled = false;
            applyBtn.innerHTML = applyBtnOrig;
            resultEl.style.display = 'block';
            resultEl.className = 'alert alert-danger';
            resultEl.textContent = 'Could not parse server response. Raw: ' + (xhr.responseText || '').slice(0, 200);
        }
    };
    xhr.onerror = function () {
        progressWrap.style.display = 'none';
        applyBtn.disabled = false;
        uploadBtn.disabled = false;
        applyBtn.innerHTML = applyBtnOrig;
        resultEl.style.display = 'block';
        resultEl.className = 'alert alert-danger';
        resultEl.textContent = 'Network error during apply. Please try again.';
    };
    xhr.send();
}
</script>
