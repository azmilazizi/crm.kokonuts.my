<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<h4>Receipt &amp; Invoice Scanning</h4>
<p class="text-muted">Used to scan receipt photos and digital invoices, extracting line items, prices, and quantities automatically.</p>
<?php echo render_input('settings[gemini_api_key]', 'Gemini API Key <small><a href="https://aistudio.google.com/apikey" target="_blank">Get a free key</a></small>', get_option('gemini_api_key'), 'password'); ?>
<hr />
<?php $mcp_token = get_option('mcp_access_token'); ?>
<h4>ChatGPT &amp; Claude Connector (MCP)</h4>
<p class="text-muted">
    Lets ChatGPT and Claude read this CRM's data — sales, quotations,
    invoices, inventory, purchasing, cost &amp; profit — read-only. Anyone holding this link can read that data, so treat it like a password.
    This shared link grants broad read access; it does not use individual staff permissions.
    Generating a new link invalidates the old one after you save settings.
</p>
<ol>
    <li>Generate a link below, then click <b>Save Settings</b>.</li>
    <li>In ChatGPT on the web, open <b>Plugins &rarr; + &rarr; Add custom MCP server</b>.</li>
    <li>Name it <b>Kokonuts CRM</b>, paste the Connector URL and select <b>No authentication</b>.
        The secret in the URL protects access. Use the full HTTPS URL.</li>
    <li>Create and install the plugin, then select it using <b>@</b> in a conversation.</li>
</ol>
<p class="text-muted">Try: “Use Kokonuts CRM to show sales and profit for this month.”
    For Claude, add the same URL under Settings &rarr; Connectors &rarr; Add custom connector.</p>
<p><a href="https://developers.openai.com/api/docs/guides/custom-mcp-server" target="_blank" rel="noopener noreferrer">ChatGPT connection instructions</a></p>
<input type="hidden" name="settings[mcp_access_token]" id="mcp_access_token" value="<?php echo html_escape($mcp_token); ?>">
<div class="form-group">
    <label for="mcp_connector_url">Connector URL</label>
    <div class="input-group">
        <input type="text" class="form-control" id="mcp_connector_url" readonly
               data-base="<?php echo html_escape(site_url('mcp/')); ?>"
               value="<?php echo $mcp_token ? html_escape(site_url('mcp/' . $mcp_token)) : ''; ?>"
               placeholder="Disabled — click Generate link">
        <span class="input-group-btn">
            <button type="button" class="btn btn-default" id="mcp_copy_url">Copy</button>
        </span>
    </div>
</div>
<button type="button" class="btn btn-info" id="mcp_generate_token">Generate new link</button>
<button type="button" class="btn btn-default" id="mcp_disable_token">Disable</button>
<p class="text-warning mtop10" id="mcp_unsaved" style="display:none;">Click <b>Save Settings</b> to apply.</p>
<script>
(function () {
    var tokenInput = document.getElementById('mcp_access_token');
    var urlInput   = document.getElementById('mcp_connector_url');
    var unsaved    = document.getElementById('mcp_unsaved');

    function setToken(token) {
        tokenInput.value = token;
        urlInput.value   = token ? urlInput.getAttribute('data-base') + token : '';
        unsaved.style.display = '';
    }

    document.getElementById('mcp_generate_token').addEventListener('click', function () {
        if (tokenInput.value && !confirm('Generate a new link? The current one will stop working once you save.')) {
            return;
        }
        var bytes = new Uint8Array(32);
        window.crypto.getRandomValues(bytes);
        setToken(Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''));
    });

    document.getElementById('mcp_disable_token').addEventListener('click', function () {
        setToken('');
    });

    document.getElementById('mcp_copy_url').addEventListener('click', function () {
        if (!urlInput.value) { return; }
        urlInput.select();
        document.execCommand('copy');
    });
})();
</script>
