<?php

return [
    /**
     * STARTING POINT
     * Route: /order [GET]
     * Purpose: Displays the initial customer data and product selection form.
     */
    [
        'method'     => 'GET',
        'path'       => '/order',
        'parameters' => [], // Initial load requires no parameters
    ],

    /**
     * FORM SUBMISSION & EVALUATION
     * Route: /order [POST]
     * Purpose: Receives the form data and forwards it to the confirmation view.
     */
    [
        'method'     => 'POST',
        'path'       => '/order',
        'parameters' => [
            'customer_name'    => 'string',
            'customer_email'   => 'string',
            'shipping_address' => 'string',
            'products'         => [
                'type'      => 'array',
                'structure' => [
                    'id'       => 'integer',
                    'name'     => 'string',
                    'image'    => 'string',
                    'price'    => 'float',
                    'quantity' => 'integer',
                ],
            ],
        ],
    ],

    /**
     * FINAL CONFIRMATION & ORDER PROCESSING
     * Route: /confirmation [POST]
     * Purpose: Saves validated data to the database and handles the post-process redirect.
     */
    [
        'method'     => 'POST',
        'path'       => '/confirmation',
        'parameters' => [
            'customer_name'    => 'string',
            'customer_email'   => 'string',
            'shipping_address' => 'string',
            'total_price'      => 'float',
            'products'         => [
                'type'      => 'array',
                'structure' => [
                    'id'       => 'integer',
                    'name'     => 'string',
                    'price'    => 'float',
                    'quantity' => 'integer',
                ],
            ],
        ],
    ],
];
