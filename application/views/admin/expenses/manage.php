<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mt-0 tw-font-bold tw-text-xl tw-mb-3">
                    <?= _l('expenses'); ?>
                </h4>
                <div id="stats-top" class="tw-mb-6">
                    <div id="expenses_total" class="empty:tw-min-h-[61px]"></div>
                </div>
                <div class="tw-mb-2">
                    <div class="_buttons sm:tw-space-x-1 rtl:sm:tw-space-x-reverse">
                        <?php if (staff_can('create', 'expenses')) { ?>
                            <a href="<?= admin_url('expenses/expense'); ?>" class="btn btn-primary">
                                <i class="fa-regular fa-plus"></i>
                                <?= _l('new_expense'); ?>
                            </a>
                            <a href="<?= admin_url('expenses/import'); ?>" class="hidden-xs btn btn-default ">
                                <i class="fa-solid fa-upload tw-mr-1"></i>
                                <?= _l('import_expenses'); ?>
                            </a>
                        <?php } ?>
                        <?php if (staff_can('view', 'bulk_pdf_exporter')) { ?>
                            <a href="<?= admin_url('utilities/bulk_pdf_exporter?feature=expenses'); ?>"
                                data-toggle="tooltip" title="<?= _l('bulk_pdf_exporter'); ?>"
                                class="btn-with-tooltip btn btn-default !tw-px-3">
                                <i class="fa-regular fa-file-pdf"></i>
                            </a>
                        <?php } ?>
                        <div class="btn-group mleft4" id="expenses-draft-toggle" role="group"
                            aria-label="Expense draft toggle">
                            <button type="button" class="btn btn-default active" data-draft-filter="">
                                All
                            </button>
                            <button type="button" class="btn btn-default" data-draft-filter="1">
                                Drafts
                            </button>
                        </div>
                        <div id="vueApp" class="tw-inline pull-right tw-ml-0 sm:tw-ml-1.5 rtl:tw-mr-1.5 rtl:tw-ml-0">
                            <app-filters id="<?= $table->id(); ?>" view="<?= $table->viewName(); ?>"
                                :saved-filters="<?= $table->filtersJs(); ?>"
                                :available-rules="<?= $table->rulesJs(); ?>">
                            </app-filters>
                        </div>
                        <a href="#" class="btn btn-default pull-right btn-with-tooltip toggle-small-view hidden-xs"
                            onclick="toggle_small_view('.table-expenses','#expense'); return false;"
                            data-toggle="tooltip" title="<?= _l('invoices_toggle_table_tooltip'); ?>"><i
                                class="fa fa-angle-double-left"></i></a>

                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12" id="small-table">
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="clearfix"></div>
                                <!-- if expenseid found in url -->
                                <?= form_hidden('expenseid', $expenseid); ?>
                                <?= form_hidden('is_draft'); ?>
                                <div class="panel-table-full">
                                    <?php $this->load->view('admin/expenses/table_html', ['withBulkActions' => true]); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7 small-table-right-col">
                        <div id="expense" class="hide">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="expense_convert_helper_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <?= _l('additional_action_required'); ?>
                </h4>
            </div>
            <div class="modal-body">
                <div class="radio radio-primary">
                    <input type="radio" checked id="expense_convert_invoice_type_1" value="save_as_draft_false"
                        name="expense_convert_invoice_type">
                    <label for="expense_convert_invoice_type_1"><?= _l('convert'); ?></label>
                </div>
                <div class="radio radio-primary">
                    <input type="radio" id="expense_convert_invoice_type_2" value="save_as_draft_true"
                        name="expense_convert_invoice_type">
                    <label for="expense_convert_invoice_type_2"><?= _l('convert_and_save_as_draft'); ?></label>
                </div>
                <div id="inc_field_wrapper">
                    <hr />
                    <p><?= _l('expense_include_additional_data_on_convert'); ?>
                    </p>
                    <p><b><?= _l('expense_add_edit_description'); ?>
                            +</b></p>
                    <div class="checkbox checkbox-primary inc_note">
                        <input type="checkbox" id="inc_note">
                        <label for="inc_note"><?= _l('expense'); ?>
                            <?= _l('expense_add_edit_note'); ?></label>
                    </div>
                    <div class="checkbox checkbox-primary inc_name">
                        <input type="checkbox" id="inc_name">
                        <label for="inc_name"><?= _l('expense'); ?>
                            <?= _l('expense_name'); ?></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary"
                    id="expense_confirm_convert"><?= _l('confirm'); ?></button>
            </div>
        </div>
        <!-- /.modal-content -->
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->
<div class="modal fade" id="expense_attachment_preview_modal" tabindex="-1" role="dialog"
    aria-labelledby="expense_attachment_preview_modal_label">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="expense_attachment_preview_modal_label">Attachment Preview</h4>
            </div>
            <div class="modal-body" id="expense_attachment_preview_body"></div>
        </div>
    </div>
