<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.promo-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:18px 20px; margin-bottom:14px; position:relative; }
.promo-card .promo-title { font-size:16px; font-weight:600; color:#222; margin-bottom:4px; }
.promo-card .promo-desc  { font-size:13px; color:#666; margin-bottom:10px; white-space:pre-line; }
.promo-card .promo-meta  { font-size:12px; color:#aaa; }
.type-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; text-transform:uppercase; }
.type-discount     { background:#d4edda; color:#155724; }
.type-event        { background:#cce5ff; color:#004085; }
.type-announcement { background:#fff3cd; color:#856404; }
.status-active   { color:#5cb85c; font-weight:600; }
.status-inactive { color:#aaa; }
.promo-actions { position:absolute; top:16px; right:16px; }
.empty-state { text-align:center; padding:60px 20px; color:#aaa; }
.empty-state i { font-size:48px; margin-bottom:12px; display:block; }
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
        <p>No promotions yet. Create your first promotion to engage members.</p>
        <?php if (has_permission('loyalty', '', 'create')): ?>
        <button class="btn btn-primary" onclick="openPromoModal()">Create Promotion</button>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <?php foreach ($rows as $promo): ?>
    <div class="promo-card" id="promo-<?php echo $promo['id']; ?>">
        <div class="promo-actions">
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

        <div>
            <span class="type-badge type-<?php echo $promo['type']; ?>"><?php echo ucfirst($promo['type']); ?></span>
            &nbsp;
            <?php if ($promo['is_active']): ?>
            <span class="status-active"><i class="fa fa-circle" style="font-size:8px;"></i> Active</span>
            <?php else: ?>
            <span class="status-inactive"><i class="fa fa-circle" style="font-size:8px;"></i> Inactive</span>
            <?php endif; ?>
            <?php if ($promo['target_tier']): ?>
            <span class="label label-default" style="margin-left:6px;"><?php echo htmlspecialchars($promo['target_tier']); ?> only</span>
            <?php endif; ?>
        </div>

        <div class="promo-title" style="margin-top:8px;"><?php echo htmlspecialchars($promo['title']); ?></div>

        <?php if ($promo['description']): ?>
        <div class="promo-desc"><?php echo htmlspecialchars($promo['description']); ?></div>
        <?php endif; ?>

        <div class="promo-meta">
            <?php if ($promo['start_date'] || $promo['end_date']): ?>
            <i class="fa fa-calendar"></i>
            <?php echo $promo['start_date'] ? date('d M Y', strtotime($promo['start_date'])) : 'Now'; ?>
            &rarr;
            <?php echo $promo['end_date'] ? date('d M Y', strtotime($promo['end_date'])) : 'No end date'; ?>
            &nbsp;&bull;&nbsp;
            <?php endif; ?>
            <i class="fa fa-clock-o"></i> Created <?php echo date('d M Y', strtotime($promo['created_at'])); ?>
            <?php if ($promo['image_url']): ?>
            &nbsp;&bull;&nbsp;<i class="fa fa-image"></i> Has image
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Pagination -->
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
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="promoModalTitle">New Promotion</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="promo_id" value="">

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
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Target Tier</label>
                            <input type="text" id="promo_target_tier" class="form-control" placeholder="Leave blank for all members">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="promo_description" class="form-control" rows="3" placeholder="Details about the promotion..."></textarea>
                </div>

                <div class="form-group">
                    <label>Image URL</label>
                    <input type="text" id="promo_image_url" class="form-control" placeholder="https://...">
                </div>

                <div class="row">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" id="promo_start_date" class="form-control">
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" id="promo_end_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="promo_is_active" value="1" checked>
                        &nbsp;Active (visible in member app)
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
function openPromoModal(promo) {
    $('#promo_id').val('');
    $('#promo_title').val('');
    $('#promo_description').val('');
    $('#promo_image_url').val('');
    $('#promo_type').val('announcement');
    $('#promo_target_tier').val('');
    $('#promo_start_date').val('');
    $('#promo_end_date').val('');
    $('#promo_is_active').prop('checked', true);
    $('#promoModalTitle').text('New Promotion');
    $('#promoModal').modal('show');
}

function editPromo(promo) {
    $('#promo_id').val(promo.id);
    $('#promo_title').val(promo.title || '');
    $('#promo_description').val(promo.description || '');
    $('#promo_image_url').val(promo.image_url || '');
    $('#promo_type').val(promo.type || 'announcement');
    $('#promo_target_tier').val(promo.target_tier || '');
    $('#promo_start_date').val(promo.start_date || '');
    $('#promo_end_date').val(promo.end_date || '');
    $('#promo_is_active').prop('checked', promo.is_active == 1);
    $('#promoModalTitle').text('Edit Promotion');
    $('#promoModal').modal('show');
}

function savePromo() {
    var title = $.trim($('#promo_title').val());
    if (!title) { alert('Title is required'); return; }

    var btn = $('#promoSaveBtn').prop('disabled', true).text('Saving...');

    $.post('<?php echo admin_url('loyalty/ajax_save_promotion'); ?>', {
        id:          $('#promo_id').val(),
        title:       title,
        description: $('#promo_description').val(),
        image_url:   $('#promo_image_url').val(),
        type:        $('#promo_type').val(),
        target_tier: $('#promo_target_tier').val(),
        start_date:  $('#promo_start_date').val(),
        end_date:    $('#promo_end_date').val(),
        is_active:   $('#promo_is_active').is(':checked') ? 1 : 0,
    }, function(r) {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
        if (r.success) {
            $('#promoModal').modal('hide');
            location.reload();
        } else {
            alert(r.message || 'Failed to save promotion');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save');
        alert('Request failed. Please try again.');
    });
}

function deletePromo(id, title) {
    if (!confirm('Delete promotion "' + title + '"? This cannot be undone.')) return;
    $.post('<?php echo admin_url('loyalty/ajax_delete_promotion'); ?>', { id: id }, function(r) {
        if (r.success) {
            $('#promo-' + id).fadeOut(300, function() { $(this).remove(); });
        } else {
            alert('Failed to delete promotion');
        }
    }, 'json');
}
</script>

<?php init_tail(); ?>
