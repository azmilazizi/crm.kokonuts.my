(function () {
  "use strict";
  $(document).on("click", ".btn-edit-datetime", function () {
    $(this).addClass('hide');
    $('.btn-close-edit-datetime').removeClass('hide');
    $("#clock_attendance_modal .curr_date .form-group").slideDown(500);
  });
  $(document).on("click", ".btn-close-edit-datetime", function () {
    $(this).addClass('hide');
    $('.btn-edit-datetime').removeClass('hide');
    $('#clock_attendance_modal input[name="edit_date"]').val('');
    $("#clock_attendance_modal .curr_date .form-group").slideUp(500);
    var staff_id = $('#clock_attendance_modal input[name="staff_id"]').val();
    get_data_attendance(staff_id, get_date());
    $('#clock_attendance_modal input[name="edit_date"]').val('');
  });
  $(window).on('load', function () {
    var hour_attendance = $('input[name="hour_attendance"]').val();
    var minute_attendance = $('input[name="minute_attendance"]').val();
    var second_attendance = $('input[name="second_attendance"]').val();
    server_time(hour_attendance, minute_attendance, second_attendance);
    get_route_point();
  });
})(jQuery);
var html_progress = '<div class="text-center">';
html_progress += '<img src="' + site_url + 'modules/timesheets/assets/images/loading.gif" alt="" width="100">';
html_progress += '</div>';
var run_time;

/**
 * set date
 */
function setDate(hour, minute, second) {
  "use strict";
  var secondDeg = ((second / 60) * 360) + 360;
  $("#clock_attendance_modal #secondHand").css('transform', 'rotate(' + secondDeg + 'deg)');
  var minuteDeg = ((minute / 60) * 360);
  $("#clock_attendance_modal #minuteHand").css('transform', 'rotate(' + minuteDeg + 'deg)');
  var hourDeg = ((hour / 12) * 360);
  $("#clock_attendance_modal #hourHand").css('transform', 'rotate(' + hourDeg + 'deg)');
}
/**
 * open check in out
 */
function open_check_in_out(passedDate = null, passedUser = null) {
  if ($('input[name="enable_get_location"]').val() == true) {
    getLocation();
  }
  $("#clock_attendance_modal .curr_date .form-group").slideUp(1);
  $('#clock_attendance_modal').modal('show');
  appValidateForm($('#timesheets-form-check-in'), {
    staff_id: 'required',
    date: 'required'
  })
  appValidateForm($('#timesheets-form-check-out'), {
    staff_id: 'required',
    date: 'required'
  })
  $(".btn-close-edit-datetime").click();

  if (passedDate) {
    $('input[name="edit_date"]').val(passedDate);
    $('#curr_date_attendance').contents().filter(function () {
      return this.nodeType === 3 && this.nodeValue.trim() !== ''; // Only text nodes
    }).first().replaceWith(passedDate + ' '); // Update the text
  } else {
    $('input[name="edit_date"]').val(''); // Optional: clear if not passed
  }

  if (passedUser) {

    $('#staff_id').val(passedUser).trigger('change');
  }
}
/**
 * update Clock
 */
function updateClock() {
  "use strict";
  var currentTime = new Date();
  var currentHoursAP = currentTime.getHours();
  var currentHours = currentTime.getHours();
  var currentMinutes = currentTime.getMinutes();
  var currentSeconds = currentTime.getSeconds();
  currentMinutes = (currentMinutes < 10 ? "0" : "") + currentMinutes;
  currentSeconds = (currentSeconds < 10 ? "0" : "") + currentSeconds;
  var timeOfDay = (currentHours < 12) ? "AM" : "PM";
  currentHoursAP = (currentHours > 12) ? currentHours - 12 : currentHours;
  currentHoursAP = (currentHoursAP == 0) ? 12 : currentHoursAP;
  var currentTimeString = currentHours + ":" + currentMinutes + ":" + currentSeconds;
  $('.time_script').text(currentTimeString);
  $('input[name="hours"]').val(currentTimeString);
}
/**
 * change date
 */
function changedate(el) {
  "use strict";
  var date = $(el).val();
  $('#clock_attendance_modal input[name="edit_date"]').val(date);
  var staff_id = $('#clock_attendance_modal input[name="staff_id"]').val();
  get_data_attendance(staff_id, get_date());
  get_route_point();
}
/**
 * changestaff id
 */
function changestaff_id(el) {
  "use strict";
  var staff_id = $(el).val();
  $('#clock_attendance_modal input[name="staff_id"]').val(staff_id);
  get_data_attendance(staff_id, get_date());
  get_route_point();
}
/**
 * get data attendance 
 */
function get_data_attendance(staff_id, date) {
  "use strict";
  if (staff_id != '') {
    var data = {};
    data.staff_id = staff_id;
    data.date = date;
    $.post(admin_url + 'timesheets/get_data_attendance', data).done(function (response) {
      response = JSON.parse(response);
      $('#attendance_history').html('');
      $('#attendance_history').html(response.html_list);
    });
  }
}
/**
 * get date
 */
