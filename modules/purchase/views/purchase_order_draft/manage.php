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
                                    <i class="fa fa-file-text"></i>
                                    <?php echo _l($title); ?>
                                </h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('purchase/pur_order_draft_form'); ?>" class="btn btn-primary">
                                    <i class="fa fa-plus"></i> <?php echo _l('new_pur_order_draft'); ?>
                                </a>
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
                            <div class="col-md-3 form-group">
                                <select name="is_paid_filter" id="is_paid_filter" class="selectpicker" data-width="100%" data-none-selected-text="<?php echo _l('payment_status'); ?>">
                                    <option value=""></option>
                                    <option value="0"><?php echo _l('unpaid'); ?></option>
                                    <option value="1"><?php echo _l('paid'); ?></option>
                                </select>
                            </div>
                        </div>
                        <?php render_datatable([
                            _l('draft_name'),
                            _l('order_number_lable'),
                            _l('vendor'),
                            _l('order_date'),
                            _l('grand_total'),
                            _l('payment_status'),
                            _l('pur_date_created'),
                            _l('options'),
                        ], 'table_pur_order_drafts'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Draft Detail Modal -->
<div class="modal fade" id="draft_detail_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><?php echo _l('pur_order_draft_detail'); ?></h4>
            </div>
            <div class="modal-body" id="draft_detail_content">
                <div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
(function($) {
    'use strict';

    var table = $('.table-table_pur_order_drafts');

    var fnServerParams = {
        'from_date': 'input[name="from_date"]',
        'to_date':   'input[name="to_date"]',
        'is_paid':   'select[name="is_paid_filter"]',
    };

    initDataTable(
        '.table-table_pur_order_drafts',
        admin_url + 'purchase/table_pur_order_drafts',
        [7], [7],
        fnServerParams,
        [6, 'desc']
    );

    $('input[name="from_date"], input[name="to_date"]').on('change', function() {
        table.DataTable().ajax.reload();
    });

    $('select[name="is_paid_filter"]').on('change', function() {
        table.DataTable().ajax.reload();
    });

})(jQuery);

function view_pur_order_draft(id) {
    $('#draft_detail_content').html('<div class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i></div>');
    $('#draft_detail_modal').modal('show');
    $.get(admin_url + 'purchase/get_pur_order_draft_ajax/' + id, function(response) {
        $('#draft_detail_content').html(response);
    });
}
</script>
</body>
</html>
