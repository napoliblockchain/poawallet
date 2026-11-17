<?php

use yii\db\Migration;

/**
 * SIP-029: cursore dell'indicizzatore wallet-realtime (ultimo blocco letto per chain e contratto)
 * e indice sull'hash delle transazioni, cercato a ogni evento Transfer.
 */
class m261115_200000_create_chain_index_cursors extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%chain_index_cursors}}', [
            'chain_id' => $this->string(20)->notNull(),
            'contract' => 'CHAR(42) CHARACTER SET ascii COLLATE ascii_bin NOT NULL',
            'last_block' => $this->bigInteger()->unsigned()->notNull(),
            'updated_at' => $this->integer()->notNull(),
            'PRIMARY KEY (chain_id, contract)',
        ], 'CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci ENGINE=InnoDB');
        $this->createIndex('idx-transactions-txhash', '{{%transactions}}', 'txhash');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-transactions-txhash', '{{%transactions}}');
        $this->dropTable('{{%chain_index_cursors}}');
    }
}
