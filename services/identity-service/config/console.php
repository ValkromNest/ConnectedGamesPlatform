<?php

declare(strict_types=1);

Yii::setAlias('@identity', dirname(__DIR__));

return [
    'id' => 'connected-games-identity-console',
    'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'identity\\commands',
    'controllerMap' => [
        'migrate' => [
            'class' => yii\console\controllers\MigrateController::class,
            'migrationPath' => '@identity/migrations',
            'migrationTable' => '{{%tbl_migration}}',
        ],
    ],
    'components' => [
        'db' => require __DIR__ . '/db.php',
        'security' => [
            'passwordHashCost' => 12,
        ],
    ],
];
