<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">

        <div class="row" style="margin-bottom:16px;">
            <div class="col-sm-6">
                <h4 class="no-margin-top" style="margin-bottom:4px;">Import Transactions</h4>
                <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                    <li><a href="<?php echo admin_url('pos/dashboard'); ?>">Dashboard</a></li>
                    <li><a href="<?php echo admin_url('pos/transactions'); ?>">Transactions</a></li>
                    <li class="active">Import</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Import Walk-in Transactions from CSV</strong></div>
                    <div class="panel-body">

                        <div class="alert alert-info">
                            <strong>Before you import:</strong>
                            <ul style="margin:8px 0 0 0;">
                                <li>CSV must be exported from Lightspeed (or compatible system) with standard column headers.</li>
                                <li>Duplicate receipt numbers are automatically skipped.</li>
                                <li>Returns (<em>Transaction Type = Return</em>) are imported as <code>REFUND</code> type.</li>
                                <li>Cancelled transactions (<em>Is_Cancelled = True</em>) are marked cancelled.</li>
                            </ul>
                        </div>

                        <form id="import-form" enctype="multipart/form-data">
                            <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>

                            <div class="form-group">
                                <label>Store <span class="text-danger">*</span></label>
                                <select name="warehouse_id" class="form-control selectpicker" data-live-search="true" required>
                                    <option value="">— Select store —</option>
                                    <?php foreach ($warehouses as $w): ?>
                                    <option value="<?php echo (int)$w['id']; ?>"><?php echo htmlspecialchars($w['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>CSV File <span class="text-danger">*</span></label>
                                <input type="file" name="csv_file" accept=".csv" required class="form-control">
                                <p class="help-block">Only .csv files. Max 20 MB.</p>
                            </div>

                            <button type="submit" class="btn btn-primary" id="import-btn">
                                <i class="fa fa-upload"></i> Import
                            </button>
                            <a href="<?php echo admin_url('pos/transactions'); ?>" class="btn btn-default">Cancel</a>
                        </form>

                        <div id="import-result" style="margin-top:20px;display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
$(function () {
    $('#import-form').on('submit', function (e) {
        e.preventDefault();
        var $btn = $('#import-btn');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importing…');
        $('#import-result').hide().empty();

        var formData = new FormData(this);

        $.ajax({
            url: '<?php echo admin_url('pos/ajax_import_transactions'); ?>',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (resp) {
                $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                if (!resp.success) {
                    $('#import-result').html(
                        '<div class="alert alert-danger"><strong>Error:</strong> ' + resp.message + '</div>'
                    ).show();
                    return;
                }

                var html = '<div class="alert alert-success">'
                    + '<strong>Import complete.</strong><br>'
                    + 'Imported: <strong>' + resp.imported + '</strong> &nbsp;|&nbsp; '
                    + 'Skipped (duplicates): <strong>' + resp.skipped + '</strong>'
                    + '</div>';

                if (resp.errors && resp.errors.length) {
                    html += '<div class="alert alert-warning"><strong>Warnings (' + resp.errors.length + '):</strong><ul style="margin:6px 0 0 0;">';
                    $.each(resp.errors, function (_, msg) {
                        html += '<li>' + $('<span>').text(msg).html() + '</li>';
                    });
                    html += '</ul></div>';
                }

                html += '<a href="<?php echo admin_url('pos/transactions'); ?>" class="btn btn-sm btn-primary">View Transactions</a>';
                $('#import-result').html(html).show();
            },
            error: function () {
                $btn.prop('disabled', false).html('<i class="fa fa-upload"></i> Import');
                $('#import-result').html(
                    '<div class="alert alert-danger">Server error. Please try again.</div>'
                ).show();
            }
        });
    });
});
</script>

<?php init_tail(); ?>
