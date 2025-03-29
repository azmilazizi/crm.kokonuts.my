function pur_calculate_total(from_discount_money){
  "use strict";
  if ($('body').hasClass('no-calculate-total')) {
    return false;
  }

  var calculated_tax,
    taxrate,
    item_taxes,
    row,
    _amount,
    _tax_name,
    taxes = {},
    taxes_rows = [],
    subtotal = 0,
    total = 0,
    total_money = 0,
    total_tax_money = 0,
    quantity = 1,
    total_discount_calculated = 0,
    item_total_payment,
    rows = $('.table.has-calculations tbody tr.item'),
    subtotal_area = $('#subtotal'),
    discount_area = $('#discount_area'),
    adjustment = $('input[name="adjustment"]').val(),
    // discount_percent = $('input[name="discount_percent"]').val(),
    discount_percent = 'before_tax',
    discount_fixed = $('input[name="discount_total"]').val(),
    discount_total_type = $('.discount-total-type.selected'),
    discount_type = $('select[name="discount_type"]').val(),
    additional_discount = $('input[name="additional_discount"]').val(),
    add_discount_type = $('select[name="add_discount_type"]').val();

    var shipping_fee = $('input[name="shipping_fee"]').val();
    if(shipping_fee == ''){
      shipping_fee = 0;
      $('input[name="shipping_fee"]').val(0);
    }

  $('.wh-tax-area').remove();

    $.each(rows, function () {
    var item_discount = 0;
    var item_discount_money = 0;
    var item_discount_from_percent = 0;
    var item_discount_percent = 0;
    var item_tax = 0,
        item_amount  = 0;

    $(this).find('td.rate input').val($(this).find('td.total input').val() * $(this).find('td.quantity input').val());

    quantity = $(this).find('[data-quantity]').val();
    if (quantity === '') {
      quantity = 1;
      $(this).find('[data-quantity]').val(1);
    }
    item_discount_percent = $(this).find('td.discount input').val();
    item_discount_money = $(this).find('td.discount_money input').val();

    if (isNaN(item_discount_percent) || item_discount_percent == '') {
      item_discount_percent = 0;
    }

    if (isNaN(item_discount_money) || item_discount_money == '') {
      item_discount_money = 0;
    }

    if(from_discount_money == 1 && item_discount_money > 0){
      $(this).find('td.discount input').val('');
    }

    _amount = accounting.toFixed($(this).find('td.rate input').val() * quantity, app.options.decimal_places);
    item_amount = _amount;
    _amount = parseFloat(_amount);

    $(this).find('td.into_money').html(format_money(_amount));
    $(this).find('td._into_money input').val(_amount);

    subtotal += _amount;
    row = $(this);
    item_taxes = $(this).find('select.taxes').val();

    if(discount_type == 'after_tax'){
      if (item_taxes) {
        $.each(item_taxes, function (i, taxname) {
          taxrate = row.find('select.taxes [value="' + taxname + '"]').data('taxrate');
          calculated_tax = (_amount / 100 * taxrate);
          item_tax += calculated_tax;
          if (!taxes.hasOwnProperty(taxname)) {
            if (taxrate != 0) {
              _tax_name = taxname.split('|');
              var tax_row = '<tr class="wh-tax-area"><td>' + _tax_name[0] + '(' + taxrate + '%)</td><td id="tax_id_' + slugify(taxname) + '"></td></tr>';
              $(subtotal_area).after(tax_row);
              taxes[taxname] = calculated_tax;
            }
          } else {
                      // Increment total from this tax
                      taxes[taxname] = taxes[taxname] += calculated_tax;
                  }
              });
      }
    }
    
      //Discount of item
      if( item_discount_percent > 0 && from_discount_money != 1){

        if(discount_type == 'after_tax'){
          item_discount_from_percent = (parseFloat(item_amount) + parseFloat(item_tax) ) * parseFloat(item_discount_percent) / 100;
        }else if(discount_type == 'before_tax'){
          item_discount_from_percent = parseFloat(item_amount) * parseFloat(item_discount_percent) / 100;
        }

        if(item_discount_from_percent != item_discount_money){
          item_discount_money = item_discount_from_percent;
        }
      }

      if( item_discount_money > 0){
        item_discount = parseFloat(item_discount_money);
      }

     
      // Append value to item
      total_discount_calculated += parseFloat(item_discount);
      $(this).find('td.discount_money input').val(item_discount);


      if(discount_type == 'before_tax'){ 
        if (item_taxes) {
          var after_dc_amount = _amount - parseFloat(item_discount);
          $.each(item_taxes, function (i, taxname) {
              taxrate = row.find('select.taxes [value="' + taxname + '"]').data('taxrate');
              calculated_tax = (after_dc_amount / 100 * taxrate);
              item_tax += calculated_tax;
              if (!taxes.hasOwnProperty(taxname)) {
                if (taxrate != 0) {
                  _tax_name = taxname.split('|');
                  var tax_row = '<tr class="wh-tax-area"><td>' + _tax_name[0] + '(' + taxrate + '%)</td><td id="tax_id_' + slugify(taxname) + '"></td></tr>';
                  $(subtotal_area).after(tax_row);
                  taxes[taxname] = calculated_tax;
                }
              } else {
                  // Increment total from this tax
                  taxes[taxname] = taxes[taxname] += calculated_tax;
              }
          });
        }
      }

      var after_tax = _amount + item_tax;
      var before_tax = _amount;

      item_total_payment = parseFloat(item_amount) + parseFloat(item_tax) - parseFloat(item_discount);

      $(this).find('td.total_after_discount input').val(item_total_payment);

    $(this).find('td.label_total_after_discount').html(format_money(item_total_payment));

    // $(this).find('td._total').html(format_money(after_tax));
    $(this).find('td._total').html(
      '<input type="number" class="form-control text-right" min="0.0" step="any" value="' + after_tax + '">'
    );
    $(this).find('td._total_after_tax input').val(after_tax);

    $(this).find('td.tax_value input').val(item_tax);

  });

  var order_discount_percent = $('input[name="order_discount"]').val();  
  var order_discount_percent_val = 0;
  // Discount by percent
  if ((order_discount_percent !== '' && order_discount_percent != 0) && discount_type == 'before_tax' && add_discount_type == 'percent') {
    total_discount_calculated += parseFloat((subtotal * order_discount_percent) / 100);
    order_discount_percent_val = (subtotal * order_discount_percent) / 100;
  } else if ((order_discount_percent !== '' && order_discount_percent != 0) && discount_type == 'before_tax' && add_discount_type == 'amount') {
    total_discount_calculated += parseFloat(order_discount_percent);
    order_discount_percent_val = order_discount_percent;
  }

  $.each(taxes, function (taxname, total_tax) {
    if ((order_discount_percent !== '' && order_discount_percent != 0) && discount_type == 'before_tax' && add_discount_type == 'percent') {
      var total_tax_calculated = (total_tax * order_discount_percent) / 100;
      total_tax = (total_tax - total_tax_calculated);
    } else if ((order_discount_percent !== '' && order_discount_percent != 0) && discount_type == 'before_tax' && add_discount_type == 'amount') {
      var t = (order_discount_percent / subtotal) * 100;
      total_tax = (total_tax - (total_tax * t) / 100);
    }

    total += total_tax;
    total_tax_money += total_tax;
    total_tax = format_money(total_tax);
    $('#tax_id_' + slugify(taxname)).html(total_tax);
  });


  total = (total + subtotal);
  total_money = total;
  // Discount by percent

  if ((order_discount_percent !== '' && order_discount_percent != 0) && discount_type == 'after_tax' && add_discount_type == 'percent') {
    total_discount_calculated += parseFloat((total * order_discount_percent) / 100);
    order_discount_percent_val = (total * order_discount_percent) / 100;
  } else if ((order_discount_percent !== '' && order_discount_percent != 0) && discount_type == 'after_tax' && add_discount_type == 'amount') {
    total_discount_calculated += parseFloat(order_discount_percent);
    order_discount_percent_val = order_discount_percent;
  }

  
  //total_discount_calculated = total_discount_calculated;

  total = parseFloat(total) - parseFloat(total_discount_calculated) - parseFloat(additional_discount);
  adjustment = parseFloat(adjustment);

  // Check if adjustment not empty
  if (!isNaN(adjustment)) {
    total = total + adjustment;
  }

  total+= parseFloat(shipping_fee);

  var discount_html = '-' + format_money(parseFloat(total_discount_calculated)+ parseFloat(additional_discount));
    $('input[name="discount_total"]').val(accounting.toFixed(total_discount_calculated, app.options.decimal_places));
    
  // Append, format to html and display
  $('.shiping_fee').html(format_money(shipping_fee));
  $('.order_discount_value').html(format_money(order_discount_percent_val));
  $('.wh-total_discount').html(discount_html + hidden_input('dc_total', accounting.toFixed(order_discount_percent_val, app.options.decimal_places))  );
  $('.adjustment').html(format_money(adjustment));
  $('.wh-subtotal').html(format_money(subtotal) + hidden_input('total_mn', accounting.toFixed(subtotal, app.options.decimal_places)));
  $('.wh-total').html(format_money(total) + hidden_input('grand_total', accounting.toFixed(total, app.options.decimal_places)));

  $(document).trigger('purchase-quotation-total-calculated');

}