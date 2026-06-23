<?php

use Tandrezone\OrderOrchestrator\Controllers\ConfirmationController;
use Tandrezone\OrderOrchestrator\Controllers\OrderController;

return [
    /**
     * =========================================================================
     * PACKAGE ENTRY POINT
     * Route: /order [GET]
     * Purpose: Displays the customer data form pre-loaded with the products to
     *          be ordered. This is the only route the host application needs to
     *          call to start the order flow.
     *
     * The host application must supply the `products` array when rendering or
     * linking to this route so the package knows what is being ordered.
     * =========================================================================
     */
    [
        'method'     => 'GET',
        'path'       => '/order',
        'callback'   => [OrderController::class, 'showForm'],
        'parameters' => [
            'products' => [
                'type'      => 'array',
                'structure' => [
                    'id'       => 'integer',
                    'image'    => 'string',
                    'name'     => 'string',
                    'price'    => 'float',
                    'quantity' => 'integer',
                ],
            ],
        ],
    ],

    /**
     * FORM SUBMISSION & EVALUATION
     * Route: /order [POST]
     * Purpose: Receives the form data and forwards it to the confirmation view.
     */
    [
        'method'     => 'POST',
        'path'       => '/order',
        'callback'   => [OrderController::class, 'processForm'],
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
        'callback'   => [ConfirmationController::class, 'processConfirmation'],
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
    /**
     * =========================================================================
     * PACKAGE RETURN POINT
     * Route: defined by config('redirect_after_order')  [GET]
     * Purpose: The host application's route to which the package redirects
     *          after the order has been successfully saved. The host application
     *          must define this route and set its path in config/config.php
     *          under the key `redirect_after_order`.
     *
     * The package appends the following query parameter to the redirect URL so
     * the host application can confirm the completed order.
     * =========================================================================
     */
    [
        'method'     => 'GET',
        'path'       => 'config[redirect_after_order]', // e.g. /thank-you — set in config/config.php
        'parameters' => [
            'total_price' => 'float', // Total price of the confirmed order
        ],
    ],
];
