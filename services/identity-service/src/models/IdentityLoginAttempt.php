<?php

declare(strict_types=1);

namespace identity\models;

use yii\db\ActiveRecord;

/** @property int $id @property string $source_hash @property string $window_started_at @property int $failed_attempts @property string|null $locked_until */
final class IdentityLoginAttempt extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_identity_login_attempt}}';
    }

    public function rules(): array
    {
        return [
            [['source_hash', 'window_started_at', 'failed_attempts'], 'required'],
            [['failed_attempts'], 'integer'],
            [['window_started_at', 'locked_until'], 'safe'],
            [['source_hash'], 'string', 'length' => 64],
            [['source_hash'], 'unique'],
        ];
    }
}
