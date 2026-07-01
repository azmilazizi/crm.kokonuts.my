<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
/* ── Cards ─────────────────────────────────────────────────────────────────── */
.promo-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:14px 16px; margin-bottom:10px; position:relative; }
.promo-card .promo-title { font-size:14px; font-weight:600; color:#222; margin:6px 0 3px; }
.promo-card .promo-desc  { font-size:13px; color:#666; margin-bottom:7px; white-space:pre-line; }
.promo-card .promo-meta  { font-size:12px; color:#aaa; line-height:1.8; }
.promo-actions           { position:absolute; top:10px; right:10px; display:flex; gap:3px; }
.type-badge  { display:inline-block; padding:2px 7px; border-radius:10px; font-size:11px; font-weight:700; text-transform:uppercase; }
.type-announcement { background:#fff3cd; color:#856404; }
.type-discount     { background:#d4edda; color:#155724; }
.type-event        { background:#cce5ff; color:#004085; }
.type-freebie      { background:#f8d7da; color:#721c24; }
.type-other        { background:#e2e3e5; color:#383d41; }
.trigger-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:11px; background:#e8d5f5; color:#5a189a; font-weight:700; }
.ni { display:inline-block; padding:1px 6px; border-radius:8px; font-size:11px; font-weight:600; }
.ni-push { background:#d0ebff; color:#1971c2; }
.ni-sms  { background:#d3f9d8; color:#2f9e44; }
.status-active   { color:#5cb85c; font-weight:600; font-size:12px; }
.status-inactive { color:#aaa; font-size:12px; }
.ns-sent      { color:#5cb85c; }
.ns-pending   { color:#f0ad4e; }
.ns-recurring { color:#9b59b6; }
.empty-state { text-align:center; padding:60px 20px; color:#aaa; }
.empty-state i { font-size:48px; margin-bottom:12px; display:block; }

/* ── Modal sections ─────────────────────────────────────────────────────────── */
.msec { border-top:1px solid #f0f0f0; margin:14px -15px 0; padding:12px 15px 0; }
.msec-title { font-size:10px; font-weight:700; text-transform:uppercase; color:#bbb; letter-spacing:.6px; margin-bottom:10px; }

/* ── Trigger button-group ────────────────────────────────────────────────────── */
.trigger-group { display:flex; gap:6px; }
.trigger-opt   { flex:1; }
.trigger-opt input[type=radio] { display:none; }
.trigger-opt label {
    display:block; cursor:pointer; text-align:center; padding:9px 8px;
    border:2px solid #ddd; border-radius:6px; margin:0;
    font-weight:600; font-size:13px; color:#555;
    transition:border-color .15s, background .15s;
    line-height:1.3;
}
.trigger-opt label small { display:block; font-weight:400; font-size:11px; color:#aaa; margin-top:3px; }
.trigger-opt input:checked + label { border-color:#337ab7; background:#f0f7ff; color:#1a5276; }

/* ── Timing checkboxes ───────────────────────────────────────────────────────── */
.timing-list { background:#f9f9f9; border:1px solid #e8e8e8; border-radius:5px; padding:10px 14px; margin-top:8px; }
.timing-list label { display:block; font-weight:400; padding:3px 0; font-size:13px; cursor:pointer; }
.timing-list label:hover { color:#337ab7; }

/* ── Variable helper ─────────────────────────────────────────────────────────── */
.var-btns { margin-top:5px; }
.var-btns .var-btn { font-family:monospace; font-size:11px; padding:2px 7px; }

/* ── Member tags ─────────────────────────────────────────────────────────────── */
.member-tags { min-height:34px; border:1px solid #ccc; border-radius:4px; padding:4px 6px; background:#fff; display:flex; flex-wrap:wrap; gap:4px; align-items:center; cursor:text; }
.member-tag  { display:inline-flex; align-items:center; background:#337ab7; color:#fff; border-radius:12px; padding:2px 8px 2px 10px; font-size:12px; gap:5px; white-space:nowrap; }
.member-tag .rm { cursor:pointer; font-size:14px; line-height:1; opacity:.7; }
.member-tag .rm:hover { opacity:1; }
.member-search-input { border:none; outline:none; flex:1; min-width:120px; font-size:13px; padding:2px 4px; }
.member-dropdown { position:absolute; z-index:1050; background:#fff; border:1px solid #ddd; border-top:0; border-radius:0 0 4px 4px; max-height:180px; overflow-y:auto; width:100%; left:0; top:100%; box-shadow:0 4px 8px rgba(0,0,0,.1); }
.member-dropdown-item { padding:8px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f5f5f5; }
.member-dropdown-item:hover { background:#f0f7ff; }
.member-dropdown-item .phone { color:#aaa; font-size:11px; }
</style>

<div id="wrapper">
<div class="content">

    <div class="row" style="margin-bottom:14px;">
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
        $has_notify    = !empty($promo['notify_push']) || !empty($promo['notify_sms']);
        $notify_status = $promo['notify_status'] ?? 'pending';
        $trigger       = $promo['trigger_type'] ?? 'standard';
        $trigger_label = ['birthday' => 'Birthday', 'anniversary' => 'Anniversary'][$trigger] ?? null;
    ?>
    <div class="promo-card" id="promo-<?php echo $promo['id']; ?>">
        <div class="promo-actions">
            <?php if ($has_notify && has_permission('loyalty', '', 'edit')): ?>
            <button class="btn btn-success btn-xs blast-btn" data-id="<?php echo (int)$promo['id']; ?>" data-title="<?php echo htmlspecialchars($promo['title'], ENT_QUOTES); ?>" title="Blast Now">
                <i class="fa fa-bullhorn"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'edit')): ?>
            <button class="btn btn-default btn-xs edit-btn" data-promo="<?php echo htmlspecialchars(json_encode($promo), ENT_QUOTES); ?>">
                <i class="fa fa-pencil"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'delete')): ?>
            <button class="btn btn-danger btn-xs del-btn" data-id="<?php echo (int)$promo['id']; ?>" data-title="<?php echo htmlspecialchars($promo['title'], ENT_QUOTES); ?>">
                <i class="fa fa-trash"></i>
            </button>
            <?php endif; ?>
        </div>

        <div style="padding-right:100px;">
            <span class="type-badge type-<?php echo $promo['type']; ?>"><?php echo ucfirst($promo['type']); ?></span>
            <?php if ($trigger_label): ?>
            &nbsp;<span class="trigger-badge"><?php echo $trigger_label; ?></span>
            <?php endif; ?>
            <?php if (!empty($promo['notify_push'])): ?>
            &nbsp;<span class="ni ni-push"><i class="fa fa-bell"></i> Push</span>
            <?php endif; ?>
            <?php if (!empty($promo['notify_sms'])): ?>
            &nbsp;<span class="ni ni-sms"><i class="fa fa-mobile"></i> SMS</span>
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
            <?php if ($trigger === 'standard' && ($promo['start_date'] || $promo['end_date'])): ?>
            <i class="fa fa-calendar"></i>
            <?php echo $promo['start_date'] ? date('d M Y', strtotime($promo['start_date'])) : 'Now'; ?>
            &rarr; <?php echo $promo['end_date'] ? date('d M Y', strtotime($promo['end_date'])) : 'No end'; ?>
            &nbsp;&bull;&nbsp;
            <?php elseif ($trigger === 'birthday'): ?>
            <i class="fa fa-birthday-cake"></i> Triggers on member birthdays &nbsp;&bull;&nbsp;
            <?php elseif ($trigger === 'anniversary'): ?>
            <i class="fa fa-star"></i> Triggers on signup anniversaries &nbsp;&bull;&nbsp;
            <?php endif; ?>
            <?php if ($has_notify): ?>
                <?php if ($notify_status === 'sent'): ?>
                <span class="ns-sent"><i class="fa fa-check-circle"></i> Blasted <?php echo $promo['notified_at'] ? date('d M Y', strtotime($promo['notified_at'])) : ''; ?></span>
                <?php elseif ($notify_status === 'recurring'): ?>
                <span class="ns-recurring"><i class="fa fa-refresh"></i> Recurring &mdash; last <?php echo $promo['notified_at'] ? date('d M Y', strtotime($promo['notified_at'])) : 'never'; ?></span>
                <?php else: ?>
                <span class="ns-pending"><i class="fa fa-clock-o"></i> Not blasted yet</span>
                <?php endif; ?>
                &nbsp;&bull;&nbsp;
            <?php endif; ?>
            Created <?php echo date('d M Y', strtotime($promo['created_at'])); ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($result['page_count'] > 1): ?>
    <div style="margin-top:14px;">
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

<!-- ── Promotion Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="promoModalTitle">New Promotion</h4>
            </div>
            <div class="modal-body" style="max-height:80vh;overflow-y:auto;">
                <input type="hidden" id="promo_id">

                <!-- PROMO DETAILS ─────────────────────────────────────────── -->
                <div class="msec-title" style="margin-top:0;border:none;padding:0;">Promotion Details</div>

                <div class="form-group">
                    <label>Title <span class="text-danger">*</span></label>
                    <input type="text" id="promo_title" class="form-control" placeholder="e.g. Birthday Freebies">
                </div>

                <div class="row">
                    <div class="col-sm-4">
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
                    <div class="col-sm-8">
                        <div class="form-group">
                            <label>Image URL <span class="text-muted" style="font-size:11px;">(optional)</span></label>
                            <input type="text" id="promo_image_url" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description <span class="text-muted" style="font-size:11px;">(also used as SMS/push message body)</span></label>
                    <textarea id="promo_description" class="form-control" rows="3"
                        placeholder="e.g. Hi {{firstname}}, enjoy a free drink on your birthday!"></textarea>
                    <div class="var-btns">
                        <span style="font-size:11px;color:#aaa;margin-right:4px;">Insert:</span>
                        <button type="button" class="btn btn-xs btn-default var-btn" data-var="{{firstname}}">{{firstname}}</button>
                        <button type="button" class="btn btn-xs btn-default var-btn" data-var="{{name}}">{{name}}</button>
                        <button type="button" class="btn btn-xs btn-default var-btn" data-var="{{birthday}}">{{birthday}}</button>
                        <button type="button" class="btn btn-xs btn-default var-btn" data-var="{{points}}">{{points}}</button>
                    </div>
                </div>

                <!-- PROMO TRIGGER ─────────────────────────────────────────── -->
                <div class="msec">
                    <div class="msec-title">Promo Trigger</div>
                    <div class="trigger-group">
                        <div class="trigger-opt">
                            <input type="radio" name="trigger_type" id="trig_standard" value="standard" checked>
                            <label for="trig_standard">
                                <i class="fa fa-calendar"></i> Standard
                                <small>Date-based, sent once</small>
                            </label>
                        </div>
                        <div class="trigger-opt">
                            <input type="radio" name="trigger_type" id="trig_birthday" value="birthday">
                            <label for="trig_birthday">
                                <i class="fa fa-birthday-cake"></i> Birthday Freebie
                                <small>Recurs every year on birthday</small>
                            </label>
                        </div>
                        <div class="trigger-opt">
                            <input type="radio" name="trigger_type" id="trig_anniversary" value="anniversary">
                            <label for="trig_anniversary">
                                <i class="fa fa-star"></i> Anniversary
                                <small>Recurs on signup anniversary</small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SCHEDULE (Standard only) ──────────────────────────────── -->
                <div class="msec" id="schedule_section">
                    <div class="msec-title">Schedule</div>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" id="promo_start_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>End Date <span class="text-muted" style="font-size:11px;">(blank = no expiry)</span></label>
                                <input type="date" id="promo_end_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AUDIENCE ──────────────────────────────────────────────── -->
                <div class="msec">
                    <div class="msec-title">Audience</div>

                    <!-- Standard: all / tier / individual -->
                    <div id="standard_audience">
                        <div class="row">
                            <div class="col-sm-5">
                                <div class="form-group">
                                    <label>Send To</label>
                                    <select id="promo_target" class="form-control" onchange="onTargetChange()">
                                        <option value="all">All Members</option>
                                        <?php if (!empty($tiers)): ?>
                                        <option value="tier">Specific Tier</option>
                                        <?php endif; ?>
                                        <option value="individual">Individual Members</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-sm-7" id="tier_wrap" style="display:none;">
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
                        </div>
                        <!-- Individual member tag search -->
                        <div id="individual_wrap" style="display:none;">
                            <label>Search Members</label>
                            <div class="member-tags" id="member_tags_box" onclick="$('#member_search_input').focus()">
                                <span id="member_tags_inner"></span>
                                <input type="text" id="member_search_input" class="member-search-input" placeholder="Type name or phone…">
                            </div>
                            <div style="position:relative;">
                                <div class="member-dropdown" id="member_dropdown" style="display:none;"></div>
                            </div>
                            <input type="hidden" id="promo_target_customer_ids">
                        </div>
                    </div>

                    <!-- Birthday/Anniversary: optional tier filter -->
                    <div id="recurring_audience" style="display:none;">
                        <div class="form-group" style="max-width:280px;">
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

                <!-- NOTIFICATIONS ─────────────────────────────────────────── -->
                <div class="msec">
                    <div class="msec-title">Notifications</div>

                    <div class="row">
                        <div class="col-sm-4 col-xs-6">
                            <label style="font-weight:normal;font-size:13px;">
                                <input type="checkbox" id="promo_notify_push" value="1">
                                &nbsp;<i class="fa fa-bell" style="color:#1971c2;"></i> <strong>Push Notification</strong>
                            </label>
                            <p class="help-block" style="font-size:11px;margin-top:2px;">In-app notification bell</p>
                        </div>
                        <div class="col-sm-4 col-xs-6">
                            <label style="font-weight:normal;font-size:13px;">
                                <input type="checkbox" id="promo_notify_sms" value="1">
                                &nbsp;<i class="fa fa-mobile" style="color:#2f9e44;"></i> <strong>SMS (Twilio)</strong>
                            </label>
                            <p class="help-block" style="font-size:11px;margin-top:2px;">Text to phone number</p>
                        </div>
                        <!-- Standard only: send timing dropdown -->
                        <div class="col-sm-4" id="standard_timing_col">
                            <div class="form-group" style="margin-top:2px;">
                                <label>Send</label>
                                <select id="promo_notify_days_before" class="form-control input-sm">
                                    <option value="0">Immediately (on save)</option>
                                    <option value="1">1 day before start</option>
                                    <option value="2">2 days before start</option>
                                    <option value="3">3 days before start</option>
                                    <option value="5">5 days before start</option>
                                    <option value="7">7 days before start</option>
                                    <option value="14">14 days before start</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Birthday/Anniversary: timing checklist -->
                    <div id="birthday_timing_section" style="display:none;">
                        <div style="font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">When to send:</div>
                        <div class="timing-list">
                            <label><input type="checkbox" name="bday_timing" value="0"> On the birthday / anniversary day</label>
                            <label><input type="checkbox" name="bday_timing" value="1"> 1 day before</label>
                            <label><input type="checkbox" name="bday_timing" value="3"> 3 days before</label>
                            <label><input type="checkbox" name="bday_timing" value="7"> 1 week before</label>
                            <label><input type="checkbox" name="bday_timing" value="14"> 2 weeks before</label>
                            <label><input type="checkbox" name="bday_timing" value="month_start"> Start of birthday / anniversary month</label>
                        </div>
                    </div>

                    <div id="blast_preview" class="alert alert-info" style="display:none;font-size:12px;margin:10px 0 0;"></div>
                </div>

                <!-- SETTINGS ──────────────────────────────────────────────── -->
                <div class="msec">
                    <div class="msec-title">Settings</div>
                    <label style="font-weight:normal;">
                        <input type="checkbox" id="promo_is_active" value="1" checked>
                        &nbsp;Active — visible in member app
                    </label>
                </div>

            </div><!-- /.modal-body -->
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
$(function () {

// ── Variable insertion ────────────────────────────────────────────────────────
$(document).on('click', '.var-btn', function () {
    var v  = $(this).data('var');
    var ta = document.getElementById('promo_description');
    var s  = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
});

// ── Member tag search ─────────────────────────────────────────────────────────
var selectedMembers = [];

function renderTags() {
    var html = selectedMembers.map(function (m) {
        return '<span class="member-tag" data-id="' + m.id + '">' +
               $('<span>').text(m.name || m.phone).html() +
               '<span class="rm" onclick="removeTag(' + m.id + ')">&times;</span></span>';
    }).join('');
    $('#member_tags_inner').html(html);
    $('#promo_target_customer_ids').val(selectedMembers.map(function(m){return m.id;}).join(','));
}

window.removeTag = function (id) {
    selectedMembers = selectedMembers.filter(function(m){ return m.id !== id; });
    renderTags();
};

var _st;
$('#member_search_input').on('input', function () {
    clearTimeout(_st);
    var q = $.trim($(this).val());
    if (q.length < 2) { $('#member_dropdown').hide(); return; }
    _st = setTimeout(function () {
        $.getJSON('<?php echo admin_url('loyalty/ajax_search_customers'); ?>', { q: q }, function (r) {
            if (!r.rows || !r.rows.length) { $('#member_dropdown').hide(); return; }
            var html = r.rows.map(function (c) {
                return '<div class="member-dropdown-item" data-id="' + c.id + '" data-name="' +
                    $('<div>').text(c.name || '').html() + '" data-phone="' +
                    $('<div>').text(c.phone || '').html() + '">' +
                    $('<div>').text(c.name || '—').html() +
                    '<span class="phone"> ' + $('<div>').text(c.phone || '').html() + '</span></div>';
            }).join('');
            $('#member_dropdown').html(html).show();
        });
    }, 280);
}).on('keydown', function (e) {
    if (e.key === 'Escape') $('#member_dropdown').hide();
});

$(document).on('click', '.member-dropdown-item', function () {
    var id   = parseInt($(this).data('id'));
    var name = $(this).data('name');
    var ph   = $(this).data('phone');
    if (!selectedMembers.find(function(m){ return m.id === id; })) {
        selectedMembers.push({ id: id, name: name, phone: ph });
        renderTags();
    }
    $('#member_search_input').val('').focus();
    $('#member_dropdown').hide();
});

$(document).on('click', function (e) {
    if (!$(e.target).closest('#individual_wrap').length) $('#member_dropdown').hide();
});

// ── Trigger UI ────────────────────────────────────────────────────────────────
$('input[name="trigger_type"]').on('change', updateTriggerUI);

function updateTriggerUI() {
    var trigger     = $('input[name="trigger_type"]:checked').val() || 'standard';
    var isRecurring = trigger === 'birthday' || trigger === 'anniversary';

    $('#schedule_section').toggle(!isRecurring);
    $('#standard_audience').toggle(!isRecurring);
    $('#recurring_audience').toggle(isRecurring);
    $('#standard_timing_col').toggle(!isRecurring);
    $('#birthday_timing_section').toggle(isRecurring);

    updateBlastPreview();
}

window.onTargetChange = function () {
    var t = $('#promo_target').val();
    $('#tier_wrap').toggle(t === 'tier');
    $('#individual_wrap').toggle(t === 'individual');
    if (t !== 'individual') { selectedMembers = []; renderTags(); }
    updateBlastPreview();
};
var onTargetChange = window.onTargetChange;

function updateBlastPreview() {
    var push    = $('#promo_notify_push').is(':checked');
    var sms     = $('#promo_notify_sms').is(':checked');
    var trigger = $('input[name="trigger_type"]:checked').val() || 'standard';
    var preview = $('#blast_preview');

    if (!push && !sms) { preview.hide(); return; }

    var channels = [];
    if (push) channels.push('push notification');
    if (sms)  channels.push('SMS');
    var ch = channels.join(' + ');

    var msg;
    if (trigger === 'birthday') {
        var selected = $('input[name="bday_timing"]:checked').map(function(){ return $(this).closest('label').text().trim(); }).get();
        msg = 'Will send ' + ch + ' to members on their birthday.' +
              (selected.length ? ' Timing: ' + selected.join(', ') + '.' : ' <em>Select at least one timing below.</em>') +
              ' Recurs every year — use <strong>Blast</strong> to run today\'s check.';
    } else if (trigger === 'anniversary') {
        var selAnni = $('input[name="bday_timing"]:checked').map(function(){ return $(this).closest('label').text().trim(); }).get();
        msg = 'Will send ' + ch + ' to members on their signup anniversary.' +
              (selAnni.length ? ' Timing: ' + selAnni.join(', ') + '.' : ' <em>Select at least one timing below.</em>') +
              ' Recurs every year.';
    } else {
        var days = parseInt($('#promo_notify_days_before').val()) || 0;
        msg = days === 0
            ? 'Will send ' + ch + ' to selected audience <strong>immediately on save</strong>.'
            : 'Will send ' + ch + ' ' + days + ' day(s) before start date. Use <strong>Blast</strong> to send manually.';
    }
    preview.html('<i class="fa fa-info-circle"></i> ' + msg).show();
}

$('#promo_notify_push, #promo_notify_sms').on('change', updateBlastPreview);
$('#promo_notify_days_before').on('change', updateBlastPreview);
$('input[name="bday_timing"]').on('change', updateBlastPreview);

// ── Open / Reset ──────────────────────────────────────────────────────────────
window.openPromoModal = function () {
    resetModal();
    $('#promoModalTitle').text('New Promotion');
    $('#promoModal').modal('show');
};

function resetModal() {
    $('#promo_id').val('');
    $('#promo_title, #promo_description, #promo_image_url').val('');
    $('#promo_type').val('announcement');
    $('#promo_start_date, #promo_end_date').val('');
    $('#promo_target').val('all');
    $('#promo_target_tier').val('');
    $('#promo_target_tier_recurring').val('');
    $('#promo_target_customer_ids').val('');
    selectedMembers = [];
    renderTags();
    $('#member_search_input').val('');
    $('#promo_notify_push, #promo_notify_sms').prop('checked', false);
    $('#promo_notify_days_before').val('0');
    $('input[name="bday_timing"]').prop('checked', false);
    $('#promo_is_active').prop('checked', true);
    $('input[name="trigger_type"][value="standard"]').prop('checked', true);
    updateTriggerUI();
    onTargetChange();
    updateBlastPreview();
}

// ── Edit ──────────────────────────────────────────────────────────────────────
$(document).on('click', '.edit-btn', function () {
    var raw   = $(this).attr('data-promo');
    var promo = JSON.parse(raw);
    resetModal();

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

    var trigger = promo.trigger_type || 'standard';
    $('input[name="trigger_type"][value="' + trigger + '"]').prop('checked', true);

    var isRecurring = trigger === 'birthday' || trigger === 'anniversary';
    if (isRecurring) {
        // Restore timing checkboxes from comma-separated string
        var timings = (promo.notify_days_before || '0').split(',');
        $('input[name="bday_timing"]').each(function() {
            $(this).prop('checked', timings.indexOf($(this).val()) !== -1);
        });
        $('#promo_target_tier_recurring').val(promo.target_tier || '');
    } else {
        $('#promo_notify_days_before').val(promo.notify_days_before || '0');
        var target = promo.target || 'all';
        $('#promo_target').val(target);
        $('#promo_target_tier').val(promo.target_tier || '');
        // Restore individual members
        if (target === 'individual' && promo.target_customer_id) {
            var ids = promo.target_customer_id.toString().split(',').filter(Boolean);
            // We only have IDs here — show placeholder, user can re-search
            $('#promo_target_customer_ids').val(ids.join(','));
            $('#member_tags_inner').html('<em style="color:#aaa;font-size:12px;">Previous selection (' + ids.length + ' member(s)). Re-search to modify.</em>');
        }
    }

    $('#promoModalTitle').text('Edit Promotion');
    updateTriggerUI();
    onTargetChange();
    updateBlastPreview();
    $('#promoModal').modal('show');
});

// ── Save ──────────────────────────────────────────────────────────────────────
window.savePromo = function () {
    var title = $.trim($('#promo_title').val());
    if (!title) { alert('Title is required'); return; }

    var trigger     = $('input[name="trigger_type"]:checked').val() || 'standard';
    var isRecurring = trigger === 'birthday' || trigger === 'anniversary';
    var target      = isRecurring ? 'all' : $('#promo_target').val();
    var tier        = isRecurring ? $('#promo_target_tier_recurring').val() : $('#promo_target_tier').val();

    if (target === 'individual' && !$('#promo_target_customer_ids').val()) {
        alert('Please select at least one member.'); return;
    }

    // Collect timing
    var daysBefore;
    if (isRecurring) {
        var checked = $('input[name="bday_timing"]:checked').map(function(){ return $(this).val(); }).get();
        if (!checked.length) { alert('Please select at least one timing option.'); return; }
        daysBefore = checked.join(',');
    } else {
        daysBefore = $('#promo_notify_days_before').val() || '0';
    }

    var btn = $('#promoSaveBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.post('<?php echo admin_url('loyalty/ajax_save_promotion'); ?>', {
        id:                 $('#promo_id').val(),
        title:              title,
        description:        $('#promo_description').val(),
        image_url:          $('#promo_image_url').val(),
        type:               $('#promo_type').val(),
        start_date:         $('#promo_start_date').val(),
        end_date:           $('#promo_end_date').val(),
        is_active:          $('#promo_is_active').is(':checked') ? 1 : 0,
        trigger_type:       trigger,
        target:             target,
        target_tier:        tier,
        target_customer_id: $('#promo_target_customer_ids').val(),
        notify_push:        $('#promo_notify_push').is(':checked') ? 1 : 0,
        notify_sms:         $('#promo_notify_sms').is(':checked') ? 1 : 0,
        notify_days_before: daysBefore,
    }, function (r) {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
        if (r.success) {
            $('#promoModal').modal('hide');
            var msg = 'Promotion saved.';
            if (typeof r.recipients !== 'undefined') {
                msg += '\n\nBlast sent to ' + r.recipients + ' recipient(s).';
                if (r.push_sent)  msg += '\nPush: ' + r.push_sent + ' sent.';
                if (r.sms_sent || r.sms_failed) msg += '\nSMS: ' + r.sms_sent + ' sent, ' + (r.sms_failed||0) + ' failed.';
                if (r.sms_error)  msg += '\nSMS error: ' + r.sms_error;
            }
            alert(msg);
            location.reload();
        } else {
            alert(r.message || 'Failed to save promotion');
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
        alert('Request failed. Please try again.');
    });
};

// ── Blast Now ─────────────────────────────────────────────────────────────────
$(document).on('click', '.blast-btn', function () {
    var id    = $(this).data('id');
    var title = $(this).data('title');
    if (!confirm('Send blast for "' + title + '" now?\nThis will immediately send to all matching members.')) return;
    var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
    $.post('<?php echo admin_url('loyalty/ajax_blast_promotion'); ?>', { id: id }, function (r) {
        $btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i>');
        if (r.success) {
            var msg = 'Blast complete! ' + r.recipients + ' recipient(s).';
            if (r.push_sent)  msg += '\nPush: ' + r.push_sent + ' sent.';
            if (r.sms_sent || r.sms_failed) msg += '\nSMS: ' + r.sms_sent + ' sent, ' + (r.sms_failed||0) + ' failed.';
            if (r.sms_error)  msg += '\nSMS: ' + r.sms_error;
            alert(msg);
            location.reload();
        } else { alert(r.message || 'Blast failed'); }
    }, 'json').fail(function () {
        $btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i>');
        alert('Request failed.');
    });
});

// ── Delete ────────────────────────────────────────────────────────────────────
$(document).on('click', '.del-btn', function () {
    var id    = $(this).data('id');
    var title = $(this).data('title');
    if (!confirm('Delete "' + title + '"? This cannot be undone.')) return;
    $.post('<?php echo admin_url('loyalty/ajax_delete_promotion'); ?>', { id: id }, function (r) {
        if (r.success) { $('#promo-' + id).fadeOut(250, function(){ $(this).remove(); }); }
        else alert('Failed to delete');
    }, 'json');
});

// Init
updateTriggerUI();
onTargetChange();

}); // end $(function)
</script>

<?php init_tail(); ?>
