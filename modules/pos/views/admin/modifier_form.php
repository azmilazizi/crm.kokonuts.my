<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">

                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin-top"><?php echo $title; ?></h4>
                        <hr />

                        <input type="hidden" id="modifier-id" value="<?php echo $group ? $group['id'] : ''; ?>">

                        <!-- Modifier name -->
                        <div class="form-group">
                            <label>Modifier name <span class="text-danger">*</span></label>
                            <input type="text" id="modifier-name" class="form-control input-lg"
                                placeholder="e.g. Cup Size, Milk Type"
                                value="<?php echo $group ? htmlspecialchars($group['name']) : ''; ?>">
                        </div>

                        <!-- Selection rules -->
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Selection</label>
                                    <select id="selection-type" class="form-control">
                                        <option value="single" <?php echo ($group && $group['selection_type'] === 'single') ? 'selected' : ''; ?>>Single</option>
                                        <option value="multiple" <?php echo ($group && $group['selection_type'] === 'multiple') ? 'selected' : ''; ?>>Multiple</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Min selections</label>
                                    <input type="number" id="min-selections" class="form-control" min="0"
                                        value="<?php echo $group ? (int)$group['min_selections'] : 0; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Max selections</label>
                                    <input type="number" id="max-selections" class="form-control" min="1"
                                        value="<?php echo $group ? (int)$group['max_selections'] : 1; ?>">
                                </div>
                            </div>
                        </div>

                        <hr />

                        <!-- Options -->
                        <div class="row" style="margin-bottom:6px;">
                            <div class="col-md-7"><label class="text-muted small">Option name</label></div>
                            <div class="col-md-4"><label class="text-muted small">Price adjustment</label></div>
                            <div class="col-md-1"></div>
                        </div>

                        <div id="options-list">
                            <?php if ($group && !empty($group['modifiers'])) {
                                foreach ($group['modifiers'] as $opt) { ?>
                            <div class="option-row row" style="margin-bottom:6px;">
                                <div class="col-md-1 text-muted" style="padding-top:8px; cursor:grab;">
                                    <i class="fa fa-bars"></i>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control option-name" placeholder="Option name"
                                        value="<?php echo htmlspecialchars($opt['name']); ?>">
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-addon">RM</span>
                                        <input type="number" class="form-control option-price" step="0.01"
                                            placeholder="0.00" value="<?php echo number_format((float)$opt['price_adjustment'], 2); ?>">
                                    </div>
                                </div>
                                <div class="col-md-1" style="padding-top:6px;">
                                    <button type="button" class="btn btn-xs btn-link text-danger" onclick="removeOption(this)">
                                        <i class="fa fa-trash" style="font-size:16px;"></i>
                                    </button>
                                </div>
                            </div>
                            <?php } } ?>
                        </div>

                        <div class="mtop10">
                            <button type="button" class="btn btn-link" onclick="addOption()">
                                <i class="fa fa-plus-circle"></i> Add option
                            </button>
                        </div>

                    </div>
                </div>

                <!-- Actions -->
                <div class="row mtop10 mbottom20">
                    <?php if ($group) { ?>
                    <div class="col-md-3">
                        <button class="btn btn-danger btn-block" onclick="deleteModifier()">Delete</button>
                    </div>
                    <div class="col-md-9 text-right">
                    <?php } else { ?>
                    <div class="col-md-12 text-right">
                    <?php } ?>
                        <a href="<?php echo admin_url('pos/modifiers'); ?>" class="btn btn-default">Cancel</a>
                        &nbsp;
                        <button class="btn btn-info" onclick="saveModifier()">Save</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
var ADMIN_URL = '<?php echo admin_url(); ?>';

function addOption(name, price) {
    var row = $('<div class="option-row row" style="margin-bottom:6px;">' +
        '<div class="col-md-1 text-muted" style="padding-top:8px; cursor:grab;"><i class="fa fa-bars"></i></div>' +
        '<div class="col-md-6"><input type="text" class="form-control option-name" placeholder="Option name" value="' + (name || '') + '"></div>' +
        '<div class="col-md-4"><div class="input-group"><span class="input-group-addon">RM</span>' +
        '<input type="number" class="form-control option-price" step="0.01" placeholder="0.00" value="' + (price || '0.00') + '"></div></div>' +
        '<div class="col-md-1" style="padding-top:6px;"><button type="button" class="btn btn-xs btn-link text-danger" onclick="removeOption(this)">' +
        '<i class="fa fa-trash" style="font-size:16px;"></i></button></div>' +
        '</div>');
    $('#options-list').append(row);
    row.find('.option-name').focus();
}

function removeOption(btn) {
    $(btn).closest('.option-row').remove();
}

function saveModifier() {
    var name = $.trim($('#modifier-name').val());
    if (!name) {
        $('#modifier-name').focus();
        alert('Modifier name is required.');
        return;
    }

    var options = [];
    $('.option-row').each(function() {
        var optName = $.trim($(this).find('.option-name').val());
        if (optName) {
            options.push({
                name:             optName,
                price_adjustment: $(this).find('.option-price').val() || '0'
            });
        }
    });

    $.post(ADMIN_URL + 'pos/ajax_save_modifier_form', {
        id:              $('#modifier-id').val(),
        name:            name,
        selection_type:  $('#selection-type').val(),
        min_selections:  $('#min-selections').val(),
        max_selections:  $('#max-selections').val(),
        options:         options
    }, function(resp) {
        if (resp.success) {
            window.location.href = ADMIN_URL + 'pos/modifiers';
        } else {
            alert(resp.message || 'Failed to save.');
        }
    }, 'json');
}

function deleteModifier() {
    if (!confirm('Delete this modifier and all its options? This cannot be undone.')) return;
    $.post(ADMIN_URL + 'pos/ajax_delete_modifier_group/' + $('#modifier-id').val(), function(resp) {
        if (resp.success) {
            window.location.href = ADMIN_URL + 'pos/modifiers';
        }
    }, 'json');
}
</script>
<?php init_tail(); ?>
