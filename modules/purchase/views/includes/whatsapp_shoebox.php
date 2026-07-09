<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<?php echo form_open(admin_url('purchase/whatsapp_shoebox_setting'), ['autocomplete' => 'off']); ?>

<div class="row">
    <div class="col-md-12">
        <h5 class="no-margin font-bold h5-color">
            WhatsApp Shoebox
        </h5>
        <p class="text-muted" style="margin-top:6px;font-size:13px;">
            Staff send receipt photos to the WhatsApp test number. Gemini scans them and saves a Purchase Draft automatically.
        </p>
        <hr class="hr-color">
    </div>
</div>

<div class="row">
    <div class="col-md-8">

        <div class="form-group">
            <label>Webhook URL <small class="text-muted">(paste this into Meta &rarr; WhatsApp &rarr; Configuration &rarr; Webhook)</small></label>
            <div class="input-group">
                <input type="text" class="form-control" readonly
                       value="<?php echo base_url('purchase/whatsapp_webhook'); ?>"
                       onclick="this.select();">
                <span class="input-group-btn">
                    <button type="button" class="btn btn-default"
                            onclick="var el=this.previousElementSibling;el.select();document.execCommand('copy');this.textContent='Copied!';">
                        Copy
                    </button>
                </span>
            </div>
        </div>

        <div class="form-group">
            <label>Webhook Verify Token <small class="text-muted">(you choose this — must match what you enter in Meta)</small></label>
            <input type="text" name="wa_verify_token" class="form-control"
                   placeholder="e.g. kokonuts2024"
                   value="<?php echo htmlspecialchars($wa_verify_token ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>Access Token <small class="text-muted">(from Meta &rarr; WhatsApp &rarr; API Setup)</small></label>
            <input type="password" name="wa_access_token" class="form-control"
                   placeholder="EAAO&hellip;"
                   value="<?php echo htmlspecialchars($wa_access_token ?? ''); ?>">
            <p class="help-block">Expires every 24 hours in test mode. Refresh here when it expires.</p>
        </div>

        <div class="form-group">
            <label>Phone Number ID <small class="text-muted">(from Meta &rarr; WhatsApp &rarr; API Setup)</small></label>
            <input type="text" name="wa_phone_number_id" class="form-control"
                   placeholder="1261645&hellip;"
                   value="<?php echo htmlspecialchars($wa_phone_id ?? ''); ?>">
        </div>

        <div class="form-group">
            <label>App Secret <small class="text-muted">(from Meta &rarr; App Settings &rarr; Basic)</small></label>
            <input type="password" name="wa_app_secret" class="form-control"
                   placeholder="App secret&hellip;"
                   value="<?php echo htmlspecialchars($wa_app_secret ?? ''); ?>">
            <p class="help-block">Used to verify that incoming webhook requests are genuinely from Meta.</p>
        </div>

        <div class="form-group">
            <label>Gemini API Key <small class="text-muted">(from <a href="https://aistudio.google.com/apikey" target="_blank">Google AI Studio</a>)</small></label>
            <input type="password" name="gemini_api_key" class="form-control"
                   placeholder="AIza&hellip;"
                   value="<?php echo htmlspecialchars($gemini_api_key ?? ''); ?>">
            <p class="help-block">Used to scan receipt images and extract vendor, items and totals.</p>
        </div>

    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </div>
</div>

<?php echo form_close(); ?>
