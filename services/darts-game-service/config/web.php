<?php

return [
    'id' => 'darts-game-service',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'darts\controllers',
    'components' => [
        'request' => [
            'cookieValidationKey' => getenv('DARTS_GAME_COOKIE_KEY') ?: 'local-darts-game-cookie-key',
            'parsers' => ['application/json' => yii\web\JsonParser::class],
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
                'PUT api/v1/games/darts/matches/<matchId:[A-Za-z0-9_-]+>/context' => 'api/darts-game/context',
                'GET api/v1/games/darts/matches/<matchId:[A-Za-z0-9_-]+>/state' => 'api/darts-game/state',
                'POST api/v1/games/darts/matches/<matchId:[A-Za-z0-9_-]+>/throws' => 'api/darts-game/throws',
                'POST api/v1/games/darts/matches/<matchId:[A-Za-z0-9_-]+>/finish' => 'api/darts-game/finish',
            ],
        ],
        'db' => require __DIR__ . '/db.php',
        'errorHandler' => [
            'errorAction' => null,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                    'logFile' => '@runtime/logs/app.log',
                ],
            ],
        ],
    ],
];
