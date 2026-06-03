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
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" class="btn btn-primary" onclick="openStoreModal()">
                                    <i class="fa fa-plus"></i> New Store
                                </button>
                            </div>
                        </div>
                        <hr />

                        <table class="table table-hover" id="stores-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>City</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($stores)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No stores yet. Create one to get started.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($stores as $store): ?>
                                <tr id="store-row-<?php echo $store['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($store['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($store['city'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($store['phone_number'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($store['address'] ?: '—'); ?></td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-default" onclick="openStoreModal(<?php echo htmlspecialchars(json_encode($store)); ?>)">
                                            <i class="fa fa-pencil"></i> Edit
                                        </button>
                                        <button class="btn btn-xs btn-danger" onclick="deleteStore(<?php echo $store['id']; ?>)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Store Modal -->
<div class="modal fade" id="storeModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title" id="storeModalTitle">New Store</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="store-id" value="">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Store Name <span class="text-danger">*</span></label>
                            <input type="text" id="store-name" class="form-control" placeholder="e.g. KL Outlet">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label>Address</label>
                            <input type="text" id="store-address" class="form-control" placeholder="Street address">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" id="store-city" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>State</label>
                            <input type="text" id="store-state" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Postal Code</label>
                            <input type="text" id="store-postal" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Country</label>
                            <input type="text" id="store-country" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" id="store-phone" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" id="store-description" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-save-store">Save Store</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
function openStoreModal(store) {
    store = store || {};
    $('#storeModalTitle').text(store.id ? 'Edit Store' : 'New Store');
    $('#store-id').val(store.id || '');
    $('#store-name').val(store.name || '');
    $('#store-address').val(store.address || '');
    $('#store-city').val(store.city || '');
    $('#store-state').val(store.state || '');
    $('#store-postal').val(store.postal_code || '');
    $('#store-country').val(store.country || '');
    $('#store-phone').val(store.phone_number || '');
    $('#store-description').val(store.description || '');
    $('#storeModal').modal('show');
}

$('#btn-save-store').on('click', function () {
    var name = $.trim($('#store-name').val());
    if (!name) { alert('Store name is required'); return; }

    $.post('<?php echo admin_url('pos/ajax_save_store'); ?>', {
        id:           $('#store-id').val(),
        name:         name,
        address:      $('#store-address').val(),
        city:         $('#store-city').val(),
        state:        $('#store-state').val(),
        postal_code:  $('#store-postal').val(),
        country:      $('#store-country').val(),
        phone_number: $('#store-phone').val(),
        description:  $('#store-description').val(),
    }, function (resp) {
        if (resp.success) {
            $('#storeModal').modal('hide');
            location.reload();
        } else {
            alert(resp.message || 'Failed to save store');
        }
    }, 'json');
});

function deleteStore(id) {
    if (!confirm('Delete this store? This cannot be undone.')) return;
    $.post('<?php echo admin_url('pos/ajax_delete_store/'); ?>' + id, function (resp) {
        if (resp.success) { $('#store-row-' + id).remove(); }
    }, 'json');
}
</script>
</body>
</html>
