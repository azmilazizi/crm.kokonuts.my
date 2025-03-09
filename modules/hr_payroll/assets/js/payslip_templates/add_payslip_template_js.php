<script >
	
	(function(){
		"use strict";

		 var maxed = false
		  , resizeTimeout
		  , availableWidth
		  , availableHeight
		  , $window = $(window)
		  , $example1 = $('#luckysheet');

		var calculateSize = function () {
		  var offset = $example1.offset();

		  availableWidth = $window.width() - offset.left + $window.scrollLeft();
		  availableHeight = $window.height() - offset.top + $window.scrollTop();
		};

		$window.on('resize', calculateSize);

		console.log('window.devicePixelRatio', window.devicePixelRatio);
		var availableHeight = $window.height()- $example1.offset().top;

		$('#luckysheet').parents('.row').css({'position': 'fixed', 'left':'13px', 'right':'0', 'bottom' : '2px', 'top' : '92px'});
		$('#luckysheet').parents('.container').css({'width': 'unset', 'padding':'0'});




		if((<?php echo isset($data_form) ? "true" : "false"?>)){
			var data = <?php echo isset($data_form) ? ($data_form != "" ? $data_form : '""') : '""' ?>;
			var dataSheet = data;
			var title = "<?php echo isset($file_excel) ? $file_excel->templates_name : "" ?>";
		}else{
			var dataSheet = [{
				name: "Sheet1",
				status: "1",
				order: "0",
				row: 36, //the number of rows in a sheet
				column: 26, //the number of columns in a sheet
				// cellData: cellData,
				data: [],
				config: {},
				index: 0
			}];

			var title = "Payslip Template";
		}
		var url_manage = admin_url + 'hr_payroll/payslip_templates_manage';

		var options = {
			container: 'luckysheet',
			lang: 'en',
			allowEdit:true,
			forceCalculation:true,
			data: dataSheet,
			title: title,
			functionButton: '<?php echo $permission_actions; ?>',

			cellRightClickConfig:{

					copy: false, // copy
					copyAs: false, // copy as
					paste: false, // paste
					insertRow: true, // insert row
					insertColumn: true, // insert column
					deleteRow: true, // delete the selected row
					deleteColumn: true, // delete the selected column
					deleteCell: true, // delete cell
					hideRow: false, // hide the selected row and display the selected row
					hideColumn: false, // hide the selected column and display the selected column
					rowHeight: true, // row height
					columnWidth: true, // column width
					clear: false, // clear content
					matrix: false, // matrix operation selection
					sort: false, // sort selection
					filter: false, // filter selection
					chart: false, // chart generation
					image: false, // insert picture
					link: false, // insert link
					data: false, // data verification
					cellFormat: false // Set cell format

			},

			rowHeaderWidth: 50,
			columnHeaderHeight: 23,
			defaultFontSize: 14,

			showtoolbar: true, // The default is true, you can leave it unset
			showtoolbarConfig:{

				image:false, // 'Insert picture'
				link:false, // 'Insert link'
				chart: false, //'chart' (the icon is hidden, but if the chart plugin is configured, you can still create a new chart by right click)
				postil: false, //'comment'
				pivotTable: false, //'PivotTable'
				frozenMode: false, //'freeze mode'
				sortAndFilter: false, //'Sort and filter'
				conditionalFormat: false, //'Conditional Format'
				dataVerification: false, // 'Data Verification'
				splitColumn: false, //'Split column'
				screenshot: false, //'screenshot'
				findAndReplace: false, //'Find and Replace'
				protection:false, // 'Worksheet protection'
				print:false, // 'Print'
			},

			showsheetbar:true,
			showsheetbarConfig:{
				add: false, //Add worksheet
		    menu: false, //Worksheet management menu
		    sheet: true //Worksheet display
			},

			showstatisticBar: true, // The default is true, you can leave it unset
			showstatisticBarConfig:{
				view: false,
				count:false
			},
			enableAddRow: false,
			enableAddBackTop: false,
			fullscreenmode: true,
			devicePixelRatio:window.devicePixelRatio,

		}


		luckysheet.create(options);

		var type_screen = $("input[name='type']").val();
		var role = $("input[name='role']").val();

		if(type_screen == 3){
			$('.luckysheet_info_detail_save_as').remove();
		}
		if(role == 1){
			$('.luckysheet_info_detail_save_as').remove();
			$('.luckysheet_info_detail_save').remove();
		}
			
	})(jQuery);


	//save payroll template via Ajax
	$(".luckysheet_info_detail_save").unbind('click').on('click', function(){   
		$("form#spreadsheet-test-form").unbind('submit').on('submit', function(e) {

			"use strict";
			
			$(".luckysheet_info_detail_save").attr( "disabled", "disabled" );

			e.preventDefault();    
			var rawData = luckysheet.getLuckysheetfile();
			var image_flag = "false";

			var currentSheet = 0; // I'm storing only the first sheet of the document, you might want to store them all, just iterate it
			var rawData = luckysheet.getLuckysheetfile();
			var rawCelldata = luckysheet.transToCellData(rawData[currentSheet ].data); // luckysheet.transToCellData this transforms rawData into celldata format
			rawData[currentSheet].celldata = rawCelldata; // from the input json on load event, cells are read from "celldata" property
			rawData[currentSheet].data = []; // dump useless data, we don't need to store this null bloat
			var finalData = JSON.stringify(rawData);

			var newFinalData = finalData.replace(/\,\(/g, '#replace#');//string replace ,( to #replace# because when submit form ,( will be loss
			var newFinalData = newFinalData.replace(/\,\I\F\(/g, '#replace2#');//string replace ,IF( to #replace2# because when submit form ,( will be loss
			var newFinalData = newFinalData.replace(/\</g, "&lt;");//string replace < to &lt; because when submit form < will be loss
			var newFinalData = newFinalData.replace(/\>/g, "&gt;");//string replace > to &gt; because when submit form > will be loss

		
			var formData = new FormData(this);
			var name = $("#luckysheet_info_detail_input").val();
			var id = $("input[name='id']").val();
			formData.append('payslip_template_data', newFinalData);
			formData.append('name', name);
			formData.append('id', id);
			formData.append('image_flag', image_flag);
			formData.append("csrf_token_name", $('input[name="csrf_token_name"]').val());


			$.ajax({
				url: admin_url + 'hr_payroll/view_payslip_templates_detail',
				type: 'POST',
				data: formData,
				contentType: false,
				success: function (response, status, xhr) {
					response = JSON.parse(response);
					if(response.success == true) {
						alert_float('success', response.message);
						var disposition = xhr.getResponseHeader('content-disposition');
						$('#SaveAsModal').modal('hide');
					}
					else{
						alert_float('warning', response.message);
					}
					location.reload();
				},
				cache: false,
				processData: false
			})
		})
	});


</script>

