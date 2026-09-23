<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $match_id
 * @property int $user_id
 * @property int $score
 * @property int|null $turn_order
 */
class MatchPlayer extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_match_players}}';
    }

    public function rules(): array
    {
        return [
            [['match_id', 'user_id'], 'required'],
            [['match_id', 'user_id', 'score', 'turn_order'], 'integer'],
            [['score'], 'integer', 'min' => 0],
            [['turn_order'], 'integer', 'min' => 1],
            [['match_id'], 'exist', 'skipOnError' => true, 'targetClass' => GameMatch::class, 'targetAttribute' => ['match_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
            [['match_id', 'user_id'], 'unique', 'targetAttribute' => ['match_id', 'user_id']],
        ];
    }

    public function getGameMatch(): ActiveQuery
    {
        return $this->hasOne(GameMatch::class, ['id' => 'match_id']);
    }
    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
    public function getThrows(): ActiveQuery
    {
        return $this->hasMany(DartThrow::class, ['user_id' => 'user_id'])
            ->andWhere(['{{%tbl_throws}}.match_id' => $this->match_id]);
    }
}
