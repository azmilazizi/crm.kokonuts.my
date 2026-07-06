<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
/* ── Cards ─────────────────────────────────────────────────────────────────── */
.promo-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:14px 16px; margin-bottom:10px; position:relative; }
.promo-card .promo-title { font-size:14px; font-weight:600; color:#222; margin:6px 0 3px; }
.promo-card .promo-desc  { font-size:13px; color:#666; margin-bottom:7px; white-space:pre-line; }
.promo-card .promo-meta  { font-size:12px; color:#aaa; line-height:1.8; }
.promo-actions           { position:absolute; top:10px; right:10px; display:flex; gap:3px; }

.type-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700; text-transform:uppercase; }
.type-event     { background:#cce5ff; color:#004085; }
.type-promotion { background:#d4edda; color:#155724; }

.trigger-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:11px; background:#e8d5f5; color:#5a189a; font-weight:700; }
.ni { display:inline-block; padding:1px 6px; border-radius:8px; font-size:11px; font-weight:600; }
.ni-push { background:#d0ebff; color:#1971c2; }
.ni-sms  { background:#d3f9d8; color:#2f9e44; }
.status-active   { color:#5cb85c; font-weight:600; font-size:12px; }
.ns-sent      { color:#5cb85c; }
.ns-pending   { color:#f0ad4e; }
.ns-recurring { color:#9b59b6; }
.empty-state { text-align:center; padding:60px 20px; color:#aaa; }
.empty-state i { font-size:48px; margin-bottom:12px; display:block; }

/* ── Voucher ────────────────────────────────────────────────────────────────── */
.voucher-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; background:#fff3cd; color:#856404; font-weight:700; font-family:monospace; letter-spacing:.3px; }
.voucher-section { background:#fafafa; border:1px solid #e8e8e8; border-radius:6px; padding:14px 16px; margin-top:10px; }
.reward-row { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; margin-top:10px; }
.reward-row .form-group { margin-bottom:0; flex:1; min-width:140px; }

/* ── Modal sections ─────────────────────────────────────────────────────────── */
.msec { border-top:1px solid #f0f0f0; margin:14px -15px 0; padding:12px 15px 0; }
.msec-title { font-size:10px; font-weight:700; text-transform:uppercase; color:#bbb; letter-spacing:.6px; margin-bottom:10px; }

/* ── Trigger button-group ────────────────────────────────────────────────────── */
.trigger-group { display:flex; gap:6px; flex-wrap:wrap; }
.trigger-opt   { flex:1; min-width:110px; }
.trigger-opt input[type=radio] { display:none; }
.trigger-opt label {
    display:block; cursor:pointer; text-align:center; padding:9px 8px;
    border:2px solid #ddd; border-radius:6px; margin:0;
    font-weight:600; font-size:12px; color:#555;
    transition:border-color .15s, background .15s;
    line-height:1.3;
}
.trigger-opt label small { display:block; font-weight:400; font-size:11px; color:#aaa; margin-top:3px; }
.trigger-opt input:checked + label { border-color:#337ab7; background:#f0f7ff; color:#1a5276; }

/* ── Timing checkboxes ───────────────────────────────────────────────────────── */
.timing-list { background:#f9f9f9; border:1px solid #e8e8e8; border-radius:5px; padding:10px 14px; margin-top:8px; }
.timing-list label { display:block; font-weight:400; padding:3px 0; font-size:13px; cursor:pointer; }
.timing-list label:hover { color:#337ab7; }

/* ── Merge tag suggestions ───────────────────────────────────────────────────── */
.var-chips { margin-top:6px; display:flex; flex-wrap:wrap; gap:4px; align-items:center; }
.var-chip { font-family:monospace; font-size:11px; padding:2px 8px; background:#f0f4ff; border:1px solid #c5d3f0; border-radius:4px; color:#2c5282; cursor:pointer; white-space:nowrap; }
.var-chip:hover { background:#dbeafe; border-color:#93c5fd; }

/* Autocomplete dropdown */
.var-autocomplete { position:absolute; background:#fff; border:1px solid #ddd; border-top:0; border-radius:0 0 5px 5px; z-index:9999; width:220px; box-shadow:0 4px 10px rgba(0,0,0,.1); }
.var-ac-item { padding:6px 10px; font-family:monospace; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:8px; }
.var-ac-item:hover, .var-ac-item.active { background:#f0f7ff; }
.var-ac-item span.ac-tag { color:#2c5282; }
.var-ac-item span.ac-desc { color:#aaa; font-size:11px; font-family:sans-serif; }

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
            <h4 class="no-margin-top" style="margin-bottom:4px;">Event &amp; Promotions</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">Event &amp; Promotions</li>
            </ol>
        </div>
        <?php if (has_permission('loyalty', '', 'create')): ?>
        <div class="col-sm-6 text-right" style="padding-top:6px;">
            <button class="btn btn-primary btn-sm" onclick="openPromoModal()">
                <i class="fa fa-plus"></i> New
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
    <div class="empty-state">
        <i class="fa fa-bullhorn"></i>
        <p>No events or promotions yet.</p>
        <?php if (has_permission('loyalty', '', 'create')): ?>
        <button class="btn btn-primary" onclick="openPromoModal()">Create New</button>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <?php
    $trigger_labels = [
        'birthday'        => ['icon' => 'fa-birthday-cake', 'label' => 'Birthday'],
        'signup_freebies' => ['icon' => 'fa-star',          'label' => 'Sign Up Freebies'],
        'stale_points'    => ['icon' => 'fa-clock-o',       'label' => 'Stale Points'],
    ];
    ?>

    <?php foreach ($rows as $promo): ?>
    <?php
        $has_notify    = !empty($promo['notify_push']) || !empty($promo['notify_sms']);
        $notify_status = $promo['notify_status'] ?? 'pending';
        $trigger       = $promo['trigger_type'] ?? 'standard';
        $trig_info     = $trigger_labels[$trigger] ?? null;
        $type          = $promo['type'] ?: 'promotion';
    ?>
    <div class="promo-card" id="promo-<?php echo $promo['id']; ?>">
        <div class="promo-actions">
            <?php if ($has_notify && has_permission('loyalty', '', 'edit')): ?>
            <button class="btn btn-success btn-xs blast-btn"
                    data-id="<?php echo (int)$promo['id']; ?>"
                    data-title="<?php echo htmlspecialchars($promo['title'], ENT_QUOTES); ?>"
                    title="Blast Now">
                <i class="fa fa-bullhorn"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'edit')): ?>
            <button class="btn btn-default btn-xs edit-btn"
                    data-promo="<?php echo htmlspecialchars(json_encode($promo), ENT_QUOTES); ?>"
                    data-voucher="<?php echo htmlspecialchars(json_encode($promo['voucher']), ENT_QUOTES); ?>">
                <i class="fa fa-pencil"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'create')): ?>
            <button class="btn btn-default btn-xs clone-btn"
                    data-id="<?php echo (int)$promo['id']; ?>"
                    title="Clone promotion">
                <i class="fa fa-copy"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'edit')): ?>
            <button class="btn btn-xs toggle-active-btn <?php echo $promo['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                    data-id="<?php echo (int)$promo['id']; ?>"
                    data-active="<?php echo (int)$promo['is_active']; ?>"
                    title="<?php echo $promo['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                <i class="fa <?php echo $promo['is_active'] ? 'fa-pause' : 'fa-play'; ?>"></i>
            </button>
            <?php endif; ?>
            <?php if (has_permission('loyalty', '', 'delete')): ?>
            <button class="btn btn-danger btn-xs del-btn"
                    data-id="<?php echo (int)$promo['id']; ?>"
                    data-title="<?php echo htmlspecialchars($promo['title'], ENT_QUOTES); ?>">
                <i class="fa fa-trash"></i>
            </button>
            <?php endif; ?>
        </div>

        <div style="padding-right:100px;">
            <span class="type-badge type-<?php echo htmlspecialchars($type); ?>"><?php echo ucfirst($type); ?></span>
            <?php if ($trig_info): ?>
            &nbsp;<span class="trigger-badge"><i class="fa <?php echo $trig_info['icon']; ?>"></i> <?php echo $trig_info['label']; ?></span>
            <?php endif; ?>
            <?php if (!empty($promo['notify_push'])): ?>
            &nbsp;<span class="ni ni-push"><i class="fa fa-bell"></i> Push</span>
            <?php endif; ?>
            <?php if (!empty($promo['notify_sms'])): ?>
            &nbsp;<span class="ni ni-sms"><i class="fa fa-mobile"></i> SMS</span>
            <?php endif; ?>
        </div>

        <div class="promo-title"><?php echo htmlspecialchars($promo['title']); ?></div>

        <?php if (!empty($promo['voucher'])): ?>
        <?php
            $v = $promo['voucher'];
            $reward_label = '';
            switch ($v['reward_type']) {
                case 'discount_pct':   $reward_label = number_format((float)$v['reward_value'], 0) . '% off'; break;
                case 'discount_fixed': $reward_label = 'RM' . number_format((float)$v['reward_value'], 2) . ' off'; break;
                case 'points_bonus':   $reward_label = '+' . number_format((float)$v['reward_value'], 0) . ' pts'; break;
                case 'free_item':      $reward_label = 'Free: ' . htmlspecialchars($v['reward_item'] ?? ''); break;
            }
            $code_display = $v['code_mode'] === 'unique_per_member'
                ? htmlspecialchars($v['base_code']) . '-*'
                : htmlspecialchars($v['base_code']);
        ?>
        <div style="margin-bottom:6px;">
            <span class="voucher-badge"><i class="fa fa-ticket"></i> <?php echo $code_display; ?></span>
            &nbsp;<span style="font-size:11px;color:#888;"><?php echo $reward_label; ?></span>
            <?php if (!$v['is_active']): ?>
            &nbsp;<span style="font-size:11px;color:#c0392b;font-weight:600;">Inactive</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($promo['description']): ?>
        <div class="promo-desc"><?php echo htmlspecialchars($promo['description']); ?></div>
        <?php endif; ?>

        <div class="promo-meta">
            <?php if ($trigger === 'standard' && $promo['start_date']): ?>
            <i class="fa fa-calendar"></i>
            <?php echo date('d M Y', strtotime($promo['start_date'])); ?>
            &nbsp;&bull;&nbsp;
            <?php elseif ($trigger === 'birthday'): ?>
            <i class="fa fa-birthday-cake"></i> Sends on member birthdays
            <?php if (!empty($promo['birthday_start_date'])): ?>
            &nbsp;(from <?php echo date('d M Y', strtotime($promo['birthday_start_date'])); ?>)
            <?php endif; ?>
            &nbsp;&bull;&nbsp;
            <?php elseif ($trigger === 'signup_freebies'): ?>
            <i class="fa fa-star"></i>
            <?php echo (($promo['signup_recurrence'] ?? 'annual') === 'once') ? 'Once on signup' : 'Annually on signup date'; ?>
            &nbsp;&bull;&nbsp;
            <?php elseif ($trigger === 'stale_points'): ?>
            <i class="fa fa-clock-o"></i> Members inactive &ge;<?php echo (int)($promo['stale_days'] ?? 90); ?> days
            &nbsp;&bull;&nbsp;
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

<!-- ── Create / Edit Modal ─────────────────────────────────────────────────── -->
<div class="modal fade" id="promoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="promoModalTitle">New</h4>
            </div>
            <div class="modal-body" style="max-height:80vh;overflow-y:auto;">
                <input type="hidden" id="promo_id">

                <!-- DETAILS ───────────────────────────────────────────────── -->
                <div class="msec-title" style="margin-top:0;border:none;padding:0;">Details</div>

                <div class="row">
                    <div class="col-sm-4">
                        <div class="form-group">
                            <label>Type</label>
                            <select id="promo_type" class="form-control" onchange="onTypeChange()">
                                <option value="event">Event</option>
                                <option value="promotion" selected>Promotion</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <div class="form-group">
                            <label>Title <span class="text-danger">*</span></label>
                            <input type="text" id="promo_title" class="form-control" placeholder="e.g. Birthday Freebies">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="position:relative;">
                    <label>Description <span class="text-muted" style="font-size:11px;">(used as SMS / push body — title is not sent)</span></label>
                    <textarea id="promo_description" class="form-control" rows="3"
                        placeholder="e.g. Hi {{firstname}}, enjoy a free drink on us!"
                        oninput="updateSmsCounter()"></textarea>
                    <div id="var_autocomplete" class="var-autocomplete" style="display:none;"></div>

                    <!-- SMS counter bar -->
                    <div id="sms_counter_bar" style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;font-size:11px;color:#aaa;">
                        <span id="sms_segment_label"></span>
                        <span id="sms_char_count">0 / 160</span>
                    </div>
                    <div id="sms_warning" style="display:none;font-size:11px;margin-top:2px;"></div>

                    <div class="var-chips" id="var_chips">
                        <span style="font-size:11px;color:#aaa;margin-right:2px;">Insert:</span>
                        <span class="var-chip" data-var="{{firstname}}">{{firstname}}</span>
                        <span class="var-chip" data-var="{{lastname}}">{{lastname}}</span>
                        <span class="var-chip" data-var="{{name}}">{{name}}</span>
                        <span class="var-chip" data-var="{{birthday}}">{{birthday}}</span>
                        <span class="var-chip" data-var="{{points}}">{{points}}</span>
                        <span class="var-chip" data-var="{{phone}}">{{phone}}</span>
                        <span class="var-chip" data-var="{{tier}}">{{tier}}</span>
                        <span class="var-chip" data-var="{{signup_date}}">{{signup_date}}</span>
                        <span class="var-chip" data-var="{{voucher_code}}" style="background:#fff3cd;border-color:#f0c040;color:#856404;">{{voucher_code}}</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Image URL <span class="text-muted" style="font-size:11px;">(optional)</span></label>
                    <input type="text" id="promo_image_url" class="form-control" placeholder="https://...">
                </div>

                <!-- TRIGGER (Promotion only) ───────────────────────────────── -->
                <div class="msec" id="trigger_section">
                    <div class="msec-title">Trigger</div>
                    <div class="trigger-group">
                        <div class="trigger-opt">
                            <input type="radio" name="trigger_type" id="trig_standard" value="standard" checked>
                            <label for="trig_standard">
                                <i class="fa fa-calendar"></i> Standard
                                <small>Manual blast</small>
                            </label>
                        </div>
                        <div class="trigger-opt">
                            <input type="radio" name="trigger_type" id="trig_birthday" value="birthday">
                            <label for="trig_birthday">
                                <i class="fa fa-birthday-cake"></i> Birthday Freebie
                                <small>On member birthday</small>
                            </label>
                        </div>
                        <div class="trigger-opt">
                            <input type="radio" name="trigger_type" id="trig_signup" value="signup_freebies">
                            <label for="trig_signup">
                                <i class="fa fa-star"></i> Sign Up Freebies
                                <small>On signup date</small>
                            </label>
                        </div>
                        <div class="trigger-opt">
                            <input type="radio" name="trigger_type" id="trig_stale" value="stale_points">
                            <label for="trig_stale">
                                <i class="fa fa-clock-o"></i> Stale Points
                                <small>Long-absent members</small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SCHEDULE (Standard / Birthday / Event) ─────────────────── -->
                <div class="msec" id="schedule_section">
                    <div class="msec-title">Schedule</div>
                    <div class="row">
                        <div class="col-sm-6" id="start_date_wrap">
                            <div class="form-group">
                                <label id="start_date_label">Start Date</label>
                                <input type="date" id="promo_start_date" class="form-control">
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-group">
                                <label>End Date <small style="font-weight:normal;color:#888;">(optional)</small></label>
                                <input type="date" id="promo_end_date" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BIRTHDAY extra settings ────────────────────────────────── -->
                <div class="msec" id="birthday_section" style="display:none;">
                    <div class="msec-title">Birthday Targeting</div>
                    <div class="form-group">
                        <label style="font-weight:normal;">
                            <input type="checkbox" id="birthday_inactive_filter">
                            &nbsp;Only target members who have been inactive
                        </label>
                    </div>
                    <div id="birthday_inactive_days_wrap" style="display:none;max-width:260px;">
                        <div class="input-group">
                            <input type="number" id="birthday_inactive_days" class="form-control" value="90" min="1" max="3650">
                            <span class="input-group-addon">days</span>
                        </div>
                        <p class="help-block" style="font-size:11px;">Members with no transaction in this many days will receive the birthday message.</p>
                    </div>
                </div>

                <!-- SIGN UP FREEBIES settings ──────────────────────────────── -->
                <div class="msec" id="signup_section" style="display:none;">
                    <div class="msec-title">Sign Up Settings</div>
                    <div class="form-group">
                        <label>Send frequency</label>
                        <div>
                            <label style="font-weight:normal;margin-right:16px;">
                                <input type="radio" name="signup_recurrence" value="annual" checked>
                                &nbsp;Annually on their signup date
                            </label>
                            <label style="font-weight:normal;">
                                <input type="radio" name="signup_recurrence" value="once">
                                &nbsp;Once only (on signup)
                            </label>
                        </div>
                    </div>
                    <div class="form-group" style="max-width:280px;">
                        <label>Only send within first <span style="font-weight:normal;color:#888;">(optional)</span></label>
                        <div class="input-group">
                            <input type="number" id="signup_days_window" class="form-control" placeholder="No limit" min="1" max="3650">
                            <span class="input-group-addon">days of signup</span>
                        </div>
                        <p class="help-block" style="font-size:11px;">Leave blank to send to all members regardless of how long they have been registered.</p>
                    </div>
                </div>

                <!-- STALE POINTS settings ──────────────────────────────────── -->
                <div class="msec" id="stale_section" style="display:none;">
                    <div class="msec-title">Stale Points Settings</div>
                    <div class="form-group" style="max-width:240px;">
                        <label>Days without any transaction</label>
                        <div class="input-group">
                            <input type="number" id="promo_stale_days" class="form-control" value="90" min="1" max="3650">
                            <span class="input-group-addon">days</span>
                        </div>
                        <p class="help-block" style="font-size:11px;">Members inactive longer than this will be targeted.</p>
                    </div>
                </div>

                <!-- AUDIENCE ──────────────────────────────────────────────── -->
                <div class="msec" id="audience_section">
                    <div class="msec-title">Audience</div>

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

                    <!-- Birthday / Sign Up Freebies / Stale: optional tier filter -->
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
                        <div class="col-sm-5 col-xs-6">
                            <label style="font-weight:normal;font-size:13px;">
                                <input type="checkbox" id="promo_notify_push" value="1">
                                &nbsp;<i class="fa fa-bell" style="color:#1971c2;"></i> <strong>Push Notification</strong>
                            </label>
                            <p class="help-block" style="font-size:11px;margin-top:2px;">In-app notification bell</p>
                        </div>
                        <div class="col-sm-5 col-xs-6">
                            <label style="font-weight:normal;font-size:13px;">
                                <input type="checkbox" id="promo_notify_sms" value="1">
                                &nbsp;<i class="fa fa-mobile" style="color:#2f9e44;"></i> <strong>SMS (Twilio)</strong>
                            </label>
                            <p class="help-block" style="font-size:11px;margin-top:2px;">Text to phone number</p>
                        </div>
                    </div>

                    <!-- Birthday / Sign Up Freebies: timing checklist -->
                    <div id="birthday_timing_section" style="display:none;">
                        <div style="font-size:12px;font-weight:600;color:#555;margin-bottom:6px;">When to send:</div>
                        <div class="timing-list">
                            <label><input type="checkbox" name="bday_timing" value="0"> On the day</label>
                            <label><input type="checkbox" name="bday_timing" value="1"> 1 day before</label>
                            <label><input type="checkbox" name="bday_timing" value="3"> 3 days before</label>
                            <label><input type="checkbox" name="bday_timing" value="7"> 1 week before</label>
                            <label><input type="checkbox" name="bday_timing" value="14"> 2 weeks before</label>
                            <label><input type="checkbox" name="bday_timing" value="month_start"> Start of the month</label>
                        </div>
                    </div>

                    <div id="blast_preview" class="alert alert-info" style="display:none;font-size:12px;margin:10px 0 0;"></div>
                </div>

                <!-- VOUCHER ───────────────────────────────────────────────── -->
                <div class="msec">
                    <div class="msec-title" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <span>Voucher / Promo Code</span>
                        <label style="font-weight:normal;font-size:12px;margin:0;cursor:pointer;">
                            <input type="checkbox" id="promo_has_voucher" onchange="toggleVoucherFields()">
                            &nbsp;Attach a redeemable code
                        </label>
                    </div>

                    <div id="voucher_fields" style="display:none;">
                        <div class="voucher-section">

                            <!-- Code mode -->
                            <div class="form-group" style="margin-bottom:10px;">
                                <label style="font-size:12px;font-weight:600;color:#555;">Code Type</label>
                                <div style="display:flex;gap:16px;margin-top:4px;">
                                    <label style="font-weight:normal;font-size:13px;cursor:pointer;">
                                        <input type="radio" name="voucher_code_mode" value="shared" checked onchange="onCodeModeChange()">
                                        &nbsp;<strong>Shared</strong> <span style="color:#888;font-size:11px;">— one code for all (e.g. MDAY2025)</span>
                                    </label>
                                    <label style="font-weight:normal;font-size:13px;cursor:pointer;">
                                        <input type="radio" name="voucher_code_mode" value="unique_per_member" onchange="onCodeModeChange()">
                                        &nbsp;<strong>Unique per member</strong> <span style="color:#888;font-size:11px;">— auto-generated on blast</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Code / prefix -->
                            <div class="row">
                                <div class="col-sm-5">
                                    <div class="form-group">
                                        <label id="voucher_code_label" style="font-size:12px;">Code <span class="text-danger">*</span></label>
                                        <input type="text" id="voucher_base_code" class="form-control"
                                               placeholder="e.g. MDAY2025"
                                               style="font-family:monospace;text-transform:uppercase;"
                                               oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9\-_]/g,'')">
                                        <p class="help-block" id="voucher_code_hint" style="font-size:11px;">Members enter this code at the counter.</p>
                                    </div>
                                </div>
                                <div class="col-sm-3">
                                    <div class="form-group">
                                        <label style="font-size:12px;">Max Uses Per Member</label>
                                        <input type="number" id="voucher_max_uses_per_member" class="form-control" value="1" min="1" max="999">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;">Global Cap <span class="text-muted" style="font-size:11px;">(optional)</span></label>
                                        <div class="input-group">
                                            <input type="number" id="voucher_max_uses" class="form-control" placeholder="Unlimited" min="1">
                                            <span class="input-group-addon">uses</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reward type -->
                            <div class="form-group" style="margin-bottom:8px;">
                                <label style="font-size:12px;font-weight:600;color:#555;">Reward</label>
                            </div>
                            <div class="reward-row">
                                <div class="form-group">
                                    <label style="font-size:12px;">Type</label>
                                    <select id="voucher_reward_type" class="form-control" onchange="onRewardTypeChange()">
                                        <option value="discount_pct">Discount %</option>
                                        <option value="discount_fixed">Fixed Amount (RM)</option>
                                        <option value="points_bonus">Bonus Points</option>
                                        <option value="free_item">Free Item</option>
                                    </select>
                                </div>
                                <div class="form-group" id="reward_value_wrap">
                                    <label id="reward_value_label" style="font-size:12px;">Value <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" id="voucher_reward_value" class="form-control" placeholder="0" min="0" step="0.01">
                                        <span class="input-group-addon" id="reward_value_unit">%</span>
                                    </div>
                                </div>
                                <div class="form-group" id="reward_item_wrap" style="display:none;flex:2;">
                                    <label style="font-size:12px;">Item <span class="text-danger">*</span></label>
                                    <select id="voucher_reward_item" class="form-control">
                                        <option value="">— Select item —</option>
                                        <?php
                                        $current_category = null;
                                        foreach ($pos_items as $pi):
                                            $cat = $pi['category'] ?: 'Uncategorised';
                                            if ($cat !== $current_category):
                                                if ($current_category !== null) echo '</optgroup>';
                                                $current_category = $cat;
                                                echo '<optgroup label="' . htmlspecialchars($cat) . '">';
                                            endif;
                                        ?>
                                        <option value="<?php echo htmlspecialchars($pi['display_name']); ?>"><?php echo htmlspecialchars($pi['display_name']); ?></option>
                                        <?php endforeach; ?>
                                        <?php if ($current_category !== null) echo '</optgroup>'; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Validity dates -->
                            <div class="row" style="margin-top:4px;">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;">Valid From <span class="text-muted" style="font-size:11px;">(optional)</span></label>
                                        <input type="date" id="voucher_valid_from" class="form-control">
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label style="font-size:12px;">Valid Until <span class="text-muted" style="font-size:11px;">(optional)</span></label>
                                        <input type="date" id="voucher_valid_until" class="form-control">
                                    </div>
                                </div>
                            </div>

                            <p class="help-block" style="font-size:11px;margin-top:2px;">
                                <i class="fa fa-info-circle"></i>
                                Use <code>{{voucher_code}}</code> in the description above to embed the code in the SMS/push message.
                            </p>

                        </div><!-- /.voucher-section -->
                    </div><!-- /#voucher_fields -->
                </div>

            </div><!-- /.modal-body -->
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" id="previewSmsBtn" onclick="previewSms()" style="display:none;">
                    <i class="fa fa-eye"></i> Preview SMS
                </button>
                <button type="button" class="btn btn-primary" id="promoSaveBtn" onclick="savePromo()">
                    <i class="fa fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SMS Preview Modal (outside promoModal to avoid Bootstrap 3 dismiss-parent bug) -->
<div class="modal fade" id="smsPreviewModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-mobile"></i> SMS Preview</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted" style="font-size:11px;">Sample values substituted for merge tags.</p>
                <div id="smsPreviewBody" style="background:#f5f5f5;border:1px solid #ddd;border-radius:4px;padding:12px;font-size:13px;white-space:pre-wrap;word-break:break-word;min-height:60px;"></div>
                <div id="smsPreviewMeta" style="margin-top:8px;font-size:11px;color:#888;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function () {

// ── SMS character counter ─────────────────────────────────────────────────────
// GSM-7 basic charset — anything outside this forces Unicode mode (70 chars/segment)
var GSM7 = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1bÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';
var GSM7_EXT = '{}\\[~]|€^';

function isGsm7(str) {
    for (var i = 0; i < str.length; i++) {
        if (GSM7.indexOf(str[i]) === -1 && GSM7_EXT.indexOf(str[i]) === -1) return false;
    }
    return true;
}

// Approximate resolved length: replace known tags with their typical expansion
function estimatedLength(text) {
    return text
        .replace(/\{\{firstname\}\}/g,   'Ahmad')
        .replace(/\{\{lastname\}\}/g,    'Ali')
        .replace(/\{\{name\}\}/g,        'Ahmad Ali')
        .replace(/\{\{birthday\}\}/g,    '15 Jan')
        .replace(/\{\{points\}\}/g,      '1,250')
        .replace(/\{\{phone\}\}/g,       '60123456789')
        .replace(/\{\{tier\}\}/g,        'Gold')
        .replace(/\{\{signup_date\}\}/g, '01 Jan 2024')
        .replace(/\{\{voucher_code\}\}/g,'BDY-A3K9M');
}

window.updateSmsCounter = function () {
    var raw      = $('#promo_description').val();
    var resolved = estimatedLength(raw);
    var len      = resolved.length;
    var unicode  = !isGsm7(resolved);
    var single   = unicode ? 70  : 160;
    var multi    = unicode ? 67  : 153;
    var segments = len === 0 ? 0 : (len <= single ? 1 : Math.ceil(len / multi));
    var colour   = len === 0 ? '#aaa' : (len <= single * 0.85 ? '#5cb85c' : (len <= single ? '#f0ad4e' : '#d9534f'));
    var label    = segments <= 1
        ? (len + ' / ' + single + ' chars')
        : (len + ' chars — ' + segments + ' SMS parts');

    $('#sms_char_count').text(label).css('color', colour);

    var segLabel = '';
    if (unicode)       segLabel = '<span style="color:#e67e22;"><i class="fa fa-exclamation-triangle"></i> Unicode detected — limit 70 chars/part</span>';
    else if (segments > 1) segLabel = '<span style="color:#d9534f;"><i class="fa fa-exclamation-triangle"></i> Multi-part SMS (' + segments + ' parts) — billed ' + segments + 'x per recipient</span>';
    else if (len > single * 0.85) segLabel = '<span style="color:#f0ad4e;">Approaching limit</span>';
    $('#sms_segment_label').html(segLabel);

    var warn = '';
    if (len === 0 && ($('#promo_notify_sms').is(':checked'))) {
        warn = '<span style="color:#d9534f;"><i class="fa fa-warning"></i> Description is empty — SMS will not be sent.</span>';
    }
    $('#sms_warning').html(warn).toggle(warn !== '');
};

$('#promo_notify_sms').on('change', updateSmsCounter);

// ── Merge tag chips ───────────────────────────────────────────────────────────
var ALL_VARS = [
    { tag: '{{firstname}}',    desc: 'First name' },
    { tag: '{{lastname}}',     desc: 'Last name' },
    { tag: '{{name}}',         desc: 'Full name' },
    { tag: '{{birthday}}',     desc: 'Birthday (dd Mon)' },
    { tag: '{{points}}',       desc: 'Total points' },
    { tag: '{{phone}}',        desc: 'Phone number' },
    { tag: '{{tier}}',         desc: 'Membership tier' },
    { tag: '{{signup_date}}',  desc: 'Sign-up date' },
    { tag: '{{voucher_code}}', desc: 'Promo / voucher code' },
];

function insertVar(v) {
    var ta = document.getElementById('promo_description');
    var s  = ta.selectionStart, e = ta.selectionEnd;
    ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + v.length;
    ta.focus();
}

$(document).on('click', '.var-chip', function () {
    insertVar($(this).data('var'));
});

// Autocomplete on {{ typed in textarea
var $ac = $('#var_autocomplete');
var acIdx = -1;

$('#promo_description').on('input keydown', function (e) {
    if (e.type === 'keydown') {
        if (!$ac.is(':visible')) return;
        var items = $ac.find('.var-ac-item');
        if (e.key === 'ArrowDown') { e.preventDefault(); acIdx = Math.min(acIdx + 1, items.length - 1); items.removeClass('active').eq(acIdx).addClass('active'); return; }
        if (e.key === 'ArrowUp')   { e.preventDefault(); acIdx = Math.max(acIdx - 1, 0); items.removeClass('active').eq(acIdx).addClass('active'); return; }
        if (e.key === 'Enter' || e.key === 'Tab') {
            var active = items.filter('.active');
            if (active.length) { e.preventDefault(); doAcPick(active.data('tag')); }
            return;
        }
        if (e.key === 'Escape') { $ac.hide(); return; }
        return;
    }
    // input event
    var val = this.value;
    var pos = this.selectionStart;
    var before = val.slice(0, pos);
    var m = before.match(/\{\{(\w*)$/);
    if (!m) { $ac.hide(); return; }
    var q = m[1].toLowerCase();
    var matches = ALL_VARS.filter(function(v) { return v.tag.slice(2).toLowerCase().startsWith(q); });
    if (!matches.length) { $ac.hide(); return; }
    acIdx = -1;
    var html = matches.map(function(v) {
        return '<div class="var-ac-item" data-tag="' + v.tag + '"><span class="ac-tag">' + v.tag + '</span><span class="ac-desc">' + v.desc + '</span></div>';
    }).join('');
    $ac.html(html).show();
});

function doAcPick(tag) {
    var ta = document.getElementById('promo_description');
    var val = ta.value, pos = ta.selectionStart;
    var before = val.slice(0, pos);
    var m = before.match(/\{\{(\w*)$/);
    if (!m) return;
    var start = pos - m[0].length;
    ta.value = val.slice(0, start) + tag + val.slice(pos);
    ta.selectionStart = ta.selectionEnd = start + tag.length;
    ta.focus();
    $ac.hide();
}

$(document).on('click', '.var-ac-item', function () { doAcPick($(this).data('tag')); });

$(document).on('click', function (e) {
    if (!$(e.target).closest('#promo_description, #var_autocomplete').length) $ac.hide();
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
    $('#promo_target_customer_ids').val(selectedMembers.map(function(m){ return m.id; }).join(','));
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

// ── Type / Trigger UI ─────────────────────────────────────────────────────────
window.onTypeChange = function () { updateUI(); };

$('input[name="trigger_type"]').on('change', updateUI);

function updateUI() {
    var type    = $('#promo_type').val();
    var trigger = $('input[name="trigger_type"]:checked').val() || 'standard';

    var isEvent      = (type === 'event');
    var isRecurring  = !isEvent && (trigger === 'birthday' || trigger === 'signup_freebies' || trigger === 'stale_points');

    // Trigger section only for Promotion type
    $('#trigger_section').toggle(!isEvent);

    // Schedule section: Event always shows start date; Standard shows start date; recurring triggers hide it
    var showSchedule = isEvent || (!isEvent && trigger === 'standard') || (!isEvent && trigger === 'birthday');
    $('#schedule_section').toggle(showSchedule);

    if (isEvent) {
        $('#start_date_label').text('Event Date');
    } else if (trigger === 'birthday') {
        $('#start_date_label').text('Campaign Start Date');
    } else {
        $('#start_date_label').text('Start Date');
    }

    // Type-specific sections
    $('#signup_section').toggle(!isEvent && trigger === 'signup_freebies');
    $('#stale_section').toggle(!isEvent && trigger === 'stale_points');
    $('#birthday_section').toggle(!isEvent && trigger === 'birthday');

    // Audience
    $('#standard_audience').toggle(!isRecurring);
    $('#recurring_audience').toggle(isRecurring);

    // Timing checklist (birthday + signup_freebies only)
    $('#birthday_timing_section').toggle(!isEvent && (trigger === 'birthday' || trigger === 'signup_freebies'));

    // Preview SMS button: show when SMS is checked
    $('#previewSmsBtn').toggle($('#promo_notify_sms').is(':checked'));

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
    var type    = $('#promo_type').val();
    var trigger = $('input[name="trigger_type"]:checked').val() || 'standard';
    var preview = $('#blast_preview');

    if (!push && !sms) { preview.hide(); return; }

    var channels = [];
    if (push) channels.push('push');
    if (sms)  channels.push('SMS');
    var ch = channels.join(' + ');
    var msg;

    if (type === 'event' || trigger === 'standard') {
        msg = 'Save first, then click <strong>Blast</strong> to send ' + ch + ' to the selected audience.';
    } else if (trigger === 'birthday') {
        var sel = $('input[name="bday_timing"]:checked').map(function(){ return $(this).closest('label').text().trim(); }).get();
        msg = '<i class="fa fa-birthday-cake"></i> Sends ' + ch + ' to members based on their birthday.' +
              (sel.length ? ' Timing: ' + sel.join(', ') + '.' : ' <em>Select at least one timing option.</em>') +
              ' Recurs annually. Members already blasted this year are skipped.';
    } else if (trigger === 'signup_freebies') {
        var recur  = $('input[name="signup_recurrence"]:checked').val() || 'annual';
        var selSU  = $('input[name="bday_timing"]:checked').map(function(){ return $(this).closest('label').text().trim(); }).get();
        msg = '<i class="fa fa-star"></i> Sends ' + ch + ' to members based on their signup date. ' +
              (recur === 'once' ? 'Sent <strong>once only</strong> — members who already received it are skipped.' : 'Repeats <strong>annually</strong>.') +
              (selSU.length ? ' Timing: ' + selSU.join(', ') + '.' : ' <em>Select at least one timing option.</em>');
    } else if (trigger === 'stale_points') {
        var days = parseInt($('#promo_stale_days').val()) || 90;
        msg = '<i class="fa fa-clock-o"></i> Sends ' + ch + ' to members who haven\'t transacted in <strong>' + days + '+ days</strong>. Members already blasted this year are skipped.';
    }

    preview.html('<i class="fa fa-info-circle"></i> ' + msg).show();
}

$('#promo_notify_push, #promo_notify_sms').on('change', updateBlastPreview);
$('input[name="bday_timing"]').on('change', updateBlastPreview);
$('input[name="signup_recurrence"]').on('change', updateBlastPreview);
$('#promo_stale_days').on('input', updateBlastPreview);

// ── Voucher fields ────────────────────────────────────────────────────────────
window.toggleVoucherFields = function () {
    $('#voucher_fields').toggle($('#promo_has_voucher').is(':checked'));
};

window.onCodeModeChange = function () {
    var unique = $('input[name="voucher_code_mode"]:checked').val() === 'unique_per_member';
    $('#voucher_code_label').text(unique ? 'Code Prefix *' : 'Code *');
    $('#voucher_code_hint').text(
        unique
            ? 'Used as prefix for auto-generated codes, e.g. BDY → BDY-A3K9M. Embedded via {{voucher_code}} in the message.'
            : 'Members enter this code at the counter.'
    );
    $('#voucher_base_code').attr('placeholder', unique ? 'e.g. BDY' : 'e.g. MDAY2025');
};

window.onRewardTypeChange = function () {
    var t = $('#voucher_reward_type').val();
    var isFree = (t === 'free_item');
    $('#reward_value_wrap').toggle(!isFree);
    $('#reward_item_wrap').toggle(isFree);
    var units = { discount_pct: '%', discount_fixed: 'RM', points_bonus: 'pts' };
    $('#reward_value_unit').text(units[t] || '%');
    $('#reward_value_label').text(t === 'points_bonus' ? 'Bonus Points *' : 'Value *');
};

function resetVoucherFields() {
    $('#promo_has_voucher').prop('checked', false);
    $('#voucher_fields').hide();
    $('input[name="voucher_code_mode"][value="shared"]').prop('checked', true);
    $('#voucher_base_code').val('');
    $('#voucher_reward_type').val('discount_pct');
    $('#voucher_reward_value').val('');
    $('#voucher_reward_item').val('');
    $('#voucher_max_uses_per_member').val('1');
    $('#voucher_max_uses').val('');
    $('#voucher_valid_from').val('');
    $('#voucher_valid_until').val('');
    onCodeModeChange();
    onRewardTypeChange();
}

// ── Open / Reset ──────────────────────────────────────────────────────────────
window.openPromoModal = function () {
    resetModal();
    $('#promoModalTitle').text('New');
    $('#promoModal').modal('show');
};

function resetModal() {
    $('#promo_id').val('');
    $('#promo_title, #promo_description, #promo_image_url').val('');
    $('#promo_type').val('promotion');
    $('#promo_start_date, #promo_end_date').val('');
    $('#promo_target').val('all');
    $('#promo_target_tier').val('');
    $('#promo_target_tier_recurring').val('');
    $('#promo_target_customer_ids').val('');
    $('#promo_stale_days').val('90');
    $('#birthday_inactive_filter').prop('checked', false);
    $('#birthday_inactive_days').val('90');
    $('#birthday_inactive_days_wrap').hide();
    $('#signup_days_window').val('');
    selectedMembers = [];
    renderTags();
    $('#member_search_input').val('');
    $('#promo_notify_push, #promo_notify_sms').prop('checked', false);
    $('input[name="bday_timing"]').prop('checked', false);
    $('input[name="trigger_type"][value="standard"]').prop('checked', true);
    $('input[name="signup_recurrence"][value="annual"]').prop('checked', true);
    $('#previewSmsBtn').hide();
    resetVoucherFields();
    updateUI();
    onTargetChange();
    updateBlastPreview();
    updateSmsCounter();
}

// ── Edit ──────────────────────────────────────────────────────────────────────
$(document).on('click', '.edit-btn', function () {
    var raw   = $(this).attr('data-promo');
    var promo = JSON.parse(raw);
    resetModal();

    $('#promo_id').val(promo.id);
    $('#promo_title').val(promo.title || '');
    $('#promo_description').val(promo.description || '').trigger('input');
    $('#promo_image_url').val(promo.image_url || '');
    $('#promo_type').val(promo.type === 'event' ? 'event' : 'promotion');
    $('#promo_start_date').val(promo.start_date || '');
    $('#promo_notify_push').prop('checked', promo.notify_push == 1);
    $('#promo_notify_sms').prop('checked', promo.notify_sms == 1);
    $('#promo_stale_days').val(promo.stale_days || 90);

    var trigger = promo.trigger_type || 'standard';
    $('input[name="trigger_type"][value="' + trigger + '"]').prop('checked', true);

    // Sign up recurrence
    var recur = promo.signup_recurrence || 'annual';
    $('input[name="signup_recurrence"][value="' + recur + '"]').prop('checked', true);

    var isRecurring = trigger === 'birthday' || trigger === 'signup_freebies';
    if (isRecurring) {
        var timings = (promo.notify_days_before || '0').split(',');
        $('input[name="bday_timing"]').each(function() {
            $(this).prop('checked', timings.indexOf($(this).val()) !== -1);
        });
        $('#promo_target_tier_recurring').val(promo.target_tier || '');
    } else {
        var target = promo.target || 'all';
        $('#promo_target').val(target);
        $('#promo_target_tier').val(promo.target_tier || '');
        if (target === 'individual' && promo.target_customer_id) {
            var ids = promo.target_customer_id.toString().split(',').filter(Boolean);
            $('#promo_target_customer_ids').val(ids.join(','));
            $('#member_tags_inner').html('<em style="color:#aaa;font-size:12px;">Previous selection (' + ids.length + ' member(s)). Re-search to modify.</em>');
        }
    }

    // New fields
    $('#promo_end_date').val(promo.end_date || '');
    var inactiveFilter = promo.birthday_inactive_filter == 1;
    $('#birthday_inactive_filter').prop('checked', inactiveFilter);
    $('#birthday_inactive_days').val(promo.birthday_inactive_days || 90);
    $('#birthday_inactive_days_wrap').toggle(inactiveFilter);
    $('#signup_days_window').val(promo.signup_days_window || '');

    // Populate voucher fields from data-voucher if present
    var voucherRaw = $(this).attr('data-voucher');
    if (voucherRaw && voucherRaw !== 'null') {
        try {
            var v = JSON.parse(voucherRaw);
            if (v && v.id) {
                $('#promo_has_voucher').prop('checked', true);
                $('#voucher_fields').show();
                $('input[name="voucher_code_mode"][value="' + (v.code_mode || 'shared') + '"]').prop('checked', true);
                $('#voucher_base_code').val(v.base_code || '');
                $('#voucher_reward_type').val(v.reward_type || 'discount_pct');
                $('#voucher_reward_value').val(v.reward_value !== null ? v.reward_value : '');
                $('#voucher_reward_item').val(v.reward_item || '');
                $('#voucher_max_uses_per_member').val(v.max_uses_per_member || 1);
                $('#voucher_max_uses').val(v.max_uses || '');
                $('#voucher_valid_from').val(v.valid_from || '');
                $('#voucher_valid_until').val(v.valid_until || '');
                onCodeModeChange();
                onRewardTypeChange();
            }
        } catch (e) {}
    }

    $('#promoModalTitle').text('Edit');
    updateUI();
    onTargetChange();
    updateBlastPreview();
    $('#promoModal').modal('show');
});

// ── Save ──────────────────────────────────────────────────────────────────────
window.savePromo = function () {
    var title = $.trim($('#promo_title').val());
    if (!title) { alert('Title is required'); return; }

    var desc    = $.trim($('#promo_description').val());
    var smsOn   = $('#promo_notify_sms').is(':checked');
    var pushOn  = $('#promo_notify_push').is(':checked');

    if ((smsOn || pushOn) && !desc) {
        alert('Description is required when SMS or Push notification is enabled — it is the message your members will receive.');
        $('#promo_description').focus();
        return;
    }

    if (smsOn && desc) {
        var resolved = estimatedLength(desc);
        var unicode  = !isGsm7(resolved);
        var single   = unicode ? 70 : 160;
        var multi    = unicode ? 67 : 153;
        var segments = resolved.length <= single ? 1 : Math.ceil(resolved.length / multi);
        if (segments > 3) {
            if (!confirm('This SMS is ' + segments + ' parts long (' + resolved.length + ' chars). Each recipient will cost ' + segments + 'x your SMS rate. Continue?')) return;
        }
    }

    var type    = $('#promo_type').val();
    var trigger = type === 'event' ? 'standard' : ($('input[name="trigger_type"]:checked').val() || 'standard');
    var isRecurring = trigger === 'birthday' || trigger === 'signup_freebies';
    var target  = isRecurring ? 'all' : $('#promo_target').val();
    var tier    = isRecurring ? $('#promo_target_tier_recurring').val() : $('#promo_target_tier').val();

    if (target === 'individual' && !$('#promo_target_customer_ids').val()) {
        alert('Please select at least one member.'); return;
    }

    var daysBefore = '0';
    if (isRecurring) {
        var checked = $('input[name="bday_timing"]:checked').map(function(){ return $(this).val(); }).get();
        if (!checked.length) { alert('Please select at least one timing option.'); return; }
        daysBefore = checked.join(',');
    }

    var btn = $('#promoSaveBtn').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    // Voucher validation
    var hasVoucher = $('#promo_has_voucher').is(':checked');
    if (hasVoucher) {
        if (!$.trim($('#voucher_base_code').val())) {
            alert('Voucher code / prefix is required'); return;
        }
        var rtype = $('#voucher_reward_type').val();
        if (rtype !== 'free_item' && !$.trim($('#voucher_reward_value').val())) {
            alert('Reward value is required'); return;
        }
        if (rtype === 'free_item' && !$.trim($('#voucher_reward_item').val())) {
            alert('Free item description is required'); return;
        }
    }

    $.post('<?php echo admin_url('loyalty/ajax_save_promotion'); ?>', {
        id:                      $('#promo_id').val(),
        title:                   title,
        description:             $('#promo_description').val(),
        image_url:               $('#promo_image_url').val(),
        type:                    type,
        start_date:              $('#promo_start_date').val(),
        trigger_type:            trigger,
        target:                  target,
        target_tier:             tier,
        target_customer_id:      $('#promo_target_customer_ids').val(),
        notify_push:             $('#promo_notify_push').is(':checked') ? 1 : 0,
        notify_sms:              $('#promo_notify_sms').is(':checked') ? 1 : 0,
        notify_days_before:      daysBefore,
        signup_recurrence:       $('input[name="signup_recurrence"]:checked').val() || 'annual',
        stale_days:              $('#promo_stale_days').val() || 90,
        birthday_start_date:     type === 'event' ? '' : ($('#promo_start_date').val() && trigger === 'birthday' ? $('#promo_start_date').val() : ''),
        end_date:                $('#promo_end_date').val(),
        birthday_inactive_filter: $('#birthday_inactive_filter').is(':checked') ? 1 : 0,
        birthday_inactive_days:  $('#birthday_inactive_days').val() || 90,
        signup_days_window:      $('#signup_days_window').val() || '',
        has_voucher:             hasVoucher ? 1 : 0,
        voucher_code_mode:       $('input[name="voucher_code_mode"]:checked').val() || 'shared',
        voucher_base_code:       $('#voucher_base_code').val(),
        voucher_reward_type:     $('#voucher_reward_type').val(),
        voucher_reward_value:    $('#voucher_reward_value').val(),
        voucher_reward_item:     $('#voucher_reward_item').val(),
        voucher_max_uses_per_member: $('#voucher_max_uses_per_member').val() || 1,
        voucher_max_uses:        $('#voucher_max_uses').val(),
        voucher_valid_from:      $('#voucher_valid_from').val(),
        voucher_valid_until:     $('#voucher_valid_until').val(),
    }, function (r) {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
        if (r.success) {
            $('#promoModal').modal('hide');
            location.reload();
        } else {
            alert(r.message || 'Failed to save');
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
    if (!confirm('Blast "' + title + '" now?\nThis sends immediately to all matching members.')) return;
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

// ── Clone ─────────────────────────────────────────────────────────────────────
$(document).on('click', '.clone-btn', function () {
    var id   = $(this).data('id');
    var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
    $.post('<?php echo admin_url('loyalty/ajax_clone_promotion'); ?>', { id: id }, function (r) {
        $btn.prop('disabled', false).html('<i class="fa fa-copy"></i>');
        if (r.success) { location.reload(); }
        else alert(r.message || 'Clone failed');
    }, 'json').fail(function () {
        $btn.prop('disabled', false).html('<i class="fa fa-copy"></i>');
        alert('Request failed.');
    });
});

// ── Toggle Active ─────────────────────────────────────────────────────────────
$(document).on('click', '.toggle-active-btn', function () {
    var id      = $(this).data('id');
    var current = parseInt($(this).data('active'));
    var newVal  = current ? 0 : 1;
    var $btn    = $(this).prop('disabled', true);
    $.post('<?php echo admin_url('loyalty/ajax_toggle_active'); ?>', { id: id, is_active: newVal }, function (r) {
        if (r.success) { location.reload(); }
        else { $btn.prop('disabled', false); alert(r.message || 'Failed'); }
    }, 'json').fail(function () {
        $btn.prop('disabled', false); alert('Request failed.');
    });
});

// ── Birthday inactive filter ───────────────────────────────────────────────────
$('#birthday_inactive_filter').on('change', function () {
    $('#birthday_inactive_days_wrap').toggle($(this).is(':checked'));
});

// ── Preview SMS ───────────────────────────────────────────────────────────────
window.previewSms = function () {
    var body  = $('#promo_description').val();
    var vcode = $('#voucher_base_code').val() || '';
    $.post('<?php echo admin_url('loyalty/ajax_preview_sms'); ?>', { body: body, voucher_code: vcode }, function (r) {
        if (!r.success) { alert('Preview unavailable'); return; }
        var text = r.preview;
        var resolved = estimatedLength(text);
        var unicode  = !isGsm7(resolved);
        var single   = unicode ? 70 : 160;
        var multi    = unicode ? 67 : 153;
        var segments = text.length === 0 ? 0 : (text.length <= single ? 1 : Math.ceil(text.length / multi));
        $('#smsPreviewBody').text(text);
        $('#smsPreviewMeta').html(text.length + ' chars &bull; ' + segments + ' SMS part(s)' + (unicode ? ' &bull; <span style="color:#e67e22">Unicode</span>' : ''));
        $('#smsPreviewModal').modal('show');
    }, 'json');
};

// Show/hide preview SMS btn when sms checkbox changes
$('#promo_notify_sms').on('change', function () {
    $('#previewSmsBtn').toggle($(this).is(':checked'));
    updateUI();
});

// Init
updateUI();
onTargetChange();
onCodeModeChange();
onRewardTypeChange();

}); // end $(function)
</script>
