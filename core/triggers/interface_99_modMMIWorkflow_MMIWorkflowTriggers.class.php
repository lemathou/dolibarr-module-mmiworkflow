<?php
/* Copyright (C) 2022 Mathieu Moulin iProspective <contact@iprospective.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

include_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';

dol_include_once('custom/mmicommon/core/triggers/MMITriggers.class.php');
dol_include_once('/custom/mmiworkflow/class/mmi_workflow.class.php');

/**
 *  Class of triggers for MMIWorkflow module
 */
class InterfaceMMIWorkflowTriggers extends MMITriggers
{
	const MOD_NAME = 'mmiworkflow';
	const DESC = 'MMIWorkflow triggers';

	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (! $this->enabled()) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		};

		// Or you can execute some code here
		switch ($action) {

			case 'ORDER_CLOSE':
				if ($conf->global->MMI_ORDER_1CLIC_INVOICE && $conf->global->MMI_ORDER_1CLIC_INVOICE_DELAY) {
					mmi_workflow::order_1clic_invoice($user, $object);
				}
				break;
			
			case 'ORDER_VALIDATE':
				// If we have a shipping, we set the status to "sending"
				$object->fk_status = Commande::STATUS_SHIPMENTONPROCESS;

				break;

			// Reception
			case 'RECEPTION_VALIDATE':
			case 'RECEPTION_DELETE':
			case 'RECEPTION_MODIFY':
				//var_dump($object); //die();
				if (!in_array($object->origin, ['commandeFournisseur', 'order_supplier']) || empty($object->origin_id))
					break;
				mmi_workflow::commande_four_reception($object->origin_id);
				break;
				
			// Supplier orders
			case 'ORDER_SUPPLIER_MODIFY':
			case 'ORDER_SUPPLIER_VALIDATE':
			case 'ORDER_SUPPLIER_APPROVE':
			case 'ORDER_SUPPLIER_DISPATCH':
			case 'ORDER_SUPPLIER_RECEIVE':
				//var_dump($object); die();
				mmi_workflow::commande_four_reception($object->id);
				break;

			// Shipping
			case 'SHIPPING_MODIFY':
			case 'SHIPPING_VALIDATE':
			case 'SHIPPING_BILLED':
			case 'SHIPPING_CLOSED':
			case 'SHIPPING_REOPEN':
				if (!in_array($object->origin, ['commande', 'order']) || empty($object->origin_id))
					break;
				
				mmi_workflow::commande_expedition($object->origin_id);
				break;
			case 'SHIPPING_DELETE':
				if (!in_array($object->origin, ['commande', 'order']) || empty($object->origin_id))
					break;
				
				mmi_workflow::commande_expedition($object->origin_id);
				break;

			default:
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
			
		}

		return 0;
	}
}
