<?php

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
//require_once DOL_DOCUMENT_ROOT.'/expedition/class/expeditionbatch.class.php';

dol_include_once('custom/mmicommon/class/mmi_generic.class.php');
// @todo check if module
dol_include_once('/custom/mmipayments/class/mmi_payments.class.php');

class mmi_workflow extends mmi_generic_1_0
{
	const MOD_NAME = 'mmiworkflow';

	public static function order_1clic_invoice($user, $order, $validate=true)
	{
		if (! $user->rights->facture->creer)
			return;
		
		//var_dump($object);
		global  $conf, $langs, $db, $soc;

		$invoice = new Facture($db);
		$invoice_gen = true;

		// Vérif si pas déjà une facture !
		$order->fetchObjectLinked();
		//var_dump($order->linkedObjectsIds); die();
		if(!empty($order->linkedObjectsIds['facture'])) {
			foreach($order->linkedObjectsIds['facture'] as $id) {
				$invoice->fetch($id);
				break;
			}
			// @todo check if already
			//$invoice_gen = false;
		}
		else {
			$invoice->createFromOrder($order, $user);
			// Validation
			if ($validate)
				$invoice->validate($user);
			// Assign payments
			// @todo c'est degue je dois tester l'activation du module !!
			if (class_exists('mmi_payments')) {
				mmi_payments::invoice_autoassign_payments($invoice);
			}
			// Retrieve everything again
			$invoice->fetch($invoice->id);
		}

		// Fix bug
		static::invoice_1ctfix($user, $invoice);
		static::order_1ctfix($user, $order);

		// Parfois ça ne classe pas correctement si on a un écart de centime par exemple
		if (!$order->billed)
			$order->classifyBilled($user); // On est sur du 1clic !

		// Génération PDF
		if ($invoice_gen) {
			// @todo : dégueu
			if (file_exists($filename=DOL_DATA_ROOT.'/doctemplates/invoices/CALICOTE_FACTURE.odt'))
				$docmodel = 'generic_invoice_odt:'.$filename;
			elseif (file_exists($filename=DOL_DATA_ROOT.'/doctemplates/invoices/PISCEEN_FACTURE.odt'))
				$docmodel = 'generic_invoice_odt:'.$filename;
			else
				$docmodel = $invoice->model_pdf;
			$hidedetails = 0;
			$hidedesc = 0;
			$hideref = 0;
			$moreparams = null;
			$invoice->generateDocument($docmodel, $langs, $hidedetails, $hidedesc, $hideref, $moreparams);
		}

		// Send invoice by email if Option true, not already sent and invoice validated correctly (closes & paid) !
		// STOP if not auto send
		if (empty($conf->global->MMI_ORDER_1CLIC_INVOICE_EMAIL_AUTO))
			return;

		// Customer
		$thirdparty = $invoice->thirdparty;

		// if specified not to send auto (thirdparty option)
		// OR specified not to send auto to pro (module option + thirdparty check pro field)
		if (
			!empty($thirdparty->array_options['options_invoice_noautosend'])
			|| (!empty($conf->global->MMI_ORDER_1CLIC_INVOICE_EMAIL_AUTO_NOPRO) && !empty($thirdparty->array_options['options_pro']))
			)
			return;

		// Check already sent
		$sql = 'SELECT 1
			FROM '.MAIN_DB_PREFIX.'actioncomm a
			WHERE a.code="AC_BILL_SENTBYMAIL" AND a.elementtype="invoice" AND a.fk_element='.$invoice->id.'
			LIMIT 1';
		//echo $sql;
		$q = $db->query($sql);
		//var_dump($q); //die();
		$invoice_sent = $q->num_rows>0;
		//var_dump($invoice_sent);
		if ($invoice_sent)
			return;

		// Check closed (sent), and payed if not professionnal
		$validated = (empty($thirdparty->array_options['options_pro']) && $invoice->statut == Facture::STATUS_CLOSED)
			|| (!empty($thirdparty->array_options['options_pro']) && in_array($invoice->statut, [Facture::STATUS_VALIDATED, Facture::STATUS_CLOSED]));
		//var_dump($validated, $invoice); die();
		if (!$validated)
			return;

		static::invoice_email($user, $invoice);

		return true;
	}

