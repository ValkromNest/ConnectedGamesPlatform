<?php

declare(strict_types=1);

use yii\db\Migration;

final class m261014_120000_create_darts_game_tables extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%darts_game_match}}', [
            'match_id' => $this->string(64)->notNull(),
            'status' => $this->string(16)->notNull(),
            'starting_score' => $this->smallInteger()->notNull()->defaultValue(501),
            'context_hash' => $this->char(64)->notNull(),
            'context_request_key' => $this->char(36)->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'finished_at' => $this->dateTime()->null(),
            'PRIMARY KEY ([[match_id]])',
        ]);
        $this->createIndex(
            'uq_darts_game_match_context_key',
            '{{%darts_game_match}}',
            ['context_request_key'],
            true,
        );

        $this->createTable('{{%darts_game_player}}', [
            'id' => $this->primaryKey(),
            'match_id' => $this->string(64)->notNull(),
            'player_id' => $this->string(64)->notNull(),
            'display_name' => $this->string(120)->notNull(),
            'turn_order' => $this->smallInteger()->notNull(),
            'remaining_score' => $this->smallInteger()->notNull()->defaultValue(501),
        ]);
        $this->createIndex(
            'uq_darts_game_player_match_player',
            '{{%darts_game_player}}',
            ['match_id', 'player_id'],
            true,
        );
        $this->createIndex(
            'uq_darts_game_player_match_order',
            '{{%darts_game_player}}',
            ['match_id', 'turn_order'],
            true,
        );
        $this->addForeignKey(
            'fk_darts_game_player_match',
            '{{%darts_game_player}}',
            'match_id',
            '{{%darts_game_match}}',
            'match_id',
            'CASCADE',
            'RESTRICT',
        );

        $this->createTable('{{%darts_game_throw}}', [
            'id' => $this->bigPrimaryKey(),
            'match_id' => $this->string(64)->notNull(),
            'player_id' => $this->string(64)->notNull(),
            'device_id' => $this->string(64)->notNull(),
            'sector' => $this->tinyInteger()->notNull(),
            'multiplier' => $this->tinyInteger()->notNull(),
            'score' => $this->smallInteger()->notNull(),
            'result' => $this->string(16)->notNull(),
            'idempotency_key' => $this->char(36)->notNull(),
            'request_hash' => $this->char(64)->notNull(),
            'response_json' => $this->text()->null(),
            'created_at' => $this->dateTime()->notNull(),
        ]);
        $this->createIndex(
            'uq_darts_game_throw_match_idempotency',
            '{{%darts_game_throw}}',
            ['match_id', 'idempotency_key'],
            true,
        );
        $this->createIndex(
            'ix_darts_game_throw_match_id',
            '{{%darts_game_throw}}',
            ['match_id', 'id'],
        );
        $this->addForeignKey(
            'fk_darts_game_throw_player',
            '{{%darts_game_throw}}',
            ['match_id', 'player_id'],
            '{{%darts_game_player}}',
            ['match_id', 'player_id'],
            'CASCADE',
            'RESTRICT',
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%darts_game_throw}}');
        $this->dropTable('{{%darts_game_player}}');
        $this->dropTable('{{%darts_game_match}}');
    }
}
