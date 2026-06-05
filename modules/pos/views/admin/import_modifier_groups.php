<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">

                <!-- Page header -->
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('pos/modifiers'); ?>" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> Back to Modifiers
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><i class="fa fa-info-circle text-info"></i> Instructions</h4>
                        <ol class="mtop10">
                            <li>Download the <a href="<?php echo admin_url('pos/download_modifier_groups_sample'); ?>"><strong>Excel template</strong></a> and fill in your modifier groups.</li>
                            <li><strong>Group Name</strong> and <strong>Selection Type</strong> (<code>single</code> or <code>multiple</code>) are required.</li>
                            <li>Each row represents one modifier group. Add up to 10 options per group using the Option columns.</li>
                            <li>Leave unused option columns blank.</li>
                            <li>Price adjustments can be negative (discount) or positive (surcharge).</li>
                            <li>To link items, enter up to 5 item SKU codes in the <strong>Linked Item SKU</strong> columns. SKUs that don't exist will be skipped with a warning.</li>
                        </ol>

                        <div class="table-responsive no-dt mtop15">
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr style="background:#1e88e5; color:#fff;">
                                        <th><span class="text-danger">*</span> Group Name</th>
                                        <th><span class="text-danger">*</span> Selection Type</th>
                                        <th>Min Selections</th>
                                        <th>Max Selections</th>
                                        <th>Option 1 Name</th>
                                        <th>Option 1 Price</th>
                                        <th class="text-muted">...</th>
                                        <th>Linked Item SKU 1</th>
                                        <th>Linked Item SKU 2</th>
                                        <th class="text-muted">...</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Sugar Level</td>
                                        <td>single</td>
                                        <td>0</td>
                                        <td>1</td>
                                        <td>No Sugar</td>
                                        <td>0.00</td>
                                        <td class="text-muted">...</td>
                                        <td>ITEM-001</td>
                                        <td>ITEM-002</td>
                                        <td class="text-muted">...</td>
                                    </tr>
                                    <tr>
                                        <td>Add-ons</td>
                                        <td>multiple</td>
                                        <td>0</td>
                                        <td>3</td>
                                        <td>Cheese</td>
                                        <td>1.50</td>
                                        <td class="text-muted">...</td>
                                        <td>ITEM-001</td>
                                        <td></td>
                                        <td class="text-muted">...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <a href="<?php echo admin_url('pos/download_modifier_groups_sample'); ?>" class="btn btn-success mtop10">
                            <i class="fa fa-download"></i> Download Excel Template
                        </a>
                    </div>
                </div>

                <!-- Upload form -->
                <div class="panel_s">
                    <div class="panel-body">
                        <h4>Upload File</h4>

                        <?php echo form_open_multipart('#', ['id' => 'import-form']); ?>

                        <div class="form-group">
                            <label for="file_xlsx">Excel File (.xlsx) <span class="text-danger">*</span></label>
                            <input type="file" name="file_xlsx" id="file_xlsx" accept=".xlsx" required>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="update_existing" id="update_existing" value="1">
                                &nbsp;Update existing modifier groups if the group name already exists
                            </label>
                            <p class="text-muted small">When checked, any existing group with the same name will have its options replaced. When unchecked, duplicate names are skipped.</p>
                        </div>

                        <button type="submit" class="btn btn-primary" id="btn-import">
                            <i class="fa fa-upload"></i> Import
                        </button>

                        <?php echo form_close(); ?>
                    </div>
                </div>

                <!-- Results -->
                <div id="import-result" style="display:none;">
                    <div class="panel_s">
                        <div class="panel-body">
                            <h4>Import Results</h4>
                            <div id="result-body"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

$('#import-form').on('submit', function (e) {
    e.preventDefault();

    var file = $('#file_xlsx')[0].files[0];
    if (!file) {
        alert('Please select an .xlsx file.');
        return;
    }
    if (!file.name.toLowerCase().endsWith('.xlsx')) {
        alert('Only .xlsx files are supported.');
        return;
    }

    var formData = new FormData();
    formData.append('file_xlsx', file);
    formData.append('update_existing', $('#update_existing').is(':checked') ? 1 : 0);

    var btn = $('#btn-import').prop('disabled', true).text('Importing...');

    $.ajax({
        url: ADMIN_URL + 'pos/import_file_modifier_groups',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (resp) {
            btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
            showResult(resp);
        },
        error: function () {
            btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
            alert('An unexpected error occurred. Please try again.');
        }
    });
});

function showResult(resp) {
    var html = '';

    if (!resp.success && !resp.total) {
        html = '<div class="alert alert-danger">' + (resp.message || 'Import failed.') + '</div>';
    } else {
        var alertClass = resp.failed === 0 ? 'alert-success' : (resp.saved > 0 ? 'alert-warning' : 'alert-danger');
        html += '<div class="alert ' + alertClass + '">';
        html += '<strong>Total rows processed:</strong> ' + resp.total + '<br>';
        html += '<strong>Successfully imported:</strong> ' + resp.saved + '<br>';
        html += '<strong>Failed / Skipped:</strong> ' + resp.failed;
        html += '</div>';

        if (resp.errors && resp.errors.length > 0) {
            html += '<div class="alert alert-warning">';
            html += '<strong>Issues:</strong><ul class="mtop5">';
            $.each(resp.errors, function (i, msg) {
                html += '<li>' + $('<div>').text(msg).html() + '</li>';
            });
            html += '</ul></div>';
        }

        if (resp.saved > 0) {
            html += '<a href="' + ADMIN_URL + 'pos/modifiers" class="btn btn-info">';
            html += '<i class="fa fa-list"></i> View Modifier Groups</a>';
        }
    }

    $('#result-body').html(html);
    $('#import-result').show();
    $('html, body').animate({ scrollTop: $('#import-result').offset().top - 60 }, 300);
}
</script>

<?php init_tail(); ?>
