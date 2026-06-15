<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>

<style>
/* ── Receipt card ──────────────────────────────────────────────────────────── */
.gf-receipt-wrap {
    max-width: 520px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 28px 28px 20px;
    font-size: 13px;
}
.gf-receipt-header { text-align: center; margin-bottom: 18px; }
.gf-receipt-header .store-name { font-size: 17px; font-weight: 700; margin-bottom: 2px; }
.gf-receipt-header .store-addr { font-size: 11px; color: #777; }
.gf-receipt-brand {
    display: inline-block;
    background: #00b14f;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 2px 10px;
    border-radius: 12px;
    margin-bottom: 8px;
    letter-spacing: .4px;
}
.gf-collection-number {
    font-size: 38px;
    font-weight: 800;
    letter-spacing: 2px;
    color: #222;
    margin: 6px 0;
    line-height: 1;
}
.gf-collection-label {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #aaa;
}
.gf-divider { border: none; border-top: 1px dashed #ddd; margin: 14px 0; }
.gf-divider-solid { border: none; border-top: 1px solid #eee; margin: 14px 0; }
.gf-row { display: flex; justify-content: space-between; padding: 3px 0; }
.gf-row-label { color: #666; }
.gf-row-value { font-weight: 600; text-align: right; }
.gf-items-header { font-size: 11px; text-transform: uppercase; letter-spacing: .6px; color: #aaa; margin-bottom: 6px; }
.gf-item { padding: 5px 0; border-bottom: 1px solid #f0f0f0; }
.gf-item:last-child { border-bottom: none; }
.gf-item-name { font-weight: 600; }
.gf-item-mod { font-size: 11px; color: #888; margin-top: 2px; }
.gf-item-price { float: right; font-weight: 600; }
.gf-total-row { display: flex; justify-content: space-between; padding: 4px 0; }
.gf-grand-total { font-size: 18px; font-weight: 800; }
.gf-meta { font-size: 11px; color: #aaa; text-align: center; margin-top: 14px; line-height: 1.7; }
/* ── Status badge ─────────────────────────────────────────────────────────── */
.gf-badge { display:inline-block; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:700; }
.gf-badge-placed      { background:#fff3cd; color:#856404; }
.gf-badge-accepted    { background:#d4edda; color:#155724; }
.gf-badge-cancelled   { background:#f8d7da; color:#721c24; }
.gf-badge-delivered   { background:#cce5ff; color:#004085; }
.gf-badge-ready       { background:#d1ecf1; color:#0c5460; }
.gf-badge-driver      { background:#e2d9f3; color:#432874; }
.gf-badge-default     { background:#e9ecef; color:#495057; }
/* ── Info panel ────────────────────────────────────────────────────────────── */
.info-panel { background:#fff; border:1px solid #ddd; border-radius:4px; padding:16px; margin-bottom:16px; }
.info-panel h5 { font-weight:700; margin-top:0; margin-bottom:12px; font-size:13px; }
</style>

<div id="wrapper">
<div class="content">

    <div class="row">

        <!-- Left: receipt -->
        <div class="col-md-8">

            <!-- Breadcrumb -->
            <ol class="breadcrumb" style="margin-bottom:16px;padding:0;background:none;font-size:12px;">
                <li><a href="<?php echo admin_url('pos/dashboard'); ?>">POS</a></li>
                <li><a href="<?php echo admin_url('pos/grabfood_orders'); ?>">GrabFood Orders</a></li>
                <li class="active"><?php echo htmlspecialchars($order['order_short_ref'] ?: $order['grabfood_order_id']); ?></li>
            </ol>

            <div class="gf-receipt-wrap" id="receipt-print">

                <!-- Header -->
                <div class="gf-receipt-header">
                    <?php if (!empty($order['receipt_settings']['logo'])): ?>
                    <img src="<?php echo base_url(htmlspecialchars($order['receipt_settings']['logo'])); ?>"
                         style="max-height:60px;max-width:160px;object-fit:contain;margin-bottom:8px;" alt="Logo">
                    <br>
                    <?php endif; ?>

                    <span class="gf-receipt-brand">GrabFood</span>

                    <div class="store-name">
                        <?php echo htmlspecialchars($order['receipt_settings']['company_name'] ?? $order['warehouse_name']); ?>
                    </div>
                    <?php if (!empty($order['receipt_settings']['address'])): ?>
                    <div class="store-addr"><?php echo htmlspecialchars($order['receipt_settings']['address']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($order['receipt_settings']['phone'])): ?>
                    <div class="store-addr"><?php echo htmlspecialchars($order['receipt_settings']['phone']); ?></div>
                    <?php endif; ?>
                </div>

                <hr class="gf-divider">

                <!-- Collection Number (GrabFood order short ref) -->
                <div style="text-align:center; margin:10px 0 16px;">
                    <div class="gf-collection-label">Collection No.</div>
                    <div class="gf-collection-number">
                        <?php echo htmlspecialchars($order['order_short_ref'] ?: substr($order['grabfood_order_id'], 0, 8)); ?>
                    </div>
                    <div style="margin-top:6px;">
                        <?php echo gf_status_badge_detail($order['order_status']); ?>
                    </div>
                </div>

                <hr class="gf-divider">

                <!-- Order Meta -->
                <div class="gf-row">
                    <span class="gf-row-label">Order Date</span>
                    <span class="gf-row-value"><?php echo date('d M Y, H:i', strtotime($order['created_at'])); ?></span>
                </div>
                <div class="gf-row">
                    <span class="gf-row-label">Order Type</span>
                    <span class="gf-row-value">
                        <?php
                        $type_labels = [
                            'DELIVERY'    => 'Delivery',
                            'SELF_PICKUP' => 'Self Pickup',
                            'DINE_IN'     => 'Dine In',
                        ];
                        echo htmlspecialchars($type_labels[$order['order_type']] ?? ($order['order_type'] ?: '—'));
                        ?>
                    </span>
                </div>
                <?php if (!empty($order['customer_name'])): ?>
                <div class="gf-row">
                    <span class="gf-row-label">Customer</span>
                    <span class="gf-row-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['customer_phone'])): ?>
                <div class="gf-row">
                    <span class="gf-row-label">Phone</span>
                    <span class="gf-row-value"><?php echo htmlspecialchars($order['customer_phone']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['delivery_address'])): ?>
                <div class="gf-row">
                    <span class="gf-row-label">Delivery Address</span>
                    <span class="gf-row-value" style="max-width:60%;text-align:right;">
                        <?php echo nl2br(htmlspecialchars($order['delivery_address'])); ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['estimated_pickup_time'])): ?>
                <div class="gf-row">
                    <span class="gf-row-label">Est. Pickup</span>
                    <span class="gf-row-value"><?php echo date('H:i', strtotime($order['estimated_pickup_time'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['scheduled_at'])): ?>
                <div class="gf-row">
                    <span class="gf-row-label">Scheduled</span>
                    <span class="gf-row-value"><?php echo date('d M Y, H:i', strtotime($order['scheduled_at'])); ?></span>
                </div>
                <?php endif; ?>

                <hr class="gf-divider">

                <!-- Items -->
                <div class="gf-items-header">Items</div>
                <?php foreach ($order['items'] as $item): ?>
                <div class="gf-item">
                    <div>
                        <span class="gf-item-name"><?php echo htmlspecialchars($item['quantity'] . 'x ' . $item['item_name']); ?></span>
                        <span class="gf-item-price">RM <?php echo number_format($item['total_price'], 2); ?></span>
                    </div>
                    <?php if (!empty($item['modifiers'])): ?>
                    <div class="gf-item-mod">
                        <?php
                        $mod_parts = [];
                        foreach ($item['modifiers'] as $m) {
                            $mod_parts[] = htmlspecialchars($m['name']) . (($m['price'] ?? 0) > 0 ? ' (+RM ' . number_format($m['price'], 2) . ')' : '');
                        }
                        echo implode(', ', $mod_parts);
                        ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($item['notes'])): ?>
                    <div class="gf-item-mod"><em><?php echo htmlspecialchars($item['notes']); ?></em></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>

                <hr class="gf-divider-solid">

                <!-- Totals -->
                <div class="gf-total-row">
                    <span>Subtotal</span>
                    <span>RM <?php echo number_format($order['subtotal'], 2); ?></span>
                </div>
                <?php if ($order['discount'] > 0): ?>
                <div class="gf-total-row" style="color:#d9534f;">
                    <span>Discount</span>
                    <span>-RM <?php echo number_format($order['discount'], 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['delivery_fee'] > 0): ?>
                <div class="gf-total-row">
                    <span>Delivery Fee</span>
                    <span>RM <?php echo number_format($order['delivery_fee'], 2); ?></span>
                </div>
                <?php endif; ?>

                <hr class="gf-divider-solid">

                <div class="gf-total-row">
                    <span class="gf-grand-total">TOTAL</span>
                    <span class="gf-grand-total">RM <?php echo number_format($order['total'], 2); ?></span>
                </div>
                <div class="gf-total-row" style="color:#888;font-size:12px;">
                    <span>Payment</span>
                    <span>GrabFood</span>
                </div>

                <?php if (!empty($order['receipt_settings']['footer'])): ?>
                <hr class="gf-divider">
                <div style="text-align:center;font-size:12px;color:#777;">
                    <?php echo nl2br(htmlspecialchars($order['receipt_settings']['footer'])); ?>
                </div>
                <?php endif; ?>

                <!-- Footer meta -->
                <div class="gf-meta">
                    GrabFood Order ID: <?php echo htmlspecialchars(substr($order['grabfood_order_id'], 0, 32)); ?><br>
                    Printed: <?php echo date('d M Y H:i'); ?>
                </div>

            </div><!-- /gf-receipt-wrap -->

        </div><!-- /col-md-8 -->

        <!-- Right: Info only (actions handled by Flutter POS app) -->
        <div class="col-md-4">

            <div class="info-panel">
                <h5><i class="fa fa-info-circle"></i> Order Info</h5>
                <table class="table table-condensed" style="margin-bottom:0;font-size:12px;">
                    <tr><td class="text-muted">GF Order ID</td><td style="word-break:break-all;"><?php echo htmlspecialchars(substr($order['grabfood_order_id'], 0, 20)); ?></td></tr>
                    <tr><td class="text-muted">Store</td><td><?php echo htmlspecialchars($order['warehouse_name']); ?></td></tr>
                    <tr><td class="text-muted">Last Synced</td><td><?php echo $order['synced_at'] ? date('d M H:i', strtotime($order['synced_at'])) : '—'; ?></td></tr>
                </table>
                <a href="<?php echo admin_url('pos/grabfood_orders'); ?>" class="btn btn-default btn-sm btn-block" style="margin-top:8px;">
                    <i class="fa fa-arrow-left"></i> Back to Orders
                </a>
            </div>

            <div class="info-panel" style="font-size:12px;color:#888;">
                <i class="fa fa-mobile"></i> Receipt printing and order status updates are managed from the POS app.
            </div>

        </div><!-- /col-md-4 -->

    </div><!-- /row -->

</div>
</div>

<?php init_tail(); ?>
<?php
function gf_status_badge_detail($status) {
    $map = [
        'PLACED'           => ['class' => 'gf-badge-placed',    'label' => 'Placed'],
        'ACCEPTED'         => ['class' => 'gf-badge-accepted',  'label' => 'Accepted'],
        'CANCELLED'        => ['class' => 'gf-badge-cancelled', 'label' => 'Cancelled'],
        'DELIVERED'        => ['class' => 'gf-badge-delivered', 'label' => 'Delivered'],
        'READY_FOR_PICKUP' => ['class' => 'gf-badge-ready',     'label' => 'Ready for Pickup'],
        'DRIVER_ALLOCATED' => ['class' => 'gf-badge-driver',    'label' => 'Driver Allocated'],
        'FAILED'           => ['class' => 'gf-badge-cancelled', 'label' => 'Failed'],
    ];
    $s    = strtoupper($status);
    $info = $map[$s] ?? ['class' => 'gf-badge-default', 'label' => ucfirst(strtolower(str_replace('_', ' ', $s)))];
    return '<span class="gf-badge ' . $info['class'] . '">' . htmlspecialchars($info['label']) . '</span>';
}
?>
</body>
</html>
