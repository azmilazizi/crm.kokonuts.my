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
                            <small class="text-muted">
                                Supports .csv and .xlsx &mdash;
                                columns: <strong>name</strong>, <strong>phone</strong>, <strong>email</strong>,
                                <strong>birthday</strong>, <strong>address1</strong>, <strong>address2</strong>,
                                <strong>city</strong>, <strong>state</strong>, <strong>postcode</strong>,
                                <strong>total_spent</strong>, <strong>total_points</strong>,
                                <strong>total_transactions</strong>, <strong>last_purchase_date</strong>
                            </small>
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
                                    <th>Birthday</th>
                                    <th>Address</th>
                                    <th>City</th>
                                    <th>State</th>
                                    <th>Postcode</th>
                                    <th class="text-right">Total Spent</th>
                                    <th class="text-right">Points</th>
                                    <th class="text-right">Transactions</th>
                                    <th>Last Purchase</th>
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

    var elAlert    = document.getElementById('import-alert');
    var elProgress = document.getElementById('import-progress');
    var elBar      = document.getElementById('import-progress-bar');
    var elBarText  = document.getElementById('import-progress-text');
    var elWrap     = document.getElementById('import-preview-wrap');
    var elTbody    = document.getElementById('preview-tbody');
    var elSubmit   = document.getElementById('import-submit');
    var elCount    = document.getElementById('preview-count');
    var elZone     = document.getElementById('drop-zone');
    var elIcon     = document.getElementById('drop-icon');
    var elDropText = document.getElementById('drop-text');
    var elFile     = document.getElementById('import-file');

    function showAlert(msg, type) {
        elAlert.className = 'alert alert-' + type;
        elAlert.textContent = msg;
    }

    function clearAlert() {
        elAlert.className = 'alert hide';
        elAlert.textContent = '';
    }

    function normalize(v) {
        return String(v == null ? '' : v).replace(/^[﻿​]+/, '').trim().toLowerCase();
    }

    function findHeaders(rows) {
        var aliases = {
            'name': 'name', 'full name': 'name', 'member name': 'name',
            'phone': 'phone', 'phone number': 'phone', 'mobile': 'phone', 'mobile number': 'phone',
            'email': 'email', 'email address': 'email',
            'birthday': 'birthday', 'date of birth': 'birthday', 'dob': 'birthday',
            'address1': 'address1', 'address line 1': 'address1', 'address 1': 'address1',
            'address2': 'address2', 'address line 2': 'address2', 'address 2': 'address2',
            'city': 'city', 'town': 'city',
            'state': 'state', 'province': 'state',
            'postcode': 'postcode', 'postal code': 'postcode', 'zip': 'postcode', 'zip code': 'postcode',
            'total_spent': 'total_spent', 'total spent': 'total_spent', 'spent': 'total_spent',
            'total_points': 'total_points', 'total points': 'total_points', 'points': 'total_points',
            'total_transactions': 'total_transactions', 'total transactions': 'total_transactions', 'transactions': 'total_transactions',
            'last_purchase_date': 'last_purchase_date', 'last purchase date': 'last_purchase_date', 'last purchase': 'last_purchase_date', 'last visit': 'last_purchase_date'
        };

        for (var i = 0; i < Math.min(rows.length, 5); i++) {
            var map = {};
            (rows[i] || []).forEach(function (c, idx) {
                var k = normalize(c);
                var canonical = aliases[k];
                if (canonical) map[canonical] = idx;
            });
            if (map['name'] !== undefined || map['phone'] !== undefined) {
                return { index: i, map: map };
            }
        }
        return null;
    }

    function cell(row, map, key) {
        var idx = map[key];
        return idx !== undefined && row[idx] != null ? String(row[idx]).trim() : '';
    }

    function parseRows(rows) {
        parsedRows = [];
        var header = findHeaders(rows);
        if (!header) {
            showAlert('Could not find header row. Make sure your file has at least a "name" or "phone" column.', 'warning');
            return false;
        }
        var map = header.map;
        for (var i = header.index + 1; i < rows.length; i++) {
            var row = rows[i] || [];
            var name  = cell(row, map, 'name');
            var phone = cell(row, map, 'phone');
            if (name === '' && phone === '') continue;
            parsedRows.push({
                name:               name,
                phone:              phone,
                email:              cell(row, map, 'email'),
                birthday:           cell(row, map, 'birthday'),
                address1:           cell(row, map, 'address1'),
                address2:           cell(row, map, 'address2'),
                city:               cell(row, map, 'city'),
                state:              cell(row, map, 'state'),
                postcode:           cell(row, map, 'postcode'),
                total_spent:        parseFloat(cell(row, map, 'total_spent'))      || 0,
                points:             parseFloat(cell(row, map, 'total_points'))     || 0,
                total_transactions: parseInt(cell(row, map, 'total_transactions')) || 0,
                last_purchase_date: cell(row, map, 'last_purchase_date'),
                _status: 'new'
            });
        }
        if (!parsedRows.length) {
            showAlert('No data rows found after the header.', 'warning');
            return false;
        }
        return true;
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function renderPreview() {
        elTbody.innerHTML = '';
        parsedRows.forEach(function (r, i) {
            var badge = '<span class="status-badge ' + r._status + '">' +
                (r._status === 'new' ? 'New' : r._status === 'update' ? 'Update' : 'Error') + '</span>';
            var addr = [r.address1, r.address2].filter(Boolean).join(', ') || '—';
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td class="text-muted">' + (i + 1) + '</td>' +
                '<td>' + escHtml(r.name || '—') + '</td>' +
                '<td style="white-space:nowrap;">' + escHtml(r.phone || '—') + '</td>' +
                '<td class="text-muted">' + escHtml(r.email || '—') + '</td>' +
                '<td class="text-muted" style="white-space:nowrap;">' + escHtml(r.birthday || '—') + '</td>' +
                '<td class="text-muted">' + escHtml(addr) + '</td>' +
                '<td>' + escHtml(r.city || '—') + '</td>' +
                '<td>' + escHtml(r.state || '—') + '</td>' +
                '<td>' + escHtml(r.postcode || '—') + '</td>' +
                '<td class="text-right">' + (r.total_spent > 0 ? r.total_spent.toFixed(2) : '—') + '</td>' +
                '<td class="text-right">' + (r.points > 0 ? r.points.toFixed(2) : '—') + '</td>' +
                '<td class="text-right">' + (r.total_transactions > 0 ? r.total_transactions : '—') + '</td>' +
                '<td class="text-muted" style="white-space:nowrap;">' + escHtml(r.last_purchase_date || '—') + '</td>' +
                '<td>' + badge + '</td>';
            elTbody.appendChild(tr);
        });
        elCount.textContent = parsedRows.length + ' row(s) ready to import';
        elWrap.classList.remove('hide');
        elSubmit.disabled = parsedRows.length === 0;
    }

    function processFile(file) {
        clearAlert();
        elWrap.classList.add('hide');
        elSubmit.disabled = true;
        elIcon.className = 'fa fa-spinner fa-spin';
        elDropText.textContent = 'Parsing ' + file.name + '…';

        var ext = file.name.split('.').pop().toLowerCase();

        if (ext === 'csv') {
            var reader = new FileReader();
            reader.onload = function (e) {
                var text = e.target.result;
                var rows = text.split(/\r?\n/).map(function (line) {
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
            elIcon.className = 'fa fa-check-circle';
            elIcon.style.color = '#5cb85c';
            elDropText.textContent = fname;
            elZone.classList.add('has-file');
            renderPreview();
            clearAlert();
        } else {
            resetZone();
        }
    }

    function resetZone() {
        elIcon.className = 'fa fa-cloud-upload';
        elIcon.style.color = '';
        elDropText.textContent = 'Click to select a file, or drag and drop here';
        elZone.classList.remove('has-file');
    }

    // File input change — vanilla, no jQuery needed
    elFile.addEventListener('change', function () {
        if (this.files[0]) processFile(this.files[0]);
        this.value = '';
    });

    // Drag and drop
    elZone.addEventListener('dragover',  function (e) { e.preventDefault(); elZone.classList.add('drag-over'); });
    elZone.addEventListener('dragenter', function (e) { e.preventDefault(); elZone.classList.add('drag-over'); });
    elZone.addEventListener('dragleave', function (e) { e.preventDefault(); elZone.classList.remove('drag-over'); });
    elZone.addEventListener('drop', function (e) {
        e.preventDefault();
        elZone.classList.remove('drag-over');
        var file = e.dataTransfer.files[0];
        if (file) processFile(file);
    });

    // Submit — XMLHttpRequest so no jQuery dependency
    var importing = false;
    elSubmit.addEventListener('click', function () {
        if (importing || !parsedRows.length) return;
        importing = true;
        elSubmit.disabled = true;
        clearAlert();
        elProgress.classList.remove('hide');
        elBar.style.width = '0%';
        elBar.textContent = '0%';

        var summary = { created: 0, updated: 0, errors: [] };
        var total = parsedRows.length;

        function sendBatch(start) {
            var batch = parsedRows.slice(start, start + batchSize);
            if (!batch.length) { finish(); return; }

            var pct = Math.round((start / total) * 100);
            elBar.style.width = pct + '%';
            elBar.textContent = pct + '%';
            elBarText.textContent = 'Processing rows ' + (start + 1) + '–' + Math.min(start + batchSize, total) + ' of ' + total + '…';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', submitUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) return;
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        summary.created += res.created || 0;
                        summary.updated += res.updated || 0;
                        if (res.errors && res.errors.length) {
                            summary.errors = summary.errors.concat(res.errors);
                        }
                    } catch (e) {}
                    sendBatch(start + batchSize);
                } else {
                    importing = false;
                    elSubmit.disabled = false;
                    elBar.classList.remove('active');
                    showAlert('Server error during import. Please try again.', 'danger');
                }
            };
            // Build POST body — append CSRF token if the CRM has set it up
            var body = 'rows=' + encodeURIComponent(JSON.stringify(batch));
            if (window.csrfData && window.csrfData.formatted) {
                for (var k in window.csrfData.formatted) {
                    if (window.csrfData.formatted.hasOwnProperty(k)) {
                        body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(window.csrfData.formatted[k]);
                    }
                }
            }
            xhr.send(body);
        }

        function finish() {
            importing = false;
            elSubmit.disabled = false;
            elBar.classList.remove('active');
            elBar.style.width = '100%';
            elBar.textContent = '100%';

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
