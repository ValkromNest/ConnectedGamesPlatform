<?php

declare(strict_types=1);

return [
    'id' => 'connected-games-identity',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'identity\\controllers',
    'bootstrap' => ['log'],
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('IDENTITY_COOKIE_KEY') ?: throw new RuntimeException('Set IDENTITY_COOKIE_KEY in the service environment.'),
            'parsers' => ['application/json' => yii\web\JsonParser::class],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'GET api/v1/auth/me' => 'api/auth/me',
                'GET api/v1/users/players' => 'api/auth/players',
            ],
        ],
        'db' => require __DIR__ . '/db.php',
        'security' => [
            'passwordHashCost' => 12,
        ],
        'errorHandler' => [
            'errorAction' => null,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [[
                'class' => yii\log\FileTarget::class,
                'levels' => ['error', 'warning'],
                'logFile' => '@runtime/logs/app.log',
            ]],
        ],
    ],
];
