<?php

namespace app\models\search;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Profiles;

class ProfilesSearch extends Profiles
{
    public function rules()
    {
        return [
            [['id', 'level'], 'integer'],
            [['description', 'resource'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = Profiles::find();

        $dataProvider = new ActiveDataProvider(['query' => $query]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['id' => $this->id, 'level' => $this->level])
              ->andFilterWhere(['like', 'description', $this->description])
              ->andFilterWhere(['like', 'resource', $this->resource]);

        return $dataProvider;
    }
}
