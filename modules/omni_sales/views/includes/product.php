<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php 
  $group_product_id = '';
  $product_id = '';
?>
<div class="col-md-12"> 
<a href="#" onclick="add_product(); return false;" class="btn btn-info pull-left">
    <?php echo _l('add'); ?>
</a>
<input type="hidden" name="id" value="<?php echo html_entity_decode($id); ?>">


<a href="Javascript:void(0);" id="toggle_popup_woo" class="btn btn-success display-block pull-right"><i class="fa fa-download"></i><?php echo ' '._l('download').' '; ?><i class="fa fa-caret-down"></i>
</a>
<h2 class="pull-right m-0">|</h2>
<a href="Javascript:void(0);" id="toggle_popup_crm" class="btn btn-info display-block pull-right"><i class="fa fa-refresh"></i><?php echo ' '._l('sync_to').' '; ?><i class="fa fa-caret-down"></i>
</a>
<ul id="popup_approval" class="dropdown-menu dropdown-menu-right">
  <li>
    <br>
    <div class="col-md-12">

    <a href="#" onclick="sync_all(this); return false;" data-id="<?php echo html_entity_decode($id); ?>" data-toggle="tooltip" data-original-title="<?php echo _l("sync_all") ?>" class="btn btn-info pull-right display-block mright5"><i class="fa fa fa-refresh  " data-toggle="dropdown" aria-expanded="false"></i><?php echo ' '._l('sync_all'); ?></a>

    <a href="#" onclick="sync_decriptions_synchronization(this); return false;" data-id="<?php echo html_entity_decode($id); ?>" class="btn btn-info pull-right display-block mright5" data-toggle="tooltip" data-placeme="top" data-original-title="<?php echo _l("sync_decriptions") ?>" ><i class="fa fa-info" data-toggle="dropdown" aria-expanded="false"></i><?php echo ' '._l('long_decriptions'); ?>
    </a>
    <a href="#" onclick="sync_images_synchronization(this); return false;" data-id="<?php echo html_entity_decode($id); ?>" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo _l("sync_images") ?>"  class="btn btn-info pull-right display-block mright5"><i class="fa fa-picture-o" data-toggle="dropdown" aria-expanded="false"></i><?php echo ' '._l('images'); ?></a>

    <a href="#" onclick="sync_price(this); return false;" data-id="<?php echo html_entity_decode($id); ?>" data-toggle="tooltip" data-original-title="<?php echo _l("sync_price") ?>" class="btn btn-info pull-right display-block mright5"><i class="fa fa fa-money" data-toggle="dropdown" aria-expanded="false"></i><?php echo ' '._l('sync_price'); ?></a>

    <a href="#" onclick="sync_inventory_synchronization(this); return false;" data-id="<?php echo html_entity_decode($id); ?>" data-toggle="tooltip" data-original-title="<?php echo _l("sync_from_store") ?>" class="btn btn-info pull-right display-block mright5"><i class="fa fa-snowflake-o" data-toggle="dropdown" aria-expanded="false"></i><?php echo ' '._l('inventory_sync'); ?></a>

    <a href="#" data-id="<?php echo html_entity_decode($id); ?>" class="btn btn-info pull-right display-block mright5 link sync_products_woo" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo _l("sync_products_to_store") ?>"><i class="fa fa-forward"></i><?php echo ' '._l('product_no_images'); ?></a>
    </div>
    <br>&nbsp;<br/>
  </li>
 </ul>

 <ul id="popup_woo" class="dropdown-menu dropdown-menu-right">
  <li>
    <br>
    <div class="col-md-12">

    <a href="#" onclick="sync_store(this); return false;" data-id="<?php echo html_entity_decode($id); ?>" class="btn btn-success pull-right display-block mright5 orders-woo" data-toggle="tooltip" data-placeme="top" data-original-title="<?php echo _l("sync_from_the_system_to_the_store") ?>"><i class="fa fa-first-order" data-toggle="dropdown" aria-expanded="false"></i><?php echo ' '._l('order'); ?>
    </a>
   
    <a href="#" data-id="<?php echo html_entity_decode($id); ?>" data-popup-open="popup-1"  class="btn btn-success pull-right display-block mright5" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo _l("sync_products_from_store") ?>"><i class="fa fa-forward"></i><?php echo ' '._l('product'); ?></a>
    </div>
    <br>&nbsp;<br/>
  </li>
 </ul>


