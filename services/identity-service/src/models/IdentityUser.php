<?php

declare(strict_types=1);

namespace identity\models;

use yii\db\ActiveRecord;

/** @property int $id @property string $subject_id @property int|null $legacy_user_id @property string $username @property string $password_hash @property string $role @property int $is_enabled @property int $failed_login_attempts @property string|null $locked_until @property string $created_at @property string|null $last_login_at */
final class IdentityUser extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_identity_user}}';
    }

    public function rules(): array
    {
        return [
            [['subject_id', 'username', 'password_hash', 'role', 'created_at'], 'required'],
            [['is_enabled', 'failed_login_attempts'], 'integer'],
            [['locked_until', 'last_login_at'], 'safe'],
            [['subject_id'], 'string', 'length' => 36],
            [['legacy_user_id'], 'integer'],
            [['username'], 'string', 'max' => 100],
            [['password_hash'], 'string', 'max' => 255],
            [['role'], 'in', 'range' => ['admin', 'player']],
            [['subject_id', 'username'], 'unique'],
        ];
    }
}
