<?php

use yii\db\Migration;

class m260516_100000_create_profiles_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('profiles', [
            'id'          => $this->primaryKey(),
            'description' => $this->string(50)->notNull(),
            'level'       => $this->integer()->notNull(),
            'resource'    => $this->string(50)->notNull(),
        ]);

        $this->batchInsert('profiles', ['description', 'level', 'resource'], [
            ['Webmaster',      100, 'webmaster'],
            ['Administrator',   80, 'administrator'],
            ['Assistant',       60, 'assistant'],
            ['Employee',        40, 'employee'],
            ['User',            20, 'user'],
        ]);
    }

    public function safeDown()
    {
        $this->dropTable('profiles');
    }
}
