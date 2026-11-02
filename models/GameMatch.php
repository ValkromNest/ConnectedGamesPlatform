<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use app\enums\MatchStatus;

/**
 * Partita persistita.
 *
 * @property int $id
 * @property int $game_id
 * @property string $name
 * @property int $status
 * @property int $max_players
 * @property int|null $device_id
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

    public function getDevice(): ActiveQuery
    {
        return $this->hasOne(Device::class, ['id' => 'device_id']);
    }
    public function getMatchPlayers(): ActiveQuery
    {
        return $this->hasMany(MatchPlayer::class, ['match_id' => 'id']);
    }
    /** Transizioni amministrative ammesse, con aggiornamento atomico dello stato. */
    public function transitionTo(MatchStatus $next): bool
    {
        $current = MatchStatus::tryFrom((int) $this->status);
        $allowed = match ($current) {
            MatchStatus::CREATED => [MatchStatus::OPEN],
            MatchStatus::OPEN => [MatchStatus::RUNNING, MatchStatus::FINISHED],
            MatchStatus::RUNNING => [MatchStatus::FINISHED],
            default => [],
        };

        if (!in_array($next, $allowed, true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $attributes = ['status' => $next->value];
        if ($next === MatchStatus::RUNNING) {
            $attributes['started_at'] = $now;
        } elseif ($next === MatchStatus::FINISHED) {
            $attributes['ended_at'] = $now;
        }

        $changed = static::updateAll($attributes, ['id' => $this->id, 'status' => $current->value]);
        if ($changed !== 1) {
            return false;
        }

        $this->refresh();
        return true;
    }

    /** @return MatchPlayer[] */
    public function getOrderedPlayers(): array
    {
        return $this->getMatchPlayers()->orderBy(['turn_order' => SORT_ASC, 'id' => SORT_ASC])->all();
    }

    public function reloadState(): bool
    {
        return $this->refresh();
    }
}
