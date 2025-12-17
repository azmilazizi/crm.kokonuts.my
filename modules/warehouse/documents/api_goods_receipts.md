# Goods receipt REST payloads

## Creating a goods receipt with automatic lot numbers

The `/admin/warehouse/api/goods_receipts` endpoint expects a JSON body with top-level receipt info and an `items` array. Each item must include `commodity_code`, `warehouse_id`, `quantity`, and `unit_price`; the controller also accepts optional fields such as `unit_id`, `note`, `serial_number`, `date_manufacture`, `expiry_date`, and tax identifiers via `tax_ids`/`taxes`/`tax_select`. Leave the `lot_number` field out or set it to `null`/`""` to have it auto-generated when the `auto_generate_lotnumber` option is enabled.

```json
{
  "date_c": "2025-12-06",
  "date_add": "2025-12-06",
  "supplier_name": "ACME Supplies",
  "supplier_code": "SUP-001",
  "buyer_id": 7,
  "purchase_order_id": 42,
  "description": "Inbound shipment from PO 42",
  "items": [
    {
      "commodity_code": 101,
      "warehouse_id": 3,
      "quantity": 10,
      "unit_price": 15.5,
      "tax_ids": [1],
      "unit_id": 2,
      "note": "Batch for QA",
      "serial_number": "SN-XYZ-1001",
      "date_manufacture": "2025-11-20",
      "expiry_date": "2026-11-20",
      "lot_number": ""
    }
  ]
}
```

With the configuration flag turned on, the server fills in `lot_number` for each item where the provided value is empty. If `auto_generate_lotnumber` is disabled, the `lot_number` remains `null` and must be supplied explicitly.