</div>
<script>
    var hidden_columns = [4, 5, 6];

    function preview_expense_attachment(event, url, fileType, fileName) {
        if (event) {
            event.preventDefault();
        }

        var $modal = $('#expense_attachment_preview_modal');
        var $body = $('#expense_attachment_preview_body');
        var normalizedType = (fileType || '').toLowerCase();

        $('#expense_attachment_preview_modal_label').text(fileName || 'Attachment Preview');
        $body.empty();

        if (normalizedType.indexOf('pdf') !== -1) {
            $body.html('<embed src="' + url + '" type="application/pdf" width="100%" height="640px" style="display:block;">');
        } else if (normalizedType.indexOf('image/') === 0) {
            $body.html('<div class="text-center"><img src="' + url + '" class="img-responsive" style="max-width:100%;margin:0 auto;"></div>');
        } else {
            $body.html('<p class="text-muted">Preview is available for PDF and image attachments only.</p><p><a href="' + url + '" target="_blank" rel="noopener noreferrer">Open attachment</a></p>');
        }

        $modal.modal('show');
    }
</script>
<?php init_tail(); ?>
<script>
    Dropzone.autoDiscover = false;
    $(function () {
        var ExpensesServerParams = {
            is_draft: '[name="is_draft"]'
        };

        initDataTable('.table-expenses', admin_url + 'expenses/table', [0], [0], ExpensesServerParams,
            <?= hooks()->apply_filters('expenses_table_default_order', json_encode([6, 'desc'])); ?>
        )
            .column(1).visible(false, false).columns.adjust();

        $('body').on('click', '#expenses-draft-toggle [data-draft-filter]', function () {
            var $button = $(this);
            var draftValue = $button.attr('data-draft-filter') || '';

            $('input[name="is_draft"]').val(draftValue);
            $('#expenses-draft-toggle [data-draft-filter]').removeClass('active');
            $button.addClass('active');
            $('.table-expenses').DataTable().ajax.reload(null, true);
        });

        init_expense();

        $('#expense_convert_helper_modal').on('show.bs.modal', function () {
            var emptyNote = $('#tab_expense').attr('data-empty-note');
            var emptyName = $('#tab_expense').attr('data-empty-name');
            if (emptyNote == '1' && emptyName == '1') {
                $('#inc_field_wrapper').addClass('hide');
            } else {
                $('#inc_field_wrapper').removeClass('hide');
                emptyNote === '1' && $('.inc_note').addClass('hide') || $('.inc_note').removeClass(
                    'hide')
                emptyName === '1' && $('.inc_name').addClass('hide') || $('.inc_name').removeClass(
                    'hide')
            }
        });

        $('body').on('click', '#expense_confirm_convert', function () {
            var parameters = new Array();
            if ($('input[name="expense_convert_invoice_type"]:checked').val() == 'save_as_draft_true') {
                parameters['save_as_draft'] = 'true';
            }
            parameters['include_name'] = $('#inc_name').prop('checked');
            parameters['include_note'] = $('#inc_note').prop('checked');
            window.location.href = buildUrl(admin_url + 'expenses/convert_to_invoice/' + $('body').find(
                '.expense_convert_btn').attr('data-id'), parameters);
        });
    });
</script>
</body>

</html>
