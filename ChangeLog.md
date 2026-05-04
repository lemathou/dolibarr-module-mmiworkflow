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
- Module descriptor: fix `description` / `descriptionlong` to point at the `ModuleMMIWorkflowDesc` translation key instead of the literal placeholder, and fix typo (`aléliorations` → `améliorations`) in fr_FR / en_US lang files.
- Lang: drop modulebuilder boilerplate keys (`MMIWORKFLOW_MYPARAM*`, `MyPageName`, `MyWidget`, `MyWidgetDescription`) that were never referenced.
- Lang en_US: actually translate the file to English (was a French copy of fr_FR with only the admin/about scaffolding in English).
- Module descriptor: set `module_parts['substitutions']` to 0 (no `core/substitutions/` directory ships with the module).
- Permissions: switch hardcoded French permission labels to translation keys (`MMIWorkflowPermissionOrderDraftify`, `MMIWorkflowPermissionInvoiceDraftify`) added in fr_FR and en_US.
- Permissions: fix label of `MMIWorkflowPermissionInvoiceDraftify` (invoices are paid, not shipped).
- Codebase: replace direct `$conf->global->XXX` reads by `getDolGlobalString()` / `getDolGlobalInt()` (best practice on Dolibarr ≥ 16). Touches `actions_mmiworkflow.class.php`, `mmi_workflow.class.php`, the workflow trigger and `mmiworkflowindex.php`. The eval string passed to `addExtraField` is left untouched intentionally.
- Settings: rename `MMI_MOVEMENT_LIST_ENHANCE` to `MMI_CORE_MOVEMENT_LIST_ENHANCE` to make it explicit that this is a hidden core option (read in `htdocs/product/stock/class/mouvementstock.class.php`). Requires the matching parent rename to ship together; existing installations need to manually migrate the constant value in `llx_const`.

