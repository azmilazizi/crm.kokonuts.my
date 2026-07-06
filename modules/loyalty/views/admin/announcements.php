<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.ann-card { background:#fff8e1; border:1px solid #ffe082; border-radius:6px; padding:14px 16px; margin-bottom:8px; }
.ann-card .ann-title { font-weight:600; color:#333; margin-bottom:3px; }
.ann-card .ann-meta  { font-size:11px; color:#aaa; }
.ni { display:inline-block; padding:1px 6px; border-radius:8px; font-size:11px; font-weight:600; }
.ni-push { background:#d0ebff; color:#1971c2; }
.ni-sms  { background:#d3f9d8; color:#2f9e44; }

.var-chips { margin-top:6px; display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
.var-chip { font-family:monospace; font-size:11px; padding:2px 8px; background:#f0f4ff; border:1px solid #c5d3f0; border-radius:4px; color:#2c5282; cursor:pointer; white-space:nowrap; }
.var-chip:hover { background:#dbeafe; border-color:#93c5fd; }

.var-autocomplete { position:absolute; background:#fff; border:1px solid #ddd; border-top:0; border-radius:0 0 5px 5px; z-index:9999; width:220px; box-shadow:0 4px 10px rgba(0,0,0,.1); }
.var-ac-item { padding:6px 10px; font-family:monospace; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:8px; }
.var-ac-item:hover, .var-ac-item.active { background:#f0f7ff; }
.var-ac-item span.ac-tag { color:#2c5282; }
.var-ac-item span.ac-desc { color:#aaa; font-size:11px; font-family:sans-serif; }

.compose-box { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px; max-width:680px; }
.send-result { padding:14px 16px; border-radius:6px; margin-top:14px; font-size:13px; }
.send-result.success { background:#d4edda; border:1px solid #c3e6cb; color:#155724; }
.send-result.error   { background:#f8d7da; border:1px solid #f5c6cb; color:#721c24; }

/* SMS counter */
#ann_sms_counter { font-size:11px; margin-top:4px; }
#ann_segment_label { font-size:11px; margin-top:2px; }
#ann_sms_warning { font-size:11px; margin-top:2px; }

/* Member search tags */
.member-tag { display:inline-flex; align-items:center; background:#e8f4fd; border:1px solid #b8d9f0; border-radius:4px; padding:2px 8px; font-size:12px; margin:2px; white-space:nowrap; }
.member-tag .rm { cursor:pointer; color:#e74c3c; margin-left:6px; font-weight:bold; }
.member-tags-box { border:1px solid #ddd; border-radius:4px; padding:6px; min-height:38px; background:#fff; position:relative; cursor:text; }
.member-dropdown { position:absolute; left:0; right:0; background:#fff; border:1px solid #ddd; border-top:0; border-radius:0 0 4px 4px; z-index:999; max-height:200px; overflow-y:auto; }
.member-dropdown-item { padding:7px 10px; font-size:13px; cursor:pointer; }
.member-dropdown-item:hover { background:#f0f7ff; }
.member-dropdown-item .phone { color:#aaa; font-size:11px; margin-left:6px; }
</style>

<div id="wrapper">
<div class="content">

    <div class="row" style="margin-bottom:14px;">
        <div class="col-sm-12">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Announcement</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">Announcement</li>
            </ol>
        </div>
    </div>

    <p style="color:#666;font-size:13px;max-width:640px;margin-bottom:18px;">
        Send a one-time message instantly &mdash; great for store updates, closures, or quick news.
        Unlike promotions, announcements are fired immediately and not saved as campaigns.
    </p>

    <?php if (has_permission('loyalty', '', 'create')): ?>
    <div class="compose-box">
        <div class="form-group">
            <label>Title <span class="text-danger">*</span></label>
            <input type="text" id="ann_title" class="form-control" placeholder="e.g. Store closed this Sunday">
        </div>

        <div class="form-group" style="position:relative;">
            <label>
                Message <span class="text-muted" style="font-size:11px;">(optional body text — used as SMS body)</span>
            </label>
            <textarea id="ann_body" class="form-control" rows="3"
                placeholder="Hi {{firstname}}, just a quick note…"
                oninput="updateAnnSmsCounter()"></textarea>
            <div id="ann_autocomplete" class="var-autocomplete" style="display:none;"></div>
            <div id="ann_sms_counter" style="color:#aaa;"></div>
            <div id="ann_segment_label"></div>
            <div id="ann_sms_warning"></div>
            <div class="var-chips">
                <span style="font-size:11px;color:#aaa;margin-right:2px;">Insert:</span>
                <span class="var-chip ann-chip" data-var="{{firstname}}">{{firstname}}</span>
                <span class="var-chip ann-chip" data-var="{{name}}">{{name}}</span>
                <span class="var-chip ann-chip" data-var="{{points}}">{{points}}</span>
                <span class="var-chip ann-chip" data-var="{{tier}}">{{tier}}</span>
                <span class="var-chip ann-chip" data-var="{{signup_date}}">{{signup_date}}</span>
            </div>
        </div>

        <div class="form-group">
            <label>Send via</label>
            <div style="display:flex;gap:20px;margin-top:4px;">
                <label style="font-weight:normal;font-size:13px;">
                    <input type="checkbox" id="ann_push" value="1" checked onchange="updateAnnSmsCounter()">
                    &nbsp;<i class="fa fa-bell" style="color:#1971c2;"></i> <strong>Push Notification</strong>
                </label>
                <label style="font-weight:normal;font-size:13px;">
                    <input type="checkbox" id="ann_sms" value="1" onchange="updateAnnSmsCounter()">
                    &nbsp;<i class="fa fa-mobile" style="color:#2f9e44;"></i> <strong>SMS (Twilio)</strong>
                </label>
            </div>
        </div>

        <!-- Audience targeting -->
        <div class="form-group">
            <label>Send To</label>
            <select id="ann_target" class="form-control" style="max-width:280px;" onchange="onAnnTargetChange()">
                <option value="all">All Members</option>
                <?php if (!empty($tiers)): ?>
                <option value="tier">Specific Tier</option>
                <?php endif; ?>
                <option value="individual">Individual Members</option>
            </select>
        </div>

        <?php if (!empty($tiers)): ?>
        <div id="ann_tier_wrap" class="form-group" style="display:none;max-width:280px;">
            <label>Tier</label>
            <select id="ann_target_tier" class="form-control">
                <?php foreach ($tiers as $t): ?>
                <option value="<?php echo htmlspecialchars($t['name']); ?>">
                    <?php echo htmlspecialchars($t['name']); ?>
                    (<?php echo number_format((float)$t['minimum_number_of_points'], 0); ?>+ pts)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div id="ann_individual_wrap" class="form-group" style="display:none;">
            <label>Search Members</label>
            <div style="position:relative;">
                <div class="member-tags-box" id="ann_member_tags_box" onclick="$('#ann_member_search_input').focus()">
                    <span id="ann_member_tags_inner"></span>
                    <input type="text" id="ann_member_search_input" placeholder="Type name or phone…"
                        style="border:none;outline:none;background:transparent;font-size:13px;width:180px;" autocomplete="off">
                </div>
                <div id="ann_member_dropdown" class="member-dropdown" style="display:none;"></div>
            </div>
            <input type="hidden" id="ann_target_customer_ids">
        </div>

        <div id="ann_result" style="display:none;" class="send-result"></div>

        <div style="margin-top:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-warning btn-sm" id="annSendBtn" onclick="sendAnnouncement()">
                <i class="fa fa-bullhorn"></i> Send Now
            </button>
            <span id="ann_audience_label" style="font-size:12px;color:#888;"></span>
            <span style="font-size:11px;color:#aaa;">This sends immediately and cannot be undone.</span>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">You do not have permission to send announcements.</div>
    <?php endif; ?>

</div>
</div>

<?php init_tail(); ?>
<script>
$(function () {

// ── GSM-7 / SMS counter ───────────────────────────────────────────────────────
var GSM7     = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
var GSM7_EXT = '{}\\[~]|€^';

function isGsm7Ann(str) {
    for (var i = 0; i < str.length; i++) {
        if (GSM7.indexOf(str[i]) === -1 && GSM7_EXT.indexOf(str[i]) === -1) return false;
    }
    return true;
}

function estimatedLengthAnn(text) {
    return text
        .replace(/\{\{firstname\}\}/g,   'Ahmad')
        .replace(/\{\{lastname\}\}/g,    'Ali')
        .replace(/\{\{name\}\}/g,        'Ahmad Ali')
        .replace(/\{\{points\}\}/g,      '1,250')
        .replace(/\{\{phone\}\}/g,       '60123456789')
        .replace(/\{\{tier\}\}/g,        'Gold')
        .replace(/\{\{signup_date\}\}/g, '01 Jan 2024');
}

window.updateAnnSmsCounter = function () {
    var smsOn    = $('#ann_sms').is(':checked');
    var raw      = $('#ann_body').val();
    var resolved = estimatedLengthAnn(raw);
    var len      = resolved.length;
    var unicode  = !isGsm7Ann(resolved);
    var single   = unicode ? 70  : 160;
    var multi    = unicode ? 67  : 153;
    var segments = len === 0 ? 0 : (len <= single ? 1 : Math.ceil(len / multi));
    var colour   = !smsOn ? '#aaa' : (len === 0 ? '#aaa' : (len <= single * 0.85 ? '#5cb85c' : (len <= single ? '#f0ad4e' : '#d9534f')));
    var label    = segments <= 1
        ? (len + ' / ' + single + ' chars')
        : (len + ' chars — ' + segments + ' SMS parts');

    $('#ann_sms_counter').text(label).css('color', colour);

    var segLabel = '';
    if (smsOn) {
        if (unicode)          segLabel = '<span style="color:#e67e22;"><i class="fa fa-exclamation-triangle"></i> Unicode detected — limit 70 chars/part</span>';
        else if (segments > 1) segLabel = '<span style="color:#d9534f;"><i class="fa fa-exclamation-triangle"></i> Multi-part SMS (' + segments + ' parts) — billed ' + segments + 'x per recipient</span>';
        else if (len > single * 0.85) segLabel = '<span style="color:#f0ad4e;">Approaching limit</span>';
    }
    $('#ann_segment_label').html(segLabel);

    var warn = '';
    if (smsOn && len === 0) {
        warn = '<span style="color:#d9534f;"><i class="fa fa-warning"></i> Message is empty — title will be used as SMS body.</span>';
    }
    $('#ann_sms_warning').html(warn).toggle(warn !== '');
};

// ── Autocomplete ──────────────────────────────────────────────────────────────
var ALL_VARS = [
    { tag: '{{firstname}}',   desc: 'First name' },
    { tag: '{{lastname}}',    desc: 'Last name' },
    { tag: '{{name}}',        desc: 'Full name' },
    { tag: '{{points}}',      desc: 'Total points' },
    { tag: '{{phone}}',       desc: 'Phone number' },
    { tag: '{{tier}}',        desc: 'Membership tier' },
    { tag: '{{signup_date}}', desc: 'Sign-up date' },
];

function insertVar(v) {
    var ta = document.getElementById('ann_body');
    var s  = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
    updateAnnSmsCounter();
}

$(document).on('click', '.ann-chip', function () {
    insertVar($(this).data('var'));
});

var $ac = $('#ann_autocomplete'), acIdx = -1;

$('#ann_body').on('input keydown', function (e) {
    if (e.type === 'keydown') {
        if (!$ac.is(':visible')) return;
        var items = $ac.find('.var-ac-item');
        if (e.key === 'ArrowDown') { e.preventDefault(); acIdx = Math.min(acIdx + 1, items.length - 1); items.removeClass('active').eq(acIdx).addClass('active'); return; }
        if (e.key === 'ArrowUp')   { e.preventDefault(); acIdx = Math.max(acIdx - 1, 0); items.removeClass('active').eq(acIdx).addClass('active'); return; }
        if ((e.key === 'Enter' || e.key === 'Tab') && items.filter('.active').length) {
            e.preventDefault(); doAcPick(items.filter('.active').data('tag')); return;
        }
        if (e.key === 'Escape') { $ac.hide(); return; }
        return;
    }
    var val = this.value, pos = this.selectionStart;
    var m = val.slice(0, pos).match(/\{\{(\w*)$/);
    if (!m) { $ac.hide(); return; }
    var q = m[1].toLowerCase();
    var matches = ALL_VARS.filter(function(v) { return v.tag.slice(2).toLowerCase().startsWith(q); });
    if (!matches.length) { $ac.hide(); return; }
    acIdx = -1;
    $ac.html(matches.map(function(v) {
        return '<div class="var-ac-item" data-tag="' + v.tag + '"><span class="ac-tag">' + v.tag + '</span><span class="ac-desc">' + v.desc + '</span></div>';
    }).join('')).show();
});

$(document).on('click', '.var-ac-item', function () { doAcPick($(this).data('tag')); });

function doAcPick(tag) {
    var ta = document.getElementById('ann_body');
    var val = ta.value, pos = ta.selectionStart;
    var m = val.slice(0, pos).match(/\{\{(\w*)$/);
    if (!m) return;
    var start = pos - m[0].length;
    ta.value = val.slice(0, start) + tag + val.slice(pos);
    ta.selectionStart = ta.selectionEnd = start + tag.length;
    ta.focus(); $ac.hide();
    updateAnnSmsCounter();
}

$(document).on('click', function (e) {
    if (!$(e.target).closest('#ann_body, #ann_autocomplete').length) $ac.hide();
});

// ── Audience targeting ────────────────────────────────────────────────────────
var annSelectedMembers = [];

function renderAnnTags() {
    var html = annSelectedMembers.map(function (m) {
        return '<span class="member-tag" data-id="' + m.id + '">' +
               $('<span>').text(m.name || m.phone).html() +
               '<span class="rm" onclick="removeAnnTag(' + m.id + ')">&times;</span></span>';
    }).join('');
    $('#ann_member_tags_inner').html(html);
    $('#ann_target_customer_ids').val(annSelectedMembers.map(function(m){ return m.id; }).join(','));
    updateAnnAudienceLabel();
}

window.removeAnnTag = function (id) {
    annSelectedMembers = annSelectedMembers.filter(function(m){ return m.id !== id; });
    renderAnnTags();
};

window.onAnnTargetChange = function () {
    var t = $('#ann_target').val();
    $('#ann_tier_wrap').toggle(t === 'tier');
    $('#ann_individual_wrap').toggle(t === 'individual');
    if (t !== 'individual') { annSelectedMembers = []; renderAnnTags(); }
    updateAnnAudienceLabel();
};

function updateAnnAudienceLabel() {
    var t = $('#ann_target').val();
    var label = '';
    if (t === 'all') {
        label = 'Sending to all members with a phone number';
    } else if (t === 'tier') {
        var tier = $('#ann_target_tier').val();
        label = tier ? 'Sending to members in tier: <strong>' + $('<span>').text(tier).html() + '</strong>' : '';
    } else if (t === 'individual') {
        var n = annSelectedMembers.length;
        label = n ? 'Sending to <strong>' + n + '</strong> selected member(s)' : 'No members selected yet';
    }
    $('#ann_audience_label').html(label);
}

$('#ann_target_tier').on('change', updateAnnAudienceLabel);

var _annSt;
$('#ann_member_search_input').on('input', function () {
    clearTimeout(_annSt);
    var q = $.trim($(this).val());
    if (q.length < 2) { $('#ann_member_dropdown').hide(); return; }
    _annSt = setTimeout(function () {
        $.getJSON('<?php echo admin_url('loyalty/ajax_search_customers'); ?>', { q: q }, function (r) {
            if (!r.rows || !r.rows.length) { $('#ann_member_dropdown').hide(); return; }
            var html = r.rows.map(function (c) {
                return '<div class="member-dropdown-item" data-id="' + c.id + '" data-name="' +
                    $('<div>').text(c.name || '').html() + '" data-phone="' +
                    $('<div>').text(c.phone || '').html() + '">' +
                    $('<div>').text(c.name || '—').html() +
                    '<span class="phone">' + $('<div>').text(c.phone || '').html() + '</span></div>';
            }).join('');
            $('#ann_member_dropdown').html(html).show();
        });
    }, 280);
}).on('keydown', function (e) {
    if (e.key === 'Escape') $('#ann_member_dropdown').hide();
});

$(document).on('click', '.member-dropdown-item', function () {
    var id   = parseInt($(this).data('id'));
    var name = $(this).data('name');
    var ph   = $(this).data('phone');
    if (!annSelectedMembers.find(function(m){ return m.id === id; })) {
        annSelectedMembers.push({ id: id, name: name, phone: ph });
        renderAnnTags();
    }
    $('#ann_member_search_input').val('').focus();
    $('#ann_member_dropdown').hide();
});

$(document).on('click', function (e) {
    if (!$(e.target).closest('#ann_individual_wrap').length) $('#ann_member_dropdown').hide();
});

// ── Send ──────────────────────────────────────────────────────────────────────
window.sendAnnouncement = function () {
    var title  = $.trim($('#ann_title').val());
    var push   = $('#ann_push').is(':checked');
    var sms    = $('#ann_sms').is(':checked');
    var target = $('#ann_target').val();
    var tier   = $('#ann_target_tier').val();
    var indIds = $('#ann_target_customer_ids').val();

    if (!title) { alert('Title is required'); return; }
    if (!push && !sms) { alert('Select at least one channel (Push or SMS)'); return; }
    if (target === 'individual' && !indIds) { alert('Please select at least one member.'); return; }

    var body = $('#ann_body').val();
    if (sms && body) {
        var resolved = estimatedLengthAnn(body);
        var unicode  = !isGsm7Ann(resolved);
        var single   = unicode ? 70 : 160;
        var multi    = unicode ? 67 : 153;
        var segments = resolved.length <= single ? 1 : Math.ceil(resolved.length / multi);
        if (segments > 3) {
            if (!confirm('This SMS is ' + segments + ' parts long. Each recipient will cost ' + segments + 'x your SMS rate. Continue?')) return;
        }
    }

    var targetLabel = target === 'all' ? 'ALL members' : (target === 'tier' ? 'tier: ' + tier : indIds.split(',').length + ' member(s)');
    if (!confirm('Send this announcement to ' + targetLabel + ' now?')) return;

    var btn = $('#annSendBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
    $('#ann_result').hide();

    $.post('<?php echo admin_url('loyalty/ajax_send_announcement'); ?>', {
        title:               title,
        body:                body,
        notify_push:         push ? 1 : 0,
        notify_sms:          sms  ? 1 : 0,
        target:              target,
        target_tier:         tier,
        target_customer_ids: indIds,
    }, function (r) {
        btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Send Now');
        var $res = $('#ann_result');
        if (r.success) {
            var msg = 'Sent to ' + r.recipients + ' member(s).';
            if (r.push_sent)  msg += '  Push: ' + r.push_sent + ' delivered.';
            if (r.sms_sent || r.sms_failed) msg += '  SMS: ' + r.sms_sent + ' sent, ' + (r.sms_failed||0) + ' failed.';
            if (r.sms_error)  msg += '  Note: ' + r.sms_error;
            $res.html('<i class="fa fa-check-circle"></i> ' + msg).removeClass('error').addClass('success').show();
            $('#ann_title, #ann_body').val('');
            updateAnnSmsCounter();
        } else {
            $res.html('<i class="fa fa-times-circle"></i> ' + (r.message || 'Failed to send')).removeClass('success').addClass('error').show();
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Send Now');
        $('#ann_result').html('<i class="fa fa-times-circle"></i> Request failed. Please try again.').addClass('error').show();
    });
};

// Init
updateAnnSmsCounter();
updateAnnAudienceLabel();

}); // end $(function)
</script>
