<?php
return [
    'components' => [
        'shop' => [
            'paysystemHandlers'             => [
                \skeeks\cms\shop\yookassa\YookassaPaysystemHandler::class
            ]
        ],
    ],
];