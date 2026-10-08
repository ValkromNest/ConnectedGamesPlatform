<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * Partita persistita; "match" è una parola riservata in PHP 8.
 *
 * @property int $id
 * @property int $game_id
 * @property string $name
 * @property int $status
 * @property int $max_players
 * @property string|null $created_at
 * @property string|null $started_at
 * @property string|null $ended_at
 */
class GameMatch extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_matches}}';
    }

    public function rules(): array
    {
        return [
            [['game_id', 'name'], 'required', 'on' => self::SCENARIO_DEFAULT],
            [['game_id', 'status', 'max_players'], 'integer'],
            [['game_id'], 'exist', 'skipOnError' => true, 'targetClass' => Game::class, 'targetAttribute' => ['game_id' => 'id']],
            [['name'], 'string', 'max' => 150],
            [['status'], 'in', 'range' => range(0, 3)],
            [['max_players'], 'integer', 'min' => 1, 'max' => 2],
            [['created_at', 'started_at', 'ended_at'], 'safe'],
        ];
    }

    public function getGame(): ActiveQuery
    {
        return $this->hasOne(Game::class, ['id' => 'game_id']);
    }
    public function getMatchPlayers(): ActiveQuery
    {
        return $this->hasMany(MatchPlayer::class, ['match_id' => 'id']);
    }
    public function getThrows(): ActiveQuery
    {
        return $this->hasMany(DartThrow::class, ['match_id' => 'id']);
    }
}
