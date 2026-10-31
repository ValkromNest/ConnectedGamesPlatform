<?php

declare(strict_types=1);

return [
    'class' => yii\db\Connection::class,
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('IDENTITY_DB_HOST') ?: 'localhost',
        getenv('IDENTITY_DB_PORT') ?: '3306',
        getenv('IDENTITY_DB_NAME') ?: 'connected_games_identity',
    ),
    'username' => getenv('IDENTITY_DB_USER') ?: 'root',
    'password' => getenv('IDENTITY_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
