<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.notif-row { background:#fff; border:1px solid #e0e0e0; border-radius:5px; padding:14px 18px; margin-bottom:10px; position:relative; }
.notif-row .notif-title { font-size:14px; font-weight:600; color:#222; margin-bottom:2px; }
.notif-row .notif-msg   { font-size:13px; color:#555; margin-bottom:8px; }
.notif-row .notif-meta  { font-size:12px; color:#aaa; }
.notif-row .notif-actions { position:absolute; top:12px; right:14px; }
.type-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; text-transform:uppercase; }
.type-promo  { background:#d4edda; color:#155724; }
.type-event  { background:#cce5ff; color:#004085; }
.type-info   { background:#e2e3e5; color:#383d41; }
.type-system { background:#f8d7da; color:#721c24; }
.target-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:11px; background:#fff3cd; color:#856404; }
.empty-state { text-align:center; padding:60px 20px; color:#aaa; }
.empty-state i { font-size:48px; margin-bottom:12px; display:block; }
</style>

<div id="wrapper">
<div class="content">

    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Notifications</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">Notifications</li>
            </ol>
        </div>
        <?php if (has_permission('loyalty', '', 'create')): ?>
        <div class="col-sm-6 text-right" style="padding-top:6px;">
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#sendModal">
                <i class="fa fa-bell"></i> Send Notification
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
    <div class="empty-state">
        <i class="fa fa-bell-slash-o"></i>
        <p>No notifications sent yet.</p>
        <?php if (has_permission('loyalty', '', 'create')): ?>
        <button class="btn btn-primary" data-toggle="modal" data-target="#sendModal">Send First Notification</button>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <?php foreach ($rows as $n): ?>
    <div class="notif-row" id="notif-<?php echo $n['id']; ?>">
        <div class="notif-actions">
            <?php if (has_permission('loyalty', '', 'delete')): ?>
            <button class="btn btn-danger btn-xs" onclick="deleteNotif(<?php echo (int)$n['id']; ?>)">
                <i class="fa fa-trash"></i>
            </button>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:6px;">
            <span class="type-badge type-<?php echo $n['type']; ?>"><?php echo ucfirst($n['type']); ?></span>
            &nbsp;
            <?php if (is_null($n['customer_id'])): ?>
                <span class="target-badge"><i class="fa fa-globe"></i> Broadcast</span>
            <?php elseif ($n['customer_name']): ?>
                <span class="target-badge">
                    <i class="fa fa-user"></i>
                    <a href="<?php echo admin_url('loyalty/customer/' . (int)$n['customer_id']); ?>" style="color:inherit;">
                        <?php echo htmlspecialchars($n['customer_name']); ?>
                    </a>
                </span>
            <?php else: ?>
                <span class="target-badge"><i class="fa fa-user"></i> Member #<?php echo (int)$n['customer_id']; ?></span>
            <?php endif; ?>
            <?php if ($n['promotion_title']): ?>
            <span class="label label-default" style="margin-left:4px;"><?php echo htmlspecialchars($n['promotion_title']); ?></span>
            <?php endif; ?>
        </div>

        <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
        <div class="notif-msg"><?php echo nl2br(htmlspecialchars($n['message'])); ?></div>
        <div class="notif-meta">
            <i class="fa fa-clock-o"></i> <?php echo date('d M Y, g:ia', strtotime($n['created_at'])); ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($result['page_count'] > 1): ?>
    <div style="margin-top:16px;">
        <?php for ($p = 1; $p <= $result['page_count']; $p++): ?>
        <a href="<?php echo admin_url('loyalty/notifications?page=' . $p); ?>"
           class="btn btn-sm <?php echo $p === $result['page'] ? 'btn-primary' : 'btn-default'; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
</div>

<!-- Send Notification Modal -->
<div class="modal fade" id="sendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Send Notification</h4>
            </div>
            <div class="modal-body">

                <div class="form-group">
                    <label>Title <span class="text-danger">*</span></label>
                    <input type="text" id="notif_title" class="form-control" placeholder="Notification title">
                </div>

                <div class="form-group">
                    <label>Message <span class="text-danger">*</span></label>
                    <textarea id="notif_message" class="form-control" rows="3" placeholder="Your message to members..."></textarea>
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Type</label>
                            <select id="notif_type" class="form-control">
                                <option value="info">Info</option>
                                <option value="promo">Promotion</option>
                                <option value="event">Event</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Linked Promotion</label>
                            <select id="notif_promotion_id" class="form-control">
                                <option value="">None</option>
                                <?php foreach ($promotions as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars($p['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Send To</label>
                    <select id="notif_target" class="form-control" onchange="toggleTargetFields()">
                        <option value="all">All Members (Broadcast)</option>
                        <?php if (!empty($tiers)): ?>
                        <option value="tier">Specific Tier</option>
                        <?php endif; ?>
                        <option value="individual">Individual Member</option>
                    </select>
                </div>

                <div id="target_tier_wrap" style="display:none;">
                    <div class="form-group">
                        <label>Tier</label>
                        <select id="notif_target_tier" class="form-control">
                            <?php foreach ($tiers as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div id="target_customer_wrap" style="display:none;">
                    <div class="form-group">
                        <label>Member Phone Number</label>
                        <input type="text" id="notif_customer_phone" class="form-control" placeholder="Search by phone...">
                        <input type="hidden" id="notif_customer_id" value="">
                        <div id="customer_search_results" style="border:1px solid #ddd;border-top:0;max-height:150px;overflow-y:auto;display:none;background:#fff;"></div>
                    </div>
                </div>

                <div id="send_preview" class="alert alert-info" style="display:none;font-size:13px;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendBtn" onclick="sendNotification()">
                    <i class="fa fa-paper-plane"></i> Send
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTargetFields() {
    var target = $('#notif_target').val();
    $('#target_tier_wrap').toggle(target === 'tier');
    $('#target_customer_wrap').toggle(target === 'individual');
    $('#notif_customer_id').val('');
    updatePreview();
}

function updatePreview() {
    var target = $('#notif_target').val();
    var preview = $('#send_preview');
    if (target === 'all') {
        preview.text('This notification will be sent as a broadcast to all members.').show();
    } else if (target === 'tier') {
        preview.text('This notification will be sent to all members in the selected tier.').show();
    } else {
        preview.hide();
    }
}

// Live customer search
var searchTimer;
$('#notif_customer_phone').on('input', function() {
    clearTimeout(searchTimer);
    var q = $.trim($(this).val());
    if (q.length < 3) { $('#customer_search_results').hide(); return; }
    searchTimer = setTimeout(function() {
        $.get('<?php echo admin_url('loyalty/customers'); ?>', { q: q, limit: 10, format: 'json' }, function(html) {
            // Fallback: parse name+phone from table rows since we don't have a JSON endpoint yet
        });
        // Simple approach: fetch via AJAX search
        $.ajax({
            url: '<?php echo admin_url('loyalty/ajax_search_customers'); ?>',
            data: { q: q },
            success: function(r) {
                if (!r.rows || !r.rows.length) { $('#customer_search_results').hide(); return; }
                var html = '';
                $.each(r.rows, function(i, c) {
                    html += '<div class="customer-result" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;" ' +
                        'data-id="' + c.id + '" data-name="' + $('<div>').text(c.name || c.phone).html() + '">' +
                        '<strong>' + $('<div>').text(c.name || '—').html() + '</strong> ' +
                        '<span style="color:#aaa;">' + $('<div>').text(c.phone || '').html() + '</span></div>';
                });
                $('#customer_search_results').html(html).show();
            },
            dataType: 'json'
        });
    }, 300);
});

$(document).on('click', '.customer-result', function() {
    $('#notif_customer_id').val($(this).data('id'));
    $('#notif_customer_phone').val($(this).data('name'));
    $('#customer_search_results').hide();
});

function sendNotification() {
    var title   = $.trim($('#notif_title').val());
    var message = $.trim($('#notif_message').val());
    var target  = $('#notif_target').val();

    if (!title)   { alert('Title is required'); return; }
    if (!message) { alert('Message is required'); return; }
    if (target === 'individual' && !$('#notif_customer_id').val()) {
        alert('Please select a member'); return;
    }

    var btn = $('#sendBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');

    var data = {
        title:        title,
        message:      message,
        type:         $('#notif_type').val(),
        target:       target,
        promotion_id: $('#notif_promotion_id').val(),
        target_tier:  target === 'tier' ? $('#notif_target_tier').val() : '',
        customer_id:  target === 'individual' ? $('#notif_customer_id').val() : '',
    };

    $.post('<?php echo admin_url('loyalty/ajax_send_notification'); ?>', data, function(r) {
        btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send');
        if (r.success) {
            $('#sendModal').modal('hide');
            alert('Notification sent to ' + (r.sent || 1) + ' member(s).');
            location.reload();
        } else {
            alert(r.message || 'Failed to send notification');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="fa fa-paper-plane"></i> Send');
        alert('Request failed. Please try again.');
    });
}

function deleteNotif(id) {
    if (!confirm('Delete this notification?')) return;
    $.post('<?php echo admin_url('loyalty/ajax_delete_notification'); ?>', { id: id }, function(r) {
        if (r.success) {
            $('#notif-' + id).fadeOut(300, function() { $(this).remove(); });
        }
    }, 'json');
}

// Init
toggleTargetFields();
</script>

<?php init_tail(); ?>
