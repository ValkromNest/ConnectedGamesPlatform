<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/test_db.php';

/**
 * Application configuration shared by all test types
 */
return [
    'id' => 'basic-tests',
    'name' => 'Connected Games Platform - Simone Cagliano',
    'basePath' => dirname(__DIR__),
    'bootstrap' => [
        \app\tests\Support\MailerBootstrap::class,
    ],
    'modules' => [
        'dynagrid' => [
            'class' => \kartik\dynagrid\Module::class,
            'bsVersion' => '5.x',
            'configView' => '@app/views/dynagrid/config',
            'defaultTheme' => 'simple-default',
            'configEncryptSalt' => 'cgp-dynagrid-settings-v1',
        ],
        'gridview' => [
            'class' => \kartik\grid\Module::class,
            'bsVersion' => '5.x',
            'exportEncryptSalt' => 'cgp-grid-export-v1',
        ],
    ],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'en-US',
    'components' => [
        'db' => $db,
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'messageClass' => \yii\symfonymailer\Message::class,
            'useFileTransport' => true,
            'viewPath' => '@app/mail',
        ],
        'assetManager' => [
            'basePath' => __DIR__ . '/../web/assets',
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
        ],
        'request' => [
            'cookieValidationKey' => 'test',
            'enableCsrfValidation' => false,
            // but if you absolutely need it set cookie domain to localhost
            /*
            'csrfCookie' => [
                'domain' => 'localhost',
            ],
            */
        ],
    ],
    'params' => $params,
];
