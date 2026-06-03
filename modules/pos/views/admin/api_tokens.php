<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
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
                                    <i class="fa fa-plus"></i> Generate Token
                                </button>
                            </div>
                        </div>
                        <hr />
                        <p class="text-muted">
                            These tokens are used by the Flutter POS app to authenticate API requests.<br />
                            Pass the token in the <code>Authorization: Bearer &lt;token&gt;</code> header on every request.
                        </p>

                        <table class="table table-hover" id="tokens-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Token</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tokens)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No tokens yet. Generate one to get started.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($tokens as $t): ?>
                                <tr id="row-<?php echo $t['id']; ?>">
                                    <td><?php echo htmlspecialchars($t['name'] ?: '—'); ?></td>
                                    <td>
                                        <code class="token-value" style="word-break:break-all;"><?php echo htmlspecialchars($t['token']); ?></code>
                                        <button class="btn btn-xs btn-default copy-btn" data-token="<?php echo htmlspecialchars($t['token']); ?>" title="Copy">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ((int)$t['active'] === 1): ?>
                                            <span class="label label-success">Active</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo _dt($t['created_at']); ?></td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-default toggle-btn" data-id="<?php echo $t['id']; ?>" data-active="<?php echo $t['active']; ?>">
                                            <?php echo (int)$t['active'] === 1 ? 'Deactivate' : 'Activate'; ?>
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
                <h4 class="modal-title">Generate New API Token</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Token Name <span class="text-muted">(optional)</span></label>
                    <input type="text" id="token-name" class="form-control" placeholder="e.g. Flutter POS App">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn-generate">Generate</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
$(function () {

    $('#btn-generate').on('click', function () {
        var name = $('#token-name').val();
        $.post('<?php echo admin_url('pos/ajax_generate_token'); ?>', { name: name }, function (resp) {
            if (resp.success) {
                $('#generateTokenModal').modal('hide');
                $('#token-name').val('');
                location.reload();
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
        var id  = $(this).data('id');
        $.post('<?php echo admin_url('pos/ajax_toggle_token/'); ?>' + id, function (resp) {
            if (resp.success) { location.reload(); }
        }, 'json');
    });

    $(document).on('click', '.delete-btn', function () {
        if (!confirm('Delete this token? Any app using it will stop working.')) return;
        var id = $(this).data('id');
        $.post('<?php echo admin_url('pos/ajax_delete_token/'); ?>' + id, function (resp) {
            if (resp.success) { $('#row-' + id).remove(); }
        }, 'json');
    });

});
</script>
</body>
</html>
