<?php

Yii::setAlias('@darts', dirname(__DIR__));

return [
    'id' => 'darts-game-service-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'darts\commands',
    'components' => [
        'db' => require __DIR__ . '/db.php',
    ],
];
