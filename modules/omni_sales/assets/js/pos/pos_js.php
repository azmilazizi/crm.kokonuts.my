 <script type="text/javascript">
  var group_id = 0;

  $('.item-group').click(function(){
    "use strict";
    group_id = $(this).data('group');
    $('input[name="id_group"]').val(group_id);
    $('input[name="index_page"]').val(1);
    get_list_group();
  });

  function replacequote(text) {
    "use strict";
    var newText = "";
    for (var i = 0; i < text.length; i++) {
      if (text[i] == "'") {
        newText += "\\'";
      }
      else
        newText += text[i];
    }
    return newText;
  };
  function create_invoice(el){
    "use strict";
    var list_id = $('.tab-pane.active').find('input[name="list_id_product"]').val();
    var list_qty = $('.tab-pane.active').find('input[name="list_qty_product"]').val();
    var list_price = $('.tab-pane.active').find('input[name="list_price_product"]').val();
    var list_price_discount = $('.tab-pane.active').find('input[name="list_price_discount_product"]').val();
    var list_percent_discount = $('.tab-pane.active').find('input[name="list_percent_discount_product"]').val();
    var voucher = $('.tab-pane.active').find('input[name="voucher"]').val();
    var discount_total = $('.tab-pane.active').find('input[name="discount_total"]').val();

    var subtotal = $('.tab-pane.active').find('input[name="sub_total_cart"]').val();
    var total = $('.tab-pane.active').find('input[name="total_cart"]').val();
    var tax = $('.tab-pane.active').find('input[name="tax"]').val();
    var create_invoice = $('input[name="create_invoice"]').is(":checked");
    var debit_order = $('input[name="debit_order"]').is(":checked");
    var stock_export = $('input[name="stock_export"]').is(":checked");
    var warehouse_id = $('select[name="warehouse_id"]').val();
    var customer = $('.tab-pane.active').find('select[name="client_id"]').val();

    var redeem_from = '';
    var redeem_to = '';
    if(typeof $('.tab-pane.active input[name="redeem_from"]').val() != 'undefined'){
      redeem_from = $('.tab-pane.active input[name="redeem_from"]').val();
    }

    if(typeof $('.tab-pane.active input[name="redeem_to"]').val() != 'undefined'){
      redeem_to = $('.tab-pane.active input[name="redeem_to"]').val();
    }


    if(warehouse_id == ''){
     warehouse_id = 0;
   }

   if(stock_export == true){
    stock_export = 'on';
  }
  else{
    stock_export = 'off';
  }
  if(create_invoice == true){
    create_invoice = 'on';
  }
  else{
    create_invoice = 'off';
  }



  var customers_pay = $('input[name="customers_pay"]').val().replace(new RegExp(',', 'g'),"");
  var amount_returned = $('.balance_s').text().replace(new RegExp(',', 'g'),"");
  var seller = $('input[name="seller"]').val();

  var note = $('textarea[name="note"]').val();
  var staff_note = $('textarea[name="staff_note"]').val();
  var payment_note = $('textarea[name="payment_note"]').val();
  var payment_methods = $('select[name="payment_methods"]').val();
  if(payment_methods == ''){
    $('.payment_methods_alert').removeClass('hide');
    return false;
  }
  else{
    $('.payment_methods_alert').addClass('hide');
  }
  var is_effect = true;
  if(debit_order == true){
    debit_order = 'on';
  }
  else{
    debit_order = 'off';
    if(customers_pay == ''){
      $('input[name="customers_pay"]').addClass('danger');
      is_effect = false;
    }
    else{
      $('input[name="customers_pay"]').removeClass('danger');
    }
  }
  if(is_effect == false){
    return false;
  }

  if(customer!='' && seller!=''){
    $.ajax({
     url: "create_invoice_pos/"+customer,
     type: "post",
     data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>','list_id_product':replacequote(list_id),'list_qty_product':replacequote(list_qty),'list_price_product':replacequote(list_price),'sub_total':replacequote(subtotal),'total':replacequote(total),'create_invoice':replacequote(create_invoice),'stock_export':replacequote(stock_export),'customers_pay':replacequote(customers_pay),'amount_returned':replacequote(amount_returned),'tax': replacequote(tax),'list_price_discount_product': replacequote(list_price_discount),'list_percent_discount_product': replacequote(list_percent_discount),'discount_total': replacequote(discount_total),'seller':replacequote(seller),'warehouse_id': replacequote(warehouse_id),'notes': replacequote(note),'staff_note': replacequote(staff_note),'payment_note': replacequote(payment_note),'voucher': replacequote(voucher), 'payment_methods': payment_methods, 'userid': customer, 'redeem_from': replacequote(redeem_from), 'redeem_to': replacequote(redeem_to), 'debit_order':replacequote(debit_order)},
     success: function(){
     },
     error:function(){
      $('#alert').modal('show').find('.alert_content').text('Failure');
      setTimeout(function(){ $('#alert').modal('hide'); },1500);
    }
  }).done(function(response) {
    response = JSON.parse(response);
    var html_success = '';           
    $('#payments').modal('hide');
    $('#alert').modal('show').find('.alert_content').text('Created successfull');
    setTimeout(function(){
     $('#alert').modal('hide');

     if(response.stock_export_number != ''){
      $('.tab-pane.active').find('.view-export-stock').attr('href','../warehouse/manage_delivery#'+response.stock_export_number+'');  
      $('.tab-pane.active').find('.view-export-stock').removeClass('hide');
    }
    if(response.number_invoice != ''){
      $('.tab-pane.active').find('.view-invoice').attr('href','../invoices#'+response.number_invoice+'');     
      $('.tab-pane.active').find('.view-invoice').removeClass('hide');         
    }
    if(response.html_bill != ''){
      html_success += '<iframe id="content_print" class="w100" name="content_print"></iframe>'
      $('.tab-pane.active').find('.content_cart').html(html_success);
      $('.tab-pane.active').find("#content_print").contents().find('body').html(response.html_bill);
      $('.tab-pane.active').find("#content_print").contents().find('body').attr('style','text-align: center');
      $(".tab-pane.active #content_print").get(0).contentWindow.print();
      $('.tab-pane.active').find('.title_pn').css({'height':'39px'});
      $('.tab-pane.active').find('.title_pn .fvoucher').remove();
      $('.tab-pane.active').find('.title_pn .line').remove();
      $('.tab-pane.active').find('.title_pn .fclientid').remove();
      $('.tab-pane.active').find('.content_cart').css({'margin-top':'24px'});
      $('.tab-pane.active').find('.print-bill').removeClass('hide');
    }
    if(typeof response.payment.paid == 'undefined'){
      $('.tab-pane.active').find('.payment_after').removeClass('hide');
      $('.tab-pane.active').find('.btn_payment_after').attr('data-invoice_id',response.payment.invoiceid);
      $('.tab-pane.active').find('.btn_payment_after').attr('data-note',response.payment.note);
      $('.tab-pane.active').find('.btn_payment_after').attr('data-order_number',response.payment.transactionid);
      $('.tab-pane.active').find('.btn_payment_after').attr('data-payment_methods',response.payment.paymentmode);
      $('.tab-pane.active').find('.btn_payment_after').attr('data-total_payment',response.payment.amount);
    }
  },1500);

    $('.tab-pane.active').find('.payment_s').addClass('hide');
    $('.tab-pane.active').find('.success_s').removeClass('hide');
    get_list_group();
  });  
}
else{
  $('#alert').modal('show').find('.alert_content').text('<?php echo _l('please_select_customers_and_sellers'); ?>');
  setTimeout(function(){ $('#alert').modal('hide'); },1500);
}
if(seller==''&&customer!=''){
  $('#alert').modal('show').find('.alert_content').text('<?php echo _l('please_select_a_seller'); ?>');
  setTimeout(function(){ $('#alert').modal('hide'); },1500);
}
if(seller!=''&&customer==''){
  $('#alert').modal('show').find('.alert_content').text('<?php echo _l('please_select_a_customer'); ?>');
  setTimeout(function(){ $('#alert').modal('hide'); },1500);
}
}
function search(el){
 "use strict";
 get_list_group();
}
function get_list_group(){
  "use strict";
  var group_id = $('input[name="id_group"]').val();
  $('.content_list').html(''); 
  var key = $('input[name="keyword"]').val();    
  var page = $('input[name="index_page"]').val();
  var warehouse_id = $('select[name="warehouse_id"]').val();
  if(warehouse_id == ''){
   warehouse_id = 0;
 }

 if(page!=''){
  $.ajax({
   url: "get_product_by_group_pos_channel/"+page+'/'+group_id+'/'+warehouse_id+'/'+key,
   type: "post",
   data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>'},
   success: function(){
   },
   error:function(){
    $('#alert').modal('show').find('.alert_content').text('Failure');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
  }
}).done(function(response) {
  response = JSON.parse(response);
  $('.content_list').html(response.data);

});  
}
}
function get_voucher(el){
  "use strict";
  var data = {};
  var voucher = $(el).val();
  var customer = $('.tab-pane.active').find('select[name="client_id"]').val();
  var subtotal = $(el).closest('.tab-pane.active').find('input[name="sub_total_cart"]').val();
  if(voucher!=''){
   $.ajax({
     url: "voucher_apply",
     type: "post",
     data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>', 'voucher':replacequote(voucher), 'client':customer, 'channel':1},
     success: function(){
     },
     error:function(){
      $('#alert').modal('show').find('.alert_content').text('Failure');
      setTimeout(function(){ $('#alert').modal('hide'); },1500);
      $(el).val('');
    }
  }).done(function(response) {
    response = JSON.parse(response);
    if(response[0] != null){
      var  test = 0;
      if(parseFloat(response[0].minimum_order_value)>0){
        test = 1;
        if(subtotal >= parseFloat(response[0].minimum_order_value)){
          test = 0;
        }
      }
      if(test == 0){
       $('.tab-pane.active').find('input[name="discount_type"]').val(response[0].formal);
       $('.tab-pane.active').find('input[name="discount_voucher"]').val(response[0].discount);                  
       total_cart(); 
       $('#alert').modal('show').find('.alert_content').text('Voucher applied');
       setTimeout(function(){ $('#alert').modal('hide'); },2000);                   
     }
     else{
      $('#alert').modal('show').find('.alert_content').text('Your order is not eligible for this code');
      setTimeout(function(){ $('#alert').modal('hide'); },1500);
      $(el).val('');
    }
  }else{
    $('.tab-pane.active').find('input[name="discount_voucher"]').val('0');
    $('.tab-pane.active').find('input[name="discount_type"]').val('');
    total_cart();
    $('#alert').modal('show').find('.alert_content').text('Voucher does not exist');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
    $(el).val('');
  }
}); 
}
else{
  $('.tab-pane.active').find('input[name="discount_voucher"]').val('0');
  var discount_auto = $('.tab-pane.active').find('input[name="discount_auto"]').val();
  if(discount_auto == 0){
    $('.tab-pane.active').find('input[name="discount_type"]').val('');
  }
  total_cart();
}   
}
var list_discount = [];
function get_trade_discount(el){
  "use strict";
  var id = $(el).val();
  $('.tab-pane.active').find('input[name="customer_id"]').val(id);
  if(id != ''){
   $.ajax({
     url: "get_trade_discount",
     type: "post",
     data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>', 'id':id},
     success: function(){
     },
     error:function(){
      $('#alert').modal('show').find('.alert_content').text('Failure');
      setTimeout(function(){ $('#alert').modal('hide'); },1500);
    }
  }).done(function(response) {
    response = JSON.parse(response);
    if(response[0].length >= 1){
      list_discount[id] = [];
      for(var i = 0; i < response[0].length;i++){
        list_discount[id].push({item:response[0][i]['items'], formal:response[0][i]['formal'],group_list:response[0][i]['group_items'], discount:response[0][i]['discount'], voucher:response[0][i]['voucher'], minimum_order_value:response[0][i]['minimum_order_value']});
      }
    }              
    total_cart(); 
  }); 
} 
else{
  $('.tab-pane.active').find('input[name="discount_auto"]').val('0');
  var discount_voucher = $('.tab-pane.active').find('input[name="discount_voucher"]').val();
  if(discount_voucher == 0){
    $('.tab-pane.active').find('input[name="discount_type"]').val('');
  }
  total_cart(); 
} 
}

