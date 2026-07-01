<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<div id="wrapper">
<div class="content">

    <div class="row" style="margin-bottom:16px;">
        <div class="col-sm-8">
            <h4 class="no-margin-top" style="margin-bottom:4px;">SMS Settings</h4>
            <ol class="breadcrumb" style="margin:0;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('loyalty/dashboard'); ?>">Loyalty</a></li>
                <li class="active">SMS Settings</li>
            </ol>
        </div>
    </div>

    <?php echo $this->session->flashdata('alert') ? '' : ''; ?>
    <?php echo render_alert(); ?>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-mobile"></i> Twilio SMS Configuration</h3>
                </div>
                <div class="panel-body">
                    <p class="text-muted" style="font-size:13px;">
                        Enter your <a href="https://www.twilio.com/console" target="_blank">Twilio Console</a> credentials.
                        The <strong>From Number</strong> must be a Twilio phone number in E.164 format (e.g. <code>+14155552671</code>).
                    </p>

                    <?php echo form_open(admin_url('loyalty/sms_settings')); ?>

                    <div class="form-group">
                        <label>Account SID <span class="text-danger">*</span></label>
                        <input type="text" name="twilio_account_sid" class="form-control"
                            value="<?php echo htmlspecialchars($twilio_account_sid); ?>"
                            placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    </div>

                    <div class="form-group">
                        <label>Auth Token <span class="text-danger">*</span></label>
                        <input type="password" name="twilio_auth_token" class="form-control"
                            value="<?php echo htmlspecialchars($twilio_auth_token); ?>"
                            placeholder="Your Twilio Auth Token">
                        <p class="help-block" style="font-size:12px;">Stored securely in your database settings.</p>
                    </div>

                    <div class="form-group">
                        <label>From Number <span class="text-danger">*</span></label>
                        <input type="text" name="twilio_from_number" class="form-control"
                            value="<?php echo htmlspecialchars($twilio_from_number); ?>"
                            placeholder="+14155552671">
                        <p class="help-block" style="font-size:12px;">Must be a Twilio number in E.164 format.</p>
                    </div>

                    <?php if (!empty($twilio_account_sid) && !empty($twilio_auth_token) && !empty($twilio_from_number)): ?>
                    <div class="alert alert-success" style="font-size:13px;">
                        <i class="fa fa-check-circle"></i> Twilio is configured. SMS blast is enabled on the Notifications page.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning" style="font-size:13px;">
                        <i class="fa fa-exclamation-triangle"></i> Twilio is not fully configured. Fill all three fields to enable SMS blasts.
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save Settings
                    </button>

                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-info-circle"></i> How to get your credentials</h3>
                </div>
                <div class="panel-body" style="font-size:13px;">
                    <ol style="padding-left:18px;line-height:1.9;">
                        <li>Sign up or log in at <strong>twilio.com</strong></li>
                        <li>Go to <strong>Console &rarr; Account Info</strong></li>
                        <li>Copy your <strong>Account SID</strong> and <strong>Auth Token</strong></li>
                        <li>Under <strong>Phone Numbers &rarr; Manage &rarr; Active Numbers</strong>, copy your Twilio number</li>
                        <li>Paste all three values above and click Save</li>
                    </ol>
                    <hr style="margin:12px 0;">
                    <p><strong>Pricing reminder:</strong><br>
                    Twilio charges per SMS sent. Malaysian numbers (+60) cost approximately
                    <strong>USD $0.04–$0.05 per message</strong>. A blast to 500 members ≈ RM 90–100.</p>
                </div>
            </div>
        </div>
    </div>

</div>
</div>

<?php init_tail(); ?>
