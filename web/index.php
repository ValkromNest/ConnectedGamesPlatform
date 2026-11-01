<?php

declare(strict_types=1);

// Let PHP's built-in server serve existing static files directly.
if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
    $file = str_contains($path, "\0") ? false : realpath(__DIR__ . $path);
    if ($file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)
        && is_file($file) && $file !== __FILE__) {
        return false;
    }
}

// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
