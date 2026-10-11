<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Dispositivo di gioco autorizzabile a comunicare con il server.
 *
 * @property int $id
 * @property string $name
 * @property int $game_id
 * @property string $token_hash
 * @property string|null $token_encrypted
 * @property bool $is_enabled
 */
class Device extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_device}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'game_id', 'token_hash'], 'required'],
            [['name'], 'trim'],
            [['name'], 'string', 'max' => 100],
            [['name'], 'unique'],
            [['game_id'], 'integer'],
            [['game_id'], 'exist', 'skipOnError' => true, 'targetClass' => Game::class, 'targetAttribute' => ['game_id' => 'id']],
            [['token_hash'], 'string', 'length' => 64],
            [['token_hash'], 'unique'],
            [['is_enabled'], 'boolean'],
        ];
    }

    public function getGame(): ActiveQuery
    {
        return $this->hasOne(Game::class, ['id' => 'game_id']);
    }

    public static function findEnabledByToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }

        return static::find()->where([
            'token_hash' => hash('sha256', $token),
            'is_enabled' => true,
        ])->one();
    }
}
