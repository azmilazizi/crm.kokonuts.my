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
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="invoice-payments-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Invoice #</th>
                                        <th>Date</th>
                                        <th>Due Date</th>
                                        <th>Currency</th>
                                        <th>Subtotal</th>
                                        <th>Total</th>
                                        <th>Discount</th>
                                        <th>Sale Agent</th>
                                        <th>Payments</th>
                                        <th>Payment Total</th>
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    $(function() {
        var warehouseOptions = <?php echo json_encode($warehouses ?? []); ?>;
        var paymentModeOptions = <?php echo json_encode($payment_modes ?? []); ?>;
        var currencyOptions = [{ value: 1, label: "RM" }];
        var requiredHeaders = [
            "formatted_number",
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
            "formatted_number",
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
                return;
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
                    var formattedNumber = rowData.formatted_number || "";
                    var invoiceKey = formattedNumber !== "" ? String(formattedNumber) : "row-" + i;
                    var invoice = {
                        formatted_number: formattedNumber,
                        date: rowData.date || "",
                        duedate: rowData.duedate || "",
                        currency_id: rowData.currency_id || 1,
                        subtotal: rowData.subtotal || "",
                        total: rowData.total || "",
                        discount_total: rowData.discount_total || "",
                        sale_agent: rowData.sale_agent || "",
                        payments: [],
                    };

                    parsedInvoices.push(invoice);
                    invoiceByNumber[invoiceKey] = invoice;
                    lastInvoiceKey = invoiceKey;

                    if (hasPayment) {
                        invoice.payments.push({
                            payment_amount: rowData.payment_amount || "",
                            paymentmode_id: rowData.paymentmode_id || "",
                            payment_note: rowData.payment_note || "",
                        });
                    }

                    continue;
                }

                if (hasPayment && lastInvoiceKey && invoiceByNumber[lastInvoiceKey]) {
                    invoiceByNumber[lastInvoiceKey].payments.push({
                        payment_amount: rowData.payment_amount || "",
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

            $("#invoice-payment-count-" + index).val(invoice.payments.length);
            $("#invoice-payment-total-" + index).val(
                paymentTotal ? paymentTotal.toFixed(2) : ""
            );
        }

        function renderTable() {
            var $tbody = $("#invoice-payments-table tbody");
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
                        "<td class=\"tw-w-8\">" +
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
                            invoice.formatted_number,
                            "invoice-input",
                            'data-index="' + index + '" data-field="formatted_number"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            invoice.date,
                            "invoice-input invoice-date",
                            'data-index="' + index + '" data-field="date"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            invoice.duedate,
                            "invoice-input invoice-duedate",
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
                            invoice.subtotal,
                            "invoice-input",
                            'data-index="' + index + '" data-field="subtotal"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            invoice.total,
                            "invoice-input",
                            'data-index="' + index + '" data-field="total"'
                        ) +
                        "</td>" +
                        "<td>" +
                        buildTextInput(
                            invoice.discount_total,
                            "invoice-input",
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
                        "<td>" +
                        '<input type="text" class="form-control" readonly id="invoice-payment-count-' +
                        index +
                        '" value="' +
                        invoice.payments.length +
                        '">' +
                        "</td>" +
                        "<td>" +
                        '<input type="text" class="form-control" readonly id="invoice-payment-total-' +
                        index +
                        '" value="' +
                        (paymentTotal ? paymentTotal.toFixed(2) : "") +
                        '">' +
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
                        '<tr><td colspan="3" class="text-muted text-center">No payment rows found for this invoice.</td></tr>'
                    );
                } else {
                    invoice.payments.forEach(function(payment, paymentIndex) {
                        $paymentsBody.append(
                            "<tr>" +
                                "<td>" +
                                buildTextInput(
                                    payment.payment_amount,
                                    "payment-input",
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
                                "</tr>"
                        );
                    });
                }
            });

            appDatepicker({ element_date: ".invoice-date" });
            appDatepicker({ element_date: ".invoice-duedate" });
        }

        $("#invoice-payments-file").on("change", function(event) {
            var file = event.target.files[0];
            if (!file) {
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                try {
                    var data = new Uint8Array(e.target.result);
                    var workbook = XLSX.read(data, { type: "array" });
                    var sheetName = workbook.SheetNames[0];
                    var sheet = workbook.Sheets[sheetName];
                    var rows = XLSX.utils.sheet_to_json(sheet, { header: 1, raw: true });
                    parseSheet(rows);
                    renderTable();
                } catch (error) {
                    showAlert("Unable to parse the Excel file. Please verify the format.", "danger");
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
            $(this)
                .prev("tr")
                .find(".invoice-toggle i")
                .removeClass("fa-caret-right")
                .addClass("fa-caret-down");
        });

        $(document).on("hide.bs.collapse", ".invoice-payments-collapse", function() {
            $(this)
                .prev("tr")
                .find(".invoice-toggle i")
                .removeClass("fa-caret-down")
                .addClass("fa-caret-right");
        });
    });
</script>
</body>
</html>
