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
 * @property-read Game|null $game
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
    /** @return array<int, string> */
    public static function availableForGame(int $gameId, ?int $currentDeviceId = null): array
    {
        $query = Device::find()
            ->select('name')
            ->where(['game_id' => $gameId]);

        if ($currentDeviceId === null) {
            $query->andWhere(['is_enabled' => true]);
        } else {
            $query->andWhere(['or', ['is_enabled' => true], ['id' => $currentDeviceId]]);
        }

        return $query->indexBy('id')->column();
    }

    public static function findEnabledDarts(): array
    {
        return static::find()
            ->joinWith('game')
            ->where(['{{%tbl_device}}.is_enabled' => true, '{{%tbl_game}}.code' => DartsMatch::GAME_CODE])
            ->orderBy(['{{%tbl_device}}.name' => SORT_ASC])
            ->all();
    }

    public static function findEnabledDartsById(int $id): ?self
    {
        return static::find()
            ->joinWith('game')
            ->where([
                '{{%tbl_device}}.id' => $id,
                '{{%tbl_device}}.is_enabled' => true,
                '{{%tbl_game}}.code' => DartsMatch::GAME_CODE,
            ])
            ->one();
    }

    public static function listDataProvider(): \yii\data\ActiveDataProvider
    {
        return new \yii\data\ActiveDataProvider([
            'query' => Device::find()->with('game')->orderBy(['name' => SORT_ASC]),
            'pagination' => ['pageSize' => 20],
        ]);
    }

    public static function findById(int $id): ?self
    {
        return static::findOne($id);
    }

    public function saveDetails(): bool
    {
        return $this->save();
    }
}