<div class="clearfix"></div><br>
</div>
<div id="box-loadding">
</div>
<div class="col-md-12">
  <div class="modal bulk_actions" id="product-woocommerce_bulk_actions" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document">
         <div class="modal-content">
            <div class="modal-header">
               <h4 class="modal-title"><?php echo _l('bulk_actions'); ?></h4>
               <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
               <?php if(has_permission('warehouse','','delete') || is_admin()){ ?>
               <div class="checkbox checkbox-danger">
                  <input type="checkbox" name="mass_delete" id="mass_delete">
                  <label for="mass_delete"><?php echo _l('mass_delete'); ?></label>
               </div>
              
               <?php } ?>
            </div>
            <div class="modal-footer">
               <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>

               <?php if(has_permission('warehouse','','delete') || is_admin()){ ?>
               <a href="#" class="btn btn-info" onclick="omi_sales_delete_bulk_action(this); return false;"><?php echo _l('confirm'); ?></a>
                <?php } ?>
            </div>
         </div>
        
      </div>
      
   </div>

  <a href="#" onclick="staff_bulk_actions(); return false;"  data-toggle="modal" data-table=".table-product-woocommerce" data-target="#leads_bulk_actions" class=" hide bulk-actions-btn table-btn"><?php echo _l('bulk_actions'); ?></a>                   
  <?php
  $table_data = array(
     '<input type="checkbox" id="mass_select_all" data-to-table="product-woocommerce">',
      _l('product_code'),
      _l('product_name'),
      _l('price'),
      _l('price_on_store'),
      _l('options'),
      );

    render_datatable($table_data,'product-woocommerce',
      array('customizable-table'),
      array(
        'proposal_sm' => 'proposal_sm',
         'id'=>'table-product-woocommerce',
         'data-last-order-identifier'=>'product-woocommerce',
         'data-default-order'=>get_table_last_order('product-woocommerce'),
       )); ?>

</div>
<div class="col-md-12">
  <a href="<?php echo admin_url('omni_sales/omni_sales_channel'); ?>" class="btn btn-danger"><?php echo _l('close'); ?></a>
</div>
<?php echo form_hidden('check'); ?>
<?php echo form_hidden('check_product'); ?>
<div class="modal fade" id="chose_product" tabindex="-1" role="dialog">
<div class="modal-dialog">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">
                <span class="add-title"><?php echo _l('add_product'); ?></span>
                <span class="update-title hide"><?php echo _l('update_product'); ?></span>
            </h4>
        </div>
    <?php echo form_open(admin_url('omni_sales/add_product_channel_wcm'),array('id'=>'form_add_product')); ?>             
        <div class="modal-body">
           <div class="row">
            <input type="hidden" name="woocommere_store_id" value="<?php echo html_entity_decode($id); ?>">
            <div class="col-md-12">
              <?php 
                      echo render_select('group_product_id',$group_product,array('id',array('commodity_group_code','name')),'group_product',$group_product_id,array('onchange'=>'get_list_product(this);'));
                    ?>
            </div>

            <div class="col-md-12">
               <div class="form-group" app-field-wrapper="product_id">
                <label for="product_id" class="control-label"><?php echo _l('product'); ?></label>
                  <select id="product_id" name="product_id[]" class="selectpicker" multiple  data-width="100%" data-none-selected-text="Nothing selected" data-live-search="true" data-actions-box="true" tabindex="-98">
                    <option value=""></option>
                    <?php foreach ($products as $key => $value){ ?>
                      <option value="<?php echo html_entity_decode($value['id']); ?>"><?php echo html_entity_decode($value['description']); ?></option>
                    <?php } ?>
                  </select>
             </div>
            </div>
              <div class="col-md-12 pricefr hide">
              <?php 
                $arrAtt = array();
                    $arrAtt['data-type']='currency';
                    echo render_input('prices','prices','','text',$arrAtt);
              ?>
            </div>
          </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo _l('close'); ?></button>
            <button type="submit" class="btn btn-info"><?php echo _l('submit'); ?></button>
        </div>
        <?php echo form_close(); ?>                 
      </div>
    </div>
</div>

 
<div class="popup" data-popup="popup-1">
    <div class="popup-inner">
       <div class="popup-scroll">
        <div class="col-md-12">
          <button class="btn btn-success mx-3 sync_products_from_info_woo cus_btn" data-id="<?php echo html_entity_decode($id); ?>" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo _l("synchronize_product_information_basic") ?>"><i class="fa fa-refresh" aria-hidden="true" data-toggle="dropdown" aria-expanded="false"></i>  <?php echo _l('synchronize_product_information_basic'); ?></button>
        </div>
        <br>
        <br>
        <br>
        <div class="col-md-12 w-sync">
          <button class="btn btn-primary mx-3 sync_products_from_woo cus_btn" data-id="<?php echo html_entity_decode($id); ?>" data-toggle="tooltip" data-placement="top" data-original-title="<?php echo _l("synchronize_product_information_full") ?>"><i class="fa fa-refresh" aria-hidden="true" data-toggle="dropdown" aria-expanded="false"></i> <?php echo _l('synchronize_product_information_full'); ?></button>
          <a href="#" data-toggle="tooltip" data-original-title="<?php echo _l("warning_may_take_longer") ?>" class="btn btn-danger pull-right btn-icon">
            <i class="fa fa-question-circle" aria-hidden="true" data-toggle="dropdown" aria-expanded="false"></i>
          </a>
        </div>
        <a class="popup-close" data-popup-close="popup-1" href="#">x</a>
    </div>
</div>