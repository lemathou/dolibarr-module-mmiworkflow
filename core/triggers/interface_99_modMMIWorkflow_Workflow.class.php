<?php
/* Copyright (C) 2021-2022		Mathieu Moulin		<contact@iprospective.fr>
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

/**
 * \file    htdocs/core/triggers/interface_99_all_ERPSync.class.php
 * \ingroup MMIPrestaSync
 * \brief   Trigger for Synchronisation with Prestashop.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('custom/mmiworkflow/class/mmi_workflow.class.php');

/**
 *  Class of triggers for MyModule module
 */
class InterfaceWorkflow extends DolibarrTriggers
{
	public static function __init()
	{
	}

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		//die('MODULE BUILDER OK');
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "demo";
		$this->description = "MMIWorkflow triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'logo@mmiworkflow';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}

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
		//var_dump($action); var_dump($object); die();
		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		//var_dump($action); echo '<p>'.$methodName.'</p>'; //die();
		//die();
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		};
		
		//var_dump('<p>MMI_DEBUG 	ACTION: '.$action); die();

		// Or you can execute some code here
		switch ($action) {
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

			// and more...

			default:
				dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
				break;
		}

		return 0;
	}
}

InterfaceWorkflow::__init();
