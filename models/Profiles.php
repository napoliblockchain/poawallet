<?php

namespace app\models;

use Yii;

/**
 * @property int $id
 * @property string $description
 * @property int $level
 * @property string $resource
 */
class Profiles extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'profiles';
    }

    public function rules()
    {
        return [
            [['description', 'level', 'resource'], 'required'],
            [['level'], 'integer'],
            [['description', 'resource'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'          => Yii::t('app', 'ID'),
            'description' => Yii::t('app', 'Description'),
            'level'       => Yii::t('app', 'Level'),
            'resource'    => Yii::t('app', 'Resource'),
        ];
    }

    public static function find()
    {
        return new \app\models\query\ProfilesQuery(get_called_class());
    }

    public function translate(): string
    {
        $map = [
            'Webmaster'     => Yii::t('app', 'Webmaster'),
            'Administrator' => Yii::t('app', 'Administrator'),
            'Assistant'     => Yii::t('app', 'Assistant'),
            'Employee'      => Yii::t('app', 'Employee'),
            'User'          => Yii::t('app', 'User'),
        ];
        return $map[$this->description] ?? $this->description;
    }
}
