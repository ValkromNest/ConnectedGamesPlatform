<?php

declare(strict_types=1);

namespace darts\models;

use yii\db\ActiveRecord;

final class GameThrow extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%darts_game_throw}}';
    }

    public function rules(): array
    {
        return [
            [['match_id', 'player_id', 'device_id', 'sector', 'multiplier', 'score', 'result', 'idempotency_key', 'request_hash', 'created_at'], 'required'],
            [['match_id', 'player_id', 'device_id'], 'string', 'max' => 64],
            [['sector'], 'integer', 'min' => 0, 'max' => 25],
            [['multiplier'], 'integer', 'min' => 1, 'max' => 3],
            [['score'], 'integer', 'min' => 0, 'max' => 60],
            [['result'], 'in', 'range' => ['scored', 'missed', 'bust', 'checkout']],
            [['idempotency_key'], 'string', 'length' => 36],
            [['request_hash'], 'string', 'length' => 64],
            [['created_at', 'response_json'], 'safe'],
        ];
    }
}
