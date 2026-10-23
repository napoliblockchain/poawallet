<?php

use yii\db\Migration;

/**
 * Removes the VAPID keys stored in the database.
 * The old key pair was published in the repository and must not be used anymore:
 * keys now come from VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY in .env.
 */
class m261023_100000_clear_vapid_keys extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->delete('{{%vapid}}');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // compromised keys are not restored
        return true;
    }
}
