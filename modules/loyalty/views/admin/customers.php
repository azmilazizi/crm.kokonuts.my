<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
.loyalty-stat-card { background:#fff; border:1px solid #e0e0e0; border-radius:6px; padding:18px 22px; margin-bottom:18px; }
.loyalty-stat-card .stat-value { font-size:26px; font-weight:700; color:#333; }
.loyalty-stat-card .stat-label { font-size:12px; color:#888; margin-top:2px; }
.tier-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; background:#eee; color:#555; }
.member-table tbody tr { cursor:pointer; }
.member-table tbody tr:hover { background:#f5f9ff; }
.filter-bar { background:#fff; border:1px solid #ddd; border-radius:4px; padding:12px 16px; margin-bottom:18px; }
.pagination-wrap { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
th.sortable { cursor: pointer; user-select: none; }
th.sortable:hover { background: #f0f4ff; }
th.sortable::after { content: ' \2195'; color: #ccc; font-size: 10px; }
th.sort-asc::after  { content: ' \25B2'; color: #337ab7; font-size: 10px; }
th.sort-desc::after { content: ' \25BC'; color: #337ab7; font-size: 10px; }
</style>

<div id="wrapper">
<div class="content">

    <!-- Toolbar -->
    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-6">
            <h4 class="no-margin-top" style="margin-bottom:4px;">Loyalty Members</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/customers'); ?>">Loyalty</a></li>
                <li class="active">Members</li>
            </ol>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row">
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_members']); ?></div>
                <div class="stat-label">Total Members</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_points'], 2); ?></div>
                <div class="stat-label">Points Outstanding</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_earned'], 2); ?></div>
                <div class="stat-label">Total Points Earned</div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="loyalty-stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_redeemed'], 2); ?></div>
                <div class="stat-label">Total Points Redeemed</div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="<?php echo admin_url('loyalty/customers'); ?>" id="filter-form">
    <div class="filter-bar">
        <div class="row">
            <div class="col-md-6">
                <div class="input-group input-group-sm">
                    <input type="text" name="q" class="form-control" placeholder="Search name, phone, email…" value="<?php echo htmlspecialchars($filters['search']); ?>">
                    <span class="input-group-btn">
                        <button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
                    </span>
                </div>
            </div>
            <div class="col-md-2">
                <select name="limit" class="form-control input-sm" onchange="this.form.submit()">
                    <?php foreach ([10, 20, 50, 100] as $l): ?>
                    <option value="<?php echo $l; ?>" <?php echo (int)$filters['per_page'] === $l ? 'selected' : ''; ?>><?php echo $l; ?> per page</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <input type="hidden" name="page" id="page-input" value="1">
    <input type="hidden" name="sort" id="sort-input" value="<?php echo htmlspecialchars($filters['sort']); ?>">
    <input type="hidden" name="dir"  id="dir-input"  value="<?php echo htmlspecialchars($filters['dir']); ?>">
    </form>

    <!-- Results Info -->
    <div class="row" style="margin-bottom:10px;">
        <div class="col-sm-6">
            <span class="text-muted" style="font-size:13px;">
                Page <strong><?php echo $result['page']; ?></strong> of <strong><?php echo $result['page_count']; ?></strong>
                &nbsp;|&nbsp; <strong><?php echo number_format($result['total']); ?></strong> members
            </span>
        </div>
        <div class="col-sm-6 text-right">
            <div class="pagination-wrap" style="justify-content:flex-end;">
                <?php if ($result['page'] > 1): ?>
                <button class="btn btn-default btn-sm" onclick="goPage(<?php echo $result['page'] - 1; ?>)"><i class="fa fa-chevron-left"></i></button>
                <?php endif; ?>
                <span class="text-muted" style="font-size:12px;">Page <?php echo $result['page']; ?></span>
                <?php if ($result['page'] < $result['page_count']): ?>
                <button class="btn btn-default btn-sm" onclick="goPage(<?php echo $result['page'] + 1; ?>)"><i class="fa fa-chevron-right"></i></button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="panel_s">
        <div class="panel-body no-padding">
            <table class="table table-hover no-margin member-table">
                <thead>
                    <tr>
                        <?php $s = $filters['sort']; $d = $filters['dir']; ?>
                        <th class="sortable <?php echo $s==='name'          ? 'sort-'.$d : ''; ?>" onclick="sortBy('name')">Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th class="text-right sortable <?php echo $s==='total_points' ? 'sort-'.$d : ''; ?>" onclick="sortBy('total_points')">Points</th>
                        <th>Tier</th>
                        <th class="text-right sortable <?php echo $s==='total_spent'  ? 'sort-'.$d : ''; ?>" onclick="sortBy('total_spent')">Total Spent</th>
                        <th class="sortable <?php echo $s==='last_visit'    ? 'sort-'.$d : ''; ?>" onclick="sortBy('last_visit')">Last Visit</th>
                        <th class="sortable <?php echo $s==='registered_at' ? 'sort-'.$d : ''; ?>" onclick="sortBy('registered_at')">Joined</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted" style="padding:30px;">
                            No members found.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr onclick="window.location='<?php echo admin_url('loyalty/customer/' . (int)$r['id']); ?>'">
                        <td style="font-weight:500;"><?php echo htmlspecialchars($r['name'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['phone'] ?: '—'); ?></td>
                        <td class="text-muted"><?php echo htmlspecialchars($r['email'] ?: '—'); ?></td>
                        <td class="text-right"><strong style="color:#337ab7;"><?php echo number_format((float)$r['total_points'], 2); ?></strong></td>
                        <td>
                            <?php if ($r['tier']): ?>
                            <span class="tier-badge" style="background:<?php echo htmlspecialchars($r['tier']['color'] ?? '#eee'); ?>;color:#fff;">
                                <?php echo htmlspecialchars($r['tier']['name'] ?? ''); ?>
                            </span>
                            <?php else: ?>
                            <span class="tier-badge">Member</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">RM <?php echo number_format((float)$r['total_spent'], 2); ?></td>
                        <td class="text-muted" style="white-space:nowrap;">
                            <?php echo $r['last_visit'] ? date('d/m/Y', strtotime($r['last_visit'])) : '—'; ?>
                        </td>
                        <td class="text-muted" style="white-space:nowrap;font-size:12px;">
                            <?php echo date('d/m/Y', strtotime($r['registered_at'])); ?>
                        </td>
                        <td class="text-right" style="white-space:nowrap;" onclick="event.stopPropagation()">
                            <?php if (has_permission('loyalty', '', 'edit')): ?>
                            <button class="btn btn-sm btn-default" onclick="openEditMember(<?php echo $r['id']; ?>,<?php echo htmlspecialchars(json_encode(['name'=>$r['name'],'phone'=>$r['phone'],'email'=>$r['email'],'birthday'=>$r['birthday'],'address1'=>$r['address1'],'address2'=>$r['address2'],'city'=>$r['city'],'state'=>$r['state'],'postcode'=>$r['postcode']]), ENT_QUOTES); ?>)">
                                <i class="fa fa-pencil"></i>
                            </button>
                            <?php endif; ?>
                            <?php if (has_permission('loyalty', '', 'delete')): ?>
                            <button class="btn btn-sm btn-danger" onclick="deleteMember(<?php echo $r['id']; ?>, '<?php echo htmlspecialchars(addslashes($r['name'] ?: $r['phone']), ENT_QUOTES); ?>')">
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

    <!-- Bottom Pagination -->
    <?php if ($result['page_count'] > 1): ?>
    <div class="text-center" style="margin-top:16px;">
        <ul class="pagination pagination-sm" style="margin:0;">
            <?php if ($result['page'] > 1): ?>
            <li><a href="#" onclick="goPage(<?php echo $result['page'] - 1; ?>);return false;">&laquo;</a></li>
            <?php endif; ?>
            <?php
            $start = max(1, $result['page'] - 2);
            $end   = min($result['page_count'], $result['page'] + 2);
            if ($start > 1) echo '<li class="disabled"><a>…</a></li>';
            for ($p = $start; $p <= $end; $p++):
            ?>
            <li class="<?php echo $p === $result['page'] ? 'active' : ''; ?>">
                <a href="#" onclick="goPage(<?php echo $p; ?>);return false;"><?php echo $p; ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($end < $result['page_count']) echo '<li class="disabled"><a>…</a></li>'; ?>
            <?php if ($result['page'] < $result['page_count']): ?>
            <li><a href="#" onclick="goPage(<?php echo $result['page'] + 1; ?>);return false;">&raquo;</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

</div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="edit-member-modal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Member</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-member-id">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" id="edit-member-name" class="form-control" placeholder="Full name">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Phone</label>
                            <input type="text" id="edit-member-phone" class="form-control" placeholder="Phone number">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="edit-member-email" class="form-control" placeholder="Email address">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Birthday</label>
                            <input type="date" id="edit-member-birthday" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Address Line 1</label>
                    <input type="text" id="edit-member-address1" class="form-control" placeholder="Street address">
                </div>
                <div class="form-group">
                    <label>Address Line 2</label>
                    <input type="text" id="edit-member-address2" class="form-control" placeholder="Unit, suite, etc.">
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Postcode</label>
                            <input type="text" id="edit-member-postcode" class="form-control" placeholder="Postcode">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" id="edit-member-city" class="form-control" placeholder="City">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>State</label>
                            <input type="text" id="edit-member-state" class="form-control" placeholder="State">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="edit-member-save-btn" onclick="saveMember()">
                    <i class="fa fa-check"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function goPage(p) {
    document.getElementById('page-input').value = p;
    document.getElementById('filter-form').submit();
}

function sortBy(col) {
    var sortInput = document.getElementById('sort-input');
    var dirInput  = document.getElementById('dir-input');
    if (sortInput.value === col) {
        dirInput.value = dirInput.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortInput.value = col;
        dirInput.value  = 'desc';
    }
    document.getElementById('page-input').value = 1;
    document.getElementById('filter-form').submit();
}

function openEditMember(id, data) {
    $('#edit-member-id').val(id);
    $('#edit-member-name').val(data.name || '');
    $('#edit-member-phone').val(data.phone || '');
    $('#edit-member-email').val(data.email || '');
    $('#edit-member-birthday').val(data.birthday || '');
    $('#edit-member-address1').val(data.address1 || '');
    $('#edit-member-address2').val(data.address2 || '');
    $('#edit-member-city').val(data.city || '');
    $('#edit-member-state').val(data.state || '');
    $('#edit-member-postcode').val(data.postcode || '');
    $('#edit-member-modal').modal('show');
}

function saveMember() {
    var btn = $('#edit-member-save-btn').prop('disabled', true);
    $.post(ADMIN_URL + 'loyalty/ajax_update_customer', {
        id:       $('#edit-member-id').val(),
        name:     $('#edit-member-name').val(),
        phone:    $('#edit-member-phone').val(),
        email:    $('#edit-member-email').val(),
        birthday: $('#edit-member-birthday').val(),
        address1: $('#edit-member-address1').val(),
        address2: $('#edit-member-address2').val(),
        city:     $('#edit-member-city').val(),
        state:    $('#edit-member-state').val(),
        postcode: $('#edit-member-postcode').val(),
    }, function(resp) {
        btn.prop('disabled', false);
        if (resp.success) {
            $('#edit-member-modal').modal('hide');
            location.reload();
        } else {
            alert(resp.message || 'Failed to update member');
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false);
        alert('Request failed. Please try again.');
    });
}

function deleteMember(id, name) {
    if (!confirm('Delete member "' + name + '"?\n\nThis will also remove all their transaction history and cannot be undone.')) return;
    $.post(ADMIN_URL + 'loyalty/ajax_delete_customer', { id: id }, function(resp) {
        if (resp.success) {
            location.reload();
        } else {
            alert(resp.message || 'Failed to delete member');
        }
    }, 'json').fail(function() {
        alert('Request failed. Please try again.');
    });
}
</script>

<?php init_tail(); ?>
