<?php

return array(
    'tool' => array(
        'title' => 'Statistiques',

        'panel'=> array(
            'customer' => array(
                'title' => 'Clients',
                'subtitle' => 'Statistiques sur les clients',
                'newCustomer' => 'Nouveau client',
                'firstOrder' => 'Première commande',
            ),
            'general' => array(
                'title' => 'Statistiques générales',
                'subtitle' => 'Statistiques générales',
                'averageCart' => 'Panier moyen',
                'bestSales' => array(
                    'name' => 'Nom',
                    'reference' => 'Référence',
                    'totalHT' => 'Total Hors Taxe (€)',
                    'totalSold' => 'Total des ventes',
                    'totalTTC' => 'Total TTC (€)',
                    'title' => 'Meilleures ventes',
                ),
                'discountCode' => array(
                    'code' => 'Code promotionnel',
                    'nbUse' => 'Nombre d\'utilisation',
                    'rule' => 'Règle',
                    'title' => 'Code promotionnel',
                ),
                'meansTransport' => array(
                    'description' => 'Description',
                    'means' => 'Moyen de transport',
                    'nbUse' => 'Nombre d\'utilisation',
                    'title' => 'Moyen de transport',
                ),
                'meansPayment' => array(
                    'description' => 'Description',
                    'means' => 'Moyen de paiement',
                    'nbUse' => 'Nombre d\'utilisation',
                    'title' => 'Moyen de paiement',
                ),
                'turnover' => array(
                    'TTCWithShippping' => 'Chiffre d\'affaires avec les frais de port',
                    'TTCWithoutShippping' => 'Chiffre d\'affaires sans les frais de port',
                    'month' => 'Mois',
                    'title' => 'Chiffre d\'affaires annuel',
                ),
            ),
            'product' => array(
                'title' => 'Détail par produit',
                'subtitle' => 'Statistiques par produit',
                'turnover' => array(
                    'title' => 'Part du chiffre d\'affaires'
                ),
                'sale' => array(
                    'title' => 'Nombre de ventes'
                ),
                'selectCategory' => 'Sélectionnez une catégorie',
            )
        )
    )
);