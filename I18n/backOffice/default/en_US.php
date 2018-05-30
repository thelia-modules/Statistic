<?php

return array(
    'tool' => array(
        'title' => 'Statistics',

        'panel'=> array(
            'customer' => array(
                'title' => 'Customers',
                'subtitle' => 'Statistics on customers',
                'newCustomer' => 'New customer',
                'firstOrder' => 'First order',
            ),
            'general' => array(
                'title' => 'General Statistics',
                'subtitle' => 'General Statistics',
                'averageCart' => 'Average cart',
                'bestSales' => array(
                    'name' => 'Name',
                    'reference' => 'Reference',
                    'totalHT' => 'Total without taxes (€)',
                    'totalSold' => 'Total sold',
                    'totalTTC' => 'Total with taxes (€)',
                    'title' => 'Best sale',
                ),
                'discountCode' => array(
                    'code' => 'Promotional code',
                    'nbUse' => 'Number use',
                    'rule' => 'Rule',
                    'title' => 'Promotional code',
                ),
                'meansTransport' => array(
                    'description' => 'Description',
                    'means' => 'Means transport',
                    'nbUse' => 'Number of use',
                    'title' => 'Means transport',
                ),
                'meansPayment' => array(
                    'description' => 'Description',
                    'means' => 'Means of payment',
                    'nbUse' => 'Number of use',
                    'title' => 'Means of payment',
                ),
                'turnover' => array(
                    'TTCWithShippping' => 'Turnover with shipping costs',
                    'TTCWithoutShippping' => 'Turnover without shipping costs',
                    'month' => 'Month',
                    'title' => 'Annual sales',
                ),
            ),
            'product' => array(
                'title' => 'Detail by product',
                'subtitle' => 'Statistics by product',
                'turnover' => array(
                    'title' => 'Turnover'
                ),
                'sale' => array(
                    'title' => 'Number sale'
                ),
                'selectCategory' => 'Select category',
            )
        )
    )
);
