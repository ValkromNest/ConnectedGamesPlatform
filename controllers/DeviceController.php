<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Device;
use app\models\Game;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class DeviceController extends AdminController
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'create' => ['GET', 'POST'],
                'update' => ['GET', 'POST'],
                'show-token' => ['POST'],
            ]],
        ]);
    }

    public function actionIndex(): string
    {
        $dataProvider = Device::listDataProvider();

        return $this->render('index', compact('dataProvider'));
    }

    public function actionCreate(): Response|string
    {
        $model = new Device(['is_enabled' => true]);
        if ($model->load($this->request->post())) {
            $model->is_enabled = true;
            $token = Yii::$app->security->generateRandomString(64);
            $model->token_hash = hash('sha256', $token);
            // Yii restituisce un cifrato binario: lo codifichiamo per conservarlo senza perdita in una colonna testuale.
            $model->token_encrypted = base64_encode(
                Yii::$app->security->encryptByKey($token, Yii::$app->request->cookieValidationKey),
            );

            if ($model->saveDetails()) {
                Yii::$app->session->setFlash('success', 'Dispositivo creato.');

                return $this->redirect(['index']);
            }
        }

        $games = Game::options();

        return $this->render('create', compact('model', 'games'));
    }

    public function actionUpdate(int $id): Response|string
    {
        $model = $this->findModel($id);
        $tokenHash = $model->token_hash;
        $tokenEncrypted = $model->token_encrypted;

        if ($model->load($this->request->post())) {
            // Il token è immutabile dalla schermata di aggiornamento.
            $model->token_hash = $tokenHash;
            $model->token_encrypted = $tokenEncrypted;
            if ($model->saveDetails()) {
                Yii::$app->session->setFlash('success', 'Dispositivo aggiornato.');

                return $this->redirect(['index']);
            }
        }

        $model->token_hash = $tokenHash;
        $model->token_encrypted = $tokenEncrypted;
        $games = Game::options();

        return $this->render('update', compact('model', 'games'));
    }

    public function actionShowToken(int $id): array
    {
        $model = $this->findModel($id);
        Yii::$app->response->format = Response::FORMAT_JSON;

        if ($model->token_encrypted === null || $model->token_encrypted === '') {
            Yii::$app->response->statusCode = 422;

            return ['error' => 'Token non disponibile per questo dispositivo.'];
        } else {
            $token = Yii::$app->security->decryptByKey(
                $model->token_encrypted,
                Yii::$app->request->cookieValidationKey,
            );
            if ($token === false) {
                $encryptedToken = base64_decode($model->token_encrypted, true);
                $token = $encryptedToken === false
                    ? false
                    : Yii::$app->security->decryptByKey($encryptedToken, Yii::$app->request->cookieValidationKey);
            }
            if (is_string($token)) {
                return ['token' => $token];
            } else {
                Yii::$app->response->statusCode = 422;

                return ['error' => 'Impossibile decifrare il token.'];
            }
        }
    }

    private function findModel(int $id): Device
    {
        $model = Device::findById($id);
        if ($model === null) {
            throw new NotFoundHttpException('Dispositivo non trovato.');
        }

        return $model;
    }

}
