<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.profile-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:24px; margin-bottom:20px; }
.points-display { font-size:36px; font-weight:700; color:#337ab7; line-height:1; }
.points-label { font-size:13px; color:#888; margin-top:4px; }
.tier-badge-lg { display:inline-block; padding:4px 14px; border-radius:20px; font-size:13px; font-weight:600; background:#eee; color:#555; }
.stat-mini { text-align:center; padding:12px; border-right:1px solid #eee; }
.stat-mini:last-child { border-right:none; }
.stat-mini .val { font-size:18px; font-weight:600; color:#333; }
.stat-mini .lbl { font-size:11px; color:#aaa; }
.txn-type-earn    { color:#5cb85c; font-weight:600; }
.txn-type-redeem  { color:#f0ad4e; font-weight:600; }
.txn-type-adjust  { color:#337ab7; font-weight:600; }
.account-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:18px 20px; margin-bottom:20px; }
.account-card .section-label { font-size:11px; color:#999; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; font-weight:600; }
.status-active   { color:#5cb85c; font-weight:600; }
.status-inactive { color:#aaa; }
.status-banned   { color:#d9534f; font-weight:600; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-8">
            <h4 class="no-margin-top" style="margin-bottom:4px;"><?php echo htmlspecialchars($customer['name'] ?: $customer['phone']); ?></h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/customers'); ?>">Members</a></li>
                <li class="active"><?php echo htmlspecialchars($customer['name'] ?: 'Member #' . $customer['id']); ?></li>
            </ol>
        </div>
        <?php if (has_permission('loyalty', '', 'edit')): ?>
        <div class="col-sm-4 text-right" style="padding-top:4px;">
            <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#recoveryModal" style="margin-right:4px;">
                <i class="fa fa-ticket"></i> Recovery Voucher
            </button>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#adjustModal">
                <i class="fa fa-plus-minus"></i> Adjust Points
            </button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Profile Card -->
    <div class="profile-card">
        <div class="row">
            <div class="col-sm-4">
                <div style="margin-bottom:12px;">
                    <div class="points-display"><?php echo number_format((float)$customer['total_points'], 2); ?></div>
                    <div class="points-label">Points Balance</div>
                </div>
                <?php if ($customer['tier']): ?>
                <span class="tier-badge-lg" style="background:<?php echo htmlspecialchars($customer['tier']['color'] ?? '#eee'); ?>;color:#fff;">
                    <?php echo htmlspecialchars($customer['tier']['name']); ?>
                </span>
                <?php else: ?>
                <span class="tier-badge-lg">Member</span>
                <?php endif; ?>
            </div>
            <div class="col-sm-4">
                <table class="table no-margin" style="font-size:14px;">
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;width:80px;">Name</td>
                        <td style="border:none;padding:4px 0;"><strong><?php echo htmlspecialchars($customer['name'] ?: '—'); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Phone</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($customer['phone'] ?: '—'); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Email</td>
                        <td style="border:none;padding:4px 0;"><?php echo htmlspecialchars($customer['email'] ?: '—'); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-4">
                <table class="table no-margin" style="font-size:14px;">
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;width:90px;">Total Spent</td>
                        <td style="border:none;padding:4px 0;"><strong>RM <?php echo number_format((float)$customer['total_spent'], 2); ?></strong></td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Last Visit</td>
                        <td style="border:none;padding:4px 0;">
                            <?php echo $customer['last_visit'] ? date('d/m/Y', strtotime($customer['last_visit'])) : '—'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:4px 8px 4px 0;">Joined</td>
                        <td style="border:none;padding:4px 0;font-size:12px;">
                            <?php echo date('d/m/Y', strtotime($customer['registered_at'])); ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Member Account Card -->
    <div class="account-card">
        <div class="row">
            <div class="col-sm-4">
                <div class="section-label">Member Account</div>
                <?php
                    $has_password  = !empty($customer['password_hash']);
                    $acct_status   = $customer['account_status'] ?? 'active';
                ?>
                <?php if ($has_password): ?>
                    <span class="status-active"><i class="fa fa-check-circle"></i> Account Active</span>
                    <div style="font-size:12px;color:#aaa;margin-top:4px;">Member can log in to the loyalty portal</div>
                <?php else: ?>
                    <span class="status-inactive"><i class="fa fa-circle-o"></i> No Password Set</span>
                    <div style="font-size:12px;color:#aaa;margin-top:4px;">Member has not claimed their portal account yet</div>
                <?php endif; ?>

                <?php if ($acct_status === 'banned'): ?>
                <div style="margin-top:6px;"><span class="status-banned"><i class="fa fa-ban"></i> Account Suspended</span></div>
                <?php endif; ?>
            </div>
            <div class="col-sm-4">
                <div class="section-label">Personal Details</div>
                <table class="table no-margin" style="font-size:13px;">
                    <tr>
                        <td class="text-muted" style="border:none;padding:3px 8px 3px 0;width:80px;">Birthday</td>
                        <td style="border:none;padding:3px 0;">
                            <?php echo $customer['birthday'] ? date('d M Y', strtotime($customer['birthday'])) : '<span class="text-muted">—</span>'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:3px 8px 3px 0;">PDPA</td>
                        <td style="border:none;padding:3px 0;">
                            <?php if (!empty($customer['pdpa_consented_at'])): ?>
                            <span class="text-success"><i class="fa fa-check"></i> Consented</span>
                            <span style="font-size:11px;color:#aaa;"> (<?php echo date('d/m/Y', strtotime($customer['pdpa_consented_at'])); ?>)</span>
                            <?php else: ?>
                            <span class="text-muted">Not yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="text-muted" style="border:none;padding:3px 8px 3px 0;">Txns</td>
                        <td style="border:none;padding:3px 0;"><?php echo number_format((int)($customer['total_transactions'] ?? 0)); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-sm-4">
                <div class="section-label">Address</div>
                <?php
                    $addr_parts = array_filter([
                        $customer['address1'] ?? '',
                        $customer['address2'] ?? '',
                        $customer['city'] ?? '',
                        $customer['state'] ?? '',
                        $customer['postcode'] ?? '',
                    ]);
                ?>
                <?php if (!empty($addr_parts)): ?>
                <div style="font-size:13px;color:#555;line-height:1.6;">
                    <?php echo nl2br(htmlspecialchars(implode("\n", $addr_parts))); ?>
                </div>
                <?php else: ?>
                <span class="text-muted" style="font-size:13px;">No address on file</span>
                <?php endif; ?>

                <?php if (has_permission('loyalty', '', 'edit')): ?>
                <div style="margin-top:10px;">
                    <button class="btn btn-default btn-xs" data-toggle="modal" data-target="#editModal">
                        <i class="fa fa-pencil"></i> Edit Profile
                    </button>
                    <?php if ($acct_status !== 'banned'): ?>
                    <button class="btn btn-warning btn-xs" onclick="setAccountStatus(<?php echo (int)$customer['id']; ?>, 'banned')"
                        title="Suspend this member's portal access">
                        <i class="fa fa-ban"></i> Suspend
                    </button>
                    <?php else: ?>
                    <button class="btn btn-success btn-xs" onclick="setAccountStatus(<?php echo (int)$customer['id']; ?>, 'active')"
                        title="Re-activate this member's portal access">
                        <i class="fa fa-check"></i> Unsuspend
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Transaction History -->
    <h5 style="margin-bottom:12px;font-weight:600;">Points History
        <span class="text-muted" style="font-weight:400;font-size:13px;">(<?php echo number_format($result['total']); ?> records)</span>
    </h5>

    <?php if ($result['page_count'] > 1): ?>
    <div class="row" style="margin-bottom:10px;">
        <div class="col-sm-12 text-right">
            <div class="pagination-wrap" style="justify-content:flex-end;display:flex;gap:8px;align-items:center;">
                <?php if ($result['page'] > 1): ?>
                <a href="<?php echo admin_url('loyalty/customer/' . $customer['id'] . '?page=' . ($result['page'] - 1)); ?>" class="btn btn-default btn-xs"><i class="fa fa-chevron-left"></i></a>
                <?php endif; ?>
                <span class="text-muted" style="font-size:12px;">Page <?php echo $result['page']; ?> of <?php echo $result['page_count']; ?></span>
                <?php if ($result['page'] < $result['page_count']): ?>
                <a href="<?php echo admin_url('loyalty/customer/' . $customer['id'] . '?page=' . ($result['page'] + 1)); ?>" class="btn btn-default btn-xs"><i class="fa fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin" id="txn-history">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Type</th>
                        <th class="text-right">Points</th>
                        <th>Description</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($txns)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding:30px;">No transactions yet.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($txns as $t): ?>
                    <?php
                        $type_class = 'txn-type-' . $t['type'];
                        $type_label = ucfirst($t['type']);
                        $sign       = $t['type'] === 'earn' ? '+' : ($t['type'] === 'redeem' ? '−' : ($t['points'] >= 0 ? '+' : ''));
                    ?>
                    <tr>
                        <td style="white-space:nowrap;color:#337ab7;">
                            <?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?>
                        </td>
                        <td><span class="<?php echo $type_class; ?>"><?php echo $type_label; ?></span></td>
                        <td class="text-right <?php echo $type_class; ?>">
                            <?php echo $sign . number_format(abs((float)$t['points']), 2); ?>
                        </td>
                        <td class="text-muted"><?php echo htmlspecialchars($t['description'] ?: '—'); ?></td>
                        <td style="font-family:monospace;font-size:12px;">
                            <?php if (!empty($t['receipt_number'])): ?>
                            <a href="<?php echo admin_url('pos/transaction/' . urlencode($t['receipt_number'])); ?>" onclick="event.stopPropagation();">
                                <?php echo htmlspecialchars($t['receipt_number']); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
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

<!-- Edit Profile Modal -->
<?php if (has_permission('loyalty', '', 'edit')): ?>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Member Profile</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-id" value="<?php echo (int)$customer['id']; ?>">
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" id="edit-name" class="form-control" value="<?php echo htmlspecialchars($customer['name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" id="edit-phone" class="form-control" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="edit-email" class="form-control" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Birthday</label>
                            <input type="date" id="edit-birthday" class="form-control" value="<?php echo htmlspecialchars($customer['birthday'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address Line 1</label>
                    <input type="text" id="edit-address1" class="form-control" value="<?php echo htmlspecialchars($customer['address1'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Address Line 2</label>
                    <input type="text" id="edit-address2" class="form-control" value="<?php echo htmlspecialchars($customer['address2'] ?? ''); ?>">
                </div>
                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" id="edit-city" class="form-control" value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>State</label>
                            <input type="text" id="edit-state" class="form-control" value="<?php echo htmlspecialchars($customer['state'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Postcode</label>
                            <input type="text" id="edit-postcode" class="form-control" value="<?php echo htmlspecialchars($customer['postcode'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                <div id="edit-msg" style="display:none;" class="alert alert-danger"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="editSaveBtn" onclick="saveProfile()">
                    <i class="fa fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function saveProfile() {
    var btn = $('#editSaveBtn').prop('disabled', true).text('Saving…');
    $.post('<?php echo admin_url('loyalty/ajax_update_customer'); ?>', {
        id:       $('#edit-id').val(),
        name:     $('#edit-name').val(),
        phone:    $('#edit-phone').val(),
        email:    $('#edit-email').val(),
        birthday: $('#edit-birthday').val(),
        address1: $('#edit-address1').val(),
        address2: $('#edit-address2').val(),
        city:     $('#edit-city').val(),
        state:    $('#edit-state').val(),
        postcode: $('#edit-postcode').val(),
    }, function(r) {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Changes');
        if (r.success) { $('#editModal').modal('hide'); location.reload(); }
        else { $('#edit-msg').text(r.message || 'Save failed').show(); }
    }, 'json');
}

function setAccountStatus(id, status) {
    var label = status === 'banned' ? 'suspend' : 'unsuspend';
    if (!confirm('Are you sure you want to ' + label + ' this member\'s portal access?')) return;
    $.post('<?php echo admin_url('loyalty/ajax_set_account_status'); ?>', { id: id, status: status }, function(r) {
        if (r.success) { location.reload(); }
        else { alert(r.message || 'Action failed'); }
    }, 'json');
}
</script>
<?php endif; ?>

<!-- Recovery Voucher Modal -->
<?php if (has_permission('loyalty', '', 'edit')): ?>
<div class="modal fade" id="recoveryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-ticket"></i> Issue Recovery Voucher</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:13px;margin-bottom:14px;">
                    Issue a voucher to <strong><?php echo htmlspecialchars($customer['name'] ?: $customer['phone']); ?></strong> as a service recovery gesture.
                </p>
                <div class="form-group">
                    <label>Voucher</label>
                    <select id="rec-voucher" class="form-control">
                        <option value="">— Select a voucher —</option>
                        <?php foreach ($active_vouchers as $v):
                            $reward = $v['reward_type'] === 'free_item'
                                ? 'Free: ' . ($v['reward_item'] ?: '—')
                                : ($v['reward_type'] === 'discount_pct'
                                    ? number_format((float)$v['reward_value'], 0) . '% off'
                                    : 'RM ' . number_format((float)$v['reward_value'], 2) . ' off');
                        ?>
                        <option value="<?php echo (int)$v['id']; ?>"
                                data-mode="<?php echo htmlspecialchars($v['code_mode']); ?>"
                                data-code="<?php echo htmlspecialchars($v['base_code']); ?>">
                            <?php echo htmlspecialchars($v['title']); ?> — <?php echo htmlspecialchars($reward); ?>
                            <?php if ($v['valid_until']): ?>(exp <?php echo date('d/m/Y', strtotime($v['valid_until'])); ?>)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="rec-sms-wrap">
                    <label>
                        <input type="checkbox" id="rec-send-sms" <?php echo !empty($customer['phone']) ? '' : 'disabled'; ?>>
                        Send SMS to <?php echo htmlspecialchars($customer['phone'] ?: '(no phone on file)'); ?>
                    </label>
                </div>
                <div class="form-group">
                    <label>Note <small class="text-muted">(appended to SMS if sending)</small></label>
                    <input type="text" id="rec-note" class="form-control" placeholder="e.g. Sorry for your experience today." maxlength="200">
                </div>
                <div id="rec-result" style="display:none;" class="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-warning" id="rec-submit" onclick="issueRecoveryVoucher()">
                    <i class="fa fa-ticket"></i> Issue Voucher
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function issueRecoveryVoucher() {
    var vid = $('#rec-voucher').val();
    if (!vid) { alert('Please select a voucher.'); return; }

    var btn = $('#rec-submit').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Issuing…');
    $('#rec-result').hide();

    $.post('<?php echo admin_url('loyalty/ajax_issue_recovery_voucher'); ?>', {
        customer_id: <?php echo (int)$customer['id']; ?>,
        voucher_id:  vid,
        send_sms:    $('#rec-send-sms').is(':checked') ? 1 : 0,
        note:        $('#rec-note').val()
    }, function(r) {
        btn.prop('disabled', false).html('<i class="fa fa-ticket"></i> Issue Voucher');
        var el = $('#rec-result');
        if (r.success) {
            var msg = 'Voucher code: <strong style="font-size:15px;letter-spacing:1px;">' + r.code + '</strong>';
            if (r.sms_sent) msg += ' &mdash; <span class="text-success"><i class="fa fa-check"></i> SMS sent</span>';
            el.removeClass('alert-danger').addClass('alert-success').html(msg).show();
            $('#rec-submit').hide();
        } else {
            el.removeClass('alert-success').addClass('alert-danger').text(r.message || 'Failed to issue voucher.').show();
        }
    }, 'json');
}

$('#recoveryModal').on('hidden.bs.modal', function() {
    $('#rec-voucher').val('');
    $('#rec-send-sms').prop('checked', false);
    $('#rec-note').val('');
    $('#rec-result').hide().removeClass('alert-success alert-danger');
    $('#rec-submit').show().prop('disabled', false).html('<i class="fa fa-ticket"></i> Issue Voucher');
});
</script>
<?php endif; ?>

<!-- Adjust Points Modal -->
<?php if (has_permission('loyalty', '', 'edit')): ?>
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Adjust Points</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Points <small class="text-muted">(use negative to deduct)</small></label>
                    <input type="number" id="adj-points" class="form-control" step="0.01" placeholder="e.g. 10 or -5">
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" id="adj-desc" class="form-control" placeholder="Optional note" maxlength="255">
                </div>
                <div id="adj-msg" style="display:none;" class="alert alert-danger"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="adj-submit">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('adj-submit').addEventListener('click', function () {
    var points = parseFloat(document.getElementById('adj-points').value);
    var desc   = document.getElementById('adj-desc').value.trim();
    var msg    = document.getElementById('adj-msg');

    if (isNaN(points) || points === 0) {
        msg.textContent = 'Enter a non-zero points value.';
        msg.style.display = 'block';
        return;
    }

    msg.style.display = 'none';
    this.disabled = true;
    this.textContent = 'Saving…';

    fetch('<?php echo admin_url('loyalty/manual_adjust'); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            customer_id: '<?php echo (int)$customer['id']; ?>',
            points:      points,
            description: desc,
            <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
        })
    })
    .then(r => r.json())
    .then(function (res) {
        if (res.success) {
            location.reload();
        } else {
            msg.textContent = res.message || 'Adjustment failed.';
            msg.style.display = 'block';
            document.getElementById('adj-submit').disabled = false;
            document.getElementById('adj-submit').textContent = 'Save';
        }
    });
});
</script>
<?php endif; ?>

<?php init_tail(); ?>
