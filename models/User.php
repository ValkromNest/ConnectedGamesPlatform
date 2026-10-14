<?php

declare(strict_types=1);

namespace app\models;

use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * @property int $id
 * @property string $username
 * @property string $password_hash
 * @property string|null $auth_key
 * @property string $role
 * @property string $created_at
 * @property-read string $passwordHash
 */
class User extends ActiveRecord implements IdentityInterface
{
    public static function tableName(): string
    {
        return '{{%tbl_user}}';
    }

    public function rules(): array
    {
        return [
            [['username', 'password_hash'], 'required'],
            [['username'], 'string', 'max' => 100],
            [['password_hash', 'auth_key'], 'string', 'max' => 255],
            [['role'], 'string', 'max' => 30],
            [['created_at'], 'safe'],
            [['username'], 'unique'],
        ];
    }

    public static function findIdentity($id): static|null
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null): static|null
    {
        // Non esiste un token applicativo nello schema attuale.
        return null;
    }

    public static function findByUsername(string $username): static|null
    {
        return static::find()->where(['username' => $username])->one();
    }

    public function getId(): int|string
    {
        return $this->id ?? '';
    }

    public function getAuthKey(): string|null
    {
        return $this->auth_key;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->auth_key !== null && hash_equals($this->auth_key, (string) $authKey);
    }

    public function getPasswordHash(): string
    {
        return (string) $this->password_hash;
    }

    public function isAdministrator(): bool
    {
        return strcasecmp($this->role, 'admin') === 0;
    }
}
