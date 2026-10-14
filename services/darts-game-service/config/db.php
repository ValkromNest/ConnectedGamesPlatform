<?php

return [
    'class' => yii\db\Connection::class,
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('DARTS_GAME_DB_HOST') ?: 'localhost',
        getenv('DARTS_GAME_DB_PORT') ?: '3306',
        getenv('DARTS_GAME_DB_NAME') ?: 'connected_games_dartsgame',
    ),
    'username' => getenv('DARTS_GAME_DB_USER') ?: 'root',
    'password' => getenv('DARTS_GAME_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'tablePrefix' => '',
];
