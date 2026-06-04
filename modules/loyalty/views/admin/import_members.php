<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.import-dropzone {
    border: 2px dashed #ccc;
    border-radius: 6px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: #fafafa;
}
.import-dropzone:hover, .import-dropzone.drag-over {
    border-color: #337ab7;
    background: #f0f6ff;
}
.import-dropzone i { font-size: 36px; color: #ccc; display: block; margin-bottom: 10px; }
.import-dropzone.has-file i { color: #5cb85c; }
.import-dropzone.has-file p  { color: #5cb85c; font-weight: 600; }

.status-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.status-badge.new     { background: #dff0d8; color: #3c763d; }
.status-badge.update  { background: #d9edf7; color: #31708f; }
.status-badge.error   { background: #f2dede; color: #a94442; }

#import-preview-wrap { overflow-x: auto; }
</style>

<div id="wrapper">
<div class="content">

    <div class="row" style="margin-bottom: 16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom: 4px;">Import Members</h4>
            <ol class="breadcrumb" style="margin: 0; padding: 0; background: none; font-size: 12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">Import Members</li>
            </ol>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel_s">
                <div class="panel-body">

                    <!-- Template Download -->
                    <div class="alert alert-info" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <strong>Download the template first</strong>
                            <p class="no-margin text-muted" style="font-size: 13px; margin-top: 2px;">
                                Fill in the CSV/Excel template with member details, then upload it here.
                                Existing members matched by phone will be updated, not duplicated.
                            </p>
                        </div>
                        <a href="<?php echo admin_url('loyalty/import_members_template'); ?>" class="btn btn-default" download>
                            <i class="fa fa-download"></i> Download Template (.csv)
                        </a>
                    </div>

                    <!-- File Upload Zone -->
                    <div class="form-group" style="margin-top: 20px;">
                        <label>Upload File <small class="text-muted">(.csv or .xlsx)</small></label>
                        <div class="import-dropzone" id="drop-zone" onclick="document.getElementById('import-file').click()">
                            <i class="fa fa-cloud-upload" id="drop-icon"></i>
                            <p id="drop-text">Click to select a file, or drag and drop here</p>
                            <small class="text-muted">Supports .csv and .xlsx — columns: <strong>name</strong>, <strong>phone</strong>, <strong>email</strong>, <strong>points</strong></small>
                        </div>
                        <input type="file" id="import-file" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" style="display: none;">
                    </div>

                    <!-- Alert -->
                    <div id="import-alert" class="alert hide"></div>

                    <!-- Progress -->
                    <div id="import-progress" class="hide" style="margin-bottom: 16px;">
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped active" id="import-progress-bar"
                                 role="progressbar" style="width: 0%;">0%</div>
                        </div>
                        <p class="text-muted" id="import-progress-text" style="font-size: 12px; margin-top: 4px;"></p>
                    </div>

                    <!-- Preview Table -->
                    <div id="import-preview-wrap" class="hide">
                        <div class="row" style="margin-bottom: 8px;">
                            <div class="col-sm-6">
                                <strong id="preview-count"></strong>
                            </div>
                            <div class="col-sm-6 text-right">
                                <button type="button" class="btn btn-primary" id="import-submit" disabled>
                                    <i class="fa fa-upload"></i> Import Members
                                </button>
                            </div>
                        </div>
                        <table class="table table-bordered table-hover" id="preview-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th class="text-right">Points</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="preview-tbody"></tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function () {
    var parsedRows = [];
    var submitUrl  = '<?php echo admin_url('loyalty/import_members_submit'); ?>';
    var batchSize  = 50;

    var $alert    = $('#import-alert');
    var $progress = $('#import-progress');
    var $bar      = $('#import-progress-bar');
    var $barText  = $('#import-progress-text');
    var $wrap     = $('#import-preview-wrap');
    var $tbody    = $('#preview-tbody');
    var $submit   = $('#import-submit');
    var $count    = $('#preview-count');
    var $zone     = $('#drop-zone');
    var $icon     = $('#drop-icon');
    var $dropText = $('#drop-text');

    function showAlert(msg, type) {
        $alert.removeClass('alert-info alert-success alert-warning alert-danger')
              .addClass('alert-' + type).text(msg).removeClass('hide');
    }

    function clearAlert() { $alert.addClass('hide').text(''); }

    function normalize(v) { return String(v == null ? '' : v).trim().toLowerCase(); }

    function findHeaders(rows) {
        var required = ['name', 'phone', 'email', 'points'];
        for (var i = 0; i < Math.min(rows.length, 5); i++) {
            var map = {};
            (rows[i] || []).forEach(function (cell, idx) {
                var k = normalize(cell);
                if (k !== '') map[k] = idx;
            });
            if (required.every(function (h) { return map[h] !== undefined; })) {
                return { index: i, map: map };
            }
        }
        // Fallback: accept partial — at least name or phone
        for (var i = 0; i < Math.min(rows.length, 5); i++) {
            var map = {};
            (rows[i] || []).forEach(function (cell, idx) {
                var k = normalize(cell);
                if (k !== '') map[k] = idx;
            });
            if (map['name'] !== undefined || map['phone'] !== undefined) {
                return { index: i, map: map };
            }
        }
        return null;
    }

    function parseRows(rows) {
        parsedRows = [];
        var header = findHeaders(rows);
        if (!header) {
            showAlert('Could not find header row. Make sure your file has columns: name, phone, email, points.', 'warning');
            return false;
        }
        var map = header.map;
        for (var i = header.index + 1; i < rows.length; i++) {
            var row = rows[i] || [];
            var name   = String(row[map['name']]   != null ? row[map['name']]   : '').trim();
            var phone  = String(row[map['phone']]  != null ? row[map['phone']]  : '').trim();
            var email  = String(row[map['email']]  != null ? row[map['email']]  : '').trim();
            var points = parseFloat(row[map['points']]) || 0;

            if (name === '' && phone === '') continue;

            var status = 'new';
            if (name === '' && phone === '') status = 'error';

            parsedRows.push({ name: name, phone: phone, email: email, points: points, _status: status });
        }

        if (!parsedRows.length) {
            showAlert('No data rows found after the header.', 'warning');
            return false;
        }
        return true;
    }

    function renderPreview() {
        $tbody.empty();
        parsedRows.forEach(function (r, i) {
            var badge = '<span class="status-badge ' + r._status + '">' +
                (r._status === 'new' ? 'New' : r._status === 'update' ? 'Update' : 'Error') + '</span>';
            $tbody.append(
                '<tr>' +
                '<td class="text-muted">' + (i + 1) + '</td>' +
                '<td>' + escHtml(r.name || '—') + '</td>' +
                '<td>' + escHtml(r.phone || '—') + '</td>' +
                '<td class="text-muted">' + escHtml(r.email || '—') + '</td>' +
                '<td class="text-right">' + (r.points > 0 ? r.points.toFixed(2) : '—') + '</td>' +
                '<td>' + badge + '</td>' +
                '</tr>'
            );
        });
        $count.text(parsedRows.length + ' row(s) ready to import');
        $wrap.removeClass('hide');
        $submit.prop('disabled', parsedRows.length === 0);
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function processFile(file) {
        clearAlert();
        $wrap.addClass('hide');
        $submit.prop('disabled', true);

        $icon.removeClass().addClass('fa fa-spinner fa-spin');
        $dropText.text('Parsing ' + file.name + '…');

        var ext = file.name.split('.').pop().toLowerCase();

        if (ext === 'csv') {
            var reader = new FileReader();
            reader.onload = function (e) {
                var text = e.target.result;
                var rows = text.split(/\r?\n/).map(function (line) {
                    // Simple CSV split (handles quoted fields)
                    var result = [], cur = '', inQuote = false;
                    for (var c = 0; c < line.length; c++) {
                        var ch = line[c];
                        if (ch === '"') { inQuote = !inQuote; }
                        else if (ch === ',' && !inQuote) { result.push(cur); cur = ''; }
                        else { cur += ch; }
                    }
                    result.push(cur);
                    return result;
                });
                finishParse(rows, file.name);
            };
            reader.readAsText(file, 'UTF-8');
        } else {
            var reader = new FileReader();
            reader.onload = function (e) {
                try {
                    var wb = XLSX.read(new Uint8Array(e.target.result), { type: 'array' });
                    var ws = wb.Sheets[wb.SheetNames[0]];
                    var rows = XLSX.utils.sheet_to_json(ws, { header: 1, raw: false, defval: '' });
                    finishParse(rows, file.name);
                } catch (err) {
                    showAlert('Unable to read file. Please check the format.', 'danger');
                    resetZone();
                }
            };
            reader.readAsArrayBuffer(file);
        }
    }

    function finishParse(rows, fname) {
        var ok = parseRows(rows);
        if (ok) {
            $icon.removeClass().addClass('fa fa-check-circle');
            $icon.css('color', '#5cb85c');
            $dropText.text(fname);
            $zone.addClass('has-file');
            renderPreview();
            clearAlert();
        } else {
            resetZone();
        }
    }

    function resetZone() {
        $icon.removeClass().addClass('fa fa-cloud-upload').css('color', '');
        $dropText.text('Click to select a file, or drag and drop here');
        $zone.removeClass('has-file');
    }

    // File input change
    $('#import-file').on('change', function () {
        if (this.files[0]) processFile(this.files[0]);
        this.value = '';
    });

    // Drag and drop
    $zone.on('dragover dragenter', function (e) {
        e.preventDefault();
        $zone.addClass('drag-over');
    }).on('dragleave drop', function (e) {
        e.preventDefault();
        $zone.removeClass('drag-over');
        if (e.type === 'drop') {
            var file = e.originalEvent.dataTransfer.files[0];
            if (file) processFile(file);
        }
    });

    // Submit
    var importing = false;
    $submit.on('click', function () {
        if (importing || !parsedRows.length) return;
        importing = true;
        $submit.prop('disabled', true);
        clearAlert();
        $progress.removeClass('hide');
        $bar.addClass('active').css('width', '0%').text('0%');

        var summary = { created: 0, updated: 0, errors: [] };
        var total = parsedRows.length;

        function sendBatch(start) {
            var batch = parsedRows.slice(start, start + batchSize);
            if (!batch.length) {
                finish();
                return;
            }

            var pct = Math.round((start / total) * 100);
            $bar.css('width', pct + '%').text(pct + '%');
            $barText.text('Processing rows ' + (start + 1) + '–' + Math.min(start + batchSize, total) + ' of ' + total + '…');

            $.ajax({
                url: submitUrl,
                method: 'POST',
                dataType: 'json',
                data: { rows: JSON.stringify(batch) }
            }).done(function (res) {
                if (res) {
                    summary.created += res.created || 0;
                    summary.updated += res.updated || 0;
                    if (res.errors && res.errors.length) {
                        summary.errors = summary.errors.concat(res.errors);
                    }
                }
                sendBatch(start + batchSize);
            }).fail(function () {
                importing = false;
                $submit.prop('disabled', false);
                $bar.removeClass('active');
                showAlert('Server error during import. Please try again.', 'danger');
            });
        }

        function finish() {
            importing = false;
            $submit.prop('disabled', false);
            $bar.removeClass('active').css('width', '100%').text('100%');

            var msg = 'Import complete: ' + summary.created + ' created, ' + summary.updated + ' updated.';
            if (summary.errors.length) {
                msg += ' ' + summary.errors.length + ' error(s) — see browser console.';
                console.warn('Import errors:', summary.errors);
                showAlert(msg, 'warning');
            } else {
                showAlert(msg, 'success');
            }
        }

        sendBatch(0);
    });

}());
</script>

<?php init_tail(); ?>
