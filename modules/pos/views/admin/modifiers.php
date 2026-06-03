<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="no-margin-top"><?php echo $title; ?></h4>
                <hr />
                <?php if (empty($sets)) { ?>
                    <p class="text-muted">No modifier sets found.</p>
                <?php } ?>
                <?php foreach ($sets as $set) { ?>
                <div class="panel_s" style="margin-bottom: 16px;">
                    <div class="panel-body">
                        <h5 class="no-margin-top bold"><?php echo htmlspecialchars($set['name']); ?></h5>
                        <?php if (!empty($set['description'])) { ?>
                            <p class="text-muted small no-margin"><?php echo htmlspecialchars($set['description']); ?></p>
                        <?php } ?>
                        <table class="table table-bordered no-margin-top" style="margin-top:10px;">
                            <thead>
                                <tr>
                                    <th>Option</th>
                                    <th>Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($set['options'])) { ?>
                                <tr><td colspan="2" class="text-muted">No options.</td></tr>
                                <?php } ?>
                                <?php foreach ($set['options'] as $option) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($option['name']); ?></td>
                                    <td><?php echo number_format((float)$option['price'], 2); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
