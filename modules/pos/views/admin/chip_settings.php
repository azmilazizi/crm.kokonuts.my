<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                            <div>
                                <h4 class="no-margin-top">Chip-in DuitNow QR Settings</h4>
                                <p class="text-muted" style="font-size:13px;margin:4px 0 0;">
                                    Configure Chip-in API credentials for DuitNow QR payments.
                                    <a href="https://docs.chip-in.asia/chip-collect/overview/introduction" target="_blank">View documentation <i class="fa fa-external-link"></i></a>
                                </p>
                            </div>
                            <a href="<?php echo admin_url('pos/settings/payment_modes'); ?>" class="btn btn-default">
                                <i class="fa fa-arrow-left"></i> Back to Payment Modes
                            </a>
                        </div>
                        <hr style="margin-top:0;">

                        <?php echo form_open(admin_url('pos/ajax_save_chip_settings'), ['id' => 'chip-settings-form']); ?>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="brand_id">Brand ID <span class="text-danger">*</span></label>
                                    <input type="text" id="brand_id" name="brand_id" class="form-control"
                                           placeholder="e.g. your_brand_id"
                                           value="<?php echo isset($settings['brand_id']) ? htmlspecialchars($settings['brand_id']) : ''; ?>" required>
                                    <small class="text-muted">Your Chip Brand ID from the Chip dashboard</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" name="test_mode" id="test_mode" value="1"
                                               <?php echo (!isset($settings['test_mode']) || (int)$settings['test_mode'] === 1) ? 'checked' : ''; ?>>
                                        Test Mode (Sandbox)
                                    </label>
                                    <p class="text-muted" style="font-size:12px;margin:4px 0 0;">Enable for testing with Chip sandbox environment</p>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="secret_key">Secret Key <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" id="secret_key" name="secret_key" class="form-control"
                                               placeholder="Enter your secret key"
                                               value="<?php echo isset($settings['secret_key']) ? htmlspecialchars($settings['secret_key']) : ''; ?>" required>
                                        <span class="input-group-btn">
                                            <button class="btn btn-default" type="button" onclick="toggleSecretKeyVisibility()">
                                                <i class="fa fa-eye" id="secret-key-icon"></i>
                                            </button>
                                        </span>
                                    </div>
                                    <small class="text-muted">Your Chip Secret Key (kept secure)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="public_key">Public Key <span class="text-danger">*</span></label>
                                    <input type="text" id="public_key" name="public_key" class="form-control"
                                           placeholder="Enter your public key"
                                           value="<?php echo isset($settings['public_key']) ? htmlspecialchars($settings['public_key']) : ''; ?>" required>
                                    <small class="text-muted">Your Chip Public Key</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="webhook_url">Webhook URL (Optional)</label>
                            <input type="text" id="webhook_url" name="webhook_url" class="form-control" readonly
                                   value="<?php echo site_url('pos/webhook/chip'); ?>">
                            <small class="text-muted">Configure this URL in your Chip dashboard to receive payment notifications</small>
                        </div>

                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="active" id="active" value="1"
                                       <?php echo (!isset($settings['active']) || (int)$settings['active'] === 1) ? 'checked' : ''; ?>>
                                Enable DuitNow QR payments
                            </label>
                        </div>

                        <hr>

                        <div class="text-right">
                            <button type="submit" class="btn btn-primary" id="btn-save-chip">
                                <i class="fa fa-save"></i> Save Settings
                            </button>
                            <?php if (!empty($settings)): ?>
                            <button type="button" class="btn btn-info" onclick="testChipConnection()" id="btn-test-chip">
                                <i class="fa fa-plug"></i> Test Connection
                            </button>
                            <?php endif; ?>
                        </div>

                        <?php echo form_close(); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function toggleSecretKeyVisibility() {
    var input = document.getElementById('secret_key');
    var icon = document.getElementById('secret-key-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

$('#chip-settings-form').on('submit', function(e) {
    e.preventDefault();
    var btn = $('#btn-save-chip');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

    $.post($(this).attr('action'), $(this).serialize(), function(resp) {
        if (resp.success) {
            alert_float('success', 'Settings saved successfully');
            btn.html('<i class="fa fa-check"></i> Saved');
            setTimeout(function() {
                btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Settings');
            }, 2000);
        } else {
            alert_float('danger', resp.message || 'Failed to save settings');
            btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Settings');
        }
    }, 'json').fail(function() {
        alert_float('danger', 'Request failed. Please try again.');
        btn.prop('disabled', false).html('<i class="fa fa-save"></i> Save Settings');
    });
});

function testChipConnection() {
    var btn = $('#btn-test-chip');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');

    $.post(ADMIN_URL + 'pos/ajax_test_chip_connection', {}, function(resp) {
        btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test Connection');
        if (resp.success) {
            alert_float('success', 'Connection successful! Chip API is working correctly.');
        } else {
            alert_float('danger', 'Connection failed: ' + (resp.message || 'Unknown error'));
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test Connection');
        alert_float('danger', 'Request failed. Please check your settings.');
    });
}
</script>
</body>
</html>
