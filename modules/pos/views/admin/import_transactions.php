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
                    <div class="panel-heading"><strong id="panel-title">Import Sales from CSV</strong></div>
                    <div class="panel-body">

                        <div class="alert alert-info">
                            <strong id="intro-title">Before you import (Walk-in / Cash Register):</strong>
                            <ul id="intro-list" style="margin:8px 0 0 0;">
                                <li>CSV must be exported from Lightspeed (or compatible system) with standard column headers.</li>
                                <li>Duplicate receipt / order numbers are automatically skipped.</li>
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
                                <label>Sales Source <span class="text-danger">*</span></label>
                                <select name="source" id="source" class="form-control selectpicker" required>
                                    <option value="WALKIN">Walk-in / Cash Register (Import)</option>
                                    <option value="GRABFOOD">GrabFood</option>
                                    <option value="FOODPANDA">FoodPanda</option>
                                    <option value="SHOPEEFOOD">ShopeeFood</option>
                                </select>
                                <p class="help-block" id="source-help">
                                    Choose the sales channel this CSV belongs to. Reports will combine all sources into a single breakdown.
                                </p>
                            </div>

                            <div class="form-group">
                                <div id="tpl-walkin" class="tpl-block">
                                    <div class="alert alert-success" style="padding:12px;margin-bottom:8px;">
                                        <strong>Walk-in CSV Format</strong>
                                        <p style="margin:4px 0 0 0;font-size:13px;">
                                            Required columns: <code>Receipt Number</code>, <code>Time</code>, <code>SubTotal</code>, <code>Discount</code>, <code>Tax</code>, <code>Total</code>.
                                            Optional: <code>Service Charge</code>, <code>Is_Cancelled</code>, <code>Notes</code>, <code>Transaction Type</code> (Sale/Return).
                                            Any other column is treated as a payment method (e.g. <em>Cash</em>, <em>Credit Card</em>, <em>DuitNow QR</em>).
                                        </p>
                                        <button type="button" class="btn btn-xs btn-default download-tpl" data-tpl="walkin" style="margin-top:8px;">
                                            <i class="fa fa-download"></i> Download Walk-in CSV Template
                                        </button>
                                    </div>
                                </div>

                                <div id="tpl-grabfood" class="tpl-block" style="display:none;">
                                    <div class="alert alert-success" style="padding:12px;margin-bottom:8px;">
                                        <strong>GrabFood CSV Format</strong>
                                        <p style="margin:4px 0 0 0;font-size:13px;">
                                            Export from GrabFood Merchant Portal. Required columns:
                                            <code>Order ID</code>, <code>Order Date</code>, <code>Order Status</code>,
                                            <code>Customer Name</code>, <code>Subtotal</code>, <code>Promo / Discount</code>,
                                            <code>Tax</code>, <code>Delivery Fee</code>, <code>Merchant Payout</code>.
                                        </p>
                                        <button type="button" class="btn btn-xs btn-default download-tpl" data-tpl="grabfood" style="margin-top:8px;">
                                            <i class="fa fa-download"></i> Download GrabFood CSV Template
                                        </button>
                                    </div>
                                </div>

                                <div id="tpl-foodpanda" class="tpl-block" style="display:none;">
                                    <div class="alert alert-success" style="padding:12px;margin-bottom:8px;">
                                        <strong>FoodPanda CSV Format</strong>
                                        <p style="margin:4px 0 0 0;font-size:13px;">
                                            Export from FoodPanda Merchant Portal. Required columns:
                                            <code>Order ID</code>, <code>Order Date</code>, <code>Status</code>,
                                            <code>Customer Name</code>, <code>Subtotal</code>, <code>Voucher / Discount</code>,
                                            <code>VAT / Tax</code>, <code>Delivery Fee</code>, <code>Merchant Remittance</code>.
                                        </p>
                                        <button type="button" class="btn btn-xs btn-default download-tpl" data-tpl="foodpanda" style="margin-top:8px;">
                                            <i class="fa fa-download"></i> Download FoodPanda CSV Template
                                        </button>
                                    </div>
                                </div>

                                <div id="tpl-shopeefood" class="tpl-block" style="display:none;">
                                    <div class="alert alert-success" style="padding:12px;margin-bottom:8px;">
                                        <strong>ShopeeFood CSV Format</strong>
                                        <p style="margin:4px 0 0 0;font-size:13px;">
                                            Export from ShopeeFood Merchant Portal. Required columns:
                                            <code>Order ID</code>, <code>Order Date</code>, <code>Order Status</code>,
                                            <code>Customer Name</code>, <code>Subtotal</code>, <code>Promo / Discount</code>,
                                            <code>Tax</code>, <code>Delivery Fee</code>, <code>Merchant Payout</code>.
                                        </p>
                                        <button type="button" class="btn btn-xs btn-default download-tpl" data-tpl="shopeefood" style="margin-top:8px;">
                                            <i class="fa fa-download"></i> Download ShopeeFood CSV Template
                                        </button>
                                    </div>
                                </div>
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
    var templates = {
        walkin: [
            ['Receipt Number','Time','Transaction Type','Is_Cancelled','Customer','SubTotal','Discount','Service Charge','Tax','Rounding','Total','Cash','Credit Card','DuitNow QR','Notes'],
            ['R001234','2026-08-01 12:30:00','Sale','False','Ahmad bin Ali',50.00,0,0,3.00,0,53.00,53.00,0,0,'Dine-in table 3'],
            ['R001235','2026-08-01 13:05:00','Sale','False','',28.50,5.00,0,1.41,0,24.91,0,24.91,0,'Takeaway']
        ],
        grabfood: [
            ['Order ID','Order Date','Order Status','Short Ref','Customer Name','Customer Phone','Subtotal','Promo / Discount','Tax','Delivery Fee','Eater Payment','Merchant Payout','Promo Notes'],
            ['GF-20260801-000123','2026-08-01 12:15:00','COMPLETED','#123','Siti Nurhaliza','+6012-345-6789',45.00,5.00,2.40,5.00,47.40,42.40,'New user RM5 off'],
            ['GF-20260801-000124','2026-08-01 12:48:00','COMPLETED','#124','Tan Sri Lim','',32.00,0,1.60,4.00,37.60,33.60','']
        ],
        foodpanda: [
            ['Order ID','Order Date','Status','Customer Name','Customer Contact','Subtotal','Voucher / Discount','VAT / Tax','Delivery Fee','Eater Total','Merchant Remittance','Remarks'],
            ['FP260801120001','2026-08-01 12:00:00','delivered','Muhammad Amir','+6013-456-7890',38.00,3.00,1.75,4.50,41.25,36.75,'Less spicy please'],
            ['FP260801121502','2026-08-01 12:15:00','delivered','Chan Mei Ling','',22.00,0,1.10,4.00,27.10,23.10,'']
        ],
        shopeefood: [
            ['Order ID','Order Date','Order Status','Customer Name','Customer Phone','Subtotal','Promo / Discount','Tax','Delivery Fee','Eater Paid','Merchant Payout','Notes'],
            ['SF2608011001','2026-08-01 11:50:00','Completed','Nurul Huda','+6019-876-5432',55.00,8.00,2.35,5.00,54.35,49.35,'ShopeePay cashback'],
            ['SF2608011030','2026-08-01 12:20:00','Completed','Raju Krishnan','',41.00,0,2.05,4.50,47.55,43.05','']
        ]
    };

    function downloadCsv(tplKey) {
        var rows = templates[tplKey] || templates.walkin;
        var csv = '\ufeff' + rows.map(function(r){
            return r.map(function(c){
                var s = String(c === null || c === undefined ? '' : c);
                return /[",\n]/.test(s) ? '"' + s.replace(/"/g,'""') + '"' : s;
            }).join(',');
        }).join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        var a = document.createElement('a');
        var url = URL.createObjectURL(blob);
        a.href = url;
        a.download = 'template_' + tplKey + '_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    function syncSource() {
        var src = ($('#source').val() || 'WALKIN').toLowerCase();
        $('.tpl-block').hide();
        $('#tpl-' + src).show();
        var titleMap = {
            walkin: 'Import Walk-in / Cash Register Transactions from CSV',
            grabfood: 'Import GrabFood Sales from CSV',
            foodpanda: 'Import FoodPanda Sales from CSV',
            shopeefood: 'Import ShopeeFood Sales from CSV'
        };
        var introMap = {
            walkin: { title: 'Before you import (Walk-in / Cash Register):', items: [
                'CSV must be exported from Lightspeed (or compatible system) with standard column headers.',
                'Duplicate receipt numbers are automatically skipped.',
                'Returns (<em>Transaction Type = Return</em>) are imported as <code>REFUND</code> type.',
                'Cancelled transactions (<em>Is_Cancelled = True</em>) are marked cancelled.'
            ]},
            grabfood: { title: 'Before you import (GrabFood):', items: [
                'Use the exact template (Download button below) or export from GrabFood Merchant Portal.',
                'Duplicate Order IDs are automatically skipped — safe to re-upload the same CSV.',
                'Orders with status <em>cancelled / rejected / failed</em> are marked cancelled.',
                'Merchant Payout is used as the payment amount (excludes Grab\'s delivery fee & commission).'
            ]},
            foodpanda: { title: 'Before you import (FoodPanda):', items: [
                'Use the exact template (Download button below) or export from FoodPanda Merchant Portal.',
                'Duplicate Order IDs are automatically skipped — safe to re-upload the same CSV.',
                'Orders with status <em>cancelled / rejected / failed</em> are marked cancelled.',
                'Merchant Remittance is used as the payment amount (excludes delivery fee & commission).'
            ]},
            shopeefood: { title: 'Before you import (ShopeeFood):', items: [
                'Use the exact template (Download button below) or export from ShopeeFood Merchant Portal.',
                'Duplicate Order IDs are automatically skipped — safe to re-upload the same CSV.',
                'Orders with status <em>cancelled / rejected / failed</em> are marked cancelled.',
                'Merchant Payout is used as the payment amount (excludes Shopee\'s delivery fee & commission).'
            ]}
        };
        var meta = introMap[src] || introMap.walkin;
        $('#panel-title').text(titleMap[src] || titleMap.walkin);
        $('#intro-title').html(meta.title);
        $('#intro-list').html(meta.items.map(function(i){ return '<li>' + i + '</li>'; }).join(''));
    }

    $('#source').on('change', syncSource);
    syncSource();

    $('.download-tpl').on('click', function () {
        downloadCsv($(this).data('tpl'));
    });

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
                    + 'Skipped (duplicates): <strong>' + resp.skipped + '</strong>';
                if (resp.batch_id) {
                    html += '&nbsp;|&nbsp; Batch: <strong>#' + resp.batch_id + '</strong>';
                }
                html += '</div>';

                if (resp.errors && resp.errors.length) {
                    html += '<div class="alert alert-warning"><strong>Warnings (' + resp.errors.length + '):</strong><ul style="margin:6px 0 0 0;">';
                    $.each(resp.errors, function (_, msg) {
                        html += '<li>' + $('<span>').text(msg).html() + '</li>';
                    });
                    html += '</ul></div>';
                }

                html += '<a href="<?php echo admin_url('pos/transactions'); ?>" class="btn btn-sm btn-primary">View Transactions</a> '
                      + '<a href="<?php echo admin_url('pos/reports/txn_types'); ?>" class="btn btn-sm btn-default">View Channel Report</a>';
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
