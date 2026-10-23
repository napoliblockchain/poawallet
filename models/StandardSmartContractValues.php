<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "standard_smart_contract_values".
 *
 * @property int $id
 * @property int $id_blockchain
 * @property int $id_contract_type
 * @property string $denomination
 * @property string $smart_contract_address
 * @property int $decimals
 * @property string $symbol
 *
 * @property StandardBlockchainValues $blockchainStandard
 * @property ContractType $contractType
 */
class StandardSmartContractValues extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'standard_smart_contract_values';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_blockchain', 'id_contract_type', 'denomination', 'smart_contract_address', 'decimals', 'symbol'], 'required'],
            [['id_blockchain', 'id_contract_type', 'decimals'], 'integer'],
            [['denomination', 'smart_contract_address', 'symbol'], 'string', 'max' => 255],
            [['id_blockchain'], 'exist', 'skipOnError' => true, 'targetClass' => StandardBlockchainValues::class, 'targetAttribute' => ['id_blockchain' => 'id']],
            [['id_contract_type'], 'exist', 'skipOnError' => true, 'targetClass' => ContractType::class, 'targetAttribute' => ['id_contract_type' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'id_blockchain' => Yii::t('app', 'Blockchain'),
            'id_contract_type' => Yii::t('app', 'Id Contract Type'),
            'denomination' => Yii::t('app', 'Denomination'),
            'smart_contract_address' => Yii::t('app', 'Smart Contract Address'),
            'decimals' => Yii::t('app', 'Decimals'),
            'symbol' => Yii::t('app', 'Symbol'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery|\app\models\query\StandardBlockchainValuesQuery
     */
    public function getBlockchainStandard()
    {
        return $this->hasOne(StandardBlockchainValues::class, ['id' => 'id_blockchain']);
    }

    /**
     * @return \yii\db\ActiveQuery|\app\models\query\ContractTypeQuery
     */
    public function getContractType()
    {
        return $this->hasOne(ContractType::class, ['id' => 'id_contract_type']);
    }

    /**
     * {@inheritdoc}
     * @return \app\models\query\StandardSmartContractValuesQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new \app\models\query\StandardSmartContractValuesQuery(get_called_class());
    }
}
