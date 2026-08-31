<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.loyalty-stat-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:18px 22px; margin-bottom:18px; }
.loyalty-stat-card .stat-value { font-size:26px; font-weight:700; color:#333; }
.loyalty-stat-card .stat-label { font-size:12px; color:#888; margin-top:2px; }
.franchisee-table tbody tr { cursor:pointer; }
.franchisee-table tbody tr:hover { background:#f5f9ff; }
.owing-amount { color:#c0392b; font-weight:700; }
.owing-zero { color:#888; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Franchise Settlement</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty'); ?>">Loyalty</a></li>
                <li class="active">Franchise Settlement</li>
            </ol>
        </div>
        <div class="col-sm-6 text-right">
            <?php if (has_permission('loyalty', '', 'create')): ?>
            <button type="button" class="btn btn-primary" onclick="openFranchiseeModal()">
                <i class="fa fa-plus"></i> New Franchisee
            </button>
            <?php endif; ?>
        </div>
    </div>

    <p class="text-muted" style="max-width:760px;">
        Customers can redeem cashback at any outlet, regardless of where it was earned. When a customer redeems
        cashback at a franchisee-owned outlet, that outlet has effectively given the customer a discount funded by
        you. Use this page to track how much is owed to each franchisee and record when you've transferred it to them.
    </p>

    <!-- Stats Row -->
    <?php
        $total_outstanding = array_sum(array_column($franchisees, 'outstanding'));
        $total_transferred = array_sum(array_column($franchisees, 'lifetime_transferred'));
    ?>
    <div class="row">
        <div class="col-sm-4">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo count($franchisees); ?></div>
                <div class="stat-label">Franchisees</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="loyalty-stat-card">
                <div class="stat-value owing-amount">RM <?php echo number_format($total_outstanding, 2); ?></div>
                <div class="stat-label">Total Outstanding (owed to franchisees)</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="loyalty-stat-card">
                <div class="stat-value">RM <?php echo number_format($total_transferred, 2); ?></div>
                <div class="stat-label">Total Transferred (lifetime)</div>
            </div>
        </div>
    </div>

    <!-- Franchisees Table -->
    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin franchisee-table">
                <thead>
                    <tr>
                        <th>Franchisee</th>
                        <th>Contact</th>
                        <th class="text-center">Outlets</th>
                        <th class="text-right">Outstanding</th>
                        <th class="text-right">Lifetime Transferred</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($franchisees)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted" style="padding:30px;">
                            No franchisees yet. Add one, then assign outlets to them below.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($franchisees as $f): ?>
                    <tr onclick="window.location='<?php echo admin_url('loyalty/franchisee/' . (int)$f['id']); ?>'">
                        <td style="font-weight:500;">
                            <?php echo htmlspecialchars($f['name']); ?>
                            <?php if (!(int)$f['is_active']): ?><span class="label label-default">inactive</span><?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?php echo htmlspecialchars($f['contact_person'] ?: '—'); ?>
                            <?php if ($f['phone']): ?><br><span style="font-size:12px;"><?php echo htmlspecialchars($f['phone']); ?></span><?php endif; ?>
                        </td>
                        <td class="text-center"><?php echo (int)$f['outlet_count']; ?></td>
                        <td class="text-right <?php echo $f['outstanding'] > 0 ? 'owing-amount' : 'owing-zero'; ?>">
                            RM <?php echo number_format((float)$f['outstanding'], 2); ?>
                        </td>
                        <td class="text-right text-muted">RM <?php echo number_format((float)$f['lifetime_transferred'], 2); ?></td>
                        <td class="text-right" style="white-space:nowrap;" onclick="event.stopPropagation()">
                            <?php if (has_permission('loyalty', '', 'edit')): ?>
                            <button class="btn btn-sm btn-default" onclick='openFranchiseeModal(<?php echo json_encode($f, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                <i class="fa fa-pencil"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (has_permission('loyalty', '', 'delete')): ?>
                            <button class="btn btn-sm btn-danger" onclick="deleteFranchisee(<?php echo (int)$f['id']; ?>, '<?php echo htmlspecialchars(addslashes($f['name']), ENT_QUOTES); ?>')">
                                <i class="fa fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Outlet Ownership -->
    <h4 style="margin-top:30px;">Outlet Ownership</h4>
    <p class="text-muted">Assign which outlets belong to your company vs. a franchisee.</p>
    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin" id="stores-ownership-table">
                <thead>
                    <tr>
                        <th>Outlet</th>
                        <th style="width:320px;">Owner</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stores as $s): ?>
                    <tr id="store-owner-row-<?php echo (int)$s['id']; ?>">
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td>
                            <?php if (has_permission('loyalty', '', 'edit')): ?>
                            <select class="form-control input-sm store-owner-select" data-warehouse-id="<?php echo (int)$s['id']; ?>">
                                <option value="">Company-owned (franchisor)</option>
                                <?php foreach ($franchisees as $f): ?>
                                <option value="<?php echo (int)$f['id']; ?>" <?php echo (int)($s['franchisee_id'] ?? 0) === (int)$f['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($f['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else: ?>
                            <?php echo htmlspecialchars($s['franchisee_name'] ?: 'Company-owned (franchisor)'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>

<!-- Franchisee Modal -->
<div class="modal fade" id="franchisee-modal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="franchisee-modal-title">New Franchisee</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="franchisee-id">
                <div class="form-group">
                    <label>Franchisee / Business Name <span class="text-danger">*</span></label>
                    <input type="text" id="franchisee-name" class="form-control" placeholder="e.g. Ali Enterprise Sdn Bhd">
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Contact Person</label>
                            <input type="text" id="franchisee-contact-person" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" id="franchisee-phone" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="franchisee-email" class="form-control">
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Bank Name</label>
                            <input type="text" id="franchisee-bank-name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Account Name</label>
                            <input type="text" id="franchisee-bank-account-name" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Account No.</label>
                            <input type="text" id="franchisee-bank-account-no" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea id="franchisee-notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" id="franchisee-is-active" checked> Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="franchisee-save-btn" onclick="saveFranchisee()">
                    <i class="fa fa-check"></i> Save Franchisee
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function openFranchiseeModal(f) {
    f = f || {};
    $('#franchisee-modal-title').text(f.id ? 'Edit Franchisee' : 'New Franchisee');
    $('#franchisee-id').val(f.id || '');
    $('#franchisee-name').val(f.name || '');
    $('#franchisee-contact-person').val(f.contact_person || '');
    $('#franchisee-phone').val(f.phone || '');
    $('#franchisee-email').val(f.email || '');
    $('#franchisee-bank-name').val(f.bank_name || '');
    $('#franchisee-bank-account-name').val(f.bank_account_name || '');
    $('#franchisee-bank-account-no').val(f.bank_account_no || '');
    $('#franchisee-notes').val(f.notes || '');
    $('#franchisee-is-active').prop('checked', f.id ? !!parseInt(f.is_active) : true);
    $('#franchisee-modal').modal('show');
}

function saveFranchisee() {
    var name = $.trim($('#franchisee-name').val());
    if (!name) { alert('Franchisee name is required'); return; }

    var btn = $('#franchisee-save-btn').prop('disabled', true);
    $.post(ADMIN_URL + 'loyalty/ajax_save_franchisee', {
        id:                 $('#franchisee-id').val(),
        name:               name,
        contact_person:     $('#franchisee-contact-person').val(),
        phone:              $('#franchisee-phone').val(),
        email:              $('#franchisee-email').val(),
        bank_name:          $('#franchisee-bank-name').val(),
        bank_account_name:  $('#franchisee-bank-account-name').val(),
        bank_account_no:    $('#franchisee-bank-account-no').val(),
        notes:              $('#franchisee-notes').val(),
        is_active:          $('#franchisee-is-active').is(':checked') ? 1 : 0,
    }, function (resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to save franchisee');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

function deleteFranchisee(id, name) {
    if (!confirm('Delete franchisee "' + name + '"?')) return;
    $.post(ADMIN_URL + 'loyalty/ajax_delete_franchisee/' + id, function (resp) {
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to delete franchisee');
        }
    }, 'json').fail(function () {
        alert('Request failed. Please try again.');
    });
}

$('.store-owner-select').on('change', function () {
    var $sel = $(this);
    var warehouse_id = $sel.data('warehouse-id');
    var franchisee_id = $sel.val();
    $sel.prop('disabled', true);
    $.post(ADMIN_URL + 'loyalty/ajax_assign_store', {
        warehouse_id: warehouse_id,
        franchisee_id: franchisee_id,
    }, function (resp) {
        $sel.prop('disabled', false);
        if (!resp.success) {
            alert(resp.message || 'Failed to update outlet owner');
        }
    }, 'json').fail(function () {
        $sel.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
});
</script>

<?php init_tail(); ?>
