# CHANGELOG MMIWORKFLOW FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0

Initial version

## 1.1

Retrieve API to automatically create shipping with the right batches.

## 1.1.1

Organize setup parameters.

## 1.2.0

ORDER_VALIDATE trigger: when re-validating an order that was reopened from "shipment in progress" status, automatically restore it to "shipment in progress". Then, if everything is already shipped, classify it as "shipped" according to native workflow settings (`WORKFLOW_ORDER_CLASSIFY_SHIPPED_SHIPPING`, `WORKFLOW_ORDER_CLASSIFY_SHIPPED_SHIPPING_CLOSED`).

