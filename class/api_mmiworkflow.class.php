<?php

use Luracast\Restler\RestException;

dol_include_once('custom/mmicommon/class/mmi_prestasyncapi.class.php');
dol_include_once('custom/mmiworkflow/class/mmi_workflow.class.php');

class MMIWorkflowApi extends MMI_PrestasyncApi_1_0
{
	protected $commande;
	
	function __construct()
	{
		parent::__construct();

		require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
		global $db;

		$this->commande = new Commande($db);
	}

	/**
	 * Send commande
	 *
	 * @param int   $id             Id of commande to send
	 * @param array $lots   Lots
	 * @return int
	 *
	 * @url GET commande_expe/{id}
	 */
	function expe($id, $lots=[])
	{
		global $user;
		
		if (getDolGlobalInt('MMI_ORDER_1CLIC_API_SHIPPING')) {
			dol_syslog("mmiworkflow::commande_expe() : ID#".$id." / ".count($lots).' lots / détail : '.json_encode($lots), LOG_NOTICE);
			static::_getsynchrouser();

			$this->commande->fetch($id);
			if ($this->commande->id) {
				$shipping = mmi_workflow::order_1clic_shipping($user, $this->commande, true, $lots);
				//var_dump($shipping);
				return 1;
			}
			else {
				return 0;
			}
		}
		else {
			dol_syslog("mmiworkflow::commande_expe() : API Shipping disabled", LOG_WARNING);
			return 0;
		}
	}
}

