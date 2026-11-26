<?php

use yii\db\Migration;

class m260516_100002_create_logs_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('logs', [
            'id'             => $this->primaryKey(),
            'timestamp'      => $this->integer()->notNull(),
            'remote_address' => $this->string(45)->notNull(),
            'browser'        => $this->string(255)->notNull(),
            'description'    => $this->text()->notNull(),
        ]);

        $this->createIndex('idx_logs_timestamp', 'logs', 'timestamp');
    }

    public function safeDown()
    {
        $this->dropTable('logs');
    }
}
