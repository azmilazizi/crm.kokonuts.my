<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<h4>Receipt &amp; Invoice Scanning</h4>
<p class="text-muted">Used to scan receipt photos and digital invoices, extracting line items, prices, and quantities automatically.</p>
<?php echo render_input('settings[gemini_api_key]', 'Gemini API Key <small><a href="https://aistudio.google.com/apikey" target="_blank">Get a free key</a></small>', get_option('gemini_api_key'), 'password'); ?>
<hr />
