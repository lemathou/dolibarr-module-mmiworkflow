# CHANGELOG MMIWORKFLOW FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.0

Initial version

## 1.1

Retrieve API to automatically create shipping with the right batches.

## 1.1.1

Organize setup parameters.

## 1.2.0

ORDER_VALIDATE trigger: when re-validating an order that was reopened from "shipment in progress" status, automatically restore it to "shipment in progress". Then, if everything is already shipped, classify it as "shipped" according to native workflow settings (`WORKFLOW_ORDER_CLASSIFY_SHIPPED_SHIPPING`, `WORKFLOW_ORDER_CLASSIFY_SHIPPED_SHIPPING_CLOSED`).

## Unreleased

- Cleanup: remove duplicated `1ct_fix` action handler block in `actions_mmiworkflow.class.php`.
- Triggers: consolidate `SHIPPING_*` cases in MMIWorkflowTriggers, restrict `expe_ok` recomputation to events that actually change shipped quantities (`CREATE`, `MODIFY`, `VALIDATE`, `DELETE`); drop `BILLED`, `CLOSED`, `REOPEN` which had no effect on quantities.
- Lang fr_FR: remove duplicated `MMI1ctFix` translation key.

