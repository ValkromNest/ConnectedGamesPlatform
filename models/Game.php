<?php

namespace app\models;

use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $enabled
 */
class Game extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_game}}';
    }

    public function rules(): array
    {
        return [
            [['name', 'code'], 'required'],
            [['enabled'], 'boolean'],
            [['name'], 'string', 'max' => 100],
            [['code'], 'string', 'max' => 50],
            [['code'], 'unique'],
        ];
    }

    public function getMatches(): ActiveQuery
    {
        return $this->hasMany(GameMatch::class, ['game_id' => 'id']);
    }
}
