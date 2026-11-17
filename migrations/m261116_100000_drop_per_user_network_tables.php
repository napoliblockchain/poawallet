<?php

use yii\db\Migration;

/**
 * SIP-032 passo 3: rete e token arrivano dalle variabili d'ambiente (ChainConfig), le righe per utente non servono
 * più. Il vincolo transactions -> smart_contract va tolto per primo: con ON DELETE CASCADE eliminerebbe le transazioni.
 * Anche ethtxs/ethtxs_status (vecchio indicizzatore PHP) sono sostituite dall'indicizzatore Go.
 */
class m261116_100000_drop_per_user_network_tables extends Migration
{
    private const TABLES = ['nodes', 'smart_contract', 'standard_smart_contract_values', 'standard_blockchain_values', 'blockchains', 'contract_type', 'ethtxs_status', 'ethtxs'];

    public function safeUp()
    {
        $transactions = $this->db->getTableSchema('{{%transactions}}', true);
        if ($transactions !== null && $transactions->getColumn('id_smart_contract') !== null) {
            foreach ($transactions->foreignKeys as $name => $reference) {
                if (array_key_exists('id_smart_contract', $reference)) $this->dropForeignKey($name, '{{%transactions}}');
            }
            $this->dropColumn('{{%transactions}}', 'id_smart_contract');
        }
        foreach (self::TABLES as $table) {
            if ($this->db->getTableSchema('{{%' . $table . '}}', true) !== null) $this->dropTable('{{%' . $table . '}}');
        }
    }

    public function safeDown()
    {
        echo "m261116_100000_drop_per_user_network_tables cannot be reverted.\n";
        return false;
    }
}
