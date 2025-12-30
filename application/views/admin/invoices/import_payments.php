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
                                        <td colspan="10" class="text-muted text-center">
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

<div class="modal fade" id="invoice-payments-modal" tabindex="-1" role="dialog" aria-labelledby="invoicePaymentsLabel">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="invoicePaymentsLabel">Payment Records</h4>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="invoice-payments-modal-table">
                        <thead>
                            <tr>
                                <th>Payment Amount</th>
                                <th>Payment Mode</th>
                                <th>Payment Note</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
    $(function() {
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

        function isInvoiceRow(rowData) {
            return invoiceHeaders.some(function(header) {
                return !isEmpty(rowData[header]);
            });
        }

        function isPaymentRow(rowData) {
            return paymentHeaders.some(function(header) {
                return !isEmpty(rowData[header]);
            });
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

                var hasInvoice = isInvoiceRow(rowData);
                var hasPayment = isPaymentRow(rowData);

                if (hasInvoice) {
                    var formattedNumber = rowData.formatted_number || "";
                    var invoiceKey = formattedNumber !== "" ? String(formattedNumber) : "row-" + i;
                    var invoice = {
                        formatted_number: formattedNumber,
                        date: rowData.date || "",
                        duedate: rowData.duedate || "",
                        currency_id: rowData.currency_id || "",
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
                    "Parsed " + parsedInvoices.length + " invoice(s). Click a row to view payments.",
                    "success"
                );
            }
        }

        function renderTable() {
            var $tbody = $("#invoice-payments-table tbody");
            $tbody.empty();

            if (!parsedInvoices.length) {
                $tbody.append(
                    '<tr><td colspan="10" class="text-muted text-center">No data parsed yet.</td></tr>'
                );
                return;
            }

            parsedInvoices.forEach(function(invoice, index) {
                var paymentTotal = invoice.payments.reduce(function(total, payment) {
                    var value = parseFloat(payment.payment_amount);
                    return total + (isNaN(value) ? 0 : value);
                }, 0);

                $tbody.append(
                    "<tr class=\"invoice-payments-row\" data-index=\"" +
                        index +
                        "\">" +
                        "<td>" +
                        (invoice.formatted_number || "-") +
                        "</td>" +
                        "<td>" +
                        (invoice.date || "-") +
                        "</td>" +
                        "<td>" +
                        (invoice.duedate || "-") +
                        "</td>" +
                        "<td>" +
                        (invoice.currency_id || "-") +
                        "</td>" +
                        "<td>" +
                        (invoice.subtotal || "-") +
                        "</td>" +
                        "<td>" +
                        (invoice.total || "-") +
                        "</td>" +
                        "<td>" +
                        (invoice.discount_total || "-") +
                        "</td>" +
                        "<td>" +
                        (invoice.sale_agent || "-") +
                        "</td>" +
                        "<td>" +
                        invoice.payments.length +
                        "</td>" +
                        "<td>" +
                        (paymentTotal ? paymentTotal.toFixed(2) : "-") +
                        "</td>" +
                        "</tr>"
                );
            });
        }

        function renderPaymentsModal(invoice) {
            var $tbody = $("#invoice-payments-modal-table tbody");
            $tbody.empty();

            if (!invoice.payments.length) {
                $tbody.append(
                    '<tr><td colspan="3" class="text-center text-muted">No payment rows found for this invoice.</td></tr>'
                );
            } else {
                invoice.payments.forEach(function(payment) {
                    $tbody.append(
                        "<tr>" +
                            "<td>" +
                            (payment.payment_amount || "-") +
                            "</td>" +
                            "<td>" +
                            (payment.paymentmode_id || "-") +
                            "</td>" +
                            "<td>" +
                            (payment.payment_note || "-") +
                            "</td>" +
                            "</tr>"
                    );
                });
            }

            $("#invoice-payments-modal").modal("show");
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

        $(document).on("click", ".invoice-payments-row", function() {
            var index = $(this).data("index");
            var invoice = parsedInvoices[index];
            if (invoice) {
                renderPaymentsModal(invoice);
            }
        });
    });
</script>
</body>
</html>