function get_date() {
  "use strict";
  var val_date = $('#clock_attendance_modal input[name="edit_date"]').val();
  if (val_date == '') {
    val_date = $('input[name="date_attendance"]').val();
  }
  return val_date;
}
/**
 * server time 
 */
function server_time(hour, minute, second) {
  "use strict";
  setDate(hour, minute, second);
  second++;
  if (second > 59) {
    second = 0;
    minute++;
    if (minute > 59) {
      minute = 0;
      hour++;
      if (hour > 23) {
        hour = 0;
      }
    }
  }
  run_time = setTimeout(function () {
    server_time(hour, minute, second);
  }, 1000)
}

function get_route_point() {
  "use strict";
  var geolocation = $('input[name="location_user"]').eq(0).val();

  if (geolocation != '' && geolocation != undefined) {
    var split = geolocation.split(',');
    var data = {};
    data.lat = split[0];
    data.lng = split[1];
    data.staff = $('select[name="staff_id"]').val();
    data.date = $('#edit_date').val();

    $.post(admin_url + 'timesheets/get_route_point_combobox', data).done(function (response) {
      response = JSON.parse(response);
      if (response.point_id == '') {
        $('.route_point_combobox').addClass('hide');
      }
      else {
        $('.route_point_combobox').removeClass('hide');
        $('select[name="route_point"]').html(response.option).selectpicker('refresh');
      }
    });
  }
}
function get_data() {
  "use strict";
  var route_point = $('#route_point').val();
  $('input[name="point_id"]').val(route_point);
  $('#timesheets-form-check-in .check_in').attr('disabled', 'disabled');
  $('#timesheets-form-check-out .check_out').attr('disabled', 'disabled');
}

function getLocation() {
  "use strict";
  function success(position) {
    const latitude = position.coords.latitude;
    const longitude = position.coords.longitude;
    $('#clock_attendance_modal input[name="location_user"]').val(latitude + ',' + longitude);
    get_route_point();
  }

  function error() {
    alert('Unable to retrieve your location');
  }

  if (!navigator.geolocation) {
    alert('Geolocation is not supported by your browser');
  } else {
    navigator.geolocation.getCurrentPosition(success, error);
  }
}

/**
* open check in out
*/
function open_staff_qrcode() {
  "use strict";
  var modal_obj = $('#staff_qr_code_modal');
  modal_obj.modal('show');
  if (modal_obj.find('.modal-body').html() == '') {
    modal_obj.find('.modal-body').html(html_progress);
    $.post(admin_url + 'timesheets/get_staff_qr_code').done(function (response) {
      modal_obj.find('.modal-body').html(response);
    });
  }
}

function open_scan_qr_code() {
  $('#clock_attendance_modal').modal('hide');
  var modal_obj = $('#scan_qr_code_modal');
  modal_obj.modal('show');
  modal_obj.find('.modal-body').html(html_progress);
  $.post(admin_url + 'timesheets/get_scan_qr_code_content').done(function (response) {
    modal_obj.find('.modal-body').html(response);
    init_scan_qr();
  });
}

function init_scan_qr() {
  var html5QrcodeScanner = new Html5QrcodeScanner(
    "reader", { fps: 10, qrbox: 250 });
  html5QrcodeScanner.render(onScanSuccess);
  $('#reader').append('<div class="scan-process-image hide">' + html_progress + '</div>');
}
var scan_session = false;
function onScanSuccess(decodedText, decodedResult) {
  clearTimeout(scan_session);
  scan_session = setTimeout(function () {
    try {
      $('#scan_qr_code_modal #result_code').addClass('hide').html('');
      $('#scan_qr_code_modal #attendance_history').html('');
      var result = `${decodedText}`;
      var split_result = result.split(':');
      var split_result2 = split_result[1].split('\n');
      if (split_result2[0] != '') {
        $('.scan-process-image').removeClass('hide');
        var user_id = split_result2[0].trim();
        $.post(admin_url + 'timesheets/checkin_from_qr_code/' + user_id).done(function (response) {
          response = JSON.parse(response);
          $('.scan-process-image').addClass('hide');
          if (response.success == true) {
            alert_float('success', response.message);
            $('#scan_qr_code_modal #result_code').removeClass('hide').html(result);
            $('#scan_qr_code_modal #attendance_history').html(response.data_attendance);
            $('#scan_qr_code_modal .scan_qr_code_continue_btn').removeClass('hide');
          }
          else {
            alert_float('danger', response.message);
          }
          return false;
        });
      }
      else {
        $('.scan-process-image').addClass('hide');
      }
    }
    catch (err) {
      $('.scan-process-image').addClass('hide');
    }
  }, 1000);
}

function scan_continue() {
  $('#scan_qr_code_modal .scan_qr_code_continue_btn').addClass('hide');
  open_scan_qr_code();
}

