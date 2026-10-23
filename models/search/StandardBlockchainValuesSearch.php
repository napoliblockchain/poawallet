<?php

namespace app\models\search;

use app\models\StandardBlockchainValues;
use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * StandardBlockchainValuesSearch represents search model for StandardBlockchainValues.
 */
class StandardBlockchainValuesSearch extends StandardBlockchainValues
{
    public function rules()
    {
        return [
            [['id', 'zerogas'], 'integer'],
            [['denomination', 'chain_id', 'url', 'symbol', 'url_block_explorer'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = StandardBlockchainValues::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'zerogas' => $this->zerogas,
        ]);

        $query->andFilterWhere(['like', 'denomination', $this->denomination])
            ->andFilterWhere(['like', 'chain_id', $this->chain_id])
            ->andFilterWhere(['like', 'url', $this->url])
            ->andFilterWhere(['like', 'symbol', $this->symbol])
            ->andFilterWhere(['like', 'url_block_explorer', $this->url_block_explorer]);

        return $dataProvider;
    }
}
