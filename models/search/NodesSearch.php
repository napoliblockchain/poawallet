<?php

namespace app\models\search;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Nodes;

/**
 * NodesSearch represents the model behind the search form of app\models\Nodes.
 */
class NodesSearch extends Nodes
{
    public function rules()
    {
        return [
            [['id', 'id_user', 'id_blockchain', 'id_smart_contract'], 'integer'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search($params)
    {
        $query = Nodes::find();

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'id_user' => $this->id_user,
            'id_blockchain' => $this->id_blockchain,
            'id_smart_contract' => $this->id_smart_contract,
        ]);

        return $dataProvider;
    }
}
