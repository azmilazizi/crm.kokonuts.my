<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/** @var string $title */
/** @var array  $tokens */
/** @var array  $warehouses */
/** @var array  $staff */
?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#generateTokenModal">
                                    <i class="fa fa-plus"></i> New Token
                                </button>
                            </div>
                        </div>
                        <hr />
                        <p class="text-muted">
                            Each token grants a specific staff member access to a specific store in the Flutter POS app.<br />
                            Pass the token as <code>Authorization: Bearer &lt;token&gt;</code> on every API request.
                        </p>

                        <?php if (empty($warehouses)): ?>
                        <div class="alert alert-warning">
                            <i class="fa fa-warning"></i> No warehouses found. <a href="<?php echo admin_url('warehouse/warehouse_mange'); ?>">Create a warehouse first</a> before generating tokens.
                        </div>
                        <?php endif; ?>

                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Warehouse</th>
                                    <th>Token</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tokens)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No tokens yet.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($tokens as $t): ?>
                                <tr id="row-<?php echo $t['id']; ?>">
                                    <td><?php echo htmlspecialchars($t['staff_name'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($t['warehouse_name'] ?: '—'); ?></td>
                                    <td>
                                        <code style="word-break:break-all;font-size:11px;"><?php echo htmlspecialchars(substr($t['token'], 0, 16) . '...'); ?></code>
                                        <button class="btn btn-xs btn-default copy-btn" data-token="<?php echo htmlspecialchars($t['token']); ?>" title="Copy full token">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ((int) $t['active'] === 1): ?>
                                            <span class="label label-success">Active</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($t['created_at'])); ?></td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-default toggle-btn" data-id="<?php echo $t['id']; ?>">
                                            <?php echo (int) $t['active'] === 1 ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        <button class="btn btn-xs btn-danger delete-btn" data-id="<?php echo $t['id']; ?>">
                                            <i class="fa fa-trash"></i>
                                        </button>
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
    </div>
</div>

<!-- Generate Token Modal -->
<div class="modal fade" id="generateTokenModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">New POS Token</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Staff Member <span class="text-danger">*</span></label>
                    <select id="token-staff" class="form-control selectpicker" data-live-search="true">
                        <option value="">— Select staff member —</option>
                        <?php foreach ($staff as $s): ?>
                        <option value="<?php echo $s['staffid']; ?>">
                            <?php echo htmlspecialchars($s['firstname'] . ' ' . $s['lastname'] . ' (' . $s['email'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Warehouse <span class="text-danger">*</span></label>
                    <select id="token-warehouse" class="form-control selectpicker" data-live-search="true">
                        <option value="">— Select warehouse —</option>
                        <?php foreach ($warehouses as $wh): ?>
                        <option value="<?php echo $wh['warehouse_id']; ?>">
                            <?php echo htmlspecialchars($wh['warehouse_name'] . ($wh['warehouse_code'] ? ' (' . $wh['warehouse_code'] . ')' : '')); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-generate">Generate Token</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function () {

    $('#btn-generate').on('click', function () {
        var staff_id     = $('#token-staff').val();
        var warehouse_id = $('#token-warehouse').val();
        if (!staff_id || !warehouse_id) {
            alert('Please select both a staff member and a warehouse.');
            return;
        }
        $.post('<?php echo admin_url('pos/ajax_generate_token'); ?>', {
            staff_id:     staff_id,
            warehouse_id: warehouse_id,
        }, function (resp) {
            if (resp.success) {
                $('#generateTokenModal').modal('hide');
                location.reload();
            } else {
                alert(resp.message || 'Failed to generate token');
            }
        }, 'json');
    });

    $(document).on('click', '.copy-btn', function () {
        var token = $(this).data('token');
        navigator.clipboard.writeText(token).then(function () {
            alert_float('success', 'Token copied to clipboard');
        });
    });

    $(document).on('click', '.toggle-btn', function () {
        var id = $(this).data('id');
        $.post('<?php echo admin_url('pos/ajax_toggle_token/'); ?>' + id, function (resp) {
            if (resp.success) { location.reload(); }
        }, 'json');
    });

    $(document).on('click', '.delete-btn', function () {
        if (!confirm('Delete this token? The Flutter app using it will immediately lose access.')) return;
        var id = $(this).data('id');
        $.post('<?php echo admin_url('pos/ajax_delete_token/'); ?>' + id, function (resp) {
            if (resp.success) { $('#row-' + id).remove(); }
        }, 'json');
    });

});
</script>
</body>
</html>
