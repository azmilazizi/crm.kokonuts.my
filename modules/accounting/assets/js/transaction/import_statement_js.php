<script>
  (function($) {
    "use strict";

    appValidateForm($('#import_statement_form'), {
      file_csv: {required: true, extension: "xlsx"}
    });
  })(jQuery);

  function uploadStatementFile() {
    "use strict";

    if (($("#file_csv").val() !== '') && ($("#file_csv").val().split('.').pop() === 'xlsx')) {
      var formData = new FormData();
      formData.append("file_csv", $('#file_csv')[0].files[0]);
      if (<?php echo acc_check_csrf_protection(); ?>) {
        formData.append(csrfData.token_name, csrfData.hash);
      }

      var html = '';
      html += '<div class="Box">';
      html += '<span>';
      html += '<span></span>';
      html += '</span>';
      html += '</div>';
      $('#box-loading').html(html);
      $('button[id="upload_statement_file"]').attr("disabled", "disabled");

      $.ajax({
        url: admin_url + 'accounting/import_file_xlsx_statement',
        method: 'post',
        data: formData,
        contentType: false,
        processData: false
      }).done(function(response) {
        response = JSON.parse(response);

        $('#box-loading').html('');
        $('button[id="upload_statement_file"]').removeAttr('disabled');

        $("#file_csv").val(null);
        $("#file_csv").change();

        if ($("#statement_file_upload_response").html() !== '') {
          $("#statement_file_upload_response").empty();
        }

        $("#statement_file_upload_response").append("<h5>" + response.message + "</h5>");

        var $table = $('#statement-preview-table');
        var $thead = $table.find('thead tr');
        var $tbody = $table.find('tbody');
        $thead.empty();
        $tbody.empty();

        if (response.headers && response.headers.length) {
          response.headers.forEach(function(header) {
            $thead.append('<th>' + $('<div>').text(header).html() + '</th>');
          });
        }

        if (response.rows && response.rows.length) {
          response.rows.forEach(function(row) {
            var $row = $('<tr></tr>');
            row.forEach(function(cell) {
              var cellValue = cell === null ? '' : cell;
              $row.append('<td>' + $('<div>').text(cellValue).html() + '</td>');
            });
            $tbody.append($row);
          });
        }
      });
      return false;
    } else if ($("#file_csv").val() !== '') {
      alert_float('warning', "<?php echo _l('_please_select_a_file') ?>");
    }
  }
</script>
