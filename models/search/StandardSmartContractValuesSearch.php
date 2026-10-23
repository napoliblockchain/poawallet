<?php

namespace app\models\search;

use app\models\StandardSmartContractValues;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * StandardSmartContractValuesSearch represents search model for StandardSmartContractValues.
 */
class StandardSmartContractValuesSearch extends StandardSmartContractValues
{
    public function rules()
    {
        return [
            [['id', 'id_blockchain', 'id_contract_type', 'decimals'], 'integer'],
            [['denomination', 'smart_contract_address', 'symbol'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = StandardSmartContractValues::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'id_blockchain' => $this->id_blockchain,
            'id_contract_type' => $this->id_contract_type,
            'decimals' => $this->decimals,
        ]);

        $query->andFilterWhere(['like', 'denomination', $this->denomination])
            ->andFilterWhere(['like', 'smart_contract_address', $this->smart_contract_address])
            ->andFilterWhere(['like', 'symbol', $this->symbol]);

        return $dataProvider;
    }
}
