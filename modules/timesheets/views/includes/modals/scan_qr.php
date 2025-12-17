<div class="modal" id="scan_qr_code_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <h4>
                <?php echo get_staff_full_name(); ?>
             </h4>
           </div>
           <div class="modal-body"></div>
            <div class="modal-footer">
              <button type="" class="btn btn-default pull-right mleft5" data-dismiss="modal"><?php echo _l('close'); ?></button>
              <button type="" class="btn btn-primary pull-right scan_qr_code_continue_btn hide" onclick="scan_continue()" ><?php echo _l('ts_continue'); ?></button>
            </div>
          </div>
        </div>
      </div>