function change_page(el){
  "use strict";
  $('.btn_page').removeClass('active');
  $(el).addClass('active');
  $('.product_list').html(''); 
  var page = $(el).data('page');
  $('input[name="index_page"]').val(page);
  get_list_group();
}

$('#customers-form').submit(function(event) {

  var name_element = $('input[name="company"]');
  var phonenumber_element = $('input[name="phonenumber"]');
  var address_element = $('textarea[name="address"]');
  var city_element = $('input[name="city"]');
  var state_element = $('input[name="state"]');
  var email_element = $('input[name="email"]');

  var billing_street_element = $('textarea[name="billing_street"]');
  var billing_city_element = $('input[name="billing_city"]');
  var billing_state_element = $('input[name="billing_state"]');

  var shipping_street_element = $('textarea[name="shipping_street"]');
  var shipping_city_element = $('input[name="shipping_city"]');
  var shipping_state_element = $('input[name="shipping_state"]');

  name_element.removeClass('border-red-customer');
  phonenumber_element.removeClass('border-red-customer');
  address_element.removeClass('border-red-customer');
  city_element.removeClass('border-red-customer');
  state_element.removeClass('border-red-customer');

  billing_street_element.removeClass('border-red-customer');
  billing_city_element.removeClass('border-red-customer');
  billing_state_element.removeClass('border-red-customer');

  shipping_street_element.removeClass('border-red-customer');
  shipping_city_element.removeClass('border-red-customer');
  shipping_state_element.removeClass('border-red-customer');

  var invalid = true;
  if(name_element.val() == ''){
    name_element.addClass('border-red-customer');
    invalid = false;
  }
  if(phonenumber_element.val() == ''){
    phonenumber_element.addClass('border-red-customer');
    invalid = false;
  }
  if(address_element.val() == ''){
    address_element.addClass('border-red-customer');
    invalid = false;
  }
  if(city_element.val() == ''){
    city_element.addClass('border-red-customer');
    invalid = false;
  }
  if(state_element.val() == ''){
    state_element.addClass('border-red-customer');
    invalid = false;
  }
  if(email_element.hasClass('border-red-customer')){
    invalid = false;
  }

  if(billing_street_element.val() == ''){
    billing_street_element.addClass('border-red-customer');
    invalid = false;
  }
  if(billing_city_element.val() == ''){
    billing_city_element.addClass('border-red-customer');
    invalid = false;
  }
  if(billing_state_element.val() == ''){
    billing_state_element.addClass('border-red-customer');
    invalid = false;
  }

  if(shipping_street_element.val() == ''){
    shipping_street_element.addClass('border-red-customer');
    invalid = false;
  }
  if(shipping_city_element.val() == ''){
    shipping_city_element.addClass('border-red-customer');
    invalid = false;
  }
  if(shipping_state_element.val() == ''){
    shipping_state_element.addClass('border-red-customer');
    invalid = false;
  }
  if(invalid ==  false){
    return false;
  }

  $.ajax({
    method: $(this).attr('method'),
    url: $(this).attr('action'),
    data: $(this).serialize(),
  }).done(function(response) {
   response = JSON.parse(response);
   $('#myModal').modal('hide');
   if(response.success == true){          
    $('#alert').modal('show').find('.alert_content').text('Create successful customers');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
    $('.tab-pane.active').find('select[name="client_id"]').html(response.html).selectpicker('refresh');
    $('.tab-pane.active').find('select[name="client_id"]').val(response.id).change();
  }
  else{
   $('#alert').modal('show').find('.alert_content').text('Failure');
   setTimeout(function(){ $('#alert').modal('hide'); },1500);
 }
});
  event.preventDefault();
});
function get_list_product_ware_house(){
  "use strict";
  get_list_group();
  $('.content_cart .list_item').html('');

  $('input[name="list_id_product"]').val('');
  $('input[name="list_qty_product"]').val('');
  $('input[name="list_price_product"]').val('');
  $('input[name="list_price_discount_product"]').val('');
  $('input[name="list_price_tax"]').val('');
  $('input[name="list_percent_discount_product"]').val('');

  $('input[name="discount_total"]').val('');
  $('.discount-total').text('');
  $('.subtotal').text('');
  $('.total').text('');
  $('.promotions_tax_price').text('');
  $('input[name="sub_total_cart"]').val('');
  $('input[name="total_cart"]').val('');   
  $('input[name="tax"]').val('');        
  $('input[name="discount_auto_event"]').val('');    
  $('input[name="discount_voucher_event"]').val(''); 
}
function checkout_cart(){
  "use strict";
  $('input[name="debit_order"]').prop('checked', false);
  $('input[name="customers_pay"]').val('').removeAttr('disabled');  
  var list = $('.tab-pane.active').find('.list_item');
  var count_child = list.children().length;
  var customer = $('.tab-pane.active').find('select[name="client_id"]').val();
  var seller = $('input[name="seller"]').val();
  if(count_child == 0){
    $('#alert').modal('show').find('.alert_content').text('Cart is empty, please add product to cart to make checkout');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
    return false;
  }
  if(customer=='' && seller==''){
    $('#alert').modal('show').find('.alert_content').text('<?php echo _l('please_select_customers_and_sellers'); ?>');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
    return false;
  }
  if(seller==''&&customer!=''){
    $('#alert').modal('show').find('.alert_content').text('<?php echo _l('please_select_a_seller'); ?>');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
    return false;
  }
  if(seller!=''&&customer==''){
    $('#alert').modal('show').find('.alert_content').text('<?php echo _l('please_select_a_customer'); ?>');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
    return false;
  }
  if(count_child > 0){
    $('.total_paying_s').text('');
    $('.balance_s').text(''); 
    $('input[name="customers_pay"]').val('');
    $('.total_items').text(count_child);
    var total_s = $('.tab-pane.active').find('.total').text();
    $('.total_payable').text(total_s);
    $('#payments').modal('show');
  }
}
function snapshot() {
  "use strict";
  var video = document.querySelector('video');
  var canvas = document.querySelector('canvas');
  var ctx = canvas.getContext('2d');
  var localMediaStream = null;
  var list = document.querySelector('ul#decoded');
  var host = window.location.protocol+'//'+window.location.host+'/modules/omni_sales/assets/plugins/zbar_processor/zbar-processor.js'
  var worker = new Worker(host);
  worker.onmessage = function(event) {
    if (event.data.length == 0) return;
    var d = event.data[0];
    $('[name="keyword"]').val(d[2]);
    var group_id = $('input[name="id_group"]').val();
    $('.content_list').html(''); 
    var key = $('input[name="keyword"]').val();    
    var page = $('input[name="index_page"]').val();
    var warehouse_id = $('select[name="warehouse_id"]').val();
    if(warehouse_id == ''){
     warehouse_id = 0;
   }

   if(page!=''){
    $.ajax({
     url: "get_product_by_group_pos_channel/"+page+'/'+group_id+'/'+warehouse_id+'/'+key,
     type: "post",
     data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>'},
     success: function(){
     },
     error:function(){
      $('#alert').modal('show').find('.alert_content').text('Failure');
      setTimeout(function(){ $('#alert').modal('hide'); },1500);
    }
  }).done(function(response) {
    response = JSON.parse(response);
    $('.content_list').html(response.data);
    var obj = $('.product_list');
    var first_el = obj.children().eq(0).parent();
    if(obj.html() != ''){
      first_el.prevObject.click();
    }
  });  
}
};

setInterval(snapshot1, 500);
navigator.getUserMedia = navigator.getUserMedia || navigator.webkitGetUserMedia || navigator.mozGetUserMedia || navigator.msGetUserMedia;
window.URL = window.URL || window.webkitURL || window.mozURL || window.msURL;

if (navigator.getUserMedia) {
  navigator.getUserMedia({video: true},
       function(stream) { // success callback
         if (video.mozSrcObject !== undefined) {
           video.mozSrcObject = stream;
         } else {
           video.srcObject = stream;
         }
         localMediaStream = true;
       },
       function(error) {
         console.error(error);
       });
}
else {
}
function snapshot1() {
  "use strict";
  if (localMediaStream === null) return;
  var k = (320 + 240) / (video.videoWidth + video.videoHeight);
  canvas.width = Math.ceil(video.videoWidth * k);
  canvas.height = Math.ceil(video.videoHeight * k);
  var ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0, video.videoWidth, video.videoHeight,
    0, 0, canvas.width, canvas.height);
  var data = ctx.getImageData(0, 0, canvas.width, canvas.height);
  worker.postMessage(data);
}
}
function check_exist_email(el){
  var email = $(el).val();
  if(email != ''){
   $.ajax({
     url: "check_exist_email_contact",
     type: "post",
     data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>', 'email':replacequote(email)},
     success: function(){
     },
     error:function(){

     }
   }).done(function(response) {
    response = JSON.parse(response);
    var email_element = $('input[name="email"]');

    if(response.exist == true){
      $('.alert_email').removeClass('hide');
      email_element.addClass('border-red-customer');
    }
    else{
      $('.alert_email').addClass('hide');
      email_element.removeClass('border-red-customer');
    }
  }); 
 }
}
//scan machine
//
$(document).ready(function() {
  var pressed = false;
  var chars = [];
  $(window).keypress(function(e) {
    if (e.key == '%') {
      pressed = true;
    }
    chars.push(String.fromCharCode(e.which));
    if (pressed == false) {
      setTimeout(function() {
        if (chars.length >= 8) {
          var barcode = chars.join('');
          $('input[name="keyword"]').val(''); 
          $('input[name="keyword"]').focus().val(barcode);

          var value = barcode; 
          $('.content_list').html(''); 
          var key = value;    
          var page = $('input[name="index_page"]').val();
          var warehouse_id = $('select[name="warehouse_id"]').val();
          if(warehouse_id == ''){
           warehouse_id = 0;
         }

         if(page!=''){
          $.ajax({
           url: "get_product_by_group_pos_channel/"+page+'/'+group_id+'/'+warehouse_id+'/'+key,
           type: "post",
           data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>'},
           success: function(){
           },
           error:function(){
            $('#alert').modal('show').find('.alert_content').text('Failure');
            setTimeout(function(){ $('#alert').modal('hide'); },1500);
          }
        }).done(function(response) {
          response = JSON.parse(response);
          $('.content_list').html(response.data);
          var obj = $('.product_list');
          var first_el = obj.children().eq(0).parent();
          if(obj.html() != ''){
            first_el.prevObject.click();
          }
        });  
      }


    }
    chars = [];
    pressed = false;
  }, 200);
    }
    pressed = true;
  });
});

