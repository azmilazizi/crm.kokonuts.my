<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content"><div class="row"><div class="col-md-8">
<div class="panel_s"><div class="panel-body">
<h4><?= html_escape($title); ?></h4>
<p class="text-muted">These texts appear on the public affiliate page. You can change them without editing the Flutter app.</p>
<?= form_open(admin_url('affiliate/settings')); ?>
<?= render_input('title', 'Page heading', $public_title, 'text', ['required' => true, 'maxlength' => 120]); ?>
<?= render_textarea('description', 'Page description', $public_description, ['rows' => 3, 'maxlength' => 300]); ?>
<button class="btn btn-primary" type="submit">Save settings</button>
<?= form_close(); ?>
</div></div></div></div></div><?php init_tail(); ?>
