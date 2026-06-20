<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div class="row">
  <div class="col-md-12">
    <h4 class="no-margin font-bold"><?php echo _l('bill_category_mapping'); ?></h4>
    <p class="text-muted"><?php echo _l('bill_category_mapping_desc'); ?></p>
  </div>
</div>
<hr>
<a href="#" onclick="add_bill_category(); return false;" class="btn btn-info button-margin-r-b">
  <?php echo _l('add'); ?>
</a>
<hr>
<table class="table table-bill-category-mapping">
  <thead>
    <tr>
      <th><?php echo _l('name'); ?></th>
      <th><?php echo _l('debit_account'); ?></th>
      <th><?php echo _l('credit_account'); ?></th>
      <th><?php echo _l('description'); ?></th>
      <th><?php echo _l('options'); ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($bill_categories as $category) {
      $debit_name  = '';
      $credit_name = '';
      foreach ($accounts as $account) {
        if ($account['id'] == $category['debit_account'])  { $debit_name  = $account['name']; }
        if ($account['id'] == $category['credit_account']) { $credit_name = $account['name']; }
      }
    ?>
    <tr>
      <td><?php echo new_html_entity_decode($category['name']); ?></td>
      <td><?php echo $debit_name; ?></td>
      <td><?php echo $credit_name; ?></td>
      <td><?php echo new_html_entity_decode($category['description']); ?></td>
      <td>
        <a href="#" onclick="edit_bill_category(<?php echo $category['id']; ?>, <?php echo htmlspecialchars(json_encode($category), ENT_QUOTES); ?>); return false;"><?php echo _l('edit'); ?></a>
        &nbsp;|&nbsp;
        <a href="<?php echo admin_url('accounting/delete_bill_category/' . $category['id']); ?>" class="text-danger _delete"><?php echo _l('delete'); ?></a>
      </td>
    </tr>
    <?php } ?>
  </tbody>
</table>

<!-- Add Modal -->
<div class="modal fade" id="bill-category-modal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><?php echo _l('add_bill_category'); ?></h4>
      </div>
      <?php echo form_open(admin_url('accounting/bill_category_mapping'), ['id' => 'bill-category-form']); ?>
      <?php echo form_hidden('id', '0'); ?>
      <div class="modal-body">
        <?php echo render_input('name', 'name', '', 'text', ['required' => true]); ?>
        <?php echo render_select('debit_account', $accounts, ['id', 'name', 'account_type_name'], 'debit_account', '', [], [], '', '', false); ?>
        <?php echo render_select('credit_account', $accounts, ['id', 'name', 'account_type_name'], 'credit_account', '', [], [], '', '', false); ?>
        <?php echo render_textarea('description', 'description', '', ['rows' => 2]); ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
        <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
      </div>
      <?php echo form_close(); ?>
    </div>
  </div>
</div>

<script>
function add_bill_category() {
  $('#bill-category-form input[name="id"]').val('0');
  $('#bill-category-form input[name="name"]').val('');
  $('#bill-category-form select[name="debit_account"]').val('').selectpicker('refresh');
  $('#bill-category-form select[name="credit_account"]').val('').selectpicker('refresh');
  $('#bill-category-form textarea[name="description"]').val('');
  $('#bill-category-modal .modal-title').text('<?php echo _l('add_bill_category'); ?>');
  $('#bill-category-modal').modal('show');
}

function edit_bill_category(id, data) {
  $('#bill-category-form input[name="id"]').val(id);
  $('#bill-category-form input[name="name"]').val(data.name);
  $('#bill-category-form select[name="debit_account"]').val(data.debit_account).selectpicker('refresh');
  $('#bill-category-form select[name="credit_account"]').val(data.credit_account).selectpicker('refresh');
  $('#bill-category-form textarea[name="description"]').val(data.description);
  $('#bill-category-modal .modal-title').text('<?php echo _l('edit_bill_category'); ?>');
  $('#bill-category-modal').modal('show');
}
</script>
