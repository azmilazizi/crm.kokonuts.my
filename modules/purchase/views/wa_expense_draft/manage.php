<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin font-bold">
                                    <i class="fa fa-shopping-bag"></i>
                                    <?php echo $title; ?>
                                </h4>
                            </div>
                        </div>
                        <hr />
                        <div class="row">
                            <div class="col-md-3">
                                <?php echo render_date_input('from_date', '', '', ['placeholder' => _l('from_date')]); ?>
                            </div>
                            <div class="col-md-3">
                                <?php echo render_date_input('to_date', '', '', ['placeholder' => _l('to_date')]); ?>
                            </div>
                        </div>
                        <?php render_datatable([
                            'Vendor',
                            'Description',
                            'Date',
                            'Amount',
                            'Category',
                            'Received',
                            '',
                        ], 'table_wa_expense_drafts'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function($) {
    'use strict';
    var fnServerParams = {
        'from_date': 'input[name="from_date"]',
        'to_date':   'input[name="to_date"]',
    };
    initDataTable(
        '.table-table_wa_expense_drafts',
        admin_url + 'purchase/table_wa_expense_drafts',
        [6], [6],
        fnServerParams,
        [5, 'desc']
    );
    $('input[name="from_date"], input[name="to_date"]').on('change', function() {
        $('.table-table_wa_expense_drafts').DataTable().ajax.reload();
    });
})(jQuery);
</script>
</body>
</html>
