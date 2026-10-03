<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\Autoconfigurazione;
use yii\web\Response;

final class GestoreTemiController extends AdminController
{
    public function actionIndex(): Response|string
    {
        $coloreDiurno = Autoconfigurazione::getValueAsString(
            Autoconfigurazione::THEME_LIGHT_COLOR,
            '#0d6efd',
        ) ?? '#0d6efd';
        $coloreNotturno = Autoconfigurazione::getValueAsString(
            Autoconfigurazione::THEME_DARK_COLOR,
            '#6ea8fe',
        ) ?? '#6ea8fe';
        $error = null;

        if ($this->request->isPost) {
            $coloreDiurnoInserito = $this->request->post('colore_diurno');
            $coloreNotturnoInserito = $this->request->post('colore_notturno');

            if (
                !is_string($coloreDiurnoInserito) || !is_string($coloreNotturnoInserito)
                || !self::isHexColor($coloreDiurnoInserito) || !self::isHexColor($coloreNotturnoInserito)
            ) {
                $error = 'Inserisci due colori esadecimali validi.';
            } else {
                Autoconfigurazione::setValueAsString(
                    Autoconfigurazione::THEME_LIGHT_COLOR,
                    $coloreDiurnoInserito,
                );
                Autoconfigurazione::setValueAsString(
                    Autoconfigurazione::THEME_DARK_COLOR,
                    $coloreNotturnoInserito,
                );

                Yii::$app->session->setFlash('success', 'Colori dei temi aggiornati.');

                return $this->refresh();
            }
        }

        return $this->render('index', compact('coloreDiurno', 'coloreNotturno', 'error'));
    }

    private static function isHexColor(string $color): bool
    {
        return preg_match('/\A#[0-9a-fA-F]{6}\z/', $color) === 1;
    }
}
