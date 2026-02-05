<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12 tw-mb-3">
                <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                    <?= _l('invoice_payments_import'); ?>
                </h4>
                <p class="text-muted">
                    <?= _l('invoice_payments_import_hint'); ?>
                </p>
            </div>
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="alert alert-info tw-flex tw-flex-col sm:tw-flex-row sm:tw-items-center sm:tw-justify-between tw-gap-2">
                            <div>
                                <strong>Template file</strong>
                                <p class="tw-mb-0 text-muted">Download the Excel template with guidelines before uploading payments.</p>
                            </div>
                            <a
                                class="btn btn-default"
                                href="<?php echo base_url('uploads/file_sample/invoice_payments_import_with_guideline_and_color.xlxx'); ?>"
                                download
                            >
                                Download template
                            </a>
                        </div>
                        <div class="form-group">
                            <label for="invoice-payments-file">Excel file (.xlsx)</label>
                            <input
                                type="file"
                                id="invoice-payments-file"
                                class="form-control"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            />
                        </div>
                        <div id="invoice-payments-alert" class="alert alert-info hide"></div>
                        <div id="invoice-payments-progress" class="hide tw-mb-3">
                            <div class="progress">
                                <div
                                    class="progress-bar progress-bar-striped active"
                                    role="progressbar"
                                    aria-valuenow="0"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    style="width: 0%;"
                                >
                                    0%
                                </div>
                            </div>
                            <p class="text-muted tw-mt-2" id="invoice-payments-progress-text"></p>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="invoice-payments-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>Currency</th>
                                        <th>Subtotal</th>
                                        <th>Total</th>
                                        <th>Discount</th>
                                        <th>Sale Agent</th>
                                        <th>Payments</th>
                                        <th>Payment Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="11" class="text-muted text-center">
                                            Upload a file to preview invoice payment data.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="tw-mt-4 tw-flex tw-justify-end">
                            <button type="button" class="btn btn-primary" id="invoice-payments-submit" disabled>
                                Submit Payments
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<style>
    #invoice-payments-table th.invoice-caret-col,
    #invoice-payments-table td.invoice-caret-col {
        padding-right: 4px;
        padding-left: 4px;
        width: 28px;
    }

    #invoice-payments-table td.invoice-number-cell {
        padding-left: 4px;
    }

    #invoice-payments-table .invoice-toggle {
        padding: 0;
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #invoice-payments-table .invoice-payments-count {
        text-align: center;
    }

    #invoice-payments-table .numeric-input {
        text-align: right;
    }

    #invoice-payments-table td.invoice-caret-col,
    #invoice-payments-table td.invoice-actions-col,
    #invoice-payments-table td.payment-actions-col,
    #invoice-payments-table td.invoice-payments-count,
    #invoice-payments-table td.invoice-payments-total {
        vertical-align: middle;
    }

    #invoice-payments-table .invoice-payments-collapse > td {
        padding-top: 6px;
    }

    #invoice-payments-table .invoice-payments-collapse .mtop10 {
        margin-top: 6px;
    }
