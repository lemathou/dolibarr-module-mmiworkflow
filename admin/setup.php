<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022 Mathieu Moulin <mathieu@iprospective.fr>
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
 * \file    mmiworkflow/admin/setup.php
 * \ingroup mmiworkflow
 * \brief   MMIWorkflow setup page.
 */

// Load Dolibarr environment
require_once '../env.inc.php';
require_once '../main_load.inc.php';

$arrayofparameters = array(
	'SFYCUSTOM_FIELD_CLIENT_PRO'=>array('type'=>'yesno','enabled'=>1),

	'MMI_ORDER_1CLIC_INVOICE_SHIPPING'=>array('type'=>'yesno','enabled'=>1),
	'MMI_ORDER_1CLIC_INVOICE_SHIPPING_AUTOCLOSE'=>array('type'=>'yesno','enabled'=>1),
	'MMI_ORDER_1CLIC_INVOICE'=>array('type'=>'yesno','enabled'=>1),
	'MMI_ORDER_1CLIC_INVOICE_DELAY'=>array('type'=>'yesno','enabled'=>1),
	'MMI_ORDER_1CLIC_INVOICE_EMAIL_AUTO'=>array('type'=>'yesno','enabled'=>1),
	'MMI_ORDER_1CLIC_INVOICE_EMAIL_AUTO_NOPRO'=>array('type'=>'yesno','enabled'=>1),
	'MMI_INVOICE_EMAILSEND'=>array('type'=>'yesno','enabled'=>1),

	'SFYCUSTOM_LOCK'=>array('type'=>'yesno', 'enabled'=>1),
	'MMI_ORDER_DRAFTIFY'=>array('type'=>'yesno', 'enabled'=>1),

	'SHIPPING_PDF_HIDE_WEIGHT_AND_VOLUME'=>array('type'=>'yesno','enabled'=>1),
	'SHIPPING_PDF_HIDE_BATCH'=>array('type'=>'yesno','enabled'=>1), // MMI Hack
	'SHIPPING_PDF_HIDE_DELIVERY_DATE'=>array('type'=>'yesno','enabled'=>1), // MMI Hack
	'SHIPPING_PDF_ANTIGASPI'=>array('type'=>'yesno','enabled'=>1), // MMI Hack
	'MAIN_GENERATE_SHIPMENT_WITH_PICTURE'=>array('type'=>'yesno','enabled'=>1),
	'MMI_SHIPPING_PDF_MESSAGE'=>array('type'=>'html','enabled'=>1),

	'MMI_INVOICE_DRAFTIFY'=>array('type'=>'yesno', 'enabled'=>1),
	'MMI_INVOICE_DRAFTIFY_TYPES'=>array('type'=>'string', 'enabled'=>1),

	'MMI_1CT_FIX'=>array('type'=>'yesno', 'enabled'=>1),
	'MMI_1CT_DIFFLIMIT'=>array('type'=>'decimal', 'enabled'=>1, 'default'=>0.03),
	'MMI_VAT_TX_FIX'=>array('type'=>'yesno', 'enabled'=>1),

	'SFY_ALERT_ORDER_NOT_SHIPPED'=>array('type'=>'yesno', 'enabled'=>1),

	'MMI_MOVEMENT_LIST_ENHANCE'=>array('type'=>'yesno', 'enabled'=>1),
);

require_once '../../mmicommon/admin/mmisetup_1.inc.php';

