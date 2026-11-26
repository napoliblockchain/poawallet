<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%vapid}}`.
 */
class m210415_085447_create_vapid_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%vapid}}', [
            'id' => $this->primaryKey(),
            'public_key' => $this->string(255)->notNull(),
            'secret_key' => $this->string(255)->notNull(),
        ]);

        // VAPID keys are no longer stored in the database: see VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY in .env
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%vapid}}');
    }
}