function menu(el){
  "use strict";
  var val = $(el).data('id');
  $('.menu-fr').addClass('hide');
  switch(val) {
    case 'settintg_cart':
    $('.settintg_cart').removeClass('hide');
    $('#modal_setting .modal-dialog').css({'width':'330px'});
    $('#modal_setting').modal('show').find('.modal-title').text('<?php echo _l('quantity_format'); ?>');
    $('.btn_save_setting').attr('id','type_input_qty');
    break;
    case 'webcame':
    if(check_redline == false){
      scan_line();        
    }
    snapshot();
    $('.patent_fam').slideDown(200);
    break;
    case 'barcode':
    $('#alert').modal('show').find('.alert_content').text('Barcode scanner has been turned on successfully').css({'color':'#fff'});
    $('#alert .modal-content').css({'background-color':'green'});
    setTimeout(function(){ $('#alert').modal('hide'); },2000); 
    break;
    case 'customer':
    registration_client();
    init_selectpicker();
    break;
    case 'calculator':
    $('#calculator').fadeIn(500);
    $('#calculator').css({'display':'grid'});
    break;
  } 

}
$(".relative").draggable();
$('.exit_video_frame').click(function(){
  $('.patent_fam').slideUp(300);
});
var line_top = 1;
var check_redline = false;
function scan_line() {
  "use strict";
  check_redline = true;
  if(line_top == 1){
    $('#redline').css({'top':'42px'});
    $('#redline').animate ({
      'top': '+=247',
    }, 5000, 'linear', function() {
      scan_line();
    });
    line_top = 0;
  }
  else{
    $('#redline').css({'top':'247px'});
    $('#redline').animate ({
      'top': '-=205',
    }, 5000, 'linear', function() {
      scan_line();
    });
    line_top = 1;
  }
}
var typingTimer;
var doneTypingInterval = 1000;
$('input[name="keyword"]').on('input',function(e){
  var name = $(this).val().trim();  
  clearTimeout(typingTimer);
  typingTimer = setTimeout(get_list_group, doneTypingInterval,name);
});
function print_bill() {
 $(".tab-pane.active #content_print").get(0).contentWindow.print();
}
var payment_invoiceid = '';
var payment_note = '';
var payment_order_number = '';
var payment_payment_methods = '';
var payment_total_payment = '';
function open_payment(el){
 "use strict";
 payment_invoiceid =$(el).data('invoice_id');  
 payment_note =$(el).data('note');
 payment_order_number =$(el).data('order_number');
 payment_payment_methods =$(el).data('payment_methods');
 payment_total_payment =$(el).data('total_payment');
 $('#modal_payment_afters').modal('show');
}
function payment(el){
 "use strict";
 $.ajax({
   url: "payment_pos_after",
   type: "post",
   data: {'<?php echo html_entity_decode($this->security->get_csrf_token_name()); ?>':'<?php echo html_entity_decode($this->security->get_csrf_hash()); ?>'
   , 'invoice_id':payment_invoiceid
   , 'note':replacequote(payment_note)
   , 'order_number':replacequote(payment_order_number)
   , 'payment_methods':replacequote(payment_payment_methods)
   , 'total_payment':payment_total_payment
 },
 success: function(){
 },
 error:function(){
  $('#modal_payment_afters').modal('hide');
  $('#alert').modal('show').find('.alert_content').text('Error');
  setTimeout(function(){ $('#alert').modal('hide'); },1500);
}
}).done(function(response) {
  response = JSON.parse(response);
  $('#modal_payment_afters').modal('hide');
  if(response.success == true){
    $('.tab-pane.active').find('.payment_after').addClass('hide');
    $('#alert').modal('show').find('.alert_content').text('<?php echo _l('payment_success'); ?>');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
  }
  else{
    $('#alert').modal('show').find('.alert_content').text('<?php echo _l('payment_failed'); ?>');
    setTimeout(function(){ $('#alert').modal('hide'); },1500);
  }
}); 
}
</script>
