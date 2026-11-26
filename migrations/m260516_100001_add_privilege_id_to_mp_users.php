<?php

use yii\db\Migration;

class m260516_100001_add_privilege_id_to_mp_users extends Migration
{
    public function safeUp()
    {
        $this->addColumn('mp_users', 'privilege_id', $this->integer()->notNull()->defaultValue(5)->after('first_name'));
        $this->addForeignKey('fk_mp_users_privilege', 'mp_users', 'privilege_id', 'profiles', 'id');
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_mp_users_privilege', 'mp_users');
        $this->dropColumn('mp_users', 'privilege_id');
    }
}
