<?php

declare(strict_types=1);

namespace darts\controllers\api;

use darts\DartsThrowCalculator;
use darts\exceptions\GameConflictException;
use darts\exceptions\GameNotFoundException;
use darts\services\DartsGameService;
use DomainException;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;

final class DartsGameController extends Controller
{
    public $enableCsrfValidation = false;

    private string $correlationId = '';

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'context' => ['PUT'],
                    'state' => ['GET'],
                    'throws' => ['POST'],
                    'finish' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $provided = Yii::$app->request->headers->get('X-Correlation-ID', '');
        $this->correlationId = preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $provided,
        ) === 1 ? strtolower($provided) : $this->newUuid();
        Yii::$app->response->headers->set('X-Correlation-ID', $this->correlationId);

        $environmentName = in_array($action->id, ['context', 'finish'], true)
            ? 'DARTS_GAME_MATCHES_TOKEN'
            : 'DARTS_GAME_DEVICE_TOKEN';
        $expectedToken = getenv($environmentName) ?: '';
        if ($expectedToken === '') {
            $this->setError(503, 'service_configuration_error', 'Required API credential is not configured.');
            return false;
        }
        $authorization = Yii::$app->request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authorization), $matches)) {
            $this->setError(401, 'unauthorized', 'Bearer token required.');
            return false;
        }
        if (!hash_equals($expectedToken, trim($matches[1]))) {
            $this->setError(403, 'forbidden', 'Bearer token is not authorized for this operation.');
            return false;
        }

        return parent::beforeAction($action);
    }

    public function actionContext(string $matchId): array
    {
        $idempotencyKey = $this->idempotencyKey();
        if ($idempotencyKey === null) {
            return $this->error(400, 'invalid_idempotency_key', 'Idempotency-Key must be a UUID.');
        }
        $body = $this->jsonBody();
        if (!is_array($body)) {
            return $this->error(400, 'invalid_json', 'A JSON object body is required.');
        }
        try {
            return (new DartsGameService())->initialize(
                $matchId,
                $body,
                $idempotencyKey,
                $this->correlationId,
            );
        } catch (GameConflictException $exception) {
            return $this->error(409, 'context_conflict', $exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return $this->error(422, 'invalid_context', $exception->getMessage());
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            return $this->error(500, 'game_initialization_failed', 'Game initialization failed.');
        }
    }

    public function actionState(string $matchId): array
    {
        try {
            return (new DartsGameService())->state($matchId, $this->correlationId);
        } catch (GameNotFoundException $exception) {
            return $this->error(404, 'game_not_found', $exception->getMessage());
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            return $this->error(500, 'game_state_failed', 'Could not read game state.');
        }
    }

    public function actionFinish(string $matchId): array
    {
        try {
            return (new DartsGameService())->finish($matchId, $this->correlationId);
        } catch (GameNotFoundException $exception) {
            return $this->error(404, 'game_not_found', $exception->getMessage());
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            return $this->error(500, 'game_finish_failed', 'Could not finish game.');
        }
    }

    public function actionThrows(string $matchId): array
    {
        $idempotencyKey = $this->idempotencyKey();
        if ($idempotencyKey === null) {
            return $this->error(400, 'invalid_idempotency_key', 'Idempotency-Key must be a UUID.');
        }
        $body = $this->jsonBody();
        if (!is_array($body)
            || !is_string($body['deviceId'] ?? null)
            || $body['deviceId'] === ''
            || strlen($body['deviceId']) > 64
            || filter_var($body['sector'] ?? null, FILTER_VALIDATE_INT) === false
            || filter_var($body['multiplier'] ?? null, FILTER_VALIDATE_INT) === false
        ) {
            return $this->error(400, 'invalid_throw', 'JSON must contain deviceId, integer sector and integer multiplier.');
        }
        $sector = (int) $body['sector'];
        $multiplier = (int) $body['multiplier'];
        try {
            (new DartsThrowCalculator())->calculate($sector, $multiplier);
            return (new DartsGameService())->recordThrow(
                $matchId,
                $body['deviceId'],
                $sector,
                $multiplier,
                $idempotencyKey,
                $this->correlationId,
            );
        } catch (GameNotFoundException $exception) {
            return $this->error(404, 'game_not_found', $exception->getMessage());
        } catch (GameConflictException $exception) {
            return $this->error(409, 'game_conflict', $exception->getMessage());
        } catch (DomainException $exception) {
            return $this->error(422, 'invalid_throw', $exception->getMessage());
        } catch (\Throwable $exception) {
            Yii::error($exception, __METHOD__);
            return $this->error(500, 'throw_registration_failed', 'Could not register throw.');
        }
    }

    private function idempotencyKey(): ?string
    {
        $key = Yii::$app->request->headers->get('Idempotency-Key', '');
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $key,
        ) === 1 ? strtolower($key) : null;
    }

    /** @return array<string, mixed>|null */
    private function jsonBody(): ?array
    {
        try {
            $decoded = json_decode(Yii::$app->request->getRawBody(), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }

    /** @return array{error: array{code: string, message: string, correlationId: string}} */
    private function error(int $status, string $code, string $message): array
    {
        $this->setError($status, $code, $message);
        return ['error' => ['code' => $code, 'message' => $message, 'correlationId' => $this->correlationId]];
    }

    private function setError(int $status, string $code, string $message): void
    {
        Yii::$app->response->statusCode = $status;
        Yii::$app->response->data = [
            'error' => ['code' => $code, 'message' => $message, 'correlationId' => $this->correlationId],
        ];
    }

    private function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
