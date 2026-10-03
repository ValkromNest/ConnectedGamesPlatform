<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use app\assets\AppAsset;
use app\models\Autoconfigurazione;

AppAsset::register($this);

$lightThemeColor = Autoconfigurazione::getValueAsString(
    Autoconfigurazione::THEME_LIGHT_COLOR,
    '#0d6efd',
);
$darkThemeColor = Autoconfigurazione::getValueAsString(
    Autoconfigurazione::THEME_DARK_COLOR,
    '#6ea8fe',
);
$isHexColor = static fn (?string $color): bool => preg_match('/\A#[0-9a-fA-F]{6}\z/', (string) $color) === 1;

$this->registerCss(sprintf(
    '[data-bs-theme="light"] { --app-theme-accent: %s; } [data-bs-theme="dark"] { --app-theme-accent: %s; }',
    $isHexColor($lightThemeColor) ? $lightThemeColor : '#0d6efd',
    $isHexColor($darkThemeColor) ? $darkThemeColor : '#6ea8fe',
));

$this->registerCsrfMetaTags();
$this->registerMetaTag(
    ['charset' => Yii::$app->charset],
    'charset',
);
$this->registerMetaTag(
    [
        'name' => 'viewport',
        'content' => 'width=device-width, initial-scale=1',
    ],
);
if (!empty($this->params['meta_description'])) {
    $this->registerMetaTag(
        [
            'name' => 'description',
            'content' => $this->params['meta_description'],
        ],
    );
}
if (!empty($this->params['meta_keywords'])) {
    $this->registerMetaTag(
        [
            'name' => 'keywords',
            'content' => $this->params['meta_keywords'],
        ],
    );
}
$this->registerLinkTag(
    [
        'rel' => 'icon',
        'type' => 'image/x-icon',
        'href' => Yii::getAlias('@web/favicon.ico'),
    ],
);
