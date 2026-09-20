<?php

namespace app\models;

use yii\db\ActiveRecord;

class Game extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%game}}';
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
}