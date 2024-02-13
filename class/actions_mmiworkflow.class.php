<?php
/* Copyright (C) 2022-2024 Mathieu Moulin <mathieu@iprospective.fr>
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
 * \file    mmiworkflow/class/actions_mmiworkflow.class.php
 * \ingroup mmiworkflow
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
dol_include_once('custom/mmicommon/class/mmi_actions.class.php');
dol_include_once('custom/mmiworkflow/class/mmi_workflow.class.php');


class ActionsMMIWorkflow extends MMI_Actions_1_0
{
	const MOD_NAME = 'mmiworkflow';

	/**
	 * Overloading the addMoreMassActions function : replacing the parent's function with the one below
	 *
	 * @param   array           $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		// Fiche Commande
		if ($this->in_context($parameters, 'ordercard')) {
			/** @var Commande $object */
			// Blocage validation si ligne libre avec produit (pas grave en service)
			if (!empty($conf->global->SFYCUSTOM_LOCK)) {
				$blockValid='';
				foreach($object->lines as $line) {
					if (empty($line->fk_product) && $line->product_type == 0 && $line->qty>0) {
						$blockValid = $line->description;
						break;
					}
				}

				if (!empty($blockValid)) {
					print '
					<script type="text/javascript">
						$(document).ready(function() {
							$(\'a.butAction[href*="action=validate"\').removeClass(\'butAction\')
							.addClass(\'butActionRefused\').attr(\'href\',\'#\').text(\''.$langs->transnoentities('SfCstCannotValidWithFreeLine').'\')
							.attr(\'title\',\''.dol_string_nohtmltag($blockValid).'\');
						})
					</script>';
				}
			}
			// Ajout action Facture & Expé en 1 Clic
			// SSI statut commande = validé
			//var_dump($object);
			if (!empty($conf->global->MMI_ORDER_1CLIC_INVOICE_SHIPPING) && (int)$object->status===Commande::STATUS_VALIDATED) {
				$link = '?id='.$object->id.'&action=1clic_invoice_shipping';
				// Test si déjà facture
				// Test si déjà expédition

				echo "<a class='butAction' href='".$link."' onclick='return confirm(\"".addslashes($langs->trans("MMI1ClickOrderInvoiceShippingConfirm"))."\")'>".$langs->trans("MMI1ClickOrderInvoiceShipping")."</a>";
			}
			// Bouton remise en brouillon
			if (!empty($conf->global->MMI_ORDER_DRAFTIFY) && $user->rights->mmiworkflow->commande->draftify) {
				$q = $this->db->query("SELECT 1
					FROM ".MAIN_DB_PREFIX."commande c
					WHERE c.rowid=".$object->id." AND c.fk_statut > 0");
				//var_dump($q);
				if ($q->num_rows) {
					$link = '?id='.$object->id.'&action=draftify';
					echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIInvoiceDraftify")."</a>";
				}
			}
			// Fix bug 1ct Presta & co
			if ($conf->global->MMI_1CT_FIX) {
				$link = '?id='.$object->id.'&action=1ct_fix';
				echo "<a class='butAction' href='".$link."'>".$langs->trans("MMI1ctFix")."</a>";
			}
			// Fix bug TVA Presta & co
			if ($conf->global->MMI_VAT_TX_FIX) {
				$link = '?id='.$object->id.'&action=vat_tx_fix';
				echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIVATTxFix")."</a>";
			}
		}
		// Fiche Facture
		elseif ($this->in_context($parameters, 'invoicecard')) {
			/** @var Facture $object */
			$q = $this->db->query("SELECT 1
				FROM ".MAIN_DB_PREFIX."facture i
				LEFT JOIN ".MAIN_DB_PREFIX."accounting_bookkeeping j ON j.doc_type='customer_invoice' AND j.fk_doc=i.rowid
				WHERE i.rowid=".$object->id." AND i.fk_statut > 0 AND j.rowid IS NULL");
			//var_dump($q);
			$nocompta = ($q->num_rows);

			// Bouton remise en brouillon
			if ($conf->global->MMI_INVOICE_DRAFTIFY && $user->rights->mmiworkflow->facture->draftify) {
				if (!isset($conf->global->MMI_INVOICE_DRAFTIFY_TYPES) || $conf->global->MMI_INVOICE_DRAFTIFY_TYPES=='' || in_array($object->type, explode(',', $conf->global->MMI_INVOICE_DRAFTIFY_TYPES))) {
					if ($nocompta) {
						$link = '?facid='.$object->id.'&action=draftify';
						echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIInvoiceDraftify")."</a>";
					}
					else {
						echo '<a class="butActionRefused" title="'.$langs->trans('DisabledBecauseDispatchedInBookkeeping').'" href="javascript:;">'.$langs->trans("MMIInvoiceDraftify").'</a>';
					}
				}
				else {
					echo '<a class="butActionRefused" title="'.$langs->trans('NotAllowed').'" href="javascript:;">'.$langs->trans("MMIInvoiceDraftify").'</a>';
				}
			}
			// Fix bug 1ct Presta & co
			if ($conf->global->MMI_1CT_FIX) {
				if ($nocompta) {
					$link = '?facid='.$object->id.'&action=1ct_fix';
					echo "<a class='butAction' href='".$link."'>".$langs->trans("MMI1ctFix")."</a>";
				}
				else {
					echo '<a class="butActionRefused" title="'.$langs->trans('DisabledBecauseDispatchedInBookkeeping').'" href="javascript:;">'.$langs->trans("MMI1ctFix")."</a>";
				}
			}
			// Fix bug TVA Presta & co
			if ($conf->global->MMI_VAT_TX_FIX) {
				$link = '?id='.$object->id.'&action=vat_tx_fix';
				echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIVATTxFix")."</a>";
			}
			// Envoi facture par email
			if ($conf->global->MMI_INVOICE_EMAILSEND) {
				$link = '?facid='.$object->id.'&action=email_send';
				echo "<a class='butAction' href='".$link."'>".$langs->trans("MMIInvoiceEmailSend")."</a>";
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		// Commande
		if ($this->in_context($parameters, 'ordercard')) {
			// 1 click invoice shipping
			if ($action === '1clic_invoice_shipping') {
				if ($conf->global->MMI_ORDER_1CLIC_INVOICE_SHIPPING) {
					mmi_workflow::order_1clic_invoice_shipping($user, $object);
				}
			}
			// Indraft again
			if ($action === 'draftify') {
				if ($conf->global->MMI_ORDER_DRAFTIFY && $user->rights->mmiworkflow->commande->draftify) {
					// @todo check pas envoyée au client !
					$sql = "UPDATE ".MAIN_DB_PREFIX."commande c
						SET c.fk_statut=0
						WHERE c.rowid=".$object->id;
					//var_dump($object);
					//die($sql);
					$this->db->query($sql);
				}
			}
			// Fix bug 1ct Presta & co
			if ($action === '1ct_fix') {
				if ($conf->global->MMI_1CT_FIX) {
					mmi_workflow::order_1ctfix($user, $object);
				}
			}
			// Fix bug 1ct Presta & co
			if ($action === '1ct_fix') {
				if ($conf->global->MMI_1CT_FIX) {
					mmi_workflow::order_1ctfix($user, $object);
				}
			}
			// Fix bug TVA Presta & co
			if ($action === 'vat_tx_fix') {
				if ($conf->global->MMI_VAT_TX_FIX) {
					mmi_workflow::order_vat_tx_fix($user, $object);
				}
			}
		}
		// Invoice
		elseif ($this->in_context($parameters, 'invoicecard')) {
			// Indraft again
			if ($action === 'draftify') {
				if ($conf->global->MMI_INVOICE_DRAFTIFY && $user->rights->mmiworkflow->facture->draftify) {
					// @todo check pas envoyée au client !
					mmi_workflow::invoice_draftify($user, $object);
				}
			}
			// Email send
			if ($action === 'email_send') {
				if ($conf->global->MMI_INVOICE_EMAILSEND) {
					mmi_workflow::invoice_email($user, $object);
				}
			}
			// Fix bug 1ct Presta & co
			if ($action === '1ct_fix') {
				if ($conf->global->MMI_1CT_FIX) {
					mmi_workflow::invoice_1ctfix($user, $object);
				}
			}
			// Fix bug TVA Presta & co
			if ($action === 'vat_tx_fix') {
				if ($conf->global->MMI_VAT_TX_FIX) {
					mmi_workflow::invoice_vat_tx_fix($user, $object);
				}
			}
		}
		// Shippement
		if ($this->in_context($parameters, 'shipmentlist') && !empty($conf->global->SFY_ALERT_ORDER_NOT_SHIPPED)) {
			$order = new Commande($this->db);
			$sql="SELECT count(rowid) as cnt FROM ".MAIN_DB_PREFIX."commande where fk_statut=".$order::STATUS_VALIDATED;
			$resql = $this->db->query($sql);
			if (!$resql) {
				setEventMessage($this->db->error,'errors');
			} else {
				$obj=$this->db->fetch_object($resql);
				if ($obj->cnt>0) {
					$urlList = dol_buildpath('/commande/list.php',2).'?search_status=1';
					$urlList='<a href="'.$urlList.'">'.$langs->trans('List').'</a>';
					setEventMessage($langs->transnoentities('OrderInValidatedStatusAlert',$urlList),'warnings');
				}
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	function beforePDFCreation($parameters, &$object, &$action='', $hookmanager=NULL)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		if (!is_object($object))
			return -1;

		$object_type = get_class($object);
		//var_dump($parameters); die();
		//var_dump($this->in_context($parameters, 'pdfgeneration'), $object_type);
		if ($this->in_context($parameters, 'pdfgeneration') && $object_type=='Expedition') {
			// Sort by Product Ref
			// -> directement dans le coeur ça merde l'association avec les images sinon
			//usort($object->lines, function($i, $j){
			//	return ($i->ref > $j->ref) ?1 :(($i->ref < $j->ref) ?-1 :0);
			//});
			// Bonbons
			if (!empty($conf->global->MMI_SHIPPING_PDF_MESSAGE)) {
				// Shipping message
				if (!empty($conf->global->MMI_SHIPPING_PDF_MESSAGE))
					$object->note_public .= (!empty($object->note_public) ?'<br />' :'').$conf->global->MMI_SHIPPING_PDF_MESSAGE.'<br />';
			}
		}

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	function pdf_writelinedesc($parameters, &$object, &$action='', $hookmanager=NULL)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		if (!is_object($object))
			return -1;

		$object_type = get_class($object);
		//var_dump($object_type); die();

		if ($this->in_context($parameters, 'pdfgeneration') && $object_type=='Expedition') {
			$i = $parameters['i'];
			//$object = $parameters['object'];
			$object->lines[$i]->ref = '<b>'.$object->lines[$i]->ref.'</b>';
			$object->lines[$i]->product_ref = '<b>'.$object->lines[$i]->product_ref.'</b>';
			$object->lines[$i]->label = '<b>'.$object->lines[$i]->label.'</b>';
			//var_dump($object->lines[$i]->label); die();
			//var_dump($object->lines[$i]); die();
			//var_dump('yo'); die();
			//$this->resPrint = '<b>'.$object->lines[$i]->ref.'</b>';
		}

		//$this->resprints = ' - TOTO';

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}

	function pdf_build_address($parameters, &$object, &$action='', $hookmanager=NULL)
	{
		global $conf, $user, $langs;

		$error = 0; // Error counter
		$disabled = 1;

		if (!is_object($object))
			return -1;

		$object_type = get_class($object);

		if ($this->in_context($parameters, 'pdfgeneration') && $object_type=='Expedition' && $parameters['mode']=='targetwithdetails') {
			$arrayidcontact = $object->commande->getIdContact('external', 'SHIPPING');
			if (count($arrayidcontact) > 0) {
				$object->fetch_contact($arrayidcontact[0]);
				if (!empty($object->contact->array_options['options_p_company']))
					$this->resprints = $object->contact->array_options['options_p_company'];
			}
		}

		//$this->resprints = ' - TOTO';

		if (!$error) {
			return 0; // or return 1 to replace standard code
		} else {
			$this->errors[] = 'Error message';
			return -1;
		}
	}
}

ActionsMMIWorkflow::__init();

