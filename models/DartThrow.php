<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/** Tiro di freccette; "throw" è una parola riservata in PHP 8. */
/**
 * @property int $id
 * @property int $match_id
 * @property int $user_id
 * @property int $sector
 * @property int $multiplier
 * @property int $score
 */
class DartThrow extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_throws}}';
    }

    public function rules(): array
    {
        return [
            [['match_id', 'user_id', 'sector', 'multiplier', 'score'], 'required'],
            [['match_id', 'user_id', 'sector', 'multiplier', 'score'], 'integer'],
            [['sector'], 'in', 'range' => [0, ...range(1, 20), 25]],
            [['multiplier'], 'integer', 'min' => 1, 'max' => 3],
            [['sector'], 'validateSectorMultiplier'],
            [['score'], 'integer', 'min' => 0],
            [['created_at'], 'safe'],
            [['match_id'], 'exist', 'skipOnError' => true, 'targetClass' => GameMatch::class, 'targetAttribute' => ['match_id' => 'id']],
            [['user_id'], 'exist', 'skipOnError' => true, 'targetClass' => User::class, 'targetAttribute' => ['user_id' => 'id']],
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

    public function validateSectorMultiplier(string $attribute): void
    {
        if ((int) $this->sector === 0 && (int) $this->multiplier !== 1) {
            $this->addError('multiplier', 'Una freccetta mancata vale zero e non ha moltiplicatore.');
        }

        if ((int) $this->sector === 25 && (int) $this->multiplier === 3) {
            $this->addError('multiplier', 'Il bull vale 25 (singolo) o 50 (doppio); non esiste il triplo bull.');
        }
    }
}
