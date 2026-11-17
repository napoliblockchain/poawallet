<?php

namespace app\models\query;

class ProfilesQuery extends \yii\db\ActiveQuery
{
    public function byResource(string $resource): self
    {
        return $this->andWhere(['resource' => $resource]);
    }

    public function byLevelLessThen(int $level): self
    {
        return $this->andWhere(['<=', 'level', $level]);
    }

    public function all($db = null)
    {
        return parent::all($db);
    }

    public function one($db = null)
    {
        return parent::one($db);
    }
}
