<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.promo-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:16px 20px; margin-bottom:12px; position:relative; }
.promo-card .promo-title { font-size:15px; font-weight:600; color:#222; margin:6px 0 3px; }
.promo-card .promo-desc  { font-size:13px; color:#666; margin-bottom:8px; white-space:pre-line; }
.promo-card .promo-meta  { font-size:12px; color:#aaa; }
.promo-actions { position:absolute; top:12px; right:12px; display:flex; gap:4px; }
.type-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; text-transform:uppercase; }
.type-announcement { background:#fff3cd; color:#856404; }
.type-discount     { background:#d4edda; color:#155724; }
.type-event        { background:#cce5ff; color:#004085; }
.type-freebie      { background:#f8d7da; color:#721c24; }
.type-other        { background:#e2e3e5; color:#383d41; }
.trigger-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:11px; background:#e8d5f5; color:#5a189a; font-weight:600; }
.notif-icons { display:inline-flex; gap:5px; align-items:center; margin-left:6px; }
.notif-icons .ni { font-size:12px; padding:2px 6px; border-radius:8px; font-weight:600; }
.ni-push { background:#d0ebff; color:#1971c2; }
.ni-sms  { background:#d3f9d8; color:#2f9e44; }
.status-active   { color:#5cb85c; font-weight:600; font-size:12px; }
.status-inactive { color:#aaa; font-size:12px; }
.notify-status-sent      { color:#5cb85c; }
.notify-status-pending   { color:#f0ad4e; }
.notify-status-recurring { color:#9b59b6; }
.empty-state { text-align:center; padding:60px 20px; color:#aaa; }
.empty-state i { font-size:48px; margin-bottom:12px; display:block; }
.modal-section { border-top:1px solid #f0f0f0; margin:16px -15px 0; padding:14px 15px 0; }
.modal-section-title { font-size:11px; font-weight:700; text-transform:uppercase; color:#aaa; letter-spacing:.5px; margin-bottom:12px; }
</style>

<div id="wrapper">
<div class="content">

    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Promotions</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">Promotions</li>
            </ol>
        </div>
        <?php if (has_permission('loyalty', '', 'create')): ?>
        <div class="col-sm-6 text-right" style="padding-top:6px;">
            <button class="btn btn-primary btn-sm" onclick="openPromoModal()">
                <i class="fa fa-plus"></i> New Promotion
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
    <div class="empty-state">
        <i class="fa fa-bullhorn"></i>
        <p>No promotions yet.</p>
        <?php if (has_permission('loyalty', '', 'create')): ?>
        <button class="btn btn-primary" onclick="openPromoModal()">Create Promotion</button>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <?php foreach ($rows as $promo): ?>
    <?php
        $has_notify   = !empty($promo['notify_push']) || !empty($promo['notify_sms']);
        $notify_status = $promo['notify_status'] ?? 'pending';
        $trigger_type  = $promo['trigger_type'] ?? 'standard';
        $trigger_labels = ['standard' => 'Standard', 'birthday' => 'Birthday', 'anniversary' => 'Anniversary'];
    ?>
    <div class="promo-card" id="promo-<?php echo $promo['id']; ?>">
        <div class="promo-actions">
            <?php if ($has_notify && has_permission('loyalty', '', 'edit')): ?>
            <button class="btn btn-success btn-xs" onclick="blastPromo(<?php echo (int)$promo['id']; ?>, <?php echo htmlspecialchars(json_encode($promo['title'])); ?>)" title="Blast Now">
                <i class="fa fa-bullhorn"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'edit')): ?>
            <button class="btn btn-default btn-xs" onclick="editPromo(<?php echo htmlspecialchars(json_encode($promo)); ?>)">
                <i class="fa fa-pencil"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'delete')): ?>
            <button class="btn btn-danger btn-xs" onclick="deletePromo(<?php echo (int)$promo['id']; ?>, <?php echo htmlspecialchars(json_encode($promo['title'])); ?>)">
                <i class="fa fa-trash"></i>
            </button>
            <?php endif; ?>
        </div>

        <div style="padding-right:110px;">
            <span class="type-badge type-<?php echo $promo['type']; ?>"><?php echo ucfirst($promo['type']); ?></span>
            <?php if ($trigger_type !== 'standard'): ?>
            <span class="trigger-badge"><i class="fa fa-refresh"></i> <?php echo $trigger_labels[$trigger_type] ?? $trigger_type; ?></span>
            <?php endif; ?>
            <?php if ($has_notify): ?>
            <span class="notif-icons">
                <?php if (!empty($promo['notify_push'])): ?><span class="ni ni-push"><i class="fa fa-bell"></i> Push</span><?php endif; ?>
                <?php if (!empty($promo['notify_sms'])): ?><span class="ni ni-sms"><i class="fa fa-mobile"></i> SMS</span><?php endif; ?>
            </span>
            <?php endif; ?>
            &nbsp;
            <?php if ($promo['is_active']): ?>
            <span class="status-active"><i class="fa fa-circle" style="font-size:8px;"></i> Active</span>
            <?php else: ?>
            <span class="status-inactive"><i class="fa fa-circle" style="font-size:8px;"></i> Inactive</span>
            <?php endif; ?>
        </div>

        <div class="promo-title"><?php echo htmlspecialchars($promo['title']); ?></div>

        <?php if ($promo['description']): ?>
        <div class="promo-desc"><?php echo htmlspecialchars($promo['description']); ?></div>
        <?php endif; ?>

        <div class="promo-meta">
            <?php if ($trigger_type === 'standard' && ($promo['start_date'] || $promo['end_date'])): ?>
            <i class="fa fa-calendar"></i>
            <?php echo $promo['start_date'] ? date('d M Y', strtotime($promo['start_date'])) : 'Now'; ?>
            &rarr;
            <?php echo $promo['end_date'] ? date('d M Y', strtotime($promo['end_date'])) : 'No end date'; ?>
            &nbsp;&bull;&nbsp;
            <?php elseif ($trigger_type === 'birthday'): ?>
            <i class="fa fa-birthday-cake"></i> Triggers on member birthdays
            <?php if (!empty($promo['notify_days_before'])): ?>
            (<?php echo (int)$promo['notify_days_before']; ?> day(s) before)
            <?php endif; ?>
            &nbsp;&bull;&nbsp;
            <?php elseif ($trigger_type === 'anniversary'): ?>
            <i class="fa fa-star"></i> Triggers on member anniversaries
            <?php if (!empty($promo['notify_days_before'])): ?>
            (<?php echo (int)$promo['notify_days_before']; ?> day(s) before)
            <?php endif; ?>
            &nbsp;&bull;&nbsp;
            <?php endif; ?>
            <?php if ($promo['target_tier']): ?>
            <i class="fa fa-users"></i> <?php echo htmlspecialchars($promo['target_tier']); ?> tier &nbsp;&bull;&nbsp;
            <?php endif; ?>
            <?php if ($has_notify): ?>
            <?php if ($notify_status === 'sent'): ?>
            <span class="notify-status-sent"><i class="fa fa-check-circle"></i> Blasted <?php echo $promo['notified_at'] ? date('d M Y', strtotime($promo['notified_at'])) : ''; ?></span>
            <?php elseif ($notify_status === 'recurring'): ?>
            <span class="notify-status-recurring"><i class="fa fa-refresh"></i> Recurring &mdash; last run <?php echo $promo['notified_at'] ? date('d M Y', strtotime($promo['notified_at'])) : 'never'; ?></span>
            <?php else: ?>
            <span class="notify-status-pending"><i class="fa fa-clock-o"></i> Not blasted yet</span>
            <?php endif; ?>
            &nbsp;&bull;&nbsp;
            <?php endif; ?>
            <i class="fa fa-clock-o"></i> Created <?php echo date('d M Y', strtotime($promo['created_at'])); ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($result['page_count'] > 1): ?>
    <div style="margin-top:16px;">
        <?php for ($p = 1; $p <= $result['page_count']; $p++): ?>
        <a href="<?php echo admin_url('loyalty/promotions?page=' . $p); ?>"
           class="btn btn-sm <?php echo $p === $result['page'] ? 'btn-primary' : 'btn-default'; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
</div>

<!-- Promotion Modal -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="promoModalTitle">New Promotion</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="promo_id" value="">

                <!-- SECTION: Basic Info -->
                <div class="modal-section-title">Promotion Details</div>

                <div class="form-group">
                    <label>Title <span class="text-danger">*</span></label>
                    <input type="text" id="promo_title" class="form-control" placeholder="e.g. Ramadan Special Discount">
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Type</label>
                            <select id="promo_type" class="form-control">
                                <option value="announcement">Announcement</option>
                                <option value="discount">Discount</option>
                                <option value="event">Event</option>
                                <option value="freebie">Freebie</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Image URL <span class="text-muted" style="font-size:11px;">(optional)</span></label>
                            <input type="text" id="promo_image_url" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="promo_description" class="form-control" rows="3" placeholder="Details, terms, or message to send to members..."></textarea>
                </div>

                <!-- SECTION: Trigger -->
                <div class="modal-section">
                    <div class="modal-section-title">Promo Trigger</div>
                    <div class="row">
                        <div class="col-sm-4">
                            <label class="radio-inline" style="border:1px solid #ddd;border-radius:4px;padding:8px 12px;cursor:pointer;display:block;margin:0 0 8px;">
                                <input type="radio" name="trigger_type" id="trigger_standard" value="standard" checked>
                                &nbsp;<strong>Standard</strong><br>
                                <span style="font-size:11px;color:#888;padding-left:16px;">Runs between a start &amp; end date</span>
                            </label>
                        </div>
                        <div class="col-sm-4">
                            <label class="radio-inline" style="border:1px solid #ddd;border-radius:4px;padding:8px 12px;cursor:pointer;display:block;margin:0 0 8px;">
                                <input type="radio" name="trigger_type" id="trigger_birthday" value="birthday">
                                &nbsp;<strong>Birthday Freebie</strong><br>
                                <span style="font-size:11px;color:#888;padding-left:16px;">Sends on each member's birthday, every year</span>
                            </label>
                        </div>
                        <div class="col-sm-4">
                            <label class="radio-inline" style="border:1px solid #ddd;border-radius:4px;padding:8px 12px;cursor:pointer;display:block;margin:0 0 8px;">
                                <input type="radio" name="trigger_type" id="trigger_anniversary" value="anniversary">
                                &nbsp;<strong>Anniversary Reward</strong><br>
                                <span style="font-size:11px;color:#888;padding-left:16px;">Sends on each member's signup anniversary</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SECTION: Schedule (Standard only) -->
                <div class="modal-section" id="schedule_section">
                    <div class="modal-section-title">Schedule</div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" id="promo_start_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>End Date <span class="text-muted" style="font-size:11px;">(leave blank = no expiry)</span></label>
                                <input type="date" id="promo_end_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION: Audience -->
                <div class="modal-section" id="audience_section">
                    <div class="modal-section-title">Audience</div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Send To</label>
                                <select id="promo_target" class="form-control" onchange="toggleTargetFields()">
                                    <option value="all">All Members</option>
                                    <?php if (!empty($tiers)): ?>
                                    <option value="tier">Specific Tier</option>
                                    <?php endif; ?>
                                    <option value="individual">Individual Member</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6" id="tier_wrap" style="display:none;">
                            <div class="form-group">
                                <label>Tier</label>
                                <select id="promo_target_tier" class="form-control">
                                    <option value="">— Select tier —</option>
                                    <?php foreach ($tiers as $t): ?>
                                    <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6" id="individual_wrap" style="display:none;">
                            <div class="form-group">
                                <label>Member</label>
                                <input type="text" id="promo_customer_phone" class="form-control" placeholder="Search by phone or name...">
                                <input type="hidden" id="promo_target_customer_id" value="">
                                <div id="promo_customer_results" style="border:1px solid #ddd;border-top:0;max-height:140px;overflow-y:auto;display:none;background:#fff;position:absolute;z-index:999;width:calc(100% - 30px);"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Birthday/anniversary tier filter -->
                    <div id="recurring_tier_wrap" style="display:none;">
                        <div class="form-group" style="max-width:260px;">
                            <label>Filter by Tier <span class="text-muted" style="font-size:11px;">(optional)</span></label>
                            <select id="promo_target_tier_recurring" class="form-control">
                                <option value="">All tiers</option>
                                <?php foreach ($tiers as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['name']); ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- SECTION: Notifications -->
                <div class="modal-section">
                    <div class="modal-section-title">Notifications</div>

                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label style="font-weight:normal;">
                                    <input type="checkbox" id="promo_notify_push" value="1">
                                    &nbsp;<i class="fa fa-bell" style="color:#1971c2;"></i> <strong>Push Notification</strong>
                                </label>
                                <p class="help-block" style="font-size:11px;">Sends to member app notification bell</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label style="font-weight:normal;">
                                    <input type="checkbox" id="promo_notify_sms" value="1">
                                    &nbsp;<i class="fa fa-mobile" style="color:#2f9e44;"></i> <strong>SMS (Twilio)</strong>
                                </label>
                                <p class="help-block" style="font-size:11px;">Sends text to member's phone number</p>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label>Send</label>
                                <select id="promo_notify_days_before" class="form-control">
                                    <option value="0">Immediately (on save)</option>
                                    <option value="1">1 day before</option>
                                    <option value="2">2 days before</option>
                                    <option value="3">3 days before</option>
                                    <option value="5">5 days before</option>
                                    <option value="7">7 days before</option>
                                    <option value="14">14 days before</option>
                                </select>
                                <p class="help-block" style="font-size:11px;" id="days_before_hint">For birthday/anniversary: days before the event to send</p>
                            </div>
                        </div>
                    </div>

                    <div id="blast_preview" class="alert alert-info" style="display:none;font-size:13px;margin-bottom:0;"></div>
                </div>

                <!-- SECTION: Settings -->
                <div class="modal-section">
                    <div class="modal-section-title">Settings</div>
                    <label>
                        <input type="checkbox" id="promo_is_active" value="1" checked>
                        &nbsp;Active — visible in member app
                    </label>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="promoSaveBtn" onclick="savePromo()">
                    <i class="fa fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ── Customer search ───────────────────────────────────────────────────────────
var _searchTimer;
$('#promo_customer_phone').on('input', function() {
    clearTimeout(_searchTimer);
    var q = $.trim($(this).val());
    if (q.length < 3) { $('#promo_customer_results').hide(); return; }
    _searchTimer = setTimeout(function() {
        $.ajax({
            url: '<?php echo admin_url('loyalty/ajax_search_customers'); ?>',
            data: { q: q },
            success: function(r) {
                if (!r.rows || !r.rows.length) { $('#promo_customer_results').hide(); return; }
                var html = '';
                $.each(r.rows, function(i, c) {
                    html += '<div class="promo-cust-result" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;" ' +
                        'data-id="' + c.id + '" data-label="' + $('<div>').text((c.name||'') + ' ' + (c.phone||'')).html() + '">' +
                        '<strong>' + $('<div>').text(c.name||'—').html() + '</strong> ' +
                        '<span style="color:#aaa;">' + $('<div>').text(c.phone||'').html() + '</span></div>';
                });
                $('#promo_customer_results').html(html).show();
            },
            dataType: 'json'
        });
    }, 300);
});
$(document).on('click', '.promo-cust-result', function() {
    $('#promo_target_customer_id').val($(this).data('id'));
    $('#promo_customer_phone').val($(this).data('label'));
    $('#promo_customer_results').hide();
});

// ── Trigger type toggle ───────────────────────────────────────────────────────
$('input[name="trigger_type"]').on('change', updateTriggerUI);

function updateTriggerUI() {
    var trigger = $('input[name="trigger_type"]:checked').val();
    var isRecurring = (trigger === 'birthday' || trigger === 'anniversary');
    $('#schedule_section').toggle(!isRecurring);
    $('#audience_section #promo_target').closest('.col-sm-6').toggle(!isRecurring);
    $('#tier_wrap, #individual_wrap').hide();
    $('#recurring_tier_wrap').toggle(isRecurring);
    $('#days_before_hint').text(isRecurring
        ? 'Days before birthday/anniversary to send'
        : 'Days before start date to send. 0 = send immediately on save.');
    updateBlastPreview();
}

function toggleTargetFields() {
    var t = $('#promo_target').val();
    $('#tier_wrap').toggle(t === 'tier');
    $('#individual_wrap').toggle(t === 'individual');
    $('#promo_target_customer_id').val('');
    updateBlastPreview();
}

function updateBlastPreview() {
    var push = $('#promo_notify_push').is(':checked');
    var sms  = $('#promo_notify_sms').is(':checked');
    var days = parseInt($('#promo_notify_days_before').val()) || 0;
    var trigger = $('input[name="trigger_type"]:checked').val();
    var preview = $('#blast_preview');

    if (!push && !sms) { preview.hide(); return; }

    var channels = [];
    if (push) channels.push('push notification');
    if (sms)  channels.push('SMS');

    var msg;
    if (trigger === 'birthday') {
        msg = 'Will send ' + channels.join(' + ') + ' to members on their birthday' +
              (days > 0 ? ', ' + days + ' day(s) before.' : '.');
        msg += ' Recurs every year. Use the <strong>Blast</strong> button to run today\'s check.';
    } else if (trigger === 'anniversary') {
        msg = 'Will send ' + channels.join(' + ') + ' to members on their signup anniversary' +
              (days > 0 ? ', ' + days + ' day(s) before.' : '.');
        msg += ' Recurs every year. Use the <strong>Blast</strong> button to run today\'s check.';
    } else if (days === 0) {
        msg = 'Will send ' + channels.join(' + ') + ' to the selected audience <strong>immediately on save</strong>.';
    } else {
        var target = $('#promo_start_date').val();
        var sendDate = target ? new Date(new Date(target).getTime() - days * 86400000).toDateString() : '?';
        msg = 'Will send ' + channels.join(' + ') + ' ' + days + ' day(s) before start date (scheduled for ~' + sendDate + '). Use the <strong>Blast</strong> button to send manually.';
    }
    preview.html('<i class="fa fa-info-circle"></i> ' + msg).show();
}

$('#promo_notify_push, #promo_notify_sms').on('change', updateBlastPreview);
$('#promo_notify_days_before').on('change', updateBlastPreview);
$('#promo_start_date').on('change', updateBlastPreview);

// ── Open / Edit ───────────────────────────────────────────────────────────────
function openPromoModal() {
    $('#promo_id').val('');
    $('#promo_title').val('');
    $('#promo_description').val('');
    $('#promo_image_url').val('');
    $('#promo_type').val('announcement');
    $('#promo_start_date').val('');
    $('#promo_end_date').val('');
    $('#promo_target').val('all');
    $('#promo_target_tier').val('');
    $('#promo_target_tier_recurring').val('');
    $('#promo_target_customer_id').val('');
    $('#promo_customer_phone').val('');
    $('#promo_notify_push').prop('checked', false);
    $('#promo_notify_sms').prop('checked', false);
    $('#promo_notify_days_before').val('0');
    $('#promo_is_active').prop('checked', true);
    $('input[name="trigger_type"][value="standard"]').prop('checked', true);
    $('#promoModalTitle').text('New Promotion');
    updateTriggerUI();
    toggleTargetFields();
    updateBlastPreview();
    $('#promoModal').modal('show');
}

function editPromo(promo) {
    $('#promo_id').val(promo.id);
    $('#promo_title').val(promo.title || '');
    $('#promo_description').val(promo.description || '');
    $('#promo_image_url').val(promo.image_url || '');
    $('#promo_type').val(promo.type || 'announcement');
    $('#promo_start_date').val(promo.start_date || '');
    $('#promo_end_date').val(promo.end_date || '');
    $('#promo_is_active').prop('checked', promo.is_active == 1);
    $('#promo_notify_push').prop('checked', promo.notify_push == 1);
    $('#promo_notify_sms').prop('checked', promo.notify_sms == 1);
    $('#promo_notify_days_before').val(promo.notify_days_before || '0');

    var trigger = promo.trigger_type || 'standard';
    $('input[name="trigger_type"][value="' + trigger + '"]').prop('checked', true);

    var target = promo.target || 'all';
    $('#promo_target').val(target);
    $('#promo_target_tier').val(promo.target_tier || '');
    $('#promo_target_tier_recurring').val(promo.target_tier || '');
    $('#promo_target_customer_id').val(promo.target_customer_id || '');
    $('#promo_customer_phone').val('');

    $('#promoModalTitle').text('Edit Promotion');
    updateTriggerUI();
    toggleTargetFields();
    updateBlastPreview();
    $('#promoModal').modal('show');
}

// ── Save ──────────────────────────────────────────────────────────────────────
function savePromo() {
    var title = $.trim($('#promo_title').val());
    if (!title) { alert('Title is required'); return; }

    var trigger = $('input[name="trigger_type"]:checked').val();
    var isRecurring = (trigger === 'birthday' || trigger === 'anniversary');
    var target  = isRecurring ? 'all' : $('#promo_target').val();
    var tier    = isRecurring ? $('#promo_target_tier_recurring').val() : $('#promo_target_tier').val();

    if (target === 'individual' && !$('#promo_target_customer_id').val()) {
        alert('Please search and select a member.'); return;
    }

    var btn = $('#promoSaveBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.post('<?php echo admin_url('loyalty/ajax_save_promotion'); ?>', {
        id:                  $('#promo_id').val(),
        title:               title,
        description:         $('#promo_description').val(),
        image_url:           $('#promo_image_url').val(),
        type:                $('#promo_type').val(),
        start_date:          $('#promo_start_date').val(),
        end_date:            $('#promo_end_date').val(),
        is_active:           $('#promo_is_active').is(':checked') ? 1 : 0,
        trigger_type:        trigger,
        target:              target,
        target_tier:         tier,
        target_customer_id:  $('#promo_target_customer_id').val(),
        notify_push:         $('#promo_notify_push').is(':checked') ? 1 : 0,
        notify_sms:          $('#promo_notify_sms').is(':checked') ? 1 : 0,
        notify_days_before:  $('#promo_notify_days_before').val(),
    }, function(r) {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
        if (r.success) {
            $('#promoModal').modal('hide');
            var msg = 'Promotion saved.';
            if (typeof r.recipients !== 'undefined') {
                msg += '\n\nBlast sent to ' + r.recipients + ' recipient(s).';
                if (r.push_sent) msg += '\nPush: ' + r.push_sent + ' sent.';
                if (r.sms_sent || r.sms_failed) msg += '\nSMS: ' + r.sms_sent + ' sent, ' + (r.sms_failed||0) + ' failed.';
                if (r.sms_error) msg += '\nSMS error: ' + r.sms_error;
            }
            alert(msg);
            location.reload();
        } else {
            alert(r.message || 'Failed to save promotion');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
        alert('Request failed. Please try again.');
    });
}

// ── Blast Now ─────────────────────────────────────────────────────────────────
function blastPromo(id, title) {
    if (!confirm('Send blast for "' + title + '" now?\n\nThis will immediately send notifications to all matching members.')) return;

    var btn = $('[onclick="blastPromo(' + id + ', ' + JSON.stringify(title) + ')"]')
        .prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

    $.post('<?php echo admin_url('loyalty/ajax_blast_promotion'); ?>', { id: id }, function(r) {
        btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i>');
        if (r.success) {
            var msg = 'Blast complete!\n' + r.recipients + ' recipient(s).';
            if (r.push_sent) msg += '\nPush: ' + r.push_sent + ' sent.';
            if (r.sms_sent || r.sms_failed) msg += '\nSMS: ' + r.sms_sent + ' sent, ' + (r.sms_failed||0) + ' failed.';
            if (r.sms_error) msg += '\nSMS: ' + r.sms_error;
            alert(msg);
            location.reload();
        } else {
            alert(r.message || 'Blast failed');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i>');
        alert('Request failed.');
    });
}

// ── Delete ────────────────────────────────────────────────────────────────────
function deletePromo(id, title) {
    if (!confirm('Delete "' + title + '"? This cannot be undone.')) return;
    $.post('<?php echo admin_url('loyalty/ajax_delete_promotion'); ?>', { id: id }, function(r) {
        if (r.success) { $('#promo-' + id).fadeOut(300, function() { $(this).remove(); }); }
        else alert('Failed to delete');
    }, 'json');
}

// Init
updateTriggerUI();
</script>

<?php init_tail(); ?>