</style>
<script>
    $(function() {
        var warehouseOptions = <?php echo json_encode($warehouses ?? []); ?>;
        var paymentModeOptions = <?php echo json_encode($payment_modes ?? []); ?>;
        var currencyOptions = [{ value: 1, label: "RM" }];
        var requiredHeaders = [
            "date",
            "duedate",
            "currency_id",
            "subtotal",
            "total",
            "discount_total",
            "sale_agent",
            "payment_amount",
            "paymentmode_id",
            "payment_note",
        ];
        var invoiceHeaders = [
            "date",
            "duedate",
            "currency_id",
            "subtotal",
            "total",
            "discount_total",
            "sale_agent",
        ];
        var paymentHeaders = ["payment_amount", "paymentmode_id", "payment_note"];
        var parsedInvoices = [];
        var expandedInvoiceRows = {};

        function mapOptions(list, valueKey, labelKey) {
            if (!Array.isArray(list)) {
                return [];
            }
            return list.map(function(option) {
                return {
                    value: option[valueKey],
                    label: option[labelKey],
                };
            });
        }

        var saleAgentOptions = mapOptions(warehouseOptions, "warehouse_id", "warehouse_code");
        var paymentOptions = mapOptions(paymentModeOptions, "id", "name");

        function normalize(value) {
            if (value === null || typeof value === "undefined") {
                return "";
            }
            return String(value).trim().toLowerCase();
        }

        function isEmpty(value) {
            return normalize(value) === "";
        }

        function showAlert(message, type) {
            var $alert = $("#invoice-payments-alert");
            $alert
                .removeClass("alert-info alert-warning alert-danger alert-success")
                .addClass("alert-" + type)
                .text(message)
                .removeClass("hide");
        }

        function clearAlert() {
            $("#invoice-payments-alert").addClass("hide").text("");
        }

        function findHeaderRow(rows) {
            for (var i = 0; i < rows.length; i++) {
                var row = rows[i] || [];
                var headerMap = {};
                row.forEach(function(cell, index) {
                    var key = normalize(cell);
                    if (key !== "") {
                        headerMap[key] = index;
                    }
                });

                var hasAll = requiredHeaders.every(function(header) {
                    return typeof headerMap[header] !== "undefined";
                });

                if (hasAll) {
                    return { index: i, map: headerMap };
                }
            }

            return null;
        }

        function buildRowData(row, headerMap) {
            var data = {};
            Object.keys(headerMap).forEach(function(header) {
                data[header] = row[headerMap[header]];
            });
            return data;
        }

        function parseSheet(rows) {
            parsedInvoices = [];
            var invoiceByNumber = {};
            var headerRow = findHeaderRow(rows);

            if (!headerRow) {
                showAlert(
                    "Unable to locate header row. Make sure the sheet includes the required columns.",
                    "warning"
                );
                return false;
            }

            var lastInvoiceKey = null;
            for (var i = headerRow.index + 1; i < rows.length; i++) {
                var row = rows[i] || [];
                var rowData = buildRowData(row, headerRow.map);

                if (
                    invoiceHeaders.concat(paymentHeaders).every(function(header) {
                        return isEmpty(rowData[header]);
                    })
                ) {
                    continue;
                }

                var hasPayment = paymentHeaders.some(function(header) {
                    return !isEmpty(rowData[header]);
                });
                var isPaymentData = isEmpty(rowData.date);

                if (!isPaymentData) {
                    var invoice = {
                        date: rowData.date || "",
                        duedate: rowData.duedate || "",
                        currency_id: rowData.currency_id || 1,
                        subtotal: rowData.subtotal || "0.00",
                        total: rowData.total || "0.00",
                        discount_total: rowData.discount_total || "0.00",
                        sale_agent: rowData.sale_agent || "",
                        payments: [],
                    };

                    parsedInvoices.push(invoice);
                    invoiceByNumber["row-" + i] = invoice;
                    lastInvoiceKey = "row-" + i;

                    if (hasPayment) {
                        invoice.payments.push({
                            payment_amount: rowData.payment_amount || "0.00",
                            paymentmode_id: rowData.paymentmode_id || "",
                            payment_note: rowData.payment_note || "",
                        });
                    }

                    continue;
                }

                if (hasPayment && lastInvoiceKey && invoiceByNumber[lastInvoiceKey]) {
                    invoiceByNumber[lastInvoiceKey].payments.push({
                        payment_amount: rowData.payment_amount || "0.00",
                        paymentmode_id: rowData.paymentmode_id || "",
                        payment_note: rowData.payment_note || "",
                    });
                }
            }

            if (!parsedInvoices.length) {
                showAlert("No invoice rows found after the header.", "warning");
            } else {
                showAlert(
                    "Parsed " + parsedInvoices.length + " invoice(s). Expand a row to view payments.",
                    "success"
                );
            }
            $("#invoice-payments-submit").prop("disabled", parsedInvoices.length === 0);
            return parsedInvoices.length > 0;
        }

        function formatMoney(value) {
            var number = parseFloat(value);
            if (isNaN(number)) {
                return "0.00";
            }
            return number.toFixed(2);
        }

        function buildTextInput(value, className, dataAttributes) {
            var attrs = dataAttributes || "";
            return (
                '<input type="text" class="form-control ' +
                className +
                '" value="' +
                (value || "") +
                '" ' +
                attrs +
                ">"
            );
        }

        function buildSelect(options, selectedValue, className, dataAttributes) {
            var attrs = dataAttributes || "";
            var html =
                '<select class="form-control ' +
                className +
                '" ' +
                attrs +
                ">";

            options.forEach(function(option) {
                var selected =
                    String(option.value) === String(selectedValue) ? " selected" : "";
                html +=
                    '<option value="' +
                    option.value +
                    '"' +
                    selected +
                    ">" +
                    option.label +
                    "</option>";
            });

            html += "</select>";
            return html;
        }

        function updateInvoiceTotals(index) {
            var invoice = parsedInvoices[index];
            var paymentTotal = invoice.payments.reduce(function(total, payment) {
                var value = parseFloat(payment.payment_amount);
                return total + (isNaN(value) ? 0 : value);
            }, 0);

            $("#invoice-payment-count-" + index).text(invoice.payments.length);
            $("#invoice-payment-total-" + index).text(formatMoney(paymentTotal));
        }

        function getExpandedRows() {
            var expanded = {};
            $("#invoice-payments-table .invoice-payments-collapse.in").each(function() {
                var id = $(this).attr("id");
                var index = id ? id.replace("invoice-payments-", "") : null;
                if (index !== null) {
                    expanded[index] = true;
                }
            });
            return expanded;
        }

        function restoreExpandedRows(expanded) {
            Object.keys(expanded || {}).forEach(function(index) {
                var $collapse = $("#invoice-payments-" + index);
                if ($collapse.length) {
                    $collapse.addClass("in").attr("aria-expanded", "true");
                    $collapse
                        .prev("tr")
                        .find(".invoice-toggle i")
                        .removeClass("fa-caret-right")
                        .addClass("fa-caret-down");
                }
            });
        }

        function renderTable(expandedRows) {
            var $tbody = $("#invoice-payments-table tbody");
            var expanded = expandedRows || getExpandedRows();
            $tbody.empty();

            if (!parsedInvoices.length) {
                $tbody.append(
                    '<tr><td colspan="11" class="text-muted text-center">No data parsed yet.</td></tr>'
                );
                return;
            }

            parsedInvoices.forEach(function(invoice, index) {
                var paymentTotal = invoice.payments.reduce(function(total, payment) {
                    var value = parseFloat(payment.payment_amount);
                    return total + (isNaN(value) ? 0 : value);
                }, 0);
                var collapseId = "invoice-payments-" + index;

                $tbody.append(
                    "<tr>" +
                        "<td class=\"invoice-caret-col\">" +
                        '<button type="button" class="btn btn-link invoice-toggle" data-toggle="collapse" data-target="#' +
                        collapseId +
                        '" aria-expanded="false" aria-controls="' +
                        collapseId +
                        '">' +
                        '<i class="fa fa-caret-right"></i>' +
                        "</button>" +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            invoice.date,
                            "invoice-input invoice-date datepicker",
                            'data-index="' + index + '" data-field="date"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            invoice.duedate,
                            "invoice-input invoice-duedate datepicker",
                            'data-index="' + index + '" data-field="duedate"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildSelect(
                            currencyOptions,
                            invoice.currency_id || 1,
                            "invoice-select",
                            'data-index="' + index + '" data-field="currency_id"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            formatMoney(invoice.subtotal),
                            "invoice-input numeric-input",
                            'data-index="' + index + '" data-field="subtotal"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            formatMoney(invoice.total),
                            "invoice-input numeric-input",
                            'data-index="' + index + '" data-field="total"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            formatMoney(invoice.discount_total),
                            "invoice-input numeric-input",
                            'data-index="' + index + '" data-field="discount_total"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildSelect(
                            saleAgentOptions,
                            invoice.sale_agent,
                            "invoice-select",
                            'data-index="' + index + '" data-field="sale_agent"'
                        ) +
                        "</td>" +
                        "<td class=\"invoice-payments-count\">" +
                        '<span id="invoice-payment-count-' +
                        index +
                        '">' +
                        invoice.payments.length +
                        "</span>" +
                        "</td>" +
                        "<td class=\"text-right invoice-payments-total\">" +
                        '<span id="invoice-payment-total-' +
                        index +
                        '">' +
                        formatMoney(paymentTotal) +
                        "</span>" +
                        "</td>" +
                        "<td class=\"text-nowrap invoice-actions-col\">" +
                        '<button type="button" class="btn btn-xs btn-success add-invoice" data-index="' +
                        index +
                        '"><i class="fa fa-plus"></i></button> ' +
                        '<button type="button" class="btn btn-xs btn-danger delete-invoice" data-index="' +
                        index +
                        '"><i class="fa fa-trash"></i></button>' +
                        "</td>" +
                        "</tr>"
                );

                $tbody.append(
                    '<tr class="collapse invoice-payments-collapse" id="' +
                        collapseId +
                        '">' +
                        '<td colspan="11">' +
                        '<div class="table-responsive">' +
                        '<table class="table table-bordered mtop10">' +
                        "<thead>" +
                        "<tr>" +
                        "<th>Payment Amount</th>" +
                        "<th>Payment Mode</th>" +
                        "<th>Payment Note</th>" +
                        "<th>Actions</th>" +
                        "</tr>" +
                        "</thead>" +
                        "<tbody>" +
                        "</tbody>" +
                        "</table>" +
                        "</div>" +
                        "</td>" +
                        "</tr>"
                );

                var $paymentsBody = $("#" + collapseId + " tbody");
                if (!invoice.payments.length) {
                    $paymentsBody.append(
                        '<tr><td colspan="4" class="text-muted text-center">No payment rows found for this invoice.</td></tr>'
                    );
                } else {
                    invoice.payments.forEach(function(payment, paymentIndex) {
                        $paymentsBody.append(
                            "<tr>" +
                                "<td>" +
                                buildTextInput(
                                    formatMoney(payment.payment_amount),
                                    "payment-input numeric-input",
                                    'data-index="' +
                                        index +
                                        '" data-payment-index="' +
                                        paymentIndex +
                                        '" data-field="payment_amount"'
                                ) +
                                "</td>" +
                                "<td>" +
                                buildSelect(
                                    paymentOptions,
                                    payment.paymentmode_id,
                                    "payment-select",
                                    'data-index="' +
                                        index +
                                        '" data-payment-index="' +
                                        paymentIndex +
                                        '" data-field="paymentmode_id"'
                                ) +
                                "</td>" +
                                "<td>" +
                                buildTextInput(
                                    payment.payment_note,
                                    "payment-input",
                                    'data-index="' +
                                        index +
                                        '" data-payment-index="' +
                                        paymentIndex +
                                        '" data-field="payment_note"'
                                ) +
                                "</td>" +
                                "<td class=\"text-nowrap payment-actions-col\">" +
                                '<button type="button" class="btn btn-xs btn-success add-payment" data-index="' +
                                index +
                                '" data-payment-index="' +
                                paymentIndex +
                                '"><i class="fa fa-plus"></i></button> ' +
                                '<button type="button" class="btn btn-xs btn-danger delete-payment" data-index="' +
                                index +
                                '" data-payment-index="' +
                                paymentIndex +
                                '"><i class="fa fa-trash"></i></button>' +
                                "</td>" +
                                "</tr>"
                        );
                    });
                }
            });

            appDatepicker({ element_date: ".invoice-date" });
            appDatepicker({ element_date: ".invoice-duedate" });
            restoreExpandedRows(expanded);
        }

        $("#invoice-payments-file").on("change", function(event) {
            var file = event.target.files[0];
            if (!file) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                clearAlert();
                try {
                    var data = new Uint8Array(e.target.result);
                    var workbook = XLSX.read(data, { type: "array" });
                    var sheetName = workbook.SheetNames[0];
                    var sheet = workbook.Sheets[sheetName];
                    var rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true });
                    parseSheet(rows);
                    renderTable();
                } catch (error) {
                    if (!parsedInvoices.length) {
                        showAlert(
                            "Unable to parse the Excel file. Please verify the format.",
                            "danger"
                        );
                    }
                    console.error(error);
                }
            };

            reader.readAsArrayBuffer(file);
        });

        $(document).on("input change", ".invoice-input", function() {
            var $input = $(this);
            var index = $input.data("index");
            var field = $input.data("field");
            if (parsedInvoices[index]) {
                parsedInvoices[index][field] = $input.val();
            }
        });

        $(document).on("change", ".invoice-select", function() {
            var $input = $(this);
            var index = $input.data("index");
            var field = $input.data("field");
            if (parsedInvoices[index]) {
                parsedInvoices[index][field] = $input.val();
            }
        });

        $(document).on("input change", ".payment-input", function() {
            var $input = $(this);
            var index = $input.data("index");
            var paymentIndex = $input.data("payment-index");
            var field = $input.data("field");
            if (parsedInvoices[index] && parsedInvoices[index].payments[paymentIndex]) {
                parsedInvoices[index].payments[paymentIndex][field] = $input.val();
                if (field === "payment_amount") {
                    updateInvoiceTotals(index);
                }
            }
        });

        $(document).on("change", ".payment-select", function() {
            var $input = $(this);
            var index = $input.data("index");
            var paymentIndex = $input.data("payment-index");
            var field = $input.data("field");
            if (parsedInvoices[index] && parsedInvoices[index].payments[paymentIndex]) {
                parsedInvoices[index].payments[paymentIndex][field] = $input.val();
            }
        });

        $(document).on("show.bs.collapse", ".invoice-payments-collapse", function() {
            var id = $(this).attr("id");
            if (id) {
                expandedInvoiceRows[id.replace("invoice-payments-", "")] = true;
            }
            $(this)
                .prev("tr")
                .find(".invoice-toggle i")
                .removeClass("fa-caret-right")
                .addClass("fa-caret-down");
        });

        $(document).on("hide.bs.collapse", ".invoice-payments-collapse", function() {
            var id = $(this).attr("id");
            if (id) {
                delete expandedInvoiceRows[id.replace("invoice-payments-", "")];
            }
            $(this)
                .prev("tr")
                .find(".invoice-toggle i")
                .removeClass("fa-caret-down")
                .addClass("fa-caret-right");
        });

        $(document).on("blur", ".numeric-input", function() {
            var $input = $(this);
            $input.val(formatMoney($input.val()));
            $input.trigger("change");
        });

        $(document).on("click", ".add-invoice", function() {
            var index = parseInt($(this).data("index"), 10);
            var newInvoice = {
                date: "",
                duedate: "",
                currency_id: 1,
                subtotal: "0.00",
                total: "0.00",
                discount_total: "0.00",
                sale_agent: "",
                payments: [],
            };
            parsedInvoices.splice(index + 1, 0, newInvoice);
            renderTable();
        });

        $(document).on("click", ".delete-invoice", function() {
            var index = parseInt($(this).data("index"), 10);
            parsedInvoices.splice(index, 1);
            expandedInvoiceRows = {};
            renderTable();
        });

        $(document).on("click", ".add-payment", function() {
            var index = parseInt($(this).data("index"), 10);
            var paymentIndex = parseInt($(this).data("payment-index"), 10);
            if (parsedInvoices[index]) {
                parsedInvoices[index].payments.splice(paymentIndex + 1, 0, {
                    payment_amount: "0.00",
                    paymentmode_id: "",
                    payment_note: "",
                });
                expandedInvoiceRows[index] = true;
                renderTable(expandedInvoiceRows);
            }
        });

        $(document).on("click", ".delete-payment", function() {
            var index = parseInt($(this).data("index"), 10);
            var paymentIndex = parseInt($(this).data("payment-index"), 10);
            if (parsedInvoices[index]) {
                parsedInvoices[index].payments.splice(paymentIndex, 1);
                updateInvoiceTotals(index);
                expandedInvoiceRows[index] = true;
                renderTable(expandedInvoiceRows);
            }
        });

        var importInProgress = false;
        var importBatchSize = 25;
        var $progressContainer = $("#invoice-payments-progress");
        var $progressBar = $progressContainer.find(".progress-bar");
        var $progressText = $("#invoice-payments-progress-text");

        function updateProgress(processed, total) {
            var percent = total ? Math.min(100, Math.round((processed / total) * 100)) : 0;
            $progressBar
                .css("width", percent + "%")
                .attr("aria-valuenow", percent)
                .text(percent + "%");
            $progressText.text("Processed " + processed + " of " + total + " invoice(s).");
        }

        $("#invoice-payments-submit").on("click", function() {
            if (!parsedInvoices.length) {
                showAlert("No invoices available for import.", "warning");
                return;
            }

            var $button = $(this);
            if (importInProgress) {
                return;
            }

            importInProgress = true;
            $button.prop("disabled", true);
            clearAlert();
            $progressContainer.removeClass("hide");
            $progressBar.addClass("active");
            updateProgress(0, parsedInvoices.length);

            var summary = {
                createdInvoices: 0,
                createdPayments: 0,
                errors: [],
            };

            var finalizeImport = function() {
                importInProgress = false;
                $button.prop("disabled", false);
                $progressBar.removeClass("active");

                if (summary.errors.length) {
                    showAlert(
                        "Imported " +
                            summary.createdInvoices +
                            " invoice(s) and " +
                            summary.createdPayments +
                            " payment(s) with some errors. Please review the console.",
                        "warning"
                    );
                    console.warn(summary.errors);
                } else {
                    showAlert(
                        "Imported " +
                            summary.createdInvoices +
                            " invoice(s) and " +
                            summary.createdPayments +
                            " payment(s).",
                        "success"
                    );
                }
            };

            var sendBatch = function(startIndex) {
                var batch = parsedInvoices.slice(startIndex, startIndex + importBatchSize);
                if (!batch.length) {
                    updateProgress(parsedInvoices.length, parsedInvoices.length);
                    finalizeImport();
                    return;
                }

                $.ajax({
                    url: "<?php echo admin_url('invoices/import_payments_submit'); ?>",
                    method: "POST",
                    dataType: "json",
                    data: {
                        invoices: JSON.stringify(batch),
                    },
                })
                    .done(function(response) {
                        if (response) {
                            summary.createdInvoices += response.created_invoices || 0;
                            summary.createdPayments += response.created_payments || 0;
                            if (response.errors && response.errors.length) {
                                summary.errors = summary.errors.concat(response.errors);
                            }
                        }

                        updateProgress(startIndex + batch.length, parsedInvoices.length);
                        sendBatch(startIndex + batch.length);
                    })
                    .fail(function() {
                        importInProgress = false;
                        $button.prop("disabled", false);
                        $progressBar.removeClass("active");
                        showAlert("Unable to submit invoices. Please try again.", "danger");
                    });
            };

            sendBatch(0);
        });
    });
</script>
</body>
</html>
