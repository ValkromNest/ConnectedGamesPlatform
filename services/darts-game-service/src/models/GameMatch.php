<?php

declare(strict_types=1);

namespace darts\models;

use yii\db\ActiveRecord;

final class GameMatch extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%darts_game_match}}';
    }

    public static function primaryKey(): array
    {
        return ['match_id'];
    }

    public function rules(): array
    {
        return [
            [['match_id', 'status', 'starting_score', 'context_hash', 'context_request_key', 'created_at'], 'required'],
            [['match_id'], 'string', 'max' => 64],
            [['status'], 'in', 'range' => ['RUNNING', 'FINISHED']],
            [['starting_score'], 'integer', 'min' => 1],
            [['context_hash'], 'string', 'length' => 64],
            [['context_request_key'], 'string', 'length' => 36],
            [['created_at', 'finished_at'], 'safe'],
        ];
    }
}
