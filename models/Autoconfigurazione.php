<?php

declare(strict_types=1);

namespace app\models;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use yii\db\ActiveRecord;
use yii\db\IntegrityException;

/**
 * Configurazione persistente dell'applicazione.
 *
 * @property int $id
 * @property string $nome
 * @property string|null $valore
 * @property string|null $descrizione
 */
class Autoconfigurazione extends ActiveRecord
{
    public const THEME_LIGHT_COLOR = 'tema.colore_diurno';

    public const THEME_DARK_COLOR = 'tema.colore_notturno';

    public static function tableName(): string
    {
        return '{{%sys_autoconfigurazione}}';
    }

    public function rules(): array
    {
        return [
            [['nome'], 'required'],
            [['nome'], 'trim'],
            [['nome'], 'string', 'max' => 255],
            [['nome'], 'unique'],
            [['valore', 'descrizione'], 'string'],
        ];
    }

    public static function getValueAsString(string $nome, ?string $valoreDefault = null): ?string
    {
        return self::getOrCreate($nome, $valoreDefault)->valore;
    }

    public static function setValueAsString(string $nome, ?string $valore): void
    {
        $configurazione = self::getOrCreate($nome, null);
        $configurazione->valore = $valore;

        if (!$configurazione->save()) {
            throw new RuntimeException(sprintf(
                'Impossibile aggiornare la configurazione "%s": %s',
                $configurazione->nome,
                json_encode($configurazione->getFirstErrors(), JSON_THROW_ON_ERROR),
            ));
        }
    }

    public static function getValueAsBoolean(string $nome, ?bool $valoreDefault = null): ?bool
    {
        $value = self::getOrCreate(
            $nome,
            $valoreDefault === null ? null : ($valoreDefault ? '1' : '0'),
        )->valore;

        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public static function getValueAsInteger(string $nome, ?int $valoreDefault = null): ?int
    {
        $value = self::getOrCreate(
            $nome,
            $valoreDefault === null ? null : (string) $valoreDefault,
        )->valore;

        if ($value === null || preg_match('/\A[+-]?\d+\z/', $value) !== 1) {
            return null;
        }

        return (int) $value;
    }

    public static function getValueAsFloat(string $nome, ?float $valoreDefault = null): ?float
    {
        $value = self::getOrCreate(
            $nome,
            $valoreDefault === null ? null : (string) $valoreDefault,
        )->valore;

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    /**
     * @return array<mixed>|null
     */
    public static function getValueAsArray(string $nome, ?array $valoreDefault = null): ?array
    {
        $value = self::getOrCreate(
            $nome,
            $valoreDefault === null ? null : json_encode($valoreDefault, JSON_THROW_ON_ERROR),
        )->valore;

        if ($value === null) {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private static function getOrCreate(string $nome, ?string $valoreDefault): static
    {
        $nome = self::normaliseName($nome);

        $configurazione = static::findOne(['nome' => $nome]);
        if ($configurazione !== null) {
            return $configurazione;
        }

        $configurazione = new static([
            'nome' => $nome,
            'valore' => $valoreDefault,
        ]);

        try {
            if ($configurazione->save()) {
                return $configurazione;
            }
        } catch (IntegrityException $exception) {
            $configurazione = static::findOne(['nome' => $nome]);
            if ($configurazione !== null) {
                return $configurazione;
            }

            throw $exception;
        }

        throw new RuntimeException(sprintf(
            'Impossibile creare la configurazione "%s": %s',
            $nome,
            json_encode($configurazione->getFirstErrors(), JSON_THROW_ON_ERROR),
        ));
    }

    private static function normaliseName(string $nome): string
    {
        $nome = trim($nome);
        if ($nome === '' || mb_strlen($nome) > 255) {
            throw new InvalidArgumentException('Il nome della configurazione deve contenere da 1 a 255 caratteri.');
        }

        return $nome;
    }
}
