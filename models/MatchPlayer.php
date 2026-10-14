<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $match_id
 * @property string $identity_subject_id
 * @property string $display_name
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
            [['match_id', 'identity_subject_id', 'display_name'], 'required'],
            [['match_id', 'score', 'turn_order'], 'integer'],
            [['identity_subject_id'], 'string', 'max' => 64],
            [['display_name'], 'string', 'max' => 120],
            [['score'], 'integer', 'min' => 0],
            [['turn_order'], 'integer', 'min' => 1],
            [['match_id'], 'exist', 'skipOnError' => true, 'targetClass' => GameMatch::class, 'targetAttribute' => ['match_id' => 'id']],
            [['match_id', 'identity_subject_id'], 'unique', 'targetAttribute' => ['match_id', 'identity_subject_id']],
        ];
    }

    public function getGameMatch(): ActiveQuery
    {
        return $this->hasOne(GameMatch::class, ['id' => 'match_id']);
    }
}
