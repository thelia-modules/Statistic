# Statistic 
Show statistic of customer, sales and products. It also provides the Hook to insert other statistics.

## Installation

### Manually
* Copy the module into <thelia_root>/local/modules directory and be surethat the name of the module is `Statistic`.
* Activate it in your thelia administration.

## Usage
This module is available in the backoffice, `Tool` menu, submenu `Statistic`.
It includes 3 tabs :
* Clients,  clients general statistics - New Clients and First Order-,
* Sales and products, sales and products general statistics - Average cart, Best Sales, Discount code, Means of transport,
 Means of payment and Annual turnover -,
* Product details, product statistics -  Share of turnover and Sales number -.

## Hook 
This module provides two hooks to insert statistic tab and JS file :

`statistic.tab` type `back`, event is `HookRenderBlockEvent` type and parameters are :
* tab_id, tab identifying,
* tab_nav_titl, tab title,
* content, HTML file ($this->render(...)) to insert in tab.

`statistic.footer.js type `back`, event is `HookRenderEvent`, it is used to insert JS, like that :

    $jsFile = $this->addJS('path_to_file.js');
    $event->add($jsFile);

`statistic.best-sales.toolbar` type `back`, event is `HookRenderEvent`: actions shown beside the brand filter and the
search of the best sales table. Each header cell of the table carries `data-key`, so a script can read the rows left
after filtering through the DataTables API (`$('#table-general').DataTable()`).

## Events

`Statistic\Event\StatisticEvents::BEST_SALES_TABLE` (`BestSalesTableEvent`): the best sales table before it is sent.
`addColumn($key, $title)` adds a column before the product title, `getRows()` / `setRows()` fill it (a row without a
value shows an empty cell). Titles and values are written as HTML: escape text. A key of the module's own columns is
refused.

`Statistic\Event\StatisticEvents::PRODUCT_DETAILS` (`ProductDetailsEvent`): the sales of a product shown under its row,
grouped by attribute value. `getLines()` / `setLines()` change the lines, `getProductSaleElementsId($label)` gives the
product sale elements the first line of a group was sold as.

# Statistic
Affichage de statistique sur les client, les ventes et les produits. Il fournit aussi les Hook pour insérer d'autre 
statistiques.

## Installation

### Manuellement 
* Copier le module dans le dossier <thelia_root>/local/modules et s'assurer que le nom du module soit bien `Statistic`,
* Activer le module dans le paneau d'administration Thelia.

## Usage
Ce module est accessible dans le backOffice, menu `Outil`, sous-menu `Statistique`.
Il comporte 3 onglets :
* Clients, statistiques générales sur les clients - Nouveaux clients et Première commande -,
* Ventes et produits, statistiques générales sur les ventes et produits - Panier moyen, Meilleurs ventes, Code promotionnel,
 Moyen de transport, Moyen de paiement et Chiffre d'affaire annuel -,
* Détail par produit, statistiques par produits - Part du chiffre d'affaire et Nombre de vente.

## Hook
Ce module fournit deux Hooks pour insérer un onglet de statistiques et son fichier JS associé :

`statistic.tab` de type `back`, l'événement est de type `HookRenderBlockEvent` et les paramètres sont :
* tab_id, identifiant du onglet,
* tab_nav_title, titre du onglet,
* content, le fichier HTML ($this->render(...)) à insérer dans le onglet.

`statistic.footer.js` de type `back`, l'événement est de type `HookRenderEvent`, il sert à insérer le JS comme suit :
    
    $jsFile = $this->addJS('path_to_file.js');
    $event->add($jsFile);

