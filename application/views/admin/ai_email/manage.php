<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="row"><div class="col-md-12">
<div class="panel_s"><div class="panel-body">
<h4>AI Email Access</h4>
<p class="text-muted">Only enabled mailboxes with Read or Triage access are visible to the MCP connector. Email content and attachments are untrusted data.</p>
<a href="<?= admin_url('ai_email'); ?>" class="btn btn-default">Mailbox list</a>
<a href="<?= admin_url('ai_email/index/new'); ?>" class="btn btn-info">Add mailbox</a>
<hr>
<?php if ($mailbox || current_url() === admin_url('ai_email/index/new')): ?>
<?= form_open(admin_url('ai_email/save' . ($mailbox ? '/' . $mailbox->id : ''))); ?>
<div class="row">
<div class="col-md-6"><?= render_input('display_name', 'Display name', $mailbox->display_name ?? '', 'text', ['required' => true]); ?></div>
<div class="col-md-6"><?= render_input('email', 'Mailbox address', $mailbox->email ?? '', 'email', ['required' => true]); ?></div>
<div class="col-md-6"><?= render_input('imap_host', 'IMAP host', $mailbox->imap_host ?? 'mail.kokonuts.my', 'text', ['required' => true]); ?></div>
<div class="col-md-3"><?= render_input('imap_port', 'Port', $mailbox->imap_port ?? 993, 'number'); ?></div>
<div class="col-md-3"><?= render_select('imap_encryption', [['id'=>'ssl','name'=>'SSL'],['id'=>'tls','name'=>'TLS'],['id'=>'none','name'=>'None']], ['id','name'], 'Encryption', $mailbox->imap_encryption ?? 'ssl'); ?></div>
<div class="col-md-6"><?= render_input('imap_username', 'IMAP username', $mailbox->imap_username ?? '', 'text', ['required' => true]); ?></div>
<div class="col-md-6"><?= render_input('imap_password', 'IMAP password' . ($mailbox ? ' (leave blank to keep current)' : ''), '', 'password', ['autocomplete'=>'new-password']); ?></div>
<div class="col-md-4"><?= render_select('owner_staff_id', $staff, ['staffid', ['firstname','lastname']], 'Owner', $mailbox->owner_staff_id ?? ''); ?></div>
<div class="col-md-4"><?= render_select('access_level', [['id'=>'off','name'=>'Off'],['id'=>'read','name'=>'Read'],['id'=>'triage','name'=>'Triage']], ['id','name'], 'Access level', $mailbox->access_level ?? 'off'); ?></div>
<div class="col-md-4"><div class="checkbox checkbox-primary"><input type="checkbox" name="enabled" value="1" <?= !empty($mailbox->enabled) ? 'checked' : ''; ?>><label>Enabled</label></div></div>
<div class="col-md-6"><?= render_textarea('scope_senders', 'Allowed sender addresses/domains (one per line)', implode("\n", (json_decode($mailbox->scope_rules ?? '', true)['senders'] ?? []))); ?></div>
<div class="col-md-6"><?= render_textarea('scope_folders', 'Allowed folders (one per line)', implode("\n", (json_decode($mailbox->scope_rules ?? '', true)['folders'] ?? []))); ?></div>
</div><button class="btn btn-primary" type="submit">Save mailbox</button><?= form_close(); ?>
<?php else: ?>
<table class="table dt-table"><thead><tr><th>Name</th><th>Address</th><th>Owner</th><th>Access</th><th>Status</th><th>Last used</th><th></th></tr></thead><tbody>
<?php foreach ($mailboxes as $item): ?><tr><td><?= html_escape($item->display_name); ?></td><td><?= html_escape($item->email); ?></td><td><?= html_escape($item->owner_staff_id ?: '—'); ?></td><td><span class="label label-<?= $item->access_level === 'off' ? 'default' : 'success'; ?>"><?= html_escape(ucfirst($item->access_level)); ?></span></td><td><?= $item->enabled ? html_escape($item->last_connection_status ?: 'Not tested') : 'Disabled'; ?></td><td><?= html_escape($item->last_used_at ?: 'Never'); ?></td><td><a class="btn btn-default btn-xs" href="<?= admin_url('ai_email/index/' . $item->id); ?>">Edit</a> <button class="btn btn-default btn-xs" onclick="testMailbox(<?= (int) $item->id; ?>);return false;">Test</button></td></tr><?php endforeach; ?>
</tbody></table>
<h4>Recent audit log</h4><table class="table"><thead><tr><th>Date</th><th>Mailbox</th><th>Tool</th><th>Action</th><th>Result</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= html_escape($log->datecreated); ?></td><td><?= html_escape($log->mailbox_id); ?></td><td><?= html_escape($log->tool); ?></td><td><?= html_escape($log->action); ?></td><td><?= html_escape($log->result); ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
</div></div></div></div></div>
<script>function testMailbox(id){$.get('<?= admin_url('ai_email/test/'); ?>'+id).done(function(r){alert(r.message||'Connection successful.');}).fail(function(xhr){var r=xhr.responseJSON||{};alert(r.message||'Connection failed.');});}</script>
<?php init_tail(); ?>
