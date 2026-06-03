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
                            All endpoints are available under <code><?php echo site_url('pos/api/v1/'); ?></code> and require a Bearer token.
                        </p>
                        <a href="<?php echo admin_url('warehouse'); ?>" class="btn btn-default">
                            <i class="fa fa-building"></i> Manage Warehouses
                        </a>
                        <a href="<?php echo admin_url('pos/api_tokens'); ?>" class="btn btn-default">
                            <i class="fa fa-key"></i> Manage API Tokens
                        </a>
                        <h5>Available endpoints</h5>
                        <ul>
                            <li>POST <code>pos/api/v1/login</code> &mdash; <em>(public)</em></li>
                            <li>GET <code>pos/api/v1/stores</code> &mdash; returns warehouses</li>
                            <li>GET <code>pos/api/v1/items</code> &mdash; ?warehouse_id= for stock scoping</li>
                            <li>GET <code>pos/api/v1/items/barcode/{code}</code></li>
                            <li>GET <code>pos/api/v1/item_groups</code></li>
                            <li>GET/POST <code>pos/api/v1/bundles</code></li>
                            <li>GET <code>pos/api/v1/promotions</code> &mdash; POST <code>pos/api/v1/promotions/validate</code></li>
                            <li>POST <code>pos/api/v1/shifts/open</code> &mdash; GET <code>pos/api/v1/shifts/{id}</code> &mdash; POST <code>pos/api/v1/shifts/{id}/close</code></li>
                            <li>GET <code>pos/api/v1/customers/search</code> &mdash; POST <code>pos/api/v1/customers</code></li>
                            <li>POST <code>pos/api/v1/loyalty/earn</code> &mdash; POST <code>pos/api/v1/loyalty/redeem</code></li>
                            <li>GET/POST <code>pos/api/v1/loyalty/register</code> &mdash; <em>(public)</em></li>
                            <li>GET <code>pos/api/v1/taxes</code> &mdash; GET <code>pos/api/v1/payment_modes</code></li>
                            <li>POST <code>pos/api/v1/create_receipt</code> &mdash; POST <code>pos/api/v1/create_refund</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
