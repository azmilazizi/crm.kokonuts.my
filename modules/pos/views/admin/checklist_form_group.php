<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="group-block" style="border:1px solid #e5e5e5;border-radius:4px;padding:12px;margin-bottom:12px;">
    <div class="row">
        <div class="col-md-1 text-center group-drag-handle" style="cursor:move;padding-top:8px;"><i class="fa fa-bars text-muted"></i></div>
        <div class="col-md-3"><input type="text" class="form-control group-name" placeholder="Group name, e.g. Mesh bag" value="<?php echo htmlspecialchars($group['name']); ?>"></div>
        <div class="col-md-3"><input type="text" class="form-control group-transport-role" placeholder="Transport role (optional)" value="<?php echo htmlspecialchars($group['transport_role'] ?? ''); ?>"></div>
        <div class="col-md-3"><input type="text" class="form-control group-onsite-role" placeholder="On-site role (optional)" value="<?php echo htmlspecialchars($group['onsite_role'] ?? ''); ?>"></div>
        <div class="col-md-2 text-right"><button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest('.group-block').remove()"><i class="fa fa-trash"></i> Remove group</button></div>
    </div>
    <div class="group-items mtop10">
        <?php foreach (($group['items'] ?? []) as $item) { ?>
        <div class="item-row row" style="margin-bottom:6px;">
            <div class="col-md-1 text-center item-drag-handle" style="cursor:move;padding-top:8px;"><i class="fa fa-bars text-muted"></i></div>
            <div class="col-md-4"><input type="text" class="form-control item-label" placeholder="Item label" value="<?php echo htmlspecialchars($item['label']); ?>"></div>
            <div class="col-md-6"><input type="text" class="form-control item-description" placeholder="Description (optional)" value="<?php echo htmlspecialchars($item['description'] ?? ''); ?>"></div>
            <div class="col-md-1" style="padding-top:6px;">
                <button type="button" class="btn btn-xs btn-link text-danger" onclick="$(this).closest('.item-row').remove()"><i class="fa fa-trash"></i></button>
            </div>
        </div>
        <?php } ?>
    </div>
    <button type="button" class="btn btn-link btn-xs" onclick="addGroupItem(this)"><i class="fa fa-plus-circle"></i> Add item to group</button>
</div>
