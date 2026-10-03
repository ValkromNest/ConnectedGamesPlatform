<?php

declare(strict_types=1);

/** @var yii\web\View $this */
/** @var string $content */

use app\widgets\Alert;
use yii\helpers\Html;

$hasSidebar = !Yii::$app->user->isGuest;

$this->render('_head');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100" data-bs-theme="light">
<head>
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?></title>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<?= $this->render('_header') ?>

<?php if ($hasSidebar): ?>
    <?= $this->render('_menu_left') ?>
    <?= $this->render('_menu_right') ?>
<?php endif; ?>

<main id="main" class="flex-grow-1<?= $hasSidebar ? ' app-main-with-sidebar' : '' ?>" role="main">
    <div class="<?= $hasSidebar ? 'container-fluid' : 'container' ?>">
        <?= Alert::widget() ?>
        <?= $content ?>
    </div>
</main>

<?= $this->render('_footer', ['hasSidebar' => $hasSidebar]) ?>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
