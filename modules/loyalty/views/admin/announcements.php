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
        Send a one-time message to <strong>all members</strong> instantly &mdash; great for store updates, closures, or quick news.
        Unlike promotions, announcements are fired immediately and not saved as campaigns.
    </p>

    <?php if (has_permission('loyalty', '', 'create')): ?>
    <div class="compose-box">
        <div class="form-group">
            <label>Title <span class="text-danger">*</span></label>
            <input type="text" id="ann_title" class="form-control" placeholder="e.g. Store closed this Sunday">
        </div>

        <div class="form-group" style="position:relative;">
            <label>Message <span class="text-muted" style="font-size:11px;">(optional body text)</span></label>
            <textarea id="ann_body" class="form-control" rows="3"
                placeholder="Hi {{firstname}}, just a quick note…"></textarea>
            <div id="ann_autocomplete" class="var-autocomplete" style="display:none;"></div>
            <div class="var-chips">
                <span style="font-size:11px;color:#aaa;margin-right:2px;">Insert:</span>
                <span class="var-chip ann-chip" data-var="{{firstname}}">{{firstname}}</span>
                <span class="var-chip ann-chip" data-var="{{name}}">{{name}}</span>
                <span class="var-chip ann-chip" data-var="{{points}}">{{points}}</span>
                <span class="var-chip ann-chip" data-var="{{tier}}">{{tier}}</span>
            </div>
        </div>

        <div class="form-group">
            <label>Send via</label>
            <div style="display:flex;gap:20px;margin-top:4px;">
                <label style="font-weight:normal;font-size:13px;">
                    <input type="checkbox" id="ann_push" value="1" checked>
                    &nbsp;<i class="fa fa-bell" style="color:#1971c2;"></i> <strong>Push Notification</strong>
                </label>
                <label style="font-weight:normal;font-size:13px;">
                    <input type="checkbox" id="ann_sms" value="1">
                    &nbsp;<i class="fa fa-mobile" style="color:#2f9e44;"></i> <strong>SMS (Twilio)</strong>
                </label>
            </div>
        </div>

        <div id="ann_result" style="display:none;" class="send-result"></div>

        <div style="margin-top:16px;">
            <button class="btn btn-warning btn-sm" id="annSendBtn" onclick="sendAnnouncement()">
                <i class="fa fa-bullhorn"></i> Send to All Members
            </button>
            <span style="font-size:11px;color:#aaa;margin-left:10px;">This sends immediately and cannot be undone.</span>
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

var ALL_VARS = [
    { tag: '{{firstname}}',   desc: 'First name' },
    { tag: '{{lastname}}',    desc: 'Last name' },
    { tag: '{{name}}',        desc: 'Full name' },
    { tag: '{{points}}',      desc: 'Total points' },
    { tag: '{{phone}}',       desc: 'Phone number' },
    { tag: '{{tier}}',        desc: 'Membership tier' },
    { tag: '{{signup_date}}', desc: 'Sign-up date' },
];

function insertVar(fieldId, v) {
    var ta = document.getElementById(fieldId);
    var s  = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
}

$(document).on('click', '.ann-chip', function () {
    insertVar('ann_body', $(this).data('var'));
});

// Autocomplete
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
    ta.focus();
    $ac.hide();
}

$(document).on('click', function (e) {
    if (!$(e.target).closest('#ann_body, #ann_autocomplete').length) $ac.hide();
});

// ── Send ──────────────────────────────────────────────────────────────────────
window.sendAnnouncement = function () {
    var title = $.trim($('#ann_title').val());
    var push  = $('#ann_push').is(':checked');
    var sms   = $('#ann_sms').is(':checked');

    if (!title) { alert('Title is required'); return; }
    if (!push && !sms) { alert('Select at least one channel (Push or SMS)'); return; }

    if (!confirm('Send this announcement to ALL members now?')) return;

    var btn = $('#annSendBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Sending...');
    $('#ann_result').hide();

    $.post('<?php echo admin_url('loyalty/ajax_send_announcement'); ?>', {
        title:       title,
        body:        $('#ann_body').val(),
        notify_push: push ? 1 : 0,
        notify_sms:  sms  ? 1 : 0,
    }, function (r) {
        btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Send to All Members');
        var $res = $('#ann_result');
        if (r.success) {
            var msg = 'Sent to ' + r.recipients + ' member(s).';
            if (r.push_sent)  msg += '  Push: ' + r.push_sent + ' delivered.';
            if (r.sms_sent || r.sms_failed) msg += '  SMS: ' + r.sms_sent + ' sent, ' + (r.sms_failed||0) + ' failed.';
            if (r.sms_error)  msg += '  Note: ' + r.sms_error;
            $res.html('<i class="fa fa-check-circle"></i> ' + msg).removeClass('error').addClass('success').show();
            $('#ann_title, #ann_body').val('');
        } else {
            $res.html('<i class="fa fa-times-circle"></i> ' + (r.message || 'Failed to send')).removeClass('success').addClass('error').show();
        }
    }, 'json').fail(function () {
        btn.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Send to All Members');
        $('#ann_result').html('<i class="fa fa-times-circle"></i> Request failed. Please try again.').addClass('error').show();
    });
};

}); // end $(function)
</script>
