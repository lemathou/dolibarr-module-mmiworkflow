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

