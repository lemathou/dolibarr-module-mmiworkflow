# MMIWORKFLOW FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Features

Ensemble de paramètres et améliorations du workflow Devis / Commande / Expédition / Facture, regroupés sous une page de configuration unique (`Setup → Modules → MMIWorkflow`).

### Facturation et expédition en 1 clic

- Bouton **« 1 Clic Facture & Expé »** sur la fiche commande qui crée l'expédition (avec picking automatique des lots par DDM ou par dépôt), génère le PDF, valide et clôture éventuellement l'expédition, puis crée la facture, l'auto-assigne les paiements existants et l'envoie par email.
- Mode différé : facturation déclenchée à la clôture de l'expédition plutôt qu'à la création.
- Envoi automatique de la facture par email (avec exclusion possible des comptes pro ou via flag par tiers).
- API REST `GET /mmiworkflow/commande_expe/{id}` pour déclencher la même création d'expédition depuis un système externe (Prestashop notamment), avec passage des lots imposés.
- Bouton **« Envoi express email »** sur la fiche facture pour un envoi rapide sans passer par l'écran d'expédition de mail.

### Réouverture en brouillon

- Bouton **« Réouvrir en Brouillon »** sur les commandes expédiées (permission `mmiworkflow->commande->draftify`) et les factures payées (permission `mmiworkflow->facture->draftify`).
- Pour les factures, blocage automatique si déjà passée en compta (`accounting_bookkeeping`) ; filtre possible par type de facture autorisé.
- Trigger `ORDER_VALIDATE` : si la commande avait des expéditions liées (commande revalidée après réouverture), elle est automatiquement repassée en « expédition en cours », et clôturée si tout est livré (selon `WORKFLOW_ORDER_CLASSIFY_SHIPPED_SHIPPING` / `_CLOSED` du module workflow natif).

### Suivi expéditions / réceptions

- Extrafield `expe_ok` sur les commandes client, mis à jour automatiquement par les triggers `SHIPPING_*` (validé/modifié/clôturé/réouvert/supprimé) selon les quantités expédiées.
- Extrafield `recpt_ok` sur les commandes fournisseur, mis à jour par les triggers `RECEPTION_*` et `ORDER_SUPPLIER_*` ; passe automatiquement la commande fournisseur en `RECEIVED_COMPLETELY` quand tout est réceptionné.
- Bouton **« Marquer expédié tout »** pour forcer manuellement le flag `expe_ok` (utile sur des commandes anciennes sans expédition saisie).
- Alerte sur la liste des expéditions s'il existe des commandes validées non expédiées.

### PDF d'expédition

Plusieurs options pour personnaliser les bordereaux d'expédition :

- Cacher poids/volume, n° de lot, date de livraison.
- Afficher les images produit, un message d'en-tête HTML configurable, un libellé en gras, un message anti-gaspi.
- Hook `pdf_build_address` : si l'expédition a un contact `SHIPPING` avec un champ `options_p_company`, ce dernier est utilisé comme société destinataire (cas des points relais ou des entreprises livrées chez un particulier).

### Correction des écarts de centimes

- Bouton **« Fix Bug 1ct »** sur commande et facture : ajuste le `subprice` des lignes pour faire correspondre le total au paiement reçu, dans la limite d'un seuil de tolérance configurable (par défaut 0,03 €). Cible les écarts de synchronisation Prestashop ↔ Dolibarr.
- Re-validation automatique de la commande/facture, repassage en `SHIPMENTONPROCESS` ou `CLOSED` selon le statut initial.
- Bouton **« Fix Bug TVA »** prévu pour la correction des taux de TVA mal synchronisés (stub à compléter).

### Champs et options divers

- Champ **« Professionnel »** sur les tiers, contrôlé par `SFYCUSTOM_FIELD_CLIENT_PRO`.
- Champ **« Ne pas envoyer automatiquement les factures »** sur les tiers.
- Champ **« Envoyé par email »** sur les factures, alimenté automatiquement.
- Blocage de la validation des commandes contenant des lignes libres (lignes produit sans `fk_product`).
- Enrichissement de la liste des mouvements de stock (client, commande).

## Dependencies

- `modMMICommon`
- `modMMIPayments`

PHP ≥ 7.4, Dolibarr ≥ 11.



Other external modules are available on [Dolistore.com](https://www.dolistore.com).

## Translations

Translations can be completed manually by editing files into directories *langs*.

<!--
This module contains also a sample configuration for Transifex, under the hidden directory [.tx](.tx), so it is possible to manage translation using this service.

For more informations, see the [translator's documentation](https://wiki.dolibarr.org/index.php/Translator_documentation).

There is a [Transifex project](https://transifex.com/projects/p/dolibarr-module-template) for this module.
-->

<!--

## Installation

### From the ZIP file and GUI interface

- If you get the module in a zip file (like when downloading it from the market place [Dolistore](https://www.dolistore.com)), go into
menu ```Home - Setup - Modules - Deploy external module``` and upload the zip file.

Note: If this screen tell you there is no custom directory, check your setup is correct:

- In your Dolibarr installation directory, edit the ```htdocs/conf/conf.php``` file and check that following lines are not commented:

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Uncomment them if necessary (delete the leading ```//```) and assign a sensible value according to your Dolibarr installation

    For example :

    - UNIX:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows:
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```

### From a GIT repository

- Clone the repository in ```$dolibarr_main_document_root_alt/mmiworkflow```

```sh
cd ....../custom
git clone git@github.com:gitlogin/mmiworkflow.git mmiworkflow
```

### <a name="final_steps"></a>Final steps

From your browser:

  - Log into Dolibarr as a super-administrator
  - Go to "Setup" -> "Modules"
  - You should now be able to find and enable the module

-->

## Licenses

### Main code

GPLv3 or (at your option) any later version. See file COPYING for more information.

### Documentation

All texts and readmes are licensed under GFDL.
