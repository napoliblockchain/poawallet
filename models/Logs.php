<?php

namespace app\models;

use Yii;

/**
 * @property int $id
 * @property int $timestamp
 * @property string $remote_address
 * @property string $browser
 * @property string $description
 */
class Logs extends \yii\db\ActiveRecord
{
    public static function tableName()
    {
        return 'logs';
    }

    public function rules()
    {
        return [
            [['timestamp', 'remote_address', 'browser', 'description'], 'required'],
            [['timestamp'], 'integer'],
            [['description'], 'string'],
            [['remote_address'], 'string', 'max' => 45],
            [['browser'], 'string', 'max' => 255],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'             => Yii::t('app', 'ID'),
            'timestamp'      => Yii::t('app', 'Timestamp'),
            'remote_address' => Yii::t('app', 'Remote Address'),
            'browser'        => Yii::t('app', 'Browser'),
            'description'    => Yii::t('app', 'Description'),
        ];
    }

    public static function find()
    {
        return new \app\models\query\LogsQuery(get_called_class());
    }

    public static function write(string $description): void
    {
        $model = new self();
        $model->timestamp      = time();
        $model->remote_address = Yii::$app->request->userIP ?? '0.0.0.0';
        $model->browser        = Yii::$app->request->userAgent ?? 'unknown';
        $model->description    = $description;
        $model->save(false);
    }
}
