<?php

declare(strict_types=1);

use yii\db\Migration;

final class m261014_150000_create_identity_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%tbl_identity_user}}', [
            'id' => $this->primaryKey(),
            'subject_id' => $this->char(36)->notNull(),
            'legacy_user_id' => $this->integer()->null(),
            'username' => $this->string(100)->notNull(),
            'password_hash' => $this->string(255)->notNull(),
            'role' => $this->string(20)->notNull(),
            'is_enabled' => $this->boolean()->notNull()->defaultValue(true),
            'failed_login_attempts' => $this->smallInteger()->notNull()->defaultValue(0),
            'locked_until' => $this->dateTime()->null(),
            'created_at' => $this->dateTime()->notNull(),
            'last_login_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex('uq_identity_user_subject', '{{%tbl_identity_user}}', 'subject_id', true);
        $this->createIndex('uq_identity_user_legacy_id', '{{%tbl_identity_user}}', 'legacy_user_id', true);
        $this->createIndex('uq_identity_user_username', '{{%tbl_identity_user}}', 'username', true);

        $this->createTable('{{%tbl_identity_access_token}}', [
            'id' => $this->bigPrimaryKey(),
            'identity_user_id' => $this->integer()->notNull(),
            'token_hash' => $this->char(64)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'expires_at' => $this->dateTime()->notNull(),
            'revoked_at' => $this->dateTime()->null(),
        ]);
        $this->createIndex('uq_identity_access_token_hash', '{{%tbl_identity_access_token}}', 'token_hash', true);
        $this->createIndex('ix_identity_access_user_expiry', '{{%tbl_identity_access_token}}', ['identity_user_id', 'expires_at']);
        $this->addForeignKey(
            'fk_identity_access_user',
            '{{%tbl_identity_access_token}}',
            'identity_user_id',
            '{{%tbl_identity_user}}',
            'id',
            'CASCADE',
            'RESTRICT',
        );

        $this->createTable('{{%tbl_identity_login_attempt}}', [
            'id' => $this->primaryKey(),
            'source_hash' => $this->char(64)->notNull(),
            'window_started_at' => $this->dateTime()->notNull(),
            'failed_attempts' => $this->smallInteger()->notNull()->defaultValue(0),
            'locked_until' => $this->dateTime()->null(),
        ]);
        $this->createIndex('uq_identity_login_attempt_source', '{{%tbl_identity_login_attempt}}', 'source_hash', true);
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%tbl_identity_access_token}}');
        $this->dropTable('{{%tbl_identity_login_attempt}}');
        $this->dropTable('{{%tbl_identity_user}}');
    }
}
