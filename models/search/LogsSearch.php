<?php

namespace app\models\search;

use yii\base\Model;
use yii\data\ActiveDataProvider;
use app\models\Logs;

class LogsSearch extends Logs
{
    public function rules()
    {
        return [
            [['id', 'timestamp'], 'integer'],
            [['remote_address', 'browser', 'description'], 'safe'],
        ];
    }

    public function scenarios()
    {
        return Model::scenarios();
    }

    public function search(array $params): ActiveDataProvider
    {
        $query = Logs::find();

        $dataProvider = new ActiveDataProvider(['query' => $query]);

        $this->load($params);
        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere(['id' => $this->id, 'timestamp' => $this->timestamp])
              ->andFilterWhere(['like', 'remote_address', $this->remote_address])
              ->andFilterWhere(['like', 'browser', $this->browser])
              ->andFilterWhere(['like', 'description', $this->description]);

        return $dataProvider;
    }
}