	public static function order_1ctfix($user, $object, $autovalidate=true)
	{
		global $conf, $langs, $db, $soc, $hookmanager;

		$sql = 'SELECT SUM(ip.amount) paid
			FROM '.MAIN_DB_PREFIX.'paiement_object ip
			WHERE ip.fk_object='.$object->id;
		$q2 = $db->query($sql);
		if (!$q2 || !($row = $q2->fetch_object()))
			return;
		//var_dump($row->paid);

		$difflimit = !empty($conf->global->MMI_1CT_DIFFLIMIT) ?$conf->global->MMI_1CT_DIFFLIMIT :0.03;
		$diff = $object->total_ttc-$row->paid;
		$diffround = round($diff, 2);
		$diffabs = abs($diffround);
		//var_dump($diffabs); die();

		// Marge de tolérance
		if ($diffabs == 0 || $diffabs > $difflimit)
			return;
		
		$statut = $object->statut;
		if ($statut>0)
			$object->setDraft($user);
		foreach($object->lines as $line) if (($line->subprice > 0 || $line->subprice < 0) && $line->qty > 0) {
			// On ajuste le subprice pour tomber pile poil sans avoir à faire de modif
			$subprice = $line->subprice - ($diff>=1 ?$diff-0.5 :($diff<=-1 ?$diff-0.5 :$diff))/$line->qty/(1+$line->tva_tx/100);
			//var_dump($line->subprice, $subprice);
			$r = $object->updateline($line->id, $line->desc, $subprice, $line->qty, $line->remise_percent, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->date_start, $line->date_end, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, 0, $line->fk_unit, $subprice, 0, $line->ref_ext);

			// On vérifie normalement on est bon du premier coup mais on sait jamais, donc on boucle sur les autre produits si jamais
			$diff = $object->total_ttc-$row->paid;
			if (round($diff, 2)==0)
				break;
		}

		// On valide quel que soit le statut initial
		if ($statut>=1 || $autovalidate) {
			$object->valid($user);
			if ($statut==Commande::STATUS_SHIPMENTONPROCESS) {
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut='.Commande::STATUS_SHIPMENTONPROCESS.'
					WHERE rowid='.$object->id;
				$q2 = $db->query($sql);
				if (!$q2) {
					// @todo error
				}
			}
			elseif($statut==Commande::STATUS_CLOSED) {
				$object->cloture($user);
			}
		}
		
		// @todo vérif si vraiment utile...
		$object->fetch($object->id);

		return true;
	}

	public static function order_vat_tx_fix($user, $object)
	{
		global $conf, $langs, $db, $soc, $hookmanager;

		// @todo...
	}

	public static function invoice_1ctfix($user, $object, $autovalidate=true)
	{
		global $conf, $langs, $db, $soc, $hookmanager;

		// Si pas de paiement, rien à corriger...
		$sql = 'SELECT SUM(ip.amount) paid
			FROM '.MAIN_DB_PREFIX.'paiement_facture ip
			WHERE ip.fk_facture='.$object->id;
		$q2 = $db->query($sql);
		if (!$q2 || !($row = $q2->fetch_object()))
			return;
		
		//var_dump($row->paid, $order->total_ttc);
		$difflimit = !empty($conf->global->MMI_1CT_DIFFLIMIT) ?$conf->global->MMI_1CT_DIFFLIMIT :0.03;
		$diff = $object->total_ttc-$row->paid;
		$diffround = round($diff, 2);
		$diffabs = abs($diffround);
		//var_dump($diffabs);

		// Marge de tolérance
		if ($diffabs == 0 || $diffabs > $difflimit)
			return;
		$statut = $object->statut;
		if ($statut>0)
			$object->setDraft($user);
		// $line->subprice f*****g string should be a float
		foreach($object->lines as $line) if (($line->subprice > 0 || $line->subprice < 0) && $line->qty > 0) {
			//var_dump($line); die();
			// On ajuste le subprice pour tomber pile poil sans avoir à faire de modif
			$subprice = $line->subprice - ($diff>=1 ?$diff-0.5 :($diff<=-1 ?$diff-0.5 :$diff))/$line->qty/(1+$line->tva_tx/100);
			//var_dump($line->subprice, $subprice);
			$r = $object->updateline($line->id, $line->desc, $subprice, $line->qty, $line->remise_percent, $line->date_start, $line->date_end, $line->tva_tx, $line->localtax1_tx, $line->localtax2_tx, 'HT', $line->info_bits, $line->product_type, $line->fk_parent_line, 0, $line->fk_fournprice, $line->pa_ht, $line->label, $line->special_code, 0, 100, $line->fk_unit, $subprice, 0, $line->ref_ext);

			// On vérifie normalement on est bon du premier coup mais on sait jamais, donc on boucle sur les autre produits si jamais
			$diff = $object->total_ttc-$row->paid;
			if (round($diff, 2)==0)
				break;
		}

		// On valide quel que soit le statut initial
		if ($statut>=1 || $autovalidate) {
			$object->validate($user);
			$diff = $object->total_ttc-$row->paid;
			// @todo voir pourquoi ça ne passe pas parfois, peut-être nécessite de refaire un fetch
			if (round($diff, 2)==0 && $object->statut<=1)
				$object->setPaid($user);
		}

		// @todo vérif si vraiment utile...
		$object->fetch($object->id);

		return true;
	}

