<script>
    
    var purchase;
    
    <?php if(isset($body_value)){ ?>

      var dataObject = <?php echo new_html_entity_decode($body_value) ; ?>;
    <?php }?>

  var hotElement1 = document.querySelector('#hrp_employees_value');
   var purchase = new Handsontable(hotElement1, {

    contextMenu: true,
    manualRowMove: true,
    manualColumnMove: true,
    stretchH: 'all',
    autoWrapRow: true,

    rowHeights: 20,
    defaultRowHeight: 10,
    minHeight:'100%',
    width: '100%',
    height:600,

    licenseKey: 'non-commercial-and-evaluation',
    rowHeaders: true,
    autoColumnsub_group: {
      samplingRatio: 23
    },
    dropdownMenu: true,
     hiddenColumns: {
        columns: [0,1,2],
        indicators: false
      },
    multiColumnSorting: {
        indicator: true
      }, 
    fixedColumnsLeft: 5,

    filters: true,
    manualRowResub_group: true,
    manualColumnResub_group: true,
    allowInsertRow: false,
    allowRemoveRow: false,
    columnHeaderHeight: 40,

    rowHeights: 40,
    rowHeaderWidth: [44],


    columns: <?php echo new_html_entity_decode($columns) ?>,

    colHeaders: <?php echo new_html_entity_decode($col_header); ?>,

    data: dataObject,

  });

    //filter
  function employees_filter (invoker){
    'use strict';

    var data = {};
    data.month = $("#month_employees").val();
    data.staff  = $('select[name="staff_employees[]"]').val();
    data.department = $('#department_employees').val();
    data.role_attendance = $('select[name="role_employees[]"]').val();

    $.post(admin_url + 'hr_payroll/employees_filter', data).done(function(response) {
      response = JSON.parse(response);
      dataObject = response.data_object;
      purchase.updateSettings({
        data: dataObject,

      })
      $('input[name="month"]').val(response.month);
      $('.save_manage_employees').html(response.button_name);
      
    });
  };



  var purchase_value = purchase;

  $('.hrp_employees_synchronization').on('click', function() {
    'use strict';

    var valid_contract = $('#hrp_employees_value').find('.htInvalid').html();

    if(valid_contract){
      alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
    }else{

      $('input[name="hrp_employees_value"]').val(JSON.stringify(purchase_value.getData()));   
      $('input[name="employees_fill_month"]').val($("#month_employees").val());
      $('input[name="hrp_employees_rel_type"]').val('synchronization');   
      $('#add_manage_employees').submit(); 

    }
  });


  $('.save_manage_employees').on('click', function() {
    'use strict';

    var valid_contract = $('#hrp_employees_value').find('.htInvalid').html();

    if(valid_contract){
      alert_float('danger', "<?php echo _l('data_invalid') ; ?>");
    }else{

      $('input[name="hrp_employees_value"]').val(JSON.stringify(purchase_value.getData()));   
      $('input[name="employees_fill_month"]').val($("#month_employees").val());
      $('input[name="hrp_employees_rel_type"]').val('update');   
      $('#add_manage_employees').submit(); 

    }
  });

  $('#department_employees').on('change', function() {
    'use strict';

    $('input[name="department_employees_filter"]').val($("#department_employees").val());   
    employees_filter();
  });

  $('#staff_employees').on('change', function() {
    'use strict';

    $('input[name="staff_employees_filter"]').val($("#staff_employees").val());   
    employees_filter();
  });

  $('#role_employees').on('change', function() {
    'use strict';
    
    $('input[name="role_employees_filter"]').val($("#role_employees").val());   
    employees_filter();
  });
  
  $('#month_employees').on('change', function() {
    'use strict';

    employees_filter();

  });

  $('.hrp_employees_copy').on('click', function() {
    'use strict';

    var data = {};
    data.month = $("#month_employees").val();

    $.post(admin_url + 'hr_payroll/employees_copy', data).done(function(response) {
      response = JSON.parse(response);

      alert_float(response.status, response.message);
      employees_filter();
      
    });

  });



</script>