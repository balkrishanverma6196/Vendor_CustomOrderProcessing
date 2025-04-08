# Vendor_CustomOrderProcessing

## Installation
1. Copy the module to `app/code/Vendor/CustomOrderProcessing`
2. Run the following commands:

```bash
bin/magento module:enable Vendor_CustomOrderProcessing
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush

## API Endpoint:
Endpoint: POST /rest/V1/order/status/update

## Payload Example:

{
  "increment_id": "100000001",
  "status": "processing"
}



## Architectural Decisions Explanation

Service Class for Shipment Logic:The shipment creation logic is encapsulated in a dedicated service class method (createShipment($order)) to follow the Single Responsibility Principle and keep controllers/observers lightweight and focused.

Use of Magento Factories:We use Magento’s built-in ShipmentFactory and ShipmentItemFactory to ensure compatibility with Magento’s object management and dependency injection system. This avoids manual instantiation and supports unit testing.

Validation of Shippable Items:Before registering the shipment, we explicitly check whether there are any shippable items (qty_to_ship > 0 and not virtual). This prevents creation of "empty" shipments and avoids Magento errors like "We cannot create an empty shipment."

Granular Error Logging:All critical operations (item filtering, shipment registration, save) are wrapped with logging to provide clear traceability in production. This helps quickly diagnose edge cases where Magento’s built-in checks are misleading (e.g., canShip() == true but no items are shippable).

Safe Persistence via Try-Catch:Shipment and order saving is wrapped in a try-catch block to avoid transaction failures and to handle unexpected issues gracefully, ensuring robust error handling.

Avoiding Transaction Overhead:Instead of relying on Magento\Framework\DB\Transaction, the shipment and order are saved individually. This simplifies error tracing and avoids issues like "Rolled back transaction has not been completed correctly" that arise from partially saved entities.