	public static function invoice_vat_tx_fix($user, $object)
	{
		global $conf, $langs, $db, $soc, $hookmanager;

		// @todo...
	}

	public static function invoice_email($user, $invoice)
	{
		global $conf, $langs, $db, $soc, $hookmanager;

		$massaction = 'confirm_presend';
		$_POST['oneemailperrecipient'] = 'on';
		$_POST['addmaindocfile'] = 'on';
		$_POST['sendmail'] = 'on';
		$objectclass = 'Facture';
		$thirdparty = $invoice->thirdparty;
		// @todo fetch associated order
		$order = NULL;
		//var_dump($thirdparty); die();

		//var_dump($thirdparty); die();
		//var_dump($thirdparty->nom); die();
		$_POST['receiver'] = $thirdparty->nom.' <'.$thirdparty->email.'>';
		//var_dump($_POST['receiver']); die();
		$_POST['fromtype'] = 'company'; // @todo modifier ! mettre le responsable du client
		$_POST['subject'] = 'Votre facture '.$conf->global->MAIN_INFO_SOCIETE_NOM;
		$_POST['message'] = 'Bonjour '.$thirdparty->nom.",\r\n\r\n"
			.'Veuillez trouver ci-joint la facture de votre dernier achat chez '.$conf->global->MAIN_INFO_SOCIETE_NOM.','."\r\n"
			.'en espérant que vous serez satisfait de nos produits'."\r\n\r\n"
			.($order ?'Réf Commande: '.$order->ref."\r\n\r\n" :'')
			.'A bientôt !'."\r\n\r\n"
			.'L\'équipe '.$conf->global->MAIN_INFO_SOCIETE_NOM."\r\n";
		$toselect = [$invoice->id];
		$uploaddir = DOL_DOCUMENT_ROOT.'/../documents/facture';
		require DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

		// Update "emailsent" field
		$sql = 'SELECT *
			FROM '.MAIN_DB_PREFIX.'facture_extrafields
				WHERE fk_object='.$invoice->id;
		$q = $db->query($sql);
		if ($q && ($row = $q->fetch_object())) {
			if (!$row->emailsent) {
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'facture_extrafields
					SET emailsent=1
					WHERE fk_object='.$invoice->id;
				$db->query($sql);
			}
		}
		else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'facture_extrafields
				(fk_object, emailsent)
				VALUES
				('.$invoice->id.', 1)';
			$db->query($sql);
		}
		//var_dump($sql);
	}

	// Création expédition
	public static function order_1clic_shipping($user, $order, $validate=true, $lots=[])
	{
		//var_dump($order); die();
		//var_dump($user); die();
		global $conf, $db, $langs;
		
		dol_syslog($langs->trans("mmi_workflow::order_1clic_shipping() ".$order->id.' : '.json_encode($lots)), LOG_NOTICE);

		if (! $user->rights->expedition->creer)
			return;
		
		// vérif statut
		if ((int)$order->status !== Commande::STATUS_VALIDATED)
			return;
	
		$order->fetchObjectLinked();
		//var_dump($order->linkedObjectsIds); die();
		if(!empty($order->linkedObjectsIds['shipping']))
			return;
		
		$origin = 'commande';
		$origin_id = $order->id;
		$objectsrc = $order;
		$date_delivery = date('Y-m-d');
		$mode_pdf = '';

		// Paramétrage du picking des lots
		// 0 => par DDM par ancienneté (puis même dépôt)
		// 1 => Même dépôt en premier puis DDM
		// (prévoir le paramétrage d'un ordre des dépôts)
		$batch_conf = 0;
		// Paramétrage du picking
		// 0 => Recherche dans tous les dépôts
		// 1 => Recherche dans une liste de dépôts (et sous-dépôts)
		// 2 => Recherche dans un dépôt (et sous-dépôts)
		$warehouse_conf = 0;

		// Default
		$warehouse_ids = 1;

		$error = 0;

		$db->begin();

		$object = new Expedition($db);
		$extrafields = new ExtraFields($db);

		$staticwarehouse = new Entrepot($db);
		if ($warehouse_ids > 0) {
			$staticwarehouse->fetch($warehouse_ids);
		}
		
		$object->origin = $origin;
		$object->origin_id = $origin_id;
		$object->fk_project = $objectsrc->fk_project;

		// $object->weight				= 0;
		// $object->sizeH				= 0;
		// $object->sizeW				= 0;
		// $object->sizeS				= 0;
		// $object->size_units = 0;
		// $object->weight_units = 0;

		$object->socid = $objectsrc->socid;
		$object->ref_customer = $objectsrc->ref_client;
		$object->model_pdf = $mode_pdf;
		$object->date_delivery = $date_delivery; // Date delivery planed
		$object->shipping_method_id	= $objectsrc->shipping_method_id;
		$object->tracking_number = '';
		$object->note_private = $objectsrc->note_private;
		$object->note_public = $objectsrc->note_public;
		$object->fk_incoterms = $objectsrc->fk_incoterms;
		$object->location_incoterms = $objectsrc->location_incoterms;

		$batch_line = array();
		$stockLine = array();
		$array_options = array();

		$num = count($objectsrc->lines);
		$totalqty = 0;

		// Analyse des quantités concernant les lots demandés
		$lots2 = [];
		if (!empty($lots)) {
			foreach($lots as &$lot) {
				$sql = 'SELECT pl.fk_product, p.ref, p.label, pl.batch, pl.eatby, pl.sellby, SUM(IF(pb.qty>0,pb.qty,0)) qty
					FROM '.MAIN_DB_PREFIX.'product_lot pl
					LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=pl.fk_product 
					LEFT JOIN '.MAIN_DB_PREFIX.'product_stock ps ON ps.fk_product=pl.fk_product 
					LEFT JOIN '.MAIN_DB_PREFIX.'product_batch pb ON pb.batch=pl.batch AND pb.fk_product_stock=ps.rowid
					WHERE pl.rowid = '.$lot['fk_product_lot'];
				$q = $db->query($sql);
				if($row=$q->fetch_assoc()) {
					$lot = array_merge($lot, $row);
					if (empty($lots2[$lot['fk_product']][$lot['batch']]))
						$lots2[$lot['fk_product']][$lot['batch']] = $lot;
					else
						$lots2[$lot['fk_product']][$lot['batch']]['qte'] += $lot['qte'];

					// Analyse erreur stock a priori
					if ($lots2[$lot['fk_product']][$lot['batch']]['qte'] > $lots2[$lot['fk_product']][$lot['batch']]['qty']) {
						setEventMessages($langs->trans("ErrorProductStockUnavailable", $lot['ref'].' '.$lot['label']), null, 'errors');
						dol_syslog($langs->trans("ErrorProductStockUnavailable", $lot['ref'].' '.$lot['label']), LOG_ERR);
						$error++;
						break;
					}
				}
			}
		}
		//var_dump($lots2);
		
		$sql = '';

		// Parcours produits commande
		if (!$error) for ($i = 0; $i < $num; $i++) {
			$line = $objectsrc->lines[$i];
			//var_dump($line->qty);

			//var_dump($conf->productbatch->enabled, $line);
			if (! $line->fk_product)
				continue;

			$product = new Product($db);
			$product->fetch($line->fk_product);
			if (! $product->id)
				continue;
			// Kit alimentaire => ne pas expédier ça bug
			//if (!empty($product->array_options['options_compose']))
			//	continue;

			// Product shippable (not service, etc.)
			if ($product->type != 0)
				continue;

			$product->load_stock('warehouseopen');
			//var_dump($product->label);

			// Extrafields
			$array_options[$i] = $extrafields->getOptionalsFromPost($object->table_element_line, $i);
			
			// cbien de ce produit est dans l'expé
			// (permet de savoir lorsqu'on s'arrête)
			$subtotalqty = 0;

			// Parcours entrepots
			// @todo préférer $warehouse_id & enfants
			if (!empty($conf->productbatch->enabled) && $line->product_tobatch) {      // If product need a batch number

				// A construire : $batch_line[$i] qui sera utilisé via adlinebatch($batch_line[$i])
				// c.f. expedition/card.php
				$batch_line[$i] = [];
				
				$productlots = [];
				// Détail des batchs ajoutés
				$sub_qty = [];
				
				if ($batch_conf==0) {
					// Premier parcours, tri par lot par DDM
					foreach($product->stock_warehouse as $stock) {
						foreach($stock->detail_batch as $batch=>$productbatch) {
							if (!isset($productlots[$batch]))
								$productlots[$batch] = $productbatch->sellby ?date('Y-m-d', $productbatch->sellby) :'';
						}
					}
					asort($productlots);
					//var_dump($productlots);

					if (isset($lots2[$product->id])) {
						foreach($lots2[$product->id] as $batch=>&$batch_detail) {
							// Plus rien à prendre
							if ($batch_detail['qte']<=0)
								continue;
							// Plus rien en stock
							if ($batch_detail['qty']<=0) {
								setEventMessages($langs->trans("ErrorProductStockUnavailable", $product->label), null, 'errors');
								dol_syslog($langs->trans("ErrorProductStockUnavailable", $product->label), LOG_ERR);
								$error++;
								break;
							}

							// Parcours des dépôts
							foreach($product->stock_warehouse as $stock) {
								//var_dump($stock);
								// Recherche du batch/lot si présent dans le dépôt
								//var_dump(array_keys($stock->detail_batch));
								if(isset($stock->detail_batch[$batch])) {
									$productbatch = $stock->detail_batch[$batch];
									//var_dump($productbatch);
									// Au mieux la qté restant à expédier, au pire ce qui reste dans le batch
									$batchqty = min($productbatch->qty, $line->qty-$subtotalqty, $batch_detail['qte'], $batch_detail['qty']);
									$batch_detail['qte'] -= $batchqty;
									$batch_detail['qty'] -= $batchqty;
									$subtotalqty += $batchqty;
									$sub_qty[] = [
										'q' => $batchqty, // the qty we want to move for this stock record
										'id_batch' => $productbatch->id, // the id into llx_product_batch of stock record to move
									];
								}
								// Assez => on stoppe
								if ($subtotalqty >= $line->qty)
									break;
							}
							// Assez => on stoppe
							if ($subtotalqty >= $line->qty)
								break;
						}
					}
					else {
						// Parcours des lot par ordre de DDM
						foreach($productlots as $batch=>$batch_ddm) {
							// Parcours des dépôts
							foreach($product->stock_warehouse as $stock) {
								//var_dump($stock);
								// Recherche du batch/lot si présent dans le dépôt
								//var_dump(array_keys($stock->detail_batch));
								if(isset($stock->detail_batch[$batch])) {
									$productbatch = $stock->detail_batch[$batch];
									//var_dump($productbatch);
									// Au mieux la qté restant à expédier, au pire ce qui reste dans le batch
									$batchqty = min($productbatch->qty, $line->qty-$subtotalqty);
									$subtotalqty += $batchqty;
									$sub_qty[] = [
										'q' => $batchqty, // the qty we want to move for this stock record
										'id_batch' => $productbatch->id, // the id into llx_product_batch of stock record to move
									];
								}
								// Assez => on stoppe
								if ($subtotalqty >= $line->qty)
									break;
							}
							// Assez => on stoppe
							if ($subtotalqty >= $line->qty)
								break;
						}
					}
				}

				$batch_line[$i]['detail'] = $sub_qty; // array of details
				$batch_line[$i]['qty'] = $subtotalqty;
				$batch_line[$i]['ix_l'] = $line->id;
				//var_dump($batch_line[$i]);
			} else { // @todo finir propduits
				foreach($product->stock_warehouse as $warehouse_id=>$stock) {
					//var_dump($stock);
	
					$stockqty = min($stock->real, $line->qty-$subtotalqty);
					
					$stockLine[$i][] = [
						'qty' => $stockqty,
						'warehouse_id' => $warehouse_id,
						'ix_l' => $line->id,
					];
					$subtotalqty += $stockqty;

					if ($subtotalqty >= $line->qty)
						break;
				}
			}
			//var_dump($subtotalqty);

			// Pas assez de produit -> on stoppe
			if ($subtotalqty < $line->qty) {
				//var_dump($product->label);
				setEventMessages($langs->trans("ErrorProductStockUnavailable", $product->label), null, 'errors');
				dol_syslog($langs->trans("ErrorProductStockUnavailable", $product->label), LOG_ERR);
				$error++;
				break;
			}

			$totalqty += $subtotalqty;
		}

		//var_dump($batch_line[2]);

		// Ajout lignes
		if (!$error) {
			if ($totalqty > 0) {		// There is at least one thing to ship
				//var_dump($_POST);exit;
				for ($i = 0; $i < $num; $i++) {
					// Vu la construction c'est l'un ou l'autre
					if (isset($batch_line[$i])) {
						// batch mode
						$ret = $object->addline_batch($batch_line[$i], $array_options[$i]);
						if ($ret < 0) {
							setEventMessages($object->error, $object->errors, 'errors');
							$error++;
						}
					}
					elseif (isset($stockLine[$i])) {
						//shipment from multiple stock locations
						$nbstockline = count($stockLine[$i]);
						for ($j = 0; $j < $nbstockline; $j++) {
							$ret = $object->addline($stockLine[$i][$j]['warehouse_id'], $stockLine[$i][$j]['ix_l'], $stockLine[$i][$j]['qty'], $array_options[$i]);
							if ($ret < 0) {
								setEventMessages($object->error, $object->errors, 'errors');
								$error++;
							}
						}
					}
				}
				// Fill array 'array_options' with data from add form
				$ret = $extrafields->setOptionalsFromPost(null, $object);
				if ($ret < 0) {
					$error++;
				}

				if (!$error) {
					$ret = $object->create($user); // This create shipment (like Odoo picking) and lines of shipments. Stock movement will be done when validating shipment.
					if ($ret <= 0) {
						setEventMessages($object->error, $object->errors, 'errors');
						$error++;
					}
				}
			} else {
				setEventMessages($langs->trans("ErrorEmptyShipping"), null, 'errors');
				$error++;
			}
		}

		// Validation
		if (!$error) {
			if ($validate) {
				$result = $object->valid($user);
				if ($result<0) {
					setEventMessages($object->error, $object->errors, 'errors');
					dol_syslog(get_class().' ::' .$object->error.implode(',',$object->errors), LOG_ERR);
					$error++;
				}
				// Auto close shipping
				if (
					!empty($conf->global->MMI_ORDER_1CLIC_INVOICE_SHIPPING_AUTOCLOSE)
					|| (!empty($conf->global->MMIPAYMENTS_CAISSE_USER) && $conf->global->MMIPAYMENTS_CAISSE_USER==$user->id)
					|| (!empty($conf->global->MMIPAYMENTS_CAISSE_COMPANY) && $conf->global->MMIPAYMENTS_CAISSE_COMPANY==$order->thirdparty->id)
				) {
					$result =  $object->setClosed();
					if ($result<0) {
						setEventMessages($object->error, $object->errors, 'errors');
						dol_syslog(get_class().' ::' .$object->error.implode(',',$object->errors), LOG_ERR);
						$error++;
					}
				}
			}
		}

		// Génération PDF
		if (!$error) {
			// Retrieve everything
			$object->fetch($object->id);

			// @todo : Niet! il faut mettre espadon mais d'abord vérifier qu'il est bien à jour
			$docmodel = 'rouget';
			$object->generateDocument($docmodel, $langs);
		}

		// OK ou rollback
		if (!$error) {
			$db->commit();
			
			return $object;
			//var_dump($expe);
		} else {
			$db->rollback();
		}
	}

	public static function invoice_draftify($user, Facture $invoice)
	{
		global $db;

		return $db->query("UPDATE ".MAIN_DB_PREFIX."facture i
			LEFT JOIN ".MAIN_DB_PREFIX."accounting_bookkeeping j ON j.doc_type='customer_invoice' AND j.fk_doc=i.rowid
			SET i.fk_statut=0
			WHERE i.rowid=".$invoice->id." AND j.rowid IS NULL");
	}

	public static function order_1clic_invoice_shipping($user, Commande $order)
	{
		global $conf;

		if ($user->rights->expedition->creer) {
			if (! ($expe = static::order_1clic_shipping($user, $order, true))) {
				return;
			}
		}

		// Création Facture
		// @todo check if invoice not already done !
		if (!empty($conf->global->MMI_ORDER_1CLIC_INVOICE) && empty($conf->global->MMI_ORDER_1CLIC_INVOICE_DELAY) && $user->rights->facture->creer) {
			if (! ($invoice = static::order_1clic_invoice($user, $order, true))) {
				return;
			}
		}

		return true;
	}


	/**
	 * Calcul des expéditions commande client
	 */
	public static function commande_expedition($id)
	{
		global $db;

		$sql = 'SELECT cl.rowid, cl.qty, SUM(ed.qty) qty_expe
			FROM '.MAIN_DB_PREFIX.'commandedet cl
			LEFT JOIN '.MAIN_DB_PREFIX.'expeditiondet ed ON ed.fk_origin_line=cl.rowid
			LEFT JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid=cl.fk_product
			LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields p2 ON p2.fk_object=cl.fk_product
			WHERE cl.fk_commande='.$id.' AND cl.qty > 0 AND cl.product_type=0 AND (p2.rowid IS NULL OR p2.compose IS NULL OR p2.compose=0)
			GROUP BY cl.rowid
			HAVING qty_expe IS NULL OR cl.qty != qty_expe';
		//var_dump($sql); //die();
		$q = $db->query($sql);
		$expe_ok = ($q->num_rows == 0 ?1 :0);
		//var_dump($expe_ok); //die();
		$sql = 'SELECT rowid, expe_ok
			FROM '.MAIN_DB_PREFIX.'commande_extrafields
			WHERE fk_object='.$id;
		$q = $db->query($sql);
		if (list($rowid, $expe_ok_old)=$q->fetch_row()) {
			if ($expe_ok_old == $expe_ok)
				return;
			$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande_extrafields
				SET expe_ok='.$expe_ok.'
				WHERE rowid='.$rowid;
		}
		else
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'commande_extrafields
				(fk_object, expe_ok)
				VALUES
				('.$id.', '.$expe_ok.')';
		//var_dump($sql);
		$q = $db->query($sql);
	}
	
	/**
	 * Calcul des réceptions commande fournisseur
	 */
	public static function commande_four_reception($id)
	{
		global $user, $db;

		$sql = 'SELECT cl.rowid, cl.qty, SUM(cd.qty) qty_recpt
			FROM '.MAIN_DB_PREFIX.'commande_fournisseurdet cl
			LEFT JOIN '.MAIN_DB_PREFIX.'commande_fournisseur_dispatch cd ON cd.fk_commande=cl.fk_commande AND cd.fk_commandefourndet=cl.rowid
			LEFT JOIN '.MAIN_DB_PREFIX.'reception r ON r.rowid=cd.fk_reception
			WHERE cl.fk_commande='.$id.'
			GROUP BY cl.rowid
			HAVING qty_recpt IS NULL OR cl.qty != qty_recpt';
		//var_dump($sql); //die();
		$q = $db->query($sql);
		$recpt_ok = ($q->num_rows == 0 ?1 :0);
		//var_dump($recpt_ok); //die();
		$sql = 'SELECT rowid, recpt_ok
			FROM '.MAIN_DB_PREFIX.'commande_fournisseur_extrafields
			WHERE fk_object='.$id;
		//var_dump($sql); //die();
		$q = $db->query($sql);
		//var_dump($q);
		$update = false;
		if (list($rowid, $recpt_ok_old)=$q->fetch_row()) {
			//var_dump($rowid, $recpt_ok_old, $recpt_ok);
			if ($recpt_ok_old != $recpt_ok) {
				$sql = 'UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur_extrafields
					SET recpt_ok='.$recpt_ok.'
					WHERE rowid='.$rowid;
				$update = true;
			}
		}
		else {
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'commande_fournisseur_extrafields
				(fk_object, recpt_ok)
				VALUES
				('.$id.', '.$recpt_ok.')';
			$update = true;
		}
		//var_dump($sql);
		if ($update)
			$q = $db->query($sql);
		if ($recpt_ok) {
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
			$cmd = new CommandeFournisseur($db);
			$cmd->fetch($id);
			if ($cmd->statut==CommandeFournisseur::STATUS_RECEIVED_PARTIALLY) {
				$cmd->statut = CommandeFournisseur::STATUS_RECEIVED_COMPLETELY;
				$cmd->update($user);
			}
		}
	}
}

mmi_workflow::__init();
