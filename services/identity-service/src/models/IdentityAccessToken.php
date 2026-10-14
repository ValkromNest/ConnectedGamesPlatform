<?php

declare(strict_types=1);

namespace identity\models;

use yii\db\ActiveRecord;

/** @property int $id @property int $identity_user_id @property string $token_hash @property string $created_at @property string $expires_at @property string|null $revoked_at */
final class IdentityAccessToken extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%tbl_identity_access_token}}';
    }

    public function rules(): array
    {
        return [
            [['identity_user_id', 'token_hash', 'created_at', 'expires_at'], 'required'],
            [['identity_user_id'], 'integer'],
            [['created_at', 'expires_at', 'revoked_at'], 'safe'],
            [['token_hash'], 'string', 'length' => 64],
            [['token_hash'], 'unique'],
        ];
    }
}
