<?php
return [
    'components' => [
        'shop' => [
            'paysystemHandlers' => [
                \skeeks\cms\shop\yookassa\YookassaPaysystemHandler::class,
            ],
        ],

        'log' => [
            'targets' => [
                [
                    'class'      => 'yii\log\FileTarget',
                    'levels'     => ['info'],
                    'logVars'    => [],
                    'categories' => [\skeeks\cms\shop\yookassa\controllers\YookassaController::class, \skeeks\cms\shop\yookassa\YookassaPaysystemHandler::class],
                    'logFile'    => '@runtime/logs/yookassa-info.log',
                ],

                [
                    'class'      => 'yii\log\FileTarget',
                    'levels'     => ['error'],
                    'logVars'    => [],
                    'categories' => [\skeeks\cms\shop\yookassa\controllers\YookassaController::class, \skeeks\cms\shop\yookassa\YookassaPaysystemHandler::class],
                    'logFile'    => '@runtime/logs/yookassa-errors.log',
                ],
            ],
        ],
    ],
];