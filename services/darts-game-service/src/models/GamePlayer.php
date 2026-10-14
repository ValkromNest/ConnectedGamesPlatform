<?php

declare(strict_types=1);

namespace darts\models;

use yii\db\ActiveRecord;

final class GamePlayer extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%darts_game_player}}';
    }

    public function rules(): array
    {
        return [
            [['match_id', 'player_id', 'display_name', 'turn_order', 'remaining_score'], 'required'],
            [['match_id', 'player_id'], 'string', 'max' => 64],
            [['display_name'], 'string', 'max' => 120],
            [['turn_order'], 'integer', 'min' => 1],
            [['remaining_score'], 'integer', 'min' => 0, 'max' => 501],
        ];
    }
}
