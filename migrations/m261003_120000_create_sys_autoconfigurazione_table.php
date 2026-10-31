<?php

declare(strict_types=1);

use yii\db\Migration;

// Yii richiede che il nome della classe coincida con il nome della migration.
// phpcs:ignore Squiz.Classes.ValidClassName.NotPascalCase
final class m261003_120000_create_sys_autoconfigurazione_table extends Migration
{
    public function safeUp(): void
    {
        $this->createTable('{{%sys_autoconfigurazione}}', [
            'id' => $this->primaryKey(),
            'nome' => $this->string(255)->notNull(),
            'valore' => 'LONGTEXT NULL',
            'descrizione' => 'LONGTEXT NULL',
        ]);

        $this->createIndex(
            'ux_sys_autoconfigurazione_nome',
            '{{%sys_autoconfigurazione}}',
            'nome',
            true,
        );
    }

    public function safeDown(): void
    {
        $this->dropTable('{{%sys_autoconfigurazione}}');
    }
}
