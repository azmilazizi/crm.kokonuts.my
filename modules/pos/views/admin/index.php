<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin-top"><?php echo $title; ?></h4>
                        <hr />
                        <p class="text-muted">
                            This module exposes a REST API consumed by the Flutter POS app.<br />
                            All endpoints are available under <code><?php echo site_url('pos/api/v1/'); ?></code> and require a Bearer token.<br />
                            <a href="<?php echo admin_url('pos/api_tokens'); ?>"><i class="fa fa-key"></i> Manage API Tokens</a>
                        </p>
                        <h5>Available endpoints</h5>
                        <ul>
                            <li>GET <code>pos/api/stores</code></li>
                            <li>GET <code>pos/api/items</code></li>
                            <li>GET <code>pos/api/items/barcode/{code}</code></li>
                            <li>GET <code>pos/api/item_groups</code></li>
                            <li>GET/POST <code>pos/api/bundles</code></li>
                            <li>GET <code>pos/api/promotions</code> &mdash; POST <code>pos/api/promotions/validate</code></li>
                            <li>POST <code>pos/api/shifts/open</code> &mdash; GET <code>pos/api/shifts/{id}</code> &mdash; POST <code>pos/api/shifts/{id}/close</code></li>
                            <li>GET <code>pos/api/customers/search</code> &mdash; POST <code>pos/api/customers</code></li>
                            <li>POST <code>pos/api/loyalty/earn</code> &mdash; POST <code>pos/api/loyalty/redeem</code></li>
                            <li>GET/POST <code>pos/api/loyalty/register</code> &mdash; <em>(public, no token required)</em></li>
                            <li>GET <code>pos/api/taxes</code> &mdash; GET <code>pos/api/payment_modes</code></li>
                            <li>POST <code>pos/api/create_receipt</code> &mdash; POST <code>pos/api/create_refund</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
